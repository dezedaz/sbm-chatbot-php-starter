<?php
session_start();

/* ====== CONFIG ====== */
$AZURE_ENDPOINT = "https://sbmchatbot.openai.azure.com";
$API_VERSION    = "2024-08-01-preview";
$ASSISTANT_ID   = "asst_WrGbhVrIqfqOqg5g1omyddBB";

/* Clé : env var d'abord, sinon ta clé fournie */
$AZURE_API_KEY  = getenv("AZURE_API_KEY");
if (!$AZURE_API_KEY || trim($AZURE_API_KEY) === "") {
  $AZURE_API_KEY = "F4GkErnSxB1YyTAtIZ7aIwWirmAFTrClqALkVr2VhRvJWJxqPe32JQQJ99BHACYeBjFXJ3w3AAABACOGO0aZ";
}

/* ====== UTILS ====== */
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Appel générique Azure Assistants v1, en forçant Content-Length */
function azure_call($method, $path, $apiVersion, $apiKey, $endpoint, $bodyAssocOrNull = null) {
  $url = rtrim($endpoint, "/") . "/openai/assistants/v1/" . ltrim($path, "/");
  $url .= (strpos($url,'?')===false ? '?' : '&') . "api-version=" . urlencode($apiVersion);

  $ch = curl_init($url);

  $headers = [
    "api-key: $apiKey",
    "Accept: application/json",
    "Content-Type: application/json",
    "Expect:",                // évite 100-continue
    "Connection: close"       // plus simple avec certains proxies
  ];

  $payload = null;
  $upper = strtoupper($method);

  if (in_array($upper, ['POST','PUT','PATCH'])) {
    // Toujours envoyer un corps JSON. Si null => {}
    if ($bodyAssocOrNull === null) {
      $payload = "{}";
    } else {
      $payload = json_encode($bodyAssocOrNull, JSON_UNESCAPED_SLASHES);
      if ($payload === false || $payload === "") $payload = "{}";
    }
    $headers[] = "Content-Length: " . strlen($payload);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  }

  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => $upper,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
  ]);

  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  return [$http, $resp, $err, $url, $payload];
}

/* ====== STATE ====== */
if (!isset($_SESSION['history'])) $_SESSION['history'] = [];

/* Reset chat */
if (isset($_POST['reset'])) {
  $_SESSION['history'] = [];
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

/* ====== SEND ====== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['message'])) {
  $userMsg = trim((string)$_POST['message']);
  if ($userMsg !== '') {
    $_SESSION['history'][] = ['role'=>'user','content'=>$userMsg];

    // 1) Create thread (corps forcé à {} pour avoir Content-Length)
    [$code1,$resp1,$err1,$u1,$p1] = azure_call("POST","threads",$API_VERSION,$AZURE_API_KEY,$AZURE_ENDPOINT,null);
    $data1 = $resp1 ? json_decode($resp1,true) : null;
    $threadId = $data1['id'] ?? null;

    $botReply = null;

    if ($code1>=200 && $code1<300 && $threadId) {
      // 2) Add user message (format string accepté en v1 Azure)
      $msgBody = ["role"=>"user","content"=>$userMsg];
      [$code2,$resp2,$err2,$u2,$p2] = azure_call("POST","threads/$threadId/messages",$API_VERSION,$AZURE_API_KEY,$AZURE_ENDPOINT,$msgBody);

      if ($code2>=200 && $code2<300) {
        // 3) Run assistant
        $runBody = ["assistant_id"=>$ASSISTANT_ID];
        [$code3,$resp3,$err3,$u3,$p3] = azure_call("POST","threads/$threadId/runs",$API_VERSION,$AZURE_API_KEY,$AZURE_ENDPOINT,$runBody);
        $data3 = $resp3 ? json_decode($resp3,true) : null;
        $runId = $data3['id'] ?? null;

        if ($code3>=200 && $code3<300 && $runId) {
          // 4) Poll until completed
          $status = $data3['status'] ?? 'queued';
          $tries  = 0;
          while (in_array($status,['queued','in_progress']) && $tries<40) {
            usleep(250000); // 250 ms
            [$codeS,$respS,$errS] = azure_call("GET","threads/$threadId/runs/$runId",$API_VERSION,$AZURE_API_KEY,$AZURE_ENDPOINT);
            $dataS = $respS ? json_decode($respS,true) : null;
            $status = $dataS['status'] ?? 'failed';
            $tries++;
          }

          if ($status==='completed') {
            // 5) Read messages (première réponse assistant)
            [$codeM,$respM,$errM] = azure_call("GET","threads/$threadId/messages",$API_VERSION,$AZURE_API_KEY,$AZURE_ENDPOINT);
            $dataM = $respM ? json_decode($respM,true) : null;
            if (!empty($dataM['data'])) {
              foreach ($dataM['data'] as $m) {
                if (($m['role']??'')==='assistant') {
                  $val = $m['content'][0]['text']['value'] ?? null;
                  if ($val) { $botReply = $val; break; }
                }
              }
            }
            if (!$botReply) $botReply = "⚠️ Aucune réponse texte trouvée.";
          } else {
            $botReply = "⚠️ Run non terminé (status = ".safe($status).")";
          }
        } else {
          $botReply = "⚠️ Erreur RUN ($code3) : ".safe($resp3 ?: $err3);
        }
      } else {
        $botReply = "⚠️ Erreur MESSAGE ($code2) : ".safe($resp2 ?: $err2);
      }
    } else {
      // Affiche aussi un extrait de la page HTML (comme ton 411) pour comprendre
      $preview = $resp1 ?: $err1;
      if ($preview && strlen($preview) > 600) $preview = substr($preview, 0, 600) . "...";
      $botReply = "⚠️ Erreur THREAD ($code1).\nAperçu: " . safe($preview);
    }

    $_SESSION['history'][] = ['role'=>'assistant','content'=>$botReply ?: "⚠️ Pas de réponse générée."];
  }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<title>SBM Chatbot</title>
<style>
  body { font-family: Arial, sans-serif; background:#1e1e1e; color:#fff; margin:0; min-height:100vh; display:flex; justify-content:center; align-items:center; }
  .card { width:95%; max-width:560px; background:#2b2b2b; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,.35); overflow:hidden; }
  .header { background:#3a3a3a; padding:14px 18px; text-align:center; font-weight:700; letter-spacing:.5px; }
  .messages { max-height:60vh; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:10px; }
  .bubble { padding:10px 12px; border-radius:10px; max-width:78%; font-size:14px; line-height:1.35; white-space:pre-wrap; }
  .user      { align-self:flex-end; background:#1e88e5; }
  .assistant { align-self:flex-start; background:#424242; }
  .row { display:flex; gap:8px; padding:12px; background:#2b2b2b; border-top:1px solid #3b3b3b; }
  .row input[type=text]{ flex:1; padding:10px 12px; border-radius:8px; border:none; outline:none; background:#3a3a3a; color:#fff; }
  .row button { padding:10px 14px; border:none; border-radius:8px; background:#1e88e5; color:#fff; cursor:pointer; }
  .reset { width:100%; text-align:center; padding:10px 12px; background:#2b2b2b; border-top:1px solid #3b2b2b; }
  .reset button { padding:8px 12px; border:none; border-radius:8px; background:#d9534f; color:#fff; cursor:pointer; }
</style>
</head>
<body>
  <div class="card">
    <div class="header">SBM Chatbot</div>
    <div class="messages" id="messages">
      <?php foreach ($_SESSION['history'] as $m): ?>
        <div class="bubble <?= $m['role'] ?>"><?= safe($m['content']) ?></div>
      <?php endforeach; ?>
    </div>
    <form method="post" class="row" autocomplete="off">
      <input type="text" name="message" placeholder="Pose ta question..." />
      <button type="submit">Envoyer</button>
    </form>
    <form method="post" class="reset">
      <button name="reset" type="submit">Réinitialiser</button>
    </form>
  </div>
  <script>const box=document.getElementById('messages'); box.scrollTop=box.scrollHeight;</script>
</body>
</html>
