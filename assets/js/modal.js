/* ============================================================
   modal.js — Sensor detail modal + sensor list panel
   ============================================================ */

// ── Sensor List ──────────────────────────────────────────────
/**
 * Fetches the latest reading per sensor and renders the sensor list panel.
 */
async function loadSensorList() {
  const data = await apiFetch('api/readings.php');
  const el   = document.getElementById('sensorList');
  if (!data) return;

  if (!data.length) {
    el.innerHTML = '<div style="color:#6b7a90;font-size:.85rem;">No readings yet.</div>';
    return;
  }

  el.innerHTML = data.map(r => `
    <div class="sensor-row" onclick="openDetailModal(${r.marker_id})">
      <span class="sensor-dot"></span>
      <span class="sname">${r.marker_name}</span>
      <span class="sensor-vals">
        <span class="val-temp">${r.temperature}°C</span>
        <span class="val-hum">${r.humidity}%</span>
      </span>
    </div>`).join('');
}

// ── Detail Modal ─────────────────────────────────────────────
/**
 * Opens the sensor detail modal for a given marker.
 * Populates current reading values, then lazily loads and renders
 * the mini history chart.
 *
 * @param {number} markerId
 */
async function openDetailModal(markerId) {
  const modal = document.getElementById('detailModal');
  const r     = latestData[markerId];
  const meta  = markerLayer[markerId]?.meta;

  // ── Populate header & reading cards ──────────────────────
  document.getElementById('modalTitle').textContent =
    meta?.name ?? `Sensor #${markerId}`;

  document.getElementById('mTemp').textContent =
    r ? r.temperature : '--';

  document.getElementById('mHum').textContent =
    r ? r.humidity : '--';

  document.getElementById('mTs').textContent =
    r ? 'Updated: ' + new Date(r.recorded_at).toLocaleString() : '';

  modal.classList.add('open');

  // ── Fetch history and render mini chart ───────────────────
  const hist = await apiFetch(`api/readings.php?marker_id=${markerId}&limit=15`);
  buildMiniChart(hist);
}

/**
 * Closes the detail modal.
 * Called by the overlay click handler and the × button.
 *
 * @param {MouseEvent} [e]
 */
function closeModal(e) {
  const overlay = document.getElementById('detailModal');
  if (!e || e.target === overlay || e.target.classList.contains('modal-close')) {
    overlay.classList.remove('open');
  }
}