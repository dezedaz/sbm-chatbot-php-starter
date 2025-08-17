<?php
// chat_api.php — JSON endpoint for the BlueGate panel (English code & messages)

header('Content-Type: application/json; charset=utf-8');
session_start();

$endpoint  = rtrim(getenv('AZURE_ENDPOINT') ?: '', '/');   // e.g. https://YOUR-RESOURCE.openai.azure.com
$apiKey    = getenv('AZURE_API_KEY') ?: '';
$assistant = getenv('ASSISTANT_ID') ?: '';
$apiVer    = '2024-07-01-preview';

if (!$endpoint || !$apiKey || !$assistant) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Missing configuration: AZURE_ENDPOINT, AZURE_API_KEY, ASSISTANT_ID.']);
  exit;
}

// Accept JSON or x-www-form-urlencoded
$raw = file_get_contents('php://input');
$payload = $raw ? json_decode($raw, true) : null;
$message = $payload['message'] ?? ($_POST['message'] ?? '');
$message = is_string($message) ? trim($message) : '';
if ($message === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Empty message.']);
  exit;
}

function azureRequest(string $method, string $url, array $body = null, string $apiKey = '') {
  $ch = curl_init($url);
  $headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'api-key: ' . $apiKey,
    'Expect:' // avoid 100-continue issues on some proxies
  ];
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
  ]);
  if ($body !== null) {
    $json = json_encode($body, JSON_UNESCAPED_SLASHES);
    if ($json === '' || $json === false) $json = '{}';
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
  }
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  return [$code, $resp, $err];
}

try {
  // Create (or reuse) a thread per session
  if (empty($_SESSION['thread_id'])) {
    $url = "{$endpoint}/openai/assistants/{$apiVer}/threads?api-version={$apiVer}";
    [$code, $resp] = azureRequest('POST', $url, (object)[], $apiKey);
    if ($code < 200 || $code >= 300) {
      throw new Exception("Create thread failed ({$code}): {$resp}");
    }
    $data = json_decode($resp, true);
    $_SESSION['thread_id'] = $data['id'] ?? null;
    if (!$_SESSION['thread_id']) {
      throw new Exception('Thread id missing from response.');
    }
  }
  $threadId = $_SESSION['thread_id'];

  // Add user message
  $url = "{$endpoint}/openai/assistants/{$apiVer}/threads/{$threadId}/messages?api-version={$apiVer}";
  $body = [
    'role'    => 'user',
    'content' => [['type' => 'text', 'text' => $message]],
  ];
  [$code, $resp] = azureRequest('POST', $url, $body, $apiKey);
  if ($code < 200 || $code >= 300) {
    throw new Exception("Add message failed ({$code}): {$resp}");
  }

  // Create run
  $url  = "{$endpoint}/openai/assistants/{$apiVer}/threads/{$threadId}/runs?api-version={$apiVer}";
  $body = ['assistant_id' => $assistant];
  [$code, $resp] = azureRequest('POST', $url, $body, $apiKey);
  if ($code < 200 || $code >= 300) {
    throw new Exception("Create run failed ({$code}): {$resp}");
  }
  $run = json_decode($resp, true);
  $runId = $run['id'] ?? null;
  if (!$runId) throw new Exception('Run id missing from response.');

  // Poll until completed (timeout ~60s)
  $deadline = time() + 60;
  $status = $run['status'] ?? 'queued';
  while (time() < $deadline && in_array($status, ['queued','in_progress'])) {
    usleep(600000);
    $url = "{$endpoint}/openai/assistants/{$apiVer}/threads/{$threadId}/runs/{$runId}?api-version={$apiVer}";
    [$c2, $b2] = azureRequest('GET', $url, null, $apiKey);
    if ($c2 < 200 || $c2 >= 300) throw new Exception("Run status failed ({$c2}): {$b2}");
    $st = json_decode($b2, true);
    $status = $st['status'] ?? 'failed';
  }
  if ($status !== 'completed') {
    throw new Exception("Run did not complete (status: {$status}).");
  }

  // Fetch last assistant message
  $url = "{$endpoint}/openai/assistants/{$apiVer}/threads/{$threadId}/messages?order=desc&limit=1&api-version={$apiVer}";
  [$mCode, $mResp] = azureRequest('GET', $url, null, $apiKey);
  if ($mCode < 200 || $mCode >= 300) throw new Exception("Fetch messages failed ({$mCode}): {$mResp}");
  $msgs = json_decode($mResp, true);

  $reply = '';
  if (!empty($msgs['data'][0]['content'])) {
    foreach ($msgs['data'][0]['content'] as $part) {
      if (($part['type'] ?? '') === 'text' && isset($part['text']['value'])) {
        $reply .= $part['text']['value'];
      }
    }
    $reply = trim($reply);
  }
  if ($reply === '') $reply = 'No answer produced.';

  echo json_encode(['ok' => true, 'reply' => $reply]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
