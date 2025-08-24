#!/usr/local/cpanel/3rdparty/bin/php -q
<?php
/**
 * WHM/cPanel — Firewalld Manager API (for index.php)
 * Bridges HTTP actions to fwctl.sh, and (for zone-inspect) calls firewall-cmd
 *
 * Endpoints (query param `action`):
 *   GET:
 *     - status-json
 *     - zone-info-json   (zone)                     # passthrough to fwctl.sh
 *     - get-services-json
 *     - list-interfaces-json
 *     - zone-inspect     (zone)                     # runtime + permanent via firewall-cmd
 *   POST:
 *     - service          (cmd: start|stop|restart|reload|enable|disable|status)
 *     - panic            (mode: on|off)
 *     - create-zone      (zone)
 *     - delete-zone      (zone)
 *     - set-default-zone (zone)
 *     - add-service      (zone, service, permanent: yes|no)
 *     - remove-service   (zone, service, permanent: yes|no)
 *     - add-port         (zone, port, permanent: yes|no)
 *     - remove-port      (zone, port, permanent: yes|no)
 *     - add-source       (zone, cidr, permanent: yes|no)
 *     - remove-source    (zone, cidr, permanent: yes|no)
 *     - add-interface    (zone, iface, permanent: yes|no)
 *     - remove-interface (zone, iface, permanent: yes|no)
 *     - add-rich-rule    (zone, rule, permanent: yes|no)
 *     - remove-rich-rule (zone, rule, permanent: yes|no)
 *     - icmp-block       (op: list|add|remove, type when add/remove)
 */

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

/* ---------- Config ---------- */
define('BASE_DIR', '/usr/local/cpanel/3rdparty/firewalld_manager');
define('FW_BIN',   BASE_DIR . '/scripts/fwctl.sh');

/* ---------- Helpers ---------- */
function is_json($s){
  if (!is_string($s)) return false;
  $t = ltrim($s);
  if ($t === '' || ($t[0] !== '{' && $t[0] !== '[')) return false;
  json_decode($s);
  return json_last_error() === JSON_ERROR_NONE;
}
function respond_text($s, $status=200){
  http_response_code($status);
  if (is_json($s)) header('Content-Type: application/json; charset=UTF-8');
  else header('Content-Type: text/plain; charset=UTF-8');
  echo $s; exit;
}
function respond_json($arr, $status=200){
  header('Content-Type: application/json; charset=UTF-8');
  http_response_code($status);
  echo json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}
function error_out($msg, $status=400){
  respond_json(['ok'=>false,'error'=>$msg], $status);
}
function must_post(){
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') error_out('POST required', 405);
}
function norm_yesno($v){ return (strtolower(trim((string)$v)) === 'yes') ? 'yes' : 'no'; }
function norm_onoff($v){ return (strtolower(trim((string)$v)) === 'on') ? 'on' : 'off'; }
function safe_arg($s){
  $s = (string)$s;
  if ($s === '' || strlen($s) > 4096 || strpbrk($s, "\r\n\0") !== false) error_out('Invalid argument');
  return $s;
}
function run_fw($args){
  if (!is_executable(FW_BIN)) error_out('fwctl.sh missing or not executable', 500);
  $cmd = escapeshellcmd(FW_BIN) . ' ' . $args . ' 2>&1';
  $out = shell_exec($cmd);
  if ($out === null) $out = '';
  return $out;
}
function esc($v){ return escapeshellarg($v); }

/* ---------- firewall-cmd utilities (for zone-inspect) ---------- */
function bin_firewall_cmd(){
  static $bin = null;
  if ($bin !== null) return $bin;

  // Prefer explicit known paths first
  foreach (['/usr/bin/firewall-cmd','/usr/sbin/firewall-cmd'] as $cand){
    if (is_executable($cand)) { $bin = $cand; return $bin; }
  }
  // Fallback to PATH lookup
  $out = @shell_exec('command -v firewall-cmd 2>/dev/null');
  if ($out) {
    $path = trim($out);
    if ($path !== '' && is_executable($path)) { $bin = $path; return $bin; }
  }
  // Last resort: hope PATH resolves it at runtime
  $bin = 'firewall-cmd';
  return $bin;
}
function sh($cmd){
  $out = shell_exec($cmd . ' 2>&1');
  return ($out === null) ? '' : $out;
}
function fw_list_simple($zone, $permanent, $flag){ // services/ports/sources/interfaces
  $bin = escapeshellcmd(bin_firewall_cmd());
  $z   = '--zone=' . escapeshellarg($zone);
  $perm= $permanent ? ' --permanent' : '';
  $cmd = "$bin $z$perm $flag";
  $raw = trim(sh($cmd));
  if ($raw === '' || stripos($raw, 'not set') !== false) return [];
  $parts = preg_split('/\s+/', $raw);
  return array_values(array_filter($parts, fn($x)=>$x!==''));
}
function fw_list_rich_rules($zone, $permanent){
  $bin = escapeshellcmd(bin_firewall_cmd());
  $z   = '--zone=' . escapeshellarg($zone);
  $perm= $permanent ? ' --permanent' : '';
  $cmd = "$bin $z$perm --list-rich-rules";
  $raw = sh($cmd);
  if ($raw === '' || stripos($raw, 'not set') !== false) return [];
  $lines = preg_split('/\r?\n/', $raw);
  $out = [];
  foreach ($lines as $ln){
    $t = trim($ln);
    if ($t !== '') $out[] = $t;
  }
  return $out;
}
function zone_state($zone, $permanent=false){
  return [
    'services'   => fw_list_simple($zone,$permanent,'--list-services'),
    'ports'      => fw_list_simple($zone,$permanent,'--list-ports'),
    'sources'    => fw_list_simple($zone,$permanent,'--list-sources'),
    'interfaces' => fw_list_simple($zone,$permanent,'--list-interfaces'),
    'rich_rules' => fw_list_rich_rules($zone,$permanent),
  ];
}

/* ---------- Router ---------- */
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw    = file_get_contents('php://input') ?: '';
$body   = json_decode($raw, true);
if (!is_array($body)) $body = [];

/* ---------- Read-only endpoints ---------- */
if ($method === 'GET') {
  switch ($action) {
    case 'status-json':
      respond_text(run_fw('status-json'));

    case 'zone-info-json': {
      $zone = isset($_GET['zone']) ? safe_arg($_GET['zone']) : 'public';
      respond_text(run_fw('zone-info-json ' . esc($zone)));
    }

    case 'get-services-json':
      respond_text(run_fw('get-services-json'));

    case 'list-interfaces-json':
      respond_text(run_fw('list-interfaces-json'));

    /* Explicit runtime vs permanent using firewall-cmd */
    case 'zone-inspect': {
      $zone = isset($_GET['zone']) ? safe_arg($_GET['zone']) : 'public';
      $runtime   = zone_state($zone, false);
      $permanent = zone_state($zone, true);
      respond_json(['runtime'=>$runtime, 'permanent'=>$permanent]);
    }
  }
}

/* ---------- Mutating endpoints (POST) ---------- */
switch ($action) {

  case 'service': {
    must_post();
    $cmd = $body['cmd'] ?? 'status';
    $allowed = ['start','stop','restart','reload','enable','disable','status'];
    if (!in_array($cmd, $allowed, true)) $cmd = 'status';
    respond_text(run_fw('service ' . esc($cmd)));
  }

  case 'panic': {
    must_post();
    $mode = norm_onoff($body['mode'] ?? 'off');
    respond_text(run_fw('panic ' . esc($mode)));
  }

  case 'create-zone': {
    must_post();
    $zone = safe_arg($body['zone'] ?? '');
    if ($zone === '') error_out('zone required');
    respond_text(run_fw('create-zone ' . esc($zone)));
  }

  case 'delete-zone': {
    must_post();
    $zone = safe_arg($body['zone'] ?? '');
    if ($zone === '') error_out('zone required');
    respond_text(run_fw('delete-zone ' . esc($zone)));
  }

  case 'set-default-zone': {
    must_post();
    $zone = safe_arg($body['zone'] ?? 'public');
    respond_text(run_fw('set-default-zone ' . esc($zone)));
  }

  case 'add-service':
  case 'remove-service': {
    must_post();
    $zone = safe_arg($body['zone'] ?? 'public');
    $service = safe_arg($body['service'] ?? '');
    if ($service === '') error_out('service required');
    $permanent = norm_yesno($body['permanent'] ?? 'no');
    $cmd = ($action === 'add-service' ? 'add-service ' : 'remove-service ')
       . esc($zone) . ' ' . esc($service) . ' ' . esc($permanent);
    respond_text(run_fw($cmd));
  }

  case 'add-port':    // ← the missing colon caused the 500
  case 'remove-port': {
    must_post();
    $zone = safe_arg($body['zone'] ?? 'public');
    $port = safe_arg($body['port'] ?? '');
    if ($port === '') error_out('port required (e.g., 8080/tcp or 35000-35999/tcp)');
    $permanent = norm_yesno($body['permanent'] ?? 'no');
    $cmd = ($action === 'add-port' ? 'add-port ' : 'remove-port ')
       . esc($zone) . ' ' . esc($port) . ' ' . esc($permanent);
    respond_text(run_fw($cmd));
  }

  case 'add-source':
  case 'remove-source': {
    must_post();
    $zone = safe_arg($body['zone'] ?? 'public');
    $cidr = safe_arg($body['cidr'] ?? '');
    if ($cidr === '') error_out('cidr required (e.g., 203.0.113.0/24)');
    $permanent = norm_yesno($body['permanent'] ?? 'no');
    $cmd = ($action === 'add-source' ? 'add-source ' : 'remove-source ')
       . esc($zone) . ' ' . esc($cidr) . ' ' . esc($permanent);
    respond_text(run_fw($cmd));
  }

  case 'add-interface':
  case 'remove-interface': {
    must_post();
    $zone = safe_arg($body['zone'] ?? 'public');
    $iface = safe_arg($body['iface'] ?? '');
    if ($iface === '') error_out('iface required (e.g., eth0)');
    $permanent = norm_yesno($body['permanent'] ?? 'no');
    $cmd = ($action === 'add-interface' ? 'add-interface ' : 'remove-interface ')
       . esc($zone) . ' ' . esc($iface) . ' ' . esc($permanent);
    respond_text(run_fw($cmd));
  }

  case 'add-rich-rule':
  case 'remove-rich-rule': {
    must_post();
    $zone = safe_arg($body['zone'] ?? 'public');
    $rule = safe_arg($body['rule'] ?? '');
    if ($rule === '') error_out('rule required');
    $permanent = norm_yesno($body['permanent'] ?? 'no');
    $cmd = ($action === 'add-rich-rule' ? 'add-rich-rule ' : 'remove-rich-rule ')
       . esc($zone) . ' ' . esc($rule) . ' ' . esc($permanent);
    respond_text(run_fw($cmd));
  }

  case 'icmp-block': {
    must_post();
    $op = $body['op'] ?? 'list';
    if (!in_array($op, ['list','add','remove'], true)) $op = 'list';
    if ($op === 'list') {
      respond_text(run_fw('icmp-block list'));
    } else {
      $type = safe_arg($body['type'] ?? '');
      if ($type === '') error_out('type required for add/remove (e.g., echo-request)');
      respond_text(run_fw('icmp-block ' . esc($op) . ' ' . esc($type)));
    }
  }
}

/* Unknown or wrong method */
error_out('Unknown action', 404);

