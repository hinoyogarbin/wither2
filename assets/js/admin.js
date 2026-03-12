/* ============================================================
   admin.js — Admin-only features:
              • Picker map (click-to-place sensor)
              • Markers CRUD table
              • System analytics charts & activity log
   ============================================================ */

// ── Picker Map ───────────────────────────────────────────────
let pickerMap    = null;
let pickerMarker = null;

/**
 * Initialises the sensor-placement picker map.
 * Called once when the Manage Sensors panel first becomes active.
 */
function initPickerMap() {
  if (pickerMap) return; // already initialised

  pickerMap = L.map('pickerMap', { maxZoom: 18 })
    .setView([8.36030503390942, 124.86816627657458], 18);

  L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { maxZoom: 18, maxNativeZoom: 18, attribution: 'Tiles © Esri' }
  ).addTo(pickerMap);

  // Show existing sensors as reference dots
  Object.values(markerLayer).forEach(obj => {
    L.circleMarker([obj.meta.latitude, obj.meta.longitude], {
      radius: 6, color: '#0097a7', fillColor: '#0097a7',
      fillOpacity: .35, weight: 1.5
    })
    .bindTooltip(obj.meta.name, { direction: 'top' })
    .addTo(pickerMap);
  });

  // Click → place / move pin and fill form inputs
  pickerMap.on('click', e => {
    const { lat, lng } = e.latlng;
    const lat8 = lat.toFixed(8);
    const lng8 = lng.toFixed(8);

    document.getElementById('nm-lat').value = lat8;
    document.getElementById('nm-lng').value = lng8;

    const coordEl = document.getElementById('pickerCoords');
    coordEl.textContent = `Lat: ${lat8}   Lng: ${lng8}`;
    coordEl.classList.remove('empty');

    if (pickerMarker) {
      pickerMarker.setLatLng(e.latlng);
    } else {
      pickerMarker = L.marker(e.latlng, { draggable: true }).addTo(pickerMap);

      // Dragging fine-tunes the coordinates
      pickerMarker.on('dragend', ev => {
        const p    = ev.target.getLatLng();
        const la   = p.lat.toFixed(8);
        const ln   = p.lng.toFixed(8);
        document.getElementById('nm-lat').value = la;
        document.getElementById('nm-lng').value = ln;
        document.getElementById('pickerCoords').textContent = `Lat: ${la}   Lng: ${ln}`;
      });
    }
  });

  // Leaflet needs a size hint after the panel becomes visible
  setTimeout(() => pickerMap.invalidateSize(), 50);
}

/**
 * Clears the placed pin and resets the coordinate inputs.
 */
function clearPicker() {
  if (pickerMarker) {
    pickerMap.removeLayer(pickerMarker);
    pickerMarker = null;
  }
  document.getElementById('nm-lat').value = '';
  document.getElementById('nm-lng').value = '';

  const coordEl = document.getElementById('pickerCoords');
  coordEl.textContent = 'No location selected yet';
  coordEl.classList.add('empty');
}

// ── Markers Table ────────────────────────────────────────────
/**
 * Fetches all active markers and renders them in the admin table.
 */
async function loadMarkersTable() {
  const data  = await apiFetch('api/markers.php');
  if (!data) return;

  const tbody = document.getElementById('markersTable');
  tbody.innerHTML = data.map(m => `
    <tr>
      <td style="font-family:var(--fnt-mono);color:var(--muted)">${m.id}</td>
      <td>${m.name}</td>
      <td style="font-family:var(--fnt-mono)">${m.latitude}</td>
      <td style="font-family:var(--fnt-mono)">${m.longitude}</td>
      <td style="color:#6b7a90;font-size:.78rem">${m.created_at}</td>
      <td>
        <button class="btn-sm danger" onclick="deleteMarker(${m.id})">Remove</button>
      </td>
    </tr>`).join('');
}

/**
 * Reads the form inputs and POSTs a new marker to the API.
 */
async function addMarker() {
  const name = document.getElementById('nm-name').value.trim();
  const lat  = parseFloat(document.getElementById('nm-lat').value);
  const lng  = parseFloat(document.getElementById('nm-lng').value);
  const desc = document.getElementById('nm-desc').value.trim();

  if (!name || isNaN(lat) || isNaN(lng)) {
    showToast('Fill in name and click the map to set a location.', 'err');
    return;
  }

  const res = await apiFetch('api/markers.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ name, latitude: lat, longitude: lng, description: desc })
  });

  if (res?.id) {
    showToast('Sensor added!', 'ok');
    ['nm-name', 'nm-lat', 'nm-lng', 'nm-desc'].forEach(id => {
      document.getElementById(id).value = '';
    });
    clearPicker();
    loadMarkersTable();
    loadMapMarkers(); // refresh main map
  } else {
    showToast(res?.error ?? 'Error adding sensor.', 'err');
  }
}

/**
 * Soft-deletes a marker after confirmation.
 * @param {number} id
 */
async function deleteMarker(id) {
  if (!confirm('Remove this sensor?')) return;

  const res = await apiFetch('api/markers.php', {
    method:  'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id })
  });

  if (res?.message) {
    showToast('Sensor removed.', 'ok');
    loadMarkersTable();
  } else {
    showToast('Error removing sensor.', 'err');
  }
}

// ── Analytics ────────────────────────────────────────────────
let rtChart = null;
let ltChart = null;

const ANALYTICS_SCALES = {
  x: { grid: { color: '#dde3ea' }, ticks: { color: '#6b7a90', font: { size: 10 } } },
  y: { grid: { color: '#dde3ea' }, ticks: { color: '#6b7a90', font: { size: 10 } } }
};

/**
 * Fetches system stats and renders the analytics panel:
 * summary cards, two charts, and the recent activity table.
 */
async function loadAnalytics() {
  const data = await apiFetch('api/stats.php');
  if (!data) return;

  const s = data.summary;
  document.getElementById('statUsers').textContent    = s.users;
  document.getElementById('statSensors').textContent  = s.sensors;
  document.getElementById('statReadings').textContent = s.readings;
  document.getElementById('statLogins').textContent   = s.logins;

  // ── Readings-per-hour bar chart ───────────────────────────
  if (rtChart) rtChart.destroy();
  rtChart = new Chart(document.getElementById('readingTrendChart'), {
    type: 'bar',
    data: {
      labels:   data.readingTrend.map(r => r.hour),
      datasets: [{
        label:           'Readings',
        data:            data.readingTrend.map(r => r.total),
        backgroundColor: 'rgba(0,151,167,.15)',
        borderColor:     '#0097a7',
        borderWidth:     1
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: ANALYTICS_SCALES
    }
  });

  // ── Logins-per-day line chart ─────────────────────────────
  if (ltChart) ltChart.destroy();
  ltChart = new Chart(document.getElementById('loginTrendChart'), {
    type: 'line',
    data: {
      labels:   data.loginTrend.map(r => r.day),
      datasets: [{
        label:           'Logins',
        data:            data.loginTrend.map(r => r.total),
        borderColor:     '#d97706',
        backgroundColor: '#d9770622',
        borderWidth:     2,
        tension:         .4,
        pointRadius:     3
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: ANALYTICS_SCALES
    }
  });

  // ── Recent activity table ─────────────────────────────────
  const tbody = document.getElementById('activityTable');
  tbody.innerHTML = data.recentActivity.map(a => `
    <tr>
      <td>${a.username || '—'}</td>
      <td style="font-family:var(--fnt-mono);color:var(--teal)">${a.action}</td>
      <td style="color:#6b7a90;font-size:.78rem">${a.detail || ''}</td>
      <td style="font-family:var(--fnt-mono);font-size:.75rem;color:#6b7a90">${a.ip_address || ''}</td>
      <td style="font-size:.75rem;color:#6b7a90">${new Date(a.created_at).toLocaleString()}</td>
    </tr>`).join('');
}