<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.read','module1.write','module1.view','module1.vehicles.write']);

$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['operator_type'] ?? ($_GET['type'] ?? '')));
$status = trim((string)($_GET['status'] ?? ''));
$limit = (int)($_GET['limit'] ?? 200);
if ($limit <= 0) $limit = 200;
if ($limit > 500) $limit = 500;

$sql = "SELECT id AS operator_id, operator_type, COALESCE(NULLIF(registered_name,''), NULLIF(name,''), full_name) AS name, address, contact_no, email, verification_status, created_at FROM operators";
$conds = [];
$params = [];
$types = '';

if ($q !== '') {
  $conds[] = "(registered_name LIKE ? OR name LIKE ? OR full_name LIKE ? OR contact_no LIKE ? OR email LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types .= 'sssss';
}
if ($type !== '' && $type !== 'Type') {
  $conds[] = "operator_type=?";
  $params[] = $type;
  $types .= 's';
}
if ($status !== '' && $status !== 'Status') {
  $conds[] = "verification_status=?";
  $params[] = $status;
  $types .= 's';
}

if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY created_at DESC LIMIT ?";
$params[] = $limit;
$types .= 'i';

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
while ($row = $res->fetch_assoc()) $rows[] = $row;
$stmt->close();

echo json_encode(['ok' => true, 'data' => $rows]);
