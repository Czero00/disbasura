<?php
require_once __DIR__ . '/config/session.php';

// Auto-redirect if already logged in
if (isset($_SESSION['admin_id']) && $_SESSION['admin_role'] === 'admin') {
    header('Location: /disbasura/admin/dashboard.php'); exit;
}
if (isset($_SESSION['collector_id'])) {
    header('Location: /disbasura/collector/dashboard.php'); exit;
}
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'leader') { header('Location: /disbasura/leader/dashboard.php'); exit; }
    header('Location: /disbasura/resident/dashboard.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>DisBasura — Smart Garbage Collection System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --green:      #2d8653;
      --green-dark: #1a4a2e;
      --green-pale: #e8f5ee;
      --orange:     #f5a623;
      --blue:       #3b82f6;
      --red:        #e05252;
      --text-dark:  #1a2e22;
      --text-mid:   #4a6358;
      --text-light: #8baa96;
      --border:     #d4e6db;
      --bg:         #f0f6f3;
    }
    html, body { min-height: 100vh; font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-dark); }

    /* ── Hero ── */
    .hero {
      background: linear-gradient(135deg, #0a1f13 0%, #0f2d1e 40%, #1a4a2e 70%, #2d8653 100%);
      padding: 4rem 2rem 6rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      width: 600px; height: 600px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(76,175,128,.12) 0%, transparent 70%);
      top: -200px; right: -100px; pointer-events: none;
    }
    .hero::after {
      content: '';
      position: absolute;
      width: 400px; height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(45,134,83,.1) 0%, transparent 70%);
      bottom: -100px; left: -80px; pointer-events: none;
    }
    .hero-logo {
      display: inline-flex;
      align-items: center;
      gap: .85rem;
      margin-bottom: 2rem;
      position: relative; z-index: 1;
    }
    .hero-logo-icon {
      width: 60px; height: 60px;
      border-radius: 16px;
      background: rgba(255,255,255,.1);
      border: 1.5px solid rgba(255,255,255,.15);
      display: flex; align-items: center; justify-content: center;
    }
    .hero-logo h1 {
      font-size: 1.75rem; font-weight: 800; color: #fff;
      letter-spacing: -.02em;
    }
    .hero-logo span {
      display: block; font-size: .78rem; color: rgba(255,255,255,.5); font-weight: 500;
    }
    .hero-title {
      font-size: clamp(1.8rem, 4vw, 2.8rem);
      font-weight: 800; color: #fff;
      line-height: 1.15; margin-bottom: 1rem;
      position: relative; z-index: 1;
    }
    .hero-title span { color: #7ee8b0; }
    .hero-sub {
      font-size: 1rem; color: rgba(255,255,255,.55);
      max-width: 520px; margin: 0 auto 2rem;
      line-height: 1.65; position: relative; z-index: 1;
    }
    .hero-badge {
      display: inline-flex; align-items: center; gap: .5rem;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 20px;
      padding: .4rem 1.1rem;
      font-size: .78rem; color: rgba(255,255,255,.6);
      position: relative; z-index: 1;
    }
    .hero-badge strong { color: #7ee8b0; }

    /* ── Cards ── */
    .cards-section {
      max-width: 1060px;
      margin: -3rem auto 0;
      padding: 0 1.5rem 4rem;
      position: relative; z-index: 2;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.25rem;
    }
    @media(max-width: 768px) {
      .cards-section { grid-template-columns: 1fr; margin-top: -1.5rem; }
    }
    .panel-card {
      background: #fff;
      border-radius: 20px;
      border: 1px solid var(--border);
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
      overflow: hidden;
      transition: transform .22s, box-shadow .22s;
    }
    .panel-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 48px rgba(0,0,0,.13);
    }
    .card-top {
      padding: 2rem 1.75rem 1.5rem;
      text-align: center;
    }
    .card-icon {
      width: 72px; height: 72px;
      border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.25rem;
    }
    .card-icon svg { width: 34px; height: 34px; }
    .card-icon.green  { background: #e8f5ee; color: var(--green); }
    .card-icon.orange { background: #fff3e0; color: var(--orange); }
    .card-icon.blue   { background: #eff6ff; color: var(--blue); }
    .card-title {
      font-size: 1.2rem; font-weight: 800;
      color: var(--text-dark); margin-bottom: .4rem;
    }
    .card-desc {
      font-size: .83rem; color: var(--text-mid);
      line-height: 1.6;
    }
    .card-features {
      padding: 1.25rem 1.75rem;
      border-top: 1px solid #f0f6f3;
      background: #fafcfb;
    }
    .feature-item {
      display: flex; align-items: center; gap: .6rem;
      font-size: .8rem; color: var(--text-mid);
      padding: .3rem 0;
    }
    .feature-dot {
      width: 6px; height: 6px; border-radius: 50%;
      flex-shrink: 0;
    }
    .dot-green  { background: var(--green); }
    .dot-orange { background: var(--orange); }
    .dot-blue   { background: var(--blue); }
    .card-btn {
      display: block;
      margin: 1.25rem 1.75rem 1.75rem;
      padding: .85rem;
      border: none; border-radius: 10px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: .9rem; font-weight: 700;
      cursor: pointer; text-decoration: none;
      text-align: center;
      transition: all .18s;
    }
    .btn-green  { background: var(--green);  color: #fff; }
    .btn-green:hover  { background: #1e6b3f; }
    .btn-orange { background: var(--orange); color: #fff; }
    .btn-orange:hover { background: #d4881a; }
    .btn-blue   { background: var(--blue);   color: #fff; }
    .btn-blue:hover   { background: #2563eb; }

    /* ── How it works ── */
    .how-section {
      max-width: 860px; margin: 0 auto;
      padding: 0 1.5rem 4rem;
    }
    .section-title {
      font-size: 1.35rem; font-weight: 800;
      text-align: center; margin-bottom: 2rem;
      color: var(--text-dark);
    }
    .steps-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1rem;
    }
    .step-card {
      background: #fff;
      border-radius: 16px;
      border: 1px solid var(--border);
      padding: 1.5rem;
      text-align: center;
    }
    .step-num {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: var(--green-pale);
      color: var(--green);
      font-size: 1rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto .85rem;
    }
    .step-card h4 { font-size: .92rem; font-weight: 700; margin-bottom: .35rem; }
    .step-card p  { font-size: .78rem; color: var(--text-mid); line-height: 1.55; }

    /* ── Footer ── */
    .footer {
      background: var(--green-dark);
      padding: 2rem;
      text-align: center;
      color: rgba(255,255,255,.45);
      font-size: .78rem;
      line-height: 1.8;
    }
    .footer strong { color: rgba(255,255,255,.7); }

    /* ── Pulse dot ── */
    @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.5)} }
    .live-dot {
      display: inline-block; width: 8px; height: 8px;
      border-radius: 50%; background: #7ee8b0;
      animation: pulse 1.8s infinite;
      vertical-align: middle; margin-right: 4px;
    }
  </style>
</head>
<body>

<!-- Hero -->
<div class="hero">
  <div class="hero-logo">
    <div class="hero-logo-icon">
      <svg viewBox="0 0 260 220" fill="none" width="34" height="30">
        <rect x="120" y="62" width="20" height="10" rx="4" fill="none" stroke="#7ee8b0" stroke-width="4"/>
        <rect x="98"  y="72" width="64" height="10" rx="4" fill="none" stroke="#7ee8b0" stroke-width="4"/>
        <rect x="104" y="84" width="52" height="52" rx="4" fill="none" stroke="#7ee8b0" stroke-width="4"/>
        <line x1="116" y1="92" x2="116" y2="128" stroke="#7ee8b0" stroke-width="3" stroke-linecap="round"/>
        <line x1="126" y1="92" x2="126" y2="128" stroke="#7ee8b0" stroke-width="3" stroke-linecap="round"/>
        <line x1="136" y1="92" x2="136" y2="128" stroke="#7ee8b0" stroke-width="3" stroke-linecap="round"/>
        <line x1="146" y1="92" x2="146" y2="128" stroke="#7ee8b0" stroke-width="3" stroke-linecap="round"/>
      </svg>
    </div>
    <div>
      <h1>DisBasura</h1>
      <span>Smart Garbage Collection System</span>
    </div>
  </div>
  <h2 class="hero-title">One System,<br><span>Three Separate Panels</span></h2>
  <p class="hero-sub">A barangay-level garbage collection management system with separate portals for Admin, Residents, and Collectors — all running from one device.</p>
  <div class="hero-badge"><span class="live-dot"></span> <strong>UC</strong> &nbsp;·&nbsp; DisBasura Capstone Project &nbsp;·&nbsp; 2026</div>
</div>

<!-- Panel Cards -->
<div class="cards-section">

  <!-- Admin -->
  <div class="panel-card">
    <div class="card-top">
      <div class="card-icon green">
        <svg fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path d="M12 2L2 7l10 5 10-5-10-5z"/>
          <path d="M2 17l10 5 10-5"/>
          <path d="M2 12l10 5 10-5"/>
        </svg>
      </div>
      <div class="card-title">Admin Panel</div>
      <p class="card-desc">For the Barangay Captain. Full control over the entire garbage collection system.</p>
    </div>
    <div class="card-features">
      <div class="feature-item"><div class="feature-dot dot-green"></div>Manage sitios, schedules & collectors</div>
      <div class="feature-item"><div class="feature-dot dot-green"></div>Approve or reject pickup requests</div>
      <div class="feature-item"><div class="feature-dot dot-green"></div>Live GPS truck tracker</div>
      <div class="feature-item"><div class="feature-dot dot-green"></div>Review and resolve disputes</div>
      <div class="feature-item"><div class="feature-dot dot-green"></div>System reports & notifications</div>
    </div>
    <a href="/disbasura/admin/login.php" class="card-btn btn-green">Open Admin Panel →</a>
  </div>

  <!-- Resident -->
  <div class="panel-card">
    <div class="card-top">
      <div class="card-icon orange">
        <svg fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87"/>
          <path d="M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </div>
      <div class="card-title">Resident Panel</div>
      <p class="card-desc">For Sitio Leaders and Residents. Monitor schedules and submit pickup requests.</p>
    </div>
    <div class="card-features">
      <div class="feature-item"><div class="feature-dot dot-orange"></div>View weekly collection schedule</div>
      <div class="feature-item"><div class="feature-dot dot-orange"></div>Submit special pickup requests</div>
      <div class="feature-item"><div class="feature-dot dot-orange"></div>File disputes on missed collections</div>
      <div class="feature-item"><div class="feature-dot dot-orange"></div>Real-time notifications</div>
      <div class="feature-item"><div class="feature-dot dot-orange"></div>Leader can submit for residents</div>
    </div>
    <a href="/disbasura/login.php" class="card-btn btn-orange">Open Resident Panel →</a>
  </div>

  <!-- Collector -->
  <div class="panel-card">
    <div class="card-top">
      <div class="card-icon blue">
        <svg fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <rect x="1" y="3" width="15" height="13"/>
          <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
          <circle cx="5.5" cy="18.5" r="2.5"/>
          <circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
      </div>
      <div class="card-title">Collector Panel</div>
      <p class="card-desc">For garbage truck collectors. View assigned schedules and share live GPS location.</p>
    </div>
    <div class="card-features">
      <div class="feature-item"><div class="feature-dot dot-blue"></div>View assigned schedules & requests</div>
      <div class="feature-item"><div class="feature-dot dot-blue"></div>Mark collection as completed</div>
      <div class="feature-item"><div class="feature-dot dot-blue"></div>Upload photo proof of collection</div>
      <div class="feature-item"><div class="feature-dot dot-blue"></div>Live GPS sharing to admin tracker</div>
      <div class="feature-item"><div class="feature-dot dot-blue"></div>Set availability status</div>
    </div>
    <a href="/disbasura/collector/login.php" class="card-btn btn-blue">Open Collector Panel →</a>
  </div>

</div>

<!-- How it works -->
<div class="how-section">
  <h3 class="section-title">How to Use This System</h3>
  <div class="steps-grid">
    <div class="step-card">
      <div class="step-num">1</div>
      <h4>Admin Sets Up</h4>
      <p>Admin creates sitios, adds collectors with login accounts, and sets up weekly schedules.</p>
    </div>
    <div class="step-card">
      <div class="step-num">2</div>
      <h4>Residents Register</h4>
      <p>Residents create their account, select their sitio, and can view schedules and submit pickup requests.</p>
    </div>
    <div class="step-card">
      <div class="step-num">3</div>
      <h4>Collector Goes Live</h4>
      <p>Collector logs in, starts GPS tracking, and marks collections as done with photo proof.</p>
    </div>
    <div class="step-card">
      <div class="step-num">4</div>
      <h4>Admin Monitors</h4>
      <p>Admin sees everything in real time — live truck location, completed collections, disputes, and reports.</p>
    </div>
  </div>
</div>

<!-- Footer -->
<div class="footer">
  <strong>DisBasura</strong> — Smart Sitio-Based Garbage Collection System<br>
  Developed by <strong>DisBasura Capstone Project</strong> &nbsp;·&nbsp; <strong>UC</strong> &nbsp;·&nbsp; 2026<br>
  <span style="font-size:.72rem;opacity:.6">Running on localhost · All panels share one database · Separate sessions per role</span>
</div>

</body>
</html>
