<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$areaType = trim((string)($_POST['area_type'] ?? ''));
$areaRef = trim((string)($_POST['area_ref'] ?? ''));
$observedAt = trim((string)($_POST['observed_at'] ?? ''));
$demandCount = (int)($_POST['demand_count'] ?? 0);

$areaType = $areaType === 'parking_area' ? 'route' : $areaType;
if (!in_array($areaType, ['terminal', 'route'], true)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_area_type']);
  exit;
}
if ($areaRef === '' || $observedAt === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}
if ($demandCount < 0) $demandCount = 0;

$areaOk = false;
if ($areaType === 'terminal') {
  $stmt = $db->prepare("SELECT 1 FROM terminals WHERE id=? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('s', $areaRef);
    $stmt->execute();
    $areaOk = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
  }
} else {
  $stmt = $db->prepare("SELECT 1 FROM routes WHERE route_id=? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('s', $areaRef);
    $stmt->execute();
    $areaOk = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
  }
}
if (!$areaOk) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'unknown_area_ref']);
  exit;
}

$dt = date('Y-m-d H:00:00', strtotime($observedAt));
$stmt = $db->prepare("INSERT INTO puv_demand_observations (area_type, area_ref, observed_at, demand_count, source)
  VALUES (?, ?, ?, ?, 'manual')
  ON DUPLICATE KEY UPDATE demand_count=VALUES(demand_count), source=VALUES(source)");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('sssi', $areaType, $areaRef, $dt, $demandCount);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => (bool)$ok]);

