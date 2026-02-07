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

$id = (int)($_POST['sts_ticket_id'] ?? 0);
$status = trim((string)($_POST['status'] ?? ''));
$notes = trim((string)($_POST['verification_notes'] ?? ''));

if ($id <= 0) error_response(400, 'invalid_ticket_id');
$allowed = ['Pending Payment','Paid','Closed'];
if (!in_array($status, $allowed, true)) error_response(400, 'invalid_status');

$stmt = $db->prepare("UPDATE sts_tickets
                      SET status=?, verification_notes=CASE WHEN ?<>'' THEN ? ELSE verification_notes END
                      WHERE sts_ticket_id=?");
if (!$stmt) error_response(500, 'db_prepare_failed');
$stmt->bind_param('sssi', $status, $notes, $notes, $id);
$ok = $stmt->execute();
$stmt->close();
if (!$ok) error_response(500, 'db_error');

tmm_audit_event($db, 'STS_TICKET_STATUS_UPDATED', 'STSTicket', (string)$id, ['status' => $status]);
echo json_encode(['ok' => true]);
