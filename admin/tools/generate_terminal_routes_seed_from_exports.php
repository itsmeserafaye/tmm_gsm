<?php
function read_csv_assoc(string $path): array {
  if (!is_file($path)) return [];
  $fh = fopen($path, 'r');
  if (!$fh) return [];
  $header = null;
  $rows = [];
  while (($row = fgetcsv($fh)) !== false) {
    if ($header === null) {
      $header = array_map(function ($h) { return trim((string)$h); }, $row);
      continue;
    }
    if (!$header) continue;
    $assoc = [];
    for ($i = 0; $i < count($header); $i++) {
      $k = $header[$i] ?? '';
      if ($k === '') continue;
      $assoc[$k] = $row[$i] ?? '';
    }
    $rows[] = $assoc;
  }
  fclose($fh);
  return $rows;
}

function write_csv(string $path, array $headers, array $rows): void {
  $fh = fopen($path, 'w');
  if (!$fh) throw new RuntimeException('Cannot write: ' . $path);
  fputcsv($fh, $headers);
  foreach ($rows as $r) {
    $line = [];
    foreach ($headers as $h) $line[] = $r[$h] ?? '';
    fputcsv($fh, $line);
  }
  fclose($fh);
}

function norm(string $s): string {
  $s = strtolower($s);
  $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  return trim($s);
}

function tokenize(string $s): array {
  $s = norm($s);
  if ($s === '') return [];
  $parts = preg_split('/\s+/', $s);
  $stop = [
    'terminal'=>1,'parking'=>1,'city'=>1,'caloocan'=>1,'road'=>1,'rd'=>1,'street'=>1,'st'=>1,'ave'=>1,'avenue'=>1,'ext'=>1,
    'near'=>1,'area'=>1,'phase'=>1,'brgy'=>1,'barangay'=>1,'the'=>1,'and'=>1,'of'=>1,'to'=>1,'via'=>1,'hwy'=>1,'highway'=>1,
    'edsa'=>1,'lrt'=>1,'sm'=>1,'robinsons'=>1,'north'=>1,'south'=>1,'bound'=>1,'central'=>1,'integrated'=>1,
  ];
  $out = [];
  foreach ($parts as $p) {
    if ($p === '' || isset($stop[$p])) continue;
    if (strlen($p) < 3) continue;
    $out[$p] = true;
  }
  return array_keys($out);
}

function overlap_score(array $a, array $b): int {
  if (!$a || !$b) return 0;
  $set = array_fill_keys($b, true);
  $s = 0;
  foreach ($a as $t) if (isset($set[$t])) $s++;
  return $s;
}

function split_via(string $via): array {
  $via = trim($via);
  if ($via === '') return [];
  $via = str_replace(["•", "·", "|", ";"], "•", $via);
  $parts = array_map('trim', explode('•', $via));
  $out = [];
  foreach ($parts as $p) {
    if ($p === '') continue;
    $out[] = $p;
  }
  return array_values(array_unique($out));
}

function best_matches_by_text(string $needle, array $terminals, int $limit = 3): array {
  $n = norm($needle);
  if ($n === '') return [];
  $cands = [];
  foreach ($terminals as $t) {
    $hay = $t['hay'] ?? '';
    if ($hay !== '' && strpos($hay, $n) !== false) {
      $cands[] = ['name' => $t['name'], 'confidence' => 0.95, 'reason' => 'text_match:' . $needle];
    }
  }
  if ($cands) return array_slice($cands, 0, $limit);
  $nt = tokenize($needle);
  if (!$nt) return [];
  $scored = [];
  foreach ($terminals as $t) {
    $s = overlap_score($nt, $t['tokens']);
    if ($s > 0) $scored[] = ['name' => $t['name'], 'score' => $s];
  }
  usort($scored, function ($x, $y) { return ($y['score'] <=> $x['score']); });
  $out = [];
  for ($i = 0; $i < min($limit, count($scored)); $i++) {
    $top = $scored[0]['score'] ?? 1;
    $conf = min(0.90, max(0.35, ($scored[$i]['score'] / max(1, $top))));
    $out[] = ['name' => $scored[$i]['name'], 'confidence' => $conf, 'reason' => 'token_match:' . $needle];
  }
  return $out;
}

function cli_arg(array $argv, string $key, string $default = ''): string {
  foreach ($argv as $a) {
    if (strpos($a, $key . '=') === 0) return substr($a, strlen($key) + 1);
  }
  return $default;
}

$inDir = cli_arg($argv, '--dir', __DIR__ . '/../../routesandterminal');
$inDir = rtrim($inDir, "/\\");
$termPath = $inDir . DIRECTORY_SEPARATOR . 'terminals.csv';
$routesPath = $inDir . DIRECTORY_SEPARATOR . 'routes_lptrp.csv';

$forceAll = cli_arg($argv, '--forceAll', '0') === '1';

$termRows = read_csv_assoc($termPath);
$routeRows = read_csv_assoc($routesPath);

$terminals = [];
foreach ($termRows as $r) {
  $name = trim((string)($r['name'] ?? ''));
  $type = trim((string)($r['type'] ?? 'Terminal'));
  if ($type !== 'Terminal') continue;
  if ($name === '') continue;
  $loc = (string)($r['location'] ?? '');
  $addr = (string)($r['address'] ?? '');
  $hay = norm($name . ' ' . $loc . ' ' . $addr);
  $terminals[$name] = [
    'name' => $name,
    'location' => $loc,
    'address' => $addr,
    'hay' => $hay,
    'tokens' => tokenize($name . ' ' . $loc . ' ' . $addr),
  ];
}
$terminals = array_values($terminals);

$terminalByKey = [];
foreach ($terminals as $t) $terminalByKey[norm($t['name'])] = $t['name'];

$aliasToTerminals = function (string $place) use ($terminalByKey): array {
  $p = norm($place);
  $map = [
    'monumento' => ['MCU/Monumento Terminal','Monumento Central Terminal'],
    'mcu' => ['MCU/Monumento Terminal'],
    'monumento circle' => ['Monumento Central Terminal','MCU/Monumento Terminal'],
    'bagong silang' => ['Bagong Silang Terminal'],
    'bagong barrio' => ['Bagong Barrio Terminal'],
    'grace park' => ['Grace Park Terminal'],
    'camarin' => ['Camarin Terminal','Camarin Crossroad Terminal'],
    'deparo' => ['Deparo Terminal'],
    'tala' => ['Tala Terminal'],
    'sangandaan' => ['Sangandaan Terminal'],
    'novaliches' => ['Novaliches Bayan Terminal'],
    'novaliches bayan' => ['Novaliches Bayan Terminal'],
    'zabarte' => ['Almar Zabarte Terminal'],
    'zabarte road' => ['Almar Zabarte Terminal'],
    'central integrated terminal' => ['Central Integrated Terminal'],
    'cit' => ['Central Integrated Terminal'],
    'c i t' => ['Central Integrated Terminal'],
    'sm north edsa' => ['Central Integrated Terminal'],
    'sm north' => ['Central Integrated Terminal'],
    'trinoma' => ['Central Integrated Terminal'],
    'north ave' => ['Central Integrated Terminal'],
    'barangay 101' => ['Barangay 101 Tricycle Hub'],
    'bagumbong' => ['Deparo Terminal','Camarin Terminal','Camarin Crossroad Terminal'],
  ];
  foreach ($map as $k => $vals) {
    if ($p === $k) return $vals;
  }
  foreach ($map as $k => $vals) {
    if ($k === 'cit' || $k === 'c i t') continue;
    if ($k !== '' && strpos($p, $k) !== false) return $vals;
  }
  if (isset($terminalByKey[$p])) return [$terminalByKey[$p]];
  return [];
};

$routes = [];
foreach ($routeRows as $r) {
  $routeId = trim((string)($r['route_id'] ?? ''));
  if ($routeId === '') continue;
  $routes[] = [
    'route_id' => $routeId,
    'route_name' => (string)($r['route_name'] ?? ''),
    'vehicle_type' => (string)($r['vehicle_type'] ?? ''),
    'origin' => (string)($r['origin'] ?? ''),
    'destination' => (string)($r['destination'] ?? ''),
    'via' => (string)($r['via'] ?? ''),
  ];
}

$seedRows = [];
$usedTerminalNames = [];
$usedRouteIds = [];

function add_mapping(string $terminalName, string $routeId, string $source, string $confidence, array &$seedRows, array &$usedTerminalNames, array &$usedRouteIds, array &$seen): void {
  $k = $terminalName . '|' . $routeId;
  if (isset($seen[$k])) return;
  $seen[$k] = true;
  $seedRows[] = [
    'terminal_name' => $terminalName,
    'route_id' => $routeId,
    'source' => $source,
    'confidence' => $confidence,
  ];
  $usedTerminalNames[$terminalName] = true;
  $usedRouteIds[$routeId] = true;
}

$seen = [];

$routeSearchHay = [];
foreach ($routes as $rr) {
  $routeSearchHay[$rr['route_id']] = norm($rr['route_id'] . ' ' . $rr['route_name'] . ' ' . $rr['origin'] . ' ' . $rr['destination'] . ' ' . $rr['via'] . ' ' . $rr['vehicle_type']);
}

foreach ($routes as $r) {
  $rid = $r['route_id'];
  $origin = trim((string)$r['origin']);
  $dest = trim((string)$r['destination']);
  $viaStops = split_via((string)$r['via']);

  $picked = [];

  foreach ($aliasToTerminals($origin) as $tn) $picked[$tn] = ['source' => 'origin_alias', 'confidence' => '0.95'];
  foreach ($aliasToTerminals($dest) as $tn) $picked[$tn] = ['source' => 'dest_alias', 'confidence' => '0.95'];
  foreach ($aliasToTerminals((string)$r['route_name']) as $tn) $picked[$tn] = ['source' => 'name_alias', 'confidence' => '0.75'];

  if (!$picked && $origin !== '') {
    foreach (best_matches_by_text($origin, $terminals, 3) as $m) $picked[$m['name']] = ['source' => $m['reason'], 'confidence' => number_format($m['confidence'], 2)];
  }
  if ($dest !== '') {
    foreach (best_matches_by_text($dest, $terminals, 2) as $m) $picked[$m['name']] = ['source' => $m['reason'], 'confidence' => number_format(min(0.85, $m['confidence']), 2)];
  }

  foreach ($viaStops as $stop) {
    foreach ($aliasToTerminals($stop) as $tn) $picked[$tn] = ['source' => 'via_alias', 'confidence' => '0.70'];
  }

  foreach ($picked as $tn => $meta) {
    add_mapping($tn, $rid, (string)$meta['source'], (string)$meta['confidence'], $seedRows, $usedTerminalNames, $usedRouteIds, $seen);
  }
}

$unmappedTerminalRows = [];
foreach ($terminals as $t) {
  if (!isset($usedTerminalNames[$t['name']])) {
    $unmappedTerminalRows[] = [
      'terminal_name' => $t['name'],
      'location' => $t['location'],
      'address' => $t['address'],
    ];
  }
}

$unmappedRouteRows = [];
foreach ($routes as $r) {
  if (!isset($usedRouteIds[$r['route_id']])) {
    $unmappedRouteRows[] = [
      'route_id' => $r['route_id'],
      'route_name' => $r['route_name'],
      'origin' => $r['origin'],
      'destination' => $r['destination'],
      'via' => $r['via'],
      'vehicle_type' => $r['vehicle_type'],
    ];
  }
}

$outSeedStrict = $inDir . DIRECTORY_SEPARATOR . 'terminal_routes_seed_real_life.csv';
$outSeedForce = $inDir . DIRECTORY_SEPARATOR . 'terminal_routes_seed_force_all.csv';
$outUnmappedTerm = $inDir . DIRECTORY_SEPARATOR . 'unmapped_terminals.csv';
$outUnmappedRoutes = $inDir . DIRECTORY_SEPARATOR . 'unmapped_routes.csv';

write_csv($outSeedStrict, ['terminal_name','route_id','source','confidence'], $seedRows);
write_csv($outUnmappedTerm, ['terminal_name','location','address'], $unmappedTerminalRows);
write_csv($outUnmappedRoutes, ['route_id','route_name','origin','destination','via','vehicle_type'], $unmappedRouteRows);

if ($forceAll) {
  $seedRowsForce = $seedRows;
  $usedTerminalNamesForce = $usedTerminalNames;
  $usedRouteIdsForce = $usedRouteIds;
  $seenForce = $seen;

  foreach ($routes as $r) {
    $rid = $r['route_id'];
    if (isset($usedRouteIdsForce[$rid])) continue;
    $hay = $routeSearchHay[$rid] ?? '';
    $best = $terminals[0]['name'] ?? null;
    $bestScore = -1;
    foreach ($terminals as $t) {
      $score = overlap_score($t['tokens'], tokenize($hay));
      if ($score > $bestScore) { $bestScore = $score; $best = $t['name']; }
    }
    if ($best) add_mapping($best, $rid, 'force_best_guess', $bestScore > 0 ? '0.35' : '0.20', $seedRowsForce, $usedTerminalNamesForce, $usedRouteIdsForce, $seenForce);
  }

  foreach ($terminals as $t) {
    if (isset($usedTerminalNamesForce[$t['name']])) continue;
    $bestRid = $routes[0]['route_id'] ?? '';
    $bestScore = -1;
    foreach ($routeSearchHay as $rid => $hay) {
      $score = overlap_score($t['tokens'], tokenize($hay));
      if ($score > $bestScore) { $bestScore = $score; $bestRid = $rid; }
    }
    if ($bestRid !== '') add_mapping($t['name'], $bestRid, 'force_terminal_guess', $bestScore > 0 ? '0.35' : '0.20', $seedRowsForce, $usedTerminalNamesForce, $usedRouteIdsForce, $seenForce);
  }

  write_csv($outSeedForce, ['terminal_name','route_id','source','confidence'], $seedRowsForce);
}

fwrite(STDOUT, "Wrote: " . $outSeedStrict . PHP_EOL);
fwrite(STDOUT, "Unmapped terminals: " . count($unmappedTerminalRows) . PHP_EOL);
fwrite(STDOUT, "Unmapped routes: " . count($unmappedRouteRows) . PHP_EOL);
