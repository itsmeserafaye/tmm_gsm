<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_POST['action'] ?? '';

    if ($action === 'notify') {
        $id = $input['id'] ?? $_POST['id'] ?? 0;
        if ($id) {
            $stmt = $db->prepare("UPDATE endorsement_records SET last_notified_at = NOW() WHERE endorsement_id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                echo json_encode(['ok' => true, 'message' => 'Notification sent successfully']);
            } else {
                echo json_encode(['ok' => false, 'error' => $db->error]);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
        }
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Invalid request']);
