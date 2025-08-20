<?php
session_start();

/* --- Configuration from Render --- */
$AZURE_ENDPOINT = rtrim(getenv('AZURE_ENDPOINT') ?: '', '/');
$AZURE_API_KEY  = getenv('AZURE_API_KEY') ?: '';
$ASSISTANT_ID   = getenv('ASSISTANT_ID') ?: '';
$API_VERSION    = '2024-07-01-preview';  // Azure Assistants API version

/* --- Sanity check --- */
if (!$AZURE_ENDPOINT || !$AZURE_API_KEY || !$ASSISTANT_ID) {
  http_response_code(500);
  echo "<pre>Missing configuration. Please set AZURE_ENDPOINT, AZURE_API_KEY and ASSISTANT_ID in Render.</pre>";
  exit;
}

/* --- Minimal HTTP helper (same as your working version) --- */
function aoai_call($method, $path, $body = null) {
  global $AZURE_ENDPOINT, $AZURE_API_KEY, $API_VERSION;
  $url = $AZURE_ENDPOINT . $path . (str_contains($path, '?') ? "&" : "?") . "api-version={$API_VERSION}";

  $ch = curl_init($url);
  $headers = [
    "api-key: {$AZURE_API_KEY}",
    "Content-Type: application/json",
    "Accept: application/json"
  ];
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60
  ]);
  if (!is_null($body)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
  }

  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  return [$http, $resp, $err];
}

/* --- Session bootstrap --- */
if (!isset($_SESSION['history']))   $_SESSION['history'] = [];
if (!isset($_SESSION['thread_id'])) $_SESSION['thread_id'] = null;

/* --- Reset --- */
if (isset($_POST['reset'])) {
  $_SESSION['history'] = [];
  $_SESSION['thread_id'] = null;
  header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
  exit;
}

/* --- Send message --- */
$error = null;
if (!empty($_POST['message'])) {
  $userMessage = trim($_POST['message']);
  $_SESSION['history'][] = ['role' => 'user', 'content' => $userMessage];

  /* 1) Create thread if needed */
  if (!$_SESSION['thread_id']) {
    [$code, $resp, $err] = aoai_call('POST', "/openai/threads", []); // empty body allowed
    if ($code >= 200 && $code < 300) {
      $data = json_decode($resp, true);
      $_SESSION['thread_id'] = $data['id'] ?? null;
    } else {
      $error = "THREAD error ($code): " . htmlspecialchars($resp ?: $err);
    }
  }

  /* 2) Add user message to thread */
  if (!$error && $_SESSION['thread_id']) {
    $msgBody = [
      "role" => "user",
      "content" => [
        ["type" => "text", "text" => $userMessage]
      ]
    ];
    [$code, $resp, $err] = aoai_call('POST', "/openai/threads/{$_SESSION['thread_id']}/messages", $msgBody);
    if (!($code >= 200 && $code < 300)) {
      $error = "MESSAGE error ($code): " . htmlspecialchars($resp ?: $err);
    }
  }

  /* 3) Start a run with your assistant */
  if (!$error && $_SESSION['thread_id']) {
    $runBody = ["assistant_id" => $ASSISTANT_ID];
    [$code, $resp, $err] = aoai_call('POST', "/openai/threads/{$_SESSION['thread_id']}/runs", $runBody);
    if ($code >= 200 && $code < 300) {
      $run = json_decode($resp, true);
      $runId = $run['id'] ?? null;

      // 4) Poll until completion
      $deadline = time() + 60;
      $status = 'queued';
      while (time() < $deadline) {
        [$sCode, $sResp, $sErr] = aoai_call('GET', "/openai/threads/{$_SESSION['thread_id']}/runs/{$runId}", null);
        if (!($sCode >= 200 && $sCode < 300)) {
          $error = "RUN status error ($sCode): " . htmlspecialchars($sResp ?: $sErr);
          break;
        }
        $s = json_decode($sResp, true);
        $status = $s['status'] ?? 'unknown';
        if (in_array($status, ['completed','failed','cancelled','expired'])) break;
        usleep(800000); // 0.8s
      }

      // 5) Fetch latest assistant message
      if (!$error) {
        if ($status === 'completed') {
          [$mCode, $mResp, $mErr] = aoai_call('GET', "/openai/threads/{$_SESSION['thread_id']}/messages?order=desc&limit=1", null);
          if ($mCode >= 200 && $mCode < 300) {
            $messages = json_decode($mResp, true);
            $items = $messages['data'] ?? [];
            $assistantText = "(no response)";
            if (!empty($items)) {
              $assistantMsg = $items[0];
              if (!empty($assistantMsg['content'])) {
                $parts = [];
                foreach ($assistantMsg['content'] as $c) {
                  if (($c['type'] ?? '') === 'text' && isset($c['text']['value'])) {
                    $parts[] = $c['text']['value'];
                  }
                }
                if ($parts) $assistantText = implode("\n", $parts);
              }
            }
            $_SESSION['history'][] = ['role' => 'assistant', 'content' => $assistantText];
          } else {
            $error = "MESSAGES error ($mCode): " . htmlspecialchars($mResp ?: $mErr);
          }
        } else {
          $error = "RUN finished with status: {$status}";
        }
      }
    } else {
      $error = "RUN error ($code): " . htmlspecialchars($resp ?: $err);
    }
  }

  header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SBM Chatbot</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{background:#0f0f10;color:#e8e8e8;font-family:system-ui,Arial;margin:0}
  .wrap{max-width:700px;margin:40px auto;padding:0 16px}
  .card{background:#1b1c1f;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.4);padding:0 0 16px}
  .hdr{background:#2a2c31;border-radius:14px 14px 0 0;padding:14px 18px;text-align:center;font-weight:700}
  .chat{padding:16px;display:flex;flex-direction:column;gap:10px;max-height:62vh;overflow:auto}
  .b{max-width:78%;padding:10px 12px;border-radius:12px;line-height:1.35;font-size:15px;white-space:pre-wrap}
  .me{margin-left:auto;background:#1e88e5;color:#fff;border-bottom-right-radius:4px}
  .bot{margin-right:auto;background:#2f3238;color:#d9e1f2;border-bottom-left-radius:4px}
  .err{background:#4a3426;color:#ffd7b1}
  form{display:flex;gap:8px;padding:0 16px 12px}
  input[type=text]{flex:1;padding:12px;border-radius:10px;border:1px solid #3a3d44;background:#111214;color:#e8e8e8}
  button{padding:12px 16px;border:0;border-radius:10px;background:#1e88e5;color:#fff;cursor:pointer}
  .sub{display:flex;justify-content:space-between;align-items:center;padding:0 18px 10px;color:#8b8e96;font-size:12px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="hdr">SBM Chatbot</div>
    <div class="chat" id="chat">
      <?php foreach ($_SESSION['history'] as $m): ?>
        <div class="b <?= $m['role']==='user'?'me':'bot' ?>"><?= htmlspecialchars($m['content']) ?></div>
      <?php endforeach; ?>
      <?php if ($error): ?>
        <div class="b err"><?= $error ?></div>
      <?php endif; ?>
    </div>
    <form method="post">
      <input type="text" name="message" placeholder="Type your question..." autocomplete="off" required>
      <button type="submit">Send</button>
      <button type="submit" name="reset" value="1" style="background:#444">Reset</button>
    </form>
    <div class="sub">
      <div>Endpoint: <?= htmlspecialchars($AZURE_ENDPOINT) ?> • API: <?= htmlspecialchars($API_VERSION) ?></div>
      <div>Assistant: <?= htmlspecialchars(substr($ASSISTANT_ID,0,10)) ?>…</div>
    </div>
  </div>
</div>
<script>document.getElementById('chat').scrollTop=9999999;</script>
</body>
</html>







