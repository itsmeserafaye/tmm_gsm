<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('parking.manage');

$terminalId = isset($_GET['terminal_id']) ? (int)$_GET['terminal_id'] : 0;
$sql = "SELECT ta.assignment_id, ta.terminal_id, t.name AS terminal_name, ta.vehicle_id, v.plate_number, ta.assigned_at
        FROM terminal_assignments ta
        LEFT JOIN terminals t ON t.id=ta.terminal_id
        LEFT JOIN vehicles v ON v.id=ta.vehicle_id";
$conds = [];
if ($terminalId > 0) $conds[] = "ta.terminal_id=" . (int)$terminalId;
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY ta.assigned_at DESC LIMIT 500";
$res = $db->query($sql);
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
echo json_encode(['ok' => true, 'data' => $rows]);

