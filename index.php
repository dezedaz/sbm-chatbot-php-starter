<?php
// --- SAME LOGIC AS BEFORE, UI STRINGS IN ENGLISH ONLY ---
session_start();

// Read environment
$AZURE_ENDPOINT = rtrim(getenv('AZURE_ENDPOINT') ?: '', '/');
$AZURE_API_KEY  = getenv('AZURE_API_KEY') ?: '';
$ASSISTANT_ID   = getenv('ASSISTANT_ID') ?: '';
$API_VERSION    = '2024-07-01-preview';

// Helper to call Azure OpenAI (Assistants API on Azure)
function aoai_request(string $method, string $url, array $payload = null) {
    global $AZURE_API_KEY;
    $ch = curl_init($url);

    $headers = [
        'api-key: ' . $AZURE_API_KEY,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 60,
    ];

    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }

    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [$status, $resp, $err];
}

// Basic guardrails for missing config
$missing = [];
if (!$AZURE_ENDPOINT) $missing[] = 'AZURE_ENDPOINT';
if (!$AZURE_API_KEY)  $missing[] = 'AZURE_API_KEY';
if (!$ASSISTANT_ID)   $missing[] = 'ASSISTANT_ID';

// Reset thread if asked
if (isset($_GET['reset'])) {
    unset($_SESSION['thread_id']);
    header('Location: /');
    exit;
}

$errorBanner = '';
$messagesToShow = [];

// Handle send
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userMessage = trim($_POST['message'] ?? '');

    if ($userMessage === '') {
        $errorBanner = 'Please type a question.';
    } elseif (!empty($missing)) {
        $errorBanner = 'Missing configuration: ' . implode(', ', $missing) . '.';
    } else {
        // Ensure thread exists in session
        if (empty($_SESSION['thread_id'])) {
            $createThreadUrl = "{$AZURE_ENDPOINT}/openai/assistants/{$API_VERSION}/threads";
            [$st, $body, $err] = aoai_request('POST', $createThreadUrl, []);
            if ($st >= 200 && $st < 300) {
                $data = json_decode($body, true);
                $_SESSION['thread_id'] = $data['id'] ?? null;
            } else {
                $errorBanner = "THREAD create error: HTTP {$st} — {$body}";
            }
        }

        // If we have a thread, add the user message, start a run, poll, then fetch messages
        if (empty($errorBanner) && !empty($_SESSION['thread_id'])) {
            $threadId = $_SESSION['thread_id'];

            // 1) Add message to thread
            $addMsgUrl = "{$AZURE_ENDPOINT}/openai/assistants/{$API_VERSION}/threads/{$threadId}/messages";
            $payload = [
                'role'    => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $userMessage]
                ],
            ];
            [$st, $body, $err] = aoai_request('POST', $addMsgUrl, $payload);
            if (!($st >= 200 && $st < 300)) {
                $errorBanner = "MESSAGE add error: HTTP {$st} — {$body}";
            }

            // 2) Create a run
            if (empty($errorBanner)) {
                $runUrl = "{$AZURE_ENDPOINT}/openai/assistants/{$API_VERSION}/threads/{$threadId}/runs";
                $payload = ['assistant_id' => $ASSISTANT_ID];
                [$st, $body, $err] = aoai_request('POST', $runUrl, $payload);
                if ($st >= 200 && $st < 300) {
                    $run = json_decode($body, true);
                    $runId = $run['id'] ?? null;

                    // 3) Poll run until completed (or terminal)
                    if ($runId) {
                        $getRunUrl = "{$AZURE_ENDPOINT}/openai/assistants/{$API_VERSION}/threads/{$threadId}/runs/{$runId}";
                        $tries = 0;
                        $status = $run['status'] ?? 'queued';
                        while (!in_array($status, ['completed','failed','expired','cancelled','incomplete'], true) && $tries < 45) {
                            usleep(800000); // 0.8s
                            [$st2, $b2, $e2] = aoai_request('GET', $getRunUrl);
                            if ($st2 >= 200 && $st2 < 300) {
                                $r2 = json_decode($b2, true);
                                $status = $r2['status'] ?? $status;
                            } else {
                                $errorBanner = "RUN poll error: HTTP {$st2} — {$b2}";
                                break;
                            }
                            $tries++;
                        }

                        if (empty($errorBanner) && $status !== 'completed') {
                            $errorBanner = "Run ended with status: {$status}.";
                        }

                        // 4) List last messages (latest first)
                        if (empty($errorBanner)) {
                            $listUrl = "{$AZURE_ENDPOINT}/openai/assistants/{$API_VERSION}/threads/{$threadId}/messages?order=desc&limit=6";
                            [$st3, $b3, $e3] = aoai_request('GET', $listUrl);
                            if ($st3 >= 200 && $st3 < 300) {
                                $res = json_decode($b3, true);
                                $messagesToShow = $res['data'] ?? [];
                            } else {
                                $errorBanner = "MESSAGES list error: HTTP {$st3} — {$b3}";
                            }
                        }
                    } else {
                        $errorBanner = 'Could not obtain run id.';
                    }
                } else {
                    $errorBanner = "RUN create error: HTTP {$st} — {$body}";
                }
            }
        }
    }
}

// If no fetch was just performed, but there is a thread, show recent history
if (empty($messagesToShow) && !empty($_SESSION['thread_id']) && empty($errorBanner)) {
    $threadId = $_SESSION['thread_id'];
    $listUrl = "{$AZURE_ENDPOINT}/openai/assistants/{$API_VERSION}/threads/{$threadId}/messages?order=desc&limit=6";
    [$st, $b, $e] = aoai_request('GET', $listUrl);
    if ($st >= 200 && $st < 300) {
        $res = json_decode($b, true);
        $messagesToShow = $res['data'] ?? [];
    }
}

// Helper to pretty print assistant/user messages
function render_msg_bubble(array $msg) {
    $role = $msg['role'] ?? 'assistant';
    $text = '';
    if (!empty($msg['content']) && is_array($msg['content'])) {
        foreach ($msg['content'] as $c) {
            if (($c['type'] ?? '') === 'text') {
                $text .= $c['text']['value'] ?? '';
            }
        }
    }
    $time = '';
    if (!empty($msg['created_at'])) {
        $time = date('H:i', strtotime($msg['created_at']));
    }
    $cls = $role === 'user' ? 'bubble user' : 'bubble bot';
    return "<div class=\"{$cls}\"><div class=\"msg\">".htmlspecialchars($text)."</div></div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SBM Chatbot</title>
<style>
    :root {
        --bg:#0f172a;           /* slate-900 */
        --panel:#111827;        /* gray-900 */
        --card:#1f2937;         /* gray-800 */
        --text:#e5e7eb;         /* gray-200 */
        --muted:#9ca3af;        /* gray-400 */
        --blue:#3b82f6;         /* blue-500 */
        --blue-700:#1d4ed8;     /* blue-700 */
        --danger:#b91c1c;       /* red-700 */
    }
    * { box-sizing: border-box; }
    body {
        margin:0; background:var(--bg); color:var(--text);
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, "Apple Color Emoji","Segoe UI Emoji";
        display:flex; min-height:100vh; align-items:center; justify-content:center;
    }
    .wrap { width:min(920px, 92vw); background:#0b1220; border-radius:14px; box-shadow: 0 10px 40px rgba(0,0,0,.35); }
    .header { padding:22px 26px; background:var(--panel); border-radius:14px 14px 0 0; text-align:center; font-weight:700; letter-spacing:.3px; }
    .content { padding:22px; }
    .footnote { font-size:12px; color:var(--muted); display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; }
    .footnote span { background: #0d1325; padding:6px 10px; border-radius:8px; }
    .row { display:flex; gap:10px; }
    .row input[type=text] {
        flex:1; background:var(--card); color:var(--text);
        border:1px solid #263043; border-radius:9px; padding:12px 14px; outline: none;
    }
    button {
        background:var(--blue); color:white; border:0; padding:12px 16px; border-radius:9px; cursor:pointer;
        font-weight:600;
    }
    button:hover { background:var(--blue-700); }
    .reset { background:#374151; }
    .reset:hover { background:#4b5563; }
    .error {
        background: #2a0f12; border:1px solid #7f1d1d; color:#fecaca;
        padding:12px 14px; border-radius:10px; margin-bottom:12px; white-space:pre-wrap;
    }
    .history { display:flex; flex-direction:column; gap:10px; margin-bottom:12px; max-height:52vh; overflow:auto; }
    .bubble { max-width:80%; padding:10px 12px; border-radius:12px; }
    .bubble.user { margin-left:auto; background: #123262; }
    .bubble.bot  { background: #1a2238; }
    .muted { color:var(--muted); font-size:12px; margin-top:6px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="header">SBM Chatbot</div>

    <div class="content">
        <?php if (!empty($missing)): ?>
            <div class="error">
                Missing configuration. Please set environment variables on Render:
                AZURE_ENDPOINT, AZURE_API_KEY, ASSISTANT_ID.
            </div>
        <?php endif; ?>

        <?php if ($errorBanner): ?>
            <div class="error"><?= htmlspecialchars($errorBanner) ?></div>
        <?php endif; ?>

        <div class="history">
            <?php
            if (!empty($messagesToShow)) {
                // API returns newest first; show oldest first
                foreach (array_reverse($messagesToShow) as $m) {
                    echo render_msg_bubble($m);
                }
            }
            ?>
        </div>

        <form method="post" class="row" autocomplete="off">
            <input type="text" name="message" placeholder="Type your question..." />
            <button type="submit">Send</button>
            <a class="reset" href="/?reset=1" style="text-decoration:none;padding:12px 16px;border-radius:9px;color:#fff;">Reset</a>
        </form>

        <div class="footnote">
            <span>Endpoint: <?= htmlspecialchars($AZURE_ENDPOINT ?: 'not set') ?></span>
            <span>API: <?= htmlspecialchars($API_VERSION) ?></span>
            <span>Assistant: <?= htmlspecialchars($ASSISTANT_ID ? substr($ASSISTANT_ID, 0, 14) . '…' : 'not set') ?></span>
        </div>
    </div>
</div>
</body>
</html>




