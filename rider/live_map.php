<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['account_id']) || !in_array($_SESSION['role'], ['rider','staff','admin'])) {
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Live Rider Map";
$activePage = "map";

// FIX #1: Switched from unpkg (unstable SRI) to jsdelivr (stable, no integrity needed for dev)
$extraHead = '
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
';

include "../layout/dashboard_layout.php";
?>

<style>
/* ─── Map Page Styles ─────────────────────────────────────────── */
.map-wrap {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    min-height: 560px;
}

#map {
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    height: 560px;
    width: 100%;
    /* FIX #2: Removed z-index:1 — it was creating a broken stacking context
       that buried Leaflet's internal tile layers */
    position: relative;
}

/* Rider sidebar list */
.rider-list-panel {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.rider-list-header {
    padding: 16px 18px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--surface);
}

.rider-list-header h3 {
    font-size: 14px;
    font-weight: 700;
    margin: 0;
}

.rider-list-body {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.rider-card {
    padding: 12px 14px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    margin-bottom: 8px;
    cursor: pointer;
    transition: var(--trans);
    background: var(--surface-2);
}

.rider-card:hover,
.rider-card.active-card {
    border-color: var(--red);
    background: rgba(232,0,45,0.03);
    box-shadow: 0 0 0 3px var(--red-glow);
}

.rider-card-top {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}

.rider-avatar-sm {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--ink), var(--ink-3));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-family: 'Sora', sans-serif;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
}

.rider-card-name {
    font-weight: 600;
    font-size: 13px;
    line-height: 1.2;
}

.rider-card-vehicle {
    font-size: 11px;
    color: var(--muted);
}

.status-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    margin-left: auto;
    flex-shrink: 0;
}
.status-dot.online  { background: var(--green); box-shadow: 0 0 5px var(--green); }
.status-dot.idle    { background: var(--amber); }
.status-dot.offline { background: var(--muted); }

.rider-card-meta {
    display: flex;
    gap: 8px;
    font-size: 11px;
    color: var(--muted);
    flex-wrap: wrap;
}

.map-stat-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}

.map-stat {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    flex: 1;
    text-align: center;
    box-shadow: var(--shadow);
}

.map-stat-val {
    font-family: 'Sora', sans-serif;
    font-size: 22px;
    font-weight: 800;
    color: var(--ink);
}

.map-stat-lbl {
    font-size: 11px;
    color: var(--muted);
    margin-top: 2px;
}

.pulse-dot {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%,100% { opacity: 1; }
    50%      { opacity: 0.4; }
}

.refresh-btn {
    width: 28px; height: 28px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--surface-2);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; color: var(--muted);
    cursor: pointer; transition: var(--trans);
}

.refresh-btn:hover { color: var(--red); border-color: var(--red); }
.refresh-btn.spinning i { animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Custom Leaflet popup */
.leaflet-popup-content-wrapper {
    border-radius: var(--radius-sm) !important;
    box-shadow: var(--shadow-lg) !important;
    border: 1px solid var(--border) !important;
    font-family: 'DM Sans', sans-serif !important;
}

.nv-popup-title {
    font-family: 'Sora', sans-serif;
    font-weight: 700;
    font-size: 14px;
    margin-bottom: 4px;
}

.nv-popup-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #555;
    margin-top: 3px;
}

@media (max-width: 768px) {
    .map-wrap { grid-template-columns: 1fr; grid-template-rows: auto 400px; }
    .rider-list-panel { max-height: 200px; }
}
</style>

<div class="page-header">
    <div>
        <h1><i class="bi bi-geo-alt-fill" style="color:var(--red);"></i> Live Rider Map</h1>
        <p>Real-time positions of active riders — updates every 15 seconds</p>
    </div>
    <div style="display:flex; align-items:center; gap:8px;">
        <span class="status-dot pulse-dot online" style="width:10px;height:10px;"></span>
        <span id="liveLabel" style="font-size:13px;color:var(--muted);">Live</span>
    </div>
</div>

<!-- Stats bar -->
<div class="map-stat-bar" id="statBar">
    <div class="map-stat">
        <div class="map-stat-val" id="statTotal">—</div>
        <div class="map-stat-lbl">Active Riders</div>
    </div>
    <div class="map-stat">
        <div class="map-stat-val" id="statOnline" style="color:var(--green);">—</div>
        <div class="map-stat-lbl">Online (last 5 min)</div>
    </div>
    <div class="map-stat">
        <div class="map-stat-val" id="statParcels" style="color:var(--red);">—</div>
        <div class="map-stat-lbl">Parcels In Transit</div>
    </div>
</div>

<!-- Map + rider list -->
<div class="map-wrap">
    <!-- Rider list panel -->
    <div class="rider-list-panel">
        <div class="rider-list-header">
            <h3><i class="bi bi-bicycle"></i> Riders</h3>
            <button class="refresh-btn" id="refreshBtn" onclick="refreshMap()" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="rider-list-body" id="riderListBody">
            <div style="padding:30px; text-align:center; color:var(--muted); font-size:13px;">
                <i class="bi bi-arrow-clockwise" style="font-size:24px; display:block; margin-bottom:8px;"></i>
                Loading riders...
            </div>
        </div>
    </div>

    <!-- Leaflet map -->
    <div id="map"></div>
</div>

<script>
// FIX #3: Wrapped everything in DOMContentLoaded so the map div
// is guaranteed to exist in the DOM before Leaflet tries to mount into it.
document.addEventListener('DOMContentLoaded', function () {

// ─── Map Init ────────────────────────────────────────────────────────────────
const map = L.map('map', {
    center: [14.5995, 120.9842],
    zoom: 12,
    zoomControl: true,
});

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
}).addTo(map);

// FIX #4: Force Leaflet to recalculate the container size after layout renders.
// Without this, Leaflet measures 0px height and tiles never load.
setTimeout(() => map.invalidateSize(), 250);

// ─── Custom marker icons ──────────────────────────────────────────────────────
function riderIcon(status) {
    const colors = { online: '#00b37d', idle: '#f59e0b', offline: '#8a8580' };
    const c = colors[status] || colors.offline;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="36" height="44" viewBox="0 0 36 44">
        <filter id="ds"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-opacity="0.25"/></filter>
        <ellipse cx="18" cy="40" rx="7" ry="3" fill="rgba(0,0,0,0.15)"/>
        <path d="M18 0 C8 0 0 8 0 18 C0 30 18 44 18 44 C18 44 36 30 36 18 C36 8 28 0 18 0Z"
              fill="${c}" filter="url(#ds)"/>
        <circle cx="18" cy="18" r="10" fill="white" opacity="0.9"/>
        <text x="18" y="23" text-anchor="middle" font-size="13" font-family="sans-serif">🛵</text>
    </svg>`;
    return L.divIcon({ html: svg, iconSize: [36, 44], iconAnchor: [18, 44], popupAnchor: [0, -44], className: '' });
}

function hubIcon() {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
        <circle cx="16" cy="16" r="14" fill="#e8002d" stroke="white" stroke-width="2"/>
        <text x="16" y="21" text-anchor="middle" font-size="14" font-family="sans-serif">🏭</text>
    </svg>`;
    return L.divIcon({ html: svg, iconSize: [32, 32], iconAnchor: [16, 16], className: '' });
}

// ─── State ────────────────────────────────────────────────────────────────────
let markers       = {};
let routeLines    = {};
let activeCard    = null;
let demoMode      = false;
let demoInterval  = null;

// ─── DEMO DATA (shown when no real GPS data exists) ───────────────────────────
const demoRiders = [
    { id:'DEMO1', name:'Carlos Mendoza', vehicle:'Motorcycle', hub:'Manila Hub',
      lat:14.5547, lng:121.0244, status:'online', active_parcels:3,
      updated_at:'Just now', dest_lat:14.5700, dest_lng:121.0050 },
    { id:'DEMO2', name:'Ana Reyes',      vehicle:'Bicycle',    hub:'QC Hub',
      lat:14.6042, lng:121.0200, status:'online', active_parcels:1,
      updated_at:'2m ago',  dest_lat:14.5900, dest_lng:121.0300 },
    { id:'DEMO3', name:'Mark Santos',    vehicle:'Van',        hub:'Pasig Hub',
      lat:14.5795, lng:121.0750, status:'idle',   active_parcels:2,
      updated_at:'8m ago',  dest_lat:14.5600, dest_lng:121.0500 },
    { id:'DEMO4', name:'Joy Lim',        vehicle:'Motorcycle', hub:'Makati Hub',
      lat:14.5553, lng:121.0154, status:'idle',   active_parcels:0,
      updated_at:'18m ago', dest_lat:14.5400, dest_lng:121.0200 },
];

// ─── Fetch & Render ───────────────────────────────────────────────────────────
async function loadRiders() {
    try {
        const res  = await fetch('/ninjavan/api/rider_positions.php?_=' + Date.now());
        const data = await res.json();

        if (!Array.isArray(data) || data.length === 0) {
            if (!demoMode) activateDemoMode();
            return;
        }

        demoMode = false;
        if (demoInterval) { clearInterval(demoInterval); demoInterval = null; }
        hideDemoBanner();
        renderRiders(data);
        updateStats(data);
    } catch(e) {
        console.warn('Failed to load rider positions', e);
        if (!demoMode) activateDemoMode();
    }
}

function activateDemoMode() {
    demoMode = true;
    showDemoBanner();
    renderRiders(demoRiders, true);
    updateStats(demoRiders);

    const speeds = { DEMO1: [0.0003, -0.0002], DEMO2: [-0.0002, 0.0003],
                     DEMO3: [0.0001, -0.0003],  DEMO4: [0.0002, 0.0002] };
    demoInterval = setInterval(() => {
        demoRiders.forEach(r => {
            const [dlat, dlng] = speeds[r.id];
            r.lat += dlat + (Math.random() - 0.5) * 0.0001;
            r.lng += dlng + (Math.random() - 0.5) * 0.0001;
            if (markers[r.id]) {
                markers[r.id].setLatLng([r.lat, r.lng]);
                if (routeLines[r.id]) {
                    routeLines[r.id].setLatLngs([[r.lat, r.lng], [r.dest_lat, r.dest_lng]]);
                }
            }
        });
    }, 2000);
}

function showDemoBanner() {
    if (document.getElementById('demoBanner')) return;
    const b = document.createElement('div');
    b.id = 'demoBanner';
    b.style.cssText = 'background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:10px;';
    // FIX #5: Clearer demo banner — tells user the RIDER_GPS table is missing, not migrate_notifications
    b.innerHTML = `<i class="bi bi-lightning-charge-fill" style="font-size:18px;"></i>
        <span><strong>Demo Mode</strong> — The <code style="background:rgba(0,0,0,0.2);padding:1px 5px;border-radius:3px;">RIDER_GPS</code> table has no data yet. Riders must share their GPS location for real tracking to appear. Riders shown below are simulated.</span>`;
    document.querySelector('.page-header').after(b);
}

function hideDemoBanner() {
    const b = document.getElementById('demoBanner');
    if (b) b.remove();
}

function renderRiders(riders, showRoute = false) {
    const listEl = document.getElementById('riderListBody');

    if (!riders || riders.length === 0) {
        listEl.innerHTML = `<div style="padding:30px;text-align:center;color:var(--muted);font-size:13px;">
            <i class="bi bi-geo" style="font-size:26px;display:block;margin-bottom:8px;"></i>
            No active riders with GPS data
        </div>`;
        return;
    }

    const currentIds = riders.map(r => r.id);
    Object.keys(markers).forEach(id => {
        if (!currentIds.includes(id)) {
            map.removeLayer(markers[id]);
            delete markers[id];
            if (routeLines[id]) { map.removeLayer(routeLines[id]); delete routeLines[id]; }
        }
    });

    let html = '';
    riders.forEach(r => {
        const initials = r.name.split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase();
        html += `
        <div class="rider-card" id="card-${r.id}" onclick="focusRider('${r.id}', ${r.lat}, ${r.lng})">
            <div class="rider-card-top">
                <div class="rider-avatar-sm">${initials}</div>
                <div>
                    <div class="rider-card-name">${r.name}</div>
                    <div class="rider-card-vehicle">${r.vehicle}</div>
                </div>
                <span class="status-dot ${r.status}${r.status==='online'?' pulse-dot':''}" title="${r.status}"></span>
            </div>
            <div class="rider-card-meta">
                <span><i class="bi bi-building"></i> ${r.hub}</span>
                <span><i class="bi bi-box-seam"></i> ${r.active_parcels} parcel${r.active_parcels!==1?'s':''}</span>
                <span><i class="bi bi-clock"></i> ${r.updated_at}</span>
            </div>
        </div>`;

        const popupHtml = `
            <div style="padding:6px 2px;min-width:180px;">
                <div class="nv-popup-title">${r.name}${demoMode?' <span style="font-size:10px;color:#f59e0b;">[DEMO]</span>':''}</div>
                <div class="nv-popup-row"><i class="bi bi-bicycle"></i> ${r.vehicle}</div>
                <div class="nv-popup-row"><i class="bi bi-building"></i> ${r.hub}</div>
                <div class="nv-popup-row"><i class="bi bi-box-seam"></i> ${r.active_parcels} active parcel${r.active_parcels!==1?'s':''}</div>
                <div class="nv-popup-row" style="margin-top:6px;padding-top:6px;border-top:1px solid #eee;">
                    <span style="width:7px;height:7px;display:inline-block;border-radius:50%;background:${r.status==='online'?'#00b37d':r.status==='idle'?'#f59e0b':'#aaa'};"></span>
                    <span style="text-transform:capitalize;">${r.status}</span>
                    &nbsp;·&nbsp; ${r.updated_at}
                </div>
            </div>`;

        if (markers[r.id]) {
            markers[r.id].setLatLng([r.lat, r.lng]);
            markers[r.id].setIcon(riderIcon(r.status));
            markers[r.id].getPopup().setContent(popupHtml);
        } else {
            markers[r.id] = L.marker([r.lat, r.lng], { icon: riderIcon(r.status) })
                .addTo(map)
                .bindPopup(popupHtml, { maxWidth: 240 });
        }

        if (r.dest_lat && r.dest_lng && r.active_parcels > 0) {
            const lineCoords = [[r.lat, r.lng], [r.dest_lat, r.dest_lng]];
            if (routeLines[r.id]) {
                routeLines[r.id].setLatLngs(lineCoords);
            } else {
                routeLines[r.id] = L.polyline(lineCoords, {
                    color: r.status === 'online' ? '#00b37d' : '#f59e0b',
                    weight: 2,
                    dashArray: '6, 6',
                    opacity: 0.6,
                }).addTo(map);
            }
        }
    });

    listEl.innerHTML = html;
}

function updateStats(riders) {
    document.getElementById('statTotal').textContent   = riders.length;
    document.getElementById('statOnline').textContent  = riders.filter(r => r.status === 'online').length;
    document.getElementById('statParcels').textContent = riders.reduce((s, r) => s + (r.active_parcels || 0), 0);
}

function focusRider(id, lat, lng) {
    map.setView([lat, lng], 15, { animate: true });
    if (markers[id]) markers[id].openPopup();
    if (activeCard) document.getElementById('card-' + activeCard)?.classList.remove('active-card');
    document.getElementById('card-' + id)?.classList.add('active-card');
    activeCard = id;
}

function refreshMap() {
    const btn = document.getElementById('refreshBtn');
    btn.classList.add('spinning');
    // FIX: Also re-check map size on manual refresh in case layout shifted
    map.invalidateSize();
    loadRiders().finally(() => setTimeout(() => btn.classList.remove('spinning'), 500));
}

// ─── Auto-poll every 15 s ─────────────────────────────────────────────────────
loadRiders();
const pollInterval = setInterval(loadRiders, 15000);

let countdown = 15;
setInterval(() => {
    countdown--;
    if (countdown <= 0) countdown = 15;
    document.getElementById('liveLabel').textContent = `Live · refreshing in ${countdown}s`;
}, 1000);

window.addEventListener('beforeunload', () => {
    clearInterval(pollInterval);
    if (demoInterval) clearInterval(demoInterval);
});

// FIX: Close the DOMContentLoaded wrapper
});
</script>

<?php include "../layout/dashboard_footer.php"; ?>