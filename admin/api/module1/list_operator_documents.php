<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.view','module1.vehicles.write']);

$operatorId = isset($_GET['operator_id']) ? (int)$_GET['operator_id'] : 0;
if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
  exit;
}

$type = trim((string)($_GET['type'] ?? ''));
if ($type !== '') {
  $t = strtolower($type);
  if ($t === 'id' || $t === 'govid') $type = 'GovID';
  elseif ($t === 'cda') $type = 'CDA';
  elseif ($t === 'sec') $type = 'SEC';
  elseif ($t === 'barangaycert' || $t === 'barangay') $type = 'BarangayCert';
  elseif ($t === 'others' || $t === 'other') $type = 'Others';
}

$opRow = null;
$stmtOp = $db->prepare("SELECT id, operator_type, workflow_status, workflow_remarks FROM operators WHERE id=? LIMIT 1");
if ($stmtOp) {
  $stmtOp->bind_param('i', $operatorId);
  $stmtOp->execute();
  $opRow = $stmtOp->get_result()->fetch_assoc();
  $stmtOp->close();
}

$sql = "SELECT doc_id, doc_type, file_path, uploaded_at, doc_status, remarks, is_verified, verified_by, verified_at FROM operator_documents WHERE operator_id=?";
$params = [$operatorId];
$types = 'i';
if ($type !== '') {
  $sql .= " AND doc_type=?";
  $params[] = $type;
  $types .= 's';
}
$sql .= " ORDER BY uploaded_at DESC";

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

echo json_encode(['ok' => true, 'operator' => $opRow, 'data' => $rows]);
