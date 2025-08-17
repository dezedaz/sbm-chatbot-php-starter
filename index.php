<?php
// Health check for Render
if (isset($_GET['health'])) {
  http_response_code(200);
  echo 'ok';
  exit;
}

// Redirect visitors to the BlueGate demo
header('Location: /bluegate.php', true, 302);
exit;
