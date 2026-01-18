<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.vehicles.write','module1.coops.write','module2.franchises.manage']);

$name = trim($_POST['full_name'] ?? '');
$contact = trim($_POST['contact_info'] ?? '');
$coop = trim($_POST['coop_name'] ?? '');

if ($name === '' || strlen($name) < 3 || !preg_match("/^[A-Za-z\s'.-]+$/", $name)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Operator full name should be a realistic human name']);
    exit;
}

if ($contact === '' || strlen($contact) < 7) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Contact should be a valid phone number or email']);
    exit;
}

if (strpos($contact, '@') !== false) {
    if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $contact)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Contact should be a valid email address']);
        exit;
    }
} else {
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $contact)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Contact should be a valid phone number']);
        exit;
    }
}

// Check/Insert COOP if provided
$coopId = null;
if ($coop !== '') {
    $stmtC = $db->prepare("SELECT id FROM coops WHERE coop_name = ?");
    $stmtC->bind_param('s', $coop);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    if ($r = $resC->fetch_assoc()) {
        $coopId = $r['id'];
    } else {
        $stmtI = $db->prepare("INSERT INTO coops (coop_name) VALUES (?)");
        $stmtI->bind_param('s', $coop);
        $stmtI->execute();
        $coopId = $db->insert_id;
    }
}

// Insert/Update Operator
$stmt = $db->prepare("INSERT INTO operators (full_name, contact_info, coop_id) VALUES (?, ?, ?) 
                      ON DUPLICATE KEY UPDATE contact_info=VALUES(contact_info), coop_id=VALUES(coop_id)");
$stmt->bind_param('ssi', $name, $contact, $coopId);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'id' => $db->insert_id ?: $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save operator: ' . $db->error]);
}
?>
