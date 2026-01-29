<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['SuperAdmin']);

header('Content-Type: application/json');

$dashboardFile = realpath(__DIR__ . '/../pages/dashboard.php') ?: (__DIR__ . '/../pages/dashboard.php');
$dashboardExists = is_file($dashboardFile);
$dashboardMtime = $dashboardExists ? @filemtime($dashboardFile) : null;
$dashboardSize = $dashboardExists ? @filesize($dashboardFile) : null;
$dashboardMd5 = $dashboardExists ? @md5_file($dashboardFile) : null;

$opcacheReset = null;
if (function_exists('opcache_reset')) {
  $opcacheReset = @opcache_reset();
}

$opcacheEnabled = null;
$opcacheStatus = null;
if (function_exists('opcache_get_status')) {
  $st = @opcache_get_status(false);
  if (is_array($st)) {
    $opcacheStatus = [
      'opcache_enabled' => (bool)($st['opcache_enabled'] ?? false),
      'cache_full' => (bool)($st['cache_full'] ?? false),
      'restart_pending' => (bool)($st['restart_pending'] ?? false),
      'restart_in_progress' => (bool)($st['restart_in_progress'] ?? false),
      'memory_usage' => $st['memory_usage'] ?? null,
      'interned_strings_usage' => $st['interned_strings_usage'] ?? null,
      'opcache_statistics' => $st['opcache_statistics'] ?? null,
    ];
    $opcacheEnabled = (bool)($st['opcache_enabled'] ?? false);
  }
}

echo json_encode([
  'ok' => true,
  'server_time' => date('c'),
  'sapi' => php_sapi_name(),
  'opcache_reset_called' => $opcacheReset !== null,
  'opcache_reset_result' => $opcacheReset,
  'opcache_enabled' => $opcacheEnabled,
  'dashboard' => [
    'file' => $dashboardFile,
    'exists' => $dashboardExists,
    'mtime' => $dashboardMtime ? date('c', (int)$dashboardMtime) : null,
    'size' => $dashboardSize,
    'md5' => $dashboardMd5,
  ],
  'opcache_status' => $opcacheStatus,
], JSON_PRETTY_PRINT);
