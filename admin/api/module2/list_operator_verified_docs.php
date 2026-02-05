<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.apply','module2.read','module2.endorse','module2.approve','module2.history','module2.franchises.manage','module2.view']);

$operatorId = isset($_GET['operator_id']) ? (int)$_GET['operator_id'] : 0;
if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
  exit;
}

$opRow = null;
$stmtOp = $db->prepare("SELECT id, operator_type, verification_status, workflow_status, COALESCE(NULLIF(registered_name,''), NULLIF(name,''), full_name) AS display_name
                        FROM operators WHERE id=? LIMIT 1");
if ($stmtOp) {
  $stmtOp->bind_param('i', $operatorId);
  $stmtOp->execute();
  $opRow = $stmtOp->get_result()->fetch_assoc();
  $stmtOp->close();
}
if (!$opRow) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
  exit;
}

$hasUsers = (bool)($db->query("SHOW TABLES LIKE 'rbac_users'")?->fetch_row());
$sql = "SELECT od.doc_id, od.doc_type, od.file_path, od.uploaded_at, od.doc_status, od.is_verified, od.remarks,
               od.verified_by, od.verified_at";
if ($hasUsers) {
  $sql .= ", CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS verified_by_name";
}
$sql .= " FROM operator_documents od";
if ($hasUsers) {
  $sql .= " LEFT JOIN rbac_users u ON u.id=od.verified_by";
}
$sql .= " WHERE od.operator_id=? AND (od.doc_status='Verified' OR od.is_verified=1)
          ORDER BY od.verified_at DESC, od.uploaded_at DESC, od.doc_id DESC";
$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $operatorId);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

echo json_encode(['ok' => true, 'operator' => $opRow, 'data' => $rows]);
