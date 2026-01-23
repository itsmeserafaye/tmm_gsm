<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/util.php';

$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  error_response(405, 'method_not_allowed');
}

$expected = (string)getenv('TMM_IOT_INGEST_TOKEN');
$provided = (string)($_SERVER['HTTP_X_IOT_TOKEN'] ?? ($_GET['token'] ?? ''));
if ($expected !== '') {
  if ($provided === '' || !hash_equals($expected, $provided)) {
    error_response(401, 'unauthorized');
  }
} else {
  require_once __DIR__ . '/../../includes/auth.php';
  require_any_permission(['module5.manage_terminal','dashboard.view']);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
  if (!empty($_POST)) $payload = $_POST;
}
if (!is_array($payload)) {
  error_response(400, 'invalid_payload');
}

$deviceId = trim((string)($payload['device_id'] ?? ''));
$eventType = trim((string)($payload['event_type'] ?? ($payload['type'] ?? '')));
$data = $payload['data'] ?? ($payload['payload'] ?? null);
if ($deviceId === '' || $eventType === '') {
  error_response(400, 'missing_fields');
}

$dataJson = '';
if ($data !== null) {
  $dataJson = json_encode($data, JSON_UNESCAPED_SLASHES);
  if ($dataJson === false) $dataJson = '';
  if (strlen($dataJson) > 20000) $dataJson = substr($dataJson, 0, 20000);
}

$stmt = $db->prepare("INSERT INTO iot_events (device_id, event_type, payload_json, received_at) VALUES (?, ?, ?, NOW())");
if (!$stmt) error_response(500, 'db_prepare_failed');
$stmt->bind_param('sss', $deviceId, $eventType, $dataJson);
$ok = $stmt->execute();
$id = (int)$db->insert_id;
$stmt->close();

json_response(['ok' => (bool)$ok, 'id' => $id]);

