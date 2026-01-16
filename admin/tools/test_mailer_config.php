<?php
if (php_sapi_name() !== 'cli') {
  http_response_code(403);
  echo "forbidden\n";
  exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';

$db = db();
$m = tmm_mailer($db);

echo json_encode([
  'ok' => true,
  'is_smtp' => (bool)$m->Mailer === 'smtp',
  'host' => (string)($m->Host ?? ''),
  'port' => (int)($m->Port ?? 0),
  'smtp_secure' => (string)($m->SMTPSecure ?? ''),
  'from' => (string)($m->From ?? ''),
  'from_name' => (string)($m->FromName ?? ''),
], JSON_PRETTY_PRINT) . "\n";

