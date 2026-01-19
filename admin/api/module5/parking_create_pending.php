<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('parking.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$parkingAreaId = isset($_POST['parking_area_id']) && $_POST['parking_area_id'] !== '' ? (int)$_POST['parking_area_id'] : null;
$plate = strtoupper(trim((string)($_POST['vehicle_plate'] ?? '')));
$amount = (float)($_POST['amount'] ?? 0);
$durationHours = (int)($_POST['duration_hours'] ?? 0);
$chargeType = trim((string)($_POST['charge_type'] ?? 'Usage Fee'));

if ($plate === '' || $amount <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_fields']);
  exit;
}

$terminalId = null;
if ($parkingAreaId !== null && $parkingAreaId > 0) {
  $stmtT = $db->prepare("SELECT terminal_id FROM parking_areas WHERE id=? LIMIT 1");
  if ($stmtT) {
    $stmtT->bind_param('i', $parkingAreaId);
    $stmtT->execute();
    $rowT = $stmtT->get_result()->fetch_assoc();
    $stmtT->close();
    if ($rowT && isset($rowT['terminal_id']) && $rowT['terminal_id'] !== null && $rowT['terminal_id'] !== '') {
      $terminalId = (int)$rowT['terminal_id'];
    }
  }
}

$cols = [];
$placeholders = [];
$types = '';
$params = [];

$cols[] = 'parking_area_id'; $placeholders[] = '?'; $types .= 'i'; $params[] = $parkingAreaId;
$cols[] = 'terminal_id'; $placeholders[] = '?'; $types .= 'i'; $params[] = $terminalId;
$cols[] = 'amount'; $placeholders[] = '?'; $types .= 'd'; $params[] = $amount;
$cols[] = 'transaction_type'; $placeholders[] = '?'; $types .= 's'; $params[] = $chargeType !== '' ? $chargeType : 'Usage Fee';
$cols[] = 'vehicle_plate'; $placeholders[] = '?'; $types .= 's'; $params[] = $plate;
$cols[] = 'status'; $placeholders[] = "'Pending Payment'";

$hasDuration = (($db->query("SHOW COLUMNS FROM parking_transactions LIKE 'duration_hours'")->num_rows ?? 0) > 0);
if ($hasDuration) {
  $cols[] = 'duration_hours'; $placeholders[] = '?'; $types .= 'i'; $params[] = max(0, $durationHours);
}

$sql = "INSERT INTO parking_transactions (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}

if ($types !== '') $stmt->bind_param($types, ...$params);
$ok = $stmt->execute();
$newId = (int)$db->insert_id;
$stmt->close();

if (!$ok || $newId <= 0) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'insert_failed']);
  exit;
}

echo json_encode(['ok' => true, 'transaction_id' => $newId]);

