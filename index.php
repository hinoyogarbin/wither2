<?php
// index.php  – main dashboard (user + admin)
require_once 'includes/config.php';
requireLogin();
logActivity($_SESSION['user_id'], 'view_dashboard', '');
$isAdmin = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wither – Dashboard</title>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>


<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/layout.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/modals.css">
<link rel="stylesheet" href="assets/css/forms.css">
</head>
<body>

<!-- ─── TOP BAR ───────────────────────────────────────────── -->
<!-- Mobile sidebar backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

<header class="topbar">
  <div class="hbg" id="hbgBtn" onclick="toggleSidebar()">
    <span></span><span></span><span></span>
  </div>
  <div class="logo">Wither<span> / Monitor</span></div>
  <span class="topbar-user"><?= htmlspecialchars($_SESSION['username']) ?></span>
  <span class="badge-role <?= $isAdmin ? 'admin' : '' ?>"><?= $isAdmin ? 'Admin' : 'User' ?></span>
  <button class="btn-logout" onclick="location.href='auth/logout.php'">Sign out</button>
</header>

<!-- ─── MAIN APP ──────────────────────────────────────────── -->
<div class="app">

  <!-- SIDEBAR -->
  <nav class="sidebar" id="sidebar">
    <div style="height:.8rem"></div>
    <div class="sidebar-section">Monitor</div>
    <div class="nav-item active" data-panel="map-panel">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      Map
    </div>
    <div class="nav-item" data-panel="dashboard-panel">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </div>
    <div class="nav-item" data-panel="sensors-panel">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Sensors
    </div>

    <?php if ($isAdmin): ?>
    <div class="sidebar-section" style="margin-top:.5rem">Admin</div>
    <div class="nav-item" data-panel="manage-panel">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
      Manage Sensors
    </div>
    <div class="nav-item" data-panel="analytics-panel">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Analytics
    </div>
    <?php endif; ?>
  </nav>

  <!-- MAIN PANELS -->
  <main class="main">

    <!-- ═══ MAP PANEL ══════════════════════════════════════ -->
    <section class="panel active" id="map-panel">
      <div class="section-title">Sensor Map <span>Live locations</span></div>
      <div class="map-wrap"><div id="map"></div></div>
    </section>

    <!-- ═══ DASHBOARD PANEL ════════════════════════════════ -->
    <section class="panel" id="dashboard-panel">
      <div class="section-title">Dashboard <span>Updated every 5 s</span></div>
      <div class="stat-grid" id="statGrid">
        <div class="stat-card" style="--accent:var(--amber)">
          <div class="label">Avg Temperature</div>
          <div class="value" id="avgTemp">--<span class="unit">°C</span></div>
          <div class="trend" id="avgTempTrend">—</div>
        </div>
        <div class="stat-card" style="--accent:var(--blue)">
          <div class="label">Avg Humidity</div>
          <div class="value" id="avgHum">--<span class="unit">%</span></div>
          <div class="trend" id="avgHumTrend">—</div>
        </div>
        <div class="stat-card" style="--accent:var(--teal)">
          <div class="label">Active Sensors</div>
          <div class="value" id="activeSensors">--</div>
          <div class="trend">markers online</div>
        </div>
        <div class="stat-card">
          <div class="label">Last Update</div>
          <div class="value" style="font-size:1rem;padding-top:.4rem" id="lastUpdate">—</div>
        </div>
      </div>

      <div class="chart-grid" style="margin-top:1.1rem">
        <div class="chart-card">
          <div class="chart-title">Temperature — all sensors (last 20)</div>
          <canvas id="tempChart"></canvas>
        </div>
        <div class="chart-card">
          <div class="chart-title">Humidity — all sensors (last 20)</div>
          <canvas id="humChart"></canvas>
        </div>
      </div>
    </section>

    <!-- ═══ SENSORS LIST PANEL ═════════════════════════════ -->
    <section class="panel" id="sensors-panel">
      <div class="section-title">All Sensors <span>Latest reading per location</span></div>
      <div class="sensor-list" id="sensorList">
        <div style="color:#6b7a90;font-size:.85rem;">Loading…</div>
      </div>
    </section>

    <?php if ($isAdmin): ?>
    <!-- ═══ MANAGE PANEL ════════════════════════════════════ -->
    <section class="panel" id="manage-panel">
      <div class="section-title">Manage Sensors</div>

      <!-- Add sensor: map picker + form side by side -->
      <div class="add-sensor-wrap">

        <!-- LEFT: click-to-pick map -->
        <div class="picker-map-card">
          <div class="chart-title" style="margin-bottom:.6rem">
            📍 Click on the map to drop a pin
          </div>
          <div id="pickerMap"></div>
          <div id="pickerCoords" class="picker-coords">No location selected yet</div>
        </div>

        <!-- RIGHT: form -->
        <div class="chart-card picker-form-card">
          <div class="chart-title">Sensor details</div>
          <div class="form-group">
            <label>Name</label>
            <input type="text" id="nm-name" placeholder="e.g. NBSC Library">
          </div>
          <div class="form-group">
            <label>Latitude</label>
            <input type="number" step="any" id="nm-lat" placeholder="Click map to fill…" readonly>
          </div>
          <div class="form-group">
            <label>Longitude</label>
            <input type="number" step="any" id="nm-lng" placeholder="Click map to fill…" readonly>
          </div>
          <div class="form-group">
            <label>Description (optional)</label>
            <textarea id="nm-desc" rows="2" placeholder="Short description…"></textarea>
          </div>
          <button class="btn-primary" style="width:100%" onclick="addMarker()">Add Sensor</button>
          <button class="btn-clear" style="width:100%;margin-top:.5rem" onclick="clearPicker()">Clear Pin</button>
        </div>
      </div>

      <!-- Markers table -->
      <div class="section-title" style="margin-top:1.4rem">Existing Sensors</div>
      <div class="tbl-wrap">
        <table class="data-table">
          <thead><tr>
            <th>ID</th><th>Name</th><th>Latitude</th><th>Longitude</th><th>Created</th><th>Actions</th>
          </tr></thead>
          <tbody id="markersTable"><tr><td colspan="6" style="color:#6b7a90">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- ═══ ANALYTICS PANEL ═════════════════════════════════ -->
    <section class="panel" id="analytics-panel">
      <div class="section-title">System Analytics</div>
      <div class="stat-grid" id="adminStatGrid" style="margin-bottom:1.2rem">
        <div class="stat-card"><div class="label">Users</div><div class="value" id="statUsers">--</div></div>
        <div class="stat-card"><div class="label">Sensors</div><div class="value" id="statSensors">--</div></div>
        <div class="stat-card"><div class="label">Total Readings</div><div class="value" id="statReadings">--</div></div>
        <div class="stat-card"><div class="label">Total Logins</div><div class="value" id="statLogins">--</div></div>
      </div>
      <div class="chart-grid">
        <div class="chart-card">
          <div class="chart-title">Readings/hour – last 24 h</div>
          <canvas id="readingTrendChart"></canvas>
        </div>
        <div class="chart-card">
          <div class="chart-title">Logins – last 7 days</div>
          <canvas id="loginTrendChart"></canvas>
        </div>
      </div>
      <div class="section-title" style="margin-top:1.2rem">Recent Activity</div>
      <div class="tbl-wrap">
        <table class="data-table">
          <thead><tr><th>User</th><th>Action</th><th>Detail</th><th>IP</th><th>Time</th></tr></thead>
          <tbody id="activityTable"><tr><td colspan="5" style="color:#6b7a90">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

  </main><!-- /main -->
</div><!-- /app -->

<!-- ─── MARKER DETAIL MODAL ───────────────────────────────── -->
<div class="modal-overlay" id="detailModal" onclick="closeModal(event)">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal()">×</button>
    <div class="modal-title" id="modalTitle">—</div>
    <div class="modal-reading">
      <div class="m-val">
        <div class="mv-label">Temperature</div>
        <div class="mv-num temp" id="mTemp">--</div>
        <div style="font-size:.72rem;color:var(--muted)">°C</div>
      </div>
      <div class="m-val">
        <div class="mv-label">Humidity</div>
        <div class="mv-num hum" id="mHum">--</div>
        <div style="font-size:.72rem;color:var(--muted)">%</div>
      </div>
    </div>
    <div class="modal-ts" id="mTs">—</div>
    <div class="modal-mini-chart" id="miniChartWrap">
      <canvas id="miniChart"></canvas>
    </div>
  </div>
</div>

<!-- ─── TOAST ─────────────────────────────────────────────── -->
<div class="toast" id="toast"></div>

<script>
/* ── Runtime config injected by PHP ── */
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
const SIMULATE = true;   // set false to stop IoT simulation
const INTERVAL = 5000;   // polling interval in ms
</script>

<!-- External JS modules (load order matters) -->
<script src="assets/js/api.js"></script>
<script src="assets/js/map.js"></script>
<script src="assets/js/charts.js"></script>
<script src="assets/js/modal.js"></script>
<script src="assets/js/admin.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>