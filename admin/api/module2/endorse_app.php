<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$app_id = (int)($_POST['application_id'] ?? 0);
$officer = trim($_POST['officer_name'] ?? 'System');
$notes = trim($_POST['notes'] ?? '');

if ($app_id === 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing Application ID']);
    exit;
}

$res = $db->query("SELECT * FROM franchise_applications WHERE application_id = $app_id");
$app = $res->fetch_assoc();
if (!$app) {
    echo json_encode(['ok' => false, 'error' => 'Application not found']);
    exit;
}
if ($app['status'] === 'Endorsed') {
    echo json_encode(['ok' => false, 'error' => 'Application already endorsed']);
    exit;
}

$expiry = date('Y-m-d', strtotime('+1 year'));
$permit_no = "PERMIT-" . date('Y') . "-" . str_pad($app_id, 4, '0', STR_PAD_LEFT);
$stmt = $db->prepare("INSERT INTO endorsement_records (application_id, issued_by, issued_date, expiry_date, local_permit_no, status) VALUES (?, ?, CURDATE(), ?, ?, 'Active')");
$stmt->bind_param('isss', $app_id, $officer, $expiry, $permit_no);

if ($stmt->execute()) {
    $db->query("UPDATE franchise_applications SET status = 'Endorsed' WHERE application_id = $app_id");
    $route_id = $app['route_ids'];
    $count = (int)$app['vehicle_count'];
    if ($route_id !== null && $route_id !== '') {
        $db->query("UPDATE lptrp_routes SET current_vehicle_count = current_vehicle_count + $count WHERE id = '$route_id'");
    }

    $fr_ref = trim($app['franchise_ref_number'] ?? '');
    $op_full = null;
    if (!empty($app['operator_id'])) {
        $opStmt = $db->prepare("SELECT full_name FROM operators WHERE id=?");
        $opStmt->bind_param('i', $app['operator_id']);
        $opStmt->execute();
        $opRow = $opStmt->get_result()->fetch_assoc();
        $op_full = $opRow['full_name'] ?? null;
    }
    $updated = 0;
    if ($fr_ref !== '' && $op_full) {
        $uStmt = $db->prepare("UPDATE vehicles SET franchise_id=? WHERE operator_name=? AND (franchise_id IS NULL OR franchise_id='')");
        $uStmt->bind_param('ss', $fr_ref, $op_full);
        $uStmt->execute();
        $updated = $uStmt->affected_rows;
    }

    echo json_encode(['ok' => true, 'message' => 'Endorsement issued successfully', 'permit_no' => $permit_no, 'franchise_ref' => $fr_ref, 'vehicles_linked' => $updated]);
} else {
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
?>
