<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/_auth.php';

$db = db();
header('Content-Type: application/json');
tmm_treasury_integration_authorize();

$kind = strtolower(trim((string)($_GET['kind'] ?? 'ticket')));

if ($kind !== 'ticket' && $kind !== 'parking') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'unsupported_kind']);
  exit;
}

if ($kind === 'parking') {
  $txId = (int)($_GET['parking_transaction_id'] ?? ($_GET['transaction_id'] ?? 0));
  if ($txId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_transaction_id']);
    exit;
  }

  $stmt = $db->prepare("SELECT t.id, t.amount, t.status, t.vehicle_plate, t.transaction_type, t.created_at, a.name AS area_name
                        FROM parking_transactions t
                        LEFT JOIN parking_areas a ON a.id = t.parking_area_id
                        WHERE t.id=? LIMIT 1");
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
  }
  $stmt->bind_param('i', $txId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'transaction_not_found']);
    exit;
  }

  $plate = trim((string)($row['vehicle_plate'] ?? ''));
  $type = trim((string)($row['transaction_type'] ?? 'Parking Fee'));
  $area = trim((string)($row['area_name'] ?? ''));

  echo json_encode([
    'ok' => true,
    'transaction_type' => 'PARKING_FEE',
    'transaction_id' => (string)($row['id'] ?? $txId),
    'account_code' => '',
    'amount' => (float)($row['amount'] ?? 0),
    'description' => $type . ($area !== '' ? (' - ' . $area) : ''),
    'payer' => [
      'vehicle_plate' => $plate,
    ],
    'due_date' => '',
    'metadata' => [
      'status' => (string)($row['status'] ?? ''),
      'created_at' => (string)($row['created_at'] ?? ''),
    ],
  ]);
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
                      WHERE t.ticket_number=? OR t.external_ticket_number=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('ss', $ticketNo, $ticketNo);
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

