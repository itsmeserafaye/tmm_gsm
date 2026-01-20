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
  if (current_user_role() === 'Viewer') {
    $out = array_values(array_filter($out, function ($p) {
      return !($p === 'reports.export');
    }));
  }
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
    'module2.history' => ['module2.franchises.manage'],

    'module3.read' => ['module3.view'],
    'module3.issue' => ['tickets.issue','tickets.validate'],
    'module3.settle' => ['tickets.settle'],
    'module3.analytics' => ['analytics.view','reports.export'],

    'module4.read' => ['module4.view'],
    'module4.schedule' => ['module4.inspections.manage'],
    'module4.inspect' => ['module4.inspections.manage'],
    'module4.certify' => ['module4.inspections.manage'],

    'module5.read' => ['module5.view'],
    'module5.manage_terminal' => ['parking.manage'],
    'module5.assign_vehicle' => ['parking.manage'],
    'module5.parking_fees' => ['parking.manage','tickets.settle'],

    'module1.view' => ['module1.read'],
    'module1.vehicles.write' => ['module1.write'],
    'module1.routes.write' => ['module1.write','module1.route_manage'],
    'module1.coops.write' => ['module1.write'],

    'module2.view' => ['module2.read'],
    'module2.franchises.manage' => ['module2.apply','module2.endorse','module2.approve','module2.history'],

    'module3.view' => ['module3.read'],
    'tickets.issue' => ['module3.issue'],
    'tickets.validate' => ['module3.issue'],
    'tickets.settle' => ['module3.settle'],

    'module4.view' => ['module4.read'],
    'module4.inspections.manage' => ['module4.schedule','module4.inspect','module4.certify'],

    'module5.view' => ['module5.read'],
    'parking.manage' => ['module5.manage_terminal','module5.assign_vehicle','module5.parking_fees'],
  ];
}

function has_permission(string $code) {
  $perms = current_user_permissions();
  if (in_array($code, $perms, true)) return true;
  $aliases = tmm_permission_aliases();
  foreach ($aliases[$code] ?? [] as $alt) {
    if (is_string($alt) && $alt !== '' && in_array($alt, $perms, true)) return true;
  }
  $role = current_user_role();
  if ($role === 'SuperAdmin') return true;

  // Use config for fallback
  $config = rbac_get_config_auth();
  $rolePerms = $config['role_permissions'] ?? [];
  $allowed = $rolePerms[$role] ?? [];

  if (in_array('*', $allowed, true)) return true;

  if (in_array($code, $allowed, true)) return true;
  
  foreach ($aliases[$code] ?? [] as $alt) {
    if (in_array($alt, $allowed, true)) return true;
  }
  return false;
}

function has_any_permission(array $codes) {
  foreach ($codes as $c) {
    if (is_string($c) && $c !== '' && has_permission($c)) return true;
  }
  return false;
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

function require_any_permission(array $codes) {
  require_login();
  if (has_any_permission($codes)) return;
  if (defined('TMM_TEST')) {
    throw new Exception('forbidden');
  }
  http_response_code(403);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'forbidden','permissions'=>$codes,'role'=>current_user_role()]);
  exit;
}

function require_permission_page(string $code, string $message = 'You do not have access to this page.') {
  if (has_permission($code)) return true;
  echo '<div class="mx-auto max-w-3xl px-4 py-10">';
  echo '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-700">';
  echo '<div class="text-lg font-black">Access Denied</div>';
  echo '<div class="mt-1 text-sm font-bold">' . htmlspecialchars($message) . '</div>';
  echo '</div>';
  echo '</div>';
  return false;
}

function require_any_permission_page(array $codes, string $message = 'You do not have access to this page.') {
  if (has_any_permission($codes)) return true;
  echo '<div class="mx-auto max-w-3xl px-4 py-10">';
  echo '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-700">';
  echo '<div class="text-lg font-black">Access Denied</div>';
  echo '<div class="mt-1 text-sm font-bold">' . htmlspecialchars($message) . '</div>';
  echo '</div>';
  echo '</div>';
  return false;
}
