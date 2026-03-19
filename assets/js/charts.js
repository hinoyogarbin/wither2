/* ============================================================
   charts.js — Chart.js instances and helpers
   ============================================================ */

const CHART_SCALES = {
  x: { grid: { color: '#dde3ea' }, ticks: { color: '#6b7a90', font: { size: 10 } } },
  y: { grid: { color: '#dde3ea' }, ticks: { color: '#6b7a90', font: { size: 10 } } }
};

const CHART_SCALES_SM = {
  x: { grid: { color: '#dde3ea' }, ticks: { color: '#6b7a90', font: { size: 9 } } },
  y: { grid: { color: '#dde3ea' }, ticks: { color: '#6b7a90', font: { size: 9 } } }
};

function chartCfg(label, color) {
  return {
    type: 'line',
    data: { labels: [], datasets: [{ label, data: [], borderColor: color,
      backgroundColor: color + '22', borderWidth: 2, tension: .4, pointRadius: 3 }] },
    options: { responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } }, scales: CHART_SCALES }
  };
}

const tempChart = new Chart(document.getElementById('tempChart'), chartCfg('Temperature °C', '#d97706'));
const humChart  = new Chart(document.getElementById('humChart'),  chartCfg('Humidity %',     '#2563eb'));

let miniChartInst = null;

function pushToChart(chart, label, value, maxPts = 20) {
  chart.data.labels.push(label);
  chart.data.datasets[0].data.push(value);
  if (chart.data.labels.length > maxPts) { chart.data.labels.shift(); chart.data.datasets[0].data.shift(); }
  chart.update('none');
}

function buildMiniChart(hist) {
  if (miniChartInst) { miniChartInst.destroy(); miniChartInst = null; }
  if (!hist || !hist.length) return;
  const labels = hist.map(h => new Date(h.recorded_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })).reverse();
  miniChartInst = new Chart(document.getElementById('miniChart'), {
    type: 'line',
    data: { labels, datasets: [
      { label: 'Temp °C', data: hist.map(h => h.temperature).reverse(), borderColor: '#d97706', borderWidth: 1.5, tension: .4, pointRadius: 2 },
      { label: 'Humidity %', data: hist.map(h => h.humidity).reverse(), borderColor: '#2563eb', borderWidth: 1.5, tension: .4, pointRadius: 2 }
    ]},
    options: { responsive: true, maintainAspectRatio: true,
      plugins: { legend: { labels: { color: '#6b7a90', font: { size: 10 } } } }, scales: CHART_SCALES_SM }
  });
}