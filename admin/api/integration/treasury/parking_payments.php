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
$where = "WHERE status='Paid'";
$types = '';
$params = [];
if ($onlyUnexported) $where .= " AND (exported_to_treasury IS NULL OR exported_to_treasury=0)";
if ($from !== '') {
  $where .= " AND created_at >= ?";
  $types .= 's';
  $params[] = $from;
}

$sql = "SELECT id, parking_area_id, terminal_id, vehicle_plate, amount, transaction_type, status, receipt_ref, payment_channel, external_payment_id, paid_at, created_at
        FROM parking_transactions $where
        ORDER BY created_at ASC
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
    'transaction_id' => (string)($r['id'] ?? ''),
    'amount' => (float)($r['amount'] ?? 0),
    'description' => (string)($r['transaction_type'] ?? 'Parking Fee'),
    'payer' => ['vehicle_plate' => (string)($r['vehicle_plate'] ?? '')],
    'receipt_ref' => (string)($r['receipt_ref'] ?? ''),
    'payment_channel' => (string)($r['payment_channel'] ?? ''),
    'date_paid' => (string)(($r['paid_at'] ?? '') !== '' ? $r['paid_at'] : ($r['created_at'] ?? '')),
    'metadata' => [
      'parking_area_id' => (string)($r['parking_area_id'] ?? ''),
      'terminal_id' => (string)($r['terminal_id'] ?? ''),
      'external_payment_id' => (string)($r['external_payment_id'] ?? ''),
    ]
  ];
}

if ($types !== '') $stmt->close();

echo json_encode(['ok' => true, 'count' => count($rows), 'data' => $rows]);

