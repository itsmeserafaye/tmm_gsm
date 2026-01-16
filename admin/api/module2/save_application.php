<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lptrp.php';
$db = db();

header('Content-Type: application/json');
require_permission('module2.franchises.manage');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$coop_id = (int)($_POST['coop_id'] ?? 0);
$rep_name = trim($_POST['rep_name'] ?? '');
$franchise_ref = trim($_POST['franchise_ref'] ?? '');
$vehicle_count = (int)($_POST['vehicle_count'] ?? 0);
$route_id = (int)($_POST['route_id'] ?? 0);
$fee_receipt = trim($_POST['fee_receipt'] ?? '');

if ($coop_id === 0 || empty($rep_name) || empty($franchise_ref) || $vehicle_count <= 0 || $route_id === 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

// 1. Automated Checks
$validation_notes = [];
$lptrp_status = 'Passed';
$coop_status = 'Passed';

// Check Coop
$coop_res = $db->query("SELECT * FROM coops WHERE id = $coop_id");
$coop = $coop_res ? $coop_res->fetch_assoc() : null;
if ($coop) {
    if (($coop['consolidation_status'] ?? '') !== 'Consolidated') {
        $coop_status = 'Failed';
        $validation_notes[] = "Coop is not consolidated.";
    }
} else {
    $coop_status = 'Failed';
    $validation_notes[] = "Cooperative not found.";
}

// Check LPTRP
$route_res = $db->query("SELECT * FROM lptrp_routes WHERE id = $route_id");
$route = $route_res ? $route_res->fetch_assoc() : null;
if ($route) {
    if (!tmm_lptrp_is_approved($route)) {
        $lptrp_status = 'Failed';
        $validation_notes[] = "Route is not LPTRP-approved.";
    }
    $projected = $route['current_vehicle_count'] + $vehicle_count;
    if ($projected > $route['max_vehicle_capacity']) {
        $lptrp_status = 'Failed';
        $validation_notes[] = "Route capacity exceeded (Max: {$route['max_vehicle_capacity']}, Curr: {$route['current_vehicle_count']}).";
    }
} else {
    $lptrp_status = 'Failed';
    $validation_notes[] = "Route not found in LPTRP.";
}

$notes_str = $db->real_escape_string(implode("\n", $validation_notes));

// 2. Find/Create Operator
$operator_id = 0;
$res = $db->prepare("SELECT id FROM operators WHERE full_name = ?");
$c_name = (is_array($coop) && isset($coop['coop_name'])) ? $coop['coop_name'] : 'Unknown';
if (!$res) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $db->error]);
    exit;
}
$res->bind_param('s', $rep_name);
$res->execute();
$op_res = $res->get_result();
if ($op_res->num_rows > 0) {
    $operator_id = $op_res->fetch_assoc()['id'];
} else {
    $stmt = $db->prepare("INSERT INTO operators (full_name, coop_name) VALUES (?, ?)");
    if (!$stmt) {
        echo json_encode(['ok' => false, 'error' => 'Database error: ' . $db->error]);
        exit;
    }
    $stmt->bind_param('ss', $rep_name, $c_name);
    $stmt->execute();
    $operator_id = $db->insert_id;
}

// 3. Create Application
$stmt = $db->prepare("INSERT INTO franchise_applications 
    (franchise_ref_number, operator_id, coop_id, vehicle_count, route_ids, fee_receipt_id, status, validation_notes, lptrp_status, coop_status) 
    VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?)");

if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $db->error]);
    exit;
}

$route_ids_val = (string)$route_id;
$stmt->bind_param('siiisssss', $franchise_ref, $operator_id, $coop_id, $vehicle_count, $route_ids_val, $fee_receipt, $notes_str, $lptrp_status, $coop_status);

try {
    $execOk = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        echo json_encode(['ok' => false, 'error' => 'Application with this Franchise Reference already exists']);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

if ($execOk) {
    $app_id = $db->insert_id;
    echo json_encode(['ok' => true, 'application_id' => $app_id, 'message' => "Application submitted. ID: APP-$app_id"]);
} else {
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
?>
