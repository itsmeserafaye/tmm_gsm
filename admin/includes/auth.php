<?php
if (php_sapi_name() !== 'cli' && function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

function tmm_get_app_setting(string $key, ?string $default = null): ?string {
  static $cache = [];
  $now = time();
  if (isset($cache[$key]) && ($now - (int)($cache[$key]['ts'] ?? 0)) < 60) {
    return $cache[$key]['val'];
  }
  try {
    require_once __DIR__ . '/db.php';
    $db = db();
    $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('s', $key);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      $val = $row ? (string)($row['setting_value'] ?? '') : '';
      $val = $val !== '' ? $val : ($default ?? '');
      $cache[$key] = ['ts' => $now, 'val' => $val];
      return $val;
    }
  } catch (Throwable $e) {
  }
  $cache[$key] = ['ts' => $now, 'val' => ($default ?? '')];
  return $default;
}

function tmm_session_timeout_seconds(): int {
  $raw = tmm_get_app_setting('session_timeout', '30');
  $min = (int)trim((string)$raw);
  if ($min <= 0) $min = 30;
  if ($min > 1440) $min = 1440;
  return $min * 60;
}

function tmm_logout_unauthorized(string $error): void {
  if (php_sapi_name() !== 'cli' && function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    @session_unset();
    @session_destroy();
    $sn = session_name();
    if ($sn) {
      @setcookie($sn, '', time() - 3600, '/');
    }
  }
  if (defined('TMM_TEST')) {
    throw new Exception($error);
  }
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => $error]);
  exit;
}

function tmm_enforce_session_timeout(): void {
  if (php_sapi_name() === 'cli') return;
  if (empty($_SESSION['user_id'])) return;
  $now = time();
  $ttl = tmm_session_timeout_seconds();
  $last = (int)($_SESSION['last_activity'] ?? 0);
  if ($last > 0 && ($now - $last) > $ttl) {
    tmm_logout_unauthorized('session_expired');
  }
  $_SESSION['last_activity'] = $now;
}

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
  if (!empty($_SESSION['user_id'])) {
    tmm_enforce_session_timeout();
    return;
  }
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

    'module3.issue' => ['module3.tickets.issue', 'tickets.issue', 'tickets.validate'],
    'module3.read' => ['module3.view'],
    'module3.settle' => ['module3.tickets.settle', 'tickets.settle'],
    'module3.analytics' => ['module3.view'],

    'module4.schedule' => ['module4.inspections.manage'],
    'module4.inspect' => ['module4.inspections.manage'],
    'module4.read' => ['module4.view'],
    'module4.certify' => ['module4.inspections.manage'],

    'module5.manage_terminal' => ['module5.terminals.manage', 'parking.manage'],
    'module5.assign_vehicle' => ['module5.terminals.manage'],
    'module5.parking_fees' => ['module5.terminals.manage', 'parking.manage'],
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
  foreach ($aliases as $canonical => $legacyList) {
    if (!is_array($legacyList) || $legacyList === []) continue;
    if (in_array($p, $legacyList, true) && in_array($canonical, $my, true)) return true;
  }
  return false;
}

function has_any_permission(array $permissions): bool {
  foreach ($permissions as $p) {
    if (has_permission($p)) return true;
  }
  return false;
}

function require_permission(string $p) {
  require_login();
  if (!has_permission($p)) {
    if (defined('TMM_TEST')) {
      throw new Exception('forbidden');
    } else {
      http_response_code(403);
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'error'=>'forbidden','permission'=>$p]);
      exit;
    }
  }
}

function require_any_permission(array $permissions) {
  require_login();
  if (!has_any_permission($permissions)) {
    if (defined('TMM_TEST')) {
      throw new Exception('forbidden');
    } else {
      http_response_code(403);
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'error'=>'forbidden','required_any'=>$permissions]);
      exit;
    }
  }
}
