<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>BlueGate – Mini Chat (Demo)</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
  body{margin:0;background:#0b0f14;color:#e9eef6;font:16px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
  #chatBtn{position:fixed;right:16px;bottom:16px;padding:10px 14px;border:0;border-radius:999px;background:#2f80ff;color:#fff;font-weight:600;cursor:pointer}
  #panel{position:fixed;right:16px;bottom:70px;width:320px;display:none;background:#111823;border:1px solid #1f2a3a;border-radius:12px;overflow:hidden;box-shadow:0 12px 30px rgba(0,0,0,.45)}
  #head{padding:10px 12px;background:#0f1620;font-weight:700}
  #msgs{height:220px;overflow:auto;padding:10px}
  .msg{margin:6px 0;max-width:80%;padding:8px 10px;border-radius:10px;word-wrap:break-word}
  .me{background:#2f80ff;color:#fff;margin-left:auto}
  .bot{background:#0d223b}
  #bar{display:flex;gap:6px;padding:8px;background:#0f1620}
  #bar input{flex:1;padding:8px;border:1px solid #1f2a3a;background:#0b1220;color:#e9eef6;border-radius:8px}
  #bar button{padding:8px 10px;border:0;border-radius:8px;background:#2f80ff;color:#fff;font-weight:600;cursor:pointer}
</style>
</head>
<body>

<button id="chatBtn">Chat</button>

<div id="panel" aria-live="polite">
  <div id="head">SBM Chatbot (Demo)</div>
  <div id="msgs"></div>
  <div id="bar">
    <input id="inp" placeholder="Type a message…" autocomplete="off" />
    <button id="send">Send</button>
  </div>
</div>

<script>
  const btn = document.getElementById('chatBtn');
  const panel = document.getElementById('panel');
  const msgs = document.getElementById('msgs');
  const inp = document.getElementById('inp');

  // toggle panel
  btn.onclick = () => panel.style.display = panel.style.display ? '' : 'block';

  function add(text, cls){
    const d = document.createElement('div');
    d.className = 'msg ' + cls;
    d.textContent = text;
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
  }

  // super simple demo reply
  function simulateReply(q){
    return 'Demo reply • I received: "' + q + '"';
  }

  function send(){
    const q = inp.value.trim(); if(!q) return;
    add(q, 'me'); inp.value = '';
    setTimeout(() => add(simulateReply(q), 'bot'), 300);
  }

  document.getElementById('send').onclick = send;
  inp.addEventListener('keydown', e => { if(e.key === 'Enter') send(); });
</script>
</body>
</html>

