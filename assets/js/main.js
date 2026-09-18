// =============================================
//  main.js — DisBasura App Logic
//  Smart Garbage Collection System
// =============================================

/* ---- Page Routing ---- */
function showAuthPage(id) {
  document.querySelectorAll('.auth-bg').forEach(p => p.style.display = 'none');
  const target = document.getElementById(id);
  if (target) target.style.display = 'flex';
}

/* ---- Login ---- */
function doLogin() {
  const email = document.getElementById('loginEmail').value.trim();
  const pass  = document.getElementById('loginPass').value.trim();
  if (!email || !pass) {
    alert('Please enter your email and password.');
    return;
  }
  document.getElementById('loginPage').style.display = 'none';
  document.getElementById('adminApp').classList.add('active');
  setTimeout(() => showToast('Welcome, Admin!'), 400);
}

/* ---- Admin shortcut ---- */
function doAdminLogin() {
  document.getElementById('loginPage').style.display = 'none';
  document.getElementById('adminApp').classList.add('active');
  setTimeout(() => showToast('Welcome, Admin!'), 400);
}

/* ---- Register ---- */
function doRegister() {
  showAuthPage('loginPage');
  setTimeout(() => showToast('Account created! Please sign in.'), 300);
}

/* ---- Sign Out ---- */
function signOut() {
  document.getElementById('adminApp').classList.remove('active');
  showAuthPage('loginPage');
}

/* ---- Dashboard Nav ---- */
const pageOrder = ['dashboard', 'schedules', 'requests', 'collectors', 'reports', 'notifications'];

function switchPage(name) {
  // Hide all pages
  document.querySelectorAll('.page-content').forEach(p => p.classList.remove('active'));
  // Deactivate all nav items
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));

  // Show target page
  const page = document.getElementById('page-' + name);
  if (page) page.classList.add('active');

  // Activate nav item
  const idx = pageOrder.indexOf(name);
  const navItems = document.querySelectorAll('.nav-item');
  if (idx >= 0 && navItems[idx]) navItems[idx].classList.add('active');
}

/* ---- Filter Tabs ---- */
function filterTab(el) {
  const group = el.closest('.filter-tabs');
  if (!group) return;
  group.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}

/* ---- Modal ---- */
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('open');
}
function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('open');
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
});

/* ---- Save Schedule ---- */
function saveSchedule() {
  closeModal('scheduleModal');
  showToast('Schedule added successfully!');
}

/* ---- Approve / Reject Request ---- */
function approveRequest(btn) {
  const card = btn.closest('.request-card');
  const badge = card.querySelector('.badge');
  badge.className = 'badge approved';
  badge.textContent = 'approved';
  btn.closest('.request-actions').innerHTML = `
    <select class="collector-select">
      <option>Assign collector</option>
      <option>Juan Dela Cruz</option>
      <option>Pedro Santos</option>
    </select>
  `;
  showToast('Request approved!');
}

function rejectRequest(btn) {
  const card = btn.closest('.request-card');
  const badge = card.querySelector('.badge');
  badge.className = 'badge rejected';
  badge.textContent = 'rejected';
  btn.closest('.request-actions').remove();
  showToast('Request rejected.');
}

/* ---- Toast ---- */
let toastTimer = null;
function showToast(msg) {
  const toast = document.getElementById('toast');
  const msgEl  = document.getElementById('toastMsg');
  if (!toast || !msgEl) return;
  msgEl.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}

/* ---- Enter key on login ---- */
document.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    const loginPage = document.getElementById('loginPage');
    if (loginPage && loginPage.style.display !== 'none') {
      doLogin();
    }
  }
});