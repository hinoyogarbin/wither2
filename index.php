<?php
require_once 'includes/config.php';
startSession();

$loggedIn  = isLoggedIn();
$role      = getRole();          // guest | user | manager | admin
$isAdmin   = isAdmin();
$isManager = isManager();
$canManage = canManage();        // admin or manager

if ($loggedIn) {
    logActivity($_SESSION['user_id'], 'view_dashboard', '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wither – Micro-Climate Monitor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/layout.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/modals.css">
<link rel="stylesheet" href="assets/css/forms.css">
</head>
<body>

<!-- Mobile sidebar backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

<!-- ─── TOP BAR ─────────────────────────────────────────────── -->
<header class="topbar">
  <div class="hbg" id="hbgBtn" onclick="toggleSidebar()">
    <span></span><span></span><span></span>
  </div>
  <div class="logo">Wither<span> / Monitor</span></div>

  <?php if ($loggedIn): ?>
    <span class="topbar-user"><?= htmlspecialchars($_SESSION['username']) ?></span>
    <span class="badge-role <?= $role ?>"><?= ucfirst($role) ?></span>
    <button class="btn-logout" onclick="location.href='auth/logout.php'">Sign out</button>
  <?php else: ?>
    <a href="auth/login.php"    class="btn-topbar-ghost">Sign in</a>
    <a href="auth/register.php" class="btn-topbar-solid">Register</a>
  <?php endif; ?>
</header>

<!-- ─── APP SHELL ─────────────────────────────────────────────── -->
<div class="app">

  <!-- SIDEBAR -->
  <nav class="sidebar" id="sidebar">
    <div style="height:.6rem"></div>

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

    <?php if ($canManage): ?>
    <div class="sidebar-section" style="margin-top:.4rem">Operations</div>
    <div class="nav-item" data-panel="manage-panel">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
      Manage Sensors
    </div>
    <div class="nav-item" data-panel="logs-panel">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      Activity Logs
    </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <div class="sidebar-section" style="margin-top:.4rem">Admin</div>
    <div class="nav-item" data-panel="analytics-panel">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Analytics
    </div>
    <div class="nav-item" data-panel="users-panel">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      User Management
    </div>
    <?php endif; ?>

    <?php if (!$loggedIn): ?>
    <div class="sidebar-section" style="margin-top:.4rem">Account</div>
    <div class="nav-item" onclick="location.href='auth/login.php'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      Sign In
    </div>
    <div class="nav-item" onclick="location.href='auth/register.php'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      Register
    </div>
    <?php endif; ?>
  </nav>

  <!-- MAIN CONTENT -->
  <main class="main">

    <!-- ═══ MAP PANEL ═══════════════════════════════════════════ -->
    <section class="panel active" id="map-panel">
      <div class="section-title">Sensor Map <span>Live locations</span></div>
      <div class="map-wrap"><div id="map"></div></div>
    </section>

    <!-- ═══ DASHBOARD PANEL ═════════════════════════════════════ -->
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
          <div class="trend" id="inactiveSensors">— offline</div>
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

    <!-- ═══ SENSORS LIST PANEL ══════════════════════════════════ -->
    <section class="panel" id="sensors-panel">
      <div class="section-title">All Sensors <span>Latest reading per location</span></div>
      <div class="sensor-list" id="sensorList">
        <div style="color:#6b7a90;font-size:.85rem;">Loading…</div>
      </div>
    </section>

    <?php if ($canManage): ?>
    <!-- ═══ MANAGE SENSORS PANEL ════════════════════════════════ -->
    <section class="panel" id="manage-panel">
      <div class="section-title">Manage Sensors <span>Add, edit, toggle ON/OFF</span></div>

      <div class="add-sensor-wrap">
        <!-- Map picker -->
        <div class="picker-map-card">
          <div class="chart-title" style="margin-bottom:.6rem">📍 Click the map to place a pin</div>
          <div id="pickerMap"></div>
          <div id="pickerCoords" class="picker-coords empty">No location selected yet</div>
        </div>
        <!-- Form -->
        <div class="chart-card picker-form-card">
          <div class="chart-title">Sensor details</div>
          <div class="form-group">
            <label>Name</label>
            <input type="text" id="nm-name" placeholder="e.g. NBSC Library">
          </div>
          <div class="form-group">
            <label>Latitude</label>
            <input type="number" step="any" id="nm-lat" placeholder="Click map…" readonly>
          </div>
          <div class="form-group">
            <label>Longitude</label>
            <input type="number" step="any" id="nm-lng" placeholder="Click map…" readonly>
          </div>
          <div class="form-group">
            <label>Description (optional)</label>
            <textarea id="nm-desc" rows="2" placeholder="Short description…"></textarea>
          </div>
          <button class="btn-primary" style="width:100%" onclick="addMarker()">Add Sensor</button>
          <button class="btn-clear"   style="width:100%;margin-top:.5rem" onclick="clearPicker()">Clear Pin</button>
        </div>
      </div>

      <!-- Sensors table with ON/OFF -->
      <div class="section-title" style="margin-top:1.4rem">Existing Sensors</div>
      <div class="tbl-wrap">
        <table class="data-table">
          <thead><tr>
            <th>ID</th><th>Name</th><th>Status</th><th>Latitude</th><th>Longitude</th><th>Created</th><th>Actions</th>
          </tr></thead>
          <tbody id="markersTable"><tr><td colspan="7" style="color:#6b7a90">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- ═══ ACTIVITY LOGS PANEL ════════════════════════════════ -->
    <section class="panel" id="logs-panel">
      <div class="section-title">Activity Logs <span>All tracked events</span></div>
      <div class="tbl-wrap">
        <table class="data-table">
          <thead><tr><th>User</th><th>Action</th><th>Detail</th><th>IP</th><th>Time</th></tr></thead>
          <tbody id="logsTable"><tr><td colspan="5" style="color:#6b7a90">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <!-- ═══ ANALYTICS PANEL ════════════════════════════════════ -->
    <section class="panel" id="analytics-panel">
      <div class="section-title">System Analytics</div>
      <div class="stat-grid" id="adminStatGrid" style="margin-bottom:1.2rem">
        <div class="stat-card"><div class="label">Total Users</div><div class="value" id="statUsers">--</div></div>
        <div class="stat-card" style="--accent:var(--teal)"><div class="label">Active Sensors</div><div class="value" id="statSensors">--</div></div>
        <div class="stat-card" style="--accent:var(--red)"><div class="label">Inactive Sensors</div><div class="value" id="statInactive">--</div></div>
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
    </section>

    <!-- ═══ USER MANAGEMENT PANEL ══════════════════════════════ -->
    <section class="panel" id="users-panel">
      <div class="section-title">User Management <span>Create and manage accounts</span></div>

      <!-- Create user form -->
      <div class="chart-card" style="max-width:520px;margin-bottom:1.4rem">
        <div class="chart-title">Add New User</div>
        <div class="form-row">
          <div class="form-group"><label>Username</label><input type="text" id="u-username" placeholder="username"></div>
          <div class="form-group"><label>Email</label><input type="email" id="u-email" placeholder="email@example.com"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Password</label><input type="password" id="u-password" placeholder="Min 6 chars"></div>
          <div class="form-group">
            <label>Role</label>
            <select id="u-role">
              <option value="user">User</option>
              <option value="manager">Manager</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <button class="btn-primary" onclick="createUser()">Create User</button>
      </div>

      <!-- Users table -->
      <div class="tbl-wrap">
        <table class="data-table">
          <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody id="usersTable"><tr><td colspan="7" style="color:#6b7a90">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

  </main>
</div>

<!-- ─── MARKER DETAIL MODAL ─────────────────────────────────── -->
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
    <div class="modal-mini-chart"><canvas id="miniChart"></canvas></div>
  </div>
</div>

<!-- ─── TOAST ───────────────────────────────────────────────── -->
<div class="toast" id="toast"></div>

<script>
/* ── PHP → JS config ── */
const IS_ADMIN   = <?= $isAdmin   ? 'true' : 'false' ?>;
const IS_MANAGER = <?= $isManager ? 'true' : 'false' ?>;
const CAN_MANAGE = <?= $canManage ? 'true' : 'false' ?>;
const IS_LOGGED  = <?= $loggedIn  ? 'true' : 'false' ?>;
const SIMULATE   = true;
const INTERVAL   = 5000;
</script>
<script src="assets/js/api.js"></script>
<script src="assets/js/map.js"></script>
<script src="assets/js/charts.js"></script>
<script src="assets/js/modal.js"></script>
<script src="assets/js/admin.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>