<?php
// api.php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
  exit;
}

function envOrDie($k) {
  $v = getenv($k);
  if (!$v) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>"Missing env var $k"]);
    exit;
  }
  return $v;
}

$endpoint     = rtrim(envOrDie('AZURE_ENDPOINT'), '/');            // ex: https://sbmchatbot.openai.azure.com
$apiKey       = envOrDie('AZURE_API_KEY');
$assistantId  = envOrDie('ASSISTANT_ID');
$apiVersion   = '2024-07-01-preview';
$base         = $endpoint . '/openai/assistants/v1';               // ✅ chemin correct

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$userMessage = trim($in['message'] ?? 'Hello');

function callAzure($method, $url, $data = null, $apiKey = '') {
  $ch = curl_init($url);
  $headers = [
    'Content-Type: application/json',
    'api-key: ' . $apiKey
  ];
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  if ($data !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  }
  $body = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($body === false) {
    $err = curl_error($ch);
    curl_close($ch);
    return [0, null, $err];
  }
  curl_close($ch);
  return [$status, json_decode($body, true), null];
}

// 1) Create thread
$createThreadUrl = "{$base}/threads?api-version={$apiVersion}";
[$st, $json, $err] = callAzure('POST', $createThreadUrl, [], $apiKey);
if ($st !== 200 || empty($json['id'])) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"Thread create error: HTTP $st", 'details'=>$json ?: $err]);
  exit;
}
$threadId = $json['id'];

// 2) Add user message
$addMsgUrl = "{$base}/threads/{$threadId}/messages?api-version={$apiVersion}";
[$st, $json, $err] = callAzure('POST', $addMsgUrl, [
  'role'    => 'user',
  'content' => $userMessage,
], $apiKey);
if ($st !== 200) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"Add message error: HTTP $st", 'details'=>$json ?: $err]);
  exit;
}

// 3) Create run
$runUrl = "{$base}/threads/{$threadId}/runs?api-version={$apiVersion}";
[$st, $json, $err] = callAzure('POST', $runUrl, [
  'assistant_id' => $assistantId
], $apiKey);
if ($st !== 200 || empty($json['id'])) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"Run create error: HTTP $st", 'details'=>$json ?: $err]);
  exit;
}
$runId = $json['id'];

// 4) Poll run until completed (ou failed)
$pollUrl = "{$base}/threads/{$threadId}/runs/{$runId}?api-version={$apiVersion}";
$maxTries = 30; // ~30s max
$state = null;
for ($i = 0; $i < $maxTries; $i++) {
  [$st, $json, $err] = callAzure('GET', $pollUrl, null, $apiKey);
  if ($st !== 200) break;
  $state = $json['status'] ?? null;
  if ($state === 'completed') break;
  if ($state === 'failed' || $state === 'cancelled' || $state === 'expired') break;
  usleep(800000); // 0.8s
}
if ($state !== 'completed') {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"Run not completed", 'status'=>$state, 'details'=>$json ?? null]);
  exit;
}

// 5) Get last assistant message
$getMsgsUrl = "{$base}/threads/{$threadId}/messages?api-version={$apiVersion}&order=desc&limit=1";
[$st, $json, $err] = callAzure('GET', $getMsgsUrl, null, $apiKey);
if ($st !== 200 || empty($json['data'][0])) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"Fetch message error: HTTP $st", 'details'=>$json ?: $err]);
  exit;
}
$latest = $json['data'][0];
$reply  = '';
if (($latest['role'] ?? '') === 'assistant' && !empty($latest['content'][0]['text']['value'])) {
  $reply = $latest['content'][0]['text']['value'];
}

echo json_encode([
  'ok' => true,
  'reply' => $reply,
  'thread_id' => $threadId,
  'run_id' => $runId
]);


