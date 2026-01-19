<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$db = db();
require_role(['SuperAdmin']);
header('Content-Type: text/plain; charset=utf-8');

function tmm_has_col(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return (bool)($res && $res->num_rows > 0);
}

function tmm_find_id(mysqli $db, string $table, string $idCol, string $nameCol, string $nameVal): int {
  $stmt = $db->prepare("SELECT `$idCol` AS id FROM `$table` WHERE `$nameCol`=? LIMIT 1");
  if (!$stmt) return 0;
  $stmt->bind_param('s', $nameVal);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return (int)($row['id'] ?? 0);
}

function tmm_upsert_coops(mysqli $db): array {
  $hasConsolidation = tmm_has_col($db, 'coops', 'consolidation_status');
  $hasStatus = tmm_has_col($db, 'coops', 'status');
  $hasAccDate = tmm_has_col($db, 'coops', 'accreditation_date');

  $coops = [
    [
      'name' => 'Caloocan Transport Cooperative',
      'addr' => 'Caloocan City Hall Complex, Caloocan City',
      'chair' => 'Juan Dela Cruz',
      'approval' => 'CAL-COOP-2026-001',
      'consolidation' => 'Consolidated',
      'status' => 'Active',
      'acc_date' => date('Y-m-d', strtotime('-420 days')),
    ],
    [
      'name' => 'North Metro PUV Cooperative',
      'addr' => 'EDSA Extension, Caloocan City',
      'chair' => 'Maria Santos',
      'approval' => 'NMP-COOP-2026-002',
      'consolidation' => 'In Progress',
      'status' => 'Active',
      'acc_date' => date('Y-m-d', strtotime('-260 days')),
    ],
    [
      'name' => 'Bagong Silang Transport Cooperative',
      'addr' => 'Bagong Silang, Caloocan City',
      'chair' => 'Pedro Reyes',
      'approval' => 'BSTC-COOP-2026-004',
      'consolidation' => 'Not Consolidated',
      'status' => 'Active',
      'acc_date' => date('Y-m-d', strtotime('-190 days')),
    ],
  ];

  $sqlCols = ['coop_name', 'address', 'chairperson_name', 'lgu_approval_number'];
  $sqlVals = ['?', '?', '?', '?'];
  $sqlUpd = [
    'address=VALUES(address)',
    'chairperson_name=VALUES(chairperson_name)',
    'lgu_approval_number=VALUES(lgu_approval_number)',
  ];
  $types = 'ssss';

  if ($hasConsolidation) {
    $sqlCols[] = 'consolidation_status';
    $sqlVals[] = '?';
    $sqlUpd[] = 'consolidation_status=VALUES(consolidation_status)';
    $types .= 's';
  }
  if ($hasStatus) {
    $sqlCols[] = 'status';
    $sqlVals[] = '?';
    $sqlUpd[] = 'status=VALUES(status)';
    $types .= 's';
  }
  if ($hasAccDate) {
    $sqlCols[] = 'accreditation_date';
    $sqlVals[] = '?';
    $sqlUpd[] = 'accreditation_date=VALUES(accreditation_date)';
    $types .= 's';
  }

  $stmt = $db->prepare(
    "INSERT INTO coops (" . implode(',', $sqlCols) . ") VALUES (" . implode(',', $sqlVals) . ")
     ON DUPLICATE KEY UPDATE " . implode(',', $sqlUpd)
  );
  if (!$stmt) return [];

  foreach ($coops as $c) {
    $params = [$c['name'], $c['addr'], $c['chair'], $c['approval']];
    if ($hasConsolidation) $params[] = $c['consolidation'];
    if ($hasStatus) $params[] = $c['status'];
    if ($hasAccDate) $params[] = $c['acc_date'];

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
  }
  $stmt->close();

  $ids = [];
  foreach ($coops as $c) {
    $ids[$c['name']] = tmm_find_id($db, 'coops', 'id', 'coop_name', $c['name']);
  }
  return $ids;
}

function tmm_upsert_operators(mysqli $db): array {
  $hasCoopName = tmm_has_col($db, 'operators', 'coop_name');
  $hasContactInfo = tmm_has_col($db, 'operators', 'contact_info');
  $hasContactNumber = tmm_has_col($db, 'operators', 'contact_number');

  $ops = [
    ['name' => 'Mark Anthony Rivera', 'contact' => '0917-123-4567', 'coop' => 'Caloocan Transport Cooperative'],
    ['name' => 'Liza Marie Soriano', 'contact' => '0918-234-5678', 'coop' => 'North Metro PUV Cooperative'],
    ['name' => 'Rogelio Bautista', 'contact' => '0919-345-6789', 'coop' => 'Bagong Silang Transport Cooperative'],
    ['name' => 'Catherine D. Flores', 'contact' => '0920-456-7890', 'coop' => 'Caloocan Transport Cooperative'],
  ];

  $cols = ['full_name'];
  $vals = ['?'];
  $upd = ['full_name=VALUES(full_name)'];
  $types = 's';

  if ($hasContactInfo) { $cols[] = 'contact_info'; $vals[] = '?'; $upd[] = 'contact_info=VALUES(contact_info)'; $types .= 's'; }
  elseif ($hasContactNumber) { $cols[] = 'contact_number'; $vals[] = '?'; $upd[] = 'contact_number=VALUES(contact_number)'; $types .= 's'; }
  if ($hasCoopName) { $cols[] = 'coop_name'; $vals[] = '?'; $upd[] = 'coop_name=VALUES(coop_name)'; $types .= 's'; }

  $stmt = $db->prepare(
    "INSERT INTO operators (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")
     ON DUPLICATE KEY UPDATE " . implode(',', $upd)
  );
  if (!$stmt) return [];

  foreach ($ops as $o) {
    $params = [$o['name']];
    if ($hasContactInfo || $hasContactNumber) $params[] = $o['contact'];
    if ($hasCoopName) $params[] = $o['coop'];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
  }
  $stmt->close();

  $ids = [];
  foreach ($ops as $o) {
    $ids[$o['name']] = tmm_find_id($db, 'operators', 'id', 'full_name', $o['name']);
  }
  return $ids;
}

function tmm_upsert_franchise_applications(mysqli $db, array $coopIds, array $opIds): array {
  $hasOperatorName = tmm_has_col($db, 'franchise_applications', 'operator_name');
  $hasAppType = tmm_has_col($db, 'franchise_applications', 'application_type');
  $hasNotes = tmm_has_col($db, 'franchise_applications', 'notes');
  $hasRouteIds = tmm_has_col($db, 'franchise_applications', 'route_ids');
  $hasSubDate = tmm_has_col($db, 'franchise_applications', 'submission_date');

  $apps = [
    [
      'ref' => '2026-00012',
      'status' => 'Pending',
      'type' => 'New',
      'count' => 6,
      'notes' => 'New franchise application for modernization compliance; awaiting document verification.',
      'operator' => 'Mark Anthony Rivera',
      'coop' => 'Caloocan Transport Cooperative',
      'route_ids' => 'R-12',
    ],
    [
      'ref' => '2026-00018',
      'status' => 'Under Review',
      'type' => 'Renewal',
      'count' => 8,
      'notes' => 'Renewal under review; route capacity and cooperative consolidation status being validated.',
      'operator' => 'Liza Marie Soriano',
      'coop' => 'North Metro PUV Cooperative',
      'route_ids' => 'R-08',
    ],
    [
      'ref' => '2026-00021',
      'status' => 'Endorsed',
      'type' => 'Renewal',
      'count' => 10,
      'notes' => 'Endorsed; ready for inspection scheduling and issuance of permit number.',
      'operator' => 'Catherine D. Flores',
      'coop' => 'Caloocan Transport Cooperative',
      'route_ids' => 'R-05',
    ],
    [
      'ref' => '2026-00025',
      'status' => 'Rejected',
      'type' => 'New',
      'count' => 4,
      'notes' => 'Rejected due to incomplete cooperative documents and missing latest LTFRB record.',
      'operator' => 'Rogelio Bautista',
      'coop' => 'Bagong Silang Transport Cooperative',
      'route_ids' => 'R-12',
    ],
  ];

  $cols = ['franchise_ref_number', 'operator_id', 'coop_id', 'vehicle_count', 'status'];
  $vals = ['?', '?', '?', '?', '?'];
  $types = 'siiis';
  $upd = [
    'operator_id=VALUES(operator_id)',
    'coop_id=VALUES(coop_id)',
    'vehicle_count=VALUES(vehicle_count)',
    'status=VALUES(status)',
    'submitted_at=NOW()'
  ];

  if ($hasOperatorName) { $cols[] = 'operator_name'; $vals[] = '?'; $types .= 's'; $upd[] = 'operator_name=VALUES(operator_name)'; }
  if ($hasAppType) { $cols[] = 'application_type'; $vals[] = '?'; $types .= 's'; $upd[] = 'application_type=VALUES(application_type)'; }
  if ($hasSubDate) { $cols[] = 'submission_date'; $vals[] = '?'; $types .= 's'; $upd[] = 'submission_date=VALUES(submission_date)'; }
  if ($hasNotes) { $cols[] = 'notes'; $vals[] = '?'; $types .= 's'; $upd[] = 'notes=VALUES(notes)'; }
  if ($hasRouteIds) { $cols[] = 'route_ids'; $vals[] = '?'; $types .= 's'; $upd[] = 'route_ids=VALUES(route_ids)'; }

  $stmt = $db->prepare(
    "INSERT INTO franchise_applications (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")
     ON DUPLICATE KEY UPDATE " . implode(',', $upd)
  );
  if (!$stmt) return [];

  $result = [];
  foreach ($apps as $a) {
    $opId = (int)($opIds[$a['operator']] ?? 0);
    $coopId = (int)($coopIds[$a['coop']] ?? 0);
    if ($opId <= 0 || $coopId <= 0) continue;

    $params = [$a['ref'], $opId, $coopId, (int)$a['count'], $a['status']];
    if ($hasOperatorName) $params[] = $a['operator'];
    if ($hasAppType) $params[] = $a['type'];
    if ($hasSubDate) $params[] = date('Y-m-d');
    if ($hasNotes) $params[] = $a['notes'];
    if ($hasRouteIds) $params[] = $a['route_ids'];

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result[$a['ref']] = ['status' => $a['status'], 'operator' => $a['operator'], 'coop' => $a['coop']];
  }
  $stmt->close();

  return $result;
}

function tmm_upsert_demo_vehicles(mysqli $db, array $appInfo): void {
  $hasInspectionStatus = tmm_has_col($db, 'vehicles', 'inspection_status');
  $hasCoopName = tmm_has_col($db, 'vehicles', 'coop_name');
  $hasRouteId = tmm_has_col($db, 'vehicles', 'route_id');

  $vehicles = [
    ['plate' => 'CAL-1026', 'type' => 'Jeepney', 'ref' => '2026-00021', 'inspection' => 'Pending', 'route' => 'R-05'],
    ['plate' => 'CAL-2048', 'type' => 'Jeepney', 'ref' => '2026-00021', 'inspection' => 'Pending', 'route' => 'R-05'],
    ['plate' => 'CAL-3310', 'type' => 'Jeepney', 'ref' => '2026-00018', 'inspection' => 'Pending', 'route' => 'R-08'],
    ['plate' => 'CAL-7781', 'type' => 'Jeepney', 'ref' => '2026-00012', 'inspection' => 'Pending', 'route' => 'R-12'],
  ];

  $cols = ['plate_number', 'vehicle_type', 'operator_name', 'franchise_id', 'status'];
  $vals = ['?', '?', '?', '?', '?'];
  $upd = [
    'vehicle_type=VALUES(vehicle_type)',
    'operator_name=VALUES(operator_name)',
    'franchise_id=VALUES(franchise_id)',
    'status=VALUES(status)',
  ];
  $types = 'sssss';

  if ($hasCoopName) { $cols[] = 'coop_name'; $vals[] = '?'; $upd[] = 'coop_name=VALUES(coop_name)'; $types .= 's'; }
  if ($hasRouteId) { $cols[] = 'route_id'; $vals[] = '?'; $upd[] = 'route_id=VALUES(route_id)'; $types .= 's'; }
  if ($hasInspectionStatus) { $cols[] = 'inspection_status'; $vals[] = '?'; $upd[] = 'inspection_status=VALUES(inspection_status)'; $types .= 's'; }

  $stmt = $db->prepare(
    "INSERT INTO vehicles (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")
     ON DUPLICATE KEY UPDATE " . implode(',', $upd)
  );
  if (!$stmt) return;

  foreach ($vehicles as $v) {
    $ref = $v['ref'];
    if (!isset($appInfo[$ref])) continue;
    $operator = (string)($appInfo[$ref]['operator'] ?? '');
    $coop = (string)($appInfo[$ref]['coop'] ?? '');
    $appStatus = (string)($appInfo[$ref]['status'] ?? '');
    $vehStatus = ($appStatus === 'Endorsed') ? 'Active' : 'Suspended';

    $params = [$v['plate'], $v['type'], $operator, $ref, $vehStatus];
    if ($hasCoopName) $params[] = $coop;
    if ($hasRouteId) $params[] = (string)($v['route'] ?? '');
    if ($hasInspectionStatus) $params[] = (string)($v['inspection'] ?? 'Pending');

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
  }
  $stmt->close();
}

echo "Seeding cooperative + franchise demo data...\n";
$coopIds = tmm_upsert_coops($db);
$opIds = tmm_upsert_operators($db);
$appInfo = tmm_upsert_franchise_applications($db, $coopIds, $opIds);
tmm_upsert_demo_vehicles($db, $appInfo);

echo "OK: demo coops/operators/franchise applications/vehicles seeded.\n";
echo "Suggested flow: Module 2 → Franchise Application & Cooperative, then Validation/Endorsement.\n";

