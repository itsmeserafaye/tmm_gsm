<?php
if (php_sapi_name() !== 'cli' && function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
function normalize_role($role) {
  if (!is_string($role)) {
    return 'Admin';
  }
  $r = trim($role);
  if ($r === '') {
    return 'Admin';
  }
  $map = [
    'LGU Encoder' => 'Encoder',
    'Encoder' => 'Encoder',
    'Franchise Officer' => 'Admin',
    'Franchise/Permitting Officer' => 'Admin',
    'City Inspector' => 'Inspector',
    'City Transport Inspector' => 'Inspector',
    'Traffic Enforcer' => 'Inspector',
    'Terminal Officer' => 'Admin',
    'Terminal Supervisor' => 'Admin',
    'Parking Staff' => 'Admin',
    'Parking Attendant' => 'Admin'
  ];
  return $map[$r] ?? $r;
}
function current_user_role() {
  $role = $_SESSION['role'] ?? ($_SERVER['HTTP_X_USER_ROLE'] ?? ($_COOKIE['tmm_role'] ?? ($_POST['role'] ?? ($_GET['role'] ?? 'Guest'))));
  return normalize_role($role);
}

function require_login() {
  if (!empty($_SESSION['user_id'])) return;
  if (defined('TMM_TEST')) {
    throw new Exception('unauthorized');
  }
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'unauthorized']);
  exit;
}

function require_role(array $allowed) {
  require_login();
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

function current_user_permissions() {
  $perms = $_SESSION['permissions'] ?? [];
  if (!is_array($perms)) return [];
  $out = [];
  foreach ($perms as $p) {
    if (is_string($p) && $p !== '') $out[] = $p;
  }
  return array_values(array_unique($out));
}

function has_permission(string $code) {
  $perms = current_user_permissions();
  if (in_array($code, $perms, true)) return true;
  $role = current_user_role();
  if ($role === 'SuperAdmin') return true;
  $fallback = [
    'Admin' => ['dashboard.view','analytics.view','module1.vehicles.write','module1.routes.write','module1.coops.write','module2.franchises.manage','module4.inspections.manage','tickets.validate','parking.manage','reports.export','settings.manage'],
    'Encoder' => ['dashboard.view','module1.vehicles.write','module1.routes.write','module1.coops.write','reports.export'],
    'Inspector' => ['dashboard.view','analytics.view','module4.inspections.manage','tickets.issue','reports.export'],
    'Treasurer' => ['dashboard.view','tickets.settle','reports.export'],
    'ParkingStaff' => ['dashboard.view','parking.manage','tickets.issue'],
    'Viewer' => ['dashboard.view','analytics.view','reports.export'],
  ];
  return in_array($code, $fallback[$role] ?? [], true);
}

function require_permission(string $code) {
  require_login();
  if (has_permission($code)) return;
  if (defined('TMM_TEST')) {
    throw new Exception('forbidden');
  }
  http_response_code(403);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'forbidden','permission'=>$code,'role'=>current_user_role()]);
  exit;
}
