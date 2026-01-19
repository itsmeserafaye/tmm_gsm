<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/util.php';
require_once __DIR__ . '/../../../includes/treasury.php';
require_once __DIR__ . '/_auth.php';

$db = db();
header('Content-Type: application/json');
tmm_treasury_integration_authorize();

$kind = strtolower(trim((string)($_GET['kind'] ?? 'ticket')));
$transactionId = trim((string)($_GET['transaction_id'] ?? ($_GET['ticket_number'] ?? ($_GET['parking_transaction_id'] ?? ''))));
if ($transactionId === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_transaction_id']);
  exit;
}

$payload = tmm_treasury_build_digital_payload($db, $kind, $transactionId);
if (!($payload['ok'] ?? false)) {
  $err = (string)($payload['error'] ?? 'error');
  $code = 400;
  if ($err === 'transaction_not_found') $code = 404;
  elseif ($err === 'db_prepare_failed') $code = 500;
  http_response_code($code);
}
echo json_encode($payload);
