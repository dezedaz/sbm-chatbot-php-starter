<?php
// index.php — English UI, same logic

session_start();

// --- Config from environment ---
$AZURE_ENDPOINT = getenv('AZURE_ENDPOINT');      // e.g. https://sbmchatbot.openai.azure.com
$AZURE_API_KEY  = getenv('AZURE_API_KEY');
$ASSISTANT_ID   = getenv('ASSISTANT_ID');
$API_VERSION    = '2024-07-01-preview';

// Quick check
if (!$AZURE_ENDPOINT || !$AZURE_API_KEY || !$ASSISTANT_ID) {
  http_response_code(200);
  echo "<!doctype html><meta charset='utf-8'><style>
        body{background:#0f1218;color:#e6e8ee;font-family:Inter,system-ui,Segoe UI,Arial,sans-serif;padding:2rem}
        .box{background:#161a22;border:1px solid #242b38;border-radius:12px;padding:16px;max-width:880px}
        code{background:#111722;padding:2px 6px;border-radius:6px}
      </style>
      <h2>Configuration missing</h2>
      <div class='box'>
        Please set the environment variables <code>AZURE_API_KEY</code>, <code>AZURE_ENDPOINT</code>, and <code>ASSISTANT_ID</code> in Render.
      </div>";
  exit;
}

// --- Small HTTP helper ---
function http_json($method, $url, $apiKey, $payload = null) {
  $ch = curl_init($url);
  $headers = [
    'Content-Type: application/json',
    'api-key: ' . $apiKey,
  ];
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 60,
  ]);
  if ($payload !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  }
  $raw = curl_exec($ch);
  $err = curl_error($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$status, $raw, $err];
}

// --- Reset chat / thread ---
if (isset($_POST['reset'])) {
  $_SESSION = [];
  session_destroy();
  header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
  exit;
}

// Ensure we have a thread
if (empty($_SESSION['thread_id'])) {
  $url = rtrim($AZURE_ENDPOINT, '/') . "/openai/assistants/{$GLOBALS['API_VERSION']}/threads";
  [$status, $raw, $err] = http_json('POST', $url, $AZURE_API_KEY, []);
  $data = json_decode($raw, true);
  if ($status >= 200 && $status < 300 && !empty($data['id'])) {
    $_SESSION['thread_id'] = $data['id'];
  } else {
    $fatal = "THREAD create error: HTTP {$status} — " . ($raw ?: $err ?: 'Unknown error');
  }
}

$uiError = null;

// Handle a user message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !isset($fatal)) {
  $msg = trim((string)$_POST['message']);

  if ($msg !== '') {
    $threadId = $_SESSION['thread_id'];

    // 1) Add user message to the thread
    $url = rtrim($AZURE_ENDPOINT, '/') . "/openai/assistants/{$API_VERSION}/threads/{$threadId}/messages";
    $payload = [
      'role'    => 'user',
      'content' => [
        ['type' => 'text', 'text' => $msg]
      ],
    ];
    [$status, $raw, $err] = http_json('POST', $url, $AZURE_API_KEY, $payload);
    if ($status < 200 || $status >= 300) {
      $uiError = "Message error: HTTP {$status} — " . ($raw ?: $err ?: 'Unknown error');
    } else {
      // 2) Create a run
      $url = rtrim($AZURE_ENDPOINT, '/') . "/openai/assistants/{$API_VERSION}/threads/{$threadId}/runs";
      $payload = ['assistant_id' => $ASSISTANT_ID];
      [$status, $raw, $err] = http_json('POST', $url, $AZURE_API_KEY, $payload);
      $run = json_decode($raw, true);

      if ($status >= 200 && $status < 300 && !empty($run['id'])) {
        $runId = $run['id'];

        // 3) Poll until completed or failed
        $url = rtrim($AZURE_ENDPOINT, '/') . "/openai/assistants/{$API_VERSION}/threads/{$threadId}/runs/{$runId}";
        $maxPoll = 60; // ~60s
        $done = false; $failed = false;

        for ($i = 0; $i < $maxPoll; $i++) {
          [$s, $r, $e] = http_json('GET', $url, $AZURE_API_KEY);
          $rd = json_decode($r, true);
          $statusStr = $rd['status'] ?? '';

          if ($statusStr === 'completed') { $done = true; break; }
          if (in_array($statusStr, ['failed','cancelled','expired'])) { $failed = true; break; }

          usleep(800000); // 0.8s
        }

        if (!$done || $failed) {
          $uiError = "Run error: " . ($failed ? 'failed' : 'timeout');
        } else {
          // 4) Fetch latest assistant messages
          $url = rtrim($AZURE_ENDPOINT, '/') . "/openai/assistants/{$API_VERSION}/threads/{$threadId}/messages?limit=10";
          [$s, $r, $e] = http_json('GET', $url, $AZURE_API_KEY);
          $md = json_decode($r, true);

          $assistantReply = null;
          if (!empty($md['data'])) {
            // messages are usually newest first
            foreach ($md['data'] as $m) {
              if (($m['role'] ?? '') === 'assistant') {
                $blocks = $m['content'] ?? [];
                foreach ($blocks as $b) {
                  if (($b['type'] ?? '') === 'text' && isset($b['text']['value'])) {
                    $assistantReply = $b['text']['value'];
                    break 2;
                  }
                }
              }
            }
          }

          if ($assistantReply === null) {
            $uiError = "No assistant response was generated.";
          } else {
            $_SESSION['history'][] = ['role' => 'user', 'text' => $msg, 'ts' => time()];
            $_SESSION['history'][] = ['role' => 'assistant', 'text' => $assistantReply, 'ts' => time()];
          }
        }
      } else {
        $uiError = "Run create error: HTTP {$status} — " . ($raw ?: $err ?: 'Unknown error');
      }
    }
  }
}

// Build history to display
$history = $_SESSION['history'] ?? [];

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function when($t){ return date('H:i', $t ?: time()); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>SBM Chatbot</title>
  <style>
    :root{
      --bg:#0f1218; --panel:#121722; --panel2:#0f141d; --muted:#94a3b8;
      --border:#1f2633; --primary:#2f6fec; --primary-2:#1e57c8; --chip:#22345a;
      --text:#e6e8ee; --me:#2977ff; --ai:#303744;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,Segoe UI,Arial,sans-serif}
    .wrap{min-height:100dvh;display:grid;place-items:center;padding:24px}
    .card{background:var(--panel);border:1px solid var(--border);border-radius:16px;max-width:900px;width:100%}
    .head{padding:18px 24px;border-bottom:1px solid var(--border);font-weight:600;text-align:center}
    .chat{padding:24px;display:flex;flex-direction:column;gap:14px;max-height:70vh;overflow:auto}
    .row{display:flex}
    .row.me{justify-content:flex-end}
    .bubble{max-width:74%;padding:12px 14px;border-radius:12px;line-height:1.45}
    .me .bubble{background:var(--me);color:#fff;border-bottom-right-radius:6px}
    .ai .bubble{background:var(--ai);border-bottom-left-radius:6px}
    .ts{font-size:12px;color:var(--muted);margin-top:6px}
    .err{background:#3a2325;border:1px solid #6e2c31;color:#ffb4b7;border-radius:12px;padding:12px}
    .foot{border-top:1px solid var(--border);padding:14px 18px;display:flex;gap:10px}
    input[type=text]{flex:1;background:var(--panel2);border:1px solid var(--border);color:var(--text);
      border-radius:10px;padding:12px 14px;outline:none}
    input[type=text]::placeholder{color:#7b88a1}
    button{background:var(--primary);border:none;color:#fff;border-radius:10px;padding:12px 16px;
      font-weight:600;cursor:pointer}
    button:hover{background:var(--primary-2)}
    .muted{color:var(--muted);font-size:12px;margin:0 24px 20px}
    .meta{font-size:12px;background:var(--chip);display:inline-block;padding:6px 8px;border-radius:8px}
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="head">SBM Chatbot</div>

    <div class="chat">
      <?php if (!empty($fatal)): ?>
        <div class="err"><?=h($fatal)?></div>
      <?php endif; ?>

      <?php if (!empty($uiError)): ?>
        <div class="row ai">
          <div class="bubble">
            <div class="err"><?=h($uiError)?></div>
          </div>
        </div>
      <?php endif; ?>

      <?php foreach ($history as $m): ?>
        <div class="row <?= $m['role'] === 'user' ? 'me' : 'ai' ?>">
          <div>
            <div class="bubble"><?= nl2br(h($m['text'])) ?></div>
            <div class="ts"><?= h(when($m['ts'] ?? time())) ?></div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (empty($history) && empty($uiError) && empty($fatal)): ?>
        <div class="row ai">
          <div>
            <div class="bubble">Hi! Ask a question related to IT or your work.</div>
            <div class="ts"><?=h(when(time()))?></div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <form class="foot" method="post">
      <input type="text" name="message" autocomplete="off" placeholder="Type your question..." />
      <button type="submit">Send</button>
      <button type="submit" name="reset" value="1" style="background:#374151">Reset</button>
    </form>

    <p class="muted">
      <span class="meta">Endpoint: <?=h(rtrim($AZURE_ENDPOINT,'/'))?></span>
      &nbsp; <span class="meta">API: <?=$API_VERSION?></span>
      &nbsp; <span class="meta">Assistant: <?=h($ASSISTANT_ID)?></span>
    </p>
  </div>
</div>
</body>
</html>



