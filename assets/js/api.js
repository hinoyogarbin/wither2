/* ============================================================
   api.js — Fetch helper, sensor simulation, readings refresh
   ============================================================ */

/**
 * Thin wrapper around fetch() that always returns parsed JSON.
 * Returns null on network or parse errors.
 */
async function apiFetch(url, opts = {}) {
  try {
    const res = await fetch(url, opts);
    return await res.json();
  } catch (err) {
    console.error('[apiFetch]', url, err);
    return null;
  }
}

// ── Readings state ───────────────────────────────────────────
let prevAvgTemp = null;
let prevAvgHum  = null;

/**
 * Called every INTERVAL ms.
 * 1. Triggers IoT simulation (inserts new readings for all sensors).
 * 2. Fetches latest readings and updates the dashboard + map popups.
 */
async function refreshReadings() {
  if (SIMULATE) await apiFetch('api/simulate.php');

  const data = await apiFetch('api/readings.php');
  if (!data) return;

  // Cache latest reading per marker
  data.forEach(r => { latestData[r.marker_id] = r; });

  // Refresh map popup text
  refreshMapPopups();

  // ── Compute averages ──────────────────────────────────────
  const temps = data.map(r => parseFloat(r.temperature));
  const hums  = data.map(r => parseFloat(r.humidity));
  const avg   = arr => (arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1);
  const avgT  = avg(temps);
  const avgH  = avg(hums);

  // ── Update stat cards ─────────────────────────────────────
  document.getElementById('avgTemp').innerHTML        = avgT + '<span class="unit">°C</span>';
  document.getElementById('avgHum').innerHTML         = avgH + '<span class="unit">%</span>';
  document.getElementById('activeSensors').textContent = data.length;
  document.getElementById('lastUpdate').textContent    = new Date().toLocaleTimeString();

  if (prevAvgTemp !== null) {
    const dt = (avgT - prevAvgTemp).toFixed(1);
    document.getElementById('avgTempTrend').textContent =
      (dt >= 0 ? '▲' : '▼') + ' ' + Math.abs(dt) + '° since last';
  }
  if (prevAvgHum !== null) {
    const dh = (avgH - prevAvgHum).toFixed(1);
    document.getElementById('avgHumTrend').textContent =
      (dh >= 0 ? '▲' : '▼') + ' ' + Math.abs(dh) + '% since last';
  }

  prevAvgTemp = parseFloat(avgT);
  prevAvgHum  = parseFloat(avgH);

  // ── Push to live charts ───────────────────────────────────
  const ts = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  pushToChart(tempChart, ts, parseFloat(avgT));
  pushToChart(humChart,  ts, parseFloat(avgH));

  // Refresh sensor list panel if it is currently visible
  if (document.getElementById('sensors-panel').classList.contains('active')) {
    loadSensorList();
  }
}