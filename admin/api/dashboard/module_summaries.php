<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_login();

$db = db();

function ms_table_exists(mysqli $db, string $name): bool {
  $stmt = $db->prepare("SHOW TABLES LIKE ?");
  if (!$stmt) return false;
  $stmt->bind_param('s', $name);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_row();
  $stmt->close();
  return (bool)$row;
}

function ms_count(mysqli $db, string $sql): int {
  $res = $db->query($sql);
  if (!$res) return 0;
  $row = $res->fetch_assoc();
  return (int)($row['c'] ?? 0);
}

function ms_sum(mysqli $db, string $sql): float {
  $res = $db->query($sql);
  if (!$res) return 0.0;
  $row = $res->fetch_assoc();
  return (float)($row['s'] ?? 0);
}

function ms_status_counts(mysqli $db, string $table, string $statusCol = 'status'): array {
  $out = [];
  $res = $db->query("SELECT {$statusCol} AS st, COUNT(*) AS c FROM {$table} GROUP BY {$statusCol}");
  if (!$res) return $out;
  while ($r = $res->fetch_assoc()) {
    $k = (string)($r['st'] ?? '');
    if ($k === '') $k = 'Unknown';
    $out[$k] = (int)($r['c'] ?? 0);
  }
  return $out;
}

$modules = [];

try {
  $vehiclesTotal = ms_table_exists($db, 'vehicles') ? ms_count($db, "SELECT COUNT(*) AS c FROM vehicles") : 0;
  $operatorsTotal = ms_table_exists($db, 'operators') ? ms_count($db, "SELECT COUNT(*) AS c FROM operators") : 0;
  $routesActive = ms_table_exists($db, 'routes') ? ms_count($db, "SELECT COUNT(*) AS c FROM routes WHERE status IS NULL OR status='Active'") : 0;
  $pendingVehicleSub = ms_table_exists($db, 'vehicle_submissions') ? ms_count($db, "SELECT COUNT(*) AS c FROM vehicle_submissions WHERE status='Pending'") : null;
  $pendingOperatorSub = ms_table_exists($db, 'operator_submissions') ? ms_count($db, "SELECT COUNT(*) AS c FROM operator_submissions WHERE status='Pending'") : null;

  $m1Stats = [
    ['label' => 'Vehicles', 'value' => $vehiclesTotal],
    ['label' => 'Operators', 'value' => $operatorsTotal],
    ['label' => 'Active routes', 'value' => $routesActive],
  ];
  if ($pendingVehicleSub !== null) $m1Stats[] = ['label' => 'Pending vehicle submissions', 'value' => $pendingVehicleSub];
  if ($pendingOperatorSub !== null) $m1Stats[] = ['label' => 'Pending operator submissions', 'value' => $pendingOperatorSub];

  $modules[] = [
    'id' => 'module1',
    'label' => 'Module 1 — Operators & Vehicles',
    'icon' => 'bus',
    'link' => '?page=module1/submodule1',
    'stats' => $m1Stats,
  ];

  $faExists = ms_table_exists($db, 'franchise_applications');
  $faTotal = $faExists ? ms_count($db, "SELECT COUNT(*) AS c FROM franchise_applications") : 0;
  $faPending = $faExists ? ms_count($db, "SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Pending'") : 0;
  $faEndorsed = $faExists ? ms_count($db, "SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Endorsed'") : 0;
  $faApproved = $faExists ? ms_count($db, "SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Approved'") : 0;

  $modules[] = [
    'id' => 'module2',
    'label' => 'Module 2 — Franchises',
    'icon' => 'file-badge',
    'link' => '?page=module2/submodule1',
    'stats' => [
      ['label' => 'Applications', 'value' => $faTotal],
      ['label' => 'Pending', 'value' => $faPending],
      ['label' => 'Endorsed', 'value' => $faEndorsed],
      ['label' => 'Approved', 'value' => $faApproved],
    ],
  ];

  $ticketsToday = ms_table_exists($db, 'tickets') ? ms_count($db, "SELECT COUNT(*) AS c FROM tickets WHERE DATE(date_issued)=CURDATE()") : 0;
  $ticketsOpen = ms_table_exists($db, 'tickets') ? ms_count($db, "SELECT COUNT(*) AS c FROM tickets WHERE status IN ('Pending','Validated','Escalated')") : 0;
  $ticketRevenueToday = ms_table_exists($db, 'ticket_payments') ? ms_sum($db, "SELECT SUM(amount_paid) AS s FROM ticket_payments WHERE DATE(paid_at)=CURDATE()") : 0.0;

  $modules[] = [
    'id' => 'module3',
    'label' => 'Module 3 — Tickets & Violations',
    'icon' => 'alert-octagon',
    'link' => '?page=module3/submodule1',
    'stats' => [
      ['label' => 'Tickets today', 'value' => $ticketsToday],
      ['label' => 'Open / unpaid', 'value' => $ticketsOpen],
      ['label' => 'Collections today', 'value' => $ticketRevenueToday, 'format' => 'currency'],
    ],
  ];

  $inspExists = ms_table_exists($db, 'inspection_schedules');
  $inspTotal = $inspExists ? ms_count($db, "SELECT COUNT(*) AS c FROM inspection_schedules") : 0;
  $inspScheduled = $inspExists ? ms_count($db, "SELECT COUNT(*) AS c FROM inspection_schedules WHERE status='Scheduled'") : 0;
  $inspPendingVerify = $inspExists ? ms_count($db, "SELECT COUNT(*) AS c FROM inspection_schedules WHERE status='Pending Verification'") : 0;
  $inspPendingAssign = $inspExists ? ms_count($db, "SELECT COUNT(*) AS c FROM inspection_schedules WHERE status='Pending Assignment'") : 0;
  $inspCompleted = $inspExists ? ms_count($db, "SELECT COUNT(*) AS c FROM inspection_schedules WHERE status='Completed'") : 0;
  $certIssued = ms_table_exists($db, 'inspection_certificates') ? ms_count($db, "SELECT COUNT(*) AS c FROM inspection_certificates") : 0;

  $modules[] = [
    'id' => 'module4',
    'label' => 'Module 4 — Inspections',
    'icon' => 'clipboard-check',
    'link' => '?page=module4/submodule1',
    'stats' => [
      ['label' => 'Schedules', 'value' => $inspTotal],
      ['label' => 'Scheduled', 'value' => $inspScheduled],
      ['label' => 'Pending verification', 'value' => $inspPendingVerify],
      ['label' => 'Pending assignment', 'value' => $inspPendingAssign],
      ['label' => 'Completed', 'value' => $inspCompleted],
      ['label' => 'Certificates issued', 'value' => $certIssued],
    ],
  ];

  $terminals = ms_table_exists($db, 'terminals') ? ms_count($db, "SELECT COUNT(*) AS c FROM terminals") : 0;
  $areas = ms_table_exists($db, 'parking_areas') ? ms_count($db, "SELECT COUNT(*) AS c FROM parking_areas") : 0;
  $slotsTotal = ms_table_exists($db, 'parking_slots') ? ms_count($db, "SELECT COUNT(*) AS c FROM parking_slots") : null;
  $slotsOccupied = ms_table_exists($db, 'parking_slots') ? ms_count($db, "SELECT COUNT(*) AS c FROM parking_slots WHERE status='Occupied'") : null;
  $slotRevenueToday = ms_table_exists($db, 'parking_payments') ? ms_sum($db, "SELECT SUM(amount) AS s FROM parking_payments WHERE DATE(paid_at)=CURDATE()") : 0.0;
  $parkingTxRevenueToday = ms_table_exists($db, 'parking_transactions') ? ms_sum($db, "SELECT SUM(amount) AS s FROM parking_transactions WHERE UPPER(COALESCE(status,'Paid'))='PAID' AND DATE(COALESCE(paid_at, created_at))=CURDATE()") : 0.0;
  $parkingRevenueToday = $slotRevenueToday + $parkingTxRevenueToday;

  $m5Stats = [
    ['label' => 'Terminals', 'value' => $terminals],
    ['label' => 'Parking areas', 'value' => $areas],
    ['label' => 'Parking collections today', 'value' => $parkingRevenueToday, 'format' => 'currency'],
  ];
  if ($slotsTotal !== null && $slotsOccupied !== null) {
    $m5Stats[] = ['label' => 'Slots', 'value' => $slotsTotal];
    $m5Stats[] = ['label' => 'Occupied', 'value' => $slotsOccupied];
  }

  $modules[] = [
    'id' => 'module5',
    'label' => 'Module 5 — Terminals & Parking',
    'icon' => 'parking-circle',
    'link' => '?page=module5/submodule1',
    'stats' => $m5Stats,
  ];

  $commuters = ms_table_exists($db, 'commuters') ? ms_count($db, "SELECT COUNT(*) AS c FROM commuters") : null;
  $portalOperators = ms_table_exists($db, 'operator_portal_users') ? ms_count($db, "SELECT COUNT(*) AS c FROM operator_portal_users") : null;
  $complaints = ms_table_exists($db, 'complaints') ? ms_count($db, "SELECT COUNT(*) AS c FROM complaints") : null;

  $mUserStats = [];
  if ($commuters !== null) $mUserStats[] = ['label' => 'Commuters', 'value' => $commuters];
  if ($portalOperators !== null) $mUserStats[] = ['label' => 'Operator portal users', 'value' => $portalOperators];
  if ($complaints !== null) $mUserStats[] = ['label' => 'Complaints', 'value' => $complaints];

  if (!empty($mUserStats)) {
    $modules[] = [
      'id' => 'users',
      'label' => 'Users & Requests',
      'icon' => 'users',
      'link' => '?page=users/commuters',
      'stats' => $mUserStats,
    ];
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'module_summaries_failed']);
  exit;
}

echo json_encode([
  'ok' => true,
  'generated_at' => date('c'),
  'modules' => $modules,
]);

