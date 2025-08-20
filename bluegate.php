<?php /* bluegate.php */ ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>SBM BlueGate — Demo</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root { --bg:#0b1220; --card:#121a2b; --text:#e9eefc; --muted:#9fb0d9; --accent:#2b74ff; --accent-2:#1f5fe0; }
    html,body{margin:0;height:100%;background:var(--bg);color:var(--text);font:16px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial}
    .container{max-width:1100px;margin:48px auto;padding:0 24px}
    .hero{background:var(--card);border:1px solid #1d2640;border-radius:14px;padding:28px 32px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
    h1{font-size:40px;margin:0 0 18px}
    p{margin:0;color:var(--muted)}
    /* FAB */
    .fab{position:fixed;right:28px;bottom:28px;background:var(--accent);color:#fff;border:none;border-radius:28px;padding:14px 18px 14px 14px;font-weight:600;
         display:flex;align-items:center;gap:10px;box-shadow:0 14px 35px rgba(43,116,255,.35);cursor:pointer}
    .fab:hover{background:var(--accent-2)}
    .dot{width:8px;height:8px;border-radius:50%;background:#66f79a;box-shadow:0 0 10px #66f79a}
    /* Panel */
    .panel{position:fixed;right:24px;bottom:96px;width:420px;max-width:calc(100vw - 48px);background:var(--card);border:1px solid #1d2640;border-radius:14px;
           box-shadow:0 20px 50px rgba(0,0,0,.45);display:none;flex-direction:column;overflow:hidden}
    .panel.open{display:flex}
    .panel-hd{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #1d2640}
    .title{display:flex;gap:10px;align-items:center;font-weight:700}
    .close{background:transparent;border:1px solid #2a3557;color:#cdd8ff;border-radius:8px;padding:8px 10px;cursor:pointer}
    .body{padding:16px;display:flex;flex-direction:column;gap:10px}
    .msg{background:#0e1628;border:1px solid #1b2745;border-radius:10px;padding:12px 14px}
    .warn{background:#2a1313;border-color:#5a2b2b;color:#ffb4b4}
    .row{display:flex;gap:10px;margin-top:4px}
    .row input{flex:1;background:#0d1527;border:1px solid #1b2745;color:var(--text);padding:12px 12px;border-radius:10px;outline:none}
    .row button{background:var(--accent);border:none;color:#fff;font-weight:700;border-radius:10px;padding:12px 16px;cursor:pointer}
    .row button:hover{background:var(--accent-2)}
    .hint{font-size:12px;color:#9bb1e1;margin-top:10px}
    .foot{background:#0c1426;font-size:12px;color:#9bb1e1;padding:8px 12px;border-top:1px solid #1b2745}
  </style>
</head>
<body>
  <div class="container">
    <div class="hero">
      <h1>Welcome 👋</h1>
      <p>This is a BlueGate demonstration page. Use the blue button at the bottom-right to open the chatbot.</p>
    </div>
  </div>

  <!-- Chat button -->
  <button class="fab" id="btnOpen"><span class="dot"></span> Chatbot</button>

  <!-- Panel -->
  <section class="panel" id="panel">
    <div class="panel-hd">
      <div class="title">💬 SBM Chatbot</div>
      <button class="close" id="btnClose">Close</button>
    </div>
    <div class="body">
      <div class="msg" id="log">Tip: your message is sent to a fresh Azure Assistants thread per question (demo mode).</div>
      <div class="row">
        <input id="inp" placeholder="Type your question…" />
        <button id="btnSend">Send</button>
      </div>
      <div class="hint" id="hint"></div>
    </div>
    <div class="foot">
      Endpoint: <span id="ep"></span>
    </div>
  </section>

<script>
const panel = document.getElementById('panel');
const log   = document.getElementById('log');
const inp   = document.getElementById('inp');
const btnOpen = document.getElementById('btnOpen');
const btnClose= document.getElementById('btnClose');
const btnSend = document.getElementById('btnSend');
const hint = document.getElementById('hint');
document.getElementById('ep').textContent = '<?= htmlspecialchars(getenv("AZURE_ENDPOINT") ?: "not set", ENT_QUOTES) ?> • API: 2024-07-01-preview';

btnOpen.onclick = () => panel.classList.add('open');
btnClose.onclick= () => panel.classList.remove('open');
btnSend.onclick = send;
inp.addEventListener('keydown', e => { if (e.key === 'Enter') send(); });

async function send() {
  const text = inp.value.trim();
  if (!text) return;
  push(`[you] ${text}`);
  inp.value = ''; hint.textContent = '';

  // Cold start retry wrapper (Render free)
  async function callOnce() {
    const res = await fetch('/api.php', {
      method:'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ message: text })
    });
    if (!res.ok) throw Object.assign(new Error(`HTTP ${res.status}`), { status: res.status });
    return res.json();
  }
  try {
    let j;
    try { j = await callOnce(); }
    catch (e) {
      if (e.status === 502 || e.status === 504) { // warm-up
        await new Promise(r => setTimeout(r, 1500));
        j = await callOnce();
      } else { throw e; }
    }
    if (!j.ok) {
      push(`Thread error: ${j.error ?? 'unknown'}`, true);
    } else {
      push(j.reply || '[no reply]');
    }
  } catch(err) {
    push(`Request failed (${err.message})`, true);
  }
}
function push(txt, isWarn=false){
  const div = document.createElement('div');
  div.className = 'msg' + (isWarn ? ' warn' : '');
  div.textContent = txt;
  log.parentNode.insertBefore(div, log.nextSibling);
}
</script>
</body>
</html>








