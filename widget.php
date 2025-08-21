<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>SBM Chatbot Widget</title>
<style>
  :root{--sbm-blue:#1e88e5;--panel:#17181b;--panel-2:#1d1f24;--text:#e8e8e8}
  html,body{height:100%;margin:0;background:#fff;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--text)}
  .chat-launcher{position:fixed;right:22px;bottom:22px;background:var(--sbm-blue);color:#fff;border:0;border-radius:999px;padding:14px 18px;display:flex;align-items:center;gap:10px;box-shadow:0 10px 30px rgba(0,0,0,.35);cursor:pointer;z-index:9999}
  .chat-launcher svg{width:20px;height:20px}
  .chat-panel{position:fixed;right:22px;bottom:92px;width:420px;max-width:calc(100% - 44px);height:580px;background:var(--panel);border:1px solid #22262b;border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,.45);display:none;flex-direction:column;overflow:hidden;z-index:9998;animation:slideUp .2s ease-out}
  @keyframes slideUp{from{transform:translateY(10px);opacity:.6}to{transform:translateY(0);opacity:1}}
  .chat-panel.open{display:flex}
  .chat-header{background:var(--panel-2);border-bottom:1px solid #23262b;padding:10px 14px;display:flex;align-items:center;gap:10px}
  .chat-header .logo{display:inline-flex;align-items:center;gap:8px;font-weight:700;color:#fff}
  .chat-header .logo svg{width:28px;height:28px}
  .chat-header .spacer{flex:1}
  .chat-header .close{background:#2a2f36;color:#cfd6df;border:0;border-radius:8px;padding:8px 10px;cursor:pointer}
  .chat-frame{width:100%;height:100%;border:0;background:var(--panel)}
  @media (max-width:520px){.chat-panel{right:0;bottom:0;left:0;width:100%;height:85%;border-radius:12px 12px 0 0}}
</style>
</head>
<body>
<button class="chat-launcher" id="chatLauncher" aria-haspopup="dialog" aria-controls="sbmChatPanel">
  <svg viewBox="0 0 24 24" fill="none"><path d="M7 9h6M7 13h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M21 12a8 8 0 1 1-14.32 4.906L3 19l2.094-3.68A8 8 0 1 1 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
  <span>Chatbot</span>
</button>

<div class="chat-panel" id="sbmChatPanel" role="dialog" aria-label="SBM Chatbot">
  <div class="chat-header">
    <div class="logo" title="SBM IT Support Chatbot">
      <svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="4" y="6" width="40" height="36" rx="8" fill="#1e88e5"/><path d="M12 20h12M12 28h24" stroke="#fff" stroke-width="4" stroke-linecap="round"/></svg>
      <span>SBM IT Support Chatbot</span>
    </div>
    <div class="spacer"></div>
    <button class="close" id="chatClose">Close</button>
  </div>
  <iframe id="chatFrame" class="chat-frame" src="index.php" title="SBM Chatbot" loading="lazy" referrerpolicy="same-origin"></iframe>
</div>

<script>
  const launcher=document.getElementById('chatLauncher');
  const panel=document.getElementById('sbmChatPanel');
  const closeBtn=document.getElementById('chatClose');
  const frame=document.getElementById('chatFrame');

  launcher.addEventListener('click',()=>{panel.classList.toggle('open');if(panel.classList.contains('open')){frame.focus();}});
  closeBtn.addEventListener('click',()=>panel.classList.remove('open'));

  frame.addEventListener('load',()=>{try{const doc=frame.contentDocument||frame.contentWindow.document;if(!doc)return;const style=doc.createElement('style');style.textContent=`body{background:transparent!important}.wrap{max-width:100%!important;margin:0!important;padding:0!important}.card{border-radius:0!important;background:#17181b!important;box-shadow:none!important}.hdr{display:none!important}.sub{display:none!important}.chat{max-height:calc(100vh - 210px)!important}form{padding:12px!important}`;doc.head.appendChild(style);}catch(e){}});
</script>
</body>
</html>
