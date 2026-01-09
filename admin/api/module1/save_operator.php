<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder']);
header('Content-Type: application/json');

$name = trim($_POST['full_name'] ?? '');
$contact = trim($_POST['contact_info'] ?? '');
$coop = trim($_POST['coop_name'] ?? '');

if ($name === '') {
    echo json_encode(['error' => 'Operator name required']);
    exit;
}

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

$stmt = $db->prepare("INSERT INTO operators (full_name, contact_info, coop_id) VALUES (?, ?, ?) 
                      ON DUPLICATE KEY UPDATE contact_info=VALUES(contact_info), coop_id=VALUES(coop_id)");
$stmt->bind_param('ssi', $name, $contact, $coopId);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'id' => $db->insert_id ?: $stmt->insert_id]);
} else {
    echo json_encode(['error' => 'Failed to save operator: ' . $db->error]);
}
?> 
