<?php
session_start();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SBM Chatbot — Widget</title>
<style>
  :root{
    --bg:#0f0f10;
    --card:#1b1c1f;
    --header:#202228;
    --accent:#1e88e5;
    --text:#e8e8e8;
    --muted:#9aa0a6;
    --shadow:0 14px 40px rgba(0,0,0,.45);
    --radius:18px;
  }
  *{box-sizing:border-box}
  html,body{height:100%;margin:0;background:#fff}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;color:var(--text)}

  /* Launcher button */
  .sbm-launcher{
    position:fixed; right:24px; bottom:24px;
    display:flex; align-items:center; gap:10px;
    padding:14px 18px; background:var(--accent); color:#fff;
    border:none; border-radius:999px; cursor:pointer;
    box-shadow:var(--shadow);
    font-weight:600; letter-spacing:.2px;
  }
  .sbm-launcher .dot{
    width:12px;height:12px;border-radius:50%;background:#fff;opacity:.85;
  }

  /* Panel shell */
  .sbm-panel{
    position:fixed; right:24px; bottom:92px;
    width:420px; height:620px;
    max-height:85vh;
    background:var(--card);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    display:flex; flex-direction:column;
    overflow:hidden;
    transform:translateY(12px);
    opacity:0; pointer-events:none;
    transition:opacity .18s ease, transform .18s ease;
  }
  .sbm-panel.open{opacity:1; transform:translateY(0); pointer-events:auto;}

  /* Responsive */
  @media (max-width:560px){
    .sbm-panel{ right:12px; left:12px; width:auto; height:82vh; bottom:96px; }
    .sbm-launcher{ right:12px; bottom:12px; }
  }

  /* Header */
  .sbm-header{
    height:56px; background:var(--header);
    display:flex; align-items:center; justify-content:space-between;
    padding:0 16px; border-bottom:1px solid rgba(255,255,255,.06);
  }
  .sbm-title{ font-size:16px; font-weight:700; }
  .sbm-close{
    background:transparent; border:none; color:#fff;
    width:36px; height:36px; border-radius:10px; cursor:pointer;
  }
  .sbm-close:hover{ background:rgba(255,255,255,.07); }

  /* Body = iframe wrapper exactly fills remaining height */
  .sbm-body{ 
    height: calc(100% - 56px);
    display:block; 
  }
  .sbm-iframe{
    width:100%; height:100%; border:0; display:block;
    background:var(--bg);
  }
</style>
</head>
<body>

<button class="sbm-launcher" id="openBtn">
  <span class="dot"></span> Chatbot
</button>

<div class="sbm-panel" id="panel">
  <div class="sbm-header">
    <div class="sbm-title">SBM IT Support Chatbot</div>
    <button class="sbm-close" id="closeBtn" aria-label="Close">✕</button>
  </div>
  <div class="sbm-body">
    <iframe class="sbm-iframe" src="index.php" title="SBM Chatbot"></iframe>
  </div>
</div>

<script>
  const panel = document.getElementById('panel');
  const openBtn = document.getElementById('openBtn');
  const closeBtn = document.getElementById('closeBtn');

  openBtn.addEventListener('click', ()=> panel.classList.add('open'));
  closeBtn.addEventListener('click', ()=> panel.classList.remove('open'));

  // Optional: close if user clicks outside on mobile
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') panel.classList.remove('open'); });
</script>
</body>
</html>

