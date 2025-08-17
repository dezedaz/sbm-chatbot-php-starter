<?php
// index.php  —  UI classique + mini-API JSON pour bluegate.php
session_start();
header_remove("X-Powered-By");

// -----------------------
// Config (depuis Render)
// -----------------------
$AZURE_ENDPOINT = rtrim(getenv("AZURE_ENDPOINT") ?: "", "/");
$AZURE_API_KEY  = getenv("AZURE_API_KEY") ?: "";
$ASSISTANT_ID   = getenv("ASSISTANT_ID") ?: "";
$API_VERSION    = "2024-07-01-preview";  // la même que tu utilises dans l’autre page

// -----------------------
// Helper Azure (cURL)
// -----------------------
function az_request($method, $path, $payload = null) {
    global $AZURE_ENDPOINT, $AZURE_API_KEY, $API_VERSION;
    $url = $AZURE_ENDPOINT . "/openai/assistants/{$API_VERSION}" . $path;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "Accept: application/json",
            "api-key: {$AZURE_API_KEY}",
        ],
        CURLOPT_TIMEOUT => 60,
    ]);
    if (!is_null($payload)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return [null, "HTTP transport error: {$err}"];
    }
    $json = json_decode($raw, true);
    if ($json === null) {
        return [null, "Non-JSON response (code {$code}): ".$raw];
    }
    if ($code >= 400) {
        return [null, "HTTP {$code}: ".json_encode($json)];
    }
    return [$json, null];
}

// -----------------------
// Mini API JSON (AJAX)
// -----------------------
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === '1');
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$AZURE_ENDPOINT || !$AZURE_API_KEY || !$ASSISTANT_ID) {
        echo json_encode(['ok' => false, 'error' => 'Missing AZURE_* or ASSISTANT_ID env vars']); exit;
    }

    $action  = $_POST['action'] ?? '';
    $content = trim($_POST['content'] ?? '');

    // Thread en session
    if ($action === 'reset') {
        unset($_SESSION['thread_id']);
        echo json_encode(['ok' => true, 'info' => 'Thread reset']); exit;
    }

    if (empty($_SESSION['thread_id'])) {
        // Create thread
        list($thread, $e1) = az_request("POST", "/threads", []);
        if ($e1) { echo json_encode(['ok' => false, 'error' => "Thread create error: {$e1}"]); exit; }
        $_SESSION['thread_id'] = $thread['id'];
    }
    $threadId = $_SESSION['thread_id'];

    if ($action === 'send') {
        if ($content === '') { echo json_encode(['ok' => false, 'error' => 'Empty message']); exit; }

        // 1) Add user message
        list($msgRes, $e2) = az_request("POST", "/threads/{$threadId}/messages", [
            'role'    => 'user',
            'content' => $content,
        ]);
        if ($e2) { echo json_encode(['ok' => false, 'error' => "Add message error: {$e2}"]); exit; }

        // 2) Run the assistant
        list($runRes, $e3) = az_request("POST", "/threads/{$threadId}/runs", [
            'assistant_id' => $ASSISTANT_ID,
        ]);
        if ($e3) { echo json_encode(['ok' => false, 'error' => "Run error: {$e3}"]); exit; }

        // 3) Poll until completed
        $runId = $runRes['id'];
        $deadline = time() + 60;
        $status = $runRes['status'];
        while (in_array($status, ['queued', 'in_progress', 'cancelling']) && time() < $deadline) {
            usleep(500000); // 0.5s
            list($runGet, $e4) = az_request("GET", "/threads/{$threadId}/runs/{$runId}");
            if ($e4) { echo json_encode(['ok' => false, 'error' => "Run poll error: {$e4}"]); exit; }
            $status = $runGet['status'];
        }
        if ($status !== 'completed') {
            echo json_encode(['ok' => false, 'error' => "Run did not complete (status={$status})"]); exit;
        }

        // 4) Get last assistant message
        list($msgList, $e5) = az_request("GET", "/threads/{$threadId}/messages?limit=10");
        if ($e5) { echo json_encode(['ok' => false, 'error' => "List messages error: {$e5}"]); exit; }

        $reply = '';
        foreach ($msgList['data'] as $m) {
            if ($m['role'] === 'assistant') {
                // concatenate text parts
                foreach ($m['content'] as $c) {
                    if (($c['type'] ?? '') === 'text') {
                        $reply .= $c['text']['value'];
                    }
                }
                break;
            }
        }
        if ($reply === '') $reply = '(empty assistant reply)';

        echo json_encode(['ok' => true, 'reply' => $reply, 'thread_id' => $threadId]); exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']); exit;
}

// -----------------------
// Page HTML classique (si on ouvre index.php directement)
// -----------------------
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<title>SBM Chatbot</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<style>
  body{background:#0b1220;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial}
  .wrap{max-width:680px;margin:40px auto;padding:24px;background:#0f172a;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
  h1{margin:0 0 8px;font-size:28px}
  small{color:#94a3b8}
  .row{display:flex;gap:8px;margin-top:12px}
  input{flex:1;padding:12px;border-radius:10px;border:1px solid #1f2a44;background:#0b1220;color:#eee}
  button{padding:12px 16px;border-radius:10px;border:0;background:#2563eb;color:#fff;cursor:pointer}
  .bubble{padding:12px 14px;background:#111827;border-radius:12px;margin-top:10px}
</style>
</head>
<body>
<div class="wrap">
  <h1>SBM Chatbot</h1>
  <small>Open this only if you want the full page. The floating widget lives on <code>bluegate.php</code>.</small>

  <div id="log" class="bubble" style="display:none"></div>

  <div class="row">
    <input id="q" placeholder="Type your question..."/>
    <button onclick="send()">Send</button>
    <button style="background:#374151" onclick="resetChat()">Reset</button>
  </div>
</div>

<script>
async function api(action, content="") {
  const body = new URLSearchParams({ajax:"1", action, content});
  const r = await fetch("index.php", {
    method: "POST",
    headers: {"Content-Type":"application/x-www-form-urlencoded", "Accept":"application/json"},
    body
  });
  return r.json();
}
async function send(){
  const inp = document.getElementById('q');
  const out = document.getElementById('log');
  const text = inp.value.trim();
  if(!text) return;
  const res = await api("send", text);
  out.style.display = "block";
  out.textContent = res.ok ? res.reply : ("Error: "+res.error);
}
async function resetChat(){
  await api("reset");
  document.getElementById('log').textContent = "(thread reset)";
}
</script>
</body>
</html>


