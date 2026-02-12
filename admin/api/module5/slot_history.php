<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
 
$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.manage_terminal','module5.parking_fees','module5.read']);
 
$terminalId = (int)($_GET['terminal_id'] ?? 0);
if ($terminalId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_terminal_id']); exit; }
 
$limit = (int)($_GET['limit'] ?? 100);
if ($limit <= 0) $limit = 100;
if ($limit > 500) $limit = 500;
 
$q = trim((string)($_GET['q'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
 
$where = "e.terminal_id=?";
$types = 'i';
$params = [$terminalId];
 
if ($q !== '') {
  $where .= " AND (e.plate_number LIKE ? OR e.or_no LIKE ? OR ps.slot_no LIKE ?)";
  $like = '%' . $q . '%';
  $types .= 'sss';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
  $where .= " AND e.time_in >= ?";
  $types .= 's';
  $params[] = $from . ' 00:00:00';
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
  $where .= " AND e.time_in <= ?";
  $types .= 's';
  $params[] = $to . ' 23:59:59';
}
 
$sql = "SELECT
          e.event_id,
          e.slot_id,
          ps.slot_no,
          e.vehicle_id,
          e.plate_number,
          e.payment_id,
          e.or_no,
          e.amount,
          e.time_in,
          e.time_out,
          e.occupied_by_name,
          e.released_by_name
        FROM parking_slot_events e
        LEFT JOIN parking_slots ps ON ps.slot_id=e.slot_id
        WHERE {$where}
        ORDER BY e.time_in DESC, e.event_id DESC
        LIMIT {$limit}";
 
$stmt = $db->prepare($sql);
if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && ($r = $res->fetch_assoc())) {
  $rows[] = [
    'event_id' => (int)($r['event_id'] ?? 0),
    'slot_id' => (int)($r['slot_id'] ?? 0),
    'slot_no' => (string)($r['slot_no'] ?? ''),
    'vehicle_id' => isset($r['vehicle_id']) ? (int)$r['vehicle_id'] : null,
    'plate_number' => (string)($r['plate_number'] ?? ''),
    'payment_id' => isset($r['payment_id']) ? (int)$r['payment_id'] : null,
    'or_no' => (string)($r['or_no'] ?? ''),
    'amount' => isset($r['amount']) ? (float)$r['amount'] : null,
    'time_in' => (string)($r['time_in'] ?? ''),
    'time_out' => (string)($r['time_out'] ?? ''),
    'occupied_by' => (string)($r['occupied_by_name'] ?? ''),
    'released_by' => (string)($r['released_by_name'] ?? ''),
  ];
}
$stmt->close();
 
echo json_encode(['ok'=>true,'data'=>$rows]);
?>
