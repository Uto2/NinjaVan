<?php
/**
 * rider/live_map.php
 * Enhanced with: route animation, hub→dest waypoints, sim controls,
 * destination pin, dashed planned route, solid travelled trail.
 */
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['account_id']) || !in_array($_SESSION['role'], ['rider','staff','admin'])) {
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Live Rider Map";
$activePage = "map";
$role       = $_SESSION['role'];

$extraHead = '
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
';

include "../layout/dashboard_layout.php";
?>

<style>
.map-wrap{display:grid;grid-template-columns:300px 1fr;gap:20px;min-height:560px;}
#map{border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);height:560px;width:100%;position:relative;}
.rider-list-panel{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;}
.rider-list-header{padding:16px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--surface);}
.rider-list-header h3{font-size:14px;font-weight:700;margin:0;}
.rider-list-body{flex:1;overflow-y:auto;padding:10px;}
.rider-card{padding:12px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);margin-bottom:8px;cursor:pointer;transition:var(--trans);background:var(--surface-2);}
.rider-card:hover,.rider-card.active-card{border-color:var(--red);background:rgba(232,0,45,0.03);box-shadow:0 0 0 3px var(--red-glow);}
.rider-card-top{display:flex;align-items:center;gap:10px;margin-bottom:6px;}
.rider-avatar-sm{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--ink),var(--ink-3));display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Sora',sans-serif;font-weight:700;font-size:13px;flex-shrink:0;}
.rider-card-name{font-weight:600;font-size:13px;line-height:1.2;}
.rider-card-vehicle{font-size:11px;color:var(--muted);}
.status-dot{width:8px;height:8px;border-radius:50%;margin-left:auto;flex-shrink:0;}
.status-dot.online{background:var(--green);box-shadow:0 0 5px var(--green);}
.status-dot.idle{background:var(--amber);}
.status-dot.offline{background:var(--muted);}
.rider-card-meta{display:flex;gap:8px;font-size:11px;color:var(--muted);flex-wrap:wrap;}
.map-stat-bar{display:flex;gap:12px;margin-bottom:20px;}
.map-stat{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 16px;flex:1;text-align:center;box-shadow:var(--shadow);}
.map-stat-val{font-family:'Sora',sans-serif;font-size:22px;font-weight:800;color:var(--ink);}
.map-stat-lbl{font-size:11px;color:var(--muted);margin-top:2px;}
.pulse-dot{animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.4}}
.refresh-btn{width:28px;height:28px;border-radius:6px;border:1px solid var(--border);background:var(--surface-2);display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--muted);cursor:pointer;transition:var(--trans);}
.refresh-btn:hover{color:var(--red);border-color:var(--red);}
.refresh-btn.spinning i{animation:spin 0.6s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}
.gps-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:50px;border:1.5px solid var(--border);background:var(--surface-2);font-size:12px;font-weight:600;color:var(--muted);cursor:pointer;transition:var(--trans);}
.gps-btn.active{border-color:var(--green);color:var(--green);background:var(--green-soft);}
.gps-btn.error{border-color:#dc2626;color:#dc2626;background:rgba(220,38,38,0.05);}
.gps-dot{width:8px;height:8px;border-radius:50%;background:currentColor;}
.gps-dot.pulse{animation:pulse 1.5s infinite;}
.route-legend{display:flex;gap:14px;margin-bottom:12px;flex-wrap:wrap;align-items:center;}
.legend-item{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted);}
.legend-dot-sm{width:10px;height:10px;border-radius:50%;}
.sim-controls{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:12px;}
.sim-btn{padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:var(--surface-2);font-size:11px;font-weight:600;color:var(--muted);cursor:pointer;transition:var(--trans);}
.sim-btn:hover{border-color:var(--red);color:var(--red);}
.sim-btn.active{border-color:var(--blue);color:var(--blue);background:var(--blue-soft);}
.leaflet-popup-content-wrapper{border-radius:var(--radius-sm)!important;box-shadow:var(--shadow-lg)!important;border:1px solid var(--border)!important;font-family:'DM Sans',sans-serif!important;}
.nv-popup-title{font-family:'Sora',sans-serif;font-weight:700;font-size:14px;margin-bottom:4px;}
.nv-popup-row{display:flex;align-items:center;gap:6px;font-size:12px;color:#555;margin-top:3px;}
@media(max-width:768px){.map-wrap{grid-template-columns:1fr;grid-template-rows:auto 400px;}.rider-list-panel{max-height:220px;}}
</style>

<div class="page-header">
  <div>
    <h1><i class="bi bi-geo-alt-fill" style="color:var(--red);"></i> Live Rider Map</h1>
    <p>Real-time rider positions + simulated delivery routes — updates every 15 seconds</p>
  </div>
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <?php if ($role === 'rider'): ?>
    <button class="gps-btn" id="gpsBtn" onclick="toggleGPS()">
      <span class="gps-dot" id="gpsDot"></span><span id="gpsLabel">Share my location</span>
    </button>
    <?php endif; ?>
    <span class="status-dot pulse-dot online" style="width:10px;height:10px;"></span>
    <span id="liveLabel" style="font-size:13px;color:var(--muted);">Live</span>
  </div>
</div>

<div class="map-stat-bar">
  <div class="map-stat"><div class="map-stat-val" id="statTotal">—</div><div class="map-stat-lbl">Active Riders</div></div>
  <div class="map-stat"><div class="map-stat-val" id="statOnline" style="color:var(--green);">—</div><div class="map-stat-lbl">Online (last 5 min)</div></div>
  <div class="map-stat"><div class="map-stat-val" id="statParcels" style="color:var(--red);">—</div><div class="map-stat-lbl">Parcels In Transit</div></div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
  <div class="route-legend">
    <div class="legend-item"><div class="legend-dot-sm" style="background:#e8002d;"></div> Hub</div>
    <div class="legend-item"><div class="legend-dot-sm" style="background:#00b37d;"></div> Online rider</div>
    <div class="legend-item"><div class="legend-dot-sm" style="background:#f59e0b;"></div> Idle rider</div>
    <div class="legend-item">
      <svg width="24" height="8"><line x1="0" y1="4" x2="24" y2="4" stroke="#3b82f6" stroke-width="2" stroke-dasharray="5,4"/></svg>
      Planned route
    </div>
    <div class="legend-item">
      <svg width="24" height="8"><line x1="0" y1="4" x2="24" y2="4" stroke="#00b37d" stroke-width="3"/></svg>
      Travelled
    </div>
    <div class="legend-item"><div class="legend-dot-sm" style="background:#3b82f6;border-radius:3px;"></div> Destination</div>
  </div>
  <div class="sim-controls" id="simControls" style="display:none;">
    <span style="font-size:11px;color:var(--muted);font-weight:600;">Simulation:</span>
    <button class="sim-btn active" id="simPlayBtn"  onclick="simPlay()"><i class="bi bi-play-fill"></i> Play</button>
    <button class="sim-btn"        id="simPauseBtn" onclick="simPause()"><i class="bi bi-pause-fill"></i> Pause</button>
    <button class="sim-btn"        onclick="simReset()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
    <select id="simSpeed" onchange="simSetSpeed(this.value)" class="sim-btn" style="cursor:pointer;">
      <option value="1">1× speed</option>
      <option value="2">2× speed</option>
      <option value="5">5× speed</option>
    </select>
  </div>
</div>

<div class="map-wrap">
  <div class="rider-list-panel">
    <div class="rider-list-header">
      <h3><i class="bi bi-bicycle"></i> Riders</h3>
      <button class="refresh-btn" id="refreshBtn" onclick="refreshMap()" title="Refresh">
        <i class="bi bi-arrow-clockwise"></i>
      </button>
    </div>
    <div class="rider-list-body" id="riderListBody">
      <div style="padding:30px;text-align:center;color:var(--muted);font-size:13px;">
        <i class="bi bi-arrow-clockwise" style="font-size:24px;display:block;margin-bottom:8px;"></i>Loading riders...
      </div>
    </div>
  </div>
  <div id="map"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

const HUBS = [
  {key:'Manila',name:'Manila Hub',lat:14.5995,lng:120.9842},
  {key:'Cebu',  name:'Cebu Hub',  lat:10.3157,lng:123.8854},
  {key:'Davao', name:'Davao Hub', lat: 7.1907,lng:125.4553},
];

const map = L.map('map',{center:[12.0,122.5],zoom:6,zoomControl:true});
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
  attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',maxZoom:19
}).addTo(map);
setTimeout(()=>map.invalidateSize(),250);

// Hub pins
const hubSvg=`<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 34 34"><circle cx="17" cy="17" r="15" fill="#e8002d" stroke="white" stroke-width="2.5"/><text x="17" y="22" text-anchor="middle" font-size="14" font-family="sans-serif">&#x1F3ED;</text></svg>`;
const hubIcon=L.divIcon({html:hubSvg,iconSize:[34,34],iconAnchor:[17,17],className:''});
HUBS.forEach(h=>{ L.marker([h.lat,h.lng],{icon:hubIcon}).addTo(map).bindPopup(`<div style="padding:4px 2px;min-width:130px;"><div style="font-family:'Sora',sans-serif;font-weight:700;font-size:13px;">&#x1F3ED; ${h.name}</div><div style="font-size:12px;color:#666;">${h.key} Branch</div></div>`); });

function riderIcon(status){
  const c={online:'#00b37d',idle:'#f59e0b',offline:'#8a8580'}[status]||'#8a8580';
  return L.divIcon({html:`<svg xmlns="http://www.w3.org/2000/svg" width="38" height="46" viewBox="0 0 38 46"><filter id="ds"><feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-opacity="0.28"/></filter><ellipse cx="19" cy="42" rx="8" ry="3" fill="rgba(0,0,0,0.15)"/><path d="M19 0C8.5 0 0 8.5 0 19C0 32 19 46 19 46C19 46 38 32 38 19C38 8.5 29.5 0 19 0Z" fill="${c}" filter="url(#ds)"/><circle cx="19" cy="19" r="11" fill="white" opacity="0.92"/><text x="19" y="24" text-anchor="middle" font-size="14" font-family="sans-serif">&#x1F6F5;</text></svg>`,iconSize:[38,46],iconAnchor:[19,46],popupAnchor:[0,-46],className:''});
}
function destIcon(){
  return L.divIcon({html:`<svg xmlns="http://www.w3.org/2000/svg" width="32" height="38" viewBox="0 0 32 38"><path d="M16 0C7 0 0 7 0 16C0 26.5 16 38 16 38C16 38 32 26.5 32 16C32 7 25 0 16 0Z" fill="#3b82f6"/><circle cx="16" cy="16" r="9" fill="white" opacity="0.92"/><text x="16" y="21" text-anchor="middle" font-size="12" font-family="sans-serif">&#x1F4E6;</text></svg>`,iconSize:[32,38],iconAnchor:[16,38],popupAnchor:[0,-38],className:''});
}

let markers={},routeLines={},travelledLines={},destMarkers={},simStates={};
let activeCard=null,demoMode=false,demoInterval=null;

const DEMO_RIDERS=[
  {id:'DEMO1',name:'Carlos Mendoza',vehicle:'Motorcycle',hub:'Manila',lat:14.5547,lng:121.0244,status:'online',active_parcels:3,updated_at:'Just now',rcpt_area:'Metro Manila'},
  {id:'DEMO2',name:'Ana Reyes',vehicle:'Bicycle',hub:'Manila',lat:14.6042,lng:121.0200,status:'online',active_parcels:1,updated_at:'2m ago',rcpt_area:'Luzon'},
  {id:'DEMO3',name:'Mark Santos',vehicle:'Van',hub:'Cebu',lat:10.3200,lng:123.8950,status:'idle',active_parcels:2,updated_at:'8m ago',rcpt_area:'Visayas'},
  {id:'DEMO4',name:'Joy Lim',vehicle:'Motorcycle',hub:'Davao',lat:7.1950,lng:125.4700,status:'idle',active_parcels:0,updated_at:'18m ago',rcpt_area:'Mindanao'},
];

async function loadRiders(){
  try{
    const res=await fetch('/ninjavan/api/rider_positions.php?_='+Date.now());
    const data=await res.json();
    if(!Array.isArray(data)||data.length===0){if(!demoMode)activateDemoMode();return;}
    demoMode=false;if(demoInterval){clearInterval(demoInterval);demoInterval=null;}hideDemoBanner();
    await renderRiders(data);updateStats(data);
  }catch(e){console.warn(e);if(!demoMode)activateDemoMode();}
}

function activateDemoMode(){
  demoMode=true;showDemoBanner();
  renderRiders(DEMO_RIDERS).then(()=>{
    const d={DEMO1:[0.0003,-0.0002],DEMO2:[-0.0002,0.0003],DEMO3:[0.0001,-0.0003],DEMO4:[0.0002,0.0002]};
    demoInterval=setInterval(()=>{DEMO_RIDERS.forEach(r=>{r.lat+=d[r.id][0]+(Math.random()-0.5)*0.00008;r.lng+=d[r.id][1]+(Math.random()-0.5)*0.00008;if(markers[r.id])markers[r.id].setLatLng([r.lat,r.lng]);});},2000);
  });
  updateStats(DEMO_RIDERS);
}

function showDemoBanner(){
  if(document.getElementById('demoBanner'))return;
  const b=document.createElement('div');b.id='demoBanner';
  b.style.cssText='background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:10px;';
  b.innerHTML='<i class="bi bi-lightning-charge-fill" style="font-size:18px;"></i><span><strong>Demo Mode</strong> \u2014 No GPS data in RIDER_GPS yet. Routes shown are simulated. Tap <strong>Share my location</strong> to push real data.</span>';
  document.querySelector('.page-header').after(b);
}
function hideDemoBanner(){const b=document.getElementById('demoBanner');if(b)b.remove();}

async function renderRiders(riders){
  const el=document.getElementById('riderListBody');
  if(!riders||riders.length===0){el.innerHTML='<div style="padding:30px;text-align:center;color:var(--muted);font-size:13px;"><i class="bi bi-geo" style="font-size:26px;display:block;margin-bottom:8px;"></i>No active riders</div>';return;}
  const cur=riders.map(r=>r.id);
  Object.keys(markers).forEach(id=>{if(!cur.includes(id))cleanupRider(id);});
  let html='';
  riders.forEach(r=>{
    const ini=r.name.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
    html+=`<div class="rider-card" id="card-${r.id}" onclick="focusRider('${r.id}',${r.lat},${r.lng})">
      <div class="rider-card-top">
        <div class="rider-avatar-sm">${ini}</div>
        <div><div class="rider-card-name">${r.name}</div><div class="rider-card-vehicle">${r.vehicle}</div></div>
        <span class="status-dot ${r.status}${r.status==='online'?' pulse-dot':''}" title="${r.status}"></span>
      </div>
      <div class="rider-card-meta">
        <span><i class="bi bi-building"></i> ${r.hub}</span>
        <span><i class="bi bi-box-seam"></i> ${r.active_parcels} parcel${r.active_parcels!==1?'s':''}</span>
        <span><i class="bi bi-clock"></i> ${r.updated_at}</span>
      </div>
      ${r.active_parcels>0?'<div style="margin-top:6px;"><span style="font-size:10px;font-weight:700;color:var(--blue);background:var(--blue-soft);padding:2px 7px;border-radius:4px;"><i class="bi bi-broadcast"></i> Route active</span></div>':''}
    </div>`;
  });
  el.innerHTML=html;
  await Promise.all(riders.map(r=>placeRiderOnMap(r)));
  document.getElementById('simControls').style.display=demoMode?'flex':'none';
}

async function placeRiderOnMap(r){
  const popup=buildPopup(r);
  if(markers[r.id]){markers[r.id].setLatLng([r.lat,r.lng]).setIcon(riderIcon(r.status));markers[r.id].getPopup().setContent(popup);}
  else{markers[r.id]=L.marker([r.lat,r.lng],{icon:riderIcon(r.status)}).addTo(map).bindPopup(popup,{maxWidth:240});}
  if(r.active_parcels>0) await drawRoute(r);
  else{if(routeLines[r.id]){map.removeLayer(routeLines[r.id]);delete routeLines[r.id];}if(destMarkers[r.id]){map.removeLayer(destMarkers[r.id]);delete destMarkers[r.id];}}
}

async function drawRoute(r){
  const hub=HUBS.find(h=>r.hub&&r.hub.toLowerCase().includes(h.key.toLowerCase()))||HUBS[0];
  const area=r.rcpt_area||'Metro Manila';
  try{
    const res=await fetch('/ninjavan/api/geocode_area.php?area='+encodeURIComponent(area)+'&hub='+encodeURIComponent(hub.key)+'&seed='+encodeURIComponent(r.id)+'&_='+Date.now());
    const geo=await res.json();
    // Destination pin
    if(destMarkers[r.id])destMarkers[r.id].setLatLng([geo.lat,geo.lng]);
    else destMarkers[r.id]=L.marker([geo.lat,geo.lng],{icon:destIcon()}).addTo(map)
      .bindPopup('<div style="padding:4px;min-width:150px;"><div style="font-family:\'Sora\',sans-serif;font-weight:700;font-size:13px;">&#x1F4E6; Delivery destination</div><div style="font-size:12px;color:#666;">'+geo.zone+' \u00b7 '+geo.area+'</div><div style="font-size:11px;color:#999;margin-top:2px;">Rider: '+r.name+'</div></div>');
    // Planned route (dashed blue)
    const rCoords=geo.waypoints.map(w=>[w[0],w[1]]);
    if(routeLines[r.id])routeLines[r.id].setLatLngs(rCoords);
    else routeLines[r.id]=L.polyline(rCoords,{color:'#3b82f6',weight:2.5,dashArray:'8,8',opacity:0.45}).addTo(map);
    // Travelled trail (solid)
    const trail=[[geo.hub_lat,geo.hub_lng],[r.lat,r.lng]];
    if(travelledLines[r.id])travelledLines[r.id].setLatLngs(trail);
    else travelledLines[r.id]=L.polyline(trail,{color:r.status==='online'?'#00b37d':'#f59e0b',weight:3,opacity:0.7}).addTo(map);
    // Simulation
    if(demoMode&&!simStates[r.id]) startSim(r.id,geo.waypoints,hub);
  }catch(e){console.warn('Route failed',r.id,e);}
}

// Simulation engine
function startSim(rid,waypoints,hub){
  if(simStates[rid])stopSim(rid);
  simStates[rid]={waypoints,step:0,paused:false,speed:parseInt(document.getElementById('simSpeed')?.value||'1'),interval:null,hub};
  runSim(rid);
}
function runSim(rid){
  const s=simStates[rid];if(!s||s.paused)return;
  const tick=Math.max(300,1800/s.speed);
  s.interval=setInterval(()=>{
    if(!simStates[rid]||s.paused){clearInterval(s.interval);return;}
    if(s.step>=s.waypoints.length)s.step=0;
    const[lat,lng]=s.waypoints[s.step];s.step++;
    if(markers[rid])markers[rid].setLatLng([lat,lng]);
    if(travelledLines[rid]){
      const trail=[[s.hub.lat,s.hub.lng],...s.waypoints.slice(0,s.step).map(w=>[w[0],w[1]])];
      travelledLines[rid].setLatLngs(trail);
    }
  },tick);
}
function stopSim(rid){if(simStates[rid]?.interval)clearInterval(simStates[rid].interval);delete simStates[rid];}
function simPlay(){Object.keys(simStates).forEach(id=>{simStates[id].paused=false;if(simStates[id].interval)clearInterval(simStates[id].interval);runSim(id);});document.getElementById('simPlayBtn').classList.add('active');document.getElementById('simPauseBtn').classList.remove('active');}
function simPause(){Object.keys(simStates).forEach(id=>{simStates[id].paused=true;if(simStates[id].interval)clearInterval(simStates[id].interval);});document.getElementById('simPauseBtn').classList.add('active');document.getElementById('simPlayBtn').classList.remove('active');}
function simReset(){Object.keys(simStates).forEach(id=>{simStates[id].step=0;});simPlay();}
function simSetSpeed(v){const s=parseInt(v);Object.keys(simStates).forEach(id=>{simStates[id].speed=s;if(!simStates[id].paused){if(simStates[id].interval)clearInterval(simStates[id].interval);runSim(id);}});}

function buildPopup(r){
  const sc=r.status==='online'?'#00b37d':r.status==='idle'?'#f59e0b':'#aaa';
  return `<div style="padding:6px 2px;min-width:185px;"><div class="nv-popup-title">${r.name}${demoMode?' <span style="font-size:10px;color:#f59e0b;">[DEMO]</span>':''}</div><div class="nv-popup-row"><i class="bi bi-bicycle"></i> ${r.vehicle}</div><div class="nv-popup-row"><i class="bi bi-building"></i> ${r.hub}</div><div class="nv-popup-row"><i class="bi bi-box-seam"></i> ${r.active_parcels} active parcel${r.active_parcels!==1?'s':''}</div>${r.rcpt_area?`<div class="nv-popup-row"><i class="bi bi-geo-alt"></i> \u2192 ${r.rcpt_area}</div>`:''}<div class="nv-popup-row" style="margin-top:6px;padding-top:6px;border-top:1px solid #eee;"><span style="width:7px;height:7px;display:inline-block;border-radius:50%;background:${sc};"></span><span style="text-transform:capitalize;">${r.status}</span> &nbsp;&middot;&nbsp; ${r.updated_at}</div></div>`;
}

function cleanupRider(id){stopSim(id);[['markers',markers],['routeLines',routeLines],['travelledLines',travelledLines],['destMarkers',destMarkers]].forEach(([k,store])=>{if(store[id]){map.removeLayer(store[id]);delete store[id];}});}
function updateStats(riders){document.getElementById('statTotal').textContent=riders.length;document.getElementById('statOnline').textContent=riders.filter(r=>r.status==='online').length;document.getElementById('statParcels').textContent=riders.reduce((s,r)=>s+(r.active_parcels||0),0);}
function focusRider(id,lat,lng){map.setView([lat,lng],14,{animate:true});if(markers[id])markers[id].openPopup();if(activeCard)document.getElementById('card-'+activeCard)?.classList.remove('active-card');document.getElementById('card-'+id)?.classList.add('active-card');activeCard=id;}
async function refreshMap(){const btn=document.getElementById('refreshBtn');btn.classList.add('spinning');map.invalidateSize();await loadRiders();setTimeout(()=>btn.classList.remove('spinning'),500);}

let gpsWatchId=null,gpsPushTimer=null,lastLat=null,lastLng=null;
function toggleGPS(){gpsWatchId!==null?stopGPS():startGPS();}
function startGPS(){if(!navigator.geolocation){setGPSState('error','GPS not supported');return;}setGPSState('loading','Getting location...');gpsWatchId=navigator.geolocation.watchPosition(pos=>{lastLat=pos.coords.latitude;lastLng=pos.coords.longitude;setGPSState('active','Sharing location');pushGPS();},()=>{setGPSState('error','Location denied');stopGPS();},{enableHighAccuracy:true,maximumAge:10000,timeout:15000});gpsPushTimer=setInterval(()=>{if(lastLat)pushGPS();},30000);}
function stopGPS(){if(gpsWatchId!==null){navigator.geolocation.clearWatch(gpsWatchId);gpsWatchId=null;}if(gpsPushTimer){clearInterval(gpsPushTimer);gpsPushTimer=null;}lastLat=null;lastLng=null;setGPSState('idle','Share my location');}
function setGPSState(s,l){const b=document.getElementById('gpsBtn'),d=document.getElementById('gpsDot'),lb=document.getElementById('gpsLabel');if(!b)return;b.className='gps-btn'+(s==='active'?' active':s==='error'?' error':'');d.className='gps-dot'+(s==='active'?' pulse':'');lb.textContent=l;}
async function pushGPS(){if(!lastLat||!lastLng)return;const fd=new FormData();fd.append('lat',lastLat);fd.append('lng',lastLng);try{await fetch('/ninjavan/api/rider_positions.php',{method:'POST',body:fd});}catch(e){console.warn(e);}}

loadRiders();
const pollInterval=setInterval(loadRiders,15000);
let countdown=15;
setInterval(()=>{countdown--;if(countdown<=0)countdown=15;document.getElementById('liveLabel').textContent='Live \u00b7 refreshing in '+countdown+'s';},1000);
window.addEventListener('beforeunload',()=>{clearInterval(pollInterval);if(demoInterval)clearInterval(demoInterval);Object.keys(simStates).forEach(id=>stopSim(id));stopGPS();});

window.focusRider=focusRider;window.refreshMap=refreshMap;window.toggleGPS=toggleGPS;
window.simPlay=simPlay;window.simPause=simPause;window.simReset=simReset;window.simSetSpeed=simSetSpeed;

}); // end DOMContentLoaded
</script>

<?php include "../layout/dashboard_footer.php"; ?>