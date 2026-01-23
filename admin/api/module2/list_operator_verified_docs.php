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

$stmt = $db->prepare("SELECT doc_id, doc_type, file_path, uploaded_at, doc_status, is_verified, verified_at
                      FROM operator_documents
                      WHERE operator_id=? AND (doc_status='Verified' OR is_verified=1)
                      ORDER BY verified_at DESC, uploaded_at DESC, doc_id DESC");
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
