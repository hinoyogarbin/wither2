/* ============================================================
   map.js — Leaflet main map, markers, popup HTML
   ============================================================ */

// Shared state: filled by loadMapMarkers(), read by modal.js & api.js
const markerLayer = {};  // markerId → { lm: L.Marker, meta: object }
const latestData  = {};  // markerId → latest reading object

// ── Map init ─────────────────────────────────────────────────
const leafMap = L.map('map', { maxZoom: 18 })
  .setView([8.36030503390942, 124.86816627657458], 18);

L.tileLayer(
  'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
  { maxZoom: 18, maxNativeZoom: 18, attribution: 'Tiles © Esri' }
).addTo(leafMap);

// ── Popup HTML builder ───────────────────────────────────────
/**
 * Builds the Leaflet popup HTML for a marker.
 * Shows live temp/humidity and a "More Info" button that opens the detail modal.
 *
 * @param {object} m   - marker meta { id, name, ... }
 * @param {object|null} r - latest reading { temperature, humidity, recorded_at }
 */
function buildPopupHtml(m, r) {
  const ts = r ? new Date(r.recorded_at).toLocaleTimeString() : '—';
  return `
    <div class="popup-inner">
      <h4>${m.name}</h4>
      <div class="popup-kv">
        <span class="k">Temperature</span>
        <span class="v temp">${r ? r.temperature + '°C' : '—'}</span>
      </div>
      <div class="popup-kv">
        <span class="k">Humidity</span>
        <span class="v hum">${r ? r.humidity + '%' : '—'}</span>
      </div>
      <div class="popup-ts">Updated: ${ts}</div>
      <button class="popup-more-btn" onclick="openDetailModal(${m.id})">More Info</button>
    </div>`;
}

// ── Load markers from API ────────────────────────────────────
async function loadMapMarkers() {
  const markers = await apiFetch('api/markers.php');
  if (!markers) return;

  markers.forEach(m => {
    const lm = L.marker([m.latitude, m.longitude]).addTo(leafMap);
    lm.bindPopup(buildPopupHtml(m, null), { maxWidth: 240 });
    markerLayer[m.id] = { lm, meta: m };
  });
}

// ── Refresh popup content with latest readings ───────────────
function refreshMapPopups() {
  Object.entries(markerLayer).forEach(([id, obj]) => {
    const r = latestData[id];
    if (r) obj.lm.setPopupContent(buildPopupHtml(obj.meta, r));
  });
}