/* ============================================================
   api.js — Fetch helper + live readings polling loop

   Data flow with real ESP32:
     ESP32 → POST /api/ingest.php (every ~10s)
     Browser → GET  /api/readings.php (every 5s, polls latest)

   SIMULATE flag is kept for fallback/demo mode only.
   Set SIMULATE = false in index.php when using real sensors.
   ============================================================ */

async function apiFetch(url, opts = {}) {
  try {
    const res = await fetch(url, opts);
    return await res.json();
  } catch (err) {
    console.error('[apiFetch]', url, err);
    return null;
  }
}

let prevAvgTemp = null;
let prevAvgHum  = null;

async function refreshReadings() {
  // SIMULATE mode: generate fake data server-side (demo/dev only)
  // When using real ESP32, set SIMULATE = false in index.php
  // The ESP32 pushes data to /api/ingest.php independently
  if (SIMULATE) await apiFetch('api/simulate.php');

  // Always poll latest readings from DB regardless of mode
  const data = await apiFetch('api/readings.php');
  if (!data || !data.length) return;

  data.forEach(r => { latestData[r.marker_id] = r; });
  refreshMapPopups();

  // ── Compute averages ──────────────────────────────────────
  const temps = data.map(r => parseFloat(r.temperature));
  const hums  = data.map(r => parseFloat(r.humidity));
  const avg   = arr => (arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1);
  const avgT  = avg(temps);
  const avgH  = avg(hums);

  // ── Update stat cards ─────────────────────────────────────
  document.getElementById('avgTemp').innerHTML         = avgT + '<span class="unit">°C</span>';
  document.getElementById('avgHum').innerHTML          = avgH + '<span class="unit">%</span>';
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

  const ts = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  pushToChart(tempChart, ts, parseFloat(avgT));
  pushToChart(humChart,  ts, parseFloat(avgH));

  if (document.getElementById('sensors-panel').classList.contains('active')) {
    loadSensorList();
  }
}