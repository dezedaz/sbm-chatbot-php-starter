<?php /* BlueGate simulation + widget chatbot (ouvre index.php dans un panneau) */ ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SBM BlueGate — Simulation</title>
  <link rel="icon" href="sbm_logo.png" />
  <style>
    :root{
      --bg:#0b0f14;              /* fond page style BlueGate */
      --panel:#111318;           /* panneau / barres */
      --text:#e6e6e6;
      --muted:#9aa4b2;
      --accent:#2d6cdf;          /* bleu SBM */
      --ring:#3a71ff;
      --shadow:0 18px 50px rgba(0,0,0,.45);
      --radius:16px;
    }

    /* RESET */
    *{box-sizing:border-box}
    html,body{margin:0;height:100%}
    body{
      background:radial-gradient(1200px 600px at 75% -100px, #0f1b2a 0%, transparent 50%) , var(--bg);
      color:var(--text); font: 16px/1.45 system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif;
    }
    a{color:var(--accent);text-decoration:none}
    a:hover{text-decoration:underline}

    /* Barre "BlueGate" */
    .topbar{
      position:sticky; top:0; z-index:5;
      display:flex; align-items:center; gap:12px;
      padding:16px 20px; background:rgba(17,19,24,.7); backdrop-filter: blur(10px);
      border-bottom:1px solid rgba(255,255,255,.06);
    }
    .topbar img{height:24px;width:24px;object-fit:contain}
    .topbar-title{font-weight:600;letter-spacing:.2px}
    .container{
      max-width:1100px; margin:32px auto; padding:0 20px;
    }
    .card{
      background:rgba(17,19,24,.7);
      border:1px solid rgba(255,255,255,.06);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      padding:24px;
    }
    .muted{color:var(--muted)}

    /* --- FAB (bouton flottant) --- */
    .chat-fab{
      position:fixed; right:24px; bottom:24px; z-index:20;
      display:flex; align-items:center; gap:10px;
      padding:12px 16px;
      background:linear-gradient(180deg, #2f7bff 0%, #2d6cdf 100%);
      color:#fff; border:none; border-radius:999px; cursor:pointer;
      box-shadow:0 10px 30px rgba(47,123,255,.35);
      transition: transform .15s ease, box-shadow .15s ease;
    }
    .chat-fab:hover{ transform: translateY(-1px); box-shadow:0 14px 40px rgba(47,123,255,.45);}
    .chat-fab img{height:20px;width:20px;display:block}

    /* --- Drawer (panneau du chatbot) --- */
    .chat-drawer{
      position:fixed; right:24px; bottom:90px; z-index:30;
      width:420px; max-width:calc(100vw - 32px);
      height:70vh; max-height:calc(100vh - 140px);
      background:var(--panel);
      border:1px solid rgba(255,255,255,.08);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      transform: translateY(8px) scale(.98);
      opacity:0; pointer-events:none;
      transition: opacity .18s ease, transform .18s ease;
      display:flex; flex-direction:column; overflow:hidden;
    }
    .chat-drawer.open{opacity:1; pointer-events:auto; transform: translateY(0) scale(1);}

    .drawer-head{
      display:flex; align-items:center; justify-content:space-between; gap:12px;
      padding:10px 12px; background:rgba(255,255,255,.03);
      border-bottom:1px solid rgba(255,255,255,.06);
    }
    .drawer-head .title{
      display:flex; align-items:center; gap:10px; font-weight:600;
    }
    .drawer-head img{height:20px;width:20px}
    .drawer-close{
      background:transparent; border:none; color:var(--text);
      font-size:20px; padding:4px 8px; cursor:pointer; line-height:1;
    }
    .chat-iframe{
      border:0; width:100%; height:100%;
      background:transparent;
    }

    /* Mobile = plein écran */
    @media (max-width: 640px){
      .chat-drawer{
        right:0; left:0; bottom:0;
        width:100vw; height:100vh; max-height:none; border-radius:0;
      }
      .chat-fab{ right:16px; bottom:16px; }
    }
  </style>
</head>
<body>
  <!-- En-tête BlueGate (simulation) -->
  <header class="topbar">
    <img src="sbm_logo.png" alt="SBM" />
    <div class="topbar-title">SBM BlueGate — Simulation</div>
  </header>

  <main class="container">
    <section class="card">
      <h2 style="margin:0 0 8px">Bienvenue 👋</h2>
      <p class="muted" style="margin:0">
        Ceci est une page de démonstration BlueGate. Utilise le bouton bleu en bas à droite
        pour ouvrir le <strong>chatbot SBM</strong> (même design, bulles et couleurs).
      </p>
    </section>
  </main>

  <!-- Bouton flottant -->
  <button class="chat-fab" id="openChat" aria-controls="drawer" aria-expanded="false">
    <img src="sbm_logo.png" alt="" /> Chatbot
  </button>

  <!-- Panneau du chatbot -->
  <aside class="chat-drawer" id="drawer" aria-hidden="true" role="dialog" aria-label="SBM Chatbot">
    <div class="drawer-head">
      <div class="title">
        <img src="sbm_logo.png" alt="SBM" />
        <span>SBM Chatbot</span>
      </div>
      <button class="drawer-close" id="closeChat" aria-label="Fermer">×</button>
    </div>

    <!-- On réutilise ton index.php tel quel (même DA) -->
    <iframe class="chat-iframe" title="SBM Chatbot" src="index.php"></iframe>
  </aside>

  <script>
    const btn = document.getElementById('openChat');
    const drawer = document.getElementById('drawer');
    const closeBtn = document.getElementById('closeChat');

    function openDrawer(){
      drawer.classList.add('open');
      drawer.setAttribute('aria-hidden','false');
      btn.setAttribute('aria-expanded','true');
    }
    function closeDrawer(){
      drawer.classList.remove('open');
      drawer.setAttribute('aria-hidden','true');
      btn.setAttribute('aria-expanded','false');
    }

    btn.addEventListener('click', () => {
      if(drawer.classList.contains('open')) closeDrawer(); else openDrawer();
    });
    closeBtn.addEventListener('click', closeDrawer);
    // Escape pour fermer
    window.addEventListener('keydown', e => { if(e.key === 'Escape') closeDrawer(); });
  </script>
</body>
</html>
