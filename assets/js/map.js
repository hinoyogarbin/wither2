/* ============================================================
   map.js — Leaflet map, markers, popups
   ============================================================ */

const markerLayer = {};  // markerId → { lm, meta }
const latestData  = {};  // markerId → latest reading

const leafMap = L.map('map', { maxZoom: 18 })
  .setView([8.36030503390942, 124.86816627657458], 18);

L.tileLayer(
  'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
  { maxZoom: 18, maxNativeZoom: 18, attribution: 'Tiles © Esri' }
).addTo(leafMap);

function sensorStatusBadge(status) {
  return status === 'active'
    ? '<span class="status-badge active">● Active</span>'
    : '<span class="status-badge inactive">● Inactive</span>';
}

function buildPopupHtml(m, r) {
  const ts = r ? new Date(r.recorded_at).toLocaleTimeString() : '—';
  return `
    <div class="popup-inner">
      <h4>${m.name}</h4>
      ${sensorStatusBadge(m.sensor_status)}
      <div class="popup-kv"><span class="k">Temperature</span>
        <span class="v temp">${r ? r.temperature + '°C' : '—'}</span></div>
      <div class="popup-kv"><span class="k">Humidity</span>
        <span class="v hum">${r ? r.humidity + '%' : '—'}</span></div>
      <div class="popup-ts">Updated: ${ts}</div>
      <button class="popup-more-btn" onclick="openDetailModal(${m.id})">More Info</button>
    </div>`;
}

function markerIcon(status) {
  const color = status === 'active' ? '#0097a7' : '#9ca3af';
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="40" viewBox="0 0 28 40">
    <path d="M14 0C6.3 0 0 6.3 0 14c0 9.8 14 26 14 26S28 23.8 28 14C28 6.3 21.7 0 14 0z" fill="${color}"/>
    <circle cx="14" cy="14" r="6" fill="white"/>
  </svg>`;
  return L.divIcon({
    html: svg, className: '', iconSize: [28,40], iconAnchor: [14,40], popupAnchor: [0,-40]
  });
}

async function loadMapMarkers() {
  const markers = await apiFetch('api/markers.php');
  if (!markers) return;

  // Clear existing
  Object.values(markerLayer).forEach(o => o.lm.remove());
  Object.keys(markerLayer).forEach(k => delete markerLayer[k]);

  markers.forEach(m => {
    const lm = L.marker([m.latitude, m.longitude], { icon: markerIcon(m.sensor_status) }).addTo(leafMap);
    lm.bindPopup(buildPopupHtml(m, null), { maxWidth: 240 });
    markerLayer[m.id] = { lm, meta: m };
  });

  // Update inactive sensor count on dashboard
  const inactive = markers.filter(m => m.sensor_status === 'inactive').length;
  const el = document.getElementById('inactiveSensors');
  if (el) el.textContent = inactive + ' offline';
}

function refreshMapPopups() {
  Object.entries(markerLayer).forEach(([id, obj]) => {
    const r = latestData[id];
    if (r) obj.lm.setPopupContent(buildPopupHtml(obj.meta, r));
  });
}