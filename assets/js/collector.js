/**
 * collector.js — Collector Dashboard JavaScript
 * DisBasura — Waste Management System
 */

/* ── Complete Schedule Modal ─────────────────────────────── */
function openCompleteModal(sid, sitio) {
  document.getElementById('completeSchedId').value = sid;
  document.getElementById('completeModalSub').textContent = '📍 ' + sitio;
  document.getElementById('photoPreview').style.display = 'none';
  document.getElementById('photoDropContent').style.display = 'block';
  document.getElementById('proofInput').value = '';
  var btn = document.getElementById('submitProofBtn');
  btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Submit &amp; Notify';
  btn.disabled = false;
  document.getElementById('completeModal').classList.add('open');
}

function closeCompleteModal() {
  document.getElementById('completeModal').classList.remove('open');
}

function previewPhoto(input) {
  var p  = document.getElementById('photoPreview');
  var dc = document.getElementById('photoDropContent');
  if (input.files && input.files[0]) {
    p.src = URL.createObjectURL(input.files[0]);
    p.style.display = 'block';
    dc.style.display = 'none';
  }
}

document.getElementById('completeForm').addEventListener('submit', function () {
  var btn = document.getElementById('submitProofBtn');
  btn.innerHTML = '⏳ Uploading…';
  btn.disabled = true;
});

document.getElementById('completeModal').addEventListener('click', function (e) {
  if (e.target === this) closeCompleteModal();
});

/* ── View Schedule Proof Modal ───────────────────────────── */
function viewProof(url, sitio, time) {
  document.getElementById('proofViewImg').src = url;
  document.getElementById('proofViewSitio').textContent = '📍 ' + sitio;
  document.getElementById('proofViewTime').textContent  = '✅ Completed: ' + time;
  document.getElementById('proofViewModal').style.display = 'flex';
}

function closeProofView() {
  document.getElementById('proofViewModal').style.display = 'none';
}

document.getElementById('proofViewModal').addEventListener('click', function (e) {
  if (e.target === this) closeProofView();
});

/* ── Complete Request Modal ──────────────────────────────── */
function openReqModal(rid, name, sitio) {
  document.getElementById('reqModalId').value = rid;
  document.getElementById('reqModalSub').textContent = '👤 ' + name + ' · 📍 ' + sitio;
  document.getElementById('reqPhotoPreview').style.display = 'none';
  document.getElementById('reqDropZone').style.display = 'block';
  document.getElementById('reqDropContent').style.display = 'block';
  document.getElementById('reqProofInput').value = '';
  var btn = document.getElementById('reqSubmitBtn');
  btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Submit &amp; Notify';
  btn.disabled = false;
  document.getElementById('reqCompleteModal').classList.add('open');
}

function closeReqModal() {
  document.getElementById('reqCompleteModal').classList.remove('open');
}

function previewReqPhoto(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function (e) {
      var img = document.getElementById('reqPhotoPreview');
      img.src = e.target.result;
      img.style.display = 'block';
      document.getElementById('reqDropContent').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

document.getElementById('reqCompleteForm').addEventListener('submit', function () {
  var btn = document.getElementById('reqSubmitBtn');
  btn.innerHTML = '⏳ Uploading…';
  btn.disabled = true;
});

document.getElementById('reqCompleteModal').addEventListener('click', function (e) {
  if (e.target === this) closeReqModal();
});

/* ── View Request Proof Modal ────────────────────────────── */
function viewReqProof(url, name, time) {
  document.getElementById('reqProofViewImg').src = url;
  document.getElementById('reqProofViewName').textContent = '👤 ' + name;
  document.getElementById('reqProofViewTime').textContent = '✅ Completed: ' + time;
  document.getElementById('reqProofViewModal').style.display = 'flex';
}

document.getElementById('reqProofViewModal').addEventListener('click', function (e) {
  if (e.target === this) this.style.display = 'none';
});

/* ── GPS Tracking ────────────────────────────────────────── */
var gpsInterval = null;
var tracking    = false;

function toggleGPS() {
  tracking ? stopGPS() : startGPS();
}

function startGPS() {
  if (!navigator.geolocation) {
    document.getElementById('gpsStatus').textContent = 'GPS not supported on this device.';
    return;
  }
  tracking = true;
  document.getElementById('gpsBtn').textContent = 'Stop Tracking';
  document.getElementById('gpsBtn').classList.add('active');
  document.getElementById('gpsDot').classList.remove('off');
  sendLocation();
  gpsInterval = setInterval(sendLocation, 15000);
}

function stopGPS() {
  tracking = false;
  clearInterval(gpsInterval);
  document.getElementById('gpsBtn').textContent = 'Start Tracking';
  document.getElementById('gpsBtn').classList.remove('active');
  document.getElementById('gpsDot').classList.add('off');
  document.getElementById('gpsStatus').textContent = 'Tracking stopped.';
}

function sendLocation() {
  navigator.geolocation.getCurrentPosition(
    function (pos) {
      var lat = pos.coords.latitude;
      var lng = pos.coords.longitude;
      fetch('/disbasura/api/collector-gps.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ lat: lat, lng: lng })
      });
      document.getElementById('gpsStatus').textContent =
        '📍 ' + lat.toFixed(5) + ', ' + lng.toFixed(5) +
        ' · ' + new Date().toLocaleTimeString();
    },
    function () {
      document.getElementById('gpsStatus').textContent =
        'Could not get location — check GPS permissions.';
    }
  );
}
