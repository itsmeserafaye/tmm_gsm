<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.endorse','module2.approve']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$routeId = (int)($_POST['route_id'] ?? 0);
$fareRaw = trim((string)($_POST['fare'] ?? ''));
if ($routeId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_route_id']);
  exit;
}

$fare = null;
if ($fareRaw !== '') {
  $fareVal = (float)$fareRaw;
  if ($fareVal < 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_fare']);
    exit;
  }
  $fare = $fareVal;
}

if ($fare === null) {
  $stmt = $db->prepare("UPDATE routes SET fare=NULL WHERE id=?");
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param('i', $routeId);
} else {
  $stmt = $db->prepare("UPDATE routes SET fare=? WHERE id=?");
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param('di', $fare, $routeId);
}
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => (bool)$ok]);

