<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$db = db();
require_role(['SuperAdmin']);

if (!($db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lptrp_routes' LIMIT 1")->fetch_row())) {
  $db->query("CREATE TABLE IF NOT EXISTS lptrp_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    start_point VARCHAR(255),
    end_point VARCHAR(255),
    max_vehicle_capacity INT DEFAULT 0,
    current_vehicle_count INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
}

$confirm = (string)($_POST['confirm'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Content-Type: text/html; charset=utf-8');
  echo '<div style="max-width:720px;margin:40px auto;font-family:system-ui,Segoe UI,Arial,sans-serif">';
  echo '<h2 style="margin:0 0 12px 0">Promote Routes → LPTRP Masterlist</h2>';
  echo '<p style="margin:0 0 16px 0;color:#444">Copies any route in routes.route_id that is missing from lptrp_routes.route_code.</p>';
  echo '<form method="post">';
  echo '<label style="display:block;margin:0 0 6px 0;font-weight:700">Type PROMOTE_ROUTES to confirm</label>';
  echo '<input name="confirm" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px" />';
  echo '<button type="submit" style="margin-top:12px;padding:10px 14px;border:0;border-radius:8px;background:#0f766e;color:#fff;font-weight:800">Promote</button>';
  echo '</form>';
  echo '</div>';
  exit;
}

header('Content-Type: application/json');
if ($confirm !== 'PROMOTE_ROUTES') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'confirm_required']);
  exit;
}

$routesHasOrigin = $db->query("SHOW COLUMNS FROM routes LIKE 'origin'");
$routesHasDest = $db->query("SHOW COLUMNS FROM routes LIKE 'destination'");
$hasOrigin = $routesHasOrigin && $routesHasOrigin->num_rows > 0;
$hasDest = $routesHasDest && $routesHasDest->num_rows > 0;

$sql = "SELECT route_id, route_name, max_vehicle_limit, status" . ($hasOrigin ? ", origin" : "") . ($hasDest ? ", destination" : "") . " FROM routes";
$res = $db->query($sql);
$sel = $db->prepare("SELECT id FROM lptrp_routes WHERE route_code=? LIMIT 1");
$ins = $db->prepare("INSERT INTO lptrp_routes(route_code, description, start_point, end_point, max_vehicle_capacity, status) VALUES (?,?,?,?,?,?)");

$promoted = 0;
$skipped = 0;
if ($res && $sel && $ins) {
  while ($r = $res->fetch_assoc()) {
    $code = strtoupper(trim((string)($r['route_id'] ?? '')));
    if ($code === '') { $skipped++; continue; }
    $sel->bind_param('s', $code);
    $sel->execute();
    $exists = (bool)$sel->get_result()->fetch_row();
    if ($exists) { $skipped++; continue; }

    $desc = (string)($r['route_name'] ?? $code);
    $sp = $hasOrigin ? (string)($r['origin'] ?? '') : '';
    $ep = $hasDest ? (string)($r['destination'] ?? '') : '';
    $cap = (int)($r['max_vehicle_limit'] ?? 0);
    $st = strtolower(trim((string)($r['status'] ?? 'active')));
    $lstatus = ($st === '' || $st === 'active' || $st === 'approved') ? 'Approved' : 'Pending';
    $ins->bind_param('ssssis', $code, $desc, $sp, $ep, $cap, $lstatus);
    if ($ins->execute()) $promoted++; else $skipped++;
  }
}

echo json_encode(['ok' => true, 'promoted' => $promoted, 'skipped' => $skipped]);

