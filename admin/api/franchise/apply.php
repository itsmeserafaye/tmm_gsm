<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

// Get Inputs
$ref = trim($_POST['franchise_ref'] ?? '');
$operator = trim($_POST['operator_name'] ?? '');
$coop = trim($_POST['coop_name'] ?? '');
$count = (int)($_POST['vehicle_count'] ?? 1);
$status = 'Pending'; // Default status

// Validation
if ($ref === '' || $operator === '') {
    echo json_encode(['error' => 'Franchise Reference and Operator Name are required']);
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
        echo json_encode(['error' => 'Failed to create operator record']);
        exit;
    }
}

// 2. Find or Create Cooperative (Optional)
$coopId = null;
if ($coop !== '') {
    $stmtC = $db->prepare("SELECT id FROM coops WHERE coop_name = ?");
    $stmtC->bind_param('s', $coop);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    if ($rowC = $resC->fetch_assoc()) {
        $coopId = $rowC['id'];
    } else {
        $stmtInsC = $db->prepare("INSERT INTO coops (coop_name) VALUES (?)");
        $stmtInsC->bind_param('s', $coop);
        if ($stmtInsC->execute()) {
            $coopId = $db->insert_id;
        }
    }
}

// 3. Create Application
// Check for duplicates
$stmtDup = $db->prepare("SELECT application_id FROM franchise_applications WHERE franchise_ref_number = ?");
$stmtDup->bind_param('s', $ref);
$stmtDup->execute();
if ($stmtDup->get_result()->num_rows > 0) {
    echo json_encode(['error' => 'Application with this Franchise Reference already exists']);
    exit;
}

$stmtApp = $db->prepare("INSERT INTO franchise_applications (franchise_ref_number, operator_id, coop_id, vehicle_count, status) VALUES (?, ?, ?, ?, ?)");
$stmtApp->bind_param('siiis', $ref, $operatorId, $coopId, $count, $status);

if ($stmtApp->execute()) {
    echo json_encode(['ok' => true, 'id' => $db->insert_id, 'message' => 'Application submitted successfully']);
} else {
    echo json_encode(['error' => 'Failed to save application: ' . $db->error]);
}
?>