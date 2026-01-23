<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module3.issue','module3.settle']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  error_response(405, 'method_not_allowed');
}

$ticket = trim((string)($_POST['ticket_number'] ?? ''));
$ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
if ($ticket === '' && $ticketId <= 0) {
  error_response(400, 'missing_ticket');
}

if ($ticketId > 0) {
  $stmt = $db->prepare("SELECT ticket_id, ticket_number, status FROM tickets WHERE ticket_id=? LIMIT 1");
  if (!$stmt) error_response(500, 'db_prepare_failed');
  $stmt->bind_param('i', $ticketId);
} else {
  $ticket2 = $ticket;
  $stmt = $db->prepare("SELECT ticket_id, ticket_number, status FROM tickets WHERE ticket_number=? OR external_ticket_number=? LIMIT 1");
  if (!$stmt) error_response(500, 'db_prepare_failed');
  $stmt->bind_param('ss', $ticket, $ticket2);
}
if (!$stmt) error_response(500, 'db_prepare_failed');
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
  error_response(404, 'ticket_not_found');
}

$tid = (int)($row['ticket_id'] ?? 0);
$status = (string)($row['status'] ?? '');
if (strtolower($status) === 'settled') {
  error_response(400, 'cannot_delete_settled');
}

$db->begin_transaction();
try {
  $stmtDel = $db->prepare("DELETE FROM tickets WHERE ticket_id=? LIMIT 1");
  if (!$stmtDel) throw new Exception('db_prepare_failed');
  $stmtDel->bind_param('i', $tid);
  $ok = $stmtDel->execute();
  $stmtDel->close();
  $db->commit();
  tmm_audit_event($db, 'ticket.delete', 'ticket', (string)$tid, ['ticket_number' => (string)($row['ticket_number'] ?? '')]);
  json_response(['ok' => (bool)$ok, 'ticket_id' => $tid, 'ticket_number' => (string)($row['ticket_number'] ?? '')]);
} catch (Throwable $e) {
  $db->rollback();
  error_response(500, 'db_error');
}

