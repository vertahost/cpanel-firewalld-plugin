#!/usr/local/cpanel/3rdparty/bin/php -q
<?php
/**
 * WHM/cPanel — Firewalld Manager UI (single file)
 * Works with api.php → fwctl.sh (+ zone-inspect via firewall-cmd)
 * 
 */
header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
/* Inline JS/CSS allowed because we use a single self-contained file */
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'");

$api = 'api.php'; // relative keeps the /cpsess##### token path intact
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>Firewalld Manager</title>
<style>
  :root{--bg:#0b1426;--fg:#e5eaf3;--bord:#22314d;}
  body { font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Arial,"Noto Sans",sans-serif; margin:0; padding:22px; color:#111; }
  h1 { margin:0 0 12px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(380px,1fr)); gap:14px; }
  .card { border:1px solid #ddd; border-radius:12px; background:#fff; padding:14px; }
  .row { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
  .stack{ display:flex; flex-direction:column; gap:10px; }
  .btn { padding:8px 12px; border:1px solid #444; border-radius:8px; cursor:pointer; background:#f6f6f6; }
  .btn:active{ transform: translateY(1px); }
  input, select { padding:6px 8px; border-radius:8px; border:1px solid #ccc; }
  input[type="text"]{ min-width:160px; }
  pre { background:var(--bg); color:var(--fg); border:1px solid var(--bord); padding:12px; border-radius:10px; white-space:pre-wrap; word-break:break-word; }
  table { width:100%; border-collapse: collapse; }
  th, td{ text-align:left; border-bottom:1px solid #eee; padding:6px 8px; }
  .muted{ color:#666; }
  .pill{ font-size:12px; padding:2px 8px; border-radius:999px; background:#eef; border:1px solid #99c; }
  .divider{ height:1px; background:#eee; margin:8px 0; }
  .right{ margin-left:auto; }
  .chips{ display:flex; flex-wrap:wrap; gap:6px; }
  .chip{ font-size:12px; padding:2px 8px; border-radius:999px; border:1px solid #ddd; background:#f7f7f7; }
  .badge{ font-size:12px; padding:2px 8px; border-radius:999px; border:1px solid transparent; }
  .inline-help{ font-size:12px; color:#777; }
</style>
</head>
<body>
  <h1>🔥 Firewalld Manager</h1>
  <p class="muted">Manage zones, services, ports, sources, interfaces, rich rules, ICMP blocks, and the firewalld daemon via <code>fwctl.sh</code>.</p>

  <div class="grid">
    <!-- Service / Status + Options Loader -->
    <div class="card">
      <div class="row">
        <strong>Daemon & Status</strong>
        <span class="pill">systemd</span>
        <span class="right muted" id="default_zone_label"></span>
      </div>
      <div class="row" style="margin-top:8px;">
        <button class="btn" onclick="svc('status')">Status</button>
        <button class="btn" onclick="svc('start')">Start</button>
        <button class="btn" onclick="svc('stop')">Stop</button>
        <button class="btn" onclick="svc('restart')">Restart</button>
        <button class="btn" onclick="svc('reload')">Reload</button>
        <button class="btn" onclick="svc('enable')">Enable</button>
        <button class="btn" onclick="svc('disable')">Disable</button>
        <button class="btn" onclick="refreshAll()">Refresh</button>
      </div>
      <div class="divider"></div>
      <div id="status_pre">Click “Refresh”</div>
      <div class="divider"></div>
      <div class="inline-help">Tip: “Refresh” also reloads dropdown options for zones/services/interfaces/ICMP types.</div>
    </div>

    <!-- Zones: info / CRUD / default -->
    <div class="card">
      <div class="row">
        <strong>Zones</strong>
        <span class="pill">info / create / delete / default</span>
      </div>
      <div class="stack" style="margin-top:8px;">
        <div class="row">
          <label><b>Inspect</b></label>
          <select id="zi_zone_dd" style="min-width:180px;"></select>
          <button class="btn" onclick="zoneInfo()">Load Zone</button>
          <button class="btn" onclick="useDefaultZone()">Use Default</button>
        </div>

        <div class="row">
          <label>Create</label>
          <input id="zone_new" type="text" placeholder="new-zone-name" />
          <button class="btn" onclick="createZone()">Create</button>
        </div>

        <div class="row">
          <label>Delete</label>
          <select id="zone_del_dd" style="min-width:180px;"></select>
          <button class="btn" onclick="deleteZone()">Delete</button>
        </div>

        <div class="row">
          <label>Set default</label>
          <select id="zone_def_dd" style="min-width:180px;"></select>
          <button class="btn" onclick="setDefaultZone()">Apply</button>
        </div>
      </div>
      <div class="divider"></div>
      <div id="zones_out">Use actions above.</div>
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
        <label>Zone</label>
        <select id="svc_zone_dd" style="min-width:180px;"></select>
        <label>Service</label>
        <select id="svc_name_dd" style="min-width:180px;"></select>
        <label>Permanent</label>
        <select id="svc_perm"><option value="no">no (runtime)</option><option value="yes">yes (permanent)</option></select>
      </div>
      <div class="row">
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
        <label>Zone</label>
        <select id="port_zone_dd" style="min-width:180px;"></select>
        <label>Port/Proto</label>
        <input id="port_spec" type="text" placeholder="8080/tcp or 35000-35999/tcp" />
        <label>Permanent</label>
        <select id="port_perm"><option value="no">no (runtime)</option><option value="yes">yes (permanent)</option></select>
      </div>
      <div class="row">
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
        <label>Zone</label>
        <select id="src_zone_dd" style="min-width:180px;"></select>
        <label>CIDR</label>
        <input id="src_cidr" type="text" placeholder="203.0.113.0/24" />
        <label>Permanent</label>
        <select id="src_perm"><option value="no">no (runtime)</option><option value="yes">yes (permanent)</option></select>
      </div>
      <div class="row">
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
          <label>Zone</label>
          <select id="if_zone_dd" style="min-width:180px;"></select>
          <label>Interface</label>
          <select id="if_name_dd" style="min-width:180px;"></select>
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
          <label>Zone</label>
          <select id="rr_zone_dd" style="min-width:180px;"></select>
          <label>Permanent</label>
          <select id="rr_perm"><option value="no">no (runtime)</option><option value="yes">yes (permanent)</option></select>
        </div>
        <div class="row" style="flex:1 1 100%">
          <label>Rule</label>
          <input id="rr_text" type="text" style="flex:1" placeholder="rule family=ipv4 source address=198.51.100.0/24 port port=8080 protocol=tcp accept" />
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
        <label>Type</label>
        <select id="icmp_type_dd" style="min-width:180px;"></select>
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

/* ---------------- Utilities ---------------- */
const el   = id => document.getElementById(id);
const setText = (id, s) => { el(id).textContent = s ?? ''; };
const setHTML = (id, html) => { el(id).innerHTML = html ?? ''; };

function logLine(kind, msg){
  const pre = el('log'), ts = new Date().toLocaleString();
  pre.textContent = `[${ts}] ${kind.toUpperCase()}: ${msg}\n` + pre.textContent;
}
async function apiGET(action, params={}){
  const qs = new URLSearchParams(params);
  const url = API + '?action=' + encodeURIComponent(action) + (qs.toString() ? '&'+qs.toString() : '');
  const r = await fetch(url, {credentials:'same-origin'});
  return { ok:r.ok, status:r.status, text: await r.text() };
}
async function apiPOST(action, body={}){
  const url = API + '?action=' + encodeURIComponent(action);
  const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body), credentials:'same-origin' });
  return { ok:r.ok, status:r.status, text: await r.text() };
}
function tryJSON(t){ try{ return JSON.parse(t); }catch{ return null; } }
function prettyMaybe(t){ const o = tryJSON(t); return o ? JSON.stringify(o, null, 2) : t; }
function escapeHtml(s){ return (''+s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
function uniq(arr){ const out=[]; const seen=new Set(); for(const x of (arr||[])){ const k=String(x); if(!seen.has(k)){ seen.add(k); out.push(k);} } return out; }
function nonEmptyArray(v){
  if (!v) return [];
  if (Array.isArray(v)) return v.filter(Boolean);
  if (typeof v === 'string') return v.split(/\s+|,\s*/).map(s=>s.trim()).filter(Boolean);
  if (typeof v === 'object') return Object.keys(v);
  return [];
}

/* ---------------- Nice badges & panels (status + zone summary) ---------------- */
function badge(text, type='muted'){
  const styles = {
    ok: 'background:#e7f7e9;border-color:#a7d8b0;color:#176a2f;',
    warn:'background:#fff3cd;border-color:#ffe29a;color:#7a5a00;',
    err:'background:#fde2e2;border-color:#f5b5b5;color:#8a1c1c;',
    info:'background:#e7f1ff;border-color:#b9d0ff;color:#1f4ea3;',
    muted:'background:#f4f6f8;border-color:#dfe3e8;color:#555;',
    accent:'background:#f0f5ff;border-color:#cbd9ff;color:#3b5bdb;'
  };
  const st = styles[type] || styles.muted;
  return `<span class="badge" style="${st}">${escapeHtml(text)}</span>`;
}
function chip(text){ return `<span class="chip"><code>${escapeHtml(text)}</code></span>`; }
function normKey(k){ return String(k).toLowerCase().replace(/[\s\-]/g,'_'); }
function deepFind(obj, keyNames){
  const wanted = new Set(keyNames.map(normKey));
  function walk(v, depth){
    if (depth>6 || v==null) return undefined;
    if (Array.isArray(v)) { for (const x of v){ const r=walk(x,depth+1); if (r!==undefined) return r; } return undefined; }
    if (typeof v !== 'object') return undefined;
    for (const [k,val] of Object.entries(v)){ if (wanted.has(normKey(k))) return val; }
    for (const val of Object.values(v)){ const r=walk(val,depth+1); if (r!==undefined) return r; }
    return undefined;
  }
  return walk(obj,0);
}
function truthyStatus(v){
  if (typeof v === 'boolean') return v;
  const s = String(v||'').toLowerCase();
  return ['1','true','running','active','yes','enabled','on','ok'].includes(s);
}
function statusRenderer(jsonText){
  const o = tryJSON(jsonText);
  if (!o) return `<pre>${escapeHtml(jsonText)}</pre>`;

  const runningVal  = deepFind(o, ['running','active','state','status']);
  const enabledVal  = deepFind(o, ['enabled']);
  const versionVal  = deepFind(o, ['version','firewalld_version','firewalld-version']);
  const panicVal    = deepFind(o, ['panic','panic_mode']);
  const defZoneVal  = deepFind(o, ['default_zone','default-zone','default']);
  const zonesVal    = deepFind(o, ['zones','all_zones','zone_list']);
  const activeZones = deepFind(o, ['active_zones','active-zones']);

  const isRunning = truthyStatus(runningVal);
  const isEnabled = truthyStatus(enabledVal);
  const isPanic   = truthyStatus(panicVal);

  const header = [
    badge(isRunning ? 'Running' : 'Stopped', isRunning ? 'ok' : 'err'),
    badge(isEnabled ? 'Enabled' : 'Disabled', isEnabled ? 'info' : 'muted'),
    badge(`Default: ${defZoneVal ? defZoneVal : '(unknown)'}`, 'accent'),
    versionVal ? badge(`v${versionVal}`, 'muted') : ''
  ].filter(Boolean).join(' ');

  let azHTML = '<div class="muted">(none)</div>';
  if (activeZones && typeof activeZones === 'object'){
    const rows = Object.entries(activeZones).map(([zone, obj])=>{
      const ifs = nonEmptyArray(obj?.interfaces || obj?.ifaces || obj?.interface || []);
      const src = nonEmptyArray(obj?.sources || obj?.source || []);
      return `<tr><td><strong>${escapeHtml(zone)}</strong></td><td>${ifs.map(chip).join(' ')||'<span class="muted">-</span>'}</td><td>${src.map(chip).join(' ')||'<span class="muted">-</span>'}</td></tr>`;
    }).join('');
    azHTML = `<table><thead><tr><th>Zone</th><th>Interfaces</th><th>Sources</th></tr></thead><tbody>${rows}</tbody></table>`;
  }

  const allZones = nonEmptyArray(zonesVal);
  const allHTML = allZones.length ? `<div class="chips">${allZones.map(chip).join(' ')}</div>` : '<div class="muted">(unknown)</div>';

  return `
    <div class="stack">
      <div class="row">${header} ${isPanic ? badge('PANIC ON','warn') : ''}</div>
      <div class="divider"></div>
      <div class="row"><strong>All Zones</strong></div>
      ${allHTML}
      <div class="divider"></div>
      <details><summary>Raw JSON</summary><pre style="margin-top:8px;">${prettyMaybe(jsonText)}</pre></details>
    </div>
  `;
}

/* ---------------- Zone Summary / Details ---------------- */
function diffTriples(runtimeList, permanentList){
  const r = uniq(runtimeList||[]), p = uniq(permanentList||[]);
  const pset = new Set(p.map(x=>x.toLowerCase())), rset = new Set(r.map(x=>x.toLowerCase()));
  const shared = uniq(r.filter(x=>pset.has(x.toLowerCase())));
  const runtimeOnly = uniq(r.filter(x=>!pset.has(x.toLowerCase())));
  const permanentOnly = uniq(p.filter(x=>!rset.has(x.toLowerCase())));
  return { runtimeOnly, permanentOnly, shared };
}
function renderCountsRow(name, rArr, pArr){
  const {runtimeOnly, permanentOnly, shared} = diffTriples(rArr, pArr);
  return `<tr>
    <td><strong>${escapeHtml(name.replace('_',' '))}</strong></td>
    <td>${runtimeOnly.length}</td>
    <td>${permanentOnly.length}</td>
    <td>${shared.length}</td>
    <td>${(rArr||[]).length}</td>
    <td>${(pArr||[]).length}</td>
  </tr>`;
}
function renderSampleList(title, items){
  if (!items || !items.length) return '';
  const sample = items.slice(0,12);
  return `<div class="row" style="margin-top:6px;"><span class="muted" style="min-width:140px">${escapeHtml(title)}:</span> <div class="chips">${sample.map(chip).join(' ')}</div></div>`;
}
function zoneSummaryRenderer(zoneName, jsonText){
  const raw = tryJSON(jsonText);
  if (!raw) return `<pre>${escapeHtml(jsonText)}</pre>`;
  const rt = raw.runtime || {}, pm = raw.permanent || {};
  const cats = ['services','ports','sources','interfaces','rich_rules'];

  const rows = cats.map(c => renderCountsRow(c, rt[c]||[], pm[c]||[])).join('');
  const topServices = diffTriples(rt.services||[], pm.services||[]).shared;
  const topPorts    = diffTriples(rt.ports||[], pm.ports||[]).shared;

  return `
    <div class="stack">
      <div class="row"><strong>Zone Summary:</strong> ${badge(zoneName,'accent')}</div>
      <table>
        <thead><tr>
          <th>Category</th><th>Runtime-only</th><th>Permanent-only</th><th>Shared</th><th>Total (RT)</th><th>Total (Perm)</th>
        </tr></thead>
        <tbody>${rows}</tbody>
      </table>
      ${renderSampleList('Shared services', topServices)}
      ${renderSampleList('Shared ports', topPorts)}
      <div class="divider"></div>
      <details><summary>Raw JSON</summary><pre style="margin-top:8px;">${prettyMaybe(jsonText)}</pre></details>
    </div>
  `;
}
function renderList(arr, empty='(none)'){
  if(!Array.isArray(arr) || !arr.length) return `<div class="muted">${empty}</div>`;
  return `<ul style="margin:0;padding-left:18px;">${arr.map(x=>`<li><code>${escapeHtml(x)}</code></li>`).join('')}</ul>`;
}
function renderZoneDetails(zoneName, jsonText){
  const raw = tryJSON(jsonText) || {};
  const rt = raw.runtime || {}, pm = raw.permanent || {};
  const cats = ['services','ports','sources','interfaces','rich_rules'];
  const rowsRuntimeOnly = [], rowsPermanentOnly = [], rowsShared = [];
  for (const c of cats){
    const {runtimeOnly, permanentOnly, shared} = diffTriples(rt[c]||[], pm[c]||[]);
    rowsRuntimeOnly.push(`<div style="margin:6px 0 0;"><div class="muted" style="margin:2px 0 4px;">${c.replace('_',' ')} (runtime-only)</div>${renderList(runtimeOnly)}</div>`);
    rowsPermanentOnly.push(`<div style="margin:6px 0 0;"><div class="muted" style="margin:2px 0 4px;">${c.replace('_',' ')} (permanent-only)</div>${renderList(permanentOnly)}</div>`);
    rowsShared.push(`<div style="margin:6px 0 0;"><div class="muted" style="margin:2px 0 4px;">${c.replace('_',' ')} (shared)</div>${renderList(shared)}</div>`);
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

/* ---------------- Dropdown helpers ---------------- */
function fillSelect(id, items, {placeholder='— select —', selectValue=null}={}){
  const s = el(id);
  if (!s) return;
  const cur = selectValue ?? s.value ?? '';
  s.innerHTML = '';
  const opt0 = document.createElement('option');
  opt0.value = '';
  opt0.textContent = placeholder;
  s.appendChild(opt0);
  for (const it of (items||[])) {
    const v = String(it);
    const o = document.createElement('option');
    o.value = v; o.textContent = v;
    if (cur && v === cur) o.selected = true;
    s.appendChild(o);
  }
}

/* ---------------- Load dropdown options ---------------- */
async function loadZonesOptions(){
  const res = await apiGET('status-json');
  const o = tryJSON(res.text) || {};
  const zonesRaw = deepFind(o, ['zones','all_zones','zone_list']) || [];
  const activeZones = deepFind(o, ['active_zones','active-zones']) || {};
  const z1 = nonEmptyArray(zonesRaw);
  const z2 = Object.keys(activeZones||{});
  const set = new Set([...z1, ...z2].filter(Boolean));
  const zones = Array.from(set).sort((a,b)=>a.localeCompare(b));
  const def = (o && (o.default_zone || o.default || o?.firewalld?.default_zone)) || '';

  const ids = ['zi_zone_dd','zone_del_dd','zone_def_dd','svc_zone_dd','port_zone_dd','src_zone_dd','if_zone_dd','rr_zone_dd'];
  for (const id of ids) fillSelect(id, zones, { placeholder:'— zone —', selectValue: def });

  // Pretty status panel + default label
  setHTML('status_pre', statusRenderer(res.text));
  if (def) el('default_zone_label').textContent = `default zone: ${def}`;
  logLine(res.ok ? 'ok' : 'error', `zones/options refresh via status-json → ${res.status}`);
}
async function loadServicesOptions(){
  const r = await apiGET('get-services-json');
  const o = tryJSON(r.text);
  if (Array.isArray(o)) fillSelect('svc_name_dd', o.sort((a,b)=>a.localeCompare(b)), { placeholder:'— service —' });
  logLine(r.ok ? 'ok' : 'error', `services options → ${r.status}`);
}
async function loadInterfacesOptions(){
  const r = await apiGET('list-interfaces-json');
  const o = tryJSON(r.text);
  if (Array.isArray(o)) fillSelect('if_name_dd', o, { placeholder:'— interface —' });
  logLine(r.ok ? 'ok' : 'error', `interfaces options → ${r.status}`);
}
async function loadIcmpOptions(){
  const fallback = ['echo-request','echo-reply','destination-unreachable','time-exceeded','parameter-problem','timestamp-request','timestamp-reply','router-advertisement','router-solicitation'];
  try {
    const r = await apiGET('get-icmptypes-json'); // optional
    const o = tryJSON(r.text);
    const arr = Array.isArray(o) && o.length ? o : fallback;
    fillSelect('icmp_type_dd', arr.sort((a,b)=>a.localeCompare(b)), { placeholder:'— icmp type —' });
    logLine(r.ok ? 'ok' : 'warn', `icmp types options → ${r.status}`);
  } catch {
    fillSelect('icmp_type_dd', fallback, { placeholder:'— icmp type —' });
  }
}
async function refreshAll(){
  await loadZonesOptions();
  await loadServicesOptions();
  await loadInterfacesOptions();
  await loadIcmpOptions();
}

/* ---------------- Actions (API calls) ---------------- */

/* Status & service */
async function loadStatus(){ await loadZonesOptions(); }
async function svc(cmd){ const r = await apiPOST('service',{cmd}); logLine(r.ok?'ok':'error',`service ${cmd} → ${r.status}`); await refreshAll(); }

/* Zones */
async function useDefaultZone(){
  const m=(el('default_zone_label').textContent||'').match(/default zone:\s*(\S+)/i);
  if (m && el('zi_zone_dd')) { el('zi_zone_dd').value = m[1]; }
  zoneInfo();
}
async function zoneInfo(){
  const zoneSel = el('zi_zone_dd')?.value || 'public';
  setHTML('zones_out','<div class="muted">Loading…</div>');
  const r = await apiGET('zone-inspect', { zone: zoneSel });
  setHTML('zones_out', zoneSummaryRenderer(zoneSel, r.text));
  renderZoneDetails(zoneSel, r.text);
  logLine(r.ok?'ok':'error',`zone-inspect ${zoneSel} → ${r.status}`);
}
async function createZone(){ const zone=el('zone_new')?.value.trim(); if(!zone) return alert('Enter zone'); const r=await apiPOST('create-zone',{zone}); setText('zones_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`create-zone ${zone} → ${r.status}`); await refreshAll(); zoneInfo(); }
async function deleteZone(){ const zone=el('zone_del_dd')?.value; if(!zone) return alert('Select a zone to delete'); const r=await apiPOST('delete-zone',{zone}); setText('zones_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`delete-zone ${zone} → ${r.status}`); await refreshAll(); }
async function setDefaultZone(){ const zone=el('zone_def_dd')?.value || 'public'; const r=await apiPOST('set-default-zone',{zone}); setText('zones_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`set-default-zone ${zone} → ${r.status}`); await refreshAll(); }

/* Services */
async function listServices(){
  setHTML('services_list','<div class="muted">Loading…</div>');
  const r=await apiGET('get-services-json');
  const o=tryJSON(r.text);
  if(Array.isArray(o)){
    setHTML('services_list','<table><thead><tr><th>Known service</th></tr></thead><tbody>'+o.map(s=>`<tr><td><code>${escapeHtml(s)}</code></td></tr>`).join('')+'</tbody></table>');
    fillSelect('svc_name_dd', o.sort((a,b)=>a.localeCompare(b)), { placeholder:'— service —' });
  } else {
    setHTML('services_list',`<pre>${prettyMaybe(r.text)}</pre>`);
  }
  logLine(r.ok?'ok':'error',`get-services-json → ${r.status}`);
}
async function addService(){ const zone=el('svc_zone_dd')?.value||'public'; const service=el('svc_name_dd')?.value||''; if(!service) return alert('Select a service'); const permanent=el('svc_perm')?.value||'no'; const r=await apiPOST('add-service',{zone,service,permanent}); setText('services_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`add-service ${service}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }
async function removeService(){ const zone=el('svc_zone_dd')?.value||'public'; const service=el('svc_name_dd')?.value||''; if(!service) return alert('Select a service'); const permanent=el('svc_perm')?.value||'no'; const r=await apiPOST('remove-service',{zone,service,permanent}); setText('services_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`remove-service ${service}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }

/* Ports */
async function addPort(){ const zone=el('port_zone_dd')?.value||'public'; const port=el('port_spec')?.value.trim(); if(!port) return alert('Enter port spec like "8080/tcp"'); const permanent=el('port_perm')?.value||'no'; const r=await apiPOST('add-port',{zone,port,permanent}); setText('ports_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`add-port ${port}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }
async function removePort(){ const zone=el('port_zone_dd')?.value||'public'; const port=el('port_spec')?.value.trim(); if(!port) return alert('Enter port spec like "8080/tcp"'); const permanent=el('port_perm')?.value||'no'; const r=await apiPOST('remove-port',{zone,port,permanent}); setText('ports_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`remove-port ${port}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }

/* Sources */
async function addSource(){ const zone=el('src_zone_dd')?.value||'public'; const cidr=el('src_cidr')?.value.trim(); if(!cidr) return alert('Enter CIDR'); const permanent=el('src_perm')?.value||'no'; const r=await apiPOST('add-source',{zone,cidr,permanent}); setText('sources_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`add-source ${cidr}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }
async function removeSource(){ const zone=el('src_zone_dd')?.value||'public'; const cidr=el('src_cidr')?.value.trim(); if(!cidr) return alert('Enter CIDR'); const permanent=el('src_perm')?.value||'no'; const r=await apiPOST('remove-source',{zone,cidr,permanent}); setText('sources_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`remove-source ${cidr}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }

/* Interfaces */
async function listInterfaces(){
  setHTML('interfaces_list','<div class="muted">Loading…</div>');
  const r=await apiGET('list-interfaces-json');
  const o=tryJSON(r.text);
  if(Array.isArray(o)){
    setHTML('interfaces_list','<table><thead><tr><th>System interface</th></tr></thead><tbody>'+o.map(n=>`<tr><td><code>${escapeHtml(n)}</code></td></tr>`).join('')+'</tbody></table>');
    fillSelect('if_name_dd', o, { placeholder:'— interface —' });
  } else {
    setHTML('interfaces_list',`<pre>${prettyMaybe(r.text)}</pre>`);
  }
  logLine(r.ok?'ok':'error',`list-interfaces-json → ${r.status}`);
}
async function addInterface(){ const zone=el('if_zone_dd')?.value||'public'; const iface=el('if_name_dd')?.value||''; if(!iface) return alert('Select interface'); const permanent=el('if_perm')?.value||'no'; const r=await apiPOST('add-interface',{zone,iface,permanent}); setText('ifaces_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`add-interface ${iface}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }
async function removeInterface(){ const zone=el('if_zone_dd')?.value||'public'; const iface=el('if_name_dd')?.value||''; if(!iface) return alert('Select interface'); const permanent=el('if_perm')?.value||'no'; const r=await apiPOST('remove-interface',{zone,iface,permanent}); setText('ifaces_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`remove-interface ${iface}@${zone}/${permanent} → ${r.status}`); zoneInfo(); }

/* Rich rules */
async function addRichRule(){ const zone=el('rr_zone_dd')?.value||'public'; const rule=el('rr_text')?.value.trim(); if(!rule) return alert('Enter a rich rule string'); const permanent=el('rr_perm')?.value||'no'; const r=await apiPOST('add-rich-rule',{zone,rule,permanent}); setText('rich_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`add-rich-rule @${zone}/${permanent} → ${r.status}`); zoneInfo(); }
async function removeRichRule(){ const zone=el('rr_zone_dd')?.value||'public'; const rule=el('rr_text')?.value.trim(); if(!rule) return alert('Enter a rich rule string'); const permanent=el('rr_perm')?.value||'no'; const r=await apiPOST('remove-rich-rule',{zone,rule,permanent}); setText('rich_out',prettyMaybe(r.text)); logLine(r.ok?'ok':'error',`remove-rich-rule @${zone}/${permanent} → ${r.status}`); zoneInfo(); }

/* ICMP */
async function icmpBlock(){
  const op=el('icmp_op')?.value||'list';
  const type=el('icmp_type_dd')?.value;
  const payload={op};
  if(op!=='list'){ if(!type) return alert('Select ICMP type'); payload.type=type; }
  const r=await apiPOST('icmp-block',payload);
  setText('icmp_out',prettyMaybe(r.text));
  logLine(r.ok?'ok':'error',`icmp-block ${op}${type?(' '+type):''} → ${r.status}`);
}

/* ---------------- Init ---------------- */
(async ()=>{
  await refreshAll();
  // Set Inspect dropdown to default zone if available
  const m=(el('default_zone_label').textContent||'').match(/default zone:\s*(\S+)/i);
  if (m && el('zi_zone_dd')) el('zi_zone_dd').value = m[1];
  zoneInfo();
})();
</script>
</body>
</html>
