<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');

$fid = trim($_GET['franchise_id'] ?? '');
if ($fid === '') {
    echo json_encode(['error' => 'Franchise ID required']);
    exit;
}

// Check local DB first
$stmt = $db->prepare("SELECT fa.franchise_ref_number, o.full_name as operator, c.coop_name, fa.status 
                      FROM franchise_applications fa 
                      LEFT JOIN operators o ON fa.operator_id = o.id 
                      LEFT JOIN coops c ON fa.coop_id = c.id 
                      WHERE fa.franchise_ref_number = ?");
$stmt->bind_param('s', $fid);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode([
        'ok' => true,
        'valid' => $row['status'] === 'Endorsed',
        'franchise_id' => $row['franchise_ref_number'],
        'operator' => $row['operator'],
        'coop' => $row['coop_name'] ?? 'N/A',
        'valid_until' => '2025-12-31', // Placeholder logic
        'status' => $row['status']
    ]);
} else {
    // If not found, return empty/invalid
    echo json_encode([
        'ok' => true,
        'valid' => false,
        'franchise_id' => $fid,
        'operator' => 'Not Found',
        'coop' => 'N/A',
        'valid_until' => 'N/A',
        'status' => 'Not Registered'
    ]);
}
?>
