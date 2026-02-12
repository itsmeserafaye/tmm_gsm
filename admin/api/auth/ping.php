<?php
require_once __DIR__ . '/../../includes/auth.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$touch = true;
if (isset($_GET['touch'])) {
  $touch = ((string)$_GET['touch']) !== '0';
}

require_login(false);

$now = time();
$ttl = tmm_session_timeout_seconds();
$warnRaw = (int)trim((string)tmm_get_app_setting('session_warning_seconds', '30'));
$warn = $warnRaw > 0 ? $warnRaw : 30;
$warn = max(10, min(120, $warn));
$warn = min($warn, max(10, $ttl - 5));

$last = (int)($_SESSION['last_activity'] ?? 0);
if ($last <= 0) $last = $now;
$idle = max(0, $now - $last);
$remaining = max(0, $ttl - $idle);

if ($touch) {
  $_SESSION['last_activity'] = $now;
}

echo json_encode([
  'ok' => true,
  'touch' => $touch,
  'server_now' => $now,
  'last_activity' => $last,
  'timeout_sec' => $ttl,
  'warning_sec' => $warn,
  'idle_sec' => $idle,
  'remaining_sec' => $remaining,
]);
?>
