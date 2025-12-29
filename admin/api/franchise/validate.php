<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$ref = trim($_GET['ref'] ?? '');

if ($ref === '') {
    echo json_encode(['error' => 'Reference number is required']);
    exit;
}

// Check application
$stmt = $db->prepare("
    SELECT fa.*, o.full_name as operator_name, c.coop_name 
    FROM franchise_applications fa 
    JOIN operators o ON fa.operator_id = o.id 
    LEFT JOIN coops c ON fa.coop_id = c.id 
    WHERE fa.franchise_ref_number = ? OR fa.application_id = ?
");
$stmt->bind_param('ss', $ref, $ref);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    // Check if already endorsed
    $isEndorsed = $row['status'] === 'Endorsed';
    
    echo json_encode([
        'found' => true,
        'data' => [
            'application_id' => $row['application_id'],
            'ref_number' => $row['franchise_ref_number'],
            'operator' => $row['operator_name'],
            'coop' => $row['coop_name'] ?? 'Individual',
            'status' => $row['status'],
            'vehicle_count' => $row['vehicle_count']
        ]
    ]);
} else {
    echo json_encode(['found' => false, 'error' => 'Application not found']);
}
?>