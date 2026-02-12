<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.manage_terminal','module5.parking_fees']);

$terminalId = isset($_GET['terminal_id']) ? (int)$_GET['terminal_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$onlyUnexported = (string)($_GET['unexported'] ?? '') === '1';
$q = trim((string)($_GET['q'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$exported = trim((string)($_GET['exported'] ?? ''));

if ($limit <= 0) $limit = 50;
if ($limit > 500) $limit = 500;
if ($offset < 0) $offset = 0;

if ($terminalId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_terminal_id']);
  exit;
}

$where = "WHERE ps.terminal_id=?";
$types = "i";
$params = [$terminalId];

if ($onlyUnexported) {
  $where .= " AND (pp.exported_to_treasury IS NULL OR pp.exported_to_treasury=0)";
}
if ($exported !== '') {
  if ($exported === 'exported') {
    $where .= " AND COALESCE(pp.exported_to_treasury,0)=1";
  } elseif ($exported === 'pending') {
    $where .= " AND COALESCE(pp.exported_to_treasury,0)=0";
  }
}
if ($from !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $from)) {
  $where .= " AND pp.paid_at >= ?";
  $types .= "s";
  $params[] = $from . " 00:00:00";
}
if ($to !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $to)) {
  $where .= " AND pp.paid_at <= ?";
  $types .= "s";
  $params[] = $to . " 23:59:59";
}
if ($q !== '') {
  $where .= " AND (v.plate_number LIKE ? OR pp.or_no LIKE ? OR ps.slot_no LIKE ? OR t.name LIKE ?)";
  $like = '%' . $q . '%';
  $types .= "ssss";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

$sql = "SELECT
  pp.payment_id,
  pp.slot_id,
  pp.amount,
  pp.or_no,
  pp.paid_at,
  pp.exported_to_treasury,
  pp.exported_at,
  v.plate_number,
  ps.slot_no,
  t.name AS terminal_name,
  t.type AS terminal_type
FROM parking_payments pp
JOIN vehicles v ON v.id=pp.vehicle_id
JOIN parking_slots ps ON ps.slot_id=pp.slot_id
JOIN terminals t ON t.id=ps.terminal_id
$where
ORDER BY pp.paid_at DESC
LIMIT ? OFFSET ?";

$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && ($r = $res->fetch_assoc())) {
  $rows[] = $r;
}
$stmt->close();

echo json_encode(['ok' => true, 'data' => $rows]);
