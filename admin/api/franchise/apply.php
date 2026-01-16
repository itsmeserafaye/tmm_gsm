<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');

// Get Inputs
$ref = strtoupper(trim($_POST['franchise_ref'] ?? ''));
$operator = trim($_POST['operator_name'] ?? '');
$coop = trim($_POST['coop_name'] ?? '');
$countRaw = trim($_POST['vehicle_count'] ?? '');
$count = (int)($countRaw === '' ? 0 : $countRaw);
$status = 'Pending'; // Default status

if ($ref === '' || $operator === '') {
    echo json_encode(['ok' => false, 'error' => 'Franchise Reference and Operator Name are required']);
    exit;
}

if (!preg_match('/^[0-9]{4}-[0-9]{3,5}$/', $ref)) {
    echo json_encode(['ok' => false, 'error' => 'Franchise Reference must look like 2024-00123']);
    exit;
}

if (strlen($operator) < 5 || !preg_match("/^[A-Za-z\s'.-]+$/", $operator)) {
    echo json_encode(['ok' => false, 'error' => 'Operator name should be a realistic human name']);
    exit;
}

if ($count < 1 || $count > 1000) {
    echo json_encode(['ok' => false, 'error' => 'Requested units must be between 1 and 1000']);
    exit;
}

// 1. Find or Create Operator
$operatorId = null;
$stmtO = $db->prepare("SELECT id FROM operators WHERE full_name = ?");
$stmtO->bind_param('s', $operator);
$stmtO->execute();
$resO = $stmtO->get_result();
if ($rowO = $resO->fetch_assoc()) {
    $operatorId = $rowO['id'];
} else {
    // Create new operator
    $stmtInsO = $db->prepare("INSERT INTO operators (full_name) VALUES (?)");
    $stmtInsO->bind_param('s', $operator);
    if ($stmtInsO->execute()) {
        $operatorId = $db->insert_id;
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to create operator record']);
        exit;
    }
}

// 2. Link to existing Cooperative (Optional)
$coopId = null;
if ($coop !== '') {
    $stmtC = $db->prepare("SELECT id, lgu_approval_number FROM coops WHERE coop_name = ?");
    $stmtC->bind_param('s', $coop);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    if ($rowC = $resC->fetch_assoc()) {
        $lgu = $rowC['lgu_approval_number'] ?? '';
        if ($lgu === null) {
            $lgu = '';
        }
        $lgu = trim($lgu);
        if ($lgu === '') {
            echo json_encode([
                'ok' => false,
                'error' => 'Cooperative exists but has no LGU approval number. Please update the cooperative record first.',
                'error_code' => 'coop_missing_lgu_approval',
                'coop_name' => $coop
            ]);
            exit;
        }
        $coopId = $rowC['id'];
    } else {
        echo json_encode([
            'ok' => false,
            'error' => 'Cooperative not found. Please register the cooperative before filing an application.',
            'error_code' => 'coop_not_found',
            'coop_name' => $coop
        ]);
        exit;
    }
}

// 3. Create Application
// Check for duplicates
$stmtDup = $db->prepare("SELECT application_id FROM franchise_applications WHERE franchise_ref_number = ?");
$stmtDup->bind_param('s', $ref);
$stmtDup->execute();
if ($stmtDup->get_result()->num_rows > 0) {
    echo json_encode(['ok' => false, 'error' => 'Application with this Franchise Reference already exists']);
    exit;
}

$stmtApp = $db->prepare("INSERT INTO franchise_applications (franchise_ref_number, operator_id, coop_id, vehicle_count, status) VALUES (?, ?, ?, ?, ?)");
$stmtApp->bind_param('siiis', $ref, $operatorId, $coopId, $count, $status);

if ($stmtApp->execute()) {
    echo json_encode(['ok' => true, 'id' => $db->insert_id, 'message' => 'Application submitted successfully']);
} else {
    echo json_encode(['ok' => false, 'error' => 'Failed to save application: ' . $db->error]);
}
?>
