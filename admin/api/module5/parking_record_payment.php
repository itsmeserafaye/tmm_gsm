<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$parkingAreaId = isset($_POST['parking_area_id']) && $_POST['parking_area_id'] !== '' ? (int)$_POST['parking_area_id'] : null;
$plate = strtoupper(trim((string)($_POST['vehicle_plate'] ?? '')));
$amount = (float)($_POST['amount'] ?? 0);
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

$sql = "INSERT INTO parking_transactions (parking_area_id, terminal_id, amount, transaction_type, vehicle_plate, status)
        VALUES (?, ?, ?, ?, ?, 'Paid')";
$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('iidss', $parkingAreaId, $terminalId, $amount, $chargeType, $plate);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => (bool)$ok]);
