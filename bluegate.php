<?php
// For footer info (display only)
$endpoint  = getenv('AZURE_ENDPOINT') ?: 'https://YOUR-ENDPOINT.openai.azure.com';
$api_ver   = '2024-07-01-preview';
$assistant = getenv('ASSISTANT_ID') ?: 'asst_********';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>SBM BlueGate — Demo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="sbm_logo.png" />
  <style>
    :root{
      --bg:#0b0f14;--panel:#111823;--panel-2:#0f1620;--text:#e9eef6;--muted:#9db0c5;
      --primary:#2f80ff;--primary-700:#2563eb;--accent:#1f2937;--border:#1f2a3a;
      --chip:#0d223b;--danger:#f59e0b;
    }
    *{box-sizing:border-box}
    body{
      margin:0;background:var(--bg);color:var(--text);font:16px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
    }
    header{
      display:flex;align-items:center;gap:.6rem;padding:16px 22px;border-bottom:1px solid var(--border);
      background:linear-gradient(180deg,rgba(47,128,255,.06),transparent);
    }
    header img{height:18px;opacity:.9}
    header h1{margin:0;font-size:16px;font-weight:600;color:#cfe2ff}
    main{max-width:1100px;margin:28px auto;padding:0 20px}
    .hero{
      background:linear-gradient(180deg,var(--panel),var(--panel-2));border:1px solid var(--border);
      border-radius:18px;padding:28px 28px 26px;box-shadow:0 8px 24px rgba(0,0,0,.35);
    }
    .hero h2{margin:0 0 10px;font-size:28px;letter-spacing:.2px}
    .hero p{margin:0;color:var(--muted)}
    /* Floating button */
    .fab{
      position:fixed;right:20px;bottom:20px;display:flex;align-items:center;gap:.6rem;
      background:var(--primary);color:#fff;border:none;border-radius:999px;padding:12px 18px;
      cursor:pointer;box-shadow:0 10px 24px rgba(47,128,255,.35);font-weight:600
    }
    .fab:hover{background:var(--primary-700)}
    /* Widget panel */
    .widget{
      position:fixed;right:20px;bottom:92px;width:min(520px,92vw);
      background:var(--panel);border:1px solid var(--border);border-radius:16px;
      box-shadow:0 18px 50px rgba(0,0,0,.5);display:none;flex-direction:column;overflow:hidden;
    }
    .widget.open{display:flex}
    .w-head{
      display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--border);
      background:linear-gradient(180deg,rgba(47,128,255,.08),rgba(47,128,255,.02));
    }
    .w-title{display:flex;align-items:center;gap:.5rem;font-weight:700}
    .close{
      background:transparent;border:1px solid var(--border);color:var(--muted);border-radius:10px;padding:6px 10px;cursor:pointer
    }
    .w-body{padding:16px;display:flex;flex-direction:column;gap:10px}
    .row{display:flex;gap:8px}
    .row input{
      flex:1;background:#0b1220;border:1px solid var(--border);color:var(--text);border-radius:10px;padding:12px
    }
    .row button{
      background:var(--primary);border:none;color:#fff;border-radius:10px;padding:12px 16px;font-weight:600;cursor:pointer
    }
    .row button:hover{background:var(--primary-700)}
    .muted{font-size:12px;color:var(--muted);background:var(--chip);border:1px solid var(--border);padding:10px;border-radius:10px}
    .danger{background:#3b2a0a;border-color:#4c3610;color:#ffedc2}
    .footer{
      font-size:12px;color:var(--muted);display:flex;gap:.6rem;flex-wrap:wrap;padding:0 16px 14px
    }
    .chip{background:var(--chip);border:1px solid var(--border);padding:6px 8px;border-radius:999px}
  </style>
</head>
<body>
  <header>
    <img src="sbm_logo.png" alt="SBM logo" />
    <h1>SBM BlueGate — Demo</h1>
  </header>

  <main>
    <section class="hero">
      <h2>Welcome 👋</h2>
      <p>This is a BlueGate demonstration page. Use the blue button at the bottom-right to open the chatbot.</p>
    </section>
  </main>

  <!-- Chat widget -->
  <section id="widget" class="widget" aria-live="polite">
    <div class="w-head">
      <div class="w-title">
        <img src="sbm_logo.png" alt="" style="height:16px;opacity:.8" />
        <span>SBM Chatbot</span>
      </div>
      <button class="close" id="closeBtn" aria-label="Close chatbot">Close</button>
    </div>

    <div class="w-body">
      <div id="status" class="muted" style="display:none"></div>

      <div class="row">
        <input id="msg" type="text" placeholder="Type your question..." />
        <button id="sendBtn">Send</button>
        <button id="resetBtn" style="background:#374151">Reset</button>
      </div>

      <div class="muted">
        <strong>Endpoint:</strong> <?php echo htmlspecialchars($endpoint); ?> &nbsp;•&nbsp;
        <strong>API:</strong> <?php echo $api_ver; ?> &nbsp;•&nbsp;
        <strong>Assistant:</strong> <?php echo htmlspecialchars($assistant); ?>
      </div>
    </div>

    <div class="footer">
      <span class="chip">Tip: ask IT or company-knowledge questions.</span>
    </div>
  </section>

  <button class="fab" id="fabBtn" aria-expanded="false">Chatbot</button>

  <script>
    const widget   = document.getElementById('widget');
    const fabBtn   = document.getElementById('fabBtn');
    const closeBtn = document.getElementById('closeBtn');
    const sendBtn  = document.getElementById('sendBtn');
    const resetBtn = document.getElementById('resetBtn');
    const input    = document.getElementById('msg');
    const statusEl = document.getElementById('status');

    function openWidget(){ widget.classList.add('open'); fabBtn.setAttribute('aria-expanded','true'); input.focus(); }
    function closeWidget(){ widget.classList.remove('open'); fabBtn.setAttribute('aria-expanded','false'); }
    fabBtn.addEventListener('click', openWidget);
    closeBtn.addEventListener('click', closeWidget);

    // Helper to show temporary status/info
    function showStatus(text, kind='info'){
      statusEl.textContent = text;
      statusEl.style.display = 'block';
      statusEl.className = 'muted' + (kind==='error' ? ' danger' : '');
      setTimeout(()=>{ statusEl.style.display='none'; }, 4000);
    }

    // Send message to your existing PHP endpoint (index.php)
    async function sendMessage(){
      const q = input.value.trim();
      if(!q){ input.focus(); return; }
      sendBtn.disabled = true;

      try{
        // If your index.php exposes an AJAX path, keep it. Otherwise this will still POST to index.php.
        const res = await fetch('index.php?ajax=1', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({ message:q })
        });

        // Try to read text; many backends return plaintext
        const txt = await res.text();
        if(!res.ok){
          showStatus(`Error ${res.status}: ${txt || 'Request failed.'}`, 'error');
        }else{
          showStatus('Message sent. Check the main chat page for full history.', 'info');
        }
      }catch(err){
        showStatus('Network error. Please try again.', 'error');
      }finally{
        sendBtn.disabled = false;
        input.value = '';
        input.focus();
      }
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', e => { if(e.key==='Enter') sendMessage(); });

    resetBtn.addEventListener('click', async ()=>{
      try{
        await fetch('index.php?reset=1', { method:'GET' });
        showStatus('Chat has been reset.');
      }catch(_){
        showStatus('Unable to reset chat.', 'error');
      }
    });
  </script>
</body>
</html>


