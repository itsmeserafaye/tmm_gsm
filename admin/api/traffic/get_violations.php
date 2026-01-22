<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();

header('Content-Type: application/json');
require_any_permission(['module3.read','module3.issue','module3.settle']);

$res = $db->query("SELECT violation_code, description, fine_amount FROM violation_types ORDER BY description ASC");
$violations = [];
while ($row = $res->fetch_assoc()) {
    $violations[] = $row;
}

echo json_encode(['ok' => true, 'data' => $violations]);
?>
