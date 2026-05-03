/* ============================================================
   api.js — Fetch helper + smart polling loop

   Data flow:
     ESP32  → POST /api/ingest.php  (every 10s, real sensor data)
     Browser → GET  /api/readings.php (every 12s, polls for changes)

   Dashboard only updates when new data actually arrives.
   All timestamps match exactly when the DHT11 sent the reading.
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

// Tracks last seen recorded_at per marker — for change detection
const lastSeenTimestamp = {};

let prevAvgTemp = null;
let prevAvgHum  = null;

// ── Live status badge ─────────────────────────────────────────
function setLiveStatus(state) {
  const el = document.getElementById('liveStatus');
  if (!el) return;
  if (state === 'live') {
    el.textContent = '● Live';
    el.className   = 'live-status live';
  } else if (state === 'waiting') {
    el.textContent = '○ Waiting for sensor…';
    el.className   = 'live-status waiting';
  } else {
    el.textContent = '○ No data';
    el.className   = 'live-status idle';
  }
}

// ── Per-sensor cards ──────────────────────────────────────────
function renderSensorCards(data, markers) {
  const grid = document.getElementById('sensorCardGrid');
  if (!grid) return;

  // Build a marker name lookup from the markers API
  const nameMap = {};
  if (markers) markers.forEach(m => { nameMap[m.id] = m; });

  if (!data.length) {
    grid.innerHTML = '<div class="sensor-card-placeholder">No sensor data yet.</div>';
    return;
  }

  grid.innerHTML = data.map(r => {
    const marker    = nameMap[r.marker_id] || {};
    const isActive  = !marker.sensor_status || marker.sensor_status === 'active';
    const temp      = parseFloat(r.temperature);
    const hum       = parseFloat(r.humidity);
    const time      = new Date(r.recorded_at).toLocaleTimeString([], {
      hour: '2-digit', minute: '2-digit', second: '2-digit'
    });

    // Heat level indicator (colour changes with temperature)
    const tempColor = temp >= 34 ? 'var(--red)'
                    : temp >= 30 ? 'var(--amber)'
                    : 'var(--teal)';

    return `
      <div class="sensor-dash-card ${isActive ? '' : 'offline'}" onclick="openDetailModal(${r.marker_id})">
        <div class="sdc-header">
          <span class="sdc-dot ${isActive ? 'on' : 'off'}"></span>
          <span class="sdc-name">${r.marker_name}</span>
          <span class="sdc-status ${isActive ? 'on' : 'off'}">${isActive ? 'ON' : 'OFF'}</span>
        </div>
        <div class="sdc-readings">
          <div class="sdc-val">
            <div class="sdc-num" style="color:${tempColor}">${temp.toFixed(1)}</div>
            <div class="sdc-unit">°C</div>
            <div class="sdc-label">Temp</div>
          </div>
          <div class="sdc-divider"></div>
          <div class="sdc-val">
            <div class="sdc-num" style="color:var(--blue)">${hum.toFixed(1)}</div>
            <div class="sdc-unit">%</div>
            <div class="sdc-label">Humidity</div>
          </div>
        </div>
        <div class="sdc-time">Updated ${time}</div>
      </div>`;
  }).join('');
}

// ── Main polling function ─────────────────────────────────────
async function refreshReadings() {
  if (SIMULATE) await apiFetch('api/simulate.php');

  // Fetch both readings and markers in parallel
  const [data, markers] = await Promise.all([
    apiFetch('api/readings.php'),
    apiFetch('api/markers.php')
  ]);

  if (!data || !data.length) {
    setLiveStatus('idle');
    return;
  }

  // ── Change detection ──────────────────────────────────────
  let hasNewData = false;
  data.forEach(r => {
    if (lastSeenTimestamp[r.marker_id] !== r.recorded_at) {
      hasNewData = true;
      lastSeenTimestamp[r.marker_id] = r.recorded_at;
    }
  });

  // Always update shared state and map popups
  data.forEach(r => { latestData[r.marker_id] = r; });
  refreshMapPopups();

  // Always re-render sensor cards (status may have changed)
  renderSensorCards(data, markers);

  if (!hasNewData) {
    setLiveStatus('waiting');
    return;
  }

  setLiveStatus('live');

  // ── Summary averages ──────────────────────────────────────
  const temps = data.map(r => parseFloat(r.temperature));
  const hums  = data.map(r => parseFloat(r.humidity));
  const avg   = arr => (arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1);
  const avgT  = avg(temps);
  const avgH  = avg(hums);

  document.getElementById('avgTemp').innerHTML         = avgT + '<span class="unit">°C</span>';
  document.getElementById('avgHum').innerHTML          = avgH + '<span class="unit">%</span>';
  document.getElementById('activeSensors').textContent = data.length;

  // Use the most recent sensor's recorded_at — NOT the browser clock
  const latestReading = data.reduce((newest, r) =>
    new Date(r.recorded_at) > new Date(newest.recorded_at) ? r : newest
  , data[0]);

  const sensorTime = new Date(latestReading.recorded_at);
  document.getElementById('lastUpdate').textContent =
    sensorTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

  // ── Trend arrows ──────────────────────────────────────────
  if (prevAvgTemp !== null) {
    const dt = (avgT - prevAvgTemp).toFixed(1);
    document.getElementById('avgTempTrend').textContent =
      (dt >= 0 ? '▲' : '▼') + ' ' + Math.abs(dt) + '° since last reading';
  }
  if (prevAvgHum !== null) {
    const dh = (avgH - prevAvgHum).toFixed(1);
    document.getElementById('avgHumTrend').textContent =
      (dh >= 0 ? '▲' : '▼') + ' ' + Math.abs(dh) + '% since last reading';
  }

  prevAvgTemp = parseFloat(avgT);
  prevAvgHum  = parseFloat(avgH);

  // ── Push to charts using sensor timestamp ─────────────────
  const chartLabel = sensorTime.toLocaleTimeString([], {
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  });
  pushToChart(tempChart, chartLabel, parseFloat(avgT));
  pushToChart(humChart,  chartLabel, parseFloat(avgH));

  if (document.getElementById('sensors-panel').classList.contains('active')) {
    loadSensorList();
  }
}