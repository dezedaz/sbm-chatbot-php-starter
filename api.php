<?php
// api.php — JSON-only endpoint for the chatbot (Azure OpenAI Assistants v1)
// Env: AZURE_ENDPOINT, AZURE_API_KEY, ASSISTANT_ID
declare(strict_types=1);

@session_set_cookie_params([
  'lifetime' => 0, 'path' => '/', 'httponly' => true, 'secure' => isset($_SERVER['HTTPS']),
  'samesite' => 'Lax'
]);
@session_start();

header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $message, array $extra = []) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $message, 'details' => $extra], JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fail(405, 'Method Not Allowed');
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
  fail(400, 'Invalid JSON body');
}

$reset   = !empty($data['reset']);
$message = isset($data['message']) ? trim((string)$data['message']) : '';

$endpoint     = rtrim(getenv('AZURE_ENDPOINT') ?: $_ENV['AZURE_ENDPOINT'] ?? '', '/');
$apiKey       = getenv('AZURE_API_KEY') ?: $_ENV['AZURE_API_KEY'] ?? '';
$assistant_id = getenv('ASSISTANT_ID') ?: $_ENV['ASSISTANT_ID'] ?? '';
$api_version  = '2024-07-01-preview';

// ✅ Azure OpenAI Assistants use /openai/assistants/v1/...
$base = $endpoint . "/openai/assistants/v1";

if (!$endpoint || !$apiKey || !$assistant_id) {
  fail(500, 'Missing configuration. Check AZURE_ENDPOINT, AZURE_API_KEY, ASSISTANT_ID.');
}

if ($reset) {
  unset($_SESSION['thread_id']);
  echo json_encode(['ok' => true, 'reset' => true]);
  exit;
}

if ($message === '') {
  fail(400, 'Message text is required');
}

function az_post(string $url, array $payload, string $apiKey) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'api-key: ' . $apiKey
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
  ]);
  $body = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($body === false) {
    $err = curl_error($ch);
    curl_close($ch);
    fail(502, 'cURL error', ['curl_error' => $err]);
  }
  curl_close($ch);
  return [$status, $body];
}

function az_get(string $url, string $apiKey) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => [
      'api-key: ' . $apiKey
    ],
  ]);
  $body = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($body === false) {
    $err = curl_error($ch);
    curl_close($ch);
    fail(502, 'cURL error', ['curl_error' => $err]);
  }
  curl_close($ch);
  return [$status, $body];
}

// 1) Ensure we have a thread
$thread_id = $_SESSION['thread_id'] ?? null;
if (!$thread_id) {
  $url = "{$base}/threads?api-version={$api_version}";
  [$st, $body] = az_post($url, [], $apiKey);
  $json = json_decode($body, true);
  if ($st < 200 || $st >= 300 || !isset($json['id'])) {
    fail($st, 'Thread create error', ['response' => $json ?: $body]);
  }
  $thread_id = $json['id'];
  $_SESSION['thread_id'] = $thread_id;
}

// 2) Add user message
$url = "{$base}/threads/{$thread_id}/messages?api-version={$api_version}";
$payload = [
  'role' => 'user',
  'content' => [
    ['type' => 'text', 'text' => $message]
  ],
];
[$st, $body] = az_post($url, $payload, $apiKey);
if ($st < 200 || $st >= 300) {
  fail($st, 'Message create error', ['response' => json_decode($body, true) ?: $body]);
}

// 3) Create a run
$url = "{$base}/threads/{$thread_id}/runs?api-version={$api_version}";
$payload = ['assistant_id' => $assistant_id];
[$st, $body] = az_post($url, $payload, $apiKey);
$run = json_decode($body, true);
if ($st < 200 || $st >= 300 || !isset($run['id'])) {
  fail($st, 'Run create error', ['response' => $run ?: $body]);
}
$run_id = $run['id'];

// 4) Poll until completed
$poll_url = "{$base}/threads/{$thread_id}/runs/{$run_id}?api-version={$api_version}";
$deadline = microtime(true) + 45;
$status = $run['status'] ?? 'queued';
while (in_array($status, ['queued','in_progress','requires_action'], true)) {
  if (microtime(true) > $deadline) {
    fail(504, 'Run timed out', ['last_status' => $status]);
  }
  usleep(500000);
  [$st, $body] = az_get($poll_url, $apiKey);
  $run = json_decode($body, true);
  if ($st < 200 || $st >= 300) {
    fail($st, 'Run poll error', ['response' => $run ?: $body]);
  }
  $status = $run['status'] ?? 'unknown';
}
if ($status !== 'completed') {
  fail(502, 'Run did not complete', ['final_status' => $status, 'run' => $run]);
}

// 5) Fetch the latest assistant message
$messages_url = "{$base}/threads/{$thread_id}/messages?api-version={$api_version}&order=desc&limit=5";
[$st, $body] = az_get($messages_url, $apiKey);
$msgs = json_decode($body, true);
if ($st < 200 || $st >= 300) {
  fail($st, 'Fetch messages error', ['response' => $msgs ?: $body]);
}

$reply = '';
if (!empty($msgs['data']) && is_array($msgs['data'])) {
  foreach ($msgs['data'] as $m) {
    if (($m['role'] ?? '') === 'assistant' && !empty($m['content'][0]['text']['value'])) {
      $reply = (string)$m['content'][0]['text']['value'];
      break;
    }
  }
}
if ($reply === '') {
  $reply = "No response generated.";
}

echo json_encode(['ok' => true, 'thread_id' => $thread_id, 'reply' => $reply], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

