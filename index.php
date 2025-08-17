<?php
session_start();

if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userMessage = trim($_POST["message"]);
    if (!empty($userMessage)) {
        $_SESSION['history'][] = ["sender" => "user", "text" => $userMessage, "time" => date("H:i")];

        // Exemple de réponse statique (à remplacer par ton appel API Azure si besoin)
        $botResponse = "Hello! This is the bot's response.";
        $_SESSION['history'][] = ["sender" => "bot", "text" => $botResponse, "time" => date("H:i")];
    }
}

if (isset($_POST["reset"])) {
    $_SESSION['history'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SBM Chatbot</title>
    <style>
        body { font-family: Arial, sans-serif; background:#1e1e1e; color:#fff; display:flex; justify-content:center; align-items:center; height:100vh; margin:0; }
        .chat-container { width:400px; background:#2c2c2c; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; }
        .chat-header { background:#444; padding:10px; text-align:center; font-weight:bold; }
        .chat-box { flex:1; padding:10px; overflow-y:auto; }
        .message { margin:6px 0; padding:8px 12px; border-radius:8px; font-size:14px; max-width:75%; }
        .user { background:#007bff; align-self:flex-end; }
        .bot { background:#555; align-self:flex-start; }
        .time { font-size:11px; color:#bbb; margin-top:2px; text-align:right; }
        .chat-input { display:flex; }
        .chat-input input { flex:1; padding:10px; border:none; outline:none; }
        .chat-input button { padding:10px; border:none; cursor:pointer; background:#007bff; color:#fff; }
        .reset-btn { background:#ff4444; color:#fff; border:none; padding:5px 10px; cursor:pointer; margin:5px; border-radius:6px; }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">SBM Chatbot</div>
        <div class="chat-box">
            <?php foreach ($_SESSION['history'] as $msg): ?>
                <div class="message <?= $msg['sender'] ?>">
                    <?= htmlspecialchars($msg['text']) ?>
                    <div class="time"><?= $msg['time'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="post" class="chat-input">
            <input type="text" name="message" placeholder="Type your message..." autocomplete="off">
            <button type="submit">Send</button>
        </form>
        <form method="post" style="text-align:center;">
            <button type="submit" name="reset" class="reset-btn">Reset Chat</button>
        </form>
    </div>
</body>
</html>

