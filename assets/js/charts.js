/* ============================================================
   charts.js — Chart.js instances and helper utilities
   ============================================================ */

// ── Shared chart scale config (light theme) ──────────────────
const CHART_SCALES = {
  x: {
    grid:  { color: '#dde3ea' },
    ticks: { color: '#6b7a90', font: { size: 10 } }
  },
  y: {
    grid:  { color: '#dde3ea' },
    ticks: { color: '#6b7a90', font: { size: 10 } }
  }
};

const CHART_SCALES_SM = {
  x: {
    grid:  { color: '#dde3ea' },
    ticks: { color: '#6b7a90', font: { size: 9 } }
  },
  y: {
    grid:  { color: '#dde3ea' },
    ticks: { color: '#6b7a90', font: { size: 9 } }
  }
};

// ── Factory: create a basic live line chart config ───────────
/**
 * @param {string} label - dataset label
 * @param {string} color - hex border colour
 */
function chartCfg(label, color) {
  return {
    type: 'line',
    data: {
      labels: [],
      datasets: [{
        label,
        data: [],
        borderColor:     color,
        backgroundColor: color + '22',
        borderWidth: 2,
        tension:     .4,
        pointRadius: 3
      }]
    },
    options: {
      responsive:          true,
      maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: CHART_SCALES
    }
  };
}

// ── Dashboard live charts ────────────────────────────────────
const tempChart = new Chart(
  document.getElementById('tempChart'),
  chartCfg('Temperature °C', '#d97706')
);

const humChart = new Chart(
  document.getElementById('humChart'),
  chartCfg('Humidity %', '#2563eb')
);

// ── Modal mini-chart instance (recreated on each open) ───────
let miniChartInst = null;

// ── Push a new data point, keeping the last maxPts entries ───
/**
 * @param {Chart}  chart  - Chart.js instance
 * @param {string} label  - x-axis label
 * @param {number} value  - y-axis value
 * @param {number} maxPts - maximum points to keep (default 20)
 */
function pushToChart(chart, label, value, maxPts = 20) {
  chart.data.labels.push(label);
  chart.data.datasets[0].data.push(value);

  if (chart.data.labels.length > maxPts) {
    chart.data.labels.shift();
    chart.data.datasets[0].data.shift();
  }

  chart.update('none');
}

// ── Build the mini history chart inside the detail modal ─────
/**
 * @param {object[]} hist - array of reading objects (newest-first from API)
 */
function buildMiniChart(hist) {
  if (miniChartInst) {
    miniChartInst.destroy();
    miniChartInst = null;
  }

  if (!hist || !hist.length) return;

  // API returns newest-first; reverse for left-to-right time axis
  const labels = hist.map(h =>
    new Date(h.recorded_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  ).reverse();

  const tData = hist.map(h => h.temperature).reverse();
  const hData = hist.map(h => h.humidity).reverse();

  miniChartInst = new Chart(document.getElementById('miniChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Temp °C',
          data:        tData,
          borderColor: '#d97706',
          borderWidth: 1.5,
          tension:     .4,
          pointRadius: 2
        },
        {
          label: 'Humidity %',
          data:        hData,
          borderColor: '#2563eb',
          borderWidth: 1.5,
          tension:     .4,
          pointRadius: 2
        }
      ]
    },
    options: {
      responsive:          true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          labels: { color: '#6b7a90', font: { size: 10 } }
        }
      },
      scales: CHART_SCALES_SM
    }
  });
}