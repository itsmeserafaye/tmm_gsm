<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder','Inspector']);
header('Content-Type: application/json');

$terminalName = trim($_GET['terminal_name'] ?? '');
if ($terminalName === '') {
  echo json_encode(['ok'=>false,'error'=>'missing_terminal']);
  exit;
}

$stmtT = $db->prepare("SELECT id FROM terminals WHERE name=?");
$stmtT->bind_param('s', $terminalName);
$stmtT->execute();
$t = $stmtT->get_result()->fetch_assoc();
if (!$t) { echo json_encode(['ok'=>false,'error'=>'terminal_not_found']); exit; }
$terminalId = (int)$t['id'];

$sql = "SELECT 
          l.vehicle_plate,
          COALESCE(o.full_name, v.operator_name) AS operator_name,
          l.time_in,
          l.time_out,
          l.activity_type,
          l.remarks
        FROM terminal_logs l
        LEFT JOIN operators o ON o.id = l.operator_id
        LEFT JOIN vehicles v ON v.plate_number = l.vehicle_plate
        WHERE l.terminal_id = ?
        ORDER BY l.time_in DESC
        LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $terminalId);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
echo json_encode(['ok'=>true, 'data'=>$rows]);
?> 
