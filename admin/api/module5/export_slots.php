<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';

$db = db();
require_permission('reports.export');

$format = tmm_export_format();
$terminalId = isset($_GET['terminal_id']) ? (int)$_GET['terminal_id'] : 0;
if ($terminalId <= 0) {
  http_response_code(400);
  echo 'missing_terminal_id';
  exit;
}

$termName = '';
$stmtT = $db->prepare("SELECT name FROM terminals WHERE id=? LIMIT 1");
if ($stmtT) {
  $stmtT->bind_param('i', $terminalId);
  $stmtT->execute();
  $termName = (string)(($stmtT->get_result()->fetch_assoc()['name'] ?? '') ?: '');
  $stmtT->close();
}

$base = 'parking_slots_' . ($termName !== '' ? $termName : ('terminal_' . $terminalId));
tmm_send_export_headers($format, $base);

$stmt = $db->prepare("SELECT slot_id, terminal_id, slot_no, status FROM parking_slots WHERE terminal_id=? ORDER BY slot_no ASC");
if (!$stmt) { http_response_code(500); echo 'db_prepare_failed'; exit; }
$stmt->bind_param('i', $terminalId);
$stmt->execute();
$res = $stmt->get_result();

$headers = ['slot_id','terminal_id','slot_no','status'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'slot_id' => $r['slot_id'] ?? '',
    'terminal_id' => $r['terminal_id'] ?? '',
    'slot_no' => $r['slot_no'] ?? '',
    'status' => $r['status'] ?? '',
  ];
});
$stmt->close();
