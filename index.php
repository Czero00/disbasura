<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#f8fafc">
  <title>DisBasura — Smart Garbage Collection System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--green:#07845f;--green-dark:#075b49;--mint:#71e4b7;--ink:#111a30;--muted:#64748b;--line:#e1e8ef;--pale:#f6f9fb}
    *{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth;scroll-padding-top:82px}
    body{font-family:'Plus Jakarta Sans',sans-serif;color:var(--ink);background:#fff;-webkit-font-smoothing:antialiased}
    a{color:inherit;text-decoration:none}
    .container{width:min(1216px,calc(100% - 48px));margin:auto}
    .site-header{height:82px;background:rgba(255,255,255,.96);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:10;backdrop-filter:blur(12px)}
    .nav{height:100%;display:flex;align-items:center;justify-content:space-between;gap:24px}
    .brand{display:flex;align-items:center;gap:12px;min-width:265px}
    .brand-mark{width:44px;height:44px;display:grid;place-items:center;border-radius:50%;color:white;background:#07976d;box-shadow:0 3px 8px #09291b20}
    .brand-mark svg{width:24px;height:24px}
    .brand-name{font-size:23px;line-height:1.15;font-weight:800;letter-spacing:-.8px}
    .brand-tag{display:block;color:var(--green);font-size:11px;letter-spacing:.1px;margin-top:2px}
    .nav-links{display:flex;align-items:center;gap:42px;color:#40516c;font-weight:700;font-size:14px}
    .nav-links a,.nav-actions a{transition:color .18s}
    .nav-links a:hover,.login-link:hover{color:var(--green)}
    .nav-actions{display:flex;align-items:center;gap:30px;font-size:14px;font-weight:700;color:#33445e}
    .signup{padding:12px 26px;border-radius:999px;background:#087c5b;color:white;box-shadow:0 3px 8px #09291b18}
    .signup:hover,.button-primary:hover{background:#066b50}
    .menu-toggle{display:none;border:0;background:transparent;color:var(--ink);font-size:25px;cursor:pointer}
    .login-modal{position:fixed;inset:0;z-index:30;display:none;place-items:center;padding:20px;background:rgba(7,25,31,.62);backdrop-filter:blur(5px)}
    .login-modal.open{display:grid}
    .login-dialog{position:relative;width:min(100%,460px);padding:36px;background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:0 28px 80px #061d1845}
    .login-brand{display:flex;align-items:center;gap:12px;margin-bottom:30px;padding-bottom:23px;border-bottom:1px solid #edf1f4}
    .login-brand .brand-mark{width:48px;height:48px;flex:none}
    .login-brand-copy{display:flex;flex-direction:column;align-items:flex-start;gap:2px}
    .login-brand-name{font-size:20px;line-height:1.2;font-weight:800;letter-spacing:-.5px;color:var(--ink)}
    .login-brand-title{display:block;font-size:12px;line-height:1.35;color:var(--muted)}
    .login-heading{font-size:27px;letter-spacing:-.8px;margin-bottom:7px}
    .login-dialog>p{color:var(--muted);font-size:14px;line-height:1.55;margin-bottom:22px}
    .login-close{position:absolute;right:17px;top:17px;width:36px;height:36px;border:1px solid #e5ebef;border-radius:50%;background:#fff;color:#64748b;font-size:23px;cursor:pointer;transition:background .18s,color .18s}
    .login-close:hover{background:#f1f7f4;color:var(--green)}
    .login-field{display:block;margin-top:16px;color:#33445e;font-size:13px;font-weight:700}
    .login-field input{display:block;width:100%;height:48px;margin-top:7px;padding:0 14px;border:1px solid #d8e2e9;border-radius:10px;font:inherit;font-weight:400;outline:none}
    .login-field input:focus{border-color:var(--green);box-shadow:0 0 0 3px #07845f20}
    .login-submit{width:100%;height:50px;margin-top:22px;border:0;border-radius:11px;background:linear-gradient(110deg,#0aa775,#087653);color:white;font:inherit;font-weight:700;cursor:pointer;box-shadow:0 7px 16px #07845f26;transition:transform .18s,box-shadow .18s}
    .login-submit:hover{transform:translateY(-1px);box-shadow:0 10px 20px #07845f35}
    .login-error{padding:10px 12px;border-radius:9px;background:#fff0f0;color:#b42318;font-size:13px;margin-bottom:12px}
    .login-success{padding:10px 12px;border-radius:9px;background:#e9f8ef;color:#167347;font-size:13px;margin-bottom:12px}
    body.modal-open{overflow:hidden}
    .hero{min-height:730px;background:#063d32;color:white;display:grid;place-items:center;text-align:center;padding:90px 24px;position:relative;overflow:hidden}
    .hero:before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(126,231,190,.19) 1px,transparent 1px);background-size:24px 24px;opacity:.6;pointer-events:none}
    .hero-content{position:relative;max-width:790px}
    .badge{display:inline-flex;align-items:center;gap:9px;color:#85e9bd;border:1px solid #16735d;background:#034a3b;border-radius:30px;padding:8px 16px;font-size:13px;font-weight:600;margin-bottom:36px}
    .badge-dot{width:8px;height:8px;background:#16a878;border-radius:50%}
    .hero h1{font-size:clamp(44px,6vw,74px);font-weight:800;letter-spacing:-3.8px;line-height:.99;margin-bottom:25px}
    .hero h1 span{display:block;color:#73e5b8;margin-top:7px}
    .hero p{font-size:19px;line-height:1.55;color:#c0d5d0;max-width:650px;margin:0 auto 39px}
    .hero-buttons{display:flex;justify-content:center;gap:16px}
    .button{display:inline-flex;align-items:center;justify-content:center;border-radius:12px;padding:17px 32px;min-width:156px;font-size:15px;font-weight:700;transition:background .18s,transform .18s}
    .button:hover{transform:translateY(-2px)}
    .button-primary{background:#0eae7c;color:#06281f}
    .button-secondary{border:1px solid #477769;background:#ffffff12;color:white}
    .button-secondary:hover{background:#ffffff20}
    .section{padding:96px 0}
    .section-heading{text-align:center;max-width:610px;margin:0 auto 62px}
    .eyebrow{color:var(--green);font-size:13px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;margin-bottom:10px}
    h2{font-size:36px;line-height:1.2;letter-spacing:-1.2px;font-weight:800}
    .section-intro{font-size:16px;line-height:1.65;color:var(--muted);margin-top:12px}
    .workflow{background:#fff}
    .steps{display:grid;grid-template-columns:repeat(4,1fr);gap:32px}
    .step{min-height:269px;border:1px solid var(--line);background:#f8fafc;border-radius:24px;padding:32px 27px;text-align:center;box-shadow:0 2px 3px #0f172a08}
    .step-number{height:48px;width:48px;border-radius:15px;background:#d1fae8;color:#087456;display:grid;place-items:center;font-size:17px;font-weight:800;margin:0 auto 26px}
    .step h3,.feature-card h3{font-size:19px;letter-spacing:-.4px;margin-bottom:12px}
    .step p,.feature-card p{font-size:14px;line-height:1.62;color:#61718a}
    .about{background:#f6f9fb;border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:104px 0}
    .about-grid{display:grid;grid-template-columns:1.04fr 1fr;gap:48px;align-items:center}
    .about-copy h2{max-width:550px;margin-bottom:22px}
    .about-copy>p{font-size:15.5px;line-height:1.68;color:#5b6b84}
    .stats{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:26px;border-top:1px solid var(--line);padding-top:24px}
    .stat strong{display:block;color:var(--green);font-size:30px;letter-spacing:-1px}
    .stat span{color:#52627a;font-size:14px}
    .about-panel{min-height:356px;padding:40px;border-radius:24px;background:linear-gradient(140deg,#075744,#096151);color:#fff;box-shadow:0 18px 28px #052e201c;display:flex;flex-direction:column;justify-content:center}
    .shield{width:56px;height:56px;border:1px solid #16806a;border-radius:15px;color:#71e4b7;background:#08725a;display:grid;place-items:center;margin-bottom:27px}
    .shield svg{width:28px;height:28px}
    .about-panel h3{font-size:24px;line-height:1.32;letter-spacing:-.5px;margin-bottom:14px}
    .about-panel p{font-size:14px;line-height:1.7;color:#c5dfd7}
    .panel-foot{color:#78dfb7!important;font-weight:700;margin-top:25px}
    .features{padding:96px 0}
    .feature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:32px}
    .feature-card{border:1px solid var(--line);background:#f8fafc;border-radius:24px;min-height:247px;padding:32px;box-shadow:0 2px 3px #0f172a08}
    .feature-icon{width:48px;height:48px;border-radius:13px;background:#d1fae8;color:#07845f;display:grid;place-items:center;margin-bottom:27px}
    .feature-icon svg{width:22px;height:22px}
    footer{background:#10182e;color:#fff;padding:32px 0}
    .footer-row{display:flex;align-items:center;justify-content:space-between;gap:24px}
    .footer-brand{display:flex;align-items:center;gap:12px;font-weight:700}
    .footer-brand .brand-mark{height:32px;width:32px}
    .copyright{color:#77839c;font-size:12px;text-align:center}
    .footer-links{display:flex;gap:26px;color:#a0aec1;font-size:13px;font-weight:700}
    @media(max-width:900px){.brand{min-width:auto}.nav-links{gap:22px}.steps{grid-template-columns:repeat(2,1fr)}.about-grid{gap:30px}.feature-grid{gap:18px}.feature-card{padding:25px}}
    @media(max-width:650px){.container{width:min(100% - 36px,1216px)}.site-header{height:72px}.brand-mark{width:40px;height:40px}.brand-name{font-size:20px}.brand-tag{font-size:10px}.menu-toggle{display:block;margin-left:auto}.nav-links{display:none;position:absolute;left:0;right:0;top:71px;background:#fff;padding:18px 24px 24px;border-bottom:1px solid var(--line);flex-direction:column;align-items:flex-start;gap:20px}.nav-links.open{display:flex}.nav-actions{gap:14px;font-size:13px}.signup{padding:10px 17px}.hero{min-height:620px;padding:82px 20px}.badge{font-size:11px;margin-bottom:28px}.hero h1{font-size:clamp(42px,12vw,62px);letter-spacing:-2.6px}.hero p{font-size:16px}.hero-buttons{gap:10px}.button{min-width:0;padding:15px 21px;font-size:14px}.section,.features{padding:72px 0}.section-heading{margin-bottom:38px}h2{font-size:30px}.steps,.feature-grid{grid-template-columns:1fr}.step{min-height:0;padding:27px 24px}.about{padding:76px 0}.about-grid{grid-template-columns:1fr}.about-panel{min-height:310px;padding:30px}.footer-row{flex-direction:column;text-align:center}.footer-links{order:3}.copyright{order:2;line-height:1.6}}
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*:before,*:after{transition:none!important}}
  </style>
</head>
<body>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="#home" aria-label="DisBasura home">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 4c-7.6.3-12 2.7-12 7.1 0 2.5 1.8 4.1 4.2 4.1C17 15.2 20.6 10.5 20 4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M4 20c1-5.1 4-8.1 9-9.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
      <span><span class="brand-name">DisBasura</span><span class="brand-tag">Smart Garbage Collection System</span></span>
    </a>
    <button class="menu-toggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>
    <nav class="nav-links" aria-label="Main navigation"><a href="#home">HOME</a><a href="#about">ABOUT US</a><a href="#services">SERVICE</a></nav>
    <div class="nav-actions"><a class="login-link" href="#login" data-open-login>Sign in</a><a class="signup" href="/disbasura/register.php">Sign up</a></div>
  </div>
</header>
<main>
  <section class="hero" id="home">
    <div class="hero-content">
      <div class="badge"><span class="badge-dot"></span> UC · DisBasura Capstone Project · 2026</div>
      <h1>Smart &amp; Efficient<span>Garbage Collection</span><span>System</span></h1>
      <p>A centralized barangay-level waste collection platform connecting administrators, sitio residents, and truck collectors seamlessly.</p>
      <div class="hero-buttons"><a class="button button-primary" href="/disbasura/register.php">Get Started</a><a class="button button-secondary" href="#workflow">How It Works</a></div>
    </div>
  </section>
  <section class="section workflow" id="workflow">
    <div class="container">
      <div class="section-heading"><p class="eyebrow">Simplified workflow</p><h2>How to Use This System</h2><p class="section-intro">Connecting community leaders, residents, and collection crews through a unified digital platform.</p></div>
      <div class="steps">
        <article class="step"><div class="step-number">1</div><h3>Admin Sets Up</h3><p>Barangay admin configures sitio zones, adds truck collector accounts, and establishes weekly collection schedules.</p></article>
        <article class="step"><div class="step-number">2</div><h3>Residents Register</h3><p>Residents sign up under their designated sitio to view collection timetables and submit special pickup requests.</p></article>
        <article class="step"><div class="step-number">3</div><h3>Collector Goes Live</h3><p>Collectors log in during route runs, enable real-time GPS tracking, and upload photo proof upon completion.</p></article>
        <article class="step"><div class="step-number">4</div><h3>Admin Monitors</h3><p>Live dashboard provides visibility over active trucks, completed pickups, missed disputes, and system logs.</p></article>
      </div>
    </div>
  </section>
  <section class="about" id="about">
    <div class="container about-grid">
      <div class="about-copy"><p class="eyebrow">About DisBasura</p><h2>Empowering Barangays with Smart Eco-Tech Innovations</h2><p>DisBasura is a comprehensive capstone initiative developed for local barangay waste management. By replacing inefficient traditional garbage collection schedules with a digitized real-time dispatch, tracking, and reporting platform, we minimize missed collections and optimize urban cleanliness.</p><div class="stats"><div class="stat"><strong>100%</strong><span>Digital Accountability</span></div><div class="stat"><strong>Real-Time</strong><span>GPS Dispatching</span></div></div></div>
      <aside class="about-panel"><div class="shield"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 19 6v5c0 4.7-2.8 8-7 10-4.2-2-7-5.3-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.7"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Cleaner Neighborhoods, Transparent Governance</h3><p>Our centralized database keeps sitio leaders, local residents, and truck operators on the same page. Photo proof uploads ensure zero ambiguity when collections are marked completed.</p><p class="panel-foot">Tested for Barangay Community Deployment&nbsp; ✓</p></aside>
    </div>
  </section>
  <section class="features" id="services">
    <div class="container"><div class="section-heading"><p class="eyebrow">Our Features</p><h2>Comprehensive Waste Services</h2></div>
      <div class="feature-grid">
        <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></div><h3>Sitio-Based Scheduling</h3><p>Organized waste pickup routes tailored specifically per sitio, preventing overflow and overlapping garbage truck schedules.</p></article>
        <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg></div><h3>Special Pickup Requests</h3><p>Residents can request special bulk disposal pickups directly through the portal with instant admin approval tracking.</p></article>
        <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2"/></svg></div><h3>Dispute &amp; Missed Resolution</h3><p>Instant reporting for missed collections helps barangay captains resolve residents' concerns quickly.</p></article>
      </div>
    </div>
  </section>
</main>
<div class="login-modal" id="login-modal" aria-hidden="true">
  <section class="login-dialog" role="dialog" aria-modal="true" aria-labelledby="login-title">
    <button class="login-close" type="button" aria-label="Close sign in" data-close-login>&times;</button>
    <div class="login-brand">
      <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M20 4c-7.6.3-12 2.7-12 7.1 0 2.5 1.8 4.1 4.2 4.1C17 15.2 20.6 10.5 20 4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M4 20c1-5.1 4-8.1 9-9.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
      <span class="login-brand-copy"><span class="login-brand-name">DisBasura</span><span class="login-brand-title">Smart Garbage Collection System</span></span>
    </div>
    <h2 class="login-heading" id="login-title">Welcome back</h2>
    <p>Sign in with your account. We’ll take you to the right dashboard.</p>
    <?php if (!empty($_GET['registered'])): ?><div class="login-success">Account created successfully. Sign in to continue.</div><?php endif; ?>
    <?php if (!empty($_GET['login_error'])): ?><div class="login-error"><?= htmlspecialchars($_GET['login_error'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="/disbasura/login.php">
      <label class="login-field">Username<input name="username" required autocomplete="username"></label>
      <label class="login-field">Password<input type="password" name="password" required autocomplete="current-password"></label>
      <button class="login-submit" type="submit">Sign in</button>
    </form>
  </section>
</div>
<footer><div class="container footer-row"><a class="footer-brand" href="#home"><span class="brand-mark"><svg viewBox="0 0 24 24" fill="none"><path d="M20 4c-7.6.3-12 2.7-12 7.1 0 2.5 1.8 4.1 4.2 4.1C17 15.2 20.6 10.5 20 4Z" stroke="currentColor" stroke-width="1.6"/><path d="M4 20c1-5.1 4-8.1 9-9.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>DisBasura</a><p class="copyright">© 2026 DisBasura Capstone System. All rights reserved.</p><nav class="footer-links" aria-label="Footer navigation"><a href="#home">Home</a><a href="#about">About</a><a href="#services">Service</a></nav></div></footer>
<script>
  const menuButton=document.querySelector('.menu-toggle');
  const navLinks=document.querySelector('.nav-links');
  menuButton.addEventListener('click',()=>{const open=navLinks.classList.toggle('open');menuButton.setAttribute('aria-expanded',String(open));});
  navLinks.addEventListener('click',event=>{if(event.target.closest('a')){navLinks.classList.remove('open');menuButton.setAttribute('aria-expanded','false');}});
  const loginModal=document.getElementById('login-modal');
  const openLogin=()=>{loginModal.classList.add('open');loginModal.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');loginModal.querySelector('input').focus();};
  const closeLogin=()=>{loginModal.classList.remove('open');loginModal.setAttribute('aria-hidden','true');document.body.classList.remove('modal-open');};
  document.querySelectorAll('[data-open-login]').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();openLogin();}));
  document.querySelector('[data-close-login]').addEventListener('click',closeLogin);
  loginModal.addEventListener('click',event=>{if(event.target===loginModal)closeLogin();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&loginModal.classList.contains('open'))closeLogin();});
  const query=new URLSearchParams(location.search);
  if(query.has('show_login')||query.has('login_error')||query.has('registered'))openLogin();
</script>
</body>
</html>
