<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
require_login();

$db = db();

$canSchedule = has_permission('module4.schedule');
$canInspect = has_permission('module4.inspect');
$canVehicles = has_any_permission(['module1.read','module1.write','module4.inspect','module4.schedule']);

$scheduledCount = 0;
$overdueCount = 0;
$expiredDocsCount = 0;

if ($canSchedule || $canInspect) {
  $res = $db->query("SELECT
      SUM(status IN ('Scheduled','Rescheduled','Pending Verification','Pending Assignment')) AS scheduled_count,
      SUM(status IN ('Overdue','Overdue / No-Show')) AS overdue_count
    FROM inspection_schedules");
  if ($res && ($row = $res->fetch_assoc())) {
    $scheduledCount = (int)($row['scheduled_count'] ?? 0);
    $overdueCount = (int)($row['overdue_count'] ?? 0);
  }
}

if ($canVehicles) {
  $res = $db->query("SELECT COUNT(DISTINCT vehicle_id) AS c
                     FROM vehicle_registrations
                     WHERE registration_status='Expired'
                        OR (orcr_date IS NOT NULL AND orcr_date < DATE_SUB(CURDATE(), INTERVAL 365 DAY))");
  if ($res && ($row = $res->fetch_assoc())) {
    $expiredDocsCount = (int)($row['c'] ?? 0);
  }
  $res2 = $db->query("SELECT COUNT(DISTINCT plate_number) AS c
                      FROM vehicle_record_submissions
                      WHERE status='Approved' AND or_expiry_date IS NOT NULL AND or_expiry_date < CURDATE()");
  if ($res2 && ($row2 = $res2->fetch_assoc())) {
    $expiredDocsCount += (int)($row2['c'] ?? 0);
  }
}

$items = [];
if ($canSchedule || $canInspect) {
  $items[] = [
    'key' => 'scheduled_inspections',
    'label' => 'Scheduled inspections',
    'count' => $scheduledCount,
    'href' => '?page=module4/submodule3',
  ];
  $items[] = [
    'key' => 'overdue_inspections',
    'label' => 'Overdue / No-show',
    'count' => $overdueCount,
    'href' => '?page=module4/submodule3&list_status=' . rawurlencode('Overdue'),
  ];
}
if ($canVehicles) {
  $items[] = [
    'key' => 'expired_docs',
    'label' => 'Expired documents',
    'count' => $expiredDocsCount,
    'href' => '?page=module4/submodule1',
  ];
}

$total = 0;
foreach ($items as $it) $total += (int)($it['count'] ?? 0);

echo json_encode([
  'ok' => true,
  'total' => $total,
  'items' => $items,
]);
?>
