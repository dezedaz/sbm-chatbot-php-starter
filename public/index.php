<?php
session_start();

$AZURE_ENDPOINT = rtrim(getenv("AZURE_ENDPOINT") ?: "", "/");
$AZURE_API_KEY  = getenv("AZURE_API_KEY");
$ASSISTANT_ID   = getenv("ASSISTANT_ID");

// Vérif config
if (!$AZURE_ENDPOINT || !$AZURE_API_KEY || !$ASSISTANT_ID) {
  http_response_code(500);
  echo "Configuration manquante. Vérifie AZURE_API_KEY, AZURE_ENDPOINT, ASSISTANT_ID dans Render.";
  exit;
}

// Historique UI
if (!isset($_SESSION['history'])) $_SESSION['history'] = [];

// Reset
if (isset($_POST['reset'])) {
  $_SESSION['history'] = [];
  header("Location: " . $_SERVER['PHP_SELF']); exit;
}

// Envoi message
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["message"])) {
  $userMessage = trim($_POST["message"]);
  if ($userMessage !== "") {
    $_SESSION['history'][] = ["role" => "user", "content" => $userMessage];

    // === Responses API (simple) ===
    $url = $AZURE_ENDPOINT . "/openai/responses?api-version=2024-07-01-preview";
    $payload = json_encode([
      "assistant_id" => $ASSISTANT_ID,
      "input" => [
        ["role" => "user", "content" => $userMessage]
      ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "api-key: $AZURE_API_KEY"
      ],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 60
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
      $_SESSION['history'][] = ["role"=>"assistant","content"=>"⚠️ cURL error: $err"];
    } elseif ($code < 200 || $code >= 300) {
      $_SESSION['history'][] = ["role"=>"assistant","content"=>"⚠️ HTTP $code: ".htmlspecialchars($raw)];
    } else {
      $data = json_decode($raw, true);
      // Format de sortie Responses API :
      // $data['output'][0]['content'][0]['text']
      $bot = $data['output'][0]['content'][0]['text'] ?? "Désolé, pas de réponse.";
      $_SESSION['history'][] = ["role"=>"assistant","content"=>$bot];
    }

    header("Location: " . $_SERVER['PHP_SELF']); exit;
  }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>SBM Chatbot</title>
  <style>
    body{background:#121212;color:#fff;font-family:Arial,Helvetica,sans-serif;margin:0;padding:20px}
    .logo{display:block;margin:0 auto 16px;max-width:160px;height:auto}
    .chat{max-width:860px;margin:0 auto 12px;display:flex;flex-direction:column;gap:10px}
    .bubble{padding:10px;border-radius:10px;max-width:80%;font-size:14px;word-wrap:break-word;white-space:pre-wrap}
    .user{background:#1e88e5;align-self:flex-end}
    .assistant{background:#2e2e2e;align-self:flex-start}
    form{max-width:860px;margin:0 auto;display:flex;gap:10px}
    input[type=text]{flex:1;padding:10px;border-radius:6px;border:none;font-size:14px}
    button{padding:10px 16px;border:none;border-radius:6px;background:#1e88e5;color:#fff;cursor:pointer}
    .reset{background:#444}
    .warn{max-width:860px;margin:10px auto;background:#2b2b2b;border-left:4px solid #f5a623;padding:10px;border-radius:6px;color:#ddd;font-size:14px}
  </style>
</head>
<body>
  <img src="sbm_logo.png" alt="SBM Logo" class="logo">
  <div class="chat">
    <?php foreach ($_SESSION['history'] as $m): ?>
      <div class="bubble <?= $m['role']==='user'?'user':'assistant' ?>">
        <?= htmlspecialchars($m['content']) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <form method="post">
    <input type="text" name="message" placeholder="Pose une question..." autocomplete="off" required>
    <button type="submit">Envoyer</button>
    <button class="reset" type="submit" name="reset" value="1">Réinitialiser</button>
  </form>
  <script>window.scrollTo(0, document.body.scrollHeight);</script>
</body>
</html>




