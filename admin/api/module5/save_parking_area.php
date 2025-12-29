<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$name = $_POST['name'] ?? '';
$city = $_POST['city'] ?? 'Caloocan City';
$loc = $_POST['location'] ?? '';
$type = $_POST['type'] ?? 'Standalone';
$termId = !empty($_POST['terminal_id']) ? $_POST['terminal_id'] : NULL;
$slots = $_POST['total_slots'] ?? 0;
$puv = $_POST['allowed_puv_types'] ?? '';

if (!$name || !$city) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

$stmt = $db->prepare("INSERT INTO parking_areas (name, city, location, type, terminal_id, total_slots, allowed_puv_types) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('ssssiis', $name, $city, $loc, $type, $termId, $slots, $puv);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $db->error]);
}
?>