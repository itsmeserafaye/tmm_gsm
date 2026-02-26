<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.manage_terminal');

$terminalId = isset($_GET['terminal_id']) ? (int)$_GET['terminal_id'] : 0;

if ($terminalId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Terminal ID is required']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM terminal_contracts WHERE terminal_id=? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('i', $terminalId);
$stmt->execute();
$res = $stmt->get_result();
$contract = $res->fetch_assoc();

if ($contract) {
    // Calculate duration
    if (!empty($contract['start_date']) && !empty($contract['end_date'])) {
        $start = new DateTime($contract['start_date']);
        $end = new DateTime($contract['end_date']);
        $diff = $start->diff($end);
        $contract['duration_display'] = $diff->format('%y years, %m months, %d days');
        // Simplify if needed, e.g., "12 months"
        $months = ($diff->y * 12) + $diff->m;
        if ($diff->d > 0) $months += $diff->d / 30; // approx
        $contract['duration_months'] = round($months, 1);
    } else {
        $contract['duration_display'] = 'N/A';
    }
    
    // Determine status if not set or dynamic
    // The status field is stored in DB, but we could also compute "Expiring Soon" here.
    
    echo json_encode(['success' => true, 'data' => $contract]);
} else {
    echo json_encode(['success' => true, 'data' => null]);
}
?>
