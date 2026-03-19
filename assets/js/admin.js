/* ============================================================
   admin.js — Manage sensors, logs, analytics, users
   (Only loaded for manager/admin — CAN_MANAGE / IS_ADMIN flags)
   ============================================================ */

// ── Picker Map ───────────────────────────────────────────────
let pickerMap    = null;
let pickerMarker = null;

function initPickerMap() {
  if (pickerMap) return;
  pickerMap = L.map('pickerMap', { maxZoom: 18 })
    .setView([8.36030503390942, 124.86816627657458], 18);
  L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { maxZoom: 18, maxNativeZoom: 18, attribution: 'Tiles © Esri' }
  ).addTo(pickerMap);

  Object.values(markerLayer).forEach(obj => {
    L.circleMarker([obj.meta.latitude, obj.meta.longitude], {
      radius: 6, color: '#0097a7', fillColor: '#0097a7', fillOpacity: .35, weight: 1.5
    }).bindTooltip(obj.meta.name, { direction: 'top' }).addTo(pickerMap);
  });

  pickerMap.on('click', e => {
    const la = e.latlng.lat.toFixed(8);
    const ln = e.latlng.lng.toFixed(8);
    document.getElementById('nm-lat').value = la;
    document.getElementById('nm-lng').value = ln;
    const coordEl = document.getElementById('pickerCoords');
    coordEl.textContent = `Lat: ${la}   Lng: ${ln}`;
    coordEl.classList.remove('empty');
    if (pickerMarker) {
      pickerMarker.setLatLng(e.latlng);
    } else {
      pickerMarker = L.marker(e.latlng, { draggable: true }).addTo(pickerMap);
      pickerMarker.on('dragend', ev => {
        const p = ev.target.getLatLng();
        document.getElementById('nm-lat').value = p.lat.toFixed(8);
        document.getElementById('nm-lng').value = p.lng.toFixed(8);
        document.getElementById('pickerCoords').textContent = `Lat: ${p.lat.toFixed(8)}   Lng: ${p.lng.toFixed(8)}`;
      });
    }
  });
  setTimeout(() => pickerMap.invalidateSize(), 50);
}

function clearPicker() {
  if (pickerMarker) { pickerMap.removeLayer(pickerMarker); pickerMarker = null; }
  document.getElementById('nm-lat').value = '';
  document.getElementById('nm-lng').value = '';
  const coordEl = document.getElementById('pickerCoords');
  coordEl.textContent = 'No location selected yet';
  coordEl.classList.add('empty');
}

// ── Markers Table ────────────────────────────────────────────
async function loadMarkersTable() {
  const data = await apiFetch('api/markers.php');
  if (!data) return;
  const tbody = document.getElementById('markersTable');
  tbody.innerHTML = data.map(m => {
    const isActive = m.sensor_status === 'active';
    const toggleBtn = `<button class="btn-sm ${isActive ? 'warn' : 'primary'}"
      onclick="toggleSensor(${m.id}, '${isActive ? 'inactive' : 'active'}')">
      ${isActive ? 'Turn OFF' : 'Turn ON'}
    </button>`;
    const deleteBtn = IS_ADMIN
      ? `<button class="btn-sm danger" onclick="deleteMarker(${m.id})" style="margin-left:.35rem">Delete</button>`
      : '';
    return `<tr>
      <td style="font-family:var(--fnt-mono);color:var(--muted)">${m.id}</td>
      <td>${m.name}</td>
      <td><span class="status-badge ${isActive ? 'active' : 'inactive'}">${isActive ? '● Active' : '● Inactive'}</span></td>
      <td style="font-family:var(--fnt-mono)">${m.latitude}</td>
      <td style="font-family:var(--fnt-mono)">${m.longitude}</td>
      <td style="color:#6b7a90;font-size:.78rem">${m.created_at}</td>
      <td>${toggleBtn}${deleteBtn}</td>
    </tr>`;
  }).join('');
}

async function addMarker() {
  const name = document.getElementById('nm-name').value.trim();
  const lat  = parseFloat(document.getElementById('nm-lat').value);
  const lng  = parseFloat(document.getElementById('nm-lng').value);
  const desc = document.getElementById('nm-desc').value.trim();
  if (!name || isNaN(lat) || isNaN(lng)) { showToast('Fill in name and click the map.', 'err'); return; }

  const res = await apiFetch('api/markers.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, latitude: lat, longitude: lng, description: desc })
  });
  if (res?.id) {
    showToast('Sensor added!', 'ok');
    ['nm-name','nm-lat','nm-lng','nm-desc'].forEach(id => document.getElementById(id).value = '');
    clearPicker();
    loadMarkersTable();
    loadMapMarkers();
  } else {
    showToast(res?.error ?? 'Error adding sensor.', 'err');
  }
}

async function toggleSensor(id, newStatus) {
  const res = await apiFetch('api/markers.php', {
    method: 'PUT', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, sensor_status: newStatus })
  });
  if (res?.message) {
    showToast(`Sensor ${newStatus === 'active' ? 'activated' : 'deactivated'}.`, 'ok');
    loadMarkersTable();
    loadMapMarkers();
  } else {
    showToast('Error toggling sensor.', 'err');
  }
}

async function deleteMarker(id) {
  if (!confirm('Permanently delete this sensor and all its readings?')) return;
  const res = await apiFetch('api/markers.php', {
    method: 'DELETE', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });
  if (res?.message) { showToast('Sensor deleted.', 'ok'); loadMarkersTable(); loadMapMarkers(); }
  else showToast('Error deleting sensor.', 'err');
}

// ── Activity Logs ────────────────────────────────────────────
async function loadLogs() {
  const data = await apiFetch('api/stats.php');
  if (!data) return;
  const tbody = document.getElementById('logsTable');
  if (!data.recentActivity?.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="color:#6b7a90">No activity yet.</td></tr>';
    return;
  }
  tbody.innerHTML = data.recentActivity.map(a => `
    <tr>
      <td>${a.username || '<em style="color:#6b7a90">guest</em>'}</td>
      <td><span class="action-tag">${a.action}</span></td>
      <td style="color:#6b7a90;font-size:.78rem">${a.detail || ''}</td>
      <td style="font-family:var(--fnt-mono);font-size:.75rem;color:#6b7a90">${a.ip_address || ''}</td>
      <td style="font-size:.75rem;color:#6b7a90">${new Date(a.created_at).toLocaleString()}</td>
    </tr>`).join('');
}

// ── Analytics ────────────────────────────────────────────────
let rtChart = null, ltChart = null;
const A_SCALES = {
  x: { grid: { color: '#dde3ea' }, ticks: { color: '#6b7a90', font: { size: 10 } } },
  y: { grid: { color: '#dde3ea' }, ticks: { color: '#6b7a90', font: { size: 10 } } }
};

async function loadAnalytics() {
  const data = await apiFetch('api/stats.php');
  if (!data) return;
  const s = data.summary;
  document.getElementById('statUsers').textContent    = s.users ?? '—';
  document.getElementById('statSensors').textContent  = s.sensors;
  document.getElementById('statInactive').textContent = s.inactive;
  document.getElementById('statReadings').textContent = s.readings;
  document.getElementById('statLogins').textContent   = s.logins;

  if (rtChart) rtChart.destroy();
  rtChart = new Chart(document.getElementById('readingTrendChart'), {
    type: 'bar',
    data: { labels: data.readingTrend.map(r => r.hour),
            datasets: [{ label: 'Readings', data: data.readingTrend.map(r => r.total),
              backgroundColor: 'rgba(0,151,167,.15)', borderColor: '#0097a7', borderWidth: 1 }] },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: A_SCALES }
  });

  if (ltChart) ltChart.destroy();
  ltChart = new Chart(document.getElementById('loginTrendChart'), {
    type: 'line',
    data: { labels: data.loginTrend.map(r => r.day),
            datasets: [{ label: 'Logins', data: data.loginTrend.map(r => r.total),
              borderColor: '#d97706', backgroundColor: '#d9770622', borderWidth: 2, tension: .4, pointRadius: 3 }] },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: A_SCALES }
  });
}

// ── User Management ──────────────────────────────────────────
async function loadUsersTable() {
  const data = await apiFetch('api/users.php');
  if (!data) return;
  const tbody = document.getElementById('usersTable');
  tbody.innerHTML = data.map(u => `
    <tr>
      <td style="font-family:var(--fnt-mono);color:var(--muted)">${u.id}</td>
      <td>${u.username}</td>
      <td style="color:#6b7a90;font-size:.82rem">${u.email}</td>
      <td><span class="role-tag ${u.role}">${u.role}</span></td>
      <td><span class="status-badge ${u.is_active ? 'active' : 'inactive'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
      <td style="color:#6b7a90;font-size:.78rem">${new Date(u.created_at).toLocaleDateString()}</td>
      <td>
        <button class="btn-sm primary" onclick="toggleUser(${u.id}, ${u.is_active ? 0 : 1})">
          ${u.is_active ? 'Disable' : 'Enable'}
        </button>
        <button class="btn-sm danger" onclick="deleteUser(${u.id})" style="margin-left:.35rem">Delete</button>
      </td>
    </tr>`).join('');
}

async function createUser() {
  const username = document.getElementById('u-username').value.trim();
  const email    = document.getElementById('u-email').value.trim();
  const password = document.getElementById('u-password').value;
  const role     = document.getElementById('u-role').value;
  if (!username || !email || !password) { showToast('All fields required.', 'err'); return; }

  const res = await apiFetch('api/users.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, email, password, role })
  });
  if (res?.id) {
    showToast('User created!', 'ok');
    ['u-username','u-email','u-password'].forEach(id => document.getElementById(id).value = '');
    loadUsersTable();
  } else {
    showToast(res?.error ?? 'Error creating user.', 'err');
  }
}

async function toggleUser(id, active) {
  const res = await apiFetch('api/users.php', {
    method: 'PUT', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, is_active: active })
  });
  if (res?.message) { showToast('User updated.', 'ok'); loadUsersTable(); }
  else showToast('Error.', 'err');
}

async function deleteUser(id) {
  if (!confirm('Delete this user permanently?')) return;
  const res = await apiFetch('api/users.php', {
    method: 'DELETE', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });
  if (res?.message) { showToast('User deleted.', 'ok'); loadUsersTable(); }
  else showToast(res?.error ?? 'Error.', 'err');
}