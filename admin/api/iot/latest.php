<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['dashboard.view','module5.read','module5.manage_terminal']);

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit <= 0) $limit = 10;
if ($limit > 50) $limit = 50;

$stmt = $db->prepare("SELECT id, device_id, event_type, payload_json, received_at FROM iot_events ORDER BY received_at DESC, id DESC LIMIT ?");
if (!$stmt) error_response(500, 'db_prepare_failed');
$stmt->bind_param('i', $limit);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
  $rows[] = [
    'id' => (int)($r['id'] ?? 0),
    'device_id' => (string)($r['device_id'] ?? ''),
    'event_type' => (string)($r['event_type'] ?? ''),
    'payload' => (string)($r['payload_json'] ?? ''),
    'received_at' => (string)($r['received_at'] ?? ''),
  ];
}
$stmt->close();

json_response(['ok' => true, 'data' => $rows]);

