/* ============================================================
   modal.js — Sensor detail modal + sensor list panel
   ============================================================ */

async function loadSensorList() {
  const [readings, markers] = await Promise.all([
    apiFetch('api/readings.php'),
    apiFetch('api/markers.php')
  ]);
  const el = document.getElementById('sensorList');
  if (!markers) return;

  // Index readings by marker_id
  const readingMap = {};
  if (readings) readings.forEach(r => { readingMap[r.marker_id] = r; });

  el.innerHTML = markers.map(m => {
    const r = readingMap[m.id];
    const isActive = m.sensor_status === 'active';
    return `
      <div class="sensor-row ${isActive ? '' : 'inactive'}" onclick="openDetailModal(${m.id})">
        <span class="sensor-dot ${isActive ? '' : 'off'}"></span>
        <span class="sname">${m.name}</span>
        <span class="sensor-status-tag ${isActive ? 'on' : 'off'}">${isActive ? 'ON' : 'OFF'}</span>
        <span class="sensor-vals">
          ${r ? `<span class="val-temp">${r.temperature}°C</span><span class="val-hum">${r.humidity}%</span>`
              : `<span style="color:#6b7a90;font-size:.78rem">No data</span>`}
        </span>
      </div>`;
  }).join('');
}

async function openDetailModal(markerId) {
  const modal = document.getElementById('detailModal');
  const r     = latestData[markerId];
  const meta  = markerLayer[markerId]?.meta;

  document.getElementById('modalTitle').textContent = meta?.name ?? `Sensor #${markerId}`;
  document.getElementById('mTemp').textContent = r ? r.temperature : '--';
  document.getElementById('mHum').textContent  = r ? r.humidity    : '--';
  document.getElementById('mTs').textContent   = r ? 'Updated: ' + new Date(r.recorded_at).toLocaleString() : 'No data yet';
  modal.classList.add('open');

  const hist = await apiFetch(`api/readings.php?marker_id=${markerId}&limit=15`);
  buildMiniChart(hist);
}

function closeModal(e) {
  const overlay = document.getElementById('detailModal');
  if (!e || e.target === overlay || e.target.classList.contains('modal-close')) {
    overlay.classList.remove('open');
  }
}