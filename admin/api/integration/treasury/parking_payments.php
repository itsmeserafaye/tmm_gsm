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
$where = "WHERE 1=1";
$types = '';
$params = [];
if ($onlyUnexported) $where .= " AND (pp.exported_to_treasury IS NULL OR pp.exported_to_treasury=0)";
if ($from !== '') {
  $where .= " AND pp.paid_at >= ?";
  $types .= 's';
  $params[] = $from;
}

$sql = "SELECT pp.payment_id, pp.amount, pp.or_no, pp.paid_at, v.plate_number, ps.terminal_id
        FROM parking_payments pp
        JOIN vehicles v ON v.id=pp.vehicle_id
        JOIN parking_slots ps ON ps.slot_id=pp.slot_id
        $where
        ORDER BY pp.paid_at ASC
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
  $rows[] = [
    'transaction_type' => 'PARKING_FEE',
    'transaction_id' => (string)($r['payment_id'] ?? ''),
    'amount' => (float)($r['amount'] ?? 0),
    'description' => 'Parking Fee',
    'payer' => ['vehicle_plate' => (string)($r['plate_number'] ?? '')],
    'official_receipt_no' => (string)($r['or_no'] ?? ''),
    'date_paid' => (string)($r['paid_at'] ?? ''),
    'metadata' => [
      'terminal_id' => (string)($r['terminal_id'] ?? ''),
    ]
  ];
}

if ($types !== '') $stmt->close();

echo json_encode(['ok' => true, 'count' => count($rows), 'data' => $rows]);

