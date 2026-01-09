<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder','Inspector']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'invalid_method']);
  exit;
}
$terminalId = (int)($_POST['terminal_id'] ?? 0);
$routeId = trim($_POST['route_id'] ?? '');
$gran = (int)($_POST['granularity_min'] ?? 60);
$lookback = (int)($_POST['lookback_hours'] ?? 24);
if ($terminalId <= 0 || $routeId === '') {
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}
$granSec = max(60, $gran) * 60;
$stmtF = $db->prepare("SELECT ts, forecast_trips FROM demand_forecasts WHERE terminal_id=? AND route_id=? AND ts >= DATE_SUB(NOW(), INTERVAL ? HOUR) AND ts <= NOW() ORDER BY ts");
$stmtF->bind_param('isi', $terminalId, $routeId, $lookback);
$stmtF->execute();
$resF = $stmtF->get_result();
$fmap = [];
while ($row = $resF->fetch_assoc()) {
  $ts = $row['ts'];
  $y = isset($row['forecast_trips']) ? (double)$row['forecast_trips'] : null;
  if ($ts && $y !== null) { $fmap[$ts] = $y; }
}
$stmtA = $db->prepare("SELECT FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(l.time_in)/$granSec)*$granSec) AS slot_ts, COUNT(*) AS trips FROM terminal_logs l JOIN vehicles v ON v.plate_number=l.vehicle_plate WHERE l.activity_type='Dispatch' AND l.terminal_id=? AND v.route_id=? AND l.time_in >= DATE_SUB(NOW(), INTERVAL ? HOUR) AND l.time_in <= NOW() GROUP BY slot_ts ORDER BY slot_ts");
$stmtA->bind_param('isi', $terminalId, $routeId, $lookback);
$stmtA->execute();
$resA = $stmtA->get_result();
$pairs = [];
while ($row = $resA->fetch_assoc()) {
  $ts = $row['slot_ts'];
  $a = (int)($row['trips'] ?? 0);
  if (isset($fmap[$ts])) {
    $pairs[] = ['ts'=>$ts, 'actual'=>$a, 'forecast'=>$fmap[$ts]];
  }
}
if (empty($pairs)) {
  echo json_encode(['ok'=>false,'error'=>'no_overlap']);
  exit;
}
$sumAbsPct = 0.0;
$sumSq = 0.0;
$n = 0;
foreach ($pairs as $p) {
  $a = (double)$p['actual'];
  $f = (double)$p['forecast'];
  $sumSq += ($a - $f) * ($a - $f);
  $den = $a <= 0 ? 1.0 : $a;
  $sumAbsPct += abs($a - $f) / $den;
  $n++;
}
$mape = $n > 0 ? ($sumAbsPct / $n) : null;
$rmse = $n > 0 ? sqrt($sumSq / $n) : null;
$acc = $mape !== null ? max(0.0, 1.0 - $mape) : null;
echo json_encode(['ok'=>true,'terminal_id'=>$terminalId,'route_id'=>$routeId,'granularity_min'=>$gran,'lookback_hours'=>$lookback,'mape'=>$mape,'rmse'=>$rmse,'accuracy'=>$acc,'pairs'=>$pairs]);
?> 
