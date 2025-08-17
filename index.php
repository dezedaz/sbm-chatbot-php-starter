<?php
session_start();

/* ====== Config depuis Render ====== */
$AZURE_ENDPOINT = rtrim(getenv('AZURE_ENDPOINT') ?: '', '/');   // ex: https://sbmchatbot.openai.azure.com
$AZURE_API_KEY  = getenv('AZURE_API_KEY') ?: '';
$ASSISTANT_ID   = getenv('ASSISTANT_ID') ?: 'asst_WrGbhVrIqfqOqg5g1omyddBB';
$API_VERSION    = '2024-05-01-preview'; // Azure Assistants preview

/* ====== Sanity check ====== */
$missing = [];
if (!$AZURE_ENDPOINT) $missing[] = 'AZURE_ENDPOINT';
if (!$AZURE_API_KEY)  $missing[] = 'AZURE_API_KEY';
if (!$ASSISTANT_ID)   $missing[] = 'ASSISTANT_ID';

if (!isset($_SESSION['history'])) $_SESSION['history'] = [];

/* ====== HTTP util ====== */
function az_url($path) {
  return $GLOBALS['AZURE_ENDPOINT'] . "/openai/assistants/" . $GLOBALS['API_VERSION'] . "/" . ltrim($path,'/');
}
function az_call($method, $path, $payload = null) {
  $ch = curl_init(az_url($path));
  $headers = [
    "api-key: " . $GLOBALS['AZURE_API_KEY'],
    "Content-Type: application/json"
  ];
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 30
  ]);
  if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  return [$code, $body, $err];
}

/* ====== Reset ====== */
if (isset($_GET['reset'])) { session_destroy(); header('Location: /'); exit; }

/* ====== Envoi ====== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['message'])) {
  $msg = trim($_POST['message'] ?? '');
  if ($msg !== '') {
    $_SESSION['history'][] = ['role'=>'user','content'=>$msg];

    $bubbleError = null;
    if ($missing) {
      $bubbleError = "Configuration manquante: " . implode(', ', $missing);
    } else {
      // 1) Créer un thread (une fois)
      if (empty($_SESSION['thread_id'])) {
        [$c,$b,$e] = az_call('POST', 'threads', (object)[]);
        if ($c>=200 && $c<300) {
          $j = json_decode($b,true);
          $_SESSION['thread_id'] = $j['id'] ?? null;
        } else {
          $bubbleError = "Erreur THREAD ($c) : " . htmlspecialchars($b ?: $e);
        }
      }

      // 2) Ajouter message user
      if (!$bubbleError && !empty($_SESSION['thread_id'])) {
        $payload = ['role'=>'user','content'=>$msg];
        [$c,$b,$e] = az_call('POST', "threads/{$_SESSION['thread_id']}/messages", $payload);
        if (!($c>=200 && $c<300)) $bubbleError = "Erreur MESSAGE ($c) : " . htmlspecialchars($b ?: $e);
      }

      // 3) Lancer run
      if (!$bubbleError && !empty($_SESSION['thread_id'])) {
        $payload = ['assistant_id'=>$ASSISTANT_ID];
        [$c,$b,$e] = az_call('POST', "threads/{$_SESSION['thread_id']}/runs", $payload);
        if ($c>=200 && $c<300) {
          $run = json_decode($b,true);
          $runId = $run['id'] ?? null;
          // 4) Poll
          $status = $run['status'] ?? 'queued';
          $t0 = time();
          while (in_array($status, ['queued','in_progress']) && time()-$t0 < 45) {
            usleep(800000);
            [$c2,$b2,$e2] = az_call('GET', "threads/{$_SESSION['thread_id']}/runs/$runId");
            if (!($c2>=200 && $c2<300)) break;
            $run2 = json_decode($b2,true);
            $status = $run2['status'] ?? $status;
          }
          if ($status!=='completed') {
            $bubbleError = "Exécution non terminée (statut: $status)";
          } else {
            // 5) Lire derniers messages (desc)
            [$c3,$b3,$e3] = az_call('GET', "threads/{$_SESSION['thread_id']}/messages?order=desc&limit=20");
            if ($c3>=200 && $c3<300) {
              $list = json_decode($b3,true);
              $reply = null;
              foreach (($list['data'] ?? []) as $m) {
                if (($m['role'] ?? '')==='assistant') {
                  $parts = $m['content'] ?? [];
                  $txt = [];
                  foreach ($parts as $p) if (($p['type']??'')==='text' && isset($p['text']['value'])) $txt[]=$p['text']['value'];
                  $reply = trim(implode("\n",$txt));
                  if ($reply!=='') break;
                }
              }
              if ($reply==='') $bubbleError = "Erreur : pas de réponse générée.";
              else $_SESSION['history'][] = ['role'=>'assistant','content'=>$reply];
            } else $bubbleError = "Erreur LIST_MESSAGES ($c3) : " . htmlspecialchars($b3 ?: $e3);
          }
        } else $bubbleError = "Erreur RUN ($c) : " . htmlspecialchars($b ?: $e);
      }
    }

    if ($bubbleError) $_SESSION['history'][] = ['role'=>'error','content'=>$bubbleError];
    header('Location: /'); exit;
  }
}

/* ====== UI ====== */
function esc($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>SBM Chatbot</title>
<style>
  body{margin:0;background:#111;color:#fff;font-family:system-ui,Segoe UI,Arial,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center}
  .card{width:min(640px,94vw);background:#2a2a2a;border-radius:14px;box-shadow:0 8px 28px rgba(0,0,0,.35);overflow:hidden}
  .head{padding:14px 18px;background:#3a3a3a;font-weight:700;text-align:center}
  .feed{padding:16px;display:flex;flex-direction:column;gap:10px;max-height:64vh;overflow:auto}
  .bubble{padding:10px 12px;border-radius:12px;max-width:88%;line-height:1.4;white-space:pre-wrap}
  .user{align-self:flex-end;background:#1e88e5}
  .assistant{align-self:flex-start;background:#3a3a3a}
  .error{align-self:flex-start;background:#4a3b2a;color:#ffd9a7}
  .row{display:flex;gap:8px;padding:12px;background:#2c2c2c}
  input[type=text]{flex:1;border:none;border-radius:8px;padding:12px;background:#1a1a1a;color:#fff;outline:none}
  button{background:#1e88e5;color:#fff;border:none;border-radius:8px;padding:12px 16px;cursor:pointer}
  .foot{padding:8px 12px;color:#bbb;font-size:12px;text-align:center}
</style>
</head><body>
<div class="card">
  <div class="head">SBM Chatbot</div>
  <div class="feed">
    <?php if ($missing): ?>
      <div class="bubble error">Configuration manquante : <?=esc(implode(', ',$missing))?>.</div>
    <?php endif; ?>
    <?php foreach ($_SESSION['history'] as $m): ?>
      <div class="bubble <?= $m['role']==='user'?'user':($m['role']==='assistant'?'assistant':'error') ?>">
        <?= nl2br(esc($m['content'])) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <form class="row" method="post" action="/">
    <input type="text" name="message" placeholder="Pose ta question..." autocomplete="off" required>
    <button type="submit">Envoyer</button>
    <a href="/?reset=1"><button type="button" style="background:#555">Réinitialiser</button></a>
  </form>
  <div class="foot">Endpoint utilisé : <?=esc($AZURE_ENDPOINT)?> | Version API : <?=esc($API_VERSION)?></div>
</div>
</body></html>


