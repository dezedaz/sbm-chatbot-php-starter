<?php
session_start();

// --- CONFIG ---
$api_key = "F4GkErnSxB1YyTAtIZ7aIwWirmAFTrClqALkVr2VhRvJWJxqPe32JQQJ99BHACYeBjFXJ3w3AAABACOGO0aZ"; 
$endpoint = "https://sbmchatbot.openai.azure.com/openai/assistants/v1";
$assistant_id = "asst_WrGbhVrIqfqOqg5g1omyddBB";

// --- RESET CHAT ---
if (isset($_POST['reset'])) {
    $_SESSION['history'] = [];
}

// --- INIT HISTORY ---
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

// --- SEND MESSAGE TO AZURE ---
if (isset($_POST['message']) && !empty(trim($_POST['message']))) {
    $userMessage = trim($_POST['message']);

    $_SESSION['history'][] = ["role" => "user", "content" => $userMessage];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$endpoint/threads");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "api-key: $api_key"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "assistant_id" => $assistant_id,
        "thread" => [
            "messages" => $_SESSION['history']
        ]
    ]));

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['thread']['messages'])) {
        $messages = $data['thread']['messages'];
        foreach ($messages as $msg) {
            if ($msg['role'] === "assistant") {
                $_SESSION['history'][] = [
                    "role" => "assistant",
                    "content" => $msg['content'][0]['text']['value']
                ];
            }
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
    body { font-family: Arial; background: #121212; color: #eee; display: flex; flex-direction: column; align-items: center; }
    .chatbox { width: 90%; max-width: 600px; height: 500px; overflow-y: auto; background: #1e1e1e; padding: 10px; border-radius: 10px; margin-top: 20px; }
    .msg { margin: 5px; padding: 8px 12px; border-radius: 10px; font-size: 14px; max-width: 80%; }
    .user { background: #4a90e2; margin-left: auto; }
    .bot { background: #333; margin-right: auto; }
    .form { display: flex; margin-top: 10px; }
    input[type=text] { flex: 1; padding: 10px; border: none; border-radius: 5px; }
    button { padding: 10px; margin-left: 5px; border: none; border-radius: 5px; background: #4a90e2; color: white; cursor: pointer; }
    button:hover { background: #357ab8; }
  </style>
</head>
<body>
  <h1>🤖 SBM Chatbot</h1>
  <div class="chatbox" id="chatbox">
    <?php foreach ($_SESSION['history'] as $msg): ?>
      <div class="msg <?= $msg['role'] === 'user' ? 'user' : 'bot' ?>">
        <?= htmlspecialchars($msg['content']) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <form method="post" class="form">
    <input type="text" name="message" placeholder="Type your message..." autocomplete="off" required>
    <button type="submit">Send</button>
    <button type="submit" name="reset">Reset</button>
  </form>
  <script>
    var chatbox = document.getElementById("chatbox");
    chatbox.scrollTop = chatbox.scrollHeight;
  </script>
</body>
</html>






