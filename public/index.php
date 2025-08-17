<?php
session_start();

$api_key = "F4GkErnSxB1YyTAtIZ7aIwWirmAFTrClqALkVr2VhRvJWJxqPe32JQQJ99BHACYeBjFXJ3w3AAABACOGO0aZ";
$endpoint = "https://sbmchatbot.openai.azure.com/openai/assistants/v1/threads";
$assistant_id = "asst_WrGbhVrIqfqOqg5g1omyddBB";

if (!isset($_SESSION['messages'])) {
    $_SESSION['messages'] = [];
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["message"])) {
    $userMessage = htmlspecialchars($_POST["message"]);
    $_SESSION['messages'][] = ["role" => "user", "content" => $userMessage];

    // 1. Créer un thread
    $threadResponse = createThread($api_key, $endpoint, $assistant_id, $userMessage);

    if ($threadResponse && isset($threadResponse["content"])) {
        $botMessage = $threadResponse["content"];
        $_SESSION['messages'][] = ["role" => "assistant", "content" => $botMessage];
    } else {
        $_SESSION['messages'][] = ["role" => "assistant", "content" => "⚠️ Erreur lors de la communication avec l'assistant Azure."];
    }
}

function createThread($api_key, $endpoint, $assistant_id, $userMessage) {
    $url = $endpoint;

    $postData = [
        "assistant_id" => $assistant_id,
        "messages" => [
            ["role" => "user", "content" => $userMessage]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "api-key: $api_key"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data["messages"][0]["content"][0]["text"]["value"])) {
        return ["content" => $data["messages"][0]["content"][0]["text"]["value"]];
    } else {
        return null;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SBM Chatbot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f2f2f2; }
        .chatbox { width: 600px; margin: 30px auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px #aaa; }
        .messages { max-height: 400px; overflow-y: auto; margin-bottom: 10px; }
        .user { text-align: right; color: blue; margin: 5px; }
        .assistant { text-align: left; color: green; margin: 5px; }
        form { display: flex; }
        input[type=text] { flex: 1; padding: 10px; border-radius: 5px; border: 1px solid #aaa; }
        button { padding: 10px 15px; border: none; background: #0078D7; color: white; border-radius: 5px; margin-left: 5px; }
    </style>
</head>
<body>
    <div class="chatbox">
        <h2>💬 SBM Chatbot</h2>
        <div class="messages">
            <?php foreach ($_SESSION['messages'] as $msg): ?>
                <div class="<?= $msg['role'] ?>">
                    <strong><?= ucfirst($msg['role']) ?>:</strong> <?= $msg['content'] ?>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="post">
            <input type="text" name="message" placeholder="Écrivez votre message..." required>
            <button type="submit">Envoyer</button>
        </form>
    </div>
</body>
</html>
