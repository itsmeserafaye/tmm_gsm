<?php
require_once __DIR__ . '/../../../includes/auth.php';

function tmm_integration_authorize(): void {
  $expected = (string)getenv('TMM_PERMITS_INTEGRATION_KEY');
  $header = (string)($_SERVER['HTTP_X_INTEGRATION_KEY'] ?? '');
  $query = (string)($_GET['integration_key'] ?? '');

  $ok = false;
  if ($expected !== '' && ($header !== '' || $query !== '')) {
    $provided = $header !== '' ? $header : $query;
    if (hash_equals($expected, $provided)) $ok = true;
  }
  if (!$ok && function_exists('has_permission')) {
    $ok = has_permission('module2.franchises.manage') || has_permission('module2.view');
  }
  if (!$ok) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
  }
}

