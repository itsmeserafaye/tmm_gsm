<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
$routeId = trim($_GET['route_id'] ?? '');
header('Content-Type: application/json');
require_any_permission(['module1.view','module1.routes.write','module4.view','module5.view']);

if (!$routeId) {
    echo json_encode(['ok'=>false, 'error'=>'Missing route_id']);
    exit;
}

// Route Details
$stmtR = $db->prepare("SELECT route_name, max_vehicle_limit FROM routes WHERE route_id=?");
$stmtR->bind_param('s', $routeId);
$stmtR->execute();
$routeRow = $stmtR->get_result()->fetch_assoc();

if (!$routeRow) {
    echo json_encode(['ok'=>false, 'error'=>'Route not found']);
    exit;
}

// Assignments Count
$stmtC = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=?");
$stmtC->bind_param('s', $routeId);
$stmtC->execute();
$assignedCount = (int)($stmtC->get_result()->fetch_assoc()['c'] ?? 0);

// Terminals Stats
$stmtT = $db->prepare("SELECT terminal_name, COUNT(*) AS c FROM terminal_assignments WHERE route_id=? GROUP BY terminal_name");
$stmtT->bind_param('s', $routeId);
$stmtT->execute();
$resT = $stmtT->get_result();
$terminals = [];
while ($row = $resT->fetch_assoc()) {
    $terminals[] = $row;
}

echo json_encode([
    'ok' => true,
    'route' => [
        'id' => $routeId,
        'name' => $routeRow['route_name'],
        'capacity' => (int)$routeRow['max_vehicle_limit'],
        'assigned' => $assignedCount
    ],
    'terminals' => $terminals
]);
