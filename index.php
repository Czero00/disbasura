<?php
require_once __DIR__ . '/config/db.php';
$registrationEnabled = (bool)get_db()->query('SELECT id FROM administrators LIMIT 1')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#001912">
  <title>DisBasura — Smart Garbage Collection System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--green:#00a879;--green-dark:#003d2f;--mint:#6ce8b5;--ink:#f7fffb;--muted:#a7c8bc;--line:#123e34;--pale:#062e25}
    *{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth;scroll-padding-top:82px}
    body{font-family:'Plus Jakarta Sans',sans-serif;color:var(--ink);background:#042a21;-webkit-font-smoothing:antialiased}
    a{color:inherit;text-decoration:none}
    .container{width:min(1220px,calc(100% - 60px));margin:auto}
    .site-header{height:92px;background:rgba(0,25,18,.97);border-bottom:1px solid #104438;position:sticky;top:0;z-index:10;backdrop-filter:blur(12px)}
    .nav{height:100%;display:flex;align-items:center;justify-content:space-between;gap:20px}
    .brand{display:flex;align-items:center;gap:10px;min-width:250px;padding:7px 14px;border:1px solid #0c6654;border-radius:18px;background:#071a20}
    .brand-mark{width:40px;height:40px;display:grid;place-items:center;border-radius:12px;color:white;background:linear-gradient(145deg,#10c993,#007c5b);box-shadow:0 5px 12px #00a87935}
    .brand-mark svg{width:24px;height:24px}
    .brand>.brand-mark svg path:first-child{fill:currentColor}
    .brand-name{font-size:21px;line-height:1.15;font-weight:800;letter-spacing:-.8px;color:#f7fffb}
    .brand-tag{display:block;color:#44bd98;font-size:10px;font-weight:800;letter-spacing:.35px;text-transform:uppercase;margin-top:2px}
    .nav-links{display:flex;align-items:center;gap:10px;padding:6px;border:1px solid #152c3c;border-radius:17px;background:#030f19;color:#c3cfdb;font-weight:800;font-size:13px}
    .nav-links a,.nav-actions a{transition:color .18s,background .18s,border-color .18s,transform .18s}
    .nav-links a{padding:10px 23px;border:1px solid #1c2d40;border-radius:14px;background:#091421}
    .nav-links a:hover,.nav-links a.active{color:#21d6a1;border-color:#087c61;background:#002c24}
    .nav-actions{display:flex;align-items:center;gap:12px;font-size:13px;font-weight:800;color:#d8e4e0}
    .login-link{padding:10px 20px;border:1px solid #0c6654;border-radius:14px;background:#071a20}
    .login-link:hover{color:#54e3b2;background:#0a3028}
    .signup{padding:10px 23px;border:1px solid #10b888;border-radius:14px;background:#069b70;color:white;box-shadow:0 5px 14px #00a87935}
    .signup:hover,.button-primary:hover{background:#08b985;transform:translateY(-1px)}
    .menu-toggle{display:none;border:0;background:#0a8d68;border-radius:10px;color:white;font-size:25px;cursor:pointer}
    .login-modal{position:fixed;inset:0;z-index:30;display:none;place-items:center;padding:20px;background:linear-gradient(180deg,rgba(0,25,29,.9) 0%,rgba(4,25,31,.88) 53%,rgba(67,70,82,.91) 54%,rgba(67,70,82,.91) 100%);backdrop-filter:blur(8px)}
    .login-modal.open{display:grid}
    .login-dialog{position:relative;width:min(100%,505px);padding:36px 38px;background:#fbfbfc;border:1px solid rgba(255,255,255,.72);border-radius:28px;box-shadow:0 28px 80px #061d1845;color:#111a30}
    .login-brand{display:flex;align-items:center;gap:13px;margin-bottom:28px}
    .login-brand .brand-mark{width:48px;height:48px;flex:none}
    .login-brand .brand-mark svg path:first-child{fill:currentColor}
    .login-brand-copy{display:flex;flex-direction:column;align-items:flex-start;gap:2px}
    .login-brand-name{font-size:20px;line-height:1.2;font-weight:800;letter-spacing:-.5px;color:#111a30}
    .login-brand-title{display:block;font-size:13px;line-height:1.35;color:#8799b3}
    .login-heading{font-size:28px;letter-spacing:-.9px;margin-bottom:5px;color:#111a30}
    .login-dialog>p{color:#71819b;font-size:13px;line-height:1.55;margin-bottom:23px}
    .login-close{position:absolute;right:26px;top:26px;width:38px;height:38px;border:0;border-radius:50%;background:#f0f3f7;color:#66809c;font-size:23px;cursor:pointer;transition:background .18s,color .18s}
    .login-close:hover{background:#f1f7f4;color:var(--green)}
    .login-field{display:block;margin-top:17px;color:#33445e;font-size:13px;font-weight:700}
    .login-field input{display:block;width:100%;height:52px;margin-top:7px;padding:0 18px;border:1px solid #d8e2e9;border-radius:13px;background:#fff;color:#172033;font:inherit;font-size:15px;font-weight:400;outline:none}
    .login-field input:focus{border-color:var(--green);box-shadow:0 0 0 3px #07845f20}
    .password-wrap{position:relative;margin-top:7px}
    .login-field .password-wrap input{margin:0;padding-right:54px}
    .password-toggle{position:absolute;right:9px;top:50%;transform:translateY(-50%);width:36px;height:36px;border:0;background:transparent;color:#8a9db5;display:grid;place-items:center;cursor:pointer}
    .password-toggle svg{width:20px;height:20px}
    .forgot-row{display:flex;justify-content:flex-end;margin-top:12px;font-size:13px;font-weight:700;color:#00a879}
    .forgot-row a:hover,.signup-prompt a:hover{color:#087653;text-decoration:underline}
    .login-submit{width:100%;height:58px;margin-top:22px;border:0;border-radius:13px;background:#079b70;color:white;font:inherit;font-size:16px;font-weight:800;cursor:pointer;box-shadow:0 9px 17px #07845f35;transition:transform .18s,box-shadow .18s,background .18s}
    .login-submit:hover{transform:translateY(-1px);box-shadow:0 10px 20px #07845f35}
    .signup-prompt{text-align:center;color:#71819b;font-size:13px;margin-top:27px}
    .signup-prompt a{color:#009d70;font-weight:800}
    .login-error{padding:10px 12px;border-radius:9px;background:#fff0f0;color:#b42318;font-size:13px;margin-bottom:12px}
    .login-success{padding:10px 12px;border-radius:9px;background:#e9f8ef;color:#167347;font-size:13px;margin-bottom:12px}
    body.modal-open{overflow:hidden}
    .hero{min-height:576px;background:radial-gradient(ellipse at 50% 30%,#064734 0%,#00382b 55%,#00251c 100%);color:white;display:grid;place-items:center;text-align:center;padding:70px 24px;position:relative;overflow:hidden}
    .hero:before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,14,10,.04),rgba(0,14,10,.2));pointer-events:none}
    .hero-content{position:relative;max-width:900px}
    .badge{display:inline-flex;align-items:center;gap:9px;color:#67e7b7;border:1px solid #155a4c;background:#071e22;border-radius:30px;padding:11px 21px;font-size:13px;font-weight:800;margin-bottom:38px}
    .badge-dot{width:8px;height:8px;background:#16a878;border-radius:50%}
    .hero h1{font-size:clamp(46px,7vw,72px);font-weight:800;letter-spacing:-3.8px;line-height:.94;margin-bottom:27px}
    .hero h1 span{display:block;color:#73e5b8;margin-top:5px}
    .hero p{font-size:19px;line-height:1.55;color:#b3d1c8;max-width:700px;margin:0 auto 43px}
    .hero-buttons{display:flex;justify-content:center;gap:16px}
    .button{display:inline-flex;align-items:center;justify-content:center;border-radius:12px;padding:17px 32px;min-width:156px;font-size:15px;font-weight:700;transition:background .18s,transform .18s}
    .button:hover{transform:translateY(-2px)}
    .button-primary{background:linear-gradient(110deg,#38d6a0,#08b986);color:#06281f}
    .button-secondary{border:1px solid #145648;background:#091a24;color:white}
    .button-secondary:hover{background:#102a32}
    .section{padding:96px 0}
    .section-heading{text-align:center;max-width:610px;margin:0 auto 62px}
    .eyebrow{color:var(--green);font-size:13px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;margin-bottom:10px}
    h2{font-size:36px;line-height:1.2;letter-spacing:-1.2px;font-weight:800}
    .section-intro{font-size:16px;line-height:1.65;color:var(--muted);margin-top:12px}
    .workflow,.about{color:#14231d}
    .features{background:#032b22;color:#f7fffb}
    .workflow{background:#fff}
    .workflow h2,.about h2,.step h3,.feature-card h3{color:#14231d}
    .features h2{color:#f7fffb}
    .features .eyebrow{color:#39dca7}
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
    @media(max-width:900px){.brand{min-width:auto;padding:8px 12px}.nav-links{gap:7px;padding:6px}.nav-links a{padding:11px 16px}.nav-actions{gap:9px}.steps{grid-template-columns:repeat(2,1fr)}.about-grid{gap:30px}.feature-grid{gap:18px}.feature-card{padding:25px}}
    @media(max-width:520px){.login-modal{padding:14px}.login-dialog{padding:30px 24px;border-radius:23px;max-height:calc(100vh - 28px);overflow-y:auto}.login-brand{margin-bottom:24px}.login-close{right:18px;top:18px}.login-heading{font-size:25px}}
    @media(max-width:650px){.container{width:min(100% - 30px,1220px)}.site-header{height:82px}.brand{gap:9px;padding:6px 9px;border-radius:15px}.brand-mark{width:38px;height:38px}.brand-name{font-size:18px}.brand-tag{font-size:8px}.menu-toggle{display:block;margin-left:auto;width:40px;height:40px}.nav{gap:8px}.nav-links{display:none;position:absolute;left:15px;right:15px;top:75px;background:#031b16;padding:14px;border:1px solid #124437;border-radius:16px;flex-direction:column;align-items:stretch;gap:8px}.nav-links.open{display:flex}.nav-links a{text-align:center}.nav-actions{gap:7px;font-size:12px}.login-link{padding:9px 12px}.signup{padding:9px 13px}.hero{min-height:590px;padding:72px 18px}.badge{font-size:10px;padding:9px 13px;margin-bottom:30px}.hero h1{font-size:clamp(39px,11vw,58px);letter-spacing:-2.6px;line-height:.98}.hero p{font-size:15px}.hero-buttons{gap:10px}.button{min-width:0;padding:14px 18px;font-size:13px}.section,.features{padding:72px 0}.section-heading{margin-bottom:38px}h2{font-size:30px}.steps,.feature-grid{grid-template-columns:1fr}.step{min-height:0;padding:27px 24px}.about{padding:76px 0}.about-grid{grid-template-columns:1fr}.about-panel{min-height:310px;padding:30px}.footer-row{flex-direction:column;text-align:center}.footer-links{order:3}.copyright{order:2;line-height:1.6}}
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*:before,*:after{transition:none!important}}
  </style>
</head>
<body>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="#home" aria-label="DisBasura home">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 4c-7.6.3-12 2.7-12 7.1 0 2.5 1.8 4.1 4.2 4.1C17 15.2 20.6 10.5 20 4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M4 20c1-5.1 4-8.1 9-9.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
      <span><span class="brand-name">DisBasura</span><span class="brand-tag">Smart Garbage Collection</span></span>
    </a>
    <button class="menu-toggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>
    <nav class="nav-links" aria-label="Main navigation"><a href="#home">HOME</a><a href="#about">ABOUT US</a><a href="#services">SERVICES</a></nav>
    <div class="nav-actions"><a class="login-link" href="#login" data-open-login>Sign in</a><a class="signup" href="<?= $registrationEnabled ? '/disbasura/register.php' : '/disbasura/admin/login.php' ?>"><?= $registrationEnabled ? 'Sign up' : 'Admin Setup' ?></a></div>
  </div>
</header>
<main>
  <section class="hero" id="home">
    <div class="hero-content">
      <h1>Smart &amp; Efficient<span>Garbage Collection</span><span>System</span></h1>
      <p>A centralized barangay-level waste collection platform connecting administrators, sitio residents, and truck collectors seamlessly.</p>
      <div class="hero-buttons"><a class="button button-primary" href="<?= $registrationEnabled ? '/disbasura/register.php' : '/disbasura/admin/login.php' ?>"><?= $registrationEnabled ? 'Get Started' : 'Set Up Admin' ?></a><a class="button button-secondary" href="#workflow">How It Works</a></div>
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
      <label class="login-field">Username<input name="username" placeholder="Enter your username" required autocomplete="username"></label>
      <label class="login-field">Password
        <span class="password-wrap">
          <input id="login-password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
          <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-toggle-password>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </span>
      </label>
      <div class="forgot-row"><a href="/disbasura/forgot-password.php">Forgot password?</a></div>
      <button class="login-submit" type="submit">Sign in</button>
    </form>
    <div class="signup-prompt">Don't have an account? <a href="/disbasura/register.php">Sign up</a></div>
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
  const passwordToggle=document.querySelector('[data-toggle-password]');
  passwordToggle.addEventListener('click',()=>{const password=document.getElementById('login-password');const visible=password.type==='password';password.type=visible?'text':'password';passwordToggle.setAttribute('aria-label',visible?'Hide password':'Show password');passwordToggle.setAttribute('aria-pressed',String(visible));});
  loginModal.addEventListener('click',event=>{if(event.target===loginModal)closeLogin();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&loginModal.classList.contains('open'))closeLogin();});
  const query=new URLSearchParams(location.search);
  if(query.has('show_login')||query.has('login_error')||query.has('registered'))openLogin();
</script>
</body>
</html>
