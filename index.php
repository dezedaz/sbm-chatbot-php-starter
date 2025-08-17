<?php
session_start();

if (!isset($_SESSION["messages"])) {
    $_SESSION["messages"] = [];
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["message"])) {
    $userMessage = trim($_POST["message"]);

    if ($userMessage !== "") {
        $_SESSION["messages"][] = ["user", $userMessage];
        // Ici tu peux mettre l'appel à Azure OpenAI si tu veux, pour l'instant on simule
        $_SESSION["messages"][] = ["bot", "Hello! This is your SBM Chatbot on Render 🚀"];
    }
}

if (isset($_POST["reset"])) {
    $_SESSION["messages"] = [];
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SBM Chatbot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #121212; color: white; text-align: center; }
        .chatbox { width: 90%; max-width: 600px; margin: 30px auto; background: #1e1e1e; padding: 20px; border-radius: 15px; overflow-y: auto; height: 400px; }
        .msg { margin: 10px 0; padding: 10px; border-radius: 10px; display: inline-block; max-width: 80%; font-size: 14px; }
        .user { background: #0078d7; color: white; float: right; clear: both; }
        .bot { background: #333; color: #f1f1f1; float: left; clear: both; }
        form { margin-top: 20px; }
        input[type="text"] { width: 70%; padding: 10px; border-radius: 5px; border: none; }
        button { padding: 10px 15px; border: none; border-radius: 5px; background: #0078d7; color: white; cursor: pointer; }
        button:hover { background: #005fa3; }
        .logo { width: 120px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <img src="sbm_logo.png" class="logo" alt="SBM Logo">
    <h2>SBM Chatbot (Render)</h2>

    <div class="chatbox">
        <?php foreach ($_SESSION["messages"] as $msg): ?>
            <div class="msg <?= $msg[0] ?>"><?= htmlspecialchars($msg[1]) ?></div><br>
        <?php endforeach; ?>
    </div>

    <form method="POST">
        <input type="text" name="message" placeholder="Type your message..." autocomplete="off">
        <button type="submit">Send</button>
        <button type="submit" name="reset">Reset</button>
    </form>
</body>
</html>
