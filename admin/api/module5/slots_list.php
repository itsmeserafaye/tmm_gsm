<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.manage_terminal');

$terminalId = (int)($_GET['terminal_id'] ?? 0);
if ($terminalId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_terminal_id']);
  exit;
}
$res = $db->query("SELECT slot_id, terminal_id, slot_no, status FROM parking_slots WHERE terminal_id=" . (int)$terminalId . " ORDER BY slot_no ASC");
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
echo json_encode(['ok' => true, 'data' => $rows]);
