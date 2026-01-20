<?php
if (php_sapi_name() !== 'cli' && function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

function rbac_get_config_auth() {
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/../../config/rbac_config.php';
        if (file_exists($path)) {
            $config = require $path;
        } else {
            $config = [];
        }
    }
    return $config;
}

function normalize_role($role) {
  if (!is_string($role)) {
    return 'Admin / Transport Officer';
  }
  $r = trim($role);
  if ($r === '') {
    return 'Admin / Transport Officer';
  }
  $map = [
    'Admin' => 'Admin / Transport Officer',
    'Transport Officer' => 'Admin / Transport Officer',
    'LGU Encoder' => 'Encoder',
    'Franchise/Permitting Officer' => 'Franchise Officer',
    'City Inspector' => 'Inspector',
    'City Transport Inspector' => 'Inspector',
    'Parking Staff' => 'Terminal Manager',
    'Parking Attendant' => 'Terminal Manager',
    'Treasurer' => 'Treasurer / Cashier',
    'Cashier' => 'Treasurer / Cashier',
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
  
  // DEBUG: Log access attempts
  $log = __DIR__ . '/../../debug_auth_access.log';
  $data = date('Y-m-d H:i:s') . " - User: " . ($_SESSION['user_id'] ?? 'none') . " - Role: $role - Allowed: " . implode(',', $allowed) . "\n";
  @file_put_contents($log, $data, FILE_APPEND);

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
  $out = array_values(array_unique($out));
  if (current_user_role() === 'Terminal Manager' || current_user_role() === 'ParkingStaff') {
    $out = array_values(array_filter($out, function ($p) {
      return !(is_string($p) && strpos($p, 'tickets.') === 0);
    }));
  }
  // Viewer logic removed as it's no longer a valid role
  return $out;
}

function tmm_permission_aliases(): array {
  return [
    'module1.read' => ['module1.view'],
    'module1.write' => ['module1.vehicles.write','module1.routes.write','module1.coops.write'],
    'module1.delete' => [],
    'module1.link_vehicle' => ['module1.vehicles.write'],
    'module1.route_manage' => ['module1.routes.write'],

    'module2.read' => ['module2.view'],
    'module2.apply' => ['module2.franchises.manage'],
    'module2.endorse' => ['module2.franchises.manage'],
    'module2.approve' => ['module2.franchises.manage'],
    'module2.history' => ['module2.view'],

    'module3.issue' => ['module3.tickets.issue'],
    'module3.read' => ['module3.view'],
    'module3.settle' => ['module3.tickets.settle'],
    'module3.analytics' => ['module3.view'],

    'module4.schedule' => ['module4.inspections.manage'],
    'module4.inspect' => ['module4.inspections.manage'],
    'module4.read' => ['module4.view'],
    'module4.certify' => ['module4.inspections.manage'],

    'module5.manage_terminal' => ['module5.terminals.manage'],
    'module5.assign_vehicle' => ['module5.terminals.manage'],
    'module5.parking_fees' => ['module5.terminals.manage'],
    'module5.read' => ['module5.view'],

    'dashboard.view' => [],
    'settings.manage' => [],
    'reports.export' => [],
    'analytics.view' => [],
    'analytics.train' => [],
    'users.manage' => [],
  ];
}

function has_permission(string $p): bool {
  $my = current_user_permissions();
  if (in_array('*', $my, true)) return true;
  if (in_array($p, $my, true)) return true;
  
  $aliases = tmm_permission_aliases();
  if (isset($aliases[$p])) {
    foreach ($aliases[$p] as $alias) {
      if (in_array($alias, $my, true)) return true;
    }
  }
  return false;
}

function has_any_permission(array $permissions): bool {
  foreach ($permissions as $p) {
    if (has_permission($p)) return true;
  }
  return false;
}
