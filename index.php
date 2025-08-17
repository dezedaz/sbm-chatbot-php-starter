<?php
session_start();

$endpoint = "https://sbmchatbot.openai.azure.com/";
$apiKey = "TON_API_KEY_ICI"; // <-- Mets ta clé Azure OpenAI
$assistantId = "asst_WrGbhVrIqfqOqg5g1omyddBB";

// Initialise l'historique
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

// Réinitialiser l’historique
if (isset($_POST['reset'])) {
    $_SESSION['history'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Quand l’utilisateur envoie un message
if (isset($_POST['user_input']) && !empty(trim($_POST['user_input']))) {
    $userInput = trim($_POST['user_input']);

    // Ajout du message utilisateur dans l'historique
    $_SESSION['history'][] = ["role" => "user", "content" => $userInput];

    // --- Création d’un thread Azure ---
    $threadCurl = curl_init();
    curl_setopt_array($threadCurl, [
        CURLOPT_URL => $endpoint . "openai/threads?api-version=2024-08-01-preview",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "api-key: $apiKey"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "messages" => [
                ["role" => "user", "content" => $userInput]
            ]
        ])
    ]);
    $threadResponse = curl_exec($threadCurl);
    curl_close($threadCurl);

    $threadData = json_decode($threadResponse, true);
    $threadId = $threadData["id"] ?? null;

    $assistantReply = "⚠️ Erreur : pas de réponse générée.";

    if ($threadId) {
        // --- Lancer l’exécution ---
        $runCurl = curl_init();
        curl_setopt_array($runCurl, [
            CURLOPT_URL => $endpoint . "openai/threads/$threadId/runs?api-version=2024-08-01-preview",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "api-key: $apiKey"
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                "assistant_id" => $assistantId
            ])
        ]);
        $runResponse = curl_exec($runCurl);
        curl_close($runCurl);

        $runData = json_decode($runResponse, true);
        $runId = $runData["id"] ?? null;

        if ($runId) {
            // Vérifier jusqu’à ce que l’IA ait fini
            $status = "in_progress";
            while (in_array($status, ["queued", "in_progress"])) {
                usleep(500000); // pause 0.5s

                $statusCurl = curl_init();
                curl_setopt_array($statusCurl, [
                    CURLOPT_URL => $endpoint . "openai/threads/$threadId/runs/$runId?api-version=2024-08-01-preview",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ["api-key: $apiKey"]
                ]);
                $statusResponse = curl_exec($statusCurl);
                curl_close($statusCurl);

                $statusData = json_decode($statusResponse, true);
                $status = $statusData["status"] ?? "error";
            }

            // Quand terminé : récupérer la réponse
            if ($status === "completed") {
                $msgCurl = curl_init();
                curl_setopt_array($msgCurl, [
                    CURLOPT_URL => $endpoint . "openai/threads/$threadId/messages?api-version=2024-08-01-preview",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ["api-key: $apiKey"]
                ]);
                $msgResponse = curl_exec($msgCurl);
                curl_close($msgCurl);

                $msgData = json_decode($msgResponse, true);
                if (isset($msgData["data"][0]["content"][0]["text"]["value"])) {
                    $assistantReply = $msgData["data"][0]["content"][0]["text"]["value"];
                }
            } else {
                $assistantReply = "⚠️ Erreur : statut final = $status";
            }
        }
    }

    // Ajout réponse bot
    $_SESSION['history'][] = ["role" => "assistant", "content" => $assistantReply];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SBM Chatbot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1e1e1e; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .chat-container { width: 420px; background: #2c2c2c; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; height: 85vh; }
        .chat-box { flex: 1; overflow-y: auto; margin-bottom: 10px; }
        .message { padding: 8px 12px; border-radius: 8px; margin: 6px 0; max-width: 75%; font-size: 14px; }
        .user { background: #0078d7; color: #fff; align-self: flex-end; }
        .assistant { background: #444; color: #fff; align-self: flex-start; }
        .chat-input { display: flex; }
        .chat-input input { flex: 1; padding: 10px; border: none; border-radius: 6px; margin-right: 8px; }
        .chat-input button { padding: 10px; border: none; border-radius: 6px; background: #0078d7; color: #fff; cursor: pointer; }
        .reset-btn { background: #d9534f; margin-top: 5px; width: 100%; border: none; border-radius: 6px; padding: 10px; color: #fff; cursor: pointer; }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-box" id="chat-box">
            <?php foreach ($_SESSION['history'] as $msg): ?>
                <div class="message <?php echo $msg['role']; ?>">
                    <?php echo htmlspecialchars($msg['content']); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="POST" class="chat-input">
            <input type="text" name="user_input" placeholder="Type your message..." autocomplete="off">
            <button type="submit">Send</button>
        </form>
        <form method="POST">
            <button type="submit" name="reset" class="reset-btn">Reset Chat</button>
        </form>
    </div>
    <script>
        const chatBox = document.getElementById('chat-box');
        chatBox.scrollTop = chatBox.scrollHeight;
    </script>
</body>
</html>


