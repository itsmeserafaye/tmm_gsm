<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

// Active Terminals
$res = $db->query("SELECT COUNT(*) as c FROM terminals WHERE status='Active'");
$terminals = $res->fetch_assoc()['c'];

// Pending Applications
$res = $db->query("SELECT COUNT(*) as c FROM terminal_permits WHERE status='Pending'");
$pending = $res->fetch_assoc()['c'];

// Today's Logs
$today = date('Y-m-d');
$res = $db->query("SELECT COUNT(*) as c FROM terminal_logs WHERE DATE(log_time) = '$today'");
$logs = $res->fetch_assoc()['c'];

echo json_encode([
    'active_terminals' => $terminals,
    'pending_apps' => $pending,
    'today_logs' => $logs
]);
?>