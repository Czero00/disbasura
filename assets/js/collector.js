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
var gpsQueueDb = null;
var lastQueuedAt = 0;
var gpsQueueFlushBusy = false;
var gpsCard = document.getElementById('gpsCard');
var GPS_QUEUE_DB = 'disbasura-gps-queue-' + (gpsCard ? gpsCard.getAttribute('data-collector-id') : 'unknown');
var GPS_QUEUE_STORE = 'points';
var GPS_QUEUE_INTERVAL_MS = 30000;
var GPS_QUEUE_MAX_POINTS = 2880;

function openGpsQueue() {
  if (!('indexedDB' in window)) return Promise.reject(new Error('Offline GPS storage is unavailable in this browser.'));
  if (gpsQueueDb) return Promise.resolve(gpsQueueDb);
  return new Promise(function (resolve, reject) {
    var request = indexedDB.open(GPS_QUEUE_DB, 1);
    request.onupgradeneeded = function () {
      var db = request.result;
      if (!db.objectStoreNames.contains(GPS_QUEUE_STORE)) db.createObjectStore(GPS_QUEUE_STORE, { keyPath: 'id' });
    };
    request.onsuccess = function () { gpsQueueDb = request.result; resolve(gpsQueueDb); };
    request.onerror = function () { reject(request.error || new Error('Could not open offline GPS storage.')); };
  });
}

function queueGpsPoint(point) {
  return openGpsQueue().then(function (db) {
    return new Promise(function (resolve, reject) {
      var tx = db.transaction(GPS_QUEUE_STORE, 'readwrite');
      var store = tx.objectStore(GPS_QUEUE_STORE);
      store.put(point);
      var allRequest = store.getAll();
      allRequest.onsuccess = function () {
        var entries = (allRequest.result || []).sort(function (a, b) { return a.captured_at.localeCompare(b.captured_at); });
        var cutoff = Date.now() - 86400000;
        entries.forEach(function (entry, index) {
          if (index < entries.length - GPS_QUEUE_MAX_POINTS || Date.parse(entry.captured_at) < cutoff) store.delete(entry.id);
        });
      };
      tx.oncomplete = resolve;
      tx.onerror = function () { reject(tx.error || new Error('Could not save GPS point offline.')); };
    });
  });
}

function getGpsQueueBatch() {
  return openGpsQueue().then(function (db) {
    return new Promise(function (resolve, reject) {
      var tx = db.transaction(GPS_QUEUE_STORE, 'readonly');
      var request = tx.objectStore(GPS_QUEUE_STORE).getAll();
      request.onsuccess = function () {
        resolve((request.result || []).sort(function (a, b) { return a.captured_at.localeCompare(b.captured_at); }).slice(0, 100));
      };
      request.onerror = function () { reject(request.error || new Error('Could not read offline GPS points.')); };
    });
  });
}

function deleteGpsQueueBatch(points) {
  return openGpsQueue().then(function (db) {
    return new Promise(function (resolve, reject) {
      var tx = db.transaction(GPS_QUEUE_STORE, 'readwrite');
      var store = tx.objectStore(GPS_QUEUE_STORE);
      points.forEach(function (point) { store.delete(point.id); });
      tx.oncomplete = resolve;
      tx.onerror = function () { reject(tx.error || new Error('Could not clear synchronized GPS points.')); };
    });
  });
}

function updateGpsQueueStatus(message) {
  var el = document.getElementById('gpsQueueStatus');
  if (el) el.textContent = message || '';
}

function flushGpsQueue() {
  if (gpsQueueFlushBusy || !navigator.onLine) return;
  gpsQueueFlushBusy = true;
  getGpsQueueBatch().then(function (points) {
    if (!points.length) { updateGpsQueueStatus(''); return; }
    return fetch('/disbasura/api/gps-history-sync.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ points: points })
    }).then(function (response) {
      if (!response.ok) throw new Error('Saved GPS points are waiting for the server.');
      return response.json().then(function (result) { return deleteGpsQueueBatch(points).then(function () { return result; }); });
    }).then(function (result) {
      if (!result) return;
      return getGpsQueueBatch().then(function (remaining) {
        updateGpsQueueStatus(result.skipped
          ? result.skipped + ' old or invalid offline GPS points were discarded; ' + remaining.length + ' still waiting.'
          : (remaining.length ? remaining.length + ' offline GPS points still waiting to sync.' : 'Offline GPS history synced.'));
        if (remaining.length) setTimeout(flushGpsQueue, 500);
      });
    });
  }).catch(function (error) {
    updateGpsQueueStatus(error.message || 'Offline GPS points are waiting to sync.');
  }).finally(function () { gpsQueueFlushBusy = false; });
}

function makeGpsPoint(pos) {
  return {
    id: (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2),
    lat: pos.coords.latitude,
    lng: pos.coords.longitude,
    accuracy: pos.coords.accuracy,
    captured_at: new Date(pos.timestamp || Date.now()).toISOString()
  };
}

window.addEventListener('online', function () {
  updateGpsQueueStatus('Connection restored. Syncing saved GPS history…');
  flushGpsQueue();
});
window.addEventListener('offline', function () {
  updateGpsQueueStatus('Offline. GPS points are saved on this device; live location is not shared.');
});
setInterval(flushGpsQueue, 15000);

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
  flushGpsQueue();
  clearSharedLocation().catch(function () {
    if (!navigator.onLine) document.getElementById('gpsStatus').textContent = 'Offline. Collecting device fixes locally; live location is not shared until the server is reachable.';
  });
  gpsWatchId = navigator.geolocation.watchPosition(updateLocation, showLocationError, {
    enableHighAccuracy: true,
    maximumAge: 0,
    timeout: 30000
  });
  if (!navigator.onLine) updateGpsQueueStatus('Offline. GPS history is queued on this device for up to 24 hours; no live location is shared.');
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
    if (!gpsOutOfArea) clearSharedLocation().catch(function () {});
    gpsOutOfArea = true;
    document.getElementById('gpsStatus').textContent =
      'Your device reports ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ' outside the Cebu City service area. Location not shared.';
    return;
  }
  gpsOutOfArea = false;
  var point = makeGpsPoint(pos);
  if (Date.now() - lastQueuedAt >= GPS_QUEUE_INTERVAL_MS) {
    lastQueuedAt = Date.now();
    queueGpsPoint(point).then(flushGpsQueue).catch(function () {
      updateGpsQueueStatus('This browser could not save the GPS fix for offline history.');
    });
  }
  if (!navigator.onLine) {
    document.getElementById('gpsStatus').textContent = 'GPS fix received, but offline; it is not live on the tracker.';
    updateGpsQueueStatus('Offline. GPS points are saved on this device; live location is not shared.');
    return;
  }
  fetch('/disbasura/api/collector-gps.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ lat: lat, lng: lng, accuracy: accuracy, point_id: point.id })
  }).then(function (response) {
    if (response.ok) return;
    return response.json().catch(function () { return {}; }).then(function (body) {
      throw new Error(body.error || 'Location update failed (' + response.status + ').');
    });
  }).then(function () {
    deleteGpsQueueBatch([point]).catch(function () {});
    document.getElementById('gpsStatus').textContent =
      '📍 ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + ' · accuracy ±' + Math.round(accuracy) + ' m · Updated ' + new Date().toLocaleTimeString();
  }).catch(function (error) {
    updateGpsQueueStatus('Connection lost. This GPS fix is saved locally for route history; live location is unavailable.');
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
