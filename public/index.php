<?php
session_start();

$api_key = "F4GkErnSxB1YyTAtIZ7aIwWirmAFTrClqALkVr2VhRvJWJxqPe32JQQJ99BHACYeBjFXJ3w3AAABACOGO0aZ";
$endpoint = "https://sbmchatbot.openai.azure.com/";
$assistant_id = "asst_WrGbhVrIqfqOqg5g1omyddBB";

// Initialize chat history
if (!isset($_SESSION['chat'])) {
    $_SESSION['chat'] = [];
}

// Handle reset
if (isset($_POST['reset'])) {
    $_SESSION['chat'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle user message
if (isset($_POST['message']) && !empty(trim($_POST['message']))) {
    $user_message = htmlspecialchars($_POST['message']);
    $_SESSION['chat'][] = ["role" => "user", "content" => $user_message];

    // Create thread in Azure
    $thread_response = file_get_contents($endpoint . "openai/assistants/v1/threads", false, stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json\r\nAuthorization: Bearer $api_key\r\n",
            "content" => "{}"
        ]
    ]));
    $thread = json_decode($thread_response, true);
    $thread_id = $thread["id"] ?? null;

    if ($thread_id) {
        // Add user message
        file_get_contents($endpoint . "openai/assistants/v1/threads/$thread_id/messages", false, stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json\r\nAuthorization: Bearer $api_key\r\n",
                "content" => json_encode(["role" => "user", "content" => $user_message])
            ]
        ]));

        // Run assistant
        $run_response = file_get_contents($endpoint . "openai/assistants/v1/threads/$thread_id/runs", false, stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json\r\nAuthorization: Bearer $api_key\r\n",
                "content" => json_encode(["assistant_id" => $assistant_id])
            ]
        ]));

        // Get messages
        $messages_response = file_get_contents($endpoint . "openai/assistants/v1/threads/$thread_id/messages", false, stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => "Authorization: Bearer $api_key\r\n"
            ]
        ]));
        $messages = json_decode($messages_response, true);

        if (isset($messages["data"][0]["content"][0]["text"]["value"])) {
            $bot_reply = $messages["data"][0]["content"][0]["text"]["value"];
            $_SESSION['chat'][] = ["role" => "assistant", "content" => $bot_reply];
        } else {
            $_SESSION['chat'][] = ["role" => "assistant", "content" => "Désolé, je n’ai pas pu obtenir de réponse."];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SBM Chatbot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1e1e1e; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .chat-container { width: 100%; max-width: 600px; background: #2c2c2c; border-radius: 15px; padding: 20px; box-shadow: 0 0 10px #000; }
        .messages { max-height: 400px; overflow-y: auto; margin-bottom: 15px; }
        .message { padding: 8px 12px; border-radius: 10px; margin: 5px 0; font-size: 14px; }
        .user { background: #4a90e2; text-align: right; }
        .assistant { background: #3d3d3d; text-align: left; }
        form { display: flex; gap: 10px; }
        input[type=text] { flex: 1; padding: 10px; border-radius: 8px; border: none; }
        button { padding: 10px 15px; border: none; border-radius: 8px; cursor: pointer; background: #4a90e2; color: #fff; }
        .reset-btn { background: #e74c3c; }
    </style>
</head>
<body>
<div class="chat-container">
    <div class="messages" id="messages">
        <?php foreach ($_SESSION['chat'] as $msg): ?>
            <div class="message <?= $msg['role'] ?>"><?= $msg['content'] ?></div>
        <?php endforeach; ?>
    </div>
    <form method="post">
        <input type="text" name="message" placeholder="Type your message..." autocomplete="off">
        <button type="submit">Send</button>
        <button type="submit" name="reset" class="reset-btn">Reset</button>
    </form>
</div>
<script>
    let messages = document.getElementById('messages');
    messages.scrollTop = messages.scrollHeight;
</script>
</body>
</html>





