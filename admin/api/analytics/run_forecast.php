<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/analytics_helper.php';
$db = db();
require_role(['Admin','Encoder','Inspector']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'invalid_method']);
  exit;
}

$terminalId = isset($_POST['terminal_id']) ? (int)$_POST['terminal_id'] : 0;
$routeId = trim($_POST['route_id'] ?? '');
$horizonMin = (int)($_POST['horizon_min'] ?? 240);
$granMin = (int)($_POST['granularity_min'] ?? 60);

if ($terminalId <= 0 || $routeId === '') {
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}

$result = run_forecast_job($db, $terminalId, $routeId, $horizonMin, $granMin);
echo json_encode($result);
?>