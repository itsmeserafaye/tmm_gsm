<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_login();

$db = db();
$debug = ((int)($_GET['debug'] ?? 0)) === 1 && function_exists('has_permission') && has_permission('settings.manage');
$diagnostics = [
  'db' => null,
  'picked' => [],
  'errors' => [],
];

try {
  $rDb = $db->query("SELECT DATABASE() AS dbname");
  if ($rDb && ($rowDb = $rDb->fetch_assoc())) {
    $diagnostics['db'] = (string)($rowDb['dbname'] ?? '');
  }
} catch (Throwable $e) {
}

function ms_table_exists(mysqli $db, string $name): bool {
  $stmt = $db->prepare("SHOW TABLES LIKE ?");
  if (!$stmt) return false;
  $stmt->bind_param('s', $name);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_row();
  $stmt->close();
  return (bool)$row;
}

function ms_table_has_rows(mysqli $db, string $table): bool {
  $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
  if ($table === '') return false;
  $res = $db->query("SELECT 1 FROM {$table} LIMIT 1");
  return (bool)($res && $res->fetch_row());
}

function ms_pick_table(mysqli $db, array $candidates): ?string {
  foreach ($candidates as $t) {
    $t = trim((string)$t);
    if ($t === '') continue;
    if (ms_table_exists($db, $t)) return $t;
  }
  return null;
}

function ms_tables_with_column(mysqli $db, string $column): array {
  $column = trim($column);
  if ($column === '') return [];
  $stmt = $db->prepare("SELECT TABLE_NAME AS t
                        FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME=?
                        GROUP BY TABLE_NAME");
  if (!$stmt) return [];
  $stmt->bind_param('s', $column);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  while ($res && ($r = $res->fetch_assoc())) {
    $t = (string)($r['t'] ?? '');
    if ($t !== '') $out[] = $t;
  }
  $stmt->close();
  return $out;
}

function ms_pick_table_by_columns(mysqli $db, array $requiredCols, array $preferNameTokens = []): ?string {
  $candidates = null;
  foreach ($requiredCols as $col) {
    $list = ms_tables_with_column($db, (string)$col);
    if ($candidates === null) $candidates = $list;
    else $candidates = array_values(array_intersect($candidates, $list));
    if ($candidates === []) break;
  }
  if (!$candidates) return null;

  $scored = [];
  foreach ($candidates as $t) {
    $name = strtolower((string)$t);
    $score = 0;
    foreach ($preferNameTokens as $tok) {
      $tok = strtolower((string)$tok);
      if ($tok !== '' && strpos($name, $tok) !== false) $score += 10;
    }
    if ($name === 'vehicles' || $name === 'operators' || $name === 'routes' || $name === 'tickets') $score += 25;
    if (ms_table_has_rows($db, $t)) $score += 50;
    $scored[] = ['t' => $t, 'score' => $score];
  }
  usort($scored, function ($a, $b) { return ($b['score'] ?? 0) <=> ($a['score'] ?? 0); });
  return isset($scored[0]['t']) ? (string)$scored[0]['t'] : null;
}

function ms_column_exists(mysqli $db, string $table, string $col): bool {
  $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
  if (!$stmt) return false;
  $stmt->bind_param('s', $col);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_row();
  $stmt->close();
  return (bool)$row;
}

function ms_pick_column(mysqli $db, string $table, array $candidates): ?string {
  foreach ($candidates as $c) {
    $c = trim((string)$c);
    if ($c === '') continue;
    if (ms_column_exists($db, $table, $c)) return $c;
  }
  return null;
}

function ms_scalar(mysqli $db, string $sql, string $field): array {
  $res = $db->query($sql);
  if (!$res) return ['value' => null, 'error' => (string)($db->error ?? 'query_failed')];
  $row = $res->fetch_assoc();
  if (!$row) return ['value' => null, 'error' => 'no_row'];
  return ['value' => $row[$field] ?? null, 'error' => null];
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
  $tVehicles = ms_pick_table($db, ['vehicles', 'vehicle']);
  if (!$tVehicles) $tVehicles = ms_pick_table_by_columns($db, ['plate_number'], ['vehicle', 'puv']);
  $tOperators = ms_pick_table($db, ['operators', 'operator']);
  if (!$tOperators) $tOperators = ms_pick_table_by_columns($db, ['verification_status'], ['operator']);
  if (!$tOperators) $tOperators = ms_pick_table_by_columns($db, ['operator_type'], ['operator']);
  $tRoutes = ms_pick_table($db, ['routes', 'route']);
  if (!$tRoutes) $tRoutes = ms_pick_table_by_columns($db, ['route_id'], ['route']);
  $tVehicleSub = ms_pick_table($db, ['vehicle_submissions']);
  $tOperatorSub = ms_pick_table($db, ['operator_submissions']);
  if ($debug) {
    $diagnostics['picked']['vehicles_table'] = $tVehicles;
    $diagnostics['picked']['operators_table'] = $tOperators;
    $diagnostics['picked']['routes_table'] = $tRoutes;
  }

  $vehiclesTotalR = $tVehicles ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tVehicles}", 'c') : ['value' => null, 'error' => 'missing_table'];
  $operatorsTotalR = $tOperators ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tOperators}", 'c') : ['value' => null, 'error' => 'missing_table'];
  $routesActiveR = $tRoutes ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tRoutes} WHERE status IS NULL OR status='Active'", 'c') : ['value' => null, 'error' => 'missing_table'];
  $pendingVehicleSubR = $tVehicleSub ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tVehicleSub} WHERE status='Pending'", 'c') : ['value' => null, 'error' => 'missing_table'];
  $pendingOperatorSubR = $tOperatorSub ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tOperatorSub} WHERE status='Pending'", 'c') : ['value' => null, 'error' => 'missing_table'];
  if ($debug) {
    foreach ([
      'vehicles_total' => $vehiclesTotalR,
      'operators_total' => $operatorsTotalR,
      'routes_active' => $routesActiveR,
      'pending_vehicle_submissions' => $pendingVehicleSubR,
      'pending_operator_submissions' => $pendingOperatorSubR,
    ] as $k => $r) {
      if (($r['error'] ?? null) !== null) $diagnostics['errors'][$k] = (string)$r['error'];
    }
  }

  $m1Stats = [
    ['label' => 'Vehicles', 'value' => $vehiclesTotalR['value'] !== null ? (int)$vehiclesTotalR['value'] : null],
    ['label' => 'Operators', 'value' => $operatorsTotalR['value'] !== null ? (int)$operatorsTotalR['value'] : null],
    ['label' => 'Active routes', 'value' => $routesActiveR['value'] !== null ? (int)$routesActiveR['value'] : null],
  ];
  if ($tVehicleSub) $m1Stats[] = ['label' => 'Pending vehicle submissions', 'value' => $pendingVehicleSubR['value'] !== null ? (int)$pendingVehicleSubR['value'] : null];
  if ($tOperatorSub) $m1Stats[] = ['label' => 'Pending operator submissions', 'value' => $pendingOperatorSubR['value'] !== null ? (int)$pendingOperatorSubR['value'] : null];

  $modules[] = [
    'id' => 'module1',
    'label' => 'Module 1 — Operators & Vehicles',
    'icon' => 'bus',
    'link' => '?page=module1/submodule1',
    'stats' => $m1Stats,
  ];

  $tFranchise = ms_pick_table($db, ['franchise_applications', 'franchises', 'franchise_application']);
  if ($debug) $diagnostics['picked']['franchise_table'] = $tFranchise;
  $faTotalR = $tFranchise ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tFranchise}", 'c') : ['value' => null, 'error' => 'missing_table'];
  $faPendingR = $tFranchise ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tFranchise} WHERE status='Pending'", 'c') : ['value' => null, 'error' => 'missing_table'];
  $faEndorsedR = $tFranchise ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tFranchise} WHERE status='Endorsed'", 'c') : ['value' => null, 'error' => 'missing_table'];
  $faApprovedR = $tFranchise ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tFranchise} WHERE status='Approved'", 'c') : ['value' => null, 'error' => 'missing_table'];

  $modules[] = [
    'id' => 'module2',
    'label' => 'Module 2 — Franchises',
    'icon' => 'file-badge',
    'link' => '?page=module2/submodule1',
    'stats' => [
      ['label' => 'Applications', 'value' => $faTotalR['value'] !== null ? (int)$faTotalR['value'] : null],
      ['label' => 'Pending', 'value' => $faPendingR['value'] !== null ? (int)$faPendingR['value'] : null],
      ['label' => 'Endorsed', 'value' => $faEndorsedR['value'] !== null ? (int)$faEndorsedR['value'] : null],
      ['label' => 'Approved', 'value' => $faApprovedR['value'] !== null ? (int)$faApprovedR['value'] : null],
    ],
  ];

  $tTickets = ms_pick_table($db, ['tickets', 'violations']);
  $tTicketPayments = ms_pick_table($db, ['ticket_payments']);
  $ticketsDateCol = $tTickets ? ms_pick_column($db, $tTickets, ['date_issued', 'created_at', 'issued_at', 'date_created']) : null;
  $tpPaidAtCol = $tTicketPayments ? ms_pick_column($db, $tTicketPayments, ['paid_at', 'created_at']) : null;
  if ($debug) {
    $diagnostics['picked']['tickets_table'] = $tTickets;
    $diagnostics['picked']['tickets_date_col'] = $ticketsDateCol;
    $diagnostics['picked']['ticket_payments_table'] = $tTicketPayments;
    $diagnostics['picked']['ticket_payments_paid_col'] = $tpPaidAtCol;
  }
  $ticketsTodayR = ($tTickets && $ticketsDateCol) ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tTickets} WHERE DATE({$ticketsDateCol})=CURDATE()", 'c') : ['value' => null, 'error' => 'missing_table_or_column'];
  $ticketsOpenR = $tTickets ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tTickets} WHERE status IN ('Pending','Validated','Escalated')", 'c') : ['value' => null, 'error' => 'missing_table'];
  $ticketRevenueTodayR = ($tTicketPayments && $tpPaidAtCol) ? ms_scalar($db, "SELECT SUM(amount_paid) AS s FROM {$tTicketPayments} WHERE DATE({$tpPaidAtCol})=CURDATE()", 's') : ['value' => null, 'error' => 'missing_table_or_column'];

  $modules[] = [
    'id' => 'module3',
    'label' => 'Module 3 — Tickets & Violations',
    'icon' => 'alert-octagon',
    'link' => '?page=module3/submodule1',
    'stats' => [
      ['label' => 'Tickets today', 'value' => $ticketsTodayR['value'] !== null ? (int)$ticketsTodayR['value'] : null],
      ['label' => 'Open / unpaid', 'value' => $ticketsOpenR['value'] !== null ? (int)$ticketsOpenR['value'] : null],
      ['label' => 'Collections today', 'value' => $ticketRevenueTodayR['value'] !== null ? (float)$ticketRevenueTodayR['value'] : null, 'format' => 'currency'],
    ],
  ];

  $tInsp = ms_pick_table($db, ['inspection_schedules', 'inspections', 'inspection_schedule']);
  $tCert = ms_pick_table($db, ['inspection_certificates']);
  if ($debug) {
    $diagnostics['picked']['inspection_schedules_table'] = $tInsp;
    $diagnostics['picked']['inspection_certificates_table'] = $tCert;
  }
  $inspTotalR = $tInsp ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tInsp}", 'c') : ['value' => null, 'error' => 'missing_table'];
  $inspScheduledR = $tInsp ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tInsp} WHERE status='Scheduled'", 'c') : ['value' => null, 'error' => 'missing_table'];
  $inspPendingVerifyR = $tInsp ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tInsp} WHERE status='Pending Verification'", 'c') : ['value' => null, 'error' => 'missing_table'];
  $inspPendingAssignR = $tInsp ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tInsp} WHERE status='Pending Assignment'", 'c') : ['value' => null, 'error' => 'missing_table'];
  $inspCompletedR = $tInsp ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tInsp} WHERE status='Completed'", 'c') : ['value' => null, 'error' => 'missing_table'];
  $certIssuedR = $tCert ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tCert}", 'c') : ['value' => null, 'error' => 'missing_table'];

  $modules[] = [
    'id' => 'module4',
    'label' => 'Module 4 — Inspections',
    'icon' => 'clipboard-check',
    'link' => '?page=module4/submodule1',
    'stats' => [
      ['label' => 'Schedules', 'value' => $inspTotalR['value'] !== null ? (int)$inspTotalR['value'] : null],
      ['label' => 'Scheduled', 'value' => $inspScheduledR['value'] !== null ? (int)$inspScheduledR['value'] : null],
      ['label' => 'Pending verification', 'value' => $inspPendingVerifyR['value'] !== null ? (int)$inspPendingVerifyR['value'] : null],
      ['label' => 'Pending assignment', 'value' => $inspPendingAssignR['value'] !== null ? (int)$inspPendingAssignR['value'] : null],
      ['label' => 'Completed', 'value' => $inspCompletedR['value'] !== null ? (int)$inspCompletedR['value'] : null],
      ['label' => 'Certificates issued', 'value' => $certIssuedR['value'] !== null ? (int)$certIssuedR['value'] : null],
    ],
  ];

  $tTerminals = ms_pick_table($db, ['terminals', 'terminal']);
  $tParkingAreas = ms_pick_table($db, ['parking_areas', 'parking_area']);
  $tSlots = ms_pick_table($db, ['parking_slots', 'slots']);
  $tParkingPayments = ms_pick_table($db, ['parking_payments']);
  $tParkingTx = ms_pick_table($db, ['parking_transactions']);
  $ppPaidAtCol = $tParkingPayments ? ms_pick_column($db, $tParkingPayments, ['paid_at', 'created_at']) : null;
  $ptPaidAtCol = $tParkingTx ? ms_pick_column($db, $tParkingTx, ['paid_at', 'created_at']) : null;
  if ($debug) {
    $diagnostics['picked']['terminals_table'] = $tTerminals;
    $diagnostics['picked']['parking_areas_table'] = $tParkingAreas;
    $diagnostics['picked']['parking_slots_table'] = $tSlots;
    $diagnostics['picked']['parking_payments_table'] = $tParkingPayments;
    $diagnostics['picked']['parking_transactions_table'] = $tParkingTx;
  }
  $terminalsR = $tTerminals ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tTerminals}", 'c') : ['value' => null, 'error' => 'missing_table'];
  $areasR = $tParkingAreas ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tParkingAreas}", 'c') : ['value' => null, 'error' => 'missing_table'];
  $slotsTotalR = $tSlots ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tSlots}", 'c') : ['value' => null, 'error' => 'missing_table'];
  $slotsOccupiedR = $tSlots ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tSlots} WHERE status='Occupied'", 'c') : ['value' => null, 'error' => 'missing_table'];
  $slotRevenueTodayR = ($tParkingPayments && $ppPaidAtCol) ? ms_scalar($db, "SELECT SUM(amount) AS s FROM {$tParkingPayments} WHERE DATE({$ppPaidAtCol})=CURDATE()", 's') : ['value' => null, 'error' => 'missing_table_or_column'];
  $parkingTxRevenueTodayR = $tParkingTx ? ms_scalar($db, "SELECT SUM(amount) AS s FROM {$tParkingTx} WHERE UPPER(COALESCE(status,'Paid'))='PAID' AND DATE(COALESCE({$ptPaidAtCol}, created_at))=CURDATE()", 's') : ['value' => null, 'error' => 'missing_table_or_column'];
  $parkingRevenueToday = ($slotRevenueTodayR['value'] !== null ? (float)$slotRevenueTodayR['value'] : 0.0) + ($parkingTxRevenueTodayR['value'] !== null ? (float)$parkingTxRevenueTodayR['value'] : 0.0);

  $m5Stats = [
    ['label' => 'Terminals', 'value' => $terminalsR['value'] !== null ? (int)$terminalsR['value'] : null],
    ['label' => 'Parking areas', 'value' => $areasR['value'] !== null ? (int)$areasR['value'] : null],
    ['label' => 'Parking collections today', 'value' => ($slotRevenueTodayR['value'] === null && $parkingTxRevenueTodayR['value'] === null) ? null : $parkingRevenueToday, 'format' => 'currency'],
  ];
  if ($tSlots) {
    $m5Stats[] = ['label' => 'Slots', 'value' => $slotsTotalR['value'] !== null ? (int)$slotsTotalR['value'] : null];
    $m5Stats[] = ['label' => 'Occupied', 'value' => $slotsOccupiedR['value'] !== null ? (int)$slotsOccupiedR['value'] : null];
  }

  $modules[] = [
    'id' => 'module5',
    'label' => 'Module 5 — Terminals & Parking',
    'icon' => 'parking-circle',
    'link' => '?page=module5/submodule1',
    'stats' => $m5Stats,
  ];

  $tCommuters = ms_pick_table($db, ['commuters']);
  $tPortalOps = ms_pick_table($db, ['operator_portal_users']);
  $tComplaints = ms_pick_table($db, ['complaints']);
  $commutersR = $tCommuters ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tCommuters}", 'c') : ['value' => null, 'error' => 'missing_table'];
  $portalOperatorsR = $tPortalOps ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tPortalOps}", 'c') : ['value' => null, 'error' => 'missing_table'];
  $complaintsR = $tComplaints ? ms_scalar($db, "SELECT COUNT(*) AS c FROM {$tComplaints}", 'c') : ['value' => null, 'error' => 'missing_table'];

  $mUserStats = [];
  if ($tCommuters) $mUserStats[] = ['label' => 'Commuters', 'value' => $commutersR['value'] !== null ? (int)$commutersR['value'] : null];
  if ($tPortalOps) $mUserStats[] = ['label' => 'Operator portal users', 'value' => $portalOperatorsR['value'] !== null ? (int)$portalOperatorsR['value'] : null];
  if ($tComplaints) $mUserStats[] = ['label' => 'Complaints', 'value' => $complaintsR['value'] !== null ? (int)$complaintsR['value'] : null];

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
  'diagnostics' => $debug ? $diagnostics : null,
]);
