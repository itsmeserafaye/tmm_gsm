<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    require_role(['SuperAdmin', 'Admin']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) throw new Exception('Invalid ID');

    $stmt = $db->prepare("DELETE FROM public_advisories WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    echo json_encode(['ok' => true, 'message' => 'Advisory deleted']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
