<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    require_role(['SuperAdmin', 'Admin', 'Franchise Officer']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $status = $input['status'] ?? '';

    if ($id <= 0) throw new Exception('Invalid ID');
    if (!in_array($status, ['Submitted', 'Under Review', 'Resolved', 'Dismissed'])) {
        throw new Exception('Invalid status');
    }

    $stmt = $db->prepare("UPDATE commuter_complaints SET status=? WHERE id=?");
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();

    echo json_encode(['ok' => true, 'message' => 'Status updated']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
