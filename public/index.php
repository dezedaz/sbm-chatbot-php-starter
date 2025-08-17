<?php
session_start();

// Configuration Azure
$api_key = "F4GkErnSxB1YyTAtIZ7aIwWirmAFTrClqALkVr2VhRvJWJxqPe32JQQJ99BHACYeBjFXJ3w3AAABACOGO0aZ";
$endpoint = "https://sbmchatbot.openai.azure.com/";
$assistant_id = "asst_WrGbhVrIqfqOqg5g1omyddBB"; // ID de ton assistant Azure

// Initialiser l'historique si vide
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

// Si l'utilisateur envoie un message
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["message"])) {
    $user_message = trim($_POST["message"]);

    // Ajouter le message utilisateur à l'historique
    $_SESSION['history'][] = ["role" => "user", "content" => $user_message];

    // Créer une conversation (thread) côté Azure
    $thread = file_get_contents("https://api.openai.com/v1/threads", false, stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json\r\nAuthorization: Bearer $api_key\r\nOpenAI-Beta: assistants=v1",
            "content" => json_encode([])
        ]
    ]));
    $thread_id = json_decode($thread, true)["id"];

    // Envoyer le message utilisateur dans le thread
    file_get_contents("https://api.openai.com/v1/threads/$thread_id/messages", false, stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json\r\nAuthorization: Bearer $api_key\r\nOpenAI-Beta: assistants=v1",
            "content" => json_encode([
                "role" => "user",
                "content" => $user_message
            ])
        ]
    ]));

    // Lancer un run avec l’assistant
    file_get_contents("https://api.openai.com/v1/threads/$thread_id/runs", false, stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json\r\nAuthorization: Bearer $api_key\r\nOpenAI-Beta: assistants=v1",
            "content" => json_encode([
                "assistant_id" => $assistant_id
            ])
        ]
    ]));

    // Récupérer la réponse (on boucle jusqu’à ce que ce soit prêt)
    $bot_reply = "Erreur : aucune réponse.";
    for ($i = 0; $i < 10; $i++) {
        sleep(2); // attendre 2s
        $messages = file_get_contents("https://api.openai.com/v1/threads/$thread_id/messages", false, stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => "Authorization: Bearer $api_key\r\nOpenAI-Beta: assistants=v1"
            ]
        ]));
        $data = json_decode($messages, true);

        if (isset($data["data"][0]["content"][0]["text"]["value"])) {
            $bot_reply = $data["data"][0]["content"][0]["text"]["value"];
            break;
        }
    }

    // Ajouter la réponse à l’historique
    $_SESSION['history'][] = ["role" => "assistant", "content" => $bot_reply];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SBM Chatbot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f2f2f2; }
        .chatbox { width: 500px; margin: 50px auto; background: white; padding: 20px; border-radius: 10px; }
        .message { padding: 8px; margin: 5px; border-radius: 5px; }
        .user { background: #d1e7ff; text-align: right; }
        .bot { background: #e2e2e2; text-align: left; }
    </style>
</head>
<body>
<div class="chatbox">
    <h2>💬 SBM Chatbot</h2>
    <div>
        <?php foreach ($_SESSION['history'] as $msg): ?>
            <div class="message <?= $msg['role'] === 'user' ? 'user' : 'bot' ?>">
                <?= htmlspecialchars($msg['content']) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="post">
        <input type="text" name="message" required style="width:80%">
        <button type="submit">Send</button>
    </form>
</div>
</body>
</html>





