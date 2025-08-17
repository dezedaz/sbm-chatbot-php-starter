<?php
session_start();

if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

$AZURE_API_KEY  = getenv("AZURE_API_KEY");
$AZURE_ENDPOINT = rtrim(getenv("AZURE_ENDPOINT") ?: '', '/');
$ASSISTANT_ID   = getenv("ASSISTANT_ID");

if (!$AZURE_API_KEY || !$AZURE_ENDPOINT || !$ASSISTANT_ID) {
    http_response_code(500);
    echo "<pre>Configuration manquante. Vérifie les variables AZURE_API_KEY, AZURE_ENDPOINT, ASSISTANT_ID dans Render.</pre>";
    exit;
}

$THREADS_BASE = $AZURE_ENDPOINT . "/openai/assistants/v1/threads";

function http_call($method, $url, $headers, $payload = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 60,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) throw new Exception("cURL error: $err");
    if ($code < 200 || $code >= 300) throw new Exception("HTTP $code: $res");
    return json_decode($res, true);
}

if (isset($_GET['reset'])) {
    session_destroy();
    header("Location: /");
    exit;
}

function ensure_thread_id($threads_base, $headers) {
    if (!isset($_SESSION['azure_thread_id'])) {
        $create = http_call('POST', $threads_base, $headers, json_encode(['messages' => []]));
        $_SESSION['azure_thread_id'] = $create['id'];
    }
    return $_SESSION['azure_thread_id'];
}

$AZURE_HEADERS = [
    "api-key: " . $AZURE_API_KEY,
    "Content-Type: application/json"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userMessage = trim($_POST['message'] ?? '');
    if ($userMessage !== '') {
        $_SESSION['history'][] = ['role' => 'user', 'content' => $userMessage];
        try {
            $threadId = ensure_thread_id($THREADS_BASE, $AZURE_HEADERS);

            // Ajouter message utilisateur
            http_call('POST', "$THREADS_BASE/$threadId/messages", $AZURE_HEADERS, json_encode([
                "role"    => "user",
                "content" => $userMessage
            ]));

            // Lancer le run
            $run = http_call('POST', "$THREADS_BASE/$threadId/runs", $AZURE_HEADERS, json_encode([
                "assistant_id" => $ASSISTANT_ID,
                "instructions" => "Réponds uniquement à partir des fichiers SBM ajoutés. Si l'information n'existe pas, dis-le poliment."
            ]));
            $runId = $run['id'];

            // Polling jusqu'à completion
            $status = $run['status'] ?? '';
            $tries = 0;
            while ($status !== 'completed' && $status !== 'failed' && $status !== 'cancelled') {
                $tries++;
                usleep(400000 + min($tries * 150000, 1500000));
                $statusRes = http_call('GET', "$THREADS_BASE/$threadId/runs/$runId", $AZURE_HEADERS);
                $status = $statusRes['status'] ?? '';
                if ($tries > 60) throw new Exception("Timeout waiting for run.");
            }
            if ($status !== 'completed') throw new Exception("Run status: $status");

            // Récupérer la réponse
            $list = http_call('GET', "$THREADS_BASE/$threadId/messages", $AZURE_HEADERS);
            $botReply = "Pas de réponse.";
            foreach ($list['data'] as $m) {
                if (($m['role'] ?? '') === 'assistant') {
                    $contentArr = $m['content'][0] ?? null;
                    if ($contentArr && isset($contentArr['text']['value'])) {
                        $botReply = $contentArr['text']['value'];
                    }
                    break;
                }
            }
            $_SESSION['history'][] = ['role' => 'assistant', 'content' => $botReply];
        } catch (Exception $e) {
            $_SESSION['history'][] = ['role' => 'assistant', 'content' =>
                "⚠️ Erreur : " . htmlspecialchars($e->getMessage())
            ];
        }
    }
    header("Location: /");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SBM Chatbot</title>
    <link rel="icon" href="/sbm.png">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <img src="/sbm.png" alt="SBM Logo" class="logo">
    <div class="chat">
        <?php foreach ($_SESSION['history'] as $msg): ?>
            <div class="bubble <?= $msg['role'] === 'user' ? 'user' : 'assistant' ?>">
                <?= htmlspecialchars($msg['content']) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="post" class="row">
        <input type="text" name="message" placeholder="Pose une question..." required>
        <button type="submit">Envoyer</button>
    </form>
    <form method="get">
        <button type="submit" name="reset" value="1" class="reset-btn">Réinitialiser</button>
    </form>
    <script>window.scrollTo(0, document.body.scrollHeight);</script>
</body>
</html>
