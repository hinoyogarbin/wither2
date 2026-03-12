/* ============================================================
   app.js — Navigation, toast notifications, boot sequence
   ============================================================ */

// ── Sidebar Navigation ───────────────────────────────────────
document.querySelectorAll('.nav-item[data-panel]').forEach(el => {
  el.addEventListener('click', () => {
    // Deactivate all nav items and panels
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));

    // Activate the clicked item and its panel
    el.classList.add('active');
    document.getElementById(el.dataset.panel).classList.add('active');

    // Close sidebar on mobile after navigation
    if (window.innerWidth <= 768) {
      closeSidebar();
    }

    onPanelActivate(el.dataset.panel);
  });
});

/**
 * Opens the mobile sidebar and shows the backdrop.
 * Called by the hamburger button in the topbar.
 */
function toggleSidebar() {
  const sidebar  = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const isOpen   = sidebar.classList.contains('open');
  if (isOpen) {
    closeSidebar();
  } else {
    sidebar.classList.add('open');
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden'; // prevent body scroll while sidebar open
  }
}

/**
 * Closes the mobile sidebar and hides the backdrop.
 * Called by backdrop click, nav item click, or hamburger toggle.
 */
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarBackdrop').classList.remove('open');
  document.body.style.overflow = '';
}

/**
 * Lazy-loads panel-specific data the first time (or every time)
 * a panel becomes active.
 *
 * @param {string} id - panel element ID
 */
function onPanelActivate(id) {
  if (id === 'sensors-panel')  loadSensorList();
  if (IS_ADMIN && id === 'manage-panel')   { loadMarkersTable(); initPickerMap(); }
  if (IS_ADMIN && id === 'analytics-panel') loadAnalytics();
}

// ── Toast Notifications ──────────────────────────────────────
/**
 * Shows a brief toast message at the bottom-right of the screen.
 *
 * @param {string} msg  - message to display
 * @param {'ok'|'err'} type - visual style
 */
function showToast(msg, type = 'ok') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className   = 'toast ' + type + ' show';
  setTimeout(() => el.classList.remove('show'), 3000);
}

// ── Boot Sequence ────────────────────────────────────────────
/**
 * Entry point: load map markers first so markerLayer is populated,
 * then kick off the sensor simulation polling loop.
 */
loadMapMarkers().then(() => {
  refreshReadings();
  setInterval(refreshReadings, INTERVAL);
});