<?php
// bluegate.php — simple BlueGate-like page + floating chatbot widget
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<title>SBM BlueGate — Demo</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<style>
  :root{
    --bg:#0b1220; --panel:#0f172a; --text:#e5e7eb; --muted:#9ca3af; --primary:#2563eb;
    --border:#1f2a44; --shadow:0 14px 40px rgba(0,0,0,.38);
  }
  body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial}
  .container{max-width:1060px;margin:48px auto;padding:0 20px}
  .hero{padding:28px 28px;background:var(--panel);border-radius:16px;box-shadow:var(--shadow);border:1px solid var(--border)}
  h1{margin:0 0 12px;font-size:40px}
  p{margin:0;font-size:18px;color:#cbd5e1}

  /* Floating button */
  .chat-fab{position:fixed;right:26px;bottom:26px;background:var(--primary);color:#fff;padding:16px 18px;border-radius:14px;
    box-shadow:var(--shadow);cursor:pointer;border:none;font-weight:600}
  .chat-fab:hover{filter:brightness(1.05)}

  /* Drawer */
  .drawer{position:fixed;right:26px;bottom:96px;width:560px;max-width:calc(100vw - 40px);
    background:var(--panel);border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);display:none}
  .drawer header{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--border)}
  .drawer header h3{margin:0;font-size:18px}
  .close{margin-left:auto;background:#111827;border:1px solid var(--border);color:#e5e7eb;padding:8px 10px;border-radius:10px;cursor:pointer}
  .close:hover{filter:brightness(1.08)}
  .body{padding:14px 16px;display:flex;flex-direction:column;gap:10px}
  .note{padding:10px 12px;background:#0b1220;border:1px solid var(--border);border-radius:12px;color:#d1d5db}

  .row{display:flex;gap:8px}
  .row input{flex:1;padding:12px;border-radius:12px;border:1px solid var(--border);background:#0b1220;color:#fff}
  .row button{padding:12px 16px;border-radius:12px;border:0;background:var(--primary);color:#fff;cursor:pointer}
  .row .reset{background:#374151}
  .error{padding:10px 12px;background:#3f1d1d;border:1px solid #6b2727;border-radius:10px;color:#ffd4d4}
  .reply{padding:12px 14px;background:#111827;border:1px solid var(--border);border-radius:12px}
</style>
</head>
<body>
  <div class="container">
    <div class="hero">
      <h1>Welcome 👋</h1>
      <p>This is a BlueGate demonstration page. Use the blue button at the bottom-right to open the chatbot.</p>
    </div>
  </div>

  <button class="chat-fab" id="openBtn">Chatbot</button>

  <div class="drawer" id="drawer">
    <header>
      <h3>SBM Chatbot</h3>
      <button class="close" id="closeBtn">Close</button>
    </header>
    <div class="body">
      <div id="status" class="note">Tip: your message is sent to the same thread used by the main chat page.</div>

      <div class="row">
        <input id="ask" placeholder="Type your question…"/>
        <button id="sendBtn">Send</button>
        <button class="reset" id="resetBtn">Reset</button>
      </div>

      <div id="out" class="reply" style="display:none"></div>
      <div id="err" class="error" style="display:none"></div>
    </div>
  </div>

<script>
const drawer  = document.getElementById('drawer');
const openBtn = document.getElementById('openBtn');
const closeBtn= document.getElementById('closeBtn');
openBtn.onclick  = () => drawer.style.display = 'block';
closeBtn.onclick = () => drawer.style.display = 'none';

const out = document.getElementById('out');
const err = document.getElementById('err');

async function api(action, content=""){
  const body = new URLSearchParams({ajax:"1", action, content});
  const r = await fetch("index.php", {
    method: "POST",
    headers: {"Content-Type":"application/x-www-form-urlencoded", "Accept":"application/json"},
    body
  });
  return r.json();
}

async function send(){
  err.style.display = "none";
  out.style.display = "none";
  const q = document.getElementById('ask').value.trim();
  if(!q) return;

  const res = await api("send", q);
  if(res.ok){
    out.textContent = res.reply;
    out.style.display = "block";
  }else{
    err.textContent = res.error;
    err.style.display = "block";
  }
}
async function resetChat(){
  await api("reset");
  out.style.display = "none";
  err.style.display = "none";
}

document.getElementById('sendBtn').onclick  = send;
document.getElementById('resetBtn').onclick = resetChat;
</script>
</body>
</html>






