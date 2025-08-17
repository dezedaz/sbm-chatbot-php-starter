<?php
session_start();

/* -------------------- CONFIG -------------------- */
$AZURE_ENDPOINT = "https://sbmchatbot.openai.azure.com";
$ASSISTANT_ID   = "asst_WrGbhVrIqfqOqg5g1omyddBB";

/* Clé : on prend la variable d'environnement si présente,
   sinon on utilise la clé que tu m'as fournie. */
$AZURE_API_KEY  = getenv("AZURE_API_KEY");
if (!$AZURE_API_KEY || trim($AZURE_API_KEY) === "") {
  $AZURE_API_KEY = "F4GkErnSxB1YyTAtIZ7aIwWirmAFTrClqALkVr2VhRvJWJxqPe32JQQJ99BHACYeBjFXJ3w3AAABACOGO0aZ";
}

/* -------------------- HELPERS -------------------- */
function azure_call($method, $path, $body = null, $apiKey = "", $endpoint = "") {
  $url = rtrim($endpoint, "/") . "/openai/assistants/v1/" . ltrim($path, "/");
  $ch = curl_init($url);
  $headers = [
    "api-key: $apiKey",
    "Content-Type: application/json"
  ];
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  if (!is_null($body)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
  }
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  return [$http, $resp, $err];
}

function safe_text($s) {
  return htmlspecialchars($s ?? "", ENT_QUOTES, 'UTF-8');
}

/* -------------------- STATE -------------------- */
if (!isset($_SESSION['history'])) $_SESSION['history'] = [];

/* Reset chat */
if (isset($_POST['reset'])) {
  $_SESSION['history'] = [];
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

/* -------------------- HANDLE SEND -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
  $user = trim((string)$_POST['message']);
  if ($user !== '') {
    $_SESSION['history'][] = ['role' => 'user', 'content' => $user];

    // 1) Create thread
    [$code1, $resp1, $err1] = azure_call("POST", "threads", null, $AZURE_API_KEY, $AZURE_ENDPOINT);
    $data1 = $resp1 ? json_decode($resp1, true) : null;
    $threadId = $data1['id'] ?? null;

    $botReply = null;
    if ($code1 >= 200 && $code1 < 300 && $threadId) {
      // 2) Add user message
      $bodyMsg = ["role" => "user", "content" => $user];
      [$code2, $resp2, $err2] = azure_call("POST", "threads/$threadId/messages", $bodyMsg, $AZURE_API_KEY, $AZURE_ENDPOINT);

      if ($code2 >= 200 && $code2 < 300) {
        // 3) Run assistant
        $bodyRun = ["assistant_id" => $ASSISTANT_ID];
        [$code3, $resp3, $err3] = azure_call("POST", "threads/$threadId/runs", $bodyRun, $AZURE_API_KEY, $AZURE_ENDPOINT);
        $data3 = $resp3 ? json_decode($resp3, true) : null;
        $runId = $data3['id'] ?? null;

        if ($code3 >= 200 && $code3 < 300 && $runId) {
          // 4) Poll status until completed/failed (timeout ~8s)
          $status = $data3['status'] ?? 'queued';
          $tries  = 0;
          while (in_array($status, ['queued','in_progress']) && $tries < 40) {
            usleep(200000); // 200ms
            [$codeS, $respS, $errS] = azure_call("GET", "threads/$threadId/runs/$runId", null, $AZURE_API_KEY, $AZURE_ENDPOINT);
            $dataS = $respS ? json_decode($respS, true) : null;
            $status = $dataS['status'] ?? 'failed';
            $tries++;
          }

          if ($status === 'completed') {
            // 5) Fetch messages (assistant reply)
            [$codeM, $respM, $errM] = azure_call("GET", "threads/$threadId/messages", null, $AZURE_API_KEY, $AZURE_ENDPOINT);
            $dataM = $respM ? json_decode($respM, true) : null;
            if (!empty($dataM['data'])) {
              // The list is usually newest first
              foreach ($dataM['data'] as $m) {
                if (($m['role'] ?? '') === 'assistant') {
                  $parts = $m['content'][0]['text']['value'] ?? null;
                  if ($parts) { $botReply = $parts; break; }
                }
              }
            }
          } else {
            $botReply = "⚠️ Assistant run status: " . safe_text($status);
          }
        } else {
          $botReply = "⚠️ Run error ($code3): " . safe_text($resp3 ?: $err3);
        }
      } else {
        $botReply = "⚠️ Add message error ($code2): " . safe_text($resp2 ?: $err2);
      }
    } else {
      $botReply = "⚠️ Thread error ($code1): " . safe_text($resp1 ?: $err1);
    }

    if (!$botReply) $botReply = "⚠️ Pas de réponse de l’assistant.";
    $_SESSION['history'][] = ['role' => 'assistant', 'content' => $botReply];
  }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>SBM Chatbot</title>
  <style>
    body { font-family: Arial, sans-serif; background:#1e1e1e; color:#fff; margin:0; display:flex; min-height:100vh; align-items:center; justify-content:center; }
    .card { width: 95%; max-width: 560px; background:#2b2b2b; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,0.35); overflow:hidden; }
    .header { background:#3a3a3a; padding:14px 18px; text-align:center; font-weight:700; letter-spacing:.5px; }
    .messages { max-height: 60vh; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:10px; }
    .bubble { padding:10px 12px; border-radius:10px; max-width:78%; font-size:14px; line-height:1.35; }
    .user      { align-self:flex-end; background:#1e88e5; }
    .assistant { align-self:flex-start; background:#424242; }
    .row { display:flex; gap:8px; padding:12px; background:#2b2b2b; border-top:1px solid #3b3b3b; }
    .row input[type=text]{ flex:1; padding:10px 12px; border-radius:8px; border:none; outline:none; background:#3a3a3a; color:#fff; }
    .row button { padding:10px 14px; border:none; border-radius:8px; background:#1e88e5; color:#fff; cursor:pointer; }
    .reset { width:100%; text-align:center; padding:10px 12px; background:#2b2b2b; border-top:1px solid #3b3b3b; }
    .reset button { padding:8px 12px; border:none; border-radius:8px; background:#d9534f; color:#fff; cursor:pointer; }
    .hint { font-size:12px; opacity:.75; margin-top:6px; text-align:center; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">SBM Chatbot</div>
    <div class="messages" id="messages">
      <?php foreach ($_SESSION['history'] as $m): ?>
        <div class="bubble <?= $m['role'] ?>"><?= safe_text($m['content']) ?></div>
      <?php endforeach; ?>
    </div>

    <form method="post" class="row" autocomplete="off">
      <input type="text" name="message" placeholder="Pose ta question..." />
      <button type="submit">Envoyer</button>
    </form>

    <form method="post" class="reset">
      <button name="reset" type="submit">Réinitialiser</button>
      <div class="hint">Astuce : si tu vois un message "Thread/Run error", c’est que l’endpoint/clé/assistant_id ne répond pas.</div>
    </form>
  </div>
  <script>
    // Scroll en bas au chargement
    const box = document.getElementById('messages');
    box.scrollTop = box.scrollHeight;
  </script>
</body>
</html>
