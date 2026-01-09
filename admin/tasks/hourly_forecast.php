<?php
require_once __DIR__ . '/../includes/db.php';
$db = db();

$args = [];
if (php_sapi_name() === 'cli') {
  global $argv;
  foreach ($argv as $arg) {
    if (strpos($arg, '=') !== false) {
      [$k, $v] = explode('=', $arg, 2);
      $args[$k] = $v;
    }
  }
}
$terminalFilter = isset($args['terminal_id']) ? (int)$args['terminal_id'] : 0;
$routeFilter = isset($args['route_id']) ? trim((string)$args['route_id']) : '';

$pairs = [];
if ($terminalFilter > 0 && $routeFilter !== '') {
  $pairs[] = ['terminal_id' => $terminalFilter, 'route_id' => $routeFilter];
} else {
  $sql = "SELECT l.terminal_id, v.route_id
          FROM terminal_logs l
          JOIN vehicles v ON v.plate_number=l.vehicle_plate
          WHERE l.activity_type='Dispatch'
            AND l.time_in >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          GROUP BY l.terminal_id, v.route_id";
  $res = $db->query($sql);
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $tid = (int)($row['terminal_id'] ?? 0);
      $rid = trim((string)($row['route_id'] ?? ''));
      if ($tid > 0 && $rid !== '') { $pairs[] = ['terminal_id' => $tid, 'route_id' => $rid]; }
    }
  }
  if (empty($pairs)) {
    $trow = $db->query("SELECT id FROM terminals ORDER BY id LIMIT 1");
    $rrow = $db->query("SELECT route_id FROM routes ORDER BY route_id LIMIT 1");
    $tid = $trow && ($tr=$trow->fetch_assoc()) ? (int)$tr['id'] : 1;
    $rid = $rrow && ($rr=$rrow->fetch_assoc()) ? trim((string)$rr['route_id']) : 'R-12';
    $pairs[] = ['terminal_id' => $tid, 'route_id' => $rid];
  }
}

$totalInserted = 0;
$okPairs = 0;
foreach ($pairs as $p) {
  $tid = (int)$p['terminal_id'];
  $rid = (string)$p['route_id'];
  $payload = http_build_query([
    'terminal_id' => $tid,
    'route_id' => $rid,
    'horizon_min' => 120,
    'granularity_min' => 60,
    'role' => 'Admin'
  ]);
  $opts = [
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
      'content' => $payload,
      'timeout' => 20
    ]
  ];
  $ctx = stream_context_create($opts);
  $res = @file_get_contents('http://127.0.0.1/tmm/admin/api/analytics/run_forecast.php', false, $ctx);
  if ($res !== false) {
    $j = json_decode($res, true);
    if (is_array($j) && ($j['ok'] ?? false)) {
      $okPairs++;
      $totalInserted += (int)($j['inserted'] ?? 0);
      echo "ok " . $tid . " " . $rid . " inserted " . (int)($j['inserted'] ?? 0) . PHP_EOL;
    } else {
      echo "fail " . $tid . " " . $rid . PHP_EOL;
    }
  } else {
    echo "error " . $tid . " " . $rid . PHP_EOL;
  }
}
echo "done pairs " . $okPairs . " inserted_total " . $totalInserted . PHP_EOL;
$capsPayload = http_build_query([
  'horizon_min' => 120,
  'theta' => 0.7,
  'min_confidence' => 0.6,
  'dry_run' => 'false',
  'role' => 'Admin'
]);
$capsOpts = [
  'http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'content' => $capsPayload,
    'timeout' => 20
  ]
];
$capsCtx = stream_context_create($capsOpts);
$capsRes = @file_get_contents('http://127.0.0.1/tmm/admin/api/analytics/compute_caps.php', false, $capsCtx);
if ($capsRes !== false) {
  $cj = json_decode($capsRes, true);
  if (is_array($cj) && ($cj['ok'] ?? false)) {
    $ins = (int)($cj['inserted'] ?? 0);
    echo "caps inserted " . $ins . PHP_EOL;
  } else {
    echo "caps fail" . PHP_EOL;
  }
} else {
  echo "caps error" . PHP_EOL;
}
?> 
