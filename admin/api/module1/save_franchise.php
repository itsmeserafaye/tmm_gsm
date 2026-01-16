<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_permission('module2.franchises.manage');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $db = db();

    $operator_name = trim($_POST['operator_name'] ?? '');
    $fr_ref = trim($_POST['franchise_ref'] ?? $_POST['franchise_ref_number'] ?? '');
    $application_type = trim($_POST['application_type'] ?? 'New');
    $status = trim($_POST['status'] ?? 'Pending');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($operator_name)) {
        throw new Exception('Operator Name is required');
    }
    if ($fr_ref === '') {
        $fr_ref = 'FR-' . date('Ymd') . '-' . uniqid();
    }

    // Try to find operator_id from operators table if it exists
    $operator_id = null;
    $stmt = $db->prepare("SELECT id FROM operators WHERE full_name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $operator_name);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $operator_id = $row['id'];
        }
        $stmt->close();
    }

    $sql = "INSERT INTO franchise_applications (franchise_ref_number, operator_id, operator_name, application_type, status, submission_date, notes) VALUES (?, ?, ?, ?, ?, CURDATE(), ?)";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }

    $stmt->bind_param('sissss', $fr_ref, $operator_id, $operator_name, $application_type, $status, $notes);
    
    if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'id' => $stmt->insert_id, 'franchise_ref_number' => $fr_ref, 'message' => 'Franchise application saved successfully']);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
