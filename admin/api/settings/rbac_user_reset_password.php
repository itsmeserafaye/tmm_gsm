<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

function json_out($code, $payload) {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

function rand_password($len = 14) {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*?';
  $out = '';
  for ($i = 0; $i < $len; $i++) {
    $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
  }
  return $out;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
  $db = db();
  require_role(['SuperAdmin']);

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) json_out(400, ['ok' => false, 'error' => 'missing_id']);

  $tmpPassword = rand_password(14);
  $hash = password_hash($tmpPassword, PASSWORD_DEFAULT);

  $stmt = $db->prepare("UPDATE rbac_users SET password_hash=? WHERE id=?");
  if (!$stmt) json_out(500, ['ok' => false, 'error' => 'db_prepare_failed']);
  $stmt->bind_param('si', $hash, $id);
  $ok = $stmt->execute();
  $stmt->close();

  json_out(200, ['ok' => (bool)$ok, 'temporary_password' => $tmpPassword]);
} catch (Exception $e) {
  if (defined('TMM_TEST')) throw $e;
  json_out(400, ['ok' => false, 'error' => $e->getMessage()]);
}

