<?php
require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../includes/recaptcha.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
  $db = db();
  $cfg = recaptcha_config($db);
  $siteKey = trim((string)($cfg['site_key'] ?? ''));
  echo json_encode([
    'ok' => true,
    'data' => [
      'enabled' => $siteKey !== '',
      'site_key' => $siteKey,
    ],
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'message' => 'Failed to load reCAPTCHA config',
  ]);
}

