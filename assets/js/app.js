/* ============================================================
   app.js — Navigation, sidebar, toast, boot
   ============================================================ */

// ── Navigation ───────────────────────────────────────────────
document.querySelectorAll('.nav-item[data-panel]').forEach(el => {
  el.addEventListener('click', () => {
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById(el.dataset.panel).classList.add('active');
    if (window.innerWidth <= 768) closeSidebar();
    onPanelActivate(el.dataset.panel);
  });
});

function onPanelActivate(id) {
  if (id === 'sensors-panel')  loadSensorList();
  if (CAN_MANAGE && id === 'manage-panel')   { loadMarkersTable(); initPickerMap(); }
  if (CAN_MANAGE && id === 'logs-panel')     loadLogs();
  if (IS_ADMIN   && id === 'analytics-panel') loadAnalytics();
  if (IS_ADMIN   && id === 'users-panel')     loadUsersTable();
}

// ── Sidebar ───────────────────────────────────────────────────
function toggleSidebar() {
  const sidebar  = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (sidebar.classList.contains('open')) {
    closeSidebar();
  } else {
    sidebar.classList.add('open');
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
}

function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarBackdrop').classList.remove('open');
  document.body.style.overflow = '';
}

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, type = 'ok') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className   = 'toast ' + type + ' show';
  setTimeout(() => el.classList.remove('show'), 3000);
}

// ── Boot ──────────────────────────────────────────────────────
loadMapMarkers().then(() => {
  refreshReadings();
  setInterval(refreshReadings, INTERVAL);
});