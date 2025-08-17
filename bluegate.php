<?php // bluegate.php (demo page with floating widget) ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>SBM BlueGate — Demo</title>
  <style>
    :root {
      --bg: #0e1621;
      --card: #0f1b2d;
      --text: #e7f1ff;
      --muted: #9fb1c9;
      --primary: #2a6df4;
      --primary-600: #2460d6;
      --danger: #f44336;
    }
    html, body { height: 100%; margin: 0; background: var(--bg); color: var(--text); font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
    .container { padding: 32px 24px; max-width: 1100px; margin: 0 auto; }
    .hero { background: linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,0) 70%), var(--card); border:1px solid rgba(255,255,255,.06); border-radius:16px; padding:28px; box-shadow: 0 10px 28px rgba(0,0,0,.35); }
    .hero h1 { font-size: clamp(28px, 3.5vw, 40px); margin: 0 0 12px; display:flex; align-items:center; gap:12px; }
    .hero p { color: var(--muted); margin: 0; font-size: clamp(14px,2.1vw,18px); }
    .floating-btn {
      position: fixed; right: 28px; bottom: 28px; background: var(--primary);
      color:#fff; border:none; border-radius:28px; height:56px; padding:0 20px;
      box-shadow:0 10px 24px rgba(42,109,244,.35); font-weight:600; cursor:pointer;
      display:flex; align-items:center; gap:10px;
    }
    .floating-btn:hover { background: var(--primary-600); }
    .widget {
      position:fixed; right: 28px; bottom: 96px; width:min(680px, 92vw); max-height:min(70vh, 640px);
      background: var(--card); border:1px solid rgba(255,255,255,.08); border-radius:16px;
      box-shadow:0 18px 36px rgba(0,0,0,.45); display:none; flex-direction:column; overflow:hidden;
    }
    .widget.visible { display:flex; }
    .w-header { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid rgba(255,255,255,.08); }
    .w-title { display:flex; align-items:center; gap:10px; font-weight:700; }
    .w-body { padding: 12px 16px; overflow:auto; display:flex; flex-direction:column; gap:10px; }
    .sys, .err, .user, .bot { border-radius:12px; padding:10px 12px; font-size:14px; line-height:1.4; }
    .sys { background: rgba(255,255,255,.05); color: var(--muted); }
    .err { background: rgba(244,67,54,.1); border:1px dashed rgba(244,67,54,.55); color:#ffc7c3; white-space:pre-wrap; }
    .user { align-self:flex-end; background: rgba(42,109,244,.15); border:1px solid rgba(42,109,244,.45); }
    .bot  { align-self:flex-start; background: rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.06); }
    .w-footer { display:flex; gap:10px; padding:12px; border-top:1px solid rgba(255,255,255,.08); }
    .w-footer input { flex:1; background:#0b1420; color:var(--text); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:12px; }
    .w-footer button { min-width: 84px; border:none; border-radius:10px; font-weight:700; color:#fff; cursor:pointer; height:44px; }
    .send { background: var(--primary); }
    .send:hover { background: var(--primary-600); }
    .reset { background: #5d6a7e; }
    .reset:hover { background: #4e5a6b; }
    .close { background: transparent; color:var(--muted); border:1px solid rgba(255,255,255,.15); border-radius:10px; height:36px; padding:0 10px; cursor:pointer; }
  </style>
</head>
<body>
  <div class="container">
    <div class="hero">
      <h1>Welcome <span>👋</span></h1>
      <p>This is a BlueGate demonstration page. Use the blue button at the bottom-right to open the chatbot.</p>
    </div>
  </div>

  <!-- Floating button -->
  <button class="floating-btn" id="toggleChat">
    <span style="font-size:18px">💬</span> Chatbot
  </button>

  <!-- Chat widget -->
  <div class="widget" id="widget">
    <div class="w-header">
      <div class="w-title">
        <img src="sbm_logo.png" alt="SBM" style="width:20px; height:20px; border-radius:4px" />
        <span>SBM Chatbot</span>
      </div>
      <button class="close" id="closeChat">Close</button>
    </div>
    <div class="w-body" id="history">
      <div class="sys">Tip: ask IT or company-knowledge questions. Messages are sent to the same thread as the main chat page.</div>
    </div>
    <div class="w-footer">
      <input id="msg" placeholder="Type your question..." />
      <button class="send" id="sendBtn">Send</button>
      <button class="reset" id="resetBtn">Reset</button>
    </div>
  </div>

  <script>
    const widget   = document.getElementById('widget');
    const toggle   = document.getElementById('toggleChat');
    const closeBtn = document.getElementById('closeChat');

    const history  = document.getElementById('history');
    const input    = document.getElementById('msg');
    const sendBtn  = document.getElementById('sendBtn');
    const resetBtn = document.getElementById('resetBtn');

    function show()  { widget.classList.add('visible'); input.focus(); }
    function hide()  { widget.classList.remove('visible'); }
    toggle.addEventListener('click', show);
    closeBtn.addEventListener('click', hide);

    function pushUser(t){ const d=document.createElement('div'); d.className='user'; d.textContent=t; history.appendChild(d); history.scrollTop=history.scrollHeight; }
    function pushBot(t){  const d=document.createElement('div'); d.className='bot';  d.textContent=t; history.appendChild(d); history.scrollTop=history.scrollHeight; }
    function pushErr(t){  const d=document.createElement('div'); d.className='err';  d.textContent=t; history.appendChild(d); history.scrollTop=history.scrollHeight; }

    async function callApi(payload) {
      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const text = await res.text();
      let json;
      try { json = JSON.parse(text); }
      catch(e) { throw new Error(`Server returned non-JSON (${res.status}):\n${text.slice(0,400)}…`); }
      if (!res.ok || !json.ok) {
        const reason = json?.error || `HTTP ${res.status}`;
        throw new Error(typeof reason === 'string' ? reason : JSON.stringify(reason));
      }
      return json;
    }

    async function send() {
      const msg = input.value.trim();
      if (!msg) return;
      pushUser(msg);
      input.value = '';
      try {
        const json = await callApi({ message: msg });
        pushBot(json.reply || '(empty reply)');
      } catch (err) {
        pushErr(String(err.message || err));
      }
    }
    async function resetThread() {
      try {
        await callApi({ reset: true });
        pushBot('Session reset.');
      } catch (err) {
        pushErr(String(err.message || err));
      }
    }

    sendBtn.addEventListener('click', send);
    resetBtn.addEventListener('click', resetThread);
    input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) send(); });
  </script>
</body>
</html>







