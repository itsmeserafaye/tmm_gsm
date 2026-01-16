<?php
require_once __DIR__ . '/../../../includes/db.php';
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

$ticketNo = trim((string)($_GET['ticket_number'] ?? ($_GET['transaction_id'] ?? '')));
if ($ticketNo === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_ticket_number']);
  exit;
}

$stmt = $db->prepare("SELECT t.ticket_id, t.ticket_number, t.date_issued, t.violation_code, t.vehicle_plate, t.driver_name, t.fine_amount, t.due_date, t.location, t.status,
                             vt.description AS violation_desc, vt.category AS violation_category, vt.sts_equivalent_code
                      FROM tickets t
                      LEFT JOIN violation_types vt ON vt.violation_code = t.violation_code
                      WHERE t.ticket_number=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('s', $ticketNo);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'ticket_not_found']);
  exit;
}

$amount = (float)($row['fine_amount'] ?? 0);
$due = (string)($row['due_date'] ?? '');
$payerName = trim((string)($row['driver_name'] ?? ''));
if ($payerName === '') $payerName = 'Driver (Unknown)';

$desc = trim((string)($row['violation_desc'] ?? ''));
if ($desc === '') $desc = 'Traffic violation';

echo json_encode([
  'ok' => true,
  'transaction_type' => 'TRAFFIC_FINE',
  'transaction_id' => (string)($row['ticket_number'] ?? $ticketNo),
  'account_code' => (string)($row['sts_equivalent_code'] ?? ''),
  'amount' => $amount,
  'description' => 'Violation: ' . $desc,
  'payer' => [
    'name' => $payerName,
    'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
  ],
  'due_date' => $due,
  'metadata' => [
    'ticket_status' => (string)($row['status'] ?? ''),
    'date_issued' => (string)($row['date_issued'] ?? ''),
    'violation_code' => (string)($row['violation_code'] ?? ''),
    'violation_category' => (string)($row['violation_category'] ?? ''),
    'location' => (string)($row['location'] ?? ''),
  ],
]);

