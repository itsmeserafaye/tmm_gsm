<?php
if (php_sapi_name() !== 'cli' && function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
function current_user_role() {
  $role = $_SESSION['role'] ?? ($_SERVER['HTTP_X_USER_ROLE'] ?? ($_COOKIE['tmm_role'] ?? ($_POST['role'] ?? ($_GET['role'] ?? 'Admin'))));
  return is_string($role) ? $role : 'Admin';
}
function require_role(array $allowed) {
  $role = current_user_role();
  if (!in_array($role, $allowed, true)) {
    if (defined('TMM_TEST')) {
      throw new Exception('forbidden');
    } else {
      http_response_code(403);
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'error'=>'forbidden','role'=>$role,'allowed'=>$allowed]);
      exit;
    }
  }
}
