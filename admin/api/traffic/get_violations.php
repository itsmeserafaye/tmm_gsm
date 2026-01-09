<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

header('Content-Type: application/json');

$res = $db->query("SELECT violation_code, description, fine_amount FROM violation_types ORDER BY description ASC");
$violations = [];
while ($row = $res->fetch_assoc()) {
    $violations[] = $row;
}

echo json_encode(['ok' => true, 'data' => $violations]);
?>