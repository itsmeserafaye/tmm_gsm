<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write','module1.vehicles.write']);

$db = db();
header('Content-Type: application/json');

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit <= 0) $limit = 200;
if ($limit > 1000) $limit = 1000;

$sql = "SELECT
  t.transfer_id,
  t.vehicle_id,
  v.plate_number,
  t.from_operator_id,
  COALESCE(NULLIF(ofr.registered_name,''), NULLIF(ofr.name,''), ofr.full_name) AS from_operator_name,
  t.to_operator_id,
  COALESCE(NULLIF(oto.registered_name,''), NULLIF(oto.name,''), oto.full_name) AS to_operator_name,
  t.transfer_type,
  t.lto_reference_no,
  t.deed_of_sale_path,
  t.orcr_path,
  t.status,
  t.effective_date,
  t.reviewed_by,
  t.reviewed_at,
  t.remarks,
  t.created_at
FROM vehicle_ownership_transfers t
JOIN vehicles v ON v.id=t.vehicle_id
LEFT JOIN operators ofr ON ofr.id=t.from_operator_id
LEFT JOIN operators oto ON oto.id=t.to_operator_id";

$conds = [];
$params = [];
$types = '';

if ($q !== '') {
  $qNoDash = preg_replace('/[^A-Za-z0-9]/', '', $q);
  $conds[] = "(v.plate_number LIKE ? OR REPLACE(v.plate_number,'-','') LIKE ? OR ofr.name LIKE ? OR ofr.full_name LIKE ? OR ofr.registered_name LIKE ? OR oto.name LIKE ? OR oto.full_name LIKE ? OR oto.registered_name LIKE ?)";
  $like = "%$q%";
  $params[] = $like;
  $params[] = "%$qNoDash%";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'ssssssss';
}
if ($status !== '') {
  $allowed = ['Pending','Approved','Rejected'];
  $sel = '';
  foreach ($allowed as $a) { if (strcasecmp($status, $a) === 0) { $sel = $a; break; } }
  if ($sel !== '') {
    $conds[] = "t.status=?";
    $params[] = $sel;
    $types .= 's';
  }
}

if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY t.created_at DESC, t.transfer_id DESC LIMIT " . (int)$limit;

if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$rows = [];
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
if (isset($stmt) && $stmt) $stmt->close();
echo json_encode(['ok' => true, 'data' => $rows]);
