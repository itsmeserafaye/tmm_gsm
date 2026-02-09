<?php
function tmm_apply_dev_cors(): void {
  if (php_sapi_name() === 'cli') return;

  $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
  if ($origin === '') return;

  $allowed = [
    'http://localhost:5500',
    'http://127.0.0.1:5500',
    'http://localhost:5501',
    'http://127.0.0.1:5501',
  ];

  if (!in_array($origin, $allowed, true)) return;

  header('Access-Control-Allow-Origin: ' . $origin);
  header('Vary: Origin');
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With, X-CSRF-Token, Authorization');

  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
  }
}

