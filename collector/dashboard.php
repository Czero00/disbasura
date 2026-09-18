<?php
/**
 * collector/dashboard.php
 * Entry point — includes logic, renders HTML.
 * Logic  → collector/dashboard_logic.php
 * Styles → assets/css/collector.css
 * JS     → assets/js/collector.js
 */
require_once __DIR__ . '/dashboard_logic.php';
$isDark = (int)($collector['dark_mode'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en" class="<?= $isDark ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Dashboard — DisBasura Collector</title>
  <link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/>
  <link rel="stylesheet" href="/disbasura/assets/css/collector.css"/>
</head>
<body>

<!-- ── Background hex / circuit SVG ── -->
<svg class="bg-pattern" viewBox="0 0 1200 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
  <polygon points="80,60 140,26 200,60 200,128 140,162 80,128"     fill="none" stroke="rgba(76,175,128,.18)" stroke-width="1.5"/>
  <polygon points="200,128 260,94 320,128 320,196 260,230 200,196"  fill="none" stroke="rgba(76,175,128,.12)" stroke-width="1"/>
  <polygon points="960,30 1040,-14 1120,30 1120,118 1040,162 960,118" fill="none" stroke="rgba(76,175,128,.15)" stroke-width="1.5"/>
  <polygon points="1040,162 1120,118 1200,162 1200,250 1120,294 1040,250" fill="none" stroke="rgba(76,175,128,.1)" stroke-width="1"/>
  <polygon points="20,400 100,356 180,400 180,488 100,532 20,488"   fill="none" stroke="rgba(76,175,128,.1)" stroke-width="1"/>
  <polygon points="880,500 960,456 1040,500 1040,588 960,632 880,588" fill="none" stroke="rgba(76,175,128,.12)" stroke-width="1.5"/>
  <polygon points="500,700 580,656 660,700 660,788 580,832 500,788"  fill="none" stroke="rgba(76,175,128,.08)" stroke-width="1"/>
  <polygon points="100,750 180,706 260,750 260,838 180,882 100,838"  fill="none" stroke="rgba(76,175,128,.1)" stroke-width="1"/>
  <line x1="200" y1="128" x2="960" y2="74"  stroke="rgba(76,175,128,.1)"  stroke-width="1"/>
  <line x1="140" y1="162" x2="140" y2="400" stroke="rgba(76,175,128,.08)" stroke-width="1"/>
  <line x1="960" y1="118" x2="880" y2="500" stroke="rgba(76,175,128,.1)"  stroke-width="1"/>
  <line x1="100" y1="532" x2="500" y2="700" stroke="rgba(76,175,128,.08)" stroke-width="1"/>
  <circle cx="200" cy="128" r="3.5" fill="rgba(76,175,128,.45)"/>
  <circle cx="140" cy="162" r="2.5" fill="rgba(76,175,128,.35)"/>
  <circle cx="960" cy="74"  r="3"   fill="rgba(76,175,128,.4)"/>
  <circle cx="880" cy="500" r="3"   fill="rgba(76,175,128,.35)"/>
</svg>

<!-- ── Navbar ── -->
<nav class="col-navbar">
  <a href="/disbasura/collector/dashboard.php" class="col-nav-brand">
    <svg viewBox="0 0 72 72" fill="none" width="34" height="34">
      <defs>
        <linearGradient id="ringN" x1="0" y1="0" x2="72" y2="72" gradientUnits="userSpaceOnUse">
          <stop offset="0%"   stop-color="#5dd96b"/>
          <stop offset="50%"  stop-color="#22a94a"/>
          <stop offset="100%" stop-color="#0d6e30"/>
        </linearGradient>
        <linearGradient id="leafN" x1="36" y1="20" x2="36" y2="60" gradientUnits="userSpaceOnUse">
          <stop offset="0%"   stop-color="#7de87a"/>
          <stop offset="100%" stop-color="#1a8a38"/>
        </linearGradient>
      </defs>
      <path d="M36 8 A28 28 0 0 1 64 36" stroke="url(#ringN)" stroke-width="6" fill="none" stroke-linecap="round"/>
      <polygon points="64,28 68,38 58,36" fill="#22a94a"/>
      <path d="M36 64 A28 28 0 0 1 8 36"  stroke="url(#ringN)" stroke-width="6" fill="none" stroke-linecap="round"/>
      <polygon points="8,44 4,34 14,36"   fill="#22a94a"/>
      <path d="M64 36 A28 28 0 0 1 36 64" stroke="url(#ringN)" stroke-width="6" fill="none" stroke-linecap="round"/>
      <path d="M8 36 A28 28 0 0 1 36 8"   stroke="url(#ringN)" stroke-width="6" fill="none" stroke-linecap="round"/>
      <path d="M36 58 Q36 44 36 36" stroke="#1a8a38" stroke-width="2.5" stroke-linecap="round"/>
      <path d="M36 44 Q26 38 24 28 Q32 26 36 36 Z" fill="url(#leafN)"/>
      <path d="M36 44 Q46 38 48 28 Q40 26 36 36 Z" fill="url(#leafN)"/>
      <path d="M36 36 Q30 28 31 20 Q38 22 36 32 Z" fill="#5dd96b"/>
    </svg>
    <div>
      <div class="col-nav-brand-text">DisBasura</div>
      <div class="col-nav-brand-sub">Collector Portal</div>
    </div>
  </a>
  <div style="display:flex;align-items:center;gap:.5rem">
    <!-- Notification bell with badge -->
    <a href="/disbasura/collector/dashboard.php#notifications" class="col-signout" style="position:relative;padding:.45rem .7rem" title="Notifications">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 01-3.46 0"/>
      </svg>
      <?php if($notif_unread > 0): ?>
      <span style="position:absolute;top:-4px;right:-4px;background:#e74c3c;color:#fff;border-radius:20px;font-size:.62rem;font-weight:800;padding:.08rem .38rem;min-width:18px;text-align:center;border:2px solid #0a2015;line-height:1.5">
        <?= $notif_unread > 99 ? '99+' : $notif_unread ?>
      </span>
      <?php endif; ?>
    </a>
    <!-- Dark mode toggle -->
    <form method="POST" style="margin:0">
      <input type="hidden" name="toggle_dark" value="1">
      <button type="submit" class="dark-toggle-btn" title="<?= $isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode' ?>">
        <?php if ($isDark): ?>
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
          <span>Light</span>
        <?php else: ?>
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
          <span>Dark</span>
        <?php endif; ?>
      </button>
    </form>
    <!-- Sign out -->
    <a href="/disbasura/logout.php" class="col-signout">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Sign Out
    </a>
  </div>
</nav>

<!-- ── Hero ── -->
<div class="col-hero">
  <h1 class="col-hero-name">
    <a href="/disbasura/collector/profile.php" style="color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:.65rem">
      <?php
        // Show profile photo in hero if set
        $col_photo = $collector['profile_photo'] ?? null;
      ?>
      <?php if ($col_photo): ?>
        <img src="/disbasura/uploads/<?= htmlspecialchars($col_photo) ?>" alt="" style="width:46px;height:46px;border-radius:50%;object-fit:cover;border:2.5px solid rgba(255,255,255,.35);flex-shrink:0"/>
      <?php else: ?>
        <span style="display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);font-size:1.2rem;font-weight:800;flex-shrink:0"><?= strtoupper(substr($collector['full_name'],0,1)) ?></span>
      <?php endif; ?>
      <?= htmlspecialchars($collector['full_name']) ?> | <?= htmlspecialchars($collector['sitio']) ?>
    </a>
  </h1>
  <div class="col-hero-badges">
    <span class="badge-portal">
      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <rect x="1" y="3" width="15" height="13" rx="1"/>
        <path d="M16 8h5l2 3v3h-7V8z"/>
        <circle cx="5.5" cy="18.5" r="2.5"/>
        <circle cx="18.5" cy="18.5" r="2.5"/>
      </svg>
      Collector Portal
    </span>
    <?php $st = $collector['status'] ?? 'available'; ?>
    <?php if ($st === 'sick'): ?>
      <span class="badge-status sick">🤒 Sick</span>
    <?php elseif ($st === 'unavailable'): ?>
      <span class="badge-status off-duty">⛔ Off Duty</span>
    <?php else: ?>
      <span class="badge-status on-duty">✅ On Duty</span>
    <?php endif; ?>
  </div>
</div>

<!-- ── Main shell ── -->
<div class="col-shell">

  <!-- Availability -->
  <div class="c-card avail-card">
    <div class="avail-label">Set Your Availability</div>
    <form method="POST">
      <input type="hidden" name="set_status" value="1">
      <div class="avail-btns">
        <button type="submit" name="status" value="available"
          class="avail-btn btn-avail <?= (!$collector['status'] || $collector['status']==='available') ? 'active' : '' ?>">
          ✅ Available
        </button>
        <button type="submit" name="status" value="sick"
          class="avail-btn btn-sick <?= ($collector['status']==='sick') ? 'active' : '' ?>">
          🤒 Sick
        </button>
        <button type="submit" name="status" value="unavailable"
          class="avail-btn btn-off <?= ($collector['status']==='unavailable') ? 'active' : '' ?>">
          ⛔ Off Duty
        </button>
      </div>
    </form>
  </div>

  <!-- GPS -->
  <div class="gps-card">
    <div class="gps-truck-icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="1" y="3" width="15" height="13" rx="1"/>
        <path d="M16 8h5l2 3v3h-7V8z"/>
        <circle cx="5.5" cy="18.5" r="2.5"/>
        <circle cx="18.5" cy="18.5" r="2.5"/>
      </svg>
    </div>
    <div class="gps-info">
      <div class="gps-title">
        <span class="gps-dot off" id="gpsDot"></span>
        Live GPS Tracking
      </div>
      <p class="gps-desc" id="gpsStatus">Start tracking so the admin can see your truck location in real time.</p>
    </div>
    <button class="gps-toggle" id="gpsBtn" onclick="toggleGPS()">Start Tracking</button>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-c">
      <div class="stat-c-icon blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <rect x="3" y="4" width="18" height="18" rx="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8"  y1="2" x2="8"  y2="6"/>
          <line x1="3"  y1="10" x2="21" y2="10"/>
        </svg>
      </div>
      <span class="stat-c-val"><?= $total ?></span>
      <span class="stat-c-label">Schedules</span>
    </div>
    <div class="stat-c">
      <div class="stat-c-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <span class="stat-c-val" style="color:var(--green-main)"><?= $completed ?></span>
      <span class="stat-c-label">Completed</span>
    </div>
    <div class="stat-c">
      <div class="stat-c-icon orange">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
        </svg>
      </div>
      <span class="stat-c-val" style="color:var(--orange)"><?= count($active_requests) ?></span>
      <span class="stat-c-label">Requests</span>
    </div>
  </div>

  <!-- My Schedules -->
  <div class="panel-card">
    <div class="panel-card-header">
      <span class="panel-card-title">My Schedules</span>
      <span class="panel-card-count"><?= $total ?> total</span>
    </div>
    <?php if ($schedules): foreach ($schedules as $s): ?>
    <div class="item-row">
      <div class="row-left">
        <?php if (in_array($s['status'], ['scheduled','assigned','pending','unverified'])): ?>
          <button class="btn-complete" onclick="openCompleteModal(<?= $s['id'] ?>,'<?= htmlspecialchars($s['sitio'],ENT_QUOTES) ?>')">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            Complete
          </button>
        <?php elseif ($s['status'] === 'completed'): ?>
          <span class="done-pill">✅ Done</span>
        <?php elseif ($s['status'] === 'disputed'): ?>
          <span class="disputed-pill">⚠️ Disputed</span>
        <?php endif; ?>
        <?php if ($s['proof_photo']): ?>
          <button class="proof-btn" onclick="viewProof('/disbasura/uploads/<?= htmlspecialchars($s['proof_photo']) ?>','<?= htmlspecialchars($s['sitio'],ENT_QUOTES) ?>','<?= fmt_date($s['completed_at']) ?>')">
            📷 My Proof
          </button>
        <?php endif; ?>
      </div>
      <div class="row-info">
        <strong><?= htmlspecialchars($s['sitio']) ?></strong>
        <div class="row-meta">
          <span>📅 <?= fmt_date($s['scheduled_at']) ?></span>
          <span>🗑️ <?= htmlspecialchars($s['waste_type']) ?></span>
        </div>
        <?php if ($s['completed_at']): ?>
          <div class="row-completed-note">✅ Completed <?= fmt_date($s['completed_at']) ?></div>
        <?php endif; ?>
        <?php if ($s['status'] === 'disputed'): ?>
          <div class="row-disputed-note">⚠️ Disputed by resident — awaiting admin review</div>
        <?php endif; ?>
        <div style="margin-top:.35rem"><span class="badge <?= htmlspecialchars($s['status']) ?>"><?= htmlspecialchars($s['status']) ?></span></div>
      </div>
    </div>
    <?php endforeach; else: ?>
      <div class="empty-state" style="padding:3rem"><p>No schedules assigned yet.</p></div>
    <?php endif; ?>
  </div>

  <!-- Pickup Requests -->
  <div class="panel-card">
    <div class="panel-card-header">
      <span class="panel-card-title">📋 Pickup Requests</span>
      <span class="panel-card-count"><?= count($active_requests) ?> active · <?= count($done_requests) ?> done</span>
    </div>

    <?php if ($active_requests): foreach ($active_requests as $r): ?>
    <div class="item-row req-active">
      <div class="row-left">
        <button class="btn-complete" onclick="openReqModal(<?= $r['id'] ?>,'<?= htmlspecialchars($r['resident_name'],ENT_QUOTES) ?>','<?= htmlspecialchars($r['sitio'],ENT_QUOTES) ?>')">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Complete
        </button>
        <span class="badge assigned" style="font-size:.7rem"><?= htmlspecialchars($r['status']) ?></span>
      </div>
      <div class="row-info">
        <strong><?= htmlspecialchars($r['resident_name']) ?></strong>
        <div class="row-meta">
          <span>📍 <?= htmlspecialchars($r['sitio']) ?> · 🗑️ <?= htmlspecialchars($r['waste_type']) ?></span>
          <span>📅 Preferred: <?= fmt_date($r['preferred_date']) ?></span>
          <?php if ($r['note']): ?><span>💬 <?= htmlspecialchars($r['note']) ?></span><?php endif; ?>
          <?php if (!empty($r['location'])): ?><span>🏠 <?= htmlspecialchars($r['location']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>

    <?php if ($done_requests): ?>
      <div class="done-section-label">Completed Requests</div>
      <?php foreach ($done_requests as $r): ?>
      <div class="item-row row-done">
        <div class="row-left">
          <span class="done-pill">✅ Done</span>
          <?php if (!empty($r['proof_photo'])): ?>
            <button class="proof-btn" onclick="viewReqProof('/disbasura/uploads/<?= htmlspecialchars($r['proof_photo']) ?>','<?= htmlspecialchars($r['resident_name'],ENT_QUOTES) ?>','<?= fmt_date($r['completed_at'] ?? $r['created_at']) ?>')">
              📷 Proof
            </button>
          <?php endif; ?>
        </div>
        <div class="row-info">
          <strong><?= htmlspecialchars($r['resident_name']) ?></strong>
          <div class="row-meta">
            <span>📍 <?= htmlspecialchars($r['sitio']) ?> · 🗑️ <?= htmlspecialchars($r['waste_type']) ?></span>
          </div>
          <?php if (!empty($r['completed_at'])): ?>
            <div class="row-completed-note">✅ Completed <?= fmt_date($r['completed_at']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$active_requests && !$done_requests): ?>
      <div class="empty-state" style="padding:2.5rem;text-align:center">
        <div style="font-size:2rem;margin-bottom:.5rem">📭</div>
        <p style="color:var(--text-light);font-size:.85rem">No pickup requests assigned to you yet.</p>
      </div>
    <?php endif; ?>
  </div>

</div><!-- /.col-shell -->

<!-- ── Modals ── -->

<!-- Complete Schedule Modal -->
<div class="upload-modal" id="completeModal">
  <div class="upload-box">
    <div style="text-align:center;margin-bottom:1.25rem">
      <div style="width:56px;height:56px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;font-size:1.6rem">✅</div>
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800">Mark Collection as Complete</h3>
      <p id="completeModalSub" style="font-size:.82rem;color:var(--text-light);margin-top:.25rem"></p>
    </div>
    <p style="font-size:.8rem;color:var(--text-mid);background:#f5faf7;border-radius:8px;padding:.65rem .85rem;margin-bottom:.9rem;line-height:1.5">
      📸 Take a photo of the collected garbage area as proof. Residents and admins will be notified.
    </p>
    <form method="POST" id="completeForm" enctype="multipart/form-data">
      <input type="hidden" name="complete_schedule" value="1">
      <input type="hidden" name="schedule_id" id="completeSchedId">
      <div class="photo-drop" id="photoDropZone" onclick="document.getElementById('proofInput').click()">
        <div id="photoDropContent">
          <svg width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="display:block;margin:0 auto .5rem;opacity:.35"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          <p style="font-size:.83rem;color:var(--text-light);font-weight:600">Tap to take or upload a photo</p>
          <p style="font-size:.74rem;color:var(--text-light);margin-top:.2rem">JPG, PNG, WEBP accepted</p>
        </div>
        <input type="file" name="proof_photo" id="proofInput" accept="image/*" capture="environment" required onchange="previewPhoto(this)" style="display:none">
      </div>
      <img id="photoPreview" style="display:none;width:100%;border-radius:10px;max-height:200px;object-fit:cover;margin-bottom:.75rem;border:1.5px solid var(--border-light)" src="" alt="Preview"/>
      <div style="display:flex;gap:.65rem;margin-top:.5rem">
        <button type="button" onclick="closeCompleteModal()" style="flex:1;padding:.78rem;border:1.5px solid var(--border);border-radius:10px;background:#fff;cursor:pointer;font-family:inherit;font-size:.88rem;color:var(--text-mid);font-weight:600">Cancel</button>
        <button type="submit" id="submitProofBtn" style="flex:2;padding:.78rem;background:var(--green-main);color:#fff;border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Submit &amp; Notify
        </button>
      </div>
    </form>
  </div>
</div>

<!-- View Schedule Proof Modal -->
<div id="proofViewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);z-index:500;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;overflow:hidden;max-width:500px;width:100%;box-shadow:0 30px 80px rgba(0,0,0,.4)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border-light)">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:.95rem;font-weight:700">📸 Proof of Collection</h3>
      <button onclick="closeProofView()" style="width:32px;height:32px;border-radius:50%;border:1.5px solid var(--border);background:#fff;cursor:pointer;font-size:1rem">✕</button>
    </div>
    <div style="padding:1.25rem">
      <img id="proofViewImg" src="" style="width:100%;border-radius:12px;max-height:360px;object-fit:cover;display:block;border:1px solid var(--border-light)"/>
      <div style="margin-top:.75rem">
        <div style="font-size:.82rem;color:var(--text-dark);font-weight:600" id="proofViewSitio"></div>
        <div style="font-size:.75rem;color:var(--text-light)" id="proofViewTime"></div>
      </div>
    </div>
  </div>
</div>

<!-- Complete Request Modal -->
<div class="upload-modal" id="reqCompleteModal">
  <div class="upload-box">
    <div style="text-align:center;margin-bottom:1.1rem">
      <div style="width:56px;height:56px;border-radius:50%;background:#fff8e6;display:flex;align-items:center;justify-content:center;margin:0 auto .65rem;font-size:1.7rem">🚛</div>
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800">Complete Pickup Request</h3>
      <p id="reqModalSub" style="font-size:.82rem;color:var(--text-light);margin-top:.2rem"></p>
    </div>
    <p style="font-size:.8rem;color:var(--text-mid);background:#f5faf7;border-radius:8px;padding:.65rem .85rem;margin-bottom:.9rem;line-height:1.5">
      📸 Take a photo as proof of completion. The resident and admin will be notified immediately.
    </p>
    <form method="POST" id="reqCompleteForm" enctype="multipart/form-data">
      <input type="hidden" name="complete_request" value="1">
      <input type="hidden" name="request_id" id="reqModalId">
      <div class="photo-drop" id="reqDropZone" onclick="document.getElementById('reqProofInput').click()">
        <div id="reqDropContent">
          <svg width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="display:block;margin:0 auto .5rem;opacity:.35"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          <p style="font-size:.83rem;color:var(--text-light);font-weight:600">Tap to take or upload a photo</p>
          <p style="font-size:.74rem;color:var(--text-light);margin-top:.2rem">JPG, PNG, WEBP accepted</p>
        </div>
        <input type="file" name="req_proof_photo" id="reqProofInput" accept="image/*" capture="environment" required onchange="previewReqPhoto(this)" style="display:none">
      </div>
      <img id="reqPhotoPreview" style="display:none;width:100%;border-radius:10px;max-height:200px;object-fit:cover;margin-bottom:.75rem;border:1.5px solid var(--border-light)" src="" alt="Preview"/>
      <div style="display:flex;gap:.65rem;margin-top:.5rem">
        <button type="button" onclick="closeReqModal()" style="flex:1;padding:.78rem;border:1.5px solid var(--border);border-radius:10px;background:#fff;cursor:pointer;font-family:inherit;font-size:.88rem;color:var(--text-mid);font-weight:600">Cancel</button>
        <button type="submit" id="reqSubmitBtn" style="flex:2;padding:.78rem;background:var(--green-main);color:#fff;border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Submit &amp; Notify
        </button>
      </div>
    </form>
  </div>
</div>

<!-- View Request Proof Modal -->
<div id="reqProofViewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);z-index:500;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;overflow:hidden;max-width:500px;width:100%;box-shadow:0 30px 80px rgba(0,0,0,.4)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border-light)">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:.95rem;font-weight:700">📸 Pickup Proof</h3>
      <button onclick="document.getElementById('reqProofViewModal').style.display='none'" style="width:32px;height:32px;border-radius:50%;border:1.5px solid var(--border);background:#fff;cursor:pointer;font-size:1rem">✕</button>
    </div>
    <div style="padding:1.25rem">
      <img id="reqProofViewImg" src="" style="width:100%;border-radius:12px;max-height:360px;object-fit:cover;display:block;border:1px solid var(--border-light)"/>
      <div style="margin-top:.75rem">
        <div style="font-size:.82rem;color:var(--text-dark);font-weight:600" id="reqProofViewName"></div>
        <div style="font-size:.75rem;color:var(--text-light)" id="reqProofViewTime"></div>
      </div>
    </div>
  </div>
</div>

<!-- ── Scripts ── -->
<script src="/disbasura/assets/js/collector.js"></script>
</body>
</html>
