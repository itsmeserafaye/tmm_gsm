<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();

header('Content-Type: application/json');
require_permission('module2.franchises.manage');

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

// Check Application Status
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

// Create Endorsement Record (aligned with endorsement_records schema in db.php)
$permit_no = "PERMIT-" . date('Y') . "-" . str_pad($app_id, 4, '0', STR_PAD_LEFT);

$stmt = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number) VALUES (?, CURDATE(), ?)");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $db->error]);
    exit;
}

$stmt->bind_param('is', $app_id, $permit_no);
$okInsert = $stmt->execute();
$stmt->close();

if ($okInsert) {
    // Update Application Status
    $db->query("UPDATE franchise_applications SET status = 'Endorsed' WHERE application_id = $app_id");

    // Update LPTRP Count
    $route_id = $app['route_ids']; // Assuming single ID for now
    $count = (int)$app['vehicle_count'];
    $db->query("UPDATE lptrp_routes SET current_vehicle_count = current_vehicle_count + $count WHERE id = '$route_id'");

    $frRef = trim((string)($app['franchise_ref_number'] ?? ''));
    if ($frRef !== '') {
        $stmtVeh = $db->prepare("UPDATE vehicles SET status='Active' WHERE franchise_id=? AND (status IS NULL OR status='' OR status='Suspended')");
        if ($stmtVeh) {
            $stmtVeh->bind_param('s', $frRef);
            $stmtVeh->execute();
            $stmtVeh->close();
        }
    }

    echo json_encode(['ok' => true, 'message' => 'Endorsement issued successfully', 'permit_no' => $permit_no]);
} else {
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
?>
