<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['tickets.issue','tickets.validate','tickets.settle']);

$ticket = trim($_GET['ticket'] ?? '');
if ($ticket === '') {
  echo json_encode(['ok' => false, 'error' => 'Ticket number required']);
  exit;
}

$stmtT = $db->prepare("SELECT ticket_id, ticket_number, violation_code, vehicle_plate, status, fine_amount, date_issued FROM tickets WHERE ticket_number = ? LIMIT 1");
if (!$stmtT) {
  echo json_encode(['ok' => false, 'error' => 'Failed to prepare ticket query']);
  exit;
}
$stmtT->bind_param('s', $ticket);
$stmtT->execute();
$resT = $stmtT->get_result();
$ticketRow = $resT ? $resT->fetch_assoc() : null;
if (!$ticketRow) {
  echo json_encode(['ok' => false, 'error' => 'Ticket not found']);
  exit;
}

$ticketId = (int)$ticketRow['ticket_id'];

$stmtE = $db->prepare("SELECT evidence_id, file_path, file_type, timestamp FROM evidence WHERE ticket_id = ? ORDER BY timestamp DESC");
if (!$stmtE) {
  echo json_encode(['ok' => false, 'error' => 'Failed to prepare evidence query']);
  exit;
}
$stmtE->bind_param('i', $ticketId);
$stmtE->execute();
$resE = $stmtE->get_result();

$evidence = [];
if ($resE) {
  while ($row = $resE->fetch_assoc()) {
    $path = $row['file_path'] ?? '';
    $type = $row['file_type'] ?? '';
    $url = '';
    if ($path !== '') {
      if (strpos($path, 'uploads/') === 0) {
        $url = '/tmm/admin/' . ltrim($path, '/');
      } else {
        $url = '/tmm/admin/uploads/' . ltrim($path, '/');
      }
    }
    $row['url'] = $url;
    $row['file_type'] = $type;
    $evidence[] = $row;
  }
}

echo json_encode([
  'ok' => true,
  'ticket' => [
    'ticket_number' => $ticketRow['ticket_number'],
    'violation_code' => $ticketRow['violation_code'],
    'vehicle_plate' => $ticketRow['vehicle_plate'],
    'status' => $ticketRow['status'],
    'fine_amount' => $ticketRow['fine_amount'],
    'date_issued' => $ticketRow['date_issued'],
  ],
  'evidence' => $evidence,
]);
