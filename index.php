<?php
session_start();

/**
 * Health probe for Render
 * GET /?health=1  -> 200 ok
 */
if (isset($_GET['health'])) {
  http_response_code(200);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'ok';
  exit;
}

$AZURE_ENDPOINT = getenv('AZURE_ENDPOINT');   // e.g. https://sbmchatbot.openai.azure.com
$AZURE_API_KEY  = getenv('AZURE_API_KEY');
$ASSISTANT_ID   = getenv('ASSISTANT_ID');

function json_out(array $data, int $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * AJAX endpoint:
 * POST /index.php?ajax=1   with form field "message"
 * Returns: { ok: true, reply: "...", thread_id: "..." }  OR { ok:false, error:"..." }
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
  $msg = trim($_POST['message'] ?? '');

  if ($msg === '') {
    json_out(['ok' => false, 'error' => 'Empty message'], 400);
  }
  if (!$AZURE_ENDPOINT || !$AZURE_API_KEY || !$ASSISTANT_ID) {
    json_out(['ok' => false, 'error' => 'Missing server configuration (env vars)'], 500);
  }

  $base = rtrim($AZURE_ENDPOINT, '/') . '/openai/assistants/2024-07-01-preview';
  $headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'api-key: ' . $AZURE_API_KEY,
  ];

  $curl = function (string $method, string $url, ?array $payload = null) use ($headers) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($payload !== null) {
      $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);
    return [$status, $response, $err];
  };

  // 1) Create thread once per session
  if (empty($_SESSION['thread_id'])) {
    [$code, $res] = $curl('POST', $base . '/threads', ['messages' => []]);
    if ($code < 200 || $code >= 300) {
      json_out(['ok' => false, 'error' => 'Could not create thread', 'debug' => $res], 500);
    }
    $data = json_decode($res, true);
    $_SESSION['thread_id'] = $data['id'] ?? null;
  }
  $thread_id = $_SESSION['thread_id'];

  // 2) Add user message
  [$code, $res] = $curl('POST', $base . "/threads/$thread_id/messages", [
    'role'    => 'user',
    'content' => $msg,
  ]);
  if ($code < 200 || $code >= 300) {
    json_out(['ok' => false, 'error' => 'Could not add message', 'debug' => $res], 500);
  }

  // 3) Start run
  [$code, $res] = $curl('POST', $base . "/threads/$thread_id/runs", [
    'assistant_id' => $ASSISTANT_ID,
  ]);
  if ($code < 200 || $code >= 300) {
    json_out(['ok' => false, 'error' => 'Could not start run', 'debug' => $res], 500);
  }
  $run_id = (json_decode($res, true)['id'] ?? null);

  // 4) Poll run status
  $start = time();
  while (true) {
    [$code, $res] = $curl('GET', $base . "/threads/$thread_id/runs/$run_id");
    $obj = json_decode($res, true);
    $status = $obj['status'] ?? 'unknown';

    if ($status === 'completed') break;
    if (in_array($status, ['failed', 'cancelled', 'expired'])) {
      json_out(['ok' => false, 'error' => "Run $status", 'debug' => $res], 500);
    }
    if (time() - $start > 60) {
      json_out(['ok' => false, 'error' => 'Timeout while waiting for run'], 504);
    }
    usleep(500000); // 0.5s
  }

  // 5) Get last assistant message
  [$code, $res] = $curl('GET', $base . "/threads/$thread_id/messages?limit=1&order=desc");
  $obj   = json_decode($res, true);
  $items = $obj['data'] ?? [];
  $text  = '';

  if (!empty($items) && ($items[0]['role'] ?? '') === 'assistant') {
    $text = $items[0]['content'][0]['text']['value'] ?? '';
  }
  if ($text === '') {
    $text = '(No assistant content)';
  }

  json_out(['ok' => true, 'reply' => $text, 'thread_id' => $thread_id]);
}

// Default: redirect to the BlueGate demo page
header('Location: /bluegate.php', true, 302);
exit;

