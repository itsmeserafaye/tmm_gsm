<?php
require_once __DIR__ . '/../../../admin/includes/db.php';
header('Content-Type: application/json');
$db = db();
function json_ok($data = []) { echo json_encode(array_merge(['ok' => true], $data)); }
function json_err($message, $code = 400) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $message]); }
