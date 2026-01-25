<?php
require_once __DIR__ . '/../../includes/auth.php';
header('Content-Type: application/json');
require_login();
echo json_encode(['ok' => true]);
?>

