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
var gpsWatchId = null;
var tracking = false;
var gpsOutOfArea = false;

// Broad Cebu City service-area guard. It prevents clearly wrong readings
// (such as Leyte) from being published; the GPS coordinates remain device-based.
function isInsideCebuCityArea(lat, lng) {
  return lat >= 10.15 && lat <= 10.52 && lng >= 123.70 && lng <= 124.12;
}

function toggleGPS() {
  tracking ? stopGPS() : startGPS();
}

function startGPS() {
  if (!navigator.geolocation) {
    document.getElementById('gpsStatus').textContent = 'GPS not supported on this device.';
    return;
  }
  tracking = true;
  gpsOutOfArea = false;
  document.getElementById('gpsBtn').textContent = 'Stop Tracking';
  document.getElementById('gpsBtn').classList.add('active');
  document.getElementById('gpsDot').classList.remove('off');
  document.getElementById('gpsStatus').textContent = 'Waiting for an accurate GPS fix…';
  clearSharedLocation().then(function () {
    if (!tracking) return;
    gpsWatchId = navigator.geolocation.watchPosition(updateLocation, showLocationError, {
      enableHighAccuracy: true,
      maximumAge: 0,
      timeout: 30000
    });
  }).catch(function () {
    document.getElementById('gpsStatus').textContent = 'Could not connect to the tracker. Refresh and sign in again.';
    resetGPSButton();
  });
}

function stopGPS() {
  tracking = false;
  if (gpsWatchId !== null) navigator.geolocation.clearWatch(gpsWatchId);
  gpsWatchId = null;
  clearSharedLocation();
  resetGPSButton();
  document.getElementById('gpsStatus').textContent = 'Tracking stopped.';
}

function resetGPSButton() {
  tracking = false;
  if (gpsWatchId !== null && navigator.geolocation) navigator.geolocation.clearWatch(gpsWatchId);
  gpsWatchId = null;
  document.getElementById('gpsBtn').textContent = 'Start Tracking';
  document.getElementById('gpsBtn').classList.remove('active');
  document.getElementById('gpsDot').classList.add('off');
}

function clearSharedLocation() {
  return fetch('/disbasura/api/collector-gps.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ active: false }),
    keepalive: true
  }).then(function (response) {
    if (!response.ok) throw new Error('Location service unavailable.');
  });
}

function updateLocation(pos) {
  if (!tracking) return;
  var lat = pos.coords.latitude;
  var lng = pos.coords.longitude;
  var accuracy = pos.coords.accuracy;
  if (accuracy > 100) {
    var accuracyText = accuracy >= 1000
      ? (accuracy / 1000).toFixed(1) + ' km'
      : Math.round(accuracy) + ' m';
    document.getElementById('gpsStatus').textContent =
      accuracy >= 1000
        ? 'Your computer/browser only reports an approximate location (±' + accuracyText + '). Turn on Windows Location services, allow location for localhost, then restart tracking. No truck is shown until the location is precise.'
        : 'GPS accuracy is about ±' + accuracyText + '. Waiting for a clearer GPS fix (100 m or better).';
    return;
  }
  if (!isInsideCebuCityArea(lat, lng)) {
    if (!gpsOutOfArea) clearSharedLocation();
    gpsOutOfArea = true;
    document.getElementById('gpsStatus').textContent =
      'Your device reports ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ' outside the Cebu City service area. Location not shared.';
    return;
  }
  gpsOutOfArea = false;
  fetch('/disbasura/api/collector-gps.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ lat: lat, lng: lng, accuracy: accuracy })
  }).then(function (response) {
    if (response.ok) return;
    return response.json().catch(function () { return {}; }).then(function (body) {
      throw new Error(body.error || 'Location update failed (' + response.status + ').');
    });
  }).then(function () {
    document.getElementById('gpsStatus').textContent =
      '📍 ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + ' · accuracy ±' + Math.round(accuracy) + ' m · Updated ' + new Date().toLocaleTimeString();
  }).catch(function (error) {
    document.getElementById('gpsStatus').textContent = error.message || 'Could not share your location.';
  });
}

function showLocationError(error) {
  var message = error && error.code === error.PERMISSION_DENIED
    ? 'Location permission is blocked. Allow location access in your browser.'
    : 'Could not get GPS location. Check device GPS and browser location permissions.';
  document.getElementById('gpsStatus').textContent = message;
}

window.addEventListener('pagehide', function () {
  if (!tracking) return;
  var body = new Blob([JSON.stringify({ active: false })], { type: 'application/json' });
  navigator.sendBeacon('/disbasura/api/collector-gps.php', body);
});
