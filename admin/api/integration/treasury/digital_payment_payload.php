<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/util.php';
require_once __DIR__ . '/_auth.php';

$db = db();
header('Content-Type: application/json');
tmm_treasury_integration_authorize();

$kind = strtolower(trim((string)($_GET['kind'] ?? 'ticket')));
if ($kind !== 'ticket') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'unsupported_kind']);
  exit;
}

$transactionId = trim((string)($_GET['transaction_id'] ?? ($_GET['ticket_number'] ?? '')));
if ($transactionId === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_transaction_id']);
  exit;
}

$stmt = $db->prepare("SELECT t.ticket_number, t.fine_amount, t.status, t.driver_name, t.vehicle_plate, vt.description AS violation_desc
                      FROM tickets t
                      LEFT JOIN violation_types vt ON vt.violation_code = t.violation_code
                      WHERE t.ticket_number=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('s', $transactionId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'transaction_not_found']);
  exit;
}

$amount = (float)($row['fine_amount'] ?? 0);
$desc = trim((string)($row['violation_desc'] ?? 'Traffic violation'));
$payerName = trim((string)($row['driver_name'] ?? ''));
$plate = trim((string)($row['vehicle_plate'] ?? ''));

$systemCode = trim((string)getenv('TMM_TREASURY_SYSTEM_CODE'));
if ($systemCode === '') $systemCode = 'tmm';

$base = tmm_public_base_url();
$callback = $base !== '' ? ($base . '/admin/api/integration/treasury/digital_payment_callback.php') : '/admin/api/integration/treasury/digital_payment_callback.php';
$key = (string)getenv('TMM_TREASURY_INTEGRATION_KEY');
if ($key !== '') {
  $callback .= (strpos($callback, '?') === false ? '?' : '&') . 'integration_key=' . rawurlencode($key);
}

$purposeParts = [];
$purposeParts[] = 'Traffic Fine';
if ($desc !== '') $purposeParts[] = $desc;
if ($transactionId !== '') $purposeParts[] = 'Ticket ' . $transactionId;
if ($plate !== '') $purposeParts[] = $plate;
if ($payerName !== '') $purposeParts[] = $payerName;
$purpose = implode(': ', array_filter($purposeParts, fn($v) => $v !== ''));

echo json_encode([
  'ok' => true,
  'system' => $systemCode,
  'ref' => (string)($row['ticket_number'] ?? $transactionId),
  'amount' => $amount,
  'purpose' => $purpose,
  'callback' => $callback,
]);

