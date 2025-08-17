<?php
// ====== SBM Chatbot (Azure OpenAI Assistants via Azure endpoints) ======
session_start();

// ---------- Config depuis Render ----------
$AZURE_ENDPOINT = rtrim(getenv('AZURE_ENDPOINT') ?: '', '/');   // ex: https://sbmchatbot.openai.azure.com
$AZURE_API_KEY  = getenv('AZURE_API_KEY') ?: '';
$ASSISTANT_ID   = getenv('ASSISTANT_ID') ?: '';
$API_VERSION    = '2024-05-01-preview'; // version Assistants supportée sur Azure

// ---------- Helper HTTP ----------
function http_json($method, $url, $headers, $payload = null) {
    $ch = curl_init($url);
    $common = [
        "api-key: " . $GLOBALS['AZURE_API_KEY'],
        "Content-Type: application/json"
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => array_merge($common, $headers),
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$http, $res, $err];
}

// ---------- Vérifs config ----------
$missing = [];
if (!$AZURE_ENDPOINT) $missing[] = 'AZURE_ENDPOINT';
if (!$AZURE_API_KEY)  $missing[] = 'AZURE_API_KEY';
if (!$ASSISTANT_ID)   $missing[] = 'ASSISTANT_ID';

// Initialise l'historique UI
if (!isset($_SESSION['history'])) $_SESSION['history'] = [];

if (isset($_GET['reset'])) {
    session_destroy();
    header('Location: /');
    exit;
}

// ---------- Routes API Azure (format Azure) ----------
function url_threads_base() {
    return $GLOBALS['AZURE_ENDPOINT'] . "/openai/threads?api-version=" . $GLOBALS['API_VERSION'];
}
function url_thread_messages($threadId) {
    return $GLOBALS['AZURE_ENDPOINT'] . "/openai/threads/$threadId/messages?api-version=" . $GLOBALS['API_VERSION'];
}
function url_thread_runs($threadId) {
    return $GLOBALS['AZURE_ENDPOINT'] . "/openai/threads/$threadId/runs?api-version=" . $GLOBALS['API_VERSION'];
}
function url_thread_run($threadId, $runId) {
    return $GLOBALS['AZURE_ENDPOINT'] . "/openai/threads/$threadId/runs/$runId?api-version=" . $GLOBALS['API_VERSION'];
}
function url_list_messages($threadId) {
    // order=desc pour récupérer les derniers messages rapidement
    return $GLOBALS['AZURE_ENDPOINT'] . "/openai/threads/$threadId/messages?order=desc&limit=20&api-version=" . $GLOBALS['API_VERSION'];
}

// ---------- Envoi message ----------
$errorBubble = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $userMessage = trim($_POST['message']);
    if ($userMessage !== '') {
        $_SESSION['history'][] = ['role' => 'user', 'content' => $userMessage];

        if (!empty($missing)) {
            $errorBubble = "Configuration manquante : " . implode(', ', $missing) . ".";
        } else {
            // 1) Créer un thread une seule fois et le garder en session
            if (empty($_SESSION['thread_id'])) {
                [$code, $body, $err] = http_json('POST', url_threads_base(), [], (object)[]);
                if ($code >= 200 && $code < 300) {
                    $json = json_decode($body, true);
                    $_SESSION['thread_id'] = $json['id'] ?? null;
                } else {
                    $errorBubble = "Erreur THREAD ($code) : " . htmlspecialchars($body ?: $err);
                }
            }

            // 2) Ajouter le message utilisateur au thread
            if (!$errorBubble && !empty($_SESSION['thread_id'])) {
                $payload = [
                    'role'    => 'user',
                    'content' => $userMessage
                ];
                [$code, $body, $err] = http_json('POST', url_thread_messages($_SESSION['thread_id']), [], $payload);
                if (!($code >= 200 && $code < 300)) {
                    $errorBubble = "Erreur MESSAGE ($code) : " . htmlspecialchars($body ?: $err);
                }
            }

            // 3) Lancer le run
            if (!$errorBubble && !empty($_SESSION['thread_id'])) {
                $payload = ['assistant_id' => $ASSISTANT_ID];
                [$code, $body, $err] = http_json('POST', url_thread_runs($_SESSION['thread_id']), [], $payload);
                if ($code >= 200 && $code < 300) {
                    $run = json_decode($body, true);
                    $runId = $run['id'] ?? null;

                    // 4) Polling du statut
                    $status = $run['status'] ?? 'queued';
                    $t0 = time();
                    while (in_array($status, ['queued','in_progress','requires_action']) && (time() - $t0) < 45) {
                        usleep(800000); // 0.8s
                        [$c2, $b2, $e2] = http_json('GET', url_thread_run($_SESSION['thread_id'], $runId), [], null);
                        if (!($c2 >= 200 && $c2 < 300)) { break; }
                        $run2 = json_decode($b2, true);
                        $status = $run2['status'] ?? $status;
                    }

                    if ($status !== 'completed') {
                        $errorBubble = "Exécution non terminée (statut: $status).";
                    } else {
                        // 5) Récupérer le dernier message assistant
                        [$c3, $b3, $e3] = http_json('GET', url_list_messages($_SESSION['thread_id']), [], null);
                        if ($c3 >= 200 && $c3 < 300) {
                            $list = json_decode($b3, true);
                            $botReply = null;
                            if (!empty($list['data'])) {
                                foreach ($list['data'] as $msg) {
                                    if (($msg['role'] ?? '') === 'assistant') {
                                        // Concatène les blocs de contenu texte
                                        $parts = $msg['content'] ?? [];
                                        $txt = [];
                                        foreach ($parts as $p) {
                                            if (($p['type'] ?? '') === 'text' && isset($p['text']['value'])) {
                                                $txt[] = $p['text']['value'];
                                            }
                                        }
                                        $botReply = trim(implode("\n", $txt));
                                        if ($botReply !== '') break;
                                    }
                                }
                            }
                            if ($botReply === null || $botReply === '') {
                                $errorBubble = "Erreur : pas de réponse générée.";
                            } else {
                                $_SESSION['history'][] = ['role' => 'assistant', 'content' => $botReply];
                            }
                        } else {
                            $errorBubble = "Erreur LIST_MESSAGES ($c3) : " . htmlspecialchars($b3 ?: $e3);
                        }
                    }
                } else {
                    $errorBubble = "Erreur RUN ($code) : " . htmlspecialchars($body ?: $err);
                }
            }
        }

        if ($errorBubble) {
            $_SESSION['history'][] = ['role' => 'error', 'content' => $errorBubble];
        }

        header('Location: /');
        exit;
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>SBM Chatbot</title>
<link rel="icon" href="/sbm_logo.png">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    :root {
        --bg: #111;
        --panel: #2a2a2a;
        --bubble-user: #1e88e5;
        --bubble-bot: #3a3a3a;
        --bubble-err: #4a3b2a;
        --text: #fff;
        --muted: #bbb;
    }
    body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,Segoe UI,Arial,sans-serif;display:flex;min-height:100dvh;align-items:center;justify-content:center}
    .card{width:min(640px,94vw);background:var(--panel);border-radius:14px;box-shadow:0 8px 28px rgba(0,0,0,.35);overflow:hidden}
    .head{display:flex;align-items:center;gap:12px;padding:14px 18px;background:#3a3a3a}
    .head img{height:22px}
    .feed{padding:16px;display:flex;flex-direction:column;gap:10px;max-height:64vh;overflow:auto}
    .bubble{padding:10px 12px;border-radius:12px;max-width:88%;line-height:1.4;font-size:15px;white-space:pre-wrap}
    .user{align-self:flex-end;background:var(--bubble-user)}
    .assistant{align-self:flex-start;background:var(--bubble-bot)}
    .error{align-self:flex-start;background:var(--bubble-err);color:#ffd9a7}
    .row{display:flex;gap:8px;padding:12px;background:#2c2c2c}
    input[type=text]{flex:1;border:none;border-radius:8px;padding:12px;background:#1a1a1a;color:var(--text);outline:none}
    button{background:#1e88e5;color:#fff;border:none;border-radius:8px;padding:12px 16px;cursor:pointer}
    .foot{padding:8px 12px;color:var(--muted);font-size:12px;text-align:center}
</style>
</head>
<body>
<div class="card">
    <div class="head">
        <img src="/sbm_logo.png" alt="SBM">
        <strong>SBM Chatbot</strong>
    </div>

    <div class="feed">
        <?php if (!empty($missing)): ?>
            <div class="bubble error">Configuration manquante. Ajoute ces variables sur Render : <?=htmlspecialchars(implode(', ', $missing))?>.</div>
        <?php endif; ?>

        <?php foreach ($_SESSION['history'] as $m): ?>
            <div class="bubble <?= $m['role']==='user'?'user':($m['role']==='assistant'?'assistant':'error') ?>">
                <?= nl2br(htmlspecialchars($m['content'])) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <form class="row" method="post" action="/">
        <input type="text" name="message" placeholder="Pose ta question..." autocomplete="off" required>
        <button type="submit">Envoyer</button>
        <a href="/?reset=1"><button type="button" style="background:#555">Réinitialiser</button></a>
    </form>

    <div class="foot">
        Astuce : si tu vois <em>Erreur THREAD/Run 404</em>, vérifie l’URL AZURE_ENDPOINT (format <code>https://...openai.azure.com</code>) et l’<code>ASSISTANT_ID</code>.
    </div>
</div>
</body>
</html>

