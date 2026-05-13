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
  
  // Show request button for logged-in regular users
  const btnContainer = document.getElementById('modalButtonContainer');
  if (btnContainer) {
    btnContainer.innerHTML = '';
    if (IS_LOGGED && !CAN_MANAGE) {
      // Show request button for regular users (to request formal data export)
      btnContainer.innerHTML = `
        <button class="btn-sm primary" onclick="openRequestModal(${markerId})">
          Request Data Export
        </button>
      `;
    }
  }

  modal.classList.add('open');

  const hist = await apiFetch(`api/readings.php?marker_id=${markerId}&limit=15`);
  if (hist && hist.error) {
    // Permission error or other API error
    document.getElementById('miniChartContainer').innerHTML = 
      '<div style="padding: 1rem; color: #f97316; text-align: center; line-height: 1.6;">' + 
      hist.error + 
      '</div>';
  } else if (!hist) {
    // Network error or invalid response
    document.getElementById('miniChartContainer').innerHTML = 
      '<div style="padding: 1rem; color: #ef4444; text-align: center;">Failed to load data. Please try again.</div>';
  } else {
    // Successfully loaded data
    buildMiniChart(hist);
  }
}

function closeModal(e) {
  const overlay = document.getElementById('detailModal');
  if (!e || e.target === overlay || e.target.classList.contains('modal-close')) {
    overlay.classList.remove('open');
  }
}

// ── Request Modal ─────────────────────────────────────────────
let currentRequestMarkerId = null;

function openRequestModal(markerId) {
  const meta = markerLayer[markerId]?.meta;
  currentRequestMarkerId = markerId;
  
  document.getElementById('req-sensor').value = meta?.name ?? `Sensor #${markerId}`;
  document.getElementById('req-reason').value = '';
  document.getElementById('req-date-from').value = '';
  document.getElementById('req-date-to').value = '';
  
  document.getElementById('requestModal').classList.add('open');
}

function closeRequestModal(e) {
  const overlay = document.getElementById('requestModal');
  if (!e || e.target === overlay || e.target.classList.contains('modal-close')) {
    overlay.classList.remove('open');
    currentRequestMarkerId = null;
  }
}

async function submitDataRequest(e) {
  e.preventDefault();
  
  if (!currentRequestMarkerId) return;
  
  const reason = document.getElementById('req-reason').value.trim();
  const dateFrom = document.getElementById('req-date-from').value;
  const dateTo = document.getElementById('req-date-to').value;
  
  // Validate date range if provided
  if (dateFrom && dateTo && dateFrom > dateTo) {
    showToast('From date must be before to date', 'error');
    return;
  }

  const res = await apiFetch('api/requests.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ 
      marker_id: currentRequestMarkerId, 
      reason: reason,
      date_from: dateFrom,
      date_to: dateTo
    })
  });

  if (res?.id) {
    showToast('✓ Data request submitted! Awaiting admin approval.', 'ok');
    closeRequestModal();
    closeModal(); // Close detail modal too
  } else {
    showToast('✗ ' + (res?.error || 'Failed to submit request'), 'error');
  }
}