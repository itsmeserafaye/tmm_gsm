<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('parking.manage');

$id = (int)($_GET['transaction_id'] ?? ($_GET['id'] ?? 0));
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'transaction_id_required']);
  exit;
}

$stmt = $db->prepare("SELECT id, status, receipt_ref, reference_no, payment_method, payment_channel, paid_at, created_at
                      FROM parking_transactions
                      WHERE id=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'not_found']);
  exit;
}

echo json_encode(['ok' => true, 'transaction' => $row]);

