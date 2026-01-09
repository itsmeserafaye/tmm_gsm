<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/analytics_helper.php';
$db = db();
require_role(['Admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'invalid_method']);
  exit;
}

$routeFilter = trim($_POST['route_id'] ?? '');
$horizonMin = (int)($_POST['horizon_min'] ?? 240);
$theta = isset($_POST['theta']) ? (double)$_POST['theta'] : 0.7;
$minConfidence = isset($_POST['min_confidence']) ? (double)$_POST['min_confidence'] : 0.6;
$dryRun = strtolower(trim($_POST['dry_run'] ?? 'false')) === 'true';

$result = run_compute_caps_job($db, $routeFilter, $horizonMin, $theta, $minConfidence, $dryRun);
echo json_encode($result);
?>