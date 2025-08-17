<?php
// Simple BlueGate demo page with a floating chatbot widget.
// All strings are in English as requested.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>SBM BlueGate — Demo</title>
  <style>
    :root {
      --bg: #0b1020;
      --card: #121a2c;
      --text: #eaf0ff;
      --muted: #9fb0d1;
      --primary: #2b6fff;
      --primary-2: #1f56cc;
      --accent: #00d1ff;
      --bubble-user: #2b6fff;
      --bubble-bot: #1b243a;
      --danger: #f5a97f;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';
      background: radial-gradient(1200px 600px at 30% -10%, #14203a 0%, transparent 50%), var(--bg);
      color: var(--text);
    }
    .container {
      max-width: 1080px;
      margin: 48px auto;
      padding: 0 20px;
    }
    .hero {
      background: linear-gradient(180deg, #101a31 0%, #0d162a 100%);
      border: 1px solid #1e2a48;
      border-radius: 14px;
      padding: 28px 24px;
      box-shadow: 0 10px 30px rgba(0,0,0,.25);
    }
    .hero h1 { margin: 0 0 10px; font-size: 40px; }
    .hero p  { margin: 0; color: var(--muted); font-size: 18px; }

    /* Floating chat button */
    .chat-fab {
      position: fixed;
      right: 24px; bottom: 24px;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 999px;
      padding: 14px 18px;
      font-weight: 600;
      font-size: 16px;
      box-shadow: 0 10px 30px rgba(43, 111, 255, .35);
      cursor: pointer;
    }
    .chat-fab:hover { background: var(--primary-2); }

    /* Widget panel */
    .panel {
      position: fixed;
      right: 24px; bottom: 90px;
      width: 520px; max-width: calc(100vw - 40px); height: 520px;
      background: #0d1528;
      border: 1px solid #213058;
      border-radius: 14px;
      box-shadow: 0 18px 60px rgba(0,0,0,.45);
      display: none;
      flex-direction: column;
      overflow: hidden;
    }
    .panel.open { display: flex; }
    .panel-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 12px 14px; background: #0f1932; border-bottom: 1px solid #1e2a48;
    }
    .panel-title { font-weight: 700; }
    .close-btn {
      background: transparent; border: none; color: var(--muted); cursor: pointer; font-size: 15px;
    }
    .panel-body {
      flex: 1; padding: 12px 14px; overflow-y: auto;
      background: linear-gradient(180deg, #0b1327 0%, #0c152b 100%);
    }
    .meta {
      font-size: 12px; color: var(--muted); background: #0c1733; border: 1px solid #1a2850;
      padding: 10px 12px; border-radius: 10px; margin-top: 8px;
    }
    .bubble {
      max-width: 80%;
      padding: 10px 12px; border-radius: 12px; line-height: 1.45;
      margin: 8px 0;
      white-space: pre-wrap;
    }
    .user   { margin-left: auto; background: var(--bubble-user); color: #fff; }
    .bot    { margin-right: auto; background: var(--bubble-bot); color: var(--text); border: 1px solid #223560; }
    .error  { background: #3b2222; color: #ffd4c7; border: 1px solid #6d3939; }

    .panel-input {
      display: flex; gap: 8px;
      padding: 12px; border-top: 1px solid #1e2a48; background: #0f1932;
    }
    .panel-input input {
      flex: 1; background: #0d1528; border: 1px solid #213058; color: var(--text);
      border-radius: 10px; padding: 12px; outline: none;
    }
    .panel-input button {
      background: var(--primary); color: white; border: none; border-radius: 10px; padding: 12px 16px;
      font-weight: 600; cursor: pointer;
    }
    .panel-input button:hover { background: var(--primary-2); }
    .reset { background: #2d344a; }
    .reset:hover { background: #384267; }
  </style>
</head>
<body>
  <div class="container">
    <div class="hero">
      <h1>Welcome 👋</h1>
      <p>This is a BlueGate demonstration page. Use the blue button at the bottom-right to open the chatbot.</p>
    </div>
  </div>

  <!-- Floating open button -->
  <button class="chat-fab" id="openChat">💬 Chatbot</button>

  <!-- Chat widget -->
  <div class="panel" id="chatPanel" aria-live="polite">
    <div class="panel-header">
      <div class="panel-title">SBM Chatbot</div>
      <button class="close-btn" id="closeChat">Close</button>
    </div>
    <div class="panel-body" id="chatBody">
      <div class="meta">
        Tip: ask IT or company-knowledge questions. Messages are sent to the same thread as the main chat page.
      </div>
    </div>
    <div class="panel-input">
      <input id="chatInput" type="text" placeholder="Type your question…" />
      <button id="sendBtn">Send</button>
      <button id="resetBtn" class="reset">Reset</button>
    </div>
  </div>

  <script>
    const panel   = document.getElementById('chatPanel');
    const openBtn = document.getElementById('openChat');
    const closeBtn= document.getElementById('closeChat');
    const bodyEl  = document.getElementById('chatBody');
    const inputEl = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const resetBtn= document.getElementById('resetBtn');

    const addBubble = (role, text) => {
      const b = document.createElement('div');
      b.className = 'bubble ' + (role === 'user' ? 'user' : role === 'error' ? 'error' : 'bot');
      b.textContent = text;
      bodyEl.appendChild(b);
      bodyEl.scrollTop = bodyEl.scrollHeight;
    };

    const sendMessage = async () => {
      const text = inputEl.value.trim();
      if (!text) return;
      addBubble('user', text);
      inputEl.value = '';
      try {
        const body = new URLSearchParams({ message: text });
        const res  = await fetch('/index.php?ajax=1', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body
        });

        // If server crashed and returned HTML (502/404), this will throw:
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.ok !== true) {
          const err = (data && data.error) ? data.error : `Request failed (${res.status})`;
          addBubble('error', err);
          return;
        }
        addBubble('bot', data.reply);
      } catch (e) {
        addBubble('error', 'Network error. Please try again.');
      }
    };

    openBtn.onclick  = () => { panel.classList.add('open'); inputEl.focus(); };
    closeBtn.onclick = () => { panel.classList.remove('open'); };
    sendBtn.onclick  = sendMessage;
    inputEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') sendMessage(); });

    resetBtn.onclick = async () => {
      try {
        // Reset = close current PHP session to drop the thread_id
        await fetch('/?reset=1', { cache: 'no-store' });
      } catch {}
      // Soft reset UI
      bodyEl.innerHTML = '<div class="meta">Session reset.</div>';
    };

    // Optional server-side session reset handler:
    // you can implement it in index.php by checking GET ?reset=1 and doing session_destroy().
  </script>
</body>
</html>





