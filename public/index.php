<?php
session_start();

/* ====== CONFIG (via Render env vars) ====== */
$AZURE_ENDPOINT = rtrim(getenv('AZURE_ENDPOINT') ?: '', '/'); // ex: https://sbmchatbot.openai.azure.com
$AZURE_API_KEY  = getenv('AZURE_API_KEY');                     // ta clé Azure
$ASSISTANT_ID   = getenv('ASSISTANT_ID');                      // asst_WrGbhVrIqfqOqg5g1omyddBB
$THREADS_BASE   = $AZURE_ENDPOINT . "/openai/assistants/v1/threads";

if (!$AZURE_ENDPOINT || !$AZURE_API_KEY || !$ASSISTANT_ID) {
  http_response_code(500);
  echo "<pre>Configuration manquante. Vérifie AZURE_ENDPOINT, AZURE_API_KEY, ASSISTANT_ID dans Render.</pre>";
  exit;
}

/* ====== UI session ====== */
if (!isset($_SESSION['history'])) $_SESSION['history'] = [];

/* ====== Helpers HTTP ====== */
function http_call($method, $url, $headers, $payload = null) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 60,
  ]);
  if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  $res  = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($err) throw new Exception("cURL: $err");
  if ($code < 200 || $code >= 300) throw new Exception("HTTP $code: $res");
  $json = json_decode($res, true);
  if ($json === null && json_last_error() !== JSON_ERROR_NONE) throw new Exception("Invalid JSON");
  return $json;
}

$AZURE_HEADERS = [
  "api-key: $AZURE_API_KEY",
  "Content-Type: application/json"
];

/* ====== Reset ====== */
if (isset($_GET['reset'])) {
  session_destroy();
  header("Location: ".$_SERVER['PHP_SELF']);
  exit;
}

/* ====== Ensure one thread per session ====== */
function ensure_thread_id($threads_base, $headers) {
  if (!isset($_SESSION['azure_thread_id'])) {
    $create = http_call('POST', $threads_base, $headers, json_encode(['messages' => []]));
    $_SESSION['azure_thread_id'] = $create['id'];
  }
  return $_SESSION['azure_thread_id'];
}

/* ====== Handle POST (send message) ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
  $userMessage = trim($_POST['message']);
  if ($userMessage !== '') {
    $_SESSION['history'][] = ['role' => 'user', 'content' => $userMessage];

    try {
      $threadId = ensure_thread_id($THREADS_BASE, $AZURE_HEADERS);

      // 1) Ajouter le message utilisateur
      http_call('POST', "$THREADS_BASE/$threadId/messages", $AZURE_HEADERS, json_encode([
        "role"    => "user",
        "content" => $userMessage
      ]));

      // 2) Lancer le run avec l’assistant
      $run = http_call('POST', "$THREADS_BASE/$threadId/runs", $AZURE_HEADERS, json_encode([
        "assistant_id" => $ASSISTANT_ID,
        "instructions" => "Réponds uniquement à partir des fichiers SBM. Si l'info n'existe pas, dis-le poliment."
      ]));
      $runId  = $run['id'];
      $status = $run['status'] ?? '';

      // 3) Polling jusqu'à completion (backoff)
      $tries = 0;
      while ($status !== 'completed' && $status !== 'failed' && $status !== 'cancelled') {
        $tries++;
        usleep(300000 + min($tries * 150000, 1500000)); // 0.3s puis backoff
        $s = http_call('GET', "$THREADS_BASE/$threadId/runs/$runId", $AZURE_HEADERS);
        $status = $s['status'] ?? '';
        if ($tries > 80) throw new Exception("Timeout waiting for run.");
      }
      if ($status !== 'completed') throw new Exception("Run status: $status");

      // 4) Récupérer la dernière réponse d’assistant
      $list = http_call('GET', "$THREADS_BASE/$threadId/messages", $AZURE_HEADERS);
      $botReply = "Pas de réponse.";
      foreach ($list['data'] as $m) {
        if (($m['role'] ?? '') === 'assistant') {
          $c = $m['content'][0] ?? null;
          if ($c && isset($c['text']['value'])) { $botReply = $c['text']['value']; }
          break;
        }
      }

      $_SESSION['history'][] = ['role' => 'assistant', 'content' => $botReply];

    } catch (Exception $e) {
      $_SESSION['history'][] = ['role' => 'assistant', 'content' => "⚠️ Erreur : ".htmlspecialchars($e->getMessage())];
    }
  }

  header("Location: ".$_SERVER['PHP_SELF']); // éviter repost
  exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>SBM Chatbot</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{background:#121212;color:#fff;font-family:Arial,Helvetica,sans-serif;padding:20px;margin:0}
    .logo{display:block;margin:0 auto 20px;width:140px}
    .chat{display:flex;flex-direction:column;gap:10px;margin-bottom:10px}
    .bubble{padding:10px;border-radius:10px;margin:5px 0;max-width:80%;font-size:14px;word-wrap:break-word;white-space:pre-wrap}
    .user{background:#1e88e5;align-self:flex-end}
    .assistant{background:#2e2e2e;align-self:flex-start}
    .row{display:flex;gap:10px}
    input[type=text]{flex:1;padding:10px;border-radius:5px;border:none;font-size:14px}
    button{padding:10px 20px;background:#1e88e5;color:#fff;border:none;border-radius:5px;cursor:pointer}
    .reset-btn{margin-top:10px;background:#444}
    .bar{max-width:900px;margin:0 auto}
  </style>
</head>
<body>
  <img src="sbm_logo.png" alt="SBM Logo" class="logo">
  <div class="bar">
    <div class="chat">
      <?php foreach ($_SESSION['history'] as $msg): ?>
        <div class="bubble <?= $msg['role']==='user' ? 'user' : 'assistant' ?>"><?= htmlspecialchars($msg['content']) ?></div>
      <?php endforeach; ?>
    </div>
    <form method="post" class="row">
      <input type="text" name="message" placeholder="Pose une question..." required autocomplete="off">
      <button type="submit">Envoyer</button>
    </form>
    <form method="get">
      <button type="submit" name="reset" value="1" class="reset-btn">Réinitialiser</button>
    </form>
  </div>
  <script>window.scrollTo(0, document.body.scrollHeight);</script>
</body>
</html>



