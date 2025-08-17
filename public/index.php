<?php
session_start();

// Load env variables from Render
$endpoint = getenv("AZURE_ENDPOINT");
$apiKey = getenv("AZURE_API_KEY");
$assistantId = getenv("ASSISTANT_ID");

// Initialize session history
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

// Handle reset
if (isset($_POST['reset'])) {
    $_SESSION['history'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle user input
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["message"])) {
    $userMessage = trim($_POST["message"]);
    if ($userMessage !== "") {
        $_SESSION['history'][] = ["role" => "user", "content" => $userMessage];

        // Call Azure Assistant API
        $url = $endpoint . "/openai/assistants/" . $assistantId . "/responses?api-version=2024-07-01-preview";

        $payload = json_encode([
            "input" => [
                ["role" => "user", "content" => $userMessage]
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "api-key: $apiKey"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        $botMessage = $result['output'][0]['content'][0]['text'] ?? "Erreur : aucune réponse reçue.";
        $_SESSION['history'][] = ["role" => "assistant", "content" => $botMessage];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SBM Chatbot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1e1e1e; color: #fff; display: flex; flex-direction: column; align-items: center; }
        .chatbox { width: 80%; max-width: 600px; background: #2c2c2c; padding: 20px; border-radius: 10px; margin-top: 20px; }
        .message { padding: 10px; margin: 5px 0; border-radius: 5px; font-size: 14px; }
        .user { background: #4e8cff; text-align: right; }
        .assistant { background: #3a3a3a; text-align: left; }
        form { margin-top: 10px; display: flex; }
        input[type="text"] { flex: 1; padding: 10px; border: none; border-radius: 5px; }
        button { padding: 10px; margin-left: 5px; border: none; border-radius: 5px; cursor: pointer; }
        .logo { width: 120px; margin: 20px auto; display: block; }
    </style>
</head>
<body>
    <img src="sbm_logo.png" alt="SBM Logo" class="logo">
    <div class="chatbox">
        <?php foreach ($_SESSION['history'] as $msg): ?>
            <div class="message <?= $msg['role'] ?>"><?= htmlspecialchars($msg['content']) ?></div>
        <?php endforeach; ?>
    </div>
    <form method="post">
        <input type="text" name="message" placeholder="Écrivez un message..." autocomplete="off">
        <button type="submit">Envoyer</button>
        <button type="submit" name="reset">Reset</button>
    </form>
</body>
</html>

