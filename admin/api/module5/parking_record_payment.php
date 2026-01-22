<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.parking_fees','module3.settle']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$parkingAreaId = isset($_POST['parking_area_id']) && $_POST['parking_area_id'] !== '' ? (int)$_POST['parking_area_id'] : null;
$plate = strtoupper(trim((string)($_POST['vehicle_plate'] ?? '')));
$amount = (float)($_POST['amount'] ?? 0);
$chargeType = trim((string)($_POST['charge_type'] ?? 'Usage Fee'));
$receiptRef = trim((string)($_POST['receipt_ref'] ?? ($_POST['official_receipt_no'] ?? '')));
$paymentChannel = trim((string)($_POST['payment_channel'] ?? ''));
$externalPaymentId = trim((string)($_POST['external_payment_id'] ?? ''));

if ($plate === '' || $amount <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_fields']);
  exit;
}
if ($receiptRef === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'receipt_required']);
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

$sql = "INSERT INTO parking_transactions (parking_area_id, terminal_id, amount, transaction_type, vehicle_plate, status, receipt_ref, payment_channel, external_payment_id, paid_at)
        VALUES (?, ?, ?, ?, ?, 'Paid', ?, ?, ?, NOW())";
$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('iidsssss', $parkingAreaId, $terminalId, $amount, $chargeType, $plate, $receiptRef, $paymentChannel, $externalPaymentId);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => (bool)$ok]);
