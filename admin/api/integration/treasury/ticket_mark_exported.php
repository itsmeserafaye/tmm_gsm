<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/_auth.php';

$db = db();
header('Content-Type: application/json');
tmm_treasury_integration_authorize();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload) || !isset($payload['ids']) || !is_array($payload['ids'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_json']);
  exit;
}

$ids = array_values(array_filter(array_map('intval', $payload['ids']), function ($v) { return $v > 0; }));
if (count($ids) === 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'no_ids']);
  exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$sql = "UPDATE payment_records SET exported_to_treasury=1, exported_at=NOW() WHERE payment_id IN ($placeholders)";
$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param($types, ...$ids);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

echo json_encode(['ok' => (bool)$ok, 'updated' => (int)$affected]);

