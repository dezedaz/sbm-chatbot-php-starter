<?php
// chat_api.php — Minimal backend for Azure OpenAI Assistants on Azure (2024-07-01-preview).
// - Keeps a thread in PHP session
// - Adds user message, runs the assistant, polls until completed
// - Returns JSON: { ok, reply, thread_id } so the widget can display the answer

session_start();
header('Content-Type: application/json');

function json_fail($msg, $code = 500) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// Reset thread if asked
if (isset($_GET['reset']) || isset($_POST['reset'])) {
  unset($_SESSION['thread_id']);
  echo json_encode(['ok' => true, 'reset' => true]);
  exit;
}

// Read env vars
$API_KEY      = getenv('AZURE_API_KEY');
$ASSISTANT_ID = getenv('ASSISTANT_ID');
$ENDPOINT     = rtrim(getenv('AZURE_ENDPOINT') ?: '', '/');

if (!$API_KEY || !$ASSISTANT_ID || !$ENDPOINT) {
  json_fail('Missing AZURE_API_KEY / AZURE_ENDPOINT / ASSISTANT_ID environment variables.', 500);
}

// API base (Azure)
$API_BASE = $ENDPOINT . '/openai/assistants/2024-07-01-preview';

function curl_json($url, $method = 'GET', $payload = null, $headers = []) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_TIMEOUT, 65);
  if ($payload !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $headers[] = 'Content-Type: application/json';
  }
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $body = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($body === false) {
    $err = curl_error($ch);
    curl_close($ch);
    return [null, $status ?: 0, $err];
  }
  curl_close($ch);
  $decoded = json_decode($body, true);
  return [$decoded, $status, null];
}

// Read message
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$message = $data['message'] ?? null;
if (!$message) {
  json_fail('No message provided', 400);
}

// Ensure a thread exists
$headers = [
  'api-key: ' . $API_KEY
];

$threadId = $_SESSION['thread_id'] ?? null;
if (!$threadId) {
  [$res, $code, $err] = curl_json($API_BASE . '/threads', 'POST', (object)[], $headers);
  if ($code >= 400 || !$res || empty($res['id'])) {
    json_fail('Thread create error: ' . ($err ?: json_encode($res)), $code ?: 500);
  }
  $threadId = $res['id'];
  $_SESSION['thread_id'] = $threadId;
}

// Add user message
$addMsgBody = [
  'role' => 'user',
  'content' => [
    ['type' => 'text', 'text' => $message]
  ]
];
[$resMsg, $codeMsg, $errMsg] = curl_json("$API_BASE/threads/$threadId/messages", 'POST', $addMsgBody, $headers);
if ($codeMsg >= 400 || !$resMsg) {
  json_fail('Add message error: ' . ($errMsg ?: json_encode($resMsg)), $codeMsg ?: 500);
}

// Create run
$runBody = [
  'assistant_id' => $ASSISTANT_ID
];
[$resRun, $codeRun, $errRun] = curl_json("$API_BASE/threads/$threadId/runs", 'POST', $runBody, $headers);
if ($codeRun >= 400 || !$resRun || empty($resRun['id'])) {
  json_fail('Run create error: ' . ($errRun ?: json_encode($resRun)), $codeRun ?: 500);
}
$runId = $resRun['id'];

// Poll run status
$maxWait = 60; // seconds
$start = time();
$status = $resRun['status'] ?? 'queued';
while (!in_array($status, ['completed', 'failed', 'cancelled', 'expired'])) {
  if ((time() - $start) > $maxWait) {
    json_fail('Run polling timeout.', 504);
  }
  usleep(700000); // 0.7s
  [$resCheck, $codeCheck, $errCheck] = curl_json("$API_BASE/threads/$threadId/runs/$runId", 'GET', null, $headers);
  if ($codeCheck >= 400 || !$resCheck) {
    json_fail('Run status error: ' . ($errCheck ?: json_encode($resCheck)), $codeCheck ?: 500);
  }
  $status = $resCheck['status'] ?? 'queued';
}

if ($status !== 'completed') {
  json_fail("Run ended with status: $status", 500);
}

// Fetch latest assistant message
[$resList, $codeList, $errList] = curl_json("$API_BASE/threads/$threadId/messages?order=desc&limit=1", 'GET', null, $headers);
if ($codeList >= 400 || !$resList || empty($resList['data'][0])) {
  json_fail('Messages fetch error: ' . ($errList ?: json_encode($resList)), $codeList ?: 500);
}
$latest = $resList['data'][0];
$reply = '';
if (!empty($latest['content'][0]['text']['value'])) {
  $reply = $latest['content'][0]['text']['value'];
} else {
  // Fallback: try to assemble all text parts
  if (!empty($latest['content']) && is_array($latest['content'])) {
    foreach ($latest['content'] as $c) {
      if (!empty($c['text']['value'])) {
        $reply .= $c['text']['value'] . "\n";
      }
    }
    $reply = trim($reply);
  }
}

echo json_encode([
  'ok'        => true,
  'reply'     => $reply ?: '(empty assistant reply)',
  'thread_id' => $threadId
], JSON_UNESCAPED_UNICODE);

