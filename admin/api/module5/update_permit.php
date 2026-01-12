<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$permitId = $_POST['permit_id'] ?? 0;
$status = $_POST['status'] ?? '';
$expiry = $_POST['expiry_date'] ?? null;
$conditions = $_POST['conditions'] ?? '';

if (!$permitId || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

$sql = "UPDATE terminal_permits SET status=?, conditions=?";
$types = 'ss';
$params = [$status, $conditions];

if ($expiry) {
    $sql .= ", expiry_date=?, issue_date=CURRENT_DATE";
    $types .= 's';
    $params[] = $expiry;
}

$sql .= " WHERE id=?";
$types .= 'i';
$params[] = $permitId;

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $db->error]);
}
?>