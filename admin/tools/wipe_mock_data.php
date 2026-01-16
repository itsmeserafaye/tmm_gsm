<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$db = db();
require_role(['SuperAdmin']);

$confirm = (string)($_POST['confirm'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Content-Type: text/html; charset=utf-8');
  echo '<div style="max-width:720px;margin:40px auto;font-family:system-ui,Segoe UI,Arial,sans-serif">';
  echo '<h2 style="margin:0 0 12px 0">Wipe Mock Data</h2>';
  echo '<p style="margin:0 0 16px 0;color:#444">This removes mock/demo data for Modules 1–5 and deletes Operator Portal accounts. It does not delete RBAC users/roles.</p>';
  echo '<form method="post">';
  echo '<label style="display:block;margin:0 0 6px 0;font-weight:700">Type WIPE_MOCK_DATA to confirm</label>';
  echo '<input name="confirm" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px" />';
  echo '<button type="submit" style="margin-top:12px;padding:10px 14px;border:0;border-radius:8px;background:#b91c1c;color:#fff;font-weight:800">Wipe Now</button>';
  echo '</form>';
  echo '</div>';
  exit;
}

header('Content-Type: application/json');
if ($confirm !== 'WIPE_MOCK_DATA') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'confirm_required']);
  exit;
}

$tables = [
  'terminal_assignments',
  'vehicle_documents',
  'ownership_transfers',
  'franchise_applications',
  'vehicles',
  'coops',
  'operators',
  'routes',
  'lptrp_routes',
  'endorsement_records',
  'compliance_cases',
  'documents',
  'ticket_notifications',
  'evidence',
  'payment_records',
  'compliance_summary',
  'tickets',
  'inspection_photos',
  'inspection_certificates',
  'inspection_checklist_items',
  'inspection_results',
  'inspection_schedules',
  'parking_violations',
  'parking_transactions',
  'parking_rates',
  'parking_areas',
  'terminal_area_operators',
  'drivers',
  'terminal_areas',
  'terminals',
  'puv_demand_observations',
  'external_data_cache',
  'operator_portal_user_plates',
  'operator_portal_users',
];

$deleted = [];
$skipped = [];
$errors = [];

$db->query("SET FOREIGN_KEY_CHECKS=0");
foreach ($tables as $t) {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) { $skipped[] = $t; continue; }
  $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  if (!$stmt) { $errors[] = ['table' => $t, 'error' => 'db_prepare_failed']; continue; }
  $stmt->bind_param('s', $t);
  $stmt->execute();
  $exists = (bool)($stmt->get_result()->fetch_row());
  $stmt->close();
  if (!$exists) { $skipped[] = $t; continue; }

  if (!$db->query("TRUNCATE TABLE `$t`")) {
    $errors[] = ['table' => $t, 'error' => $db->error];
  } else {
    $deleted[] = $t;
  }
}
$db->query("SET FOREIGN_KEY_CHECKS=1");

echo json_encode([
  'ok' => empty($errors),
  'deleted_tables' => $deleted,
  'skipped_tables' => $skipped,
  'errors' => $errors,
]);
