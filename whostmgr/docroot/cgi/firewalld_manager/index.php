#!/usr/local/cpanel/3rdparty/bin/php -q
<?php
/**
 * WHM/cPanel — Firewalld Manager UI (single file)
 * Works with api.php → fwctl.sh (+ zone-inspect via firewall-cmd)
 */
header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
/* Allow inline JS + CSS because this page uses inline <script> and onclick= */
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'");

$api = 'api.php'; // relative keeps the /cpsess##### token path intact

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Firewalld Manager</title>
  <style>
    :root{--bg:#0b1426;--fg:#e5eaf3;--bord:#22314d;}
    body { font: 14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial,"Noto Sans",sans-serif; margin:0; padding: 22px; color:#111; }
    h1 { margin: 0 0 12px; }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(360px,1fr)); gap:14px; }
    .card { border:1px solid #ddd; border-radius:12px; background:#fff; padding:14px; }
    .row { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .stack{ display:flex; flex-direction:column; gap:10px; }
    .btn { padding:8px 12px; border:1px solid #444; border-radius:8px; cursor:pointer; background:#f6f6f6; }
    .btn:active{ transform: translateY(1px); }
    input, select { padding:6px 8px; border-radius:8px; border:1px solid #ccc; }
    input[type="text"]{ min-width:140px; }
    pre { background:var(--bg); color:var(--fg); border:1px solid var(--bord); padding:12px; border-radius:10px; white-space:pre-wrap; word-break:break-word; }
    table { width:100%; border-collapse: collapse; }
    th, td{ text-align:left; border-bottom:1px solid #eee; padding:6px 8px; }
    .muted{ color:#666; }
    .pill{ font-size:12px; padding:2px 8px; border-radius:999px; background:#eef; border:1px solid #99c; }
    .divider{ height:1px; background:#eee; margin:8px 0; }
    .right{ margin-left:auto; }
  </style>
</head>
<body>
  <h1>🔥 Firewalld Manager</h1>
  <p class="muted">Manage zones, services, ports, sources, interfaces, rich rules, ICMP blocks, panic mode, and the firewalld service via <code>fwctl.sh</code>.</p>

  <div class="grid">
    <!-- Service / Status -->
    <div class="card">
      <div class="row"><strong>Daemon & Status</strong><span class="pill">systemd</span><span class="right muted" id="default_zone_label"></span></div>
      <div class="row" style="margin-top:8px;">
        <button class="btn" onclick="svc('status')">Status</button>
        <button class="btn" onclick="svc('start')">Start</button>
        <button class="btn" onclick="svc('stop')">Stop</button>
        <button class="btn" onclick="svc('restart')">Restart</button>
        <button class="btn" onclick="svc('reload')">Reload</button>
        <button class="btn" onclick="svc('enable')">Enable</button>
        <button class="btn" onclick="svc('disable')">Disable</button>
        <button class="btn" onclick="loadStatus()">Refresh JSON</button>
      </div>
      <div class="divider"></div>
      <pre id="status_pre">Click “Refresh JSON”</pre>
    </div>

    <!-- Panic Mode -->
    <div class="card">
      <div class="row"><strong>Panic Mode</strong><span class="pill">blocks all traffic</span></div>
      <div class="row" style="margin-top:8px;">
        <button class="btn" onclick="panic('on')">Panic ON</button>
        <button class="btn" onclick="panic('off')">Panic OFF</button>
      </div>
      <div class="divider"></div>
      <pre id="panic_out">Use the buttons above to toggle.</pre>
    </div>

    <!-- Zones: info / CRUD / default -->
    <div class="card">
      <div class="row"><strong>Zones</strong><span class="pill">info / create / delete / default</span></div>
      <div class="stack" style="margin-top:8px;">
        <div class="row">
          <label><b>Inspect</b> zone</label>
          <input id="zi_zone" type="text" placeholder="public">
          <button class="btn" onclick="zoneInfo()">Load Zone</button>
          <button class="btn" onclick="useDefaultZone()">Use Default</button>
        </div>
        <div class="row"><label>Create</label><input id="zone_new" type="text" placeholder="example"><button class="btn" onclick="createZone()">Create</button></div>
        <div class="row"><label>Delete</label><input id="zone_del" type="text" placeholder="example"><button class="btn" onclick="deleteZone()">Delete</button></div>
        <div class="row"><label>Set default</label><input id="zone_def" type="text" placeholder="public"><button class="btn" onclick="setDefaultZone()">Apply</button></div>
      </div>
      <div class="divider"></div>
      <pre id="zones_out">Use actions above.</pre>
    </div>

    <!-- Zone details -->
    <div class="card">
      <div class="row"><strong>Zone Details</strong><span class="pill" id="zone_details_tag">zone: (none)</span></div>
      <div id="zone_details" class="stack" style="margin-top:10px;">
        <div class="muted">Load a zone to see runtime/permanent services, ports, sources, interfaces, and rich rules.</div>
      </div>
    </div>

    <!-- Services -->
    <div class="card">
      <div class="row"><strong>Services</strong><span class="pill">add / remove / catalog</span></div>
      <div class="row" style="margin-top:8px;">
        <label>Zone</label><input id="svc_zone" type="text" placeholder="public">
        <label>Service</label><input id="svc_name" type="text" placeholder="http">
        <label>Permanent</label>
        <select id="svc_perm"><option value="no">no (runtime)</option><option value="yes">yes (permanent)</option></select>
        <button class="btn" onclick="addService()">Add</button>
        <button class="btn" onclick="removeService()">Remove</button>
        <button class="btn" onclick="listServices()">List Known</button>
      </div>
      <div class="divider"></div>
      <div id="services_list"></div>
      <pre id="services_out" style="margin-top:6px;"></pre>
    </div>

    <!-- Ports -->
    <div class="card">
      <div class="row"><strong>Ports</strong><span class="pill">add / remove</span></div>
      <div class="row" style="margin-top:8px;">
        <label>Zone</label><input id="port_zone" type="text" placeholder="public">
        <label>Port/Proto</label><input id="port_spec" type="text" placeholder="8080/tcp">
        <label>Permanent</label>
        <select id="port_perm"><option value="no">no (runtime)</option><option value="yes">yes (permanent)</option></select>
        <button class="btn" onclick="addPort()">Add</button>
        <button class="btn" onclick="removePort()">Remove</button>
      </div>
      <div class="divider"></div>
      <pre id="ports_out">Use actions above.</pre>
    </div>

    <!-- Sources -->
    <div class="card">
      <div class="row"><strong>Sources</strong><span class="pill">add / remove</span></div>
      <div class="row" style="margin-top:8px;">
        <label>Zone</label><input id="src_zone" type="text" placeholder="public">
        <label>CIDR</label><input id="src_cidr" type="text" placeholder="203.0.113.0/24">
        <label>Permanent</label>
        <select id="src_perm"><option value="no">no (runtime)</option><option value="yes">yes (permanent)</option></select>
        <button class="btn" onclick="addSource()">Add</button>
        <button class="btn" onclick="removeSource()">Remove</button>
      </div>
      <div class="divider"></div>
      <pre id="sources_out">Use actions above.</pre>
    </div>

    <!-- Interfaces -->
    <div class="card">
      <div class="row"><strong>Interfaces</strong><span class="pill">add / remove / list</span></div>
      <div class="stack" style="margin-top:8px;">
        <div class="row">
          <label>Zone</label><input id="if_zone" type="text" placeholder="public">
          <label>Interface</label><input id="if_name" type="text" placeholder="eth0">
          <label>Permanent</label>
          <select id="if_perm"><option value="no">no (runtime)</option><option value="yes">yes (permanent)</option></select>
          <button class="btn" onclick="addInterface()">Add</button>
          <button class="btn" onclick="removeInterface()">Remove</button>
          <button class="btn" onclick="listInterfaces()">List System</button>
        </div>
        <div id="interfaces_list"></div>
      </div>
      <div class="divider"></div>
      <pre id="ifaces_out">Use actions above.</pre>
    </div>

    <!-- Rich Rules -->
    <div class="card">
      <div class="row"><strong>Rich Rules</strong><span class="pill">add / remove</span></div>
      <div class="stack" style="margin-top:8px;">
        <div class="row">
          <label>Zone</label><input id="rr_zone" type="text" placeholder="public">
          <label>Permanent</label>
          <select id="rr_perm"><option value="no">no (runtime)</option><option value="yes">yes (permanent)</option></select>
        </div>
        <div class="row" style="flex:1 1 100%">
          <label>Rule</label><input id="rr_text" type="text" style="flex:1" placeholder="rule family=ipv4 source address=198.51.100.0/24 port port=8080 protocol=tcp accept">
        </div>
        <div class="row">
          <button class="btn" onclick="addRichRule()">Add</button>
          <button class="btn" onclick="removeRichRule()">Remove</button>
        </div>
      </div>
      <div class="divider"></div>
      <pre id="rich_out">Use actions above.</pre>
    </div>

    <!-- ICMP Blocks -->
    <div class="card">
      <div class="row"><strong>ICMP Blocks</strong><span class="pill">list / add / remove</span></div>
      <div class="row" style="margin-top:8px;">
        <label>Action</label>
        <select id="icmp_op"><option value="list">list</option><option value="add">add</option><option value="remove">remove</option></select>
        <label>Type</label><input id="icmp_type" type="text" placeholder="echo-request">
        <button class="btn" onclick="icmpBlock()">Run</button>
      </div>
      <div class="divider"></div>
      <pre id="icmp_out">Use actions above.</pre>
    </div>
  </div>

  <div class="card" style="margin-top:14px;">
    <div class="row"><strong>Activity Log</strong><span class="muted right">latest first</span></div>
    <pre id="log"></pre>
  </div>

<script>
const API = <?= json_encode($api) ?>;

/* ----------------------- Utilities ----------------------- */
const el = id => document.getElementById(id);
const val = id => (el(id)?.value || '').trim();
const setText = (id, s) => { el(id).textContent = s ?? ''; };
const setHTML = (id, html) => { el(id).innerHTML = html ?? ''; };

function logLine(kind, msg){
  const pre = el('log');
  const ts = new Date().toLocaleString();
  pre.textContent = `[${ts}] ${kind.toUpperCase()}: ${msg}\n` + pre.textContent;
}

async function apiGET(action, params={}){
  const qs = new URLSearchParams(params);
  const url = API + '?action=' + encodeURIComponent(action) + (qs.toString() ? '&'+qs.toString() : '');
  const r = await fetch(url, {credentials:'same-origin'});
  const text = await r.text();
  return { ok:r.ok, status:r.status, text };
}
async function apiPOST(action, body={}){
  const url = API + '?action=' + encodeURIComponent(action);
  const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body), credentials:'same-origin' });
  const text = await r.text();
  return { ok:r.ok, status:r.status, text };
}
function tryJSON(t){ try{ return JSON.parse(t); }catch{ return null; } }
function prettyMaybe(t){ const o = tryJSON(t); return o ? JSON.stringify(o, null, 2) : t; }
function escapeHtml(s){ return (''+s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

/* ----------------------- Zone details (deduplicated) ----------------------- */
function uniq(arr){
  const out=[]; const seen=new Set();
  for (const x of (arr||[])){ const k=String(x); if(!seen.has(k)){ seen.add(k); out.push(k); } }
  return out;
}
function diffTriples(runtimeList, permanentList){
  const r = uniq(runtimeList||[]);
  const p = uniq(permanentList||[]);
  const pset = new Set(p.map(x=>x.toLowerCase()));
  const rset = new Set(r.map(x=>x.toLowerCase()));
  const shared = uniq(r.filter(x=>pset.has(x.toLowerCase())));
  const runtimeOnly = uniq(r.filter(x=>!pset.has(x.toLowerCase())));
  const permanentOnly = uniq(p.filter(x=>!rset.has(x.toLowerCase())));
  return { runtimeOnly, permanentOnly, shared };
}
function renderList(arr, empty='(none)'){
  if(!Array.isArray(arr) || !arr.length) return `<div class="muted">${empty}</div>`;
  return `<ul style="margin:0;padding-left:18px;">${arr.map(x=>`<li><code>${escapeHtml(x)}</code></li>`).join('')}</ul>`;
}
function renderZoneDetails(zoneName, jsonText){
  const raw = tryJSON(jsonText) || {};
  // Expect {runtime:{...}, permanent:{...}} from zone-inspect
  const rt = raw.runtime || {};
  const pm = raw.permanent || {};

  const cats = ['services','ports','sources','interfaces','rich_rules'];
  const rowsRuntimeOnly = [];
  const rowsPermanentOnly = [];
  const rowsShared = [];

  for (const c of cats){
    const {runtimeOnly, permanentOnly, shared} = diffTriples(rt[c]||[], pm[c]||[]);
    rowsRuntimeOnly.push(`<div style="margin:6px 0 0;">
      <div class="muted" style="margin:2px 0 4px;">${c.replace('_',' ')} (runtime-only)</div>
      ${renderList(runtimeOnly)}
    </div>`);
    rowsPermanentOnly.push(`<div style="margin:6px 0 0;">
      <div class="muted" style="margin:2px 0 4px;">${c.replace('_',' ')} (permanent-only)</div>
      ${renderList(permanentOnly)}
    </div>`);
    rowsShared.push(`<div style="margin:6px 0 0;">
      <div class="muted" style="margin:2px 0 4px;">${c.replace('_',' ')} (shared)</div>
      ${renderList(shared)}
    </div>`);
  }

  setHTML('zone_details',
    `<div class="stack">
       <div class="row"><strong>Runtime (unique)</strong><span class="pill">not saved</span></div>
       ${rowsRuntimeOnly.join('')}
       <div class="divider"></div>
       <div class="row"><strong>Permanent (unique)</strong><span class="pill">saved only</span></div>
       ${rowsPermanentOnly.join('')}
       <div class="divider"></div>
       <details open>
         <summary><strong>Shared (in both runtime & permanent)</strong></summary>
         ${rowsShared.join('')}
       </details>
       <div class="divider"></div>
       <details>
         <summary>Raw JSON</summary>
         <pre style="margin-top:8px;">${prettyMaybe(jsonText)}</pre>
       </details>
     </div>`
  );
  el('zone_details_tag').textContent = 'zone: ' + (zoneName || '(unknown)');
}

/* ----------------------- Actions ----------------------- */

/* Status & service */
async function loadStatus(){
  setText('status_pre','Loading…');
  const res = await apiGET('status-json');
  setText('status_pre', prettyMaybe(res.text));
  logLine(res.ok ? 'ok' : 'error', `status-json → ${res.status}`);
  const obj = tryJSON(res.text);
  let dz = obj?.default_zone || obj?.default || obj?.firewalld?.default_zone || '';
  if(typeof dz === 'string' && dz) el('default_zone_label').textContent = `default zone: ${dz}`;
}
async function svc(cmd){ const r = await apiPOST('service',{cmd}); logLine(r.ok?'ok':'error',`service ${cmd} → ${r.status}`); loadStatus(); }

/* Panic */
async function panic(mode){ const r = await apiPOST('panic',{mode}); setText('panic_out', prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`panic ${mode} → ${r.status}`); loadStatus(); }

/* Zones */
async function useDefaultZone(){ const m=(el('default_zone_label').textContent||'').match(/default zone:\s*(\S+)/i); el('zi_zone').value = m?m[1]:'public'; zoneInfo(); }
async function zoneInfo(){
  const zone = val('zi_zone')||'public';
  setText('zones_out','Loading info…');
  const r = await apiGET('zone-inspect', { zone });   // ← use explicit runtime/permanent endpoint
  setText('zones_out', prettyMaybe(r.text));
  renderZoneDetails(zone, r.text);
  logLine(r.ok?'ok':'error',`zone-inspect ${zone} → ${r.status}`);
}

/* Create/Delete/Default */
async function createZone(){ const zone=val('zone_new'); if(!zone) return alert('Enter zone'); const r=await apiPOST('create-zone',{zone}); setText('zones_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`create-zone ${zone} → ${r.status}`); zoneInfo(); }
async function deleteZone(){ const zone=val('zone_del'); if(!zone) return alert('Enter zone'); const r=await apiPOST('delete-zone',{zone}); setText('zones_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`delete-zone ${zone} → ${r.status}`); loadStatus(); }
async function setDefaultZone(){ const zone=val('zone_def')||'public'; const r=await apiPOST('set-default-zone',{zone}); setText('zones_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`set-default-zone ${zone} → ${r.status}`); loadStatus(); }

/* Services */
async function listServices(){
  setHTML('services_list','<div class="muted">Loading…</div>');
  const r=await apiGET('get-services-json');
  const o=tryJSON(r.text);
  if(Array.isArray(o)){
    setHTML('services_list','<table><thead><tr><th>Known service</th></tr></thead><tbody>'+o.map(s=>`<tr><td><code>${escapeHtml(s)}</code></td></tr>`).join('')+'</tbody></table>');
  } else {
    setHTML('services_list',`<pre>${prettyMaybe(r.text)}</pre>`);
  }
  logLine(r.ok?'ok':'error',`get-services-json → ${r.status}`);
}
async function addService(){ const zone=val('svc_zone')||'public'; const service=val('svc_name'); if(!service) return alert('Enter a service'); const permanent=el('svc_perm')?.value||'no'; const r=await apiPOST('add-service',{zone,service,permanent}); setText('services_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`add-service ${service}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }
async function removeService(){ const zone=val('svc_zone')||'public'; const service=val('svc_name'); if(!service) return alert('Enter a service'); const permanent=el('svc_perm')?.value||'no'; const r=await apiPOST('remove-service',{zone,service,permanent}); setText('services_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`remove-service ${service}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }

/* Ports */
async function addPort(){ const zone=val('port_zone')||'public'; const port=val('port_spec'); if(!port) return alert('Enter port spec like "8080/tcp"'); const permanent=el('port_perm')?.value||'no'; const r=await apiPOST('add-port',{zone,port,permanent}); setText('ports_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`add-port ${port}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }
async function removePort(){ const zone=val('port_zone')||'public'; const port=val('port_spec'); if(!port) return alert('Enter port spec like "8080/tcp"'); const permanent=el('port_perm')?.value||'no'; const r=await apiPOST('remove-port',{zone,port,permanent}); setText('ports_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`remove-port ${port}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }

/* Sources */
async function addSource(){ const zone=val('src_zone')||'public'; const cidr=val('src_cidr'); if(!cidr) return alert('Enter CIDR'); const permanent=el('src_perm')?.value||'no'; const r=await apiPOST('add-source',{zone,cidr,permanent}); setText('sources_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`add-source ${cidr}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }
async function removeSource(){ const zone=val('src_zone')||'public'; const cidr=val('src_cidr'); if(!cidr) return alert('Enter CIDR'); const permanent=el('src_perm')?.value||'no'; const r=await apiPOST('remove-source',{zone,cidr,permanent}); setText('sources_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`remove-source ${cidr}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }

/* Interfaces */
async function listInterfaces(){
  setHTML('interfaces_list','<div class="muted">Loading…</div>');
  const r=await apiGET('list-interfaces-json');
  const o=tryJSON(r.text);
  if(Array.isArray(o)){
    setHTML('interfaces_list','<table><thead><tr><th>System interface</th></tr></thead><tbody>'+o.map(n=>`<tr><td><code>${escapeHtml(n)}</code></td></tr>`).join('')+'</tbody></table>');
  } else {
    setHTML('interfaces_list',`<pre>${prettyMaybe(r.text)}</pre>`);
  }
  logLine(r.ok?'ok':'error',`list-interfaces-json → ${r.status}`);
}
async function addInterface(){ const zone=val('if_zone')||'public'; const iface=val('if_name'); if(!iface) return alert('Enter interface'); const permanent=el('if_perm')?.value||'no'; const r=await apiPOST('add-interface',{zone,iface,permanent}); setText('ifaces_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`add-interface ${iface}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }
async function removeInterface(){ const zone=val('if_zone')||'public'; const iface=val('if_name'); if(!iface) return alert('Enter interface'); const permanent=el('if_perm')?.value||'no'; const r=await apiPOST('remove-interface',{zone,iface,permanent}); setText('ifaces_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`remove-interface ${iface}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }

/* Rich rules */
async function addRichRule(){ const zone=val('rr_zone')||'public'; const rule=val('rr_text'); if(!rule) return alert('Enter a rich rule string'); const permanent=el('rr_perm')?.value||'no'; const r=await apiPOST('add-rich-rule',{zone,rule,permanent}); setText('rich_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`add-rich-rule @${zone}/${permanent} → ${r.status}`); zoneInfo(); }
async function removeRichRule(){ const zone=val('rr_zone')||'public'; const rule=val('rr_text'); if(!rule) return alert('Enter a rich rule string'); const permanent=el('rr_perm')?.value||'no'; const r=await apiPOST('remove-rich-rule',{zone,rule,permanent}); setText('rich_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`remove-rich-rule @${zone}/${permanent} → ${r.status}`); zoneInfo(); }

/* ICMP */
async function icmpBlock(){
  const op=el('icmp_op')?.value||'list';
  const type=val('icmp_type');
  const payload={op};
  if(op!=='list'){ if(!type) return alert('Enter ICMP type'); payload.type=type; }
  const r=await apiPOST('icmp-block',payload);
  setText('icmp_out',prettyMaybe(r.text));
  logLine(r.ok?'ok':'error',`icmp-block ${op}${type?(' '+type):''} → ${r.status}`);
}

/* init */
(async ()=>{ await loadStatus(); const m=(el('default_zone_label').textContent||'').match(/default zone:\s*(\S+)/i); el('zi_zone').value = m?m[1]:'public'; zoneInfo(); })();
</script>
</body>
</html>

