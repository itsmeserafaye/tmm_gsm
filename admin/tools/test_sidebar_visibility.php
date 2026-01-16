<?php
if (php_sapi_name() !== 'cli') {
  http_response_code(403);
  echo "forbidden\n";
  exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sidebar_items.php';

$role = $argv[1] ?? 'Inspector';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = $role;

$navAllowed = function (array $node): bool {
  if (!empty($node['roles']) && is_array($node['roles'])) {
    return in_array(current_user_role(), $node['roles'], true);
  }
  if (!empty($node['anyPermissions']) && is_array($node['anyPermissions'])) {
    return has_any_permission($node['anyPermissions']);
  }
  return true;
};

$out = [];
foreach ($sidebarItems as $item) {
  if (!empty($item['subItems'])) {
    $subs = [];
    foreach ($item['subItems'] as $sub) {
      if ($navAllowed($sub)) $subs[] = $sub['path'];
    }
    if ($subs) $out[$item['id']] = $subs;
    continue;
  }
  if ($navAllowed($item)) $out[$item['id']] = [$item['path'] ?? null];
}

echo json_encode([
  'ok' => true,
  'role' => current_user_role(),
  'visible' => $out
], JSON_PRETTY_PRINT) . "\n";

