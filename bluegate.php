<?php
// This page is a minimal BlueGate-like demo with a floating chatbot widget.
// UI text is in English (as requested). Backend endpoint is chat_api.php on the same origin.
$assistantId = getenv('ASSISTANT_ID') ?: 'unknown';
$endpoint    = rtrim(getenv('AZURE_ENDPOINT') ?: '', '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SBM BlueGate — Demo</title>
  <link rel="icon" href="sbm_logo.png" />
  <style>
    :root{
      --bg:#0f172a;        /* slate-900 */
      --panel:#111827;     /* gray-900 */
      --card:#0b1220;      /* dark card */
      --text:#e5e7eb;      /* gray-200 */
      --muted:#9ca3af;     /* gray-400 */
      --brand:#2563eb;     /* blue-600 */
      --brand-weak:#1e40af;/* blue-800 */
      --success:#10b981;   /* emerald-500 */
      --danger:#ef4444;    /* red-500 */
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; background:var(--bg); color:var(--text);
      font-family: system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,Apple Color Emoji,Segoe UI Emoji;
    }
    .container{max-width:1100px; margin:0 auto; padding:24px}
    .hero{background:linear-gradient(180deg,#0b1220,#0f172a); border:1px solid #1f2937; border-radius:14px; padding:28px 28px 36px}
    h1{margin:0 0 12px; font-size:40px; line-height:1.1; font-weight:800; letter-spacing:.3px}
    p{margin:0; color:var(--muted); font-size:18px}

    /* Floating button */
    .chat-fab{
      position:fixed; right:28px; bottom:28px; z-index:30;
      display:flex; align-items:center; gap:10px;
      background:var(--brand); color:white; border:none; border-radius:999px;
      padding:14px 18px; font-weight:600; cursor:pointer; box-shadow:0 10px 24px rgba(0,0,0,.35);
    }
    .chat-fab:hover{background:#1d4ed8}

    /* Widget panel */
    .chat-card{
      position:fixed; right:28px; bottom:96px; z-index:40;
      width:440px; max-width:92vw; display:none; opacity:0; transform:translateY(10px);
      background:var(--card); border:1px solid #1f2937; border-radius:14px; overflow:hidden;
      box-shadow:0 18px 45px rgba(0,0,0,.45);
      transition:opacity .15s ease, transform .15s ease;
    }
    .chat-card.open{display:block; opacity:1; transform:translateY(0)}
    .card-header{display:flex; align-items:center; gap:12px; padding:14px 16px; border-bottom:1px solid #1f2937; background:rgba(17,24,39,.7); backdrop-filter: blur(6px)}
    .card-title{font-weight:700; font-size:16px; margin-right:auto}
    .close-btn{background:transparent; color:#cbd5e1; border:1px solid #334155; border-radius:8px; padding:6px 8px; cursor:pointer}
    .close-btn:hover{background:#111827}

    .notice{font-size:14px; color:#cbd5e1; border:1px dashed #334155; background:rgba(17,24,39,.45); border-radius:10px; padding:10px 12px; margin:14px 16px 8px}
    .meta{font-size:12px; color:#9ca3af; border:1px solid #1f2937; background:rgba(17,24,39,.45); border-radius:10px; padding:10px 12px; margin:10px 16px}

    .form{display:flex; gap:10px; padding:12px 16px 16px}
    .input{
      flex:1; background:#0a0f1a; color:#e5e7eb; border:1px solid #334155; border-radius:10px;
      padding:12px 12px; font-size:14px; outline:none;
    }
    .input:focus{border-color:#3b82f6}
    .btn{
      background:var(--brand); color:white; border:none; padding:10px 14px; border-radius:10px; cursor:pointer; font-weight:700
    }
    .btn:hover{background:#1d4ed8}
    .btn-ghost{
      background:transparent; color:#cbd5e1; border:1px solid #334155; padding:10px 12px; border-radius:10px; cursor:pointer;
    }
    .btn-ghost:hover{background:#111827}
    .hint{font-size:13px; color:#9ca3af; border-top:1px solid #1f2937; padding:12px 16px 16px}
    .logo{width:16px; height:16px; border-radius:3px; object-fit:cover; background:#0ea5e9}
  </style>
</head>
<body>
  <div class="container">
    <div class="hero">
      <h1>Welcome 👋</h1>
      <p>This is a BlueGate demonstration page. Use the blue button at the bottom-right to open the chatbot.</p>
    </div>
  </div>

  <!-- Floating button -->
  <button class="chat-fab" id="chatbotToggler">
    <img class="logo" src="sbm_logo.png" alt="SBM" /> Chatbot
  </button>

  <!-- Chat widget -->
  <section class="chat-card" id="chatbotCard" aria-live="polite">
    <header class="card-header">
      <img class="logo" src="sbm_logo.png" alt="SBM" />
      <div class="card-title">SBM Chatbot</div>
      <button id="chatbotClose" class="close-btn">Close</button>
    </header>

    <div class="notice" id="widgetNotice">Ask an IT or company-knowledge question. The assistant will answer here.</div>

    <form id="chatForm" class="form" autocomplete="off">
      <input id="chatInput" class="input" type="text" placeholder="Type your question…" />
      <button id="chatSend" class="btn" type="submit">Send</button>
      <button id="chatReset" class="btn-ghost" type="button">Reset</button>
    </form>

    <div class="meta">
      Endpoint: <?php echo htmlspecialchars($endpoint ?: '(env AZURE_ENDPOINT not set)'); ?><br>
      Assistant ID: <?php echo htmlspecialchars($assistantId); ?><br>
      API: 2024-07-01-preview
    </div>

    <div class="hint">Tip: your message is sent to the same thread used by the main chat page.</div>
  </section>

  <script>
    (function () {
      const toggler = document.getElementById('chatbotToggler');
      const card = document.getElementById('chatbotCard');
      const closeBtn = document.getElementById('chatbotClose');
      const form = document.getElementById('chatForm');
      const input = document.getElementById('chatInput');
      const sendBtn = document.getElementById('chatSend');
      const resetBtn = document.getElementById('chatReset');
      const notice = document.getElementById('widgetNotice');

      function setNotice(msg) { notice.textContent = msg; }

      toggler.addEventListener('click', () => {
        card.style.display = 'block';
        setTimeout(() => card.classList.add('open'), 10);
        input.focus();
      });
      closeBtn.addEventListener('click', () => {
        card.classList.remove('open');
        setTimeout(() => (card.style.display = 'none'), 150);
      });

      resetBtn.addEventListener('click', async () => {
        setNotice('Resetting conversation…');
        try {
          const res = await fetch('chat_api.php?reset=1', { method: 'POST' });
          if (!res.ok) throw new Error('Reset failed');
          setNotice('Conversation reset.');
        } catch (e) {
          setNotice('Error: ' + e.message);
        }
      });

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;
        setNotice('Sending…');

        try {
          const res = await fetch('chat_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text, mode: 'widget' })
          });
          const data = await res.json();

          if (!res.ok || !data || !data.ok) {
            throw new Error((data && data.error) || 'API error');
          }
          // ✅ Show assistant reply directly inside the widget
          setNotice(data.reply || '(no text returned)');
        } catch (err) {
          setNotice('Error: ' + err.message);
        } finally {
          input.disabled = false;
          sendBtn.disabled = false;
          input.focus();
        }
      });
    })();
  </script>
</body>
</html>




