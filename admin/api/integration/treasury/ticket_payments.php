<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/_auth.php';

$db = db();
header('Content-Type: application/json');
tmm_treasury_integration_authorize();

$unexported = (string)($_GET['unexported'] ?? '1');
$onlyUnexported = $unexported === '1' || strtolower($unexported) === 'true';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit <= 0) $limit = 200;
if ($limit > 1000) $limit = 1000;

$from = trim((string)($_GET['from'] ?? ''));
$where = "WHERE t.status='Settled' AND (p.receipt_ref IS NOT NULL AND p.receipt_ref <> '')";
$types = '';
$params = [];
if ($onlyUnexported) $where .= " AND (p.exported_to_treasury IS NULL OR p.exported_to_treasury=0)";
if ($from !== '') {
  $where .= " AND p.date_paid >= ?";
  $types .= 's';
  $params[] = $from;
}

$sql = "SELECT p.payment_id, p.amount_paid, p.date_paid, p.receipt_ref, p.payment_channel, p.external_payment_id, p.verified_by_treasury,
               t.ticket_number, t.external_ticket_number, t.vehicle_plate, t.driver_name, t.violation_code,
               vt.description AS violation_desc, vt.sts_equivalent_code
        FROM payment_records p
        JOIN tickets t ON t.ticket_id = p.ticket_id
        LEFT JOIN violation_types vt ON vt.violation_code = t.violation_code
        $where
        ORDER BY p.date_paid ASC
        LIMIT $limit";

if ($types !== '') {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$rows = [];
while ($res && ($r = $res->fetch_assoc())) {
  $ticketNo = (string)($r['ticket_number'] ?? '');
  $desc = trim((string)($r['violation_desc'] ?? 'Traffic violation'));
  $rows[] = [
    'transaction_type' => 'TRAFFIC_FINE',
    'transaction_id' => $ticketNo,
    'account_code' => (string)($r['sts_equivalent_code'] ?? ''),
    'amount' => (float)($r['amount_paid'] ?? 0),
    'description' => $desc !== '' ? ('Violation: ' . $desc) : 'Traffic violation',
    'payer' => [
      'name' => (string)($r['driver_name'] ?? ''),
      'vehicle_plate' => (string)($r['vehicle_plate'] ?? ''),
    ],
    'receipt_ref' => (string)($r['receipt_ref'] ?? ''),
    'payment_channel' => (string)($r['payment_channel'] ?? ''),
    'date_paid' => (string)($r['date_paid'] ?? ''),
    'metadata' => [
      'payment_id' => (string)($r['payment_id'] ?? ''),
      'external_ticket_number' => (string)($r['external_ticket_number'] ?? ''),
      'violation_code' => (string)($r['violation_code'] ?? ''),
      'external_payment_id' => (string)($r['external_payment_id'] ?? ''),
      'verified_by_treasury' => (int)($r['verified_by_treasury'] ?? 0),
    ],
  ];
}

if ($types !== '') $stmt->close();

echo json_encode(['ok' => true, 'count' => count($rows), 'data' => $rows]);

