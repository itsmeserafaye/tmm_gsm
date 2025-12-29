<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');
$terminalId = $_GET['terminal_id'] ?? null;

$where = "WHERE DATE(created_at) = '$date'";
if ($terminalId) {
    $where .= " AND terminal_id = $terminalId";
}

$sql = "SELECT SUM(amount) as total, COUNT(*) as count FROM terminal_charges $where";
$res = $db->query($sql);
$row = $res->fetch_assoc();

$sqlInc = "SELECT COUNT(*) as count FROM terminal_incidents WHERE DATE(reported_at) = '$date'";
if ($terminalId) {
    $sqlInc .= " AND terminal_id = $terminalId";
}
$resInc = $db->query($sqlInc);
$rowInc = $resInc->fetch_assoc();

echo json_encode([
    'total_fees' => $row['total'] ?? 0,
    'transaction_count' => $row['count'] ?? 0,
    'incident_count' => $rowInc['count'] ?? 0,
    'reconciled' => true // Simulation
]);
?>