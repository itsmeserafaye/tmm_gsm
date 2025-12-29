<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID required']);
    exit;
}

// Terminal Info
$term = $db->query("SELECT * FROM terminals WHERE id = $id")->fetch_assoc();

// Areas
$areasRes = $db->query("SELECT * FROM terminal_designated_areas WHERE terminal_id = $id");
$areas = [];
while ($row = $areasRes->fetch_assoc()) {
    $areas[] = $row;
}

// Operators with Driver Count
$opsRes = $db->query("SELECT o.*, 
    (SELECT COUNT(*) FROM terminal_drivers WHERE operator_id = o.id) as driver_count 
    FROM terminal_operators o 
    WHERE o.terminal_id = $id");
$operators = [];
while ($row = $opsRes->fetch_assoc()) {
    // Get Drivers for this operator
    $opId = $row['id'];
    $drvRes = $db->query("SELECT * FROM terminal_drivers WHERE operator_id = $opId");
    $drivers = [];
    while ($d = $drvRes->fetch_assoc()) {
        $drivers[] = $d;
    }
    $row['drivers'] = $drivers;
    $operators[] = $row;
}

echo json_encode([
    'terminal' => $term,
    'areas' => $areas,
    'operators' => $operators
]);
?>