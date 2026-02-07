<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
require_permission('module3.issue');

$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  error_response(405, 'method_not_allowed');
}

$id = (int)($_POST['violation_id'] ?? 0);
$workflow = trim((string)($_POST['workflow_status'] ?? ''));
$remarks = trim((string)($_POST['remarks'] ?? ''));

if ($id <= 0) error_response(400, 'invalid_violation_id');
$allowed = ['Pending','Verified','Closed'];
if (!in_array($workflow, $allowed, true)) error_response(400, 'invalid_workflow_status');

$stmt = $db->prepare("UPDATE violations SET workflow_status=?, remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END WHERE id=?");
if (!$stmt) error_response(500, 'db_prepare_failed');
$stmt->bind_param('sssi', $workflow, $remarks, $remarks, $id);
$ok = $stmt->execute();
$stmt->close();
if (!$ok) error_response(500, 'db_error');

tmm_audit_event($db, 'VIOLATION_STATUS_UPDATED', 'Violation', (string)$id, ['workflow_status' => $workflow]);
echo json_encode(['ok' => true]);
