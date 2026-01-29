<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';

$db = db();
require_permission('module5.manage_terminal');

$format = tmm_export_format();
$mode = strtolower(trim((string)($_GET['mode'] ?? 'suggest')));
if ($mode !== 'current' && $mode !== 'suggest') $mode = 'suggest';

tmm_send_export_headers($format, $mode === 'current' ? 'terminal_routes_current' : 'terminal_routes_seed');

$terminals = [];
$resT = $db->query("SELECT id, name, location, city, address, type FROM terminals WHERE type <> 'Parking' ORDER BY name ASC LIMIT 5000");
if ($resT) {
  while ($r = $resT->fetch_assoc()) $terminals[] = $r;
}

$routes = [];
$resR = $db->query("SELECT route_id, route_code, route_name, origin, destination, via, vehicle_type FROM routes ORDER BY route_id ASC LIMIT 5000");
if ($resR) {
  while ($r = $resR->fetch_assoc()) {
    $rid = trim((string)($r['route_id'] ?? ''));
    if ($rid === '') continue;
    $routes[$rid] = [
      'route_id' => $rid,
      'route_code' => (string)($r['route_code'] ?? ''),
      'route_name' => (string)($r['route_name'] ?? ''),
      'origin' => (string)($r['origin'] ?? ''),
      'destination' => (string)($r['destination'] ?? ''),
      'via' => (string)($r['via'] ?? ''),
      'vehicle_type' => (string)($r['vehicle_type'] ?? ''),
    ];
  }
}

$existingByTerminalId = [];
$existingByTerminalName = [];
$resTR = $db->query("SELECT tr.terminal_id, t.name AS terminal_name, tr.route_id
                     FROM terminal_routes tr
                     JOIN terminals t ON t.id=tr.terminal_id
                     WHERE t.type <> 'Parking'");
if ($resTR) {
  while ($r = $resTR->fetch_assoc()) {
    $tid = (int)($r['terminal_id'] ?? 0);
    $tname = (string)($r['terminal_name'] ?? '');
    $rid = (string)($r['route_id'] ?? '');
    if ($tid <= 0 || $tname === '' || $rid === '') continue;
    $existingByTerminalId[$tid][] = $rid;
    $existingByTerminalName[$tname][] = $rid;
  }
}
foreach ($existingByTerminalId as $k => $arr) $existingByTerminalId[$k] = array_values(array_unique($arr));
foreach ($existingByTerminalName as $k => $arr) $existingByTerminalName[$k] = array_values(array_unique($arr));

$stop = [
  'terminal' => true, 'parking' => true, 'city' => true, 'caloocan' => true, 'road' => true, 'rd' => true,
  'street' => true, 'st' => true, 'ave' => true, 'avenue' => true, 'ext' => true, 'brgy' => true,
  'barangay' => true, 'near' => true, 'area' => true, 'phase' => true, 'edsa' => true, 'lrt' => true,
  'highway' => true, 'hwy' => true, 'the' => true, 'and' => true, 'of' => true, 'to' => true,
];

$tokenize = function (string $text) use ($stop): array {
  $text = strtolower($text);
  $parts = preg_split('/[^a-z0-9]+/', $text);
  $out = [];
  foreach ($parts as $p) {
    $p = trim($p);
    if ($p === '' || isset($stop[$p])) continue;
    if (strlen($p) < 3) continue;
    $out[$p] = true;
  }
  return array_keys($out);
};

$terminalTokens = [];
foreach ($terminals as $t) {
  $tid = (int)($t['id'] ?? 0);
  $name = (string)($t['name'] ?? '');
  $loc = (string)($t['location'] ?? '');
  $city = (string)($t['city'] ?? '');
  $addr = (string)($t['address'] ?? '');
  $terminalTokens[$tid] = $tokenize($name . ' ' . $loc . ' ' . $city . ' ' . $addr);
}

$routeSearch = [];
foreach ($routes as $rid => $r) {
  $routeSearch[$rid] = strtolower(trim($rid . ' ' . ($r['route_code'] ?? '') . ' ' . ($r['route_name'] ?? '') . ' ' . ($r['origin'] ?? '') . ' ' . ($r['destination'] ?? '') . ' ' . ($r['via'] ?? '') . ' ' . ($r['vehicle_type'] ?? '')));
}

$rows = [];
$addRow = function (string $terminalName, string $routeId, string $source, string $confidence) use (&$rows) {
  $rows[] = [
    'terminal_name' => $terminalName,
    'route_id' => $routeId,
    'source' => $source,
    'confidence' => $confidence,
  ];
};

foreach ($terminals as $t) {
  $tid = (int)($t['id'] ?? 0);
  $tname = (string)($t['name'] ?? '');
  if ($tid <= 0 || $tname === '') continue;
  $existing = $existingByTerminalId[$tid] ?? [];
  if ($existing) {
    foreach ($existing as $rid) $addRow($tname, $rid, 'existing', '1.00');
    continue;
  }
  if ($mode === 'current') continue;

  $tokens = $terminalTokens[$tid] ?? [];
  $bestTemplateTid = 0;
  $bestOverlap = 0;
  foreach ($existingByTerminalId as $otherTid => $otherRoutes) {
    $otherTokens = $terminalTokens[$otherTid] ?? [];
    if (!$tokens || !$otherTokens) continue;
    $set = array_fill_keys($otherTokens, true);
    $overlap = 0;
    foreach ($tokens as $tok) if (isset($set[$tok])) $overlap++;
    if ($overlap > $bestOverlap) { $bestOverlap = $overlap; $bestTemplateTid = (int)$otherTid; }
  }
  if ($bestTemplateTid > 0 && $bestOverlap > 0) {
    $templateName = '';
    foreach ($terminals as $tt) {
      if ((int)($tt['id'] ?? 0) === $bestTemplateTid) { $templateName = (string)($tt['name'] ?? ''); break; }
    }
    $tplRoutes = $existingByTerminalId[$bestTemplateTid] ?? [];
    $conf = $tokens ? min(0.95, max(0.50, $bestOverlap / max(1, count($tokens)))) : 0.50;
    foreach ($tplRoutes as $rid) $addRow($tname, $rid, $templateName !== '' ? ('copied_from:' . $templateName) : 'copied_from_terminal', number_format($conf, 2));
    continue;
  }

  if (!$tokens) continue;
  $scores = [];
  foreach ($routeSearch as $rid => $hay) {
    $score = 0;
    foreach ($tokens as $tok) {
      if (strpos($hay, $tok) !== false) $score += 2;
    }
    if ($score > 0) $scores[$rid] = $score;
  }
  if (!$scores) continue;
  arsort($scores);
  $picked = array_slice(array_keys($scores), 0, 25);
  $top = (int)($scores[$picked[0]] ?? 0);
  foreach ($picked as $rid) {
    $s = (int)($scores[$rid] ?? 0);
    if ($s <= 0) continue;
    $conf = $top > 0 ? min(0.90, max(0.30, $s / $top)) : 0.30;
    $addRow($tname, $rid, 'matched_text', number_format($conf, 2));
  }
}

$headers = ['terminal_name','route_id','source','confidence'];
if ($format === 'excel') {
  tmm_export_write_excel($headers, $rows);
} else {
  tmm_export_write_csv($headers, $rows);
}

