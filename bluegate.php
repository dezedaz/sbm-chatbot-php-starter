<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>SBM BlueGate — Chatbot</title>
<style>
  :root{
    --bg:#0b0f14; --panel:#0f1620; --panel2:#111823; --text:#ecf2ff; --muted:#a7b3c6;
    --accent:#2f80ff; --border:#1f2a3a; --bubble:#0d223b; --danger:#db5461;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font:16px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
  .wrap{max-width:1000px;margin:32px auto;padding:0 16px}
  .card{background:var(--panel);border:1px solid var(--border);border-radius:14px;padding:22px;box-shadow:0 8px 28px rgba(0,0,0,.35)}
  h1{margin:0 0 12px 0;font-size:28px}
  p{margin:0;color:var(--muted)}

  #chatBtn{position:fixed;right:18px;bottom:18px;padding:12px 16px;border:0;border-radius:999px;
    background:var(--accent);color:#fff;font-weight:700;box-shadow:0 10px 24px rgba(47,128,255,.35);cursor:pointer}

  #panel{position:fixed;right:18px;bottom:78px;width:360px;max-width:92vw;display:none;background:var(--panel2);
    border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 18px 36px rgba(0,0,0,.5)}
  #head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:12px 14px;background:var(--panel);
    border-bottom:1px solid var(--border)}
  #head .title{font-weight:800}
  #head .close{border:0;background:transparent;color:var(--muted);font-size:18px;cursor:pointer}

  #msgs{height:280px;overflow:auto;padding:12px}
  .msg{margin:8px 0;max-width:82%;padding:9px 11px;border-radius:12px;word-wrap:break-word;white-space:pre-wrap}
  .me{background:var(--accent);color:#fff;margin-left:auto}
  .bot{background:var(--bubble);color:var(--text)}
  .err{background:rgba(219,84,97,.15);border:1px solid rgba(219,84,97,.5);color:#ffdfe2}

  #bar{display:flex;gap:8px;padding:10px;border-top:1px solid var(--border);background:var(--panel)}
  #inp{flex:1;padding:10px;border-radius:10px;border:1px solid var(--border);background:#0b1220;color:var(--text)}
  #send,#reset{padding:10px 12px;border:0;border-radius:10px;font-weight:700;cursor:pointer}
  #send{background:var(--accent);color:#fff}
  #reset{background:#1e2a3a;color:var(--muted)}
  .hint{padding:8px 12px;color:var(--muted);font-size:12px;border-top:1px solid var(--border);background:var(--panel)}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Welcome 👋</h1>
      <p>This is a BlueGate demo page. Use the blue button at the bottom-right to open the chatbot panel.</p>
    </div>
  </div>

  <button id="chatBtn" aria-controls="panel">Chatbot</button>

  <div id="panel" role="dialog" aria-modal="true" aria-labelledby="chatTitle">
    <div id="head">
      <div class="title" id="chatTitle">SBM Chatbot</div>
      <button class="close" title="Close" aria-label="Close">&times;</button>
    </div>

    <div id="msgs" aria-live="polite" aria-atomic="false"></div>

    <div id="bar">
      <input id="inp" placeholder="Type a message…" autocomplete="off" />
      <button id="send">Send</button>
      <button id="reset" title="Clear messages">Reset</button>
    </div>

    <div class="hint">Connected to your server (Assistants API via Render).</div>
  </div>

<script>
  const btn   = document.getElementById('chatBtn');
  const panel = document.getElementById('panel');
  const close = document.querySelector('#head .close');
  const msgs  = document.getElementById('msgs');
  const inp   = document.getElementById('inp');
  const sendB = document.getElementById('send');
  const resetB= document.getElementById('reset');

  let busy = false;
  const openPanel  = () => { panel.style.display = 'block'; inp.focus(); };
  const closePanel = () => { panel.style.display = 'none'; };

  btn.addEventListener('click', openPanel);
  close.addEventListener('click', closePanel);

  function add(text, cls){
    const d = document.createElement('div');
    d.className = 'msg ' + cls;
    d.textContent = text;
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
  }

  async function callAPI(message){
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 60000);
    try {
      const res = await fetch('chat_api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ message }),
        signal: controller.signal
      });
      clearTimeout(timeout);
      if (!res.ok) throw new Error(`HTTP ${res.status}: ${await res.text()}`);
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'Unknown server error');
      return json.reply || 'No answer produced.';
    } catch (err) {
      clearTimeout(timeout);
      throw err;
    }
  }

  async function send(){
    if (busy) return;
    const q = inp.value.trim();
    if(!q) return;
    add(q, 'me');
    inp.value = '';
    busy = true;
    try {
      const reply = await callAPI(q);
      add(reply, 'bot');
    } catch (e){
      add(`Error: ${e.message || e}`, 'err');
    } finally {
      busy = false;
      inp.focus();
    }
  }

  sendB.addEventListener('click', send);
  inp.addEventListener('keydown', e => { if(e.key === 'Enter') send(); });
  resetB.addEventListener('click', () => { msgs.innerHTML = ''; });
</script>
</body>
</html>



