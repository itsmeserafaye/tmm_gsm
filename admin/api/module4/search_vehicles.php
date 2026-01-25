<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');

$q = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 25);
if ($limit <= 0) $limit = 25;
if ($limit > 50) $limit = 50;

$sql = "SELECT v.id, v.plate_number, v.engine_no, v.chassis_no,
               COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '-') AS operator_name
        FROM vehicles v
        LEFT JOIN operators o ON o.id=COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0))
        WHERE COALESCE(v.record_status,'') <> 'Archived'
          AND COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0), 0) > 0";

$params = [];
$types = '';
if ($q !== '') {
  $qq = '%' . $q . '%';
  $sql .= " AND (v.plate_number LIKE ? OR v.engine_no LIKE ? OR v.chassis_no LIKE ? OR o.name LIKE ? OR o.full_name LIKE ? OR v.operator_name LIKE ?)";
  $params = [$qq, $qq, $qq, $qq, $qq, $qq];
  $types = 'ssssss';
}
$sql .= " ORDER BY v.plate_number ASC LIMIT " . (int)$limit;

if ($types !== '') {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']); exit; }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
  $stmt->close();
  echo json_encode(['ok' => true, 'data' => $rows]);
  exit;
}

$res = $db->query($sql);
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
echo json_encode(['ok' => true, 'data' => $rows]);

