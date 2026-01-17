<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$allowed = [
  // General
  'system_name',
  'system_email',
  'maintenance_mode',
  'weather_lat',
  'weather_lon',
  'weather_label',
  'events_country',
  'events_city',
  'events_rss_url',

  // AI / Analytics weights
  'ai_weather_weight',
  'ai_event_weight',
  'ai_traffic_weight',
  
  // Security
  'password_min_length',
  'session_timeout',
  'max_login_attempts',
  'require_mfa'
];

$updates = [];
foreach ($allowed as $k) {
  if (array_key_exists($k, $_POST)) {
    $updates[$k] = trim((string)$_POST[$k]);
  }
}

if (!$updates) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'no_fields']);
  exit;
}

$stmt = $db->prepare("REPLACE INTO app_settings(setting_key, setting_value) VALUES (?,?)");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}

$okAll = true;
foreach ($updates as $k => $v) {
  $stmt->bind_param('ss', $k, $v);
  $ok = $stmt->execute();
  if (!$ok) $okAll = false;
}
$stmt->close();

echo json_encode(['ok' => $okAll]);
?>
