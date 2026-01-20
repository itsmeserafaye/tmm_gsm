<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    require_role(['SuperAdmin', 'Admin', 'Terminal Manager']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $type = $input['type'] ?? 'Normal';
    $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    if (!$title) throw new Exception('Title is required');

    if ($id > 0) {
        // Update
        $stmt = $db->prepare("UPDATE public_advisories SET title=?, content=?, type=?, is_active=? WHERE id=?");
        $stmt->bind_param('sssii', $title, $content, $type, $isActive, $id);
        $stmt->execute();
        $msg = 'Advisory updated';
    } else {
        // Create
        $stmt = $db->prepare("INSERT INTO public_advisories (title, content, type, is_active) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('sssi', $title, $content, $type, $isActive);
        $stmt->execute();
        $id = $stmt->insert_id;
        $msg = 'Advisory created';
    }

    echo json_encode(['ok' => true, 'message' => $msg, 'id' => $id]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
