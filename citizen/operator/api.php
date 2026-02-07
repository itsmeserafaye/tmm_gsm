<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}
require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../includes/operator_portal.php';
require_once __DIR__ . '/../../admin/includes/security.php';
require_once __DIR__ . '/../../admin/includes/util.php';
require_once __DIR__ . '/../../admin/includes/franchise_gate.php';
require_once __DIR__ . '/../../includes/env.php';

header('Content-Type: application/json');

function op_get_setting(mysqli $db, string $key, string $default = ''): string {
  $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
  if (!$stmt) return $default;
  $stmt->bind_param('s', $key);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $val = $row ? (string)($row['setting_value'] ?? '') : '';
  return $val !== '' ? $val : $default;
}

function op_enforce_session_timeout(mysqli $db): void {
  if (empty($_SESSION['operator_user_id'])) return;
  $min = (int)trim(op_get_setting($db, 'session_timeout', '30'));
  if ($min <= 0) $min = 30;
  if ($min > 1440) $min = 1440;
  $ttl = $min * 60;
  $now = time();
  $last = (int)($_SESSION['operator_last_activity'] ?? 0);
  if ($last > 0 && ($now - $last) > $ttl) {
    $_SESSION = [];
    @session_unset();
    @session_destroy();
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'session_expired']);
    exit;
  }
  $_SESSION['operator_last_activity'] = $now;
}

$db = db();
op_enforce_session_timeout($db);

if (empty($_SESSION['operator_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

if (empty($_SESSION['operator_csrf'])) {
  $_SESSION['operator_csrf'] = bin2hex(random_bytes(32));
}

$action = (string) ($_REQUEST['action'] ?? '');
$userId = (int) $_SESSION['operator_user_id'];
$activePlate = strtoupper((string) ($_SESSION['operator_plate'] ?? ''));

function op_send(bool $ok, array $payload = [], int $code = 200): void
{
  http_response_code($code);
  echo json_encode(array_merge(['ok' => $ok], $payload));
  exit;
}

function op_audit_event(mysqli $db, int $portalUserId, string $email, string $action, string $entityType = '', string $entityKey = '', array $meta = []): void
{
  $action = trim($action);
  if ($action === '') return;
  $entityType = trim($entityType);
  $entityKey = trim($entityKey);
  $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
  $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
  $metaJson = '';
  if ($meta) {
    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES);
    if ($metaJson === false) $metaJson = '';
    if (strlen($metaJson) > 20000) $metaJson = substr($metaJson, 0, 20000);
  }

  $stmt = $db->prepare("INSERT INTO audit_events (event_time, actor_user_id, actor_email, actor_role, action, entity_type, entity_key, ip_address, user_agent, meta_json)
                        VALUES (NOW(), ?, ?, 'Operator Portal', ?, ?, ?, ?, ?, ?)");
  if (!$stmt) return;
  $stmt->bind_param('issssssss', $portalUserId, $email, $action, $entityType, $entityKey, $ip, $ua, $metaJson);
  $stmt->execute();
  $stmt->close();
}

function op_require_csrf(): void
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    return;
  $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
  $sess = (string) ($_SESSION['operator_csrf'] ?? '');
  if ($token === '' || $sess === '' || !hash_equals($sess, $token)) {
    op_send(false, ['error' => 'Invalid request. Please refresh and try again.'], 403);
  }
}

function op_user_plates(mysqli $db, int $userId): array
{
  $plates = [];
  $stmt = $db->prepare("SELECT plate_number FROM operator_portal_user_plates WHERE user_id=?");
  if (!$stmt)
    return $plates;
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $plates[] = (string) $row['plate_number'];
    }
  }
  $stmt->close();

  if ($plates) {
    sort($plates);
    return $plates;
  }

  $u = op_get_user_row($db, $userId);
  if (!$u)
    return $plates;

  $email = strtolower(trim((string) ($u['email'] ?? '')));
  $fullName = trim((string) ($u['full_name'] ?? ''));
  $assocName = trim((string) ($u['association_name'] ?? ''));

  $opId = 0;
  if ($email !== '') {
    $stmtO = $db->prepare("SELECT id FROM operators WHERE email=? LIMIT 1");
    if ($stmtO) {
      $stmtO->bind_param('s', $email);
      $stmtO->execute();
      $rowO = $stmtO->get_result()->fetch_assoc();
      $stmtO->close();
      $opId = (int) ($rowO['id'] ?? 0);
    }
  }

  $derived = [];
  $derivedBy = '';

  if ($opId > 0) {
    $stmtV = $db->prepare("SELECT plate_number FROM vehicles WHERE record_status='Linked' AND (current_operator_id=? OR operator_id=?) AND plate_number IS NOT NULL AND plate_number<>'' ORDER BY plate_number ASC");
    if ($stmtV) {
      $stmtV->bind_param('ii', $opId, $opId);
      $stmtV->execute();
      $resV = $stmtV->get_result();
      while ($resV && ($rowV = $resV->fetch_assoc())) {
        $p = strtoupper(trim((string) ($rowV['plate_number'] ?? '')));
        if ($p !== '')
          $derived[] = $p;
      }
      $stmtV->close();
      $derivedBy = 'operator_id';
    }
  }

  if (!$derived) {
    $candidates = [];
    if ($fullName !== '')
      $candidates[] = $fullName;
    if ($assocName !== '' && $assocName !== $fullName)
      $candidates[] = $assocName;

    foreach ($candidates as $cand) {
      $stmtV2 = $db->prepare("SELECT plate_number FROM vehicles WHERE record_status='Linked' AND operator_name=? AND plate_number IS NOT NULL AND plate_number<>'' ORDER BY plate_number ASC");
      if (!$stmtV2)
        continue;
      $stmtV2->bind_param('s', $cand);
      $stmtV2->execute();
      $resV2 = $stmtV2->get_result();
      while ($resV2 && ($rowV2 = $resV2->fetch_assoc())) {
        $p = strtoupper(trim((string) ($rowV2['plate_number'] ?? '')));
        if ($p !== '')
          $derived[] = $p;
      }
      $stmtV2->close();
      if ($derived) {
        $derivedBy = 'operator_name';
        break;
      }
    }
  }

  if ($derived) {
    $unique = array_values(array_unique($derived));
    sort($unique);

    foreach ($unique as $p) {
      if ($derivedBy === 'operator_id') {
        $stmtUp = $db->prepare("INSERT INTO operator_portal_user_plates (user_id, plate_number) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)");
        if ($stmtUp) {
          $stmtUp->bind_param('is', $userId, $p);
          $stmtUp->execute();
          $stmtUp->close();
        }
      } else {
        $stmtIns = $db->prepare("INSERT IGNORE INTO operator_portal_user_plates (user_id, plate_number) VALUES (?, ?)");
        if ($stmtIns) {
          $stmtIns->bind_param('is', $userId, $p);
          $stmtIns->execute();
          $stmtIns->close();
        }
      }
    }
    return $unique;
  }

  return $plates;
}

function op_get_user_row(mysqli $db, int $userId): ?array
{
  static $hasPuvOperatorIdCol = null;
  if ($hasPuvOperatorIdCol === null) {
    $hasPuvOperatorIdCol = false;
    $col = $db->query("SHOW COLUMNS FROM operator_portal_users LIKE 'puv_operator_id'");
    if ($col && ($col->num_rows ?? 0) > 0) $hasPuvOperatorIdCol = true;
  }

  $selectPuv = $hasPuvOperatorIdCol ? ", COALESCE(puv_operator_id,0) AS puv_operator_id" : "";
  $stmt = $db->prepare("SELECT id, email, full_name, contact_info, association_name, operator_type, approval_status, verification_submitted_at, approval_remarks, status{$selectPuv} FROM operator_portal_users WHERE id=? LIMIT 1");
  if (!$stmt)
    return null;
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return is_array($row) ? $row : null;
}

function op_has_col(mysqli $db, string $table, string $col): bool
{
  static $cache = [];
  $table = trim($table);
  $col = trim($col);
  if ($table === '' || $col === '') return false;
  $k = strtolower($table . '.' . $col);
  if (array_key_exists($k, $cache)) return (bool)$cache[$k];
  $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($col) . "'");
  $ok = $res && (($res->num_rows ?? 0) > 0);
  $cache[$k] = $ok;
  return $ok;
}

function op_table_exists(mysqli $db, string $table): bool
{
  static $cache = [];
  $table = trim($table);
  if ($table === '') return false;
  $k = strtolower($table);
  if (array_key_exists($k, $cache)) return (bool)$cache[$k];
  $res = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
  $ok = $res && (($res->num_rows ?? 0) > 0);
  $cache[$k] = $ok;
  return $ok;
}

function op_get_puv_operator_id(mysqli $db, int $userId): int
{
  $u = op_get_user_row($db, $userId);
  if (!$u) return 0;
  $opId = (int)($u['puv_operator_id'] ?? 0);
  if ($opId > 0) return $opId;
  $email = strtolower(trim((string)($u['email'] ?? '')));
  if ($email === '') return 0;
  $stmt = $db->prepare("SELECT id FROM operators WHERE email=? ORDER BY id DESC LIMIT 1");
  if (!$stmt) return 0;
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return (int)($row['id'] ?? 0);
}

function op_vehicle_readiness_snapshot(mysqli $db, int $operatorId): array
{
  static $cache = [];
  if ($operatorId <= 0) {
    return [
      'total_linked' => 0,
      'orcr_have' => 0,
      'ready_have' => 0,
      'missing_orcr_plates' => [],
      'missing_ready_inspection' => [],
      'missing_ready_docs' => [],
    ];
  }
  if (isset($cache[$operatorId])) return $cache[$operatorId];

  $veh = [
    'total_linked' => 0,
    'orcr_have' => 0,
    'ready_have' => 0,
    'missing_orcr_plates' => [],
    'missing_ready_inspection' => [],
    'missing_ready_docs' => [],
  ];
  if (!op_table_exists($db, 'vehicles')) {
    $cache[$operatorId] = $veh;
    return $veh;
  }

  $vdExists = op_table_exists($db, 'vehicle_documents');
  $vdTypeCol = $vdExists
    ? (op_has_col($db, 'vehicle_documents', 'doc_type') ? 'doc_type' : (op_has_col($db, 'vehicle_documents', 'document_type') ? 'document_type' : (op_has_col($db, 'vehicle_documents', 'type') ? 'type' : 'doc_type')))
    : 'doc_type';
  $vdVerifiedCol = $vdExists
    ? (op_has_col($db, 'vehicle_documents', 'is_verified') ? 'is_verified' : (op_has_col($db, 'vehicle_documents', 'verified') ? 'verified' : (op_has_col($db, 'vehicle_documents', 'isApproved') ? 'isApproved' : 'is_verified')))
    : 'is_verified';
  $vdHasVehicleId = $vdExists ? op_has_col($db, 'vehicle_documents', 'vehicle_id') : false;
  $vdHasPlate = $vdExists ? op_has_col($db, 'vehicle_documents', 'plate_number') : false;

  $docsHasExpiry = op_has_col($db, 'documents', 'expiry_date');
  $legacyOrValidCond = $docsHasExpiry ? "(d.expiry_date IS NULL OR d.expiry_date >= CURDATE())" : "1=1";
  $orcrCond = $vdExists ? "LOWER(vd.`{$vdTypeCol}`) IN ('orcr','or/cr')" : "0=1";
  $orCond = $vdExists ? "LOWER(vd.`{$vdTypeCol}`)='or'" : "0=1";
  $crCond = $vdExists ? "LOWER(vd.`{$vdTypeCol}`)='cr'" : "0=1";
  $insCond = $vdExists ? "LOWER(vd.`{$vdTypeCol}`) IN ('insurance','ins')" : "0=1";
  $verCond = $vdExists ? "COALESCE(vd.`{$vdVerifiedCol}`,0)=1" : "0=1";

  $join = $vdExists
    ? ($vdHasVehicleId && $vdHasPlate
      ? "(vd.vehicle_id=v.id OR ((vd.vehicle_id IS NULL OR vd.vehicle_id=0) AND vd.plate_number=v.plate_number))"
      : ($vdHasVehicleId ? "vd.vehicle_id=v.id" : ($vdHasPlate ? "vd.plate_number=v.plate_number" : "0=1")))
    : "0=1";

  $stmtVeh = $db->prepare("SELECT v.id, v.plate_number,
                                 MAX(CASE WHEN {$orcrCond} AND {$verCond} THEN 1 ELSE 0 END) AS orcr_ok,
                                 MAX(CASE WHEN {$orCond} AND {$verCond} THEN 1 ELSE 0 END) AS or_ok,
                                 MAX(CASE WHEN {$crCond} AND {$verCond} THEN 1 ELSE 0 END) AS cr_ok,
                                 MAX(CASE WHEN LOWER(d.type)='or' AND COALESCE(d.verified,0)=1 AND {$legacyOrValidCond} THEN 1 ELSE 0 END) AS legacy_or_ok,
                                 MAX(CASE WHEN LOWER(d.type)='cr' AND COALESCE(d.verified,0)=1 THEN 1 ELSE 0 END) AS legacy_cr_ok,
                                 MAX(CASE WHEN LOWER(d.type) IN ('orcr','or/cr') AND COALESCE(d.verified,0)=1 THEN 1 ELSE 0 END) AS legacy_orcr_ok
                          FROM vehicles v
                          " . ($vdExists ? "LEFT JOIN vehicle_documents vd ON {$join}" : "LEFT JOIN (SELECT NULL AS vehicle_id, '' AS plate_number, '' AS {$vdTypeCol}, 0 AS {$vdVerifiedCol}) vd ON 1=0") . "
                          LEFT JOIN documents d ON d.plate_number=v.plate_number
                          WHERE v.operator_id=?
                            AND (COALESCE(v.record_status,'') <> 'Archived')
                          GROUP BY v.id, v.plate_number
                          ORDER BY v.created_at DESC");
  if ($stmtVeh) {
    $stmtVeh->bind_param('i', $operatorId);
    $stmtVeh->execute();
    $resVeh = $stmtVeh->get_result();
    $missing = [];
    $okCount = 0;
    $total = 0;
    while ($resVeh && ($r = $resVeh->fetch_assoc())) {
      $total++;
      $plate = (string)($r['plate_number'] ?? '');
      $orcrOk = ((int)($r['orcr_ok'] ?? 0)) === 1 || ((int)($r['legacy_orcr_ok'] ?? 0)) === 1;
      $orOk = ((int)($r['or_ok'] ?? 0)) === 1 || ((int)($r['legacy_or_ok'] ?? 0)) === 1;
      $crOk = ((int)($r['cr_ok'] ?? 0)) === 1 || ((int)($r['legacy_cr_ok'] ?? 0)) === 1;
      $pass = $orcrOk || ($orOk && $crOk);
      if ($pass) $okCount++;
      else if ($plate !== '') $missing[] = $plate;
    }
    $stmtVeh->close();
    $veh['total_linked'] = $total;
    $veh['orcr_have'] = $okCount;
    $veh['missing_orcr_plates'] = array_slice($missing, 0, 8);
  }

  $hasRegs = op_table_exists($db, 'vehicle_registrations');
  $stmtReady = $db->prepare("SELECT v.plate_number, COALESCE(v.record_status,'') AS record_status, COALESCE(v.inspection_status,'') AS inspection_status,
                                    COALESCE(vr.registration_status,'') AS registration_status,
                                    COALESCE(NULLIF(vr.orcr_no,''),'') AS orcr_no,
                                    vr.orcr_date,
                                    MAX(CASE WHEN {$insCond} AND {$verCond} THEN 1 ELSE 0 END) AS ins_ok,
                                    MAX(CASE WHEN LOWER(d.type)='insurance' AND COALESCE(d.verified,0)=1 AND {$legacyOrValidCond} THEN 1 ELSE 0 END) AS legacy_ins_ok
                             FROM vehicles v
                             " . ($vdExists ? "LEFT JOIN vehicle_documents vd ON {$join}" : "LEFT JOIN (SELECT NULL AS vehicle_id, '' AS plate_number, '' AS {$vdTypeCol}, 0 AS {$vdVerifiedCol}) vd ON 1=0") . "
                             LEFT JOIN documents d ON d.plate_number=v.plate_number
                             " . ($hasRegs ? "LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id" : "LEFT JOIN (SELECT NULL AS vehicle_id, '' AS registration_status, '' AS orcr_no, NULL AS orcr_date) vr ON 1=0") . "
                             WHERE v.operator_id=?
                               AND COALESCE(v.record_status,'') <> 'Archived'
                             GROUP BY v.plate_number, v.record_status, v.inspection_status, vr.registration_status, vr.orcr_no, vr.orcr_date
                             ORDER BY v.created_at DESC");
  if ($stmtReady) {
    $stmtReady->bind_param('i', $operatorId);
    $stmtReady->execute();
    $resReady = $stmtReady->get_result();
    $readyCount = 0;
    $missInsp = [];
    $missDocs = [];
    while ($resReady && ($r = $resReady->fetch_assoc())) {
      $plate = (string)($r['plate_number'] ?? '');
      if ($plate === '') continue;
      $isLinked = (string)($r['record_status'] ?? '') === 'Linked';
      $inspOk = (string)($r['inspection_status'] ?? '') === 'Passed';
      $insOk = ((int)($r['ins_ok'] ?? 0)) === 1 || ((int)($r['legacy_ins_ok'] ?? 0)) === 1;
      $regOk = true;
      if ($hasRegs) {
        $rs = (string)($r['registration_status'] ?? '');
        $orcrNo = (string)($r['orcr_no'] ?? '');
        $orcrDate = $r['orcr_date'] ?? null;
        $regOk = in_array($rs, ['Registered', 'Recorded'], true) && trim($orcrNo) !== '' && !empty($orcrDate);
      }
      $ok = $isLinked && $inspOk && $regOk && $insOk;
      if ($ok) $readyCount++;
      else {
        if (!$inspOk) $missInsp[] = $plate;
        if (!$regOk || !$insOk) $missDocs[] = $plate;
      }
    }
    $stmtReady->close();
    $veh['ready_have'] = $readyCount;
    $veh['missing_ready_inspection'] = array_slice(array_values(array_unique($missInsp)), 0, 8);
    $veh['missing_ready_docs'] = array_slice(array_values(array_unique($missDocs)), 0, 8);
  }

  $cache[$operatorId] = $veh;
  return $veh;
}

function op_franchise_build_requirements(mysqli $db, int $operatorId, array $appRow): array
{
  $needUnits = (int)($appRow['approved_vehicle_count'] ?? 0);
  if ($needUnits <= 0) $needUnits = (int)($appRow['vehicle_count'] ?? 0);
  if ($needUnits <= 0) $needUnits = 1;

  $routeId = (int)($appRow['route_id'] ?? 0);
  $endorseGate = tmm_can_endorse_application($db, $operatorId, $routeId, $needUnits, (int)($appRow['application_id'] ?? 0));

  $endorseItems = [];
  if (!($endorseGate['ok'] ?? false)) {
    $err = (string)($endorseGate['error'] ?? '');
    if ($err === 'operator_invalid') $endorseItems[] = 'Operator record must be Verified and Active.';
    if ($err === 'operator_docs_missing') $endorseItems[] = 'Upload required operator documents.';
    if ($err === 'operator_docs_not_verified') {
      $missing = (array)($endorseGate['missing'] ?? []);
      if ($missing) $endorseItems[] = 'Missing/Unverified: ' . implode(', ', array_map('strval', $missing));
      else $endorseItems[] = 'Required operator documents are not verified.';
    }
    if ($err === 'route_inactive') $endorseItems[] = 'Selected route is inactive.';
    if ($err === 'route_over_capacity') {
      $cap = (int)($endorseGate['cap'] ?? 0);
      $used = (int)($endorseGate['used'] ?? 0);
      $want = (int)($endorseGate['want'] ?? 0);
      $endorseItems[] = "Route capacity exceeded (cap {$cap}, used {$used}, requested {$want}).";
    }
  }

  $vehBase = op_vehicle_readiness_snapshot($db, $operatorId);
  $veh = array_merge(['need' => $needUnits], $vehBase);

  $ltfrbItems = [];
  if ($veh['total_linked'] <= 0) $ltfrbItems[] = 'Link at least one vehicle to your operator account.';
  if ($veh['orcr_have'] < $veh['need']) $ltfrbItems[] = "Verified OR/CR required ({$veh['orcr_have']} of {$veh['need']}).";
  if ($veh['ready_have'] < $veh['need']) $ltfrbItems[] = "Vehicles must be inspection-passed and insured ({$veh['ready_have']} of {$veh['need']}).";

  return [
    'need_units' => $veh['need'],
    'endorsement_blockers' => $endorseItems,
    'ltfrb_requirements' => $ltfrbItems,
    'vehicle_metrics' => $veh,
  ];
}

function op_is_approved(?array $userRow): bool
{
  if (!$userRow)
    return false;
  return ((string) ($userRow['approval_status'] ?? '')) === 'Approved' && ((string) ($userRow['status'] ?? '')) === 'Active';
}

function op_require_approved(mysqli $db, int $userId): void
{
  $u = op_get_user_row($db, $userId);
  if (!op_is_approved($u)) {
    op_send(false, ['error' => 'Operator account is pending approval. Please submit verification documents or wait for admin approval.'], 403);
  }
}

if ($action === 'get_session') {
  $plates = op_user_plates($db, $userId);
  $u = op_get_user_row($db, $userId);
  op_send(true, [
    'data' => [
      'active_plate' => $activePlate,
      'plates' => $plates,
      'csrf_token' => (string) ($_SESSION['operator_csrf'] ?? ''),
      'approval_status' => $u ? (string) ($u['approval_status'] ?? '') : '',
      'operator_type' => $u ? (string) ($u['operator_type'] ?? '') : '',
    ]
  ]);
}

if ($action === 'set_active_plate') {
  op_require_csrf();
  op_require_approved($db, $userId);
  $plate = strtoupper(trim((string) ($_POST['plate_number'] ?? '')));
  if ($plate === '')
    op_send(false, ['error' => 'Missing plate number'], 400);
  $plates = op_user_plates($db, $userId);
  if (!in_array($plate, $plates, true))
    op_send(false, ['error' => 'Plate is not assigned to this account.'], 403);
  $_SESSION['operator_plate'] = $plate;
  op_send(true, ['data' => ['active_plate' => $plate]]);
}

if ($action === 'get_dashboard_stats') {
  $plates = op_user_plates($db, $userId);
  if (!$plates)
    op_send(true, ['data' => ['pending_apps' => 0, 'active_vehicles' => 0, 'compliance_alerts' => 0]]);

  $in = implode(',', array_fill(0, count($plates), '?'));
  $types = str_repeat('s', count($plates));

  $pending = 0;
  $stmt = $db->prepare("SELECT COUNT(*) AS c FROM operator_portal_applications WHERE user_id=? AND status='Pending'");
  if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $pending = (int) (($res && ($r = $res->fetch_assoc())) ? ($r['c'] ?? 0) : 0);
    $stmt->close();
  }

  $activeVehicles = 0;
  $sql = "SELECT COUNT(*) AS c FROM vehicles WHERE plate_number IN ($in) AND status='Active'";
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$plates);
    $stmt->execute();
    $res = $stmt->get_result();
    $activeVehicles = (int) (($res && ($r = $res->fetch_assoc())) ? ($r['c'] ?? 0) : 0);
    $stmt->close();
  }

  $alerts = 0;
  $sql = "SELECT COUNT(*) AS c FROM vehicles WHERE plate_number IN ($in) AND (inspection_status IS NULL OR inspection_status <> 'Passed')";
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$plates);
    $stmt->execute();
    $res = $stmt->get_result();
    $alerts = (int) (($res && ($r = $res->fetch_assoc())) ? ($r['c'] ?? 0) : 0);
    $stmt->close();
  }

  op_send(true, ['data' => ['pending_apps' => $pending, 'active_vehicles' => $activeVehicles, 'compliance_alerts' => $alerts]]);
}

if ($action === 'get_ai_insights') {
  $plates = op_user_plates($db, $userId);
  $insights = [];

  if ($plates) {
    $in = implode(',', array_fill(0, count($plates), '?'));
    $types = str_repeat('s', count($plates));

    $bad = 0;
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM vehicles WHERE plate_number IN ($in) AND (inspection_status IS NULL OR inspection_status <> 'Passed')");
    if ($stmt) {
      $stmt->bind_param($types, ...$plates);
      $stmt->execute();
      $res = $stmt->get_result();
      $bad = (int) (($res && ($r = $res->fetch_assoc())) ? ($r['c'] ?? 0) : 0);
      $stmt->close();
    }
    if ($bad > 0) {
      $insights[] = [
        'title' => 'Compliance Alert',
        'desc' => $bad . ' vehicle(s) have not passed inspection. Consider scheduling inspection to avoid restrictions.',
        'type' => 'high',
      ];
    } else {
      $insights[] = [
        'title' => 'Compliance Status',
        'desc' => 'All vehicles show Passed inspection status in the system.',
        'type' => 'low',
      ];
    }

    $terminals = [];
    $stmtT = $db->prepare("SELECT DISTINCT terminal_name FROM terminal_assignments WHERE plate_number IN ($in) AND terminal_name IS NOT NULL AND terminal_name <> ''");
    if ($stmtT) {
      $stmtT->bind_param($types, ...$plates);
      $stmtT->execute();
      $resT = $stmtT->get_result();
      while ($resT && ($row = $resT->fetch_assoc()))
        $terminals[] = (string) ($row['terminal_name'] ?? '');
      $stmtT->close();
    }

    if ($terminals) {
      $insightsPayload = null;
      try {
        $savedGet = $_GET;
        $_GET = ['area_type' => 'terminal', 'hours' => '24'];
        ob_start();
        include __DIR__ . '/../../admin/api/analytics/demand_insights.php';
        $raw = ob_get_clean();
        $_GET = $savedGet;
        $insightsPayload = json_decode((string) $raw, true);
      } catch (Throwable $e) {
        $insightsPayload = null;
      }

      if (is_array($insightsPayload) && ($insightsPayload['ok'] ?? false) && !empty($insightsPayload['hotspots']) && is_array($insightsPayload['hotspots'])) {
        foreach ($insightsPayload['hotspots'] as $h) {
          if (!is_array($h))
            continue;
          $label = (string) ($h['area_label'] ?? '');
          if ($label === '' || !in_array($label, $terminals, true))
            continue;
          $sev = (string) ($h['severity'] ?? 'medium');
          $extra = $h['recommended_extra_units'] ?? null;
          $drivers = $h['drivers'] ?? [];
          $driversText = (is_array($drivers) && $drivers) ? (' Drivers: ' . implode(' • ', array_slice($drivers, 0, 2)) . '.') : '';
          $extraText = (is_numeric($extra) && (int) $extra > 0) ? (' Suggested: +' . (int) $extra . ' units.') : '';
          $insights[] = [
            'title' => 'Demand Hotspot: ' . $label,
            'desc' => 'Predicted spike at ' . (string) ($h['peak_hour'] ?? '') . '. ' . $extraText . $driversText,
            'type' => $sev === 'critical' || $sev === 'high' ? 'high' : 'medium',
            'route_plan' => $h['route_plan'] ?? [],
          ];
        }
      }
    }
  }

  if (!$insights) {
    $insights[] = [
      'title' => 'AI Insights',
      'desc' => 'No personalized insights are available yet. Add more operational logs to improve recommendations.',
      'type' => 'low',
    ];
  }

  op_send(true, ['data' => array_slice($insights, 0, 6)]);
}

if ($action === 'get_fleet_status') {
  $plates = op_user_plates($db, $userId);
  if (!$plates)
    op_send(true, ['data' => []]);
  $in = implode(',', array_fill(0, count($plates), '?'));
  $types = str_repeat('s', count($plates));

  $hasRegs = op_table_exists($db, 'vehicle_registrations');
  $hasFranchiseId = op_has_col($db, 'vehicles', 'franchise_id');
  $hasCurrentOperatorId = op_has_col($db, 'vehicles', 'current_operator_id');

  $vdExists = op_table_exists($db, 'vehicle_documents');
  $vdTypeCol = $vdExists
    ? (op_has_col($db, 'vehicle_documents', 'doc_type') ? 'doc_type' : (op_has_col($db, 'vehicle_documents', 'document_type') ? 'document_type' : (op_has_col($db, 'vehicle_documents', 'type') ? 'type' : 'doc_type')))
    : 'doc_type';
  $vdVerifiedCol = $vdExists
    ? (op_has_col($db, 'vehicle_documents', 'is_verified') ? 'is_verified' : (op_has_col($db, 'vehicle_documents', 'verified') ? 'verified' : (op_has_col($db, 'vehicle_documents', 'isApproved') ? 'isApproved' : 'is_verified')))
    : 'is_verified';
  $vdHasVehicleId = $vdExists ? op_has_col($db, 'vehicle_documents', 'vehicle_id') : false;
  $vdHasPlate = $vdExists ? op_has_col($db, 'vehicle_documents', 'plate_number') : false;

  $orcrCond = $vdExists ? "LOWER(vd.`{$vdTypeCol}`) IN ('orcr','or/cr')" : "0=1";
  $orCond = $vdExists ? "LOWER(vd.`{$vdTypeCol}`)='or'" : "0=1";
  $crCond = $vdExists ? "LOWER(vd.`{$vdTypeCol}`)='cr'" : "0=1";
  $verCond = $vdExists ? "COALESCE(vd.`{$vdVerifiedCol}`,0)=1" : "0=1";

  $joinVd = $vdExists
    ? ($vdHasVehicleId && $vdHasPlate
      ? "(vd.vehicle_id=v.id OR ((vd.vehicle_id IS NULL OR vd.vehicle_id=0) AND vd.plate_number=v.plate_number))"
      : ($vdHasVehicleId ? "vd.vehicle_id=v.id" : ($vdHasPlate ? "vd.plate_number=v.plate_number" : "0=1")))
    : "0=1";

  $hasOrcrSql = "0 AS has_orcr";
  if ($vdExists) {
    $hasOrcrSql = "(SELECT COUNT(*) FROM vehicle_documents vd WHERE {$joinVd} AND ({$orcrCond} OR (({$orCond} OR {$crCond}) AND {$verCond}))) AS has_orcr";
  }

  $opJoinKey = $hasCurrentOperatorId
    ? "COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0), 0)"
    : "COALESCE(NULLIF(v.operator_id,0), 0)";

  $sql = "SELECT v.id AS vehicle_id,
                 v.plate_number,
                 v.vehicle_type,
                 v.status,
                 v.record_status,
                 v.operator_id,
                 " . ($hasCurrentOperatorId ? "v.current_operator_id," : "NULL AS current_operator_id,") . "
                 v.inspection_status,
                 v.inspection_passed_at,
                 v.created_at,
                 " . ($hasRegs ? "vr.registration_status, vr.orcr_no, vr.orcr_date," : "NULL AS registration_status, NULL AS orcr_no, NULL AS orcr_date,") . "
                 " . ($hasFranchiseId ? "fa.status AS franchise_app_status," : "NULL AS franchise_app_status,") . "
                 COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), '') AS operator_display,
                 {$hasOrcrSql}
          FROM vehicles v
          LEFT JOIN operators o ON o.id={$opJoinKey}
          " . ($hasRegs ? "LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id" : "") . "
          " . ($hasFranchiseId ? "LEFT JOIN franchise_applications fa ON fa.franchise_ref_number=v.franchise_id" : "") . "
          WHERE v.plate_number IN ($in)
          ORDER BY v.plate_number ASC";
  $stmt = $db->prepare($sql);
  if (!$stmt)
    op_send(false, ['error' => 'Query failed'], 500);
  $stmt->bind_param($types, ...$plates);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $rs = (string)($row['record_status'] ?? '');
      if ($rs === '') {
        $opId = (int)($row['operator_id'] ?? 0);
        $rs = $opId > 0 ? 'Linked' : 'Encoded';
      }
      $insp = (string)($row['inspection_status'] ?? '');
      $frAppSt = (string)($row['franchise_app_status'] ?? '');
      $regSt = (string)($row['registration_status'] ?? '');
      $orcrNo = trim((string)($row['orcr_no'] ?? ''));
      $orcrDate = trim((string)($row['orcr_date'] ?? ''));
      $frOk = in_array($frAppSt, ['Approved', 'LTFRB-Approved'], true);
      $inspOk = $insp === 'Passed';
      $regOk = in_array($regSt, ['Registered', 'Recorded'], true) && $orcrNo !== '' && $orcrDate !== '';
      $st = 'Declared/linked';
      if ($rs === 'Archived') {
        $st = 'Archived';
      } elseif ($frOk && $inspOk && $regOk) {
        $st = 'Active';
      } elseif ($inspOk && $regOk) {
        $st = 'Registered';
      } elseif ($inspOk) {
        $st = 'Inspected';
      } elseif ($rs === 'Linked') {
        $st = 'Pending Inspection';
      }

      $rows[] = [
        'vehicle_id' => isset($row['vehicle_id']) ? (int)$row['vehicle_id'] : null,
        'plate_number' => (string)($row['plate_number'] ?? ''),
        'vehicle_type' => (string)($row['vehicle_type'] ?? ''),
        'computed_status' => $st,
        'status' => (string)($row['status'] ?? ''),
        'record_status' => $rs,
        'operator_id' => isset($row['operator_id']) ? (int)$row['operator_id'] : null,
        'current_operator_id' => isset($row['current_operator_id']) ? (int)$row['current_operator_id'] : null,
        'operator_display' => (string)($row['operator_display'] ?? ''),
        'has_orcr' => ((int)($row['has_orcr'] ?? 0)) > 0,
        'created_at' => (string)($row['created_at'] ?? ''),
        'inspection_status' => $insp !== '' ? $insp : null,
        'inspection_last_date' => $row['inspection_passed_at'] ? substr((string) $row['inspection_passed_at'], 0, 10) : null,
        'registration_status' => (string)($row['registration_status'] ?? ''),
        'orcr_no' => (string)($row['orcr_no'] ?? ''),
        'orcr_date' => (string)($row['orcr_date'] ?? ''),
        'franchise_app_status' => (string)($row['franchise_app_status'] ?? ''),
      ];
    }
  }
  $stmt->close();
  op_send(true, ['data' => $rows]);
}

if ($action === 'puv_get_vehicle_details') {
  op_require_approved($db, $userId);
  $plate = strtoupper(trim((string)($_GET['plate'] ?? '')));
  $plate = preg_replace('/[^A-Z0-9\-]/', '', $plate);
  if ($plate === '') op_send(false, ['error' => 'invalid_plate'], 400);

  $plates = op_user_plates($db, $userId);
  if (!$plates || !in_array($plate, $plates, true)) op_send(false, ['error' => 'not_allowed'], 403);

  $hasRegs = op_table_exists($db, 'vehicle_registrations');
  $hasFranchiseId = op_has_col($db, 'vehicles', 'franchise_id');
  $hasCurrentOperatorId = op_has_col($db, 'vehicles', 'current_operator_id');
  $opJoinKey = $hasCurrentOperatorId
    ? "COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0), 0)"
    : "COALESCE(NULLIF(v.operator_id,0), 0)";

  $sql = "SELECT v.*,
                 COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), '') AS operator_display,
                 " . ($hasRegs ? "vr.registration_status, vr.orcr_no, vr.orcr_date," : "NULL AS registration_status, NULL AS orcr_no, NULL AS orcr_date,") . "
                 " . ($hasFranchiseId ? "fa.status AS franchise_app_status" : "NULL AS franchise_app_status") . "
          FROM vehicles v
          LEFT JOIN operators o ON o.id={$opJoinKey}
          " . ($hasRegs ? "LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id" : "") . "
          " . ($hasFranchiseId ? "LEFT JOIN franchise_applications fa ON fa.franchise_ref_number=v.franchise_id" : "") . "
          WHERE v.plate_number=?
          LIMIT 1";
  $stmt = $db->prepare($sql);
  if (!$stmt) op_send(false, ['error' => 'db_prepare_failed'], 500);
  $stmt->bind_param('s', $plate);
  $stmt->execute();
  $veh = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$veh) op_send(false, ['error' => 'not_found'], 404);

  $vehicleId = (int)($veh['id'] ?? ($veh['vehicle_id'] ?? 0));
  if ($vehicleId <= 0 && isset($veh['vehicle_id'])) $vehicleId = (int)$veh['vehicle_id'];

  $docs = [];
  if (op_table_exists($db, 'vehicle_documents') && $vehicleId > 0) {
    $vdTypeCol = op_has_col($db, 'vehicle_documents', 'doc_type') ? 'doc_type'
      : (op_has_col($db, 'vehicle_documents', 'document_type') ? 'document_type'
      : (op_has_col($db, 'vehicle_documents', 'type') ? 'type' : 'doc_type'));
    $vdVerifiedCol = op_has_col($db, 'vehicle_documents', 'is_verified') ? 'is_verified'
      : (op_has_col($db, 'vehicle_documents', 'verified') ? 'verified'
      : (op_has_col($db, 'vehicle_documents', 'isApproved') ? 'isApproved' : 'is_verified'));
    $vdIdCol = op_has_col($db, 'vehicle_documents', 'doc_id') ? 'doc_id' : (op_has_col($db, 'vehicle_documents', 'id') ? 'id' : 'doc_id');
    $vdUpCol = op_has_col($db, 'vehicle_documents', 'uploaded_at') ? 'uploaded_at' : 'uploaded_at';

    $stmtD = $db->prepare("SELECT {$vdIdCol} AS id, {$vdTypeCol} AS doc_type, file_path, {$vdUpCol} AS uploaded_at, COALESCE({$vdVerifiedCol},0) AS is_verified
                           FROM vehicle_documents
                           WHERE vehicle_id=?
                           ORDER BY {$vdUpCol} DESC, {$vdIdCol} DESC
                           LIMIT 50");
    if ($stmtD) {
      $stmtD->bind_param('i', $vehicleId);
      $stmtD->execute();
      $resD = $stmtD->get_result();
      while ($resD && ($r = $resD->fetch_assoc())) {
        $docs[] = [
          'source' => 'vehicle_documents',
          'id' => (int)($r['id'] ?? 0),
          'doc_type' => (string)($r['doc_type'] ?? ''),
          'file_path' => (string)($r['file_path'] ?? ''),
          'uploaded_at' => (string)($r['uploaded_at'] ?? ''),
          'is_verified' => ((int)($r['is_verified'] ?? 0)) === 1,
          'expiry_date' => null,
        ];
      }
      $stmtD->close();
    }
  }

  if (op_table_exists($db, 'documents')) {
    $hasExpiry = op_has_col($db, 'documents', 'expiry_date');
    $selExpiry = $hasExpiry ? ", expiry_date" : ", NULL AS expiry_date";
    $stmtL = $db->prepare("SELECT id, type, file_path, uploaded_at, COALESCE(verified,0) AS verified{$selExpiry}
                           FROM documents
                           WHERE plate_number=?
                           ORDER BY uploaded_at DESC, id DESC
                           LIMIT 50");
    if ($stmtL) {
      $stmtL->bind_param('s', $plate);
      $stmtL->execute();
      $resL = $stmtL->get_result();
      while ($resL && ($r = $resL->fetch_assoc())) {
        $docs[] = [
          'source' => 'documents',
          'id' => (int)($r['id'] ?? 0),
          'doc_type' => (string)($r['type'] ?? ''),
          'file_path' => (string)($r['file_path'] ?? ''),
          'uploaded_at' => (string)($r['uploaded_at'] ?? ''),
          'is_verified' => ((int)($r['verified'] ?? 0)) === 1,
          'expiry_date' => (string)($r['expiry_date'] ?? ''),
        ];
      }
      $stmtL->close();
    }
  }

  op_send(true, [
    'data' => [
      'vehicle' => $veh,
      'documents' => $docs,
    ]
  ]);
}

if ($action === 'get_applications') {
  $rows = [];
  $stmt = $db->prepare("SELECT id, plate_number, type, status, notes, created_at FROM operator_portal_applications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
  if (!$stmt)
    op_send(false, ['error' => 'Query failed'], 500);
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($res && ($r = $res->fetch_assoc())) {
    $rows[] = [
      'id' => (int) ($r['id'] ?? 0),
      'plate_number' => (string) ($r['plate_number'] ?? ''),
      'type' => (string) ($r['type'] ?? ''),
      'status' => (string) ($r['status'] ?? ''),
      'notes' => (string) ($r['notes'] ?? ''),
      'created_at' => (string) ($r['created_at'] ?? ''),
    ];
  }
  $stmt->close();
  op_send(true, ['data' => $rows]);
}

if ($action === 'get_routes') {
  $rows = [];
  $res = $db->query("SELECT id, route_code, route_name FROM lptrp_routes ORDER BY route_code ASC");
  if ($res) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
  }
  op_send(true, ['data' => $rows]);
}

if ($action === 'get_profile') {
  $stmt = $db->prepare("SELECT email, full_name, contact_info, association_name, operator_type, approval_status, verification_submitted_at, approval_remarks FROM operator_portal_users WHERE id=? LIMIT 1");
  if (!$stmt)
    op_send(false, ['error' => 'Query failed'], 500);
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  $puvOpId = (int)($row['puv_operator_id'] ?? 0);
  $hasOpRecord = $puvOpId > 0;
  $submissionStatus = 'None';
  if ($hasOpRecord) {
      $submissionStatus = 'Approved';
  } else {
      $stmtS = $db->prepare("SELECT status FROM operator_record_submissions WHERE portal_user_id=? ORDER BY submission_id DESC LIMIT 1");
      if ($stmtS) {
          $stmtS->bind_param('i', $userId);
          $stmtS->execute();
          $resS = $stmtS->get_result();
          if ($rS = $resS->fetch_assoc()) {
              $submissionStatus = (string)($rS['status'] ?? 'Submitted');
          }
          $stmtS->close();
      }
  }

  op_send(true, [
    'data' => [
      'name' => $row['full_name'] ?? 'Operator',
      'email' => $row['email'] ?? ($_SESSION['operator_email'] ?? ''),
      'contact_info' => $row['contact_info'] ?? '',
      'association_name' => $row['association_name'] ?? '',
      'operator_type' => $row['operator_type'] ?? 'Individual',
      'approval_status' => $row['approval_status'] ?? 'Pending',
      'verification_submitted_at' => $row['verification_submitted_at'] ?? null,
      'approval_remarks' => $row['approval_remarks'] ?? null,
      'plate_number' => $activePlate,
      'has_operator_record' => $hasOpRecord,
      'operator_submission_status' => $submissionStatus,
    ]
  ]);
}

if ($action === 'get_verification') {
  $u = op_get_user_row($db, $userId);
  if (!$u)
    op_send(false, ['error' => 'Profile not found'], 404);
  $docs = [];
  $stmt = $db->prepare("SELECT doc_key, file_path, status, remarks, uploaded_at, reviewed_at FROM operator_portal_documents WHERE user_id=? ORDER BY doc_key ASC");
  if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) {
      $docs[] = [
        'doc_key' => (string) ($r['doc_key'] ?? ''),
        'file_path' => (string) ($r['file_path'] ?? ''),
        'status' => (string) ($r['status'] ?? ''),
        'remarks' => $r['remarks'] ?? null,
        'uploaded_at' => (string) ($r['uploaded_at'] ?? ''),
        'reviewed_at' => $r['reviewed_at'] ?? null,
      ];
    }
    $stmt->close();
  }
  op_send(true, [
    'data' => [
      'operator_type' => (string) ($u['operator_type'] ?? 'Individual'),
      'approval_status' => (string) ($u['approval_status'] ?? 'Pending'),
      'verification_submitted_at' => $u['verification_submitted_at'] ?? null,
      'approval_remarks' => $u['approval_remarks'] ?? null,
      'documents' => $docs,
    ]
  ]);
}

if ($action === 'upload_verification_docs') {
  op_require_csrf();
  $u = op_get_user_row($db, $userId);
  if (!$u)
    op_send(false, ['error' => 'Profile not found'], 404);
  if (((string) ($u['status'] ?? '')) !== 'Active')
    op_send(false, ['error' => 'Account is not active'], 403);

  $operatorType = (string) ($u['operator_type'] ?? 'Individual');
  $allowedDocKeysByType = [
    'Individual' => ['valid_id'],
    'Coop' => ['cda_registration', 'board_resolution'],
    'Corp' => ['sec_registration', 'authority_to_operate'],
  ];
  $allowedKeys = $allowedDocKeysByType[$operatorType] ?? ['valid_id'];

  if (empty($_FILES)) {
    op_send(false, ['error' => 'No files uploaded'], 400);
  }

  $targetDir = __DIR__ . '/uploads/';
  if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0777, true);
  }

  $saved = [];
  foreach ($_FILES as $docKey => $file) {
    $docKey = (string) $docKey;
    if (!in_array($docKey, $allowedKeys, true))
      continue;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
      continue;
    if ((int) ($file['size'] ?? 0) > (5 * 1024 * 1024))
      continue;
    $original = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
    if ($ext === '' || !in_array($ext, $allowedExt, true))
      continue;
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp))
      continue;
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $finfo ? finfo_file($finfo, $tmp) : null;
    if ($finfo)
      finfo_close($finfo);
    $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
    if ($mime && !in_array($mime, $allowedMime, true))
      continue;

    $filename = 'verif_' . $docKey . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!@move_uploaded_file((string) ($file['tmp_name'] ?? ''), $targetDir . $filename))
      continue;

    $relPath = 'uploads/' . $filename;
    $stmt = $db->prepare("INSERT INTO operator_portal_documents(user_id, doc_key, file_path, status, remarks, reviewed_at, reviewed_by) VALUES(?, ?, ?, 'Pending', NULL, NULL, NULL)
      ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), status='Pending', remarks=NULL, reviewed_at=NULL, reviewed_by=NULL");
    if ($stmt) {
      $stmt->bind_param('iss', $userId, $docKey, $relPath);
      $stmt->execute();
      $stmt->close();
      $saved[] = $docKey;
    }
  }

  if (!$saved) {
    op_send(false, ['error' => 'No valid files were uploaded'], 400);
  }

  $now = date('Y-m-d H:i:s');
  $stmtU = $db->prepare("UPDATE operator_portal_users SET verification_submitted_at=?, approval_status=IF(approval_status='Approved','Approved','Pending') WHERE id=?");
  if ($stmtU) {
    $stmtU->bind_param('si', $now, $userId);
    $stmtU->execute();
    $stmtU->close();
  }
  $title = 'Verification submitted';
  $message = 'Your verification documents were submitted and are pending review.';
  $type = 'info';
  $stmtN = $db->prepare("INSERT INTO operator_portal_notifications(user_id, title, message, type) VALUES(?, ?, ?, ?)");
  if ($stmtN) {
    $stmtN->bind_param('isss', $userId, $title, $message, $type);
    $stmtN->execute();
    $stmtN->close();
  }
  op_send(true, ['message' => 'Documents uploaded', 'data' => ['saved' => $saved]]);
}

if ($action === 'update_profile') {
  op_require_csrf();
  $name = trim((string) ($_POST['name'] ?? ''));
  $email = strtolower(trim((string) ($_POST['email'] ?? '')));
  $contact = trim((string) ($_POST['contact_info'] ?? ''));
  $currentPass = (string) ($_POST['current_password'] ?? '');
  $newPass = (string) ($_POST['new_password'] ?? '');

  $stmt = $db->prepare("SELECT email, password_hash FROM operator_portal_users WHERE id=? LIMIT 1");
  if (!$stmt)
    op_send(false, ['error' => 'Update failed'], 500);
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!$user)
    op_send(false, ['error' => 'Update failed'], 500);
  if ($currentPass === '' || !password_verify($currentPass, (string) ($user['password_hash'] ?? ''))) {
    op_send(false, ['error' => 'Current password is incorrect.'], 400);
  }

  $setPwd = false;
  $pwdHash = '';
  if ($newPass !== '') {
    if (strlen($newPass) < 10)
      op_send(false, ['error' => 'New password must be at least 10 characters.'], 400);
    $pwdHash = password_hash($newPass, PASSWORD_DEFAULT);
    if ($pwdHash === false)
      op_send(false, ['error' => 'Failed to set password.'], 500);
    $setPwd = true;
  }

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
    op_send(false, ['error' => 'Invalid email.'], 400);

  if ($setPwd) {
    $stmt = $db->prepare("UPDATE operator_portal_users SET full_name=?, email=?, contact_info=?, password_hash=? WHERE id=?");
    if (!$stmt)
      op_send(false, ['error' => 'Update failed'], 500);
    $stmt->bind_param('ssssi', $name, $email, $contact, $pwdHash, $userId);
  } else {
    $stmt = $db->prepare("UPDATE operator_portal_users SET full_name=?, email=?, contact_info=? WHERE id=?");
    if (!$stmt)
      op_send(false, ['error' => 'Update failed'], 500);
    $stmt->bind_param('sssi', $name, $email, $contact, $userId);
  }

  try {
    $ok = $stmt->execute();
  } catch (mysqli_sql_exception $e) {
    $stmt->close();
    op_send(false, ['error' => 'Email is already in use.'], 409);
  }
  $stmt->close();
  $_SESSION['operator_email'] = $email;
  op_send(true, ['message' => 'Profile updated']);
}

if ($action === 'submit_application') {
  op_require_csrf();
  op_require_approved($db, $userId);
  $type = trim((string) ($_POST['type'] ?? ''));
  $notes = trim((string) ($_POST['notes'] ?? ''));
  $routeId = (int) ($_POST['route_id'] ?? 0);
  $scheduleDate = trim((string) ($_POST['schedule_date'] ?? ''));

  if ($type === '')
    op_send(false, ['error' => 'Application type required'], 400);

  $plate = strtoupper(trim((string)($_POST['plate_number'] ?? '')));
  $plate = preg_replace('/[^A-Z0-9\-]/', '', $plate);
  if ($plate === '') $plate = $activePlate;
  if ($plate === '')
    op_send(false, ['error' => 'Vehicle is required'], 400);
  $plates = op_user_plates($db, $userId);
  if (!in_array($plate, $plates, true))
    op_send(false, ['error' => 'Vehicle is not assigned to this account'], 403);

  // Append extra info to notes
  if ($type === 'Franchise Endorsement' && $routeId > 0) {
      $stmtR = $db->prepare("SELECT route_code, route_name FROM lptrp_routes WHERE id=? LIMIT 1");
      if ($stmtR) {
          $stmtR->bind_param('i', $routeId);
          $stmtR->execute();
          $resR = $stmtR->get_result();
          if ($rowR = $resR->fetch_assoc()) {
              $notes = "Selected Route: {$rowR['route_code']} - {$rowR['route_name']}\n" . $notes;
          }
          $stmtR->close();
      }
  }

  if ($type === 'Vehicle Inspection' && $scheduleDate !== '') {
      $notes = "Requested Inspection Date: " . $scheduleDate . "\n" . $notes;
  }

  $filePaths = [];
  if (!empty($_FILES)) {
    $targetDir = __DIR__ . '/uploads/';
    if (!is_dir($targetDir)) {
      @mkdir($targetDir, 0777, true);
    }
    foreach ($_FILES as $file) {
      if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
        continue;
      if ((int) ($file['size'] ?? 0) > (5 * 1024 * 1024))
        continue;
      $original = (string) ($file['name'] ?? '');
      $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
      $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
      if ($ext !== '' && !in_array($ext, $allowedExt, true))
        continue;
      $tmp = (string) ($file['tmp_name'] ?? '');
      if ($tmp === '' || !is_uploaded_file($tmp))
        continue;
      $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
      $mime = $finfo ? finfo_file($finfo, $tmp) : null;
      if ($finfo)
        finfo_close($finfo);
      $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
      if ($mime && !in_array($mime, $allowedMime, true))
        continue;
      $ext = pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION);
      $filename = 'app_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext !== '' ? ('.' . $ext) : '');
      if (@move_uploaded_file((string) ($file['tmp_name'] ?? ''), $targetDir . $filename)) {
        $filePaths[] = 'uploads/' . $filename;
      }
    }
  }

  $docsJson = json_encode($filePaths);
  $stmt = $db->prepare("INSERT INTO operator_portal_applications(user_id, plate_number, type, status, notes, documents) VALUES(?, ?, ?, 'Pending', ?, ?)");
  if (!$stmt)
    op_send(false, ['error' => 'Submission failed'], 500);
  $stmt->bind_param('issss', $userId, $plate, $type, $notes, $docsJson);
  $stmt->execute();
  $stmt->close();
  op_send(true, ['ref' => 'OP-' . strtoupper(bin2hex(random_bytes(4)))]);
}

if ($action === 'puv_submit_operator_record') {
  op_require_csrf();
  op_require_approved($db, $userId);

  $u = op_get_user_row($db, $userId);
  if (!$u) op_send(false, ['error' => 'Unable to load operator account.'], 400);

  if (((int)($u['puv_operator_id'] ?? 0)) > 0) {
      op_send(false, ['error' => 'You already have an approved operator profile. Use "Edit Profile" to update details.'], 403);
  }

  $stmtChk = $db->prepare("SELECT 1 FROM operator_record_submissions WHERE portal_user_id=? AND status IN ('Submitted','Approved') LIMIT 1");
  if ($stmtChk) {
      $stmtChk->bind_param('i', $userId);
      $stmtChk->execute();
      if ($stmtChk->get_result()->fetch_row()) {
          $stmtChk->close();
          op_send(false, ['error' => 'You already have a pending or approved operator submission.'], 403);
      }
      $stmtChk->close();
  }

  $operatorType = trim((string)($_POST['operator_type'] ?? ($u['operator_type'] ?? 'Individual')));
  if (!in_array($operatorType, ['Individual','Cooperative','Corporation'], true)) $operatorType = 'Individual';

  $registeredName = trim((string)($_POST['registered_name'] ?? ''));
  $name = trim((string)($_POST['name'] ?? ''));
  $address = trim((string)($_POST['address'] ?? ''));
  $contactNoRaw = (string)($_POST['contact_no'] ?? ($_POST['contact_info'] ?? ($u['contact_info'] ?? '')));
  $contactNo = substr(preg_replace('/\D+/', '', trim($contactNoRaw)), 0, 20);
  $email = strtolower(trim((string)($u['email'] ?? '')));
  $coopName = trim((string)($_POST['coop_name'] ?? ($_POST['association_name'] ?? ($u['association_name'] ?? ''))));

  if ($name === '' || strlen($name) < 3) op_send(false, ['error' => 'Operator name is required.'], 400);
  if ($contactNo !== '' && !preg_match('/^[0-9]{7,20}$/', $contactNo)) op_send(false, ['error' => 'Invalid contact number.'], 400);

  $submittedBy = trim((string)($u['full_name'] ?? ''));
  if ($submittedBy === '') $submittedBy = $coopName;
  if ($submittedBy === '') $submittedBy = 'Operator';

  $stmt = $db->prepare("INSERT INTO operator_record_submissions
    (portal_user_id, operator_type, registered_name, name, address, contact_no, email, coop_name, status, submitted_at, submitted_by_name)
    VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', NOW(), ?)");
  if (!$stmt) op_send(false, ['error' => 'Submission failed.'], 500);
  $stmt->bind_param('issssssss', $userId, $operatorType, $registeredName, $name, $address, $contactNo, $email, $coopName, $submittedBy);
  $ok = $stmt->execute();
  $submissionId = (int)$stmt->insert_id;
  $stmt->close();
  if (!$ok) op_send(false, ['error' => 'Submission failed.'], 500);

  op_audit_event($db, $userId, $email, 'PUV_OPERATOR_SUBMITTED', 'OperatorSubmission', (string)$submissionId, [
    'operator_type' => $operatorType,
    'registered_name' => $registeredName,
  ]);

  op_send(true, [
    'message' => 'Operator record submitted for admin verification.',
    'data' => ['submission_id' => $submissionId]
  ]);
}

if ($action === 'puv_get_my_operator_submissions') {
  $rows = [];
  $stmt = $db->prepare("SELECT submission_id, operator_type, registered_name, name, status, submitted_at, approved_at, approved_by_name, approval_remarks, operator_id
                        FROM operator_record_submissions
                        WHERE portal_user_id=?
                        ORDER BY submitted_at DESC, submission_id DESC
                        LIMIT 25");
  if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    $stmt->close();
  }
  op_send(true, ['data' => $rows]);
}

if ($action === 'puv_get_my_vehicle_submissions') {
  $rows = [];
  $stmt = $db->prepare("SELECT submission_id, plate_number, vehicle_type, status, submitted_at, approved_at, approved_by_name, approval_remarks, vehicle_id
                        FROM vehicle_record_submissions
                        WHERE portal_user_id=?
                        ORDER BY submitted_at DESC, submission_id DESC
                        LIMIT 50");
  if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    $stmt->close();
  }
  op_send(true, ['data' => $rows]);
}

if ($action === 'puv_generate_declared_fleet') {
  op_require_csrf();
  op_require_approved($db, $userId);

  $u = op_get_user_row($db, $userId);
  if (!$u) op_send(false, ['error' => 'Unable to load operator account.'], 400);
  $email = strtolower(trim((string)($u['email'] ?? '')));
  $submittedBy = trim((string)($u['full_name'] ?? ''));
  if ($submittedBy === '') $submittedBy = trim((string)($u['association_name'] ?? ''));
  if ($submittedBy === '') $submittedBy = 'Operator';

  $operatorId = (int)($u['puv_operator_id'] ?? 0);
  if ($operatorId <= 0) $operatorId = op_get_puv_operator_id($db, $userId);
  if ($operatorId <= 0) op_send(false, ['error' => 'Operator record is not approved yet.'], 400);

  $commit = (string)($_POST['commit'] ?? '');
  $token = trim((string)($_POST['token'] ?? ''));
  $format = strtolower(trim((string)($_POST['format'] ?? 'pdf')));
  if (!in_array($format, ['pdf','excel'], true)) $format = 'pdf';

  $opStmt = $db->prepare("SELECT id, operator_type, COALESCE(NULLIF(name,''), full_name) AS display_name, status FROM operators WHERE id=? LIMIT 1");
  if (!$opStmt) op_send(false, ['error' => 'db_prepare_failed'], 500);
  $opStmt->bind_param('i', $operatorId);
  $opStmt->execute();
  $op = $opStmt->get_result()->fetch_assoc();
  $opStmt->close();
  if (!$op) op_send(false, ['error' => 'operator_not_found'], 404);

  $systemName = tmm_get_app_setting('system_name', 'LGU PUV Management System');
  $lguName = tmm_get_app_setting('lgu_name', $systemName);
  $operatorCode = 'OP-' . str_pad((string)$operatorId, 5, '0', STR_PAD_LEFT);

  $vehicles = [];
  $stmtVeh = $db->prepare("SELECT v.id AS vehicle_id, v.plate_number, v.vehicle_type, v.make, v.model, v.year_model, v.engine_no, v.chassis_no,
                                  COALESCE(v.or_number,'') AS or_number, COALESCE(v.cr_number,'') AS cr_number, COALESCE(v.inspection_cert_ref,'') AS inspection_cert_ref,
                                  COALESCE(v.status,'') AS status
                           FROM vehicles v
                           WHERE COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0), 0)=?
                             AND COALESCE(v.record_status,'') <> 'Archived'
                           ORDER BY v.plate_number ASC");
  if ($stmtVeh) {
    $stmtVeh->bind_param('i', $operatorId);
    $stmtVeh->execute();
    $res = $stmtVeh->get_result();
    while ($res && ($r = $res->fetch_assoc())) $vehicles[] = $r;
    $stmtVeh->close();
  }
  if (!$vehicles) op_send(false, ['error' => 'no_linked_vehicles'], 400);

  $plates = array_values(array_filter(array_map(fn($v) => trim((string)($v['plate_number'] ?? '')), $vehicles), fn($x) => $x !== ''));
  $docsByPlate = [];
  if ($plates) {
    $inP = implode(',', array_fill(0, count($plates), '?'));
    $typesP = str_repeat('s', count($plates));
    $sqlPDocs = "SELECT plate_number, LOWER(type) AS doc_type, file_path, uploaded_at
                 FROM documents
                 WHERE plate_number IN ($inP)
                   AND LOWER(type) IN ('or','cr')
                   AND COALESCE(NULLIF(file_path,''),'') <> ''
                 ORDER BY uploaded_at DESC, id DESC";
    $stmtPDocs = $db->prepare($sqlPDocs);
    if ($stmtPDocs) {
      $stmtPDocs->bind_param($typesP, ...$plates);
      $stmtPDocs->execute();
      $resPDocs = $stmtPDocs->get_result();
      while ($resPDocs && ($d = $resPDocs->fetch_assoc())) {
        $p = trim((string)($d['plate_number'] ?? ''));
        $dt = trim((string)($d['doc_type'] ?? ''));
        $fp = trim((string)($d['file_path'] ?? ''));
        if ($p === '' || $dt === '' || $fp === '') continue;
        if (!isset($docsByPlate[$p])) $docsByPlate[$p] = [];
        if (!isset($docsByPlate[$p][$dt])) $docsByPlate[$p][$dt] = $fp;
      }
      $stmtPDocs->close();
    }
  }

  $now = date('Y-m-d H:i:s');
  $opName = (string)($op['display_name'] ?? '');
  $opType = (string)($op['operator_type'] ?? '');
  $opStatus = (string)($op['status'] ?? '');

  $toWin1252 = function ($s) {
    $s = (string)$s;
    if (function_exists('iconv')) {
      $v = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
      if ($v !== false && $v !== null) return $v;
    }
    return $s;
  };
  $pdfEsc = function ($s) use ($toWin1252) {
    $s = $toWin1252($s);
    $s = str_replace("\\", "\\\\", $s);
    $s = str_replace("(", "\\(", $s);
    $s = str_replace(")", "\\)", $s);
    $s = preg_replace("/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/", "", $s);
    return $s;
  };
  $pdfFromLines = function (array $lines) use ($pdfEsc): string {
    $pageWidth = 595;
    $pageHeight = 842;
    $marginLeft = 36;
    $startY = 806;
    $leading = 10;
    $maxLines = 70;
    $pages = [];
    $cur = [];
    foreach ($lines as $ln) {
      $cur[] = (string)$ln;
      if (count($cur) >= $maxLines) { $pages[] = $cur; $cur = []; }
    }
    if ($cur) $pages[] = $cur;
    if (!$pages) $pages[] = ['No records.'];
    $objects = [];
    $addObj = function ($body) use (&$objects) { $objects[] = (string)$body; return count($objects); };
    $catalogId = $addObj('');
    $pagesId = $addObj('');
    $fontId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>");
    $pageObjIds = [];
    foreach ($pages as $pageLines) {
      $content = "BT\n/F1 9 Tf\n" . $leading . " TL\n1 0 0 1 " . $marginLeft . " " . $startY . " Tm\n";
      foreach ($pageLines as $ln) { $content .= "(" . $pdfEsc($ln) . ") Tj\nT*\n"; }
      $content .= "ET\n";
      $contentObjId = $addObj("<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream");
      $pageObjId = $addObj("<< /Type /Page /Parent " . $pagesId . " 0 R /MediaBox [0 0 " . $pageWidth . " " . $pageHeight . "] /Resources << /Font << /F1 " . $fontId . " 0 R >> >> /Contents " . $contentObjId . " 0 R >>");
      $pageObjIds[] = $pageObjId;
    }
    $kids = implode(' ', array_map(function ($id) { return $id . " 0 R"; }, $pageObjIds));
    $objects[$pagesId - 1] = "<< /Type /Pages /Count " . count($pageObjIds) . " /Kids [ " . $kids . " ] >>";
    $objects[$catalogId - 1] = "<< /Type /Catalog /Pages " . $pagesId . " 0 R >>";
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    for ($i = 0; $i < count($objects); $i++) { $offsets[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n" . $objects[$i] . "\nendobj\n"; }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) { $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n"; }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root " . $catalogId . " 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";
    return $pdf;
  };

  $trunc = function (string $s, int $max): string {
    $s = (string)$s;
    if ($max <= 0) return '';
    if (strlen($s) <= $max) return $s;
    if ($max <= 3) return substr($s, 0, $max);
    return substr($s, 0, $max - 3) . '...';
  };
  $fmtDate = function (string $dt): string {
    $ts = strtotime($dt);
    if ($ts === false) return $dt;
    return date('M d, Y H:i', $ts);
  };
  $appendixLabel = function (int $n): string {
    $n = $n + 1;
    $out = '';
    while ($n > 0) { $n--; $out = chr(65 + ($n % 26)) . $out; $n = intdiv($n, 26); }
    return $out;
  };

  $rows = [];
  $breakdown = [];
  foreach ($vehicles as $v) {
    $plate = trim((string)($v['plate_number'] ?? ''));
    $vehType = trim((string)($v['vehicle_type'] ?? ''));
    $bt = $vehType !== '' ? $vehType : 'Unknown';
    $breakdown[$bt] = (int)($breakdown[$bt] ?? 0) + 1;
    $rows[] = [
      'plate_number' => $plate,
      'vehicle_type' => $vehType,
      'make' => trim((string)($v['make'] ?? '')),
      'model' => trim((string)($v['model'] ?? '')),
      'year_model' => trim((string)($v['year_model'] ?? '')),
      'engine_no' => trim((string)($v['engine_no'] ?? '')),
      'chassis_no' => trim((string)($v['chassis_no'] ?? '')),
      'or_number' => trim((string)($v['or_number'] ?? '')),
      'cr_number' => trim((string)($v['cr_number'] ?? '')),
      'attachments' => [
        'or_file' => ($plate !== '' && isset($docsByPlate[$plate]['or'])) ? (string)$docsByPlate[$plate]['or'] : '',
        'cr_file' => ($plate !== '' && isset($docsByPlate[$plate]['cr'])) ? (string)$docsByPlate[$plate]['cr'] : '',
        'inspection_cert_ref' => trim((string)($v['inspection_cert_ref'] ?? '')),
      ],
    ];
  }
  arsort($breakdown);

  $uploadsDir = __DIR__ . '/../../admin/uploads';
  if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);

  $makeToken = function (): string {
    if (function_exists('random_bytes')) return bin2hex(random_bytes(16));
    return bin2hex(openssl_random_pseudo_bytes(16));
  };

  $writePreviewFiles = function () use ($rows, $breakdown, $uploadsDir, $operatorId, $operatorCode, $opName, $opType, $opStatus, $now, $pdfFromLines, $lguName, $systemName, $fmtDate, $trunc, $appendixLabel): array {
    $suffix = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $pdfFile = 'declared_fleet_operator_' . $operatorId . '_' . $suffix . '.pdf';
    $csvFile = 'declared_fleet_operator_' . $operatorId . '_' . $suffix . '.csv';
    $lines = [];
    $lines[] = $lguName;
    $lines[] = 'DECLARED FLEET REPORT';
    $lines[] = 'Operator: ' . $opName;
    $lines[] = 'Operator Type: ' . $opType;
    $lines[] = 'Operator ID: ' . $operatorCode . ' (' . (string)$operatorId . ')';
    $lines[] = 'Date Generated: ' . $fmtDate($now);
    $lines[] = 'Generated by: ' . $systemName;
    $lines[] = '';
    $lines[] = 'FLEET SUMMARY';
    $lines[] = 'Total Vehicles: ' . (string)count($rows);
    $lines[] = 'Breakdown:';
    foreach ($breakdown as $k => $c) { $lines[] = '- ' . (string)$k . ': ' . (string)$c; }
    $lines[] = '';
    $lines[] = 'VEHICLE LIST';
    $lines[] = str_repeat('-', 110);
    $lines[] = sprintf("%-8s %-10s %-8s %-8s %-4s %-10s %-17s %-12s %-12s", 'PLATE', 'TYPE', 'MAKE', 'MODEL', 'YEAR', 'ENGINE', 'CHASSIS', 'OR NO', 'CR NO');
    $lines[] = str_repeat('-', 110);
    foreach ($rows as $r) {
      $lines[] = sprintf(
        "%-8s %-10s %-8s %-8s %-4s %-10s %-17s %-12s %-12s",
        $trunc((string)($r['plate_number'] ?? ''), 8),
        $trunc((string)($r['vehicle_type'] ?? ''), 10),
        $trunc((string)($r['make'] ?? ''), 8),
        $trunc((string)($r['model'] ?? ''), 8),
        $trunc((string)($r['year_model'] ?? ''), 4),
        $trunc((string)($r['engine_no'] ?? ''), 10),
        $trunc((string)($r['chassis_no'] ?? ''), 17),
        $trunc((string)($r['or_number'] ?? ''), 12),
        $trunc((string)($r['cr_number'] ?? ''), 12)
      );
    }
    $lines[] = '';
    $lines[] = 'ATTACHED SUPPORTING DOCUMENTS (AUTO-PULLED)';
    $lines[] = 'Appendix entries reference vehicle documents stored in the system uploads registry.';
    $lines[] = '';
    $idx = 0;
    foreach ($rows as $r) {
      $plate = (string)($r['plate_number'] ?? '');
      $att = is_array($r['attachments'] ?? null) ? $r['attachments'] : [];
      $orFile = $trunc((string)($att['or_file'] ?? ''), 60);
      $crFile = $trunc((string)($att['cr_file'] ?? ''), 60);
      $certRef = $trunc((string)($att['inspection_cert_ref'] ?? ''), 30);
      $lbl = $appendixLabel($idx);
      $lines[] = 'Appendix ' . $lbl . ' – OR/CR: ' . $plate;
      $lines[] = '  OR No: ' . (string)($r['or_number'] ?? '') . ' | OR File: ' . ($orFile !== '' ? $orFile : 'Missing');
      $lines[] = '  CR No: ' . (string)($r['cr_number'] ?? '') . ' | CR File: ' . ($crFile !== '' ? $crFile : 'Missing');
      if ($certRef !== '') $lines[] = '  Inspection Certificate Ref: ' . $certRef;
      $lines[] = '';
      $idx++;
    }
    $pdf = $pdfFromLines($lines);
    if (@file_put_contents($uploadsDir . '/' . $pdfFile, $pdf) === false) throw new Exception('write_failed');
    $fp = @fopen($uploadsDir . '/' . $csvFile, 'w');
    if (!$fp) { if (is_file($uploadsDir . '/' . $pdfFile)) @unlink($uploadsDir . '/' . $pdfFile); throw new Exception('write_failed'); }
    fputcsv($fp, ['Plate No','Vehicle Type','Make','Model','Year','Engine No','Chassis No','OR No','CR No']);
    foreach ($rows as $r) {
      fputcsv($fp, [
        (string)($r['plate_number'] ?? ''),
        (string)($r['vehicle_type'] ?? ''),
        (string)($r['make'] ?? ''),
        (string)($r['model'] ?? ''),
        (string)($r['year_model'] ?? ''),
        (string)($r['engine_no'] ?? ''),
        (string)($r['chassis_no'] ?? ''),
        (string)($r['or_number'] ?? ''),
        (string)($r['cr_number'] ?? ''),
      ]);
    }
    fclose($fp);
    return ['pdf' => $pdfFile, 'excel' => $csvFile];
  };

  if (php_sapi_name() !== 'cli' && function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

  if ($commit !== '1') {
    $files = $writePreviewFiles();
    $token = $makeToken();
    if (!isset($_SESSION['tmm_declared_fleet_previews']) || !is_array($_SESSION['tmm_declared_fleet_previews'])) {
      $_SESSION['tmm_declared_fleet_previews'] = [];
    }
    $_SESSION['tmm_declared_fleet_previews'][$token] = [
      'operator_id' => $operatorId,
      'created_at' => time(),
      'pdf' => $files['pdf'],
      'excel' => $files['excel'],
    ];
    op_send(true, [
      'token' => $token,
      'operator' => ['id' => $operatorId, 'code' => $operatorCode, 'name' => $opName, 'type' => $opType, 'status' => $opStatus],
      'system' => ['name' => $systemName, 'lgu_name' => $lguName],
      'summary' => ['total_vehicles' => count($rows), 'breakdown' => $breakdown],
      'generated_at' => $now,
      'files' => $files,
      'rows' => $rows,
    ]);
  }

  if ($token === '' || !isset($_SESSION['tmm_declared_fleet_previews']) || !is_array($_SESSION['tmm_declared_fleet_previews']) || !isset($_SESSION['tmm_declared_fleet_previews'][$token])) {
    op_send(false, ['error' => 'preview_required'], 400);
  }
  $prev = $_SESSION['tmm_declared_fleet_previews'][$token];
  if ((int)($prev['operator_id'] ?? 0) !== $operatorId) op_send(false, ['error' => 'preview_mismatch'], 400);
  if ((time() - (int)($prev['created_at'] ?? 0)) > 20 * 60) {
    unset($_SESSION['tmm_declared_fleet_previews'][$token]);
    op_send(false, ['error' => 'preview_expired'], 400);
  }
  $chosen = $format === 'excel' ? (string)($prev['excel'] ?? '') : (string)($prev['pdf'] ?? '');
  $chosen = basename($chosen);
  if ($chosen === '' || !is_file($uploadsDir . '/' . $chosen)) op_send(false, ['error' => 'file_missing'], 400);

  $remarks = 'Declared Fleet (Planned / Owned Vehicles) | System Generated';
  $hasDocStatusCol = false;
  $r = $db->query("SHOW COLUMNS FROM operator_documents LIKE 'doc_status'");
  if ($r && ($r->num_rows ?? 0) > 0) $hasDocStatusCol = true;
  $stmtIns = $hasDocStatusCol
    ? $db->prepare("INSERT INTO operator_documents (operator_id, doc_type, file_path, doc_status, remarks, is_verified) VALUES (?, 'Others', ?, 'For Review', ?, 0)")
    : $db->prepare("INSERT INTO operator_documents (operator_id, doc_type, file_path, remarks, is_verified) VALUES (?, 'Others', ?, ?, 0)");
  if (!$stmtIns) op_send(false, ['error' => 'db_prepare_failed'], 500);
  if ($hasDocStatusCol) {
    $stmtIns->bind_param('iss', $operatorId, $chosen, $remarks);
  } else {
    $stmtIns->bind_param('iss', $operatorId, $chosen, $remarks);
  }
  $ok = $stmtIns->execute();
  $docId = (int)$db->insert_id;
  $stmtIns->close();
  unset($_SESSION['tmm_declared_fleet_previews'][$token]);
  if (!$ok) op_send(false, ['error' => 'db_error'], 500);

  op_audit_event($db, $userId, $email, 'PUV_DECLARED_FLEET_UPLOADED', 'OperatorDocument', (string)$docId, ['file' => $chosen, 'operator_id' => $operatorId]);
  op_send(true, ['doc_id' => $docId, 'file_path' => $chosen]);
}

if ($action === 'puv_request_vehicle_link') {
  op_require_csrf();
  op_require_approved($db, $userId);

  $plateRaw = (string)($_POST['plate_number'] ?? '');
  $plateNorm = strtoupper(preg_replace('/\s+/', '', trim($plateRaw)));
  $plateNorm = preg_replace('/[^A-Z0-9-]/', '', $plateNorm);
  $letters = substr(preg_replace('/[^A-Z]/', '', $plateNorm), 0, 3);
  $digits = substr(preg_replace('/[^0-9]/', '', $plateNorm), 0, 4);
  $plate = ($letters !== '' && $digits !== '') ? ($letters . '-' . $digits) : $plateNorm;
  if ($plate === '' || !preg_match('/^[A-Z]{3}\-[0-9]{3,4}$/', $plate)) {
    op_send(false, ['error' => 'Invalid plate number.'], 400);
  }

  $u = op_get_user_row($db, $userId);
  if (!$u) op_send(false, ['error' => 'Unable to load operator account.'], 400);
  $operatorId = (int)($u['puv_operator_id'] ?? 0);
  if ($operatorId <= 0) $operatorId = op_get_puv_operator_id($db, $userId);
  if ($operatorId <= 0) op_send(false, ['error' => 'Operator record is not approved yet.'], 400);
  $submittedBy = trim((string)($u['full_name'] ?? ''));
  if ($submittedBy === '') $submittedBy = trim((string)($u['association_name'] ?? ''));
  if ($submittedBy === '') $submittedBy = 'Operator';
  $email = strtolower(trim((string)($u['email'] ?? '')));

  $stmtVeh = $db->prepare("SELECT id, operator_id, record_status FROM vehicles WHERE plate_number=? LIMIT 1");
  if (!$stmtVeh) op_send(false, ['error' => 'db_prepare_failed'], 500);
  $stmtVeh->bind_param('s', $plate);
  $stmtVeh->execute();
  $veh = $stmtVeh->get_result()->fetch_assoc();
  $stmtVeh->close();
  if (!$veh) op_send(false, ['error' => 'vehicle_not_found'], 404);

  $stmtDup = $db->prepare("SELECT request_id FROM vehicle_link_requests WHERE portal_user_id=? AND plate_number=? AND status='Pending' LIMIT 1");
  if ($stmtDup) {
    $stmtDup->bind_param('is', $userId, $plate);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup) op_send(true, ['message' => 'Link request already pending.']);
  }

  $stmtIns = $db->prepare("INSERT INTO vehicle_link_requests (portal_user_id, plate_number, requested_operator_id, status, submitted_at, submitted_by_name)
                           VALUES (?, ?, ?, 'Pending', NOW(), ?)");
  if (!$stmtIns) op_send(false, ['error' => 'db_prepare_failed'], 500);
  $stmtIns->bind_param('isis', $userId, $plate, $operatorId, $submittedBy);
  $ok = $stmtIns->execute();
  $reqId = (int)$stmtIns->insert_id;
  $stmtIns->close();
  if (!$ok) op_send(false, ['error' => 'db_error'], 500);

  op_audit_event($db, $userId, $email, 'PUV_LINK_REQUEST_SUBMITTED', 'VehicleLinkRequest', (string)$reqId, ['plate_number' => $plate, 'requested_operator_id' => $operatorId]);
  op_send(true, ['message' => 'Link request submitted for admin approval.', 'data' => ['request_id' => $reqId]]);
}

if ($action === 'puv_get_owned_vehicles') {
  op_require_approved($db, $userId);
  $plates = op_user_plates($db, $userId);
  if (!$plates) op_send(true, ['data' => []]);

  $rows = [];
  $in = implode(',', array_fill(0, count($plates), '?'));
  $types = str_repeat('s', count($plates));
  $stmt = $db->prepare("SELECT id AS vehicle_id, UPPER(plate_number) AS plate_number
                        FROM vehicles
                        WHERE plate_number IN ($in)
                          AND COALESCE(NULLIF(plate_number,''),'') <> ''
                          AND COALESCE(record_status,'') <> 'Archived'
                        ORDER BY plate_number ASC");
  if ($stmt) {
    $stmt->bind_param($types, ...$plates);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    $stmt->close();
  }
  op_send(true, ['data' => $rows]);
}

if ($action === 'puv_create_transfer_request') {
  op_require_csrf();
  op_require_approved($db, $userId);

  $u = op_get_user_row($db, $userId);
  if (!$u) op_send(false, ['error' => 'Unable to load operator account.'], 400);
  $operatorId = (int)($u['puv_operator_id'] ?? 0);
  if ($operatorId <= 0) $operatorId = op_get_puv_operator_id($db, $userId);
  if ($operatorId <= 0) op_send(false, ['error' => 'Operator record is not approved yet.'], 400);
  $submittedBy = trim((string)($u['full_name'] ?? ''));
  if ($submittedBy === '') $submittedBy = trim((string)($u['association_name'] ?? ''));
  if ($submittedBy === '') $submittedBy = 'Operator';
  $email = strtolower(trim((string)($u['email'] ?? '')));

  $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
  if ($vehicleId <= 0) op_send(false, ['error' => 'Vehicle is required.'], 400);
  $toOperatorName = trim((string)($_POST['to_operator_name'] ?? ''));
  if ($toOperatorName === '' || strlen($toOperatorName) < 3) op_send(false, ['error' => 'New owner name is required.'], 400);
  $toOperatorName = substr($toOperatorName, 0, 255);

  $transferType = trim((string)($_POST['transfer_type'] ?? 'Reassignment'));
  if (!in_array($transferType, ['Sale','Donation','Inheritance','Reassignment'], true)) $transferType = 'Reassignment';
  $ltoRef = trim((string)($_POST['lto_reference_no'] ?? ''));
  $ltoRef = $ltoRef !== '' ? substr($ltoRef, 0, 128) : '';

  $deed = $_FILES['deed_doc'] ?? null;
  if (!is_array($deed) || (int)($deed['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    op_send(false, ['error' => 'Deed/authorization document is required.'], 400);
  }
  $orcr = $_FILES['orcr_doc'] ?? null;
  $hasOrcr = is_array($orcr) && (int)($orcr['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

  $stmtVeh = $db->prepare("SELECT id, plate_number FROM vehicles
                           WHERE id=? AND COALESCE(NULLIF(current_operator_id,0), NULLIF(operator_id,0), 0)=?
                           LIMIT 1");
  if (!$stmtVeh) op_send(false, ['error' => 'db_prepare_failed'], 500);
  $stmtVeh->bind_param('ii', $vehicleId, $operatorId);
  $stmtVeh->execute();
  $veh = $stmtVeh->get_result()->fetch_assoc();
  $stmtVeh->close();
  if (!$veh) op_send(false, ['error' => 'vehicle_not_owned'], 400);
  $plate = strtoupper(trim((string)($veh['plate_number'] ?? '')));

  $uploadsDir = __DIR__ . '/../../admin/uploads';
  if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);
  $moveAndScan = function (array $file, string $suffix) use ($uploadsDir, $plate): string {
    $name = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) throw new Exception('invalid_file_type');
    $filename = $plate . '_' . $suffix . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $dest = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $dest)) throw new Exception('upload_move_failed');
    $safe = tmm_scan_file_for_viruses($dest);
    if (!$safe) { if (is_file($dest)) @unlink($dest); throw new Exception('file_failed_security_scan'); }
    return $filename;
  };

  try {
    $deedPath = $moveAndScan($deed, 'deed');
    $orcrPath = $hasOrcr ? $moveAndScan($orcr, 'orcr') : null;

    $stmtIns = $db->prepare("INSERT INTO vehicle_ownership_transfers
      (vehicle_id, from_operator_id, to_operator_id, to_operator_name, transfer_type, lto_reference_no, deed_of_sale_path, orcr_path, status, effective_date,
       requested_by_portal_user_id, requested_by_name, requested_at)
      VALUES
      (?, ?, NULL, ?, ?, ?, ?, ?, 'Pending', NULL, ?, ?, NOW())");
    if (!$stmtIns) op_send(false, ['error' => 'db_prepare_failed'], 500);
    $orcrBind = $orcrPath !== null ? $orcrPath : null;
    $ltoBind = $ltoRef !== '' ? $ltoRef : null;
    $stmtIns->bind_param('iisssssis', $vehicleId, $operatorId, $toOperatorName, $transferType, $ltoBind, $deedPath, $orcrBind, $userId, $submittedBy);
    $ok = $stmtIns->execute();
    $transferId = (int)$stmtIns->insert_id;
    $stmtIns->close();
    if (!$ok) op_send(false, ['error' => 'db_error'], 500);

    op_audit_event($db, $userId, $email, 'PUV_TRANSFER_REQUEST_SUBMITTED', 'OwnershipTransfer', (string)$transferId, [
      'plate_number' => $plate,
      'to_operator_name' => $toOperatorName,
      'transfer_type' => $transferType,
    ]);

    op_send(true, ['message' => 'Transfer request submitted for admin approval.', 'data' => ['transfer_id' => $transferId]]);
  } catch (Throwable $e) {
    op_send(false, ['error' => $e instanceof Exception ? $e->getMessage() : 'Submission failed.'], 400);
  }
}

if ($action === 'puv_list_routes') {
  op_require_approved($db, $userId);
  $rows = [];
  
  if (!op_table_exists($db, 'routes')) {
      op_send(true, ['data' => []]); // Return empty list instead of failing if table missing
  }

  $res = $db->query("SELECT id, route_id, COALESCE(NULLIF(route_code,''), route_id) AS route_code, route_name, origin, destination, status
                     FROM routes
                     WHERE status='Active'
                     ORDER BY COALESCE(NULLIF(route_name,''), COALESCE(NULLIF(route_code,''), route_id)) ASC
                     LIMIT 800");
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $id = (int)($r['id'] ?? 0);
      if ($id <= 0) continue;
      $rows[] = [
        'route_id' => $id,
        'route_code' => (string)($r['route_code'] ?? ''),
        'route_name' => (string)($r['route_name'] ?? ''),
        'origin' => (string)($r['origin'] ?? ''),
        'destination' => (string)($r['destination'] ?? ''),
      ];
    }
  }
  op_send(true, ['data' => $rows]);
}

if ($action === 'puv_list_franchise_applications') {
  op_require_approved($db, $userId);
  $operatorId = op_get_puv_operator_id($db, $userId);
  
  // Return empty list if not linked, instead of error
  if ($operatorId <= 0) {
      op_send(true, ['data' => []]);
  }

  if (!op_table_exists($db, 'franchise_applications')) {
      op_send(true, ['data' => []]);
  }

  $rows = [];
  $stmt = $db->prepare("SELECT fa.application_id, fa.franchise_ref_number, fa.operator_id, fa.route_id, fa.vehicle_count, fa.approved_vehicle_count,
                               fa.representative_name, fa.status, fa.submitted_at, fa.endorsed_at, fa.approved_at,
                               COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code, r.origin, r.destination,
                               er.endorsement_status, er.conditions, er.permit_number, er.issued_date,
                               fr.ltfrb_ref_no, fr.authority_type, fr.issue_date, fr.expiry_date,
                               (SELECT COUNT(*) FROM documents d2 WHERE d2.application_id=fa.application_id AND LOWER(COALESCE(d2.type,''))='declared fleet' AND COALESCE(NULLIF(d2.file_path,''),'')<>'') AS declared_fleet_count
                        FROM franchise_applications fa
                        LEFT JOIN routes r ON r.id=fa.route_id
                        LEFT JOIN endorsement_records er ON er.application_id=fa.application_id
                        LEFT JOIN franchises fr ON fr.application_id=fa.application_id
                        WHERE fa.operator_id=?
                        ORDER BY fa.submitted_at DESC, fa.application_id DESC
                        LIMIT 300");
  if ($stmt) {
    $stmt->bind_param('i', $operatorId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) {
      $req = op_franchise_build_requirements($db, $operatorId, $r);
      $needs = false;
      if ($req['endorsement_blockers']) $needs = true;
      $status = (string)($r['status'] ?? '');
      if (in_array($status, ['Endorsed', 'LGU-Endorsed', 'Approved', 'LTFRB-Approved', 'PA Issued', 'CPC Issued'], true)) {
        if ($req['ltfrb_requirements']) $needs = true;
      }
      $rows[] = [
        'application_id' => (int)($r['application_id'] ?? 0),
        'reference' => (string)($r['franchise_ref_number'] ?? ''),
        'submitted_at' => (string)($r['submitted_at'] ?? ''),
        'route_code' => (string)($r['route_code'] ?? ''),
        'origin' => (string)($r['origin'] ?? ''),
        'destination' => (string)($r['destination'] ?? ''),
        'vehicle_count' => (int)($r['vehicle_count'] ?? 0),
        'approved_vehicle_count' => (int)($r['approved_vehicle_count'] ?? 0),
        'representative_name' => (string)($r['representative_name'] ?? ''),
        'status' => $status,
        'endorsed_at' => (string)($r['endorsed_at'] ?? ''),
        'approved_at' => (string)($r['approved_at'] ?? ''),
        'endorsement_status' => (string)($r['endorsement_status'] ?? ''),
        'conditions' => (string)($r['conditions'] ?? ''),
        'permit_number' => (string)($r['permit_number'] ?? ''),
        'issued_date' => (string)($r['issued_date'] ?? ''),
        'ltfrb_ref_no' => (string)($r['ltfrb_ref_no'] ?? ''),
        'authority_type' => (string)($r['authority_type'] ?? ''),
        'issue_date' => (string)($r['issue_date'] ?? ''),
        'expiry_date' => (string)($r['expiry_date'] ?? ''),
        'declared_fleet_count' => (int)($r['declared_fleet_count'] ?? 0),
        'requirements' => [
          'endorsement_blockers' => $req['endorsement_blockers'],
          'ltfrb' => $req['ltfrb_requirements'],
          'need_units' => (int)($req['need_units'] ?? 0),
          'vehicle_metrics' => $req['vehicle_metrics'] ?? null,
        ],
        'needs_attention' => $needs,
      ];
    }
    $stmt->close();
  }
  op_send(true, ['data' => $rows]);
}

if ($action === 'puv_get_franchise_application') {
  op_require_approved($db, $userId);
  $operatorId = op_get_puv_operator_id($db, $userId);
  if ($operatorId <= 0) op_send(false, ['error' => 'Operator record is not linked yet.'], 400);

  $appId = (int)($_GET['application_id'] ?? 0);
  if ($appId <= 0) op_send(false, ['error' => 'invalid_application_id'], 400);

  $stmt = $db->prepare("SELECT fa.*, 
                               COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code, r.route_name, r.origin, r.destination,
                               er.endorsement_status, er.conditions, er.permit_number, er.issued_date,
                               fr.ltfrb_ref_no, fr.decision_order_no, fr.authority_type, fr.issue_date, fr.expiry_date, fr.status AS franchise_status
                        FROM franchise_applications fa
                        LEFT JOIN routes r ON r.id=fa.route_id
                        LEFT JOIN endorsement_records er ON er.application_id=fa.application_id
                        LEFT JOIN franchises fr ON fr.application_id=fa.application_id
                        WHERE fa.application_id=? AND fa.operator_id=?
                        LIMIT 1");
  if (!$stmt) op_send(false, ['error' => 'db_prepare_failed'], 500);
  $stmt->bind_param('ii', $appId, $operatorId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row) op_send(false, ['error' => 'not_found'], 404);

  $docs = [];
  $stmtD = $db->prepare("SELECT id, type, file_path, uploaded_by, uploaded_at, verified
                         FROM documents
                         WHERE application_id=?
                         ORDER BY id DESC
                         LIMIT 30");
  if ($stmtD) {
    $stmtD->bind_param('i', $appId);
    $stmtD->execute();
    $resD = $stmtD->get_result();
    while ($resD && ($d = $resD->fetch_assoc())) {
      $docs[] = [
        'id' => (int)($d['id'] ?? 0),
        'type' => (string)($d['type'] ?? ''),
        'file_path' => (string)($d['file_path'] ?? ''),
        'uploaded_at' => (string)($d['uploaded_at'] ?? ''),
        'verified' => (int)($d['verified'] ?? 0),
      ];
    }
    $stmtD->close();
  }

  $req = op_franchise_build_requirements($db, $operatorId, $row);
  op_send(true, [
    'data' => [
      'application' => $row,
      'documents' => $docs,
      'requirements' => $req,
    ]
  ]);
}

if ($action === 'puv_submit_franchise_application') {
  op_require_csrf();
  op_require_approved($db, $userId);

  $u = op_get_user_row($db, $userId);
  if (!$u) op_send(false, ['error' => 'Unable to load operator account.'], 400);

  $operatorId = (int)($u['puv_operator_id'] ?? 0);
  if ($operatorId <= 0) $operatorId = op_get_puv_operator_id($db, $userId);
  if ($operatorId <= 0) op_send(false, ['error' => 'Operator record is not approved yet.'], 400);

  $routeId = (int)($_POST['route_id'] ?? 0);
  $vehicleCount = (int)($_POST['vehicle_count'] ?? 0);
  $repName = trim((string)($_POST['representative_name'] ?? ''));
  if ($routeId <= 0 || $vehicleCount <= 0) op_send(false, ['error' => 'Missing required fields.'], 400);
  if ($vehicleCount > 500) $vehicleCount = 500;
  $repName = substr($repName, 0, 150);

  $submittedBy = trim((string)($u['full_name'] ?? ''));
  if ($submittedBy === '') $submittedBy = trim((string)($u['association_name'] ?? ''));
  if ($submittedBy === '') $submittedBy = 'Operator';
  $email = strtolower(trim((string)($u['email'] ?? '')));

  $stmtR = $db->prepare("SELECT id, status FROM routes WHERE id=? LIMIT 1");
  if (!$stmtR) op_send(false, ['error' => 'db_prepare_failed'], 500);
  $stmtR->bind_param('i', $routeId);
  $stmtR->execute();
  $route = $stmtR->get_result()->fetch_assoc();
  $stmtR->close();
  if (!$route) op_send(false, ['error' => 'route_not_found'], 404);
  if ((string)($route['status'] ?? '') !== 'Active') op_send(false, ['error' => 'route_inactive'], 400);

  $franchiseRef = 'APP-' . date('Ymd') . '-' . substr(uniqid(), -6);
  $routeIdsVal = (string)$routeId;

  $uploadsDir = __DIR__ . '/../../admin/uploads/franchise/';
  if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);

  $hasUpload = isset($_FILES['declared_fleet_doc']) && is_array($_FILES['declared_fleet_doc']) && (int)($_FILES['declared_fleet_doc']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

  $db->begin_transaction();
  try {
    $stmt = $db->prepare("INSERT INTO franchise_applications
                          (franchise_ref_number, operator_id, route_id, route_ids, vehicle_count, representative_name, status, submitted_at, submitted_by_portal_user_id, submitted_by_name, submitted_channel)
                          VALUES (?, ?, ?, ?, ?, ?, 'Submitted', NOW(), ?, ?, 'operator_portal')");
    if (!$stmt) throw new Exception('db_prepare_failed');
    $stmt->bind_param('siisisiss', $franchiseRef, $operatorId, $routeId, $routeIdsVal, $vehicleCount, $repName, $userId, $submittedBy);
    if (!$stmt->execute()) throw new Exception('insert_failed');
    $appId = (int)$db->insert_id;
    $stmt->close();

    if ($hasUpload) {
      $f = $_FILES['declared_fleet_doc'];
      $tmp = (string)($f['tmp_name'] ?? '');
      $orig = (string)($f['name'] ?? '');
      if ($tmp === '' || $orig === '' || !is_uploaded_file($tmp)) throw new Exception('declared_fleet_invalid_file');
      $allowedExt = ['pdf','xlsx','xls','csv'];
      $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
      if ($ext === '' || !in_array($ext, $allowedExt, true)) throw new Exception('declared_fleet_invalid_type');
      $filename = 'APP' . $appId . '_declared_fleet_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
      $dest = $uploadsDir . $filename;
      if (!move_uploaded_file($tmp, $dest)) throw new Exception('declared_fleet_upload_failed');
      $safe = tmm_scan_file_for_viruses($dest);
      if (!$safe) {
        if (is_file($dest)) @unlink($dest);
        throw new Exception('declared_fleet_failed_scan');
      }
      $dbPath = 'franchise/' . $filename;
      $ins = $db->prepare("INSERT INTO documents (plate_number, type, file_path, uploaded_by, application_id) VALUES (NULL, 'Declared Fleet', ?, 'operator_portal', ?)");
      if (!$ins) { if (is_file($dest)) @unlink($dest); throw new Exception('db_prepare_failed'); }
      $ins->bind_param('si', $dbPath, $appId);
      if (!$ins->execute()) { $ins->close(); if (is_file($dest)) @unlink($dest); throw new Exception('db_error'); }
      $ins->close();
    } else {
      $stmtFleet = $db->prepare("SELECT file_path
                                 FROM operator_documents
                                 WHERE operator_id=?
                                   AND doc_type='Others'
                                   AND (doc_status='Verified' OR is_verified=1)
                                   AND LOWER(COALESCE(remarks,'')) LIKE '%declared fleet%'
                                 ORDER BY uploaded_at DESC, doc_id DESC
                                 LIMIT 1");
      if ($stmtFleet) {
        $stmtFleet->bind_param('i', $operatorId);
        $stmtFleet->execute();
        $rowFleet = $stmtFleet->get_result()->fetch_assoc();
        $stmtFleet->close();
        $fp = $rowFleet ? trim((string)($rowFleet['file_path'] ?? '')) : '';
        if ($fp !== '') {
          $hasVerifiedCol = false;
          $col = $db->query("SHOW COLUMNS FROM documents LIKE 'verified'");
          if ($col && $col->num_rows > 0) $hasVerifiedCol = true;
          $ins = $hasVerifiedCol
            ? $db->prepare("INSERT INTO documents (plate_number, type, file_path, uploaded_by, application_id, verified) VALUES (NULL, 'Declared Fleet', ?, 'operator_portal', ?, 1)")
            : $db->prepare("INSERT INTO documents (plate_number, type, file_path, uploaded_by, application_id) VALUES (NULL, 'Declared Fleet', ?, 'operator_portal', ?)");
          if ($ins) {
            $ins->bind_param('si', $fp, $appId);
            $ins->execute();
            $ins->close();
          }
        }
      }
    }

    $db->commit();
    op_audit_event($db, $userId, $email, 'PUV_FRANCHISE_SUBMITTED', 'FranchiseApplication', (string)$appId, ['route_id' => $routeId, 'vehicle_count' => $vehicleCount]);
    op_send(true, ['message' => 'Franchise application submitted for admin review.', 'data' => ['application_id' => $appId, 'franchise_ref_number' => $franchiseRef]]);
  } catch (Throwable $e) {
    $db->rollback();
    op_send(false, ['error' => 'Submission failed.'], 500);
  }
}

if ($action === 'puv_ocr_scan_cr') {
  op_require_csrf();
  op_require_approved($db, $userId);

  $ocr_send = function (bool $ok, string $message, array $data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data]);
    exit;
  };

  $ocr_norm_text = function (string $s): string {
    $s = preg_replace("/[\\t\\r]+/", " ", $s);
    $s = preg_replace("/[ ]{2,}/", " ", $s);
    return trim($s ?? '');
  };

  $ocr_extract_plate = function (string $text): ?string {
    $candidates = [];
    if (preg_match_all('/\\b([A-Z]{3})\\s*\\-?\\s*(\\d{3,4})\\b/', $text, $m, PREG_SET_ORDER)) {
      foreach ($m as $mm) $candidates[] = strtoupper($mm[1] . '-' . $mm[2]);
    }
    foreach ($candidates as $p) if (preg_match('/^[A-Z]{3}\\-[0-9]{3,4}$/', $p)) return $p;
    return $candidates ? $candidates[0] : null;
  };

  $ocr_norm_engine = function (string $s): string {
    $s = strtoupper($s);
    $s = preg_replace('/[^A-Z0-9\\-]/', '', $s);
    $s = preg_replace('/\\-+/', '-', $s);
    return trim($s ?? '');
  };

  $ocr_norm_vin = function (string $s): string {
    $s = strtoupper($s);
    $s = preg_replace('/[^A-Z0-9]/', '', $s);
    $s = strtr($s, ['O' => '0', 'I' => '1', 'Q' => '0']);
    return trim($s ?? '');
  };

  $ocr_extract_by_keywords = function (string $text, array $keys, string $pattern): ?string {
    foreach ($keys as $k) {
      $rx = '/(?:' . $k . ')\\s*(?:[:\\-\\|]|\\s)\\s*' . $pattern . '/i';
      if (preg_match($rx, $text, $m)) {
        $v = trim((string)($m[1] ?? ''));
        if ($v !== '') return $v;
      }
    }
    return null;
  };

  $ocr_parse_date_to_ymd = function (?string $raw): ?string {
    if (!is_string($raw)) return null;
    $s = strtoupper(trim($raw));
    if ($s === '') return null;
    $s = preg_replace('/[^0-9\/\-\.\s]/', '', $s);
    $s = trim((string)$s);
    if ($s === '') return null;
    if (preg_match('/\b(\d{4})\s*[\-\/\.]\s*(\d{1,2})\s*[\-\/\.]\s*(\d{1,2})\b/', $s, $m)) {
      $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
      if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    if (preg_match('/\b(\d{1,2})\s*[\-\/\.]\s*(\d{1,2})\s*[\-\/\.]\s*(\d{4})\b/', $s, $m)) {
      $a = (int)$m[1]; $b = (int)$m[2]; $y = (int)$m[3];
      $mo = $a; $d = $b;
      if (!checkdate($mo, $d, $y) && checkdate($b, $a, $y)) { $mo = $b; $d = $a; }
      if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    $months = [
      'JANUARY' => 1, 'JAN' => 1,
      'FEBRUARY' => 2, 'FEB' => 2,
      'MARCH' => 3, 'MAR' => 3,
      'APRIL' => 4, 'APR' => 4,
      'MAY' => 5,
      'JUNE' => 6, 'JUN' => 6,
      'JULY' => 7, 'JUL' => 7,
      'AUGUST' => 8, 'AUG' => 8,
      'SEPTEMBER' => 9, 'SEP' => 9, 'SEPT' => 9,
      'OCTOBER' => 10, 'OCT' => 10,
      'NOVEMBER' => 11, 'NOV' => 11,
      'DECEMBER' => 12, 'DEC' => 12,
    ];
    $s2 = strtoupper(trim((string)$raw));
    $s2 = preg_replace('/\s+/', ' ', $s2);
    if (preg_match('/\b([A-Z]{3,9})\s+(\d{1,2}),\s*(\d{4})\b/', $s2, $m)) {
      $mo = $months[$m[1]] ?? 0;
      $d = (int)$m[2];
      $y = (int)$m[3];
      if ($mo > 0 && checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    if (preg_match('/\b(\d{1,2})\s+([A-Z]{3,9})\s+(\d{4})\b/', $s2, $m)) {
      $d = (int)$m[1];
      $mo = $months[$m[2]] ?? 0;
      $y = (int)$m[3];
      if ($mo > 0 && checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    return null;
  };

  $ocr_extract_fields = function (string $raw) use ($ocr_extract_plate, $ocr_norm_engine, $ocr_norm_vin, $ocr_extract_by_keywords, $ocr_parse_date_to_ymd): array {
    $rawUp = strtoupper($raw);
    $lines = preg_split("/\\r?\\n+/", $rawUp) ?: [];
    $lines = array_values(array_filter(array_map(function ($ln) {
      $ln = preg_replace("/[\\t\\r]+/", " ", (string)$ln);
      $ln = preg_replace("/[ ]{2,}/", " ", (string)$ln);
      return trim($ln ?? '');
    }, $lines), function ($ln) { return $ln !== ''; }));
    $textFlat = preg_replace("/\\s+/", " ", $rawUp);

    $extractLineValue = function (array $keys) use ($lines): ?string {
      foreach ($lines as $ln) {
        foreach ($keys as $k) {
          if (!preg_match('/\\b' . $k . '\\b/i', $ln)) continue;
          $v = preg_replace('/^.*?\\b' . $k . '\\b\\s*(?:[:\\-\\|]|\\s)\\s*/i', '', $ln);
          $v = trim((string)$v);
          if ($v !== '' && strlen($v) <= 140) return $v;
        }
      }
      return null;
    };

    $plateLine = $extractLineValue(['PLATE\\s*NUMBER', 'PLATE\\s*NO', 'PLATE']);
    $plate = $ocr_extract_plate($plateLine ? $plateLine : $textFlat);

    $engineLine = $extractLineValue(['ENGINE\\s*NUMBER', 'ENGINE\\s*NO', 'ENGINE\\s*#', 'ENGINE\\s*NUM']);
    $engine = $engineLine ? $ocr_norm_engine($engineLine) : null;
    if ($engine !== null) {
      $engine = preg_replace('/(CHASSIS|VIN|NUMBER|MODEL|YEAR|FUEL|COLOR|OWNER).*$/', '', $engine);
      $engine = trim((string)$engine);
    }

    $chassisLine = $extractLineValue(['CHASSIS\\s*NUMBER', 'CHASSIS\\s*NO', 'CHASSIS\\s*#', 'VIN']);
    $vin = $chassisLine ? $ocr_norm_vin($chassisLine) : '';
    if ($vin === '' || strlen($vin) < 17) {
      $cand = $ocr_norm_vin($textFlat);
      if (preg_match('/[A-HJ-NPR-Z0-9]{17}/', $cand, $m)) $vin = (string)$m[0];
    }
    $chassis = ($vin !== '' && strlen($vin) === 17) ? $vin : null;

    $make = $extractLineValue(['MAKE']);
    if ($make) {
      $make = preg_replace('/\\b(MODEL|YEAR|FUEL|COLOR|ENGINE|CHASSIS|OWNER)\\b.*$/', '', $make);
      $make = trim((string)$make);
    }
    $series = $extractLineValue(['MODEL', 'SERIES']);
    if ($series) {
      $series = preg_replace('/\\b(YEAR|FUEL|COLOR|ENGINE|CHASSIS|OWNER)\\b.*$/', '', $series);
      $series = trim((string)$series);
    }

    $year = $extractLineValue(['YEAR\\s*MODEL']);
    if (!$year) $year = $ocr_extract_by_keywords($textFlat, ['YEAR\\s*MODEL', 'YEAR'], '([0-9]{4})');
    $fuel = $extractLineValue(['FUEL\\s*TYPE', 'FUEL']);
    if ($fuel) {
      $fuel = preg_replace('/\\b(TYPE|COLOR|ENGINE|CHASSIS|OWNER)\\b.*$/', '', $fuel);
      $fuel = trim((string)$fuel);
    }
    $color = $extractLineValue(['COLOR', 'COLOUR']);
    if ($color) {
      $color = preg_replace('/\\b(ENGINE|CHASSIS|OWNER)\\b.*$/', '', $color);
      $color = trim((string)$color);
    }

    $crNo = $extractLineValue(['CR\\s*NUMBER', 'CR\\s*NO', 'CERTIFICATE\\s*OF\\s*REGISTRATION\\s*NO', 'CERTIFICATE\\s*NO', 'REGISTRATION\\s*NO']);
    if (!$crNo) $crNo = $ocr_extract_by_keywords($textFlat, ['CR\\s*NUMBER', 'CR\\s*NO'], '([A-Z0-9\\-\\/]{4,40})');
    $crIssueRaw = $extractLineValue(['CR\\s*ISSUE\\s*DATE', 'CR\\s*DATE', 'DATE\\s*ISSUED', 'ISSUE\\s*DATE', 'DATE\\s*OF\\s*ISSUE']);
    if (!$crIssueRaw) $crIssueRaw = $ocr_extract_by_keywords($textFlat, ['CR\\s*ISSUE\\s*DATE', 'CR\\s*DATE', 'DATE\\s*ISSUED'], '([A-Z0-9,\\-\\/\\. ]{8,24})');
    $crIssueDate = $ocr_parse_date_to_ymd($crIssueRaw);
    $owner = $extractLineValue(['REGISTERED\\s*OWNER']);
    if (!$owner) $owner = $extractLineValue(['OWNER\\s*NAME']);
    if ($owner) {
      $owner = preg_replace('/\\b(OWNER\\s*ADDRESS|ADDRESS|NOTE)\\b.*$/', '', $owner);
      $owner = trim((string)$owner);
    }

    $out = [
      'plate_no' => $plate ? $plate : null,
      'engine_no' => $engine ? $engine : null,
      'chassis_no' => $chassis ? $chassis : null,
      'make' => $make ? trim($make) : null,
      'model' => $series ? trim($series) : null,
      'year_model' => $year ? $year : null,
      'fuel_type' => $fuel ? $fuel : null,
      'color' => $color ? trim($color) : null,
      'cr_number' => $crNo ? trim($crNo) : null,
      'cr_issue_date' => $crIssueDate,
      'registered_owner' => $owner ? trim($owner) : null,
    ];
    foreach ($out as $k => $v) if (!is_string($v) || trim($v) === '') $out[$k] = null;
    return $out;
  };

  $ocr_find_tesseract_path = function (): string {
    $fromEnv = trim((string)getenv('TMM_TESSERACT_PATH'));
    if ($fromEnv !== '') return $fromEnv;
    $root = realpath(__DIR__ . '/../../');
    if ($root) {
      $win = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tesseract' . DIRECTORY_SEPARATOR . 'tesseract.exe';
      if (is_file($win)) return $win;
      $nix = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tesseract' . DIRECTORY_SEPARATOR . 'tesseract';
      if (is_file($nix)) return $nix;
    }
    return 'tesseract';
  };

  $ocr_find_tessdata_dir = function (): string {
    $fromEnv = trim((string)getenv('TMM_TESSDATA_DIR'));
    if ($fromEnv !== '') return $fromEnv;
    $root = realpath(__DIR__ . '/../../');
    if ($root) {
      $t = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tesseract' . DIRECTORY_SEPARATOR . 'tessdata';
      if (is_dir($t) && is_file($t . DIRECTORY_SEPARATOR . 'eng.traineddata')) return $t;
    }
    $prefix = trim((string)getenv('TESSDATA_PREFIX'));
    if ($prefix !== '') return rtrim($prefix, "/\\") . DIRECTORY_SEPARATOR . 'tessdata';
    return '';
  };

  $ocr_run_tesseract = function (string $inputPath, int $psm) use ($ocr_find_tesseract_path, $ocr_find_tessdata_dir): array {
    $bin = $ocr_find_tesseract_path();
    $tessdata = $ocr_find_tessdata_dir();
    $tessArg = '';
    if ($tessdata !== '') $tessArg = ' --tessdata-dir "' . str_replace('"', '\"', $tessdata) . '"';
    $psm = $psm > 0 ? $psm : 6;
    $cmd = '"' . str_replace('"', '\"', $bin) . '" "' . str_replace('"', '\"', $inputPath) . '" stdout -l eng --oem 1 --psm ' . (int)$psm . ' -c preserve_interword_spaces=1' . $tessArg . ' 2>&1';
    $out = @shell_exec($cmd);
    $out = is_string($out) ? $out : '';
    $outTrim = trim($out);
    if ($outTrim === '') return ['ok' => false, 'text' => '', 'error' => 'ocr_empty_output'];
    if (stripos($outTrim, 'not recognized') !== false || stripos($outTrim, 'No such file') !== false) return ['ok' => false, 'text' => '', 'error' => 'tesseract_not_found'];
    if (preg_match('/\b(error|failed|cannot|could not|unable)\b/i', $outTrim) && !preg_match('/\b(PLATE|ENGINE|CHASSIS|CERTIFICATE|REGISTRATION|OWNER)\b/i', $outTrim)) {
      return ['ok' => false, 'text' => $outTrim, 'error' => 'tesseract_error'];
    }
    return ['ok' => true, 'text' => $outTrim, 'error' => '', 'psm' => $psm];
  };

  $ocr_count_filled = function (array $fields): int {
    $filled = 0;
    foreach ($fields as $v) if (is_string($v) && trim($v) !== '') $filled++;
    return $filled;
  };

  $ocr_best_tesseract = function (string $inputPath) use ($ocr_run_tesseract, $ocr_extract_fields, $ocr_norm_text, $ocr_count_filled): array {
    $psms = [6, 4, 11, 3];
    $best = ['ok' => false, 'text' => '', 'error' => 'ocr_empty_output', 'psm' => 0];
    $bestScore = -1;
    foreach ($psms as $psm) {
      $r = $ocr_run_tesseract($inputPath, $psm);
      if (!$r['ok']) {
        if (!$best['ok'] && $best['error'] === 'ocr_empty_output') $best = $r + ['psm' => $psm];
        continue;
      }
      $fields = $ocr_extract_fields($ocr_norm_text((string)$r['text']));
      $score = $ocr_count_filled($fields);
      if ($score > $bestScore) { $bestScore = $score; $best = $r + ['psm' => $psm]; }
      if ($bestScore >= 3) break;
    }
    return $best;
  };

  try {
    tmm_load_env_default();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') $ocr_send(false, 'Method not allowed', [], 405);
    if (!isset($_FILES['cr']) || !is_array($_FILES['cr'])) $ocr_send(false, 'CR file is required', [], 400);
    if ((int)($_FILES['cr']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) $ocr_send(false, 'CR upload failed', [], 400);
    $ext = strtolower(pathinfo((string)($_FILES['cr']['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) $ocr_send(false, 'Invalid file type', [], 400);

    $tmpDir = sys_get_temp_dir();
    $tmpName = 'tmm_cr_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $tmpPath = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $tmpName;
    if (!move_uploaded_file((string)$_FILES['cr']['tmp_name'], $tmpPath)) $ocr_send(false, 'Failed to read upload', [], 400);

    $safe = tmm_scan_file_for_viruses($tmpPath);
    if (!$safe) {
      if (is_file($tmpPath)) @unlink($tmpPath);
      $ocr_send(false, 'File failed security scan', [], 400);
    }

    $engine = strtolower(trim((string)getenv('TMM_OCR_ENGINE')));
    if ($engine === '') $engine = 'tesseract';
    if ($engine !== 'tesseract') {
      if (is_file($tmpPath)) @unlink($tmpPath);
      $ocr_send(false, 'OCR engine not configured', ['engine' => $engine], 400);
    }

    $r = $ocr_best_tesseract($tmpPath);
    if (is_file($tmpPath)) @unlink($tmpPath);

    if (!$r['ok']) {
      $msg = $r['error'] === 'tesseract_error'
        ? 'OCR could not read this file. Try uploading a clear CR image (JPG/PNG).'
        : 'OCR failed';
      $ocr_send(false, $msg, ['error' => $r['error'], 'raw_text_preview' => substr($ocr_norm_text((string)$r['text']), 0, 800)], 400);
    }

    $raw = $ocr_norm_text((string)$r['text']);
    $fields = $ocr_extract_fields($raw);
    $filled = $ocr_count_filled($fields);
    if ($filled === 0) {
      $ocr_send(false, 'No details were extracted. Try a clearer image or adjust OCR.', [
        'error' => 'no_fields_extracted',
        'raw_text_preview' => substr($raw, 0, 800),
        'fields' => $fields
      ], 400);
    }

    $ocr_send(true, 'ok', [
      'engine' => 'tesseract',
      'psm' => (int)($r['psm'] ?? 0),
      'raw_text_preview' => substr($raw, 0, 800),
      'fields' => $fields
    ]);
  } catch (Throwable $e) {
    $ocr_send(false, 'OCR failed', ['error' => 'server_error'], 500);
  }
}

if ($action === 'add_vehicle') {
  op_require_csrf();
  op_require_approved($db, $userId);
  $plateRaw = (string)($_POST['plate_number'] ?? '');
  $plateNorm = strtoupper(preg_replace('/\s+/', '', trim($plateRaw)));
  $plateNorm = preg_replace('/[^A-Z0-9-]/', '', $plateNorm);
  $letters = substr(preg_replace('/[^A-Z]/', '', $plateNorm), 0, 3);
  $digits = substr(preg_replace('/[^0-9]/', '', $plateNorm), 0, 4);
  $plate = ($letters !== '' && $digits !== '') ? ($letters . '-' . $digits) : $plateNorm;
  if ($plate === '' || !preg_match('/^[A-Z]{3}\-[0-9]{3,4}$/', $plate)) {
    op_send(false, ['error' => 'Invalid plate number.'], 400);
  }

  $type = trim((string)($_POST['vehicle_type'] ?? ''));
  if ($type === '') op_send(false, ['error' => 'Vehicle type required.'], 400);

  $ocrUsed = (int)($_POST['ocr_used'] ?? 0);
  $ocrConfirmed = (int)($_POST['ocr_confirmed'] ?? 0);
  if ($ocrUsed === 1 && $ocrConfirmed !== 1) {
    op_send(false, ['error' => 'Confirm scanned details before submitting.'], 400);
  }

  $engineNoRaw = (string)($_POST['engine_no'] ?? '');
  $engineNo = strtoupper(preg_replace('/\s+/', '', trim($engineNoRaw)));
  $engineNo = preg_replace('/[^A-Z0-9\-]/', '', $engineNo);
  if ($engineNo !== '' && !preg_match('/^[A-Z0-9\-]{5,20}$/', $engineNo)) {
    op_send(false, ['error' => 'Invalid engine number.'], 400);
  }
  $chassisNoRaw = (string)($_POST['chassis_no'] ?? '');
  $chassisNo = strtoupper(preg_replace('/\s+/', '', trim($chassisNoRaw)));
  $chassisNo = preg_replace('/[^A-HJ-NPR-Z0-9]/', '', $chassisNo);
  if ($chassisNo !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $chassisNo)) {
    op_send(false, ['error' => 'Invalid chassis number (VIN).'], 400);
  }

  $make = trim((string)($_POST['make'] ?? ''));
  $model = trim((string)($_POST['model'] ?? ''));
  $yearModel = preg_replace('/\D+/', '', trim((string)($_POST['year_model'] ?? '')));
  $yearModel = substr($yearModel, 0, 4);
  $fuelType = trim((string)($_POST['fuel_type'] ?? ''));
  $color = trim((string)($_POST['color'] ?? ''));

  $make = substr($make, 0, 40);
  $model = substr($model, 0, 40);
  $fuelType = substr($fuelType, 0, 20);
  $color = substr($color, 0, 64);
  if ($yearModel !== '' && !preg_match('/^[0-9]{4}$/', $yearModel)) {
    op_send(false, ['error' => 'Invalid year model.'], 400);
  }

  $orNumberRaw = (string)($_POST['or_number'] ?? '');
  $orNumber = preg_replace('/[^0-9]/', '', trim($orNumberRaw));
  $orNumber = substr($orNumber, 0, 12);
  if ($orNumber !== '' && !preg_match('/^[0-9]{6,12}$/', $orNumber)) {
    op_send(false, ['error' => 'Invalid OR number.'], 400);
  }
  $crNumberRaw = (string)($_POST['cr_number'] ?? '');
  $crNumber = strtoupper(preg_replace('/\s+/', '', trim($crNumberRaw)));
  $crNumber = preg_replace('/[^A-Z0-9\-]/', '', $crNumber);
  $crNumber = substr($crNumber, 0, 20);
  if ($crNumber !== '' && !preg_match('/^[A-Z0-9\-]{6,20}$/', $crNumber)) {
    op_send(false, ['error' => 'Invalid CR number.'], 400);
  }
  $crIssueDate = trim((string)($_POST['cr_issue_date'] ?? ''));
  if ($crIssueDate !== '' && !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $crIssueDate)) {
    op_send(false, ['error' => 'Invalid CR issue date.'], 400);
  }
  $registeredOwner = trim((string)($_POST['registered_owner'] ?? ''));

  $crFile = $_FILES['cr'] ?? null;
  if (!is_array($crFile) || (int)($crFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    op_send(false, ['error' => 'CR file is required.'], 400);
  }

  $orFile = $_FILES['or'] ?? null;
  $hasOrUpload = is_array($orFile) && (int)($orFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
  $orExpiry = trim((string)($_POST['or_expiry_date'] ?? ''));
  if ($hasOrUpload) {
    if ($orExpiry === '' || !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $orExpiry)) {
      op_send(false, ['error' => 'OR expiry date is required when OR file is uploaded.'], 400);
    }
  } else {
    $orExpiry = '';
  }

  $u = op_get_user_row($db, $userId);
  $submittedBy = $u ? (trim((string)($u['full_name'] ?? '')) ?: trim((string)($u['association_name'] ?? ''))) : '';
  if ($submittedBy === '') $submittedBy = 'Operator';
  $email = $u ? strtolower(trim((string)($u['email'] ?? ''))) : '';

  $uploadsDir = __DIR__ . '/../../admin/uploads';
  if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);

  $moveAndScan = function (array $file, string $suffix) use ($uploadsDir, $plate): string {
    $name = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
      throw new Exception('invalid_file_type');
    }
    $filename = $plate . '_' . $suffix . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $dest = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
      throw new Exception('upload_move_failed');
    }
    $safe = tmm_scan_file_for_viruses($dest);
    if (!$safe) {
      if (is_file($dest)) { @unlink($dest); }
      throw new Exception('file_failed_security_scan');
    }
    return $filename;
  };

  try {
    $crPath = $moveAndScan($crFile, 'cr');
    $orPath = $hasOrUpload ? $moveAndScan($orFile, 'or') : null;

    $stmt = $db->prepare("INSERT INTO vehicle_record_submissions
      (portal_user_id, plate_number, vehicle_type, engine_no, chassis_no, make, model, year_model, fuel_type, color, or_number, cr_number, cr_issue_date, registered_owner,
       cr_file_path, or_file_path, or_expiry_date, status, submitted_at, submitted_by_name)
      VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', NOW(), ?)");
    if (!$stmt) op_send(false, ['error' => 'Submission failed.'], 500);
    $orExpiryBind = $orExpiry !== '' ? $orExpiry : null;
    $crIssueBind = $crIssueDate !== '' ? $crIssueDate : null;
    $orPathBind = $orPath !== null ? $orPath : null;
    $stmt->bind_param(
      'isssssssssssssssss',
      $userId,
      $plate,
      $type,
      $engineNo,
      $chassisNo,
      $make,
      $model,
      $yearModel,
      $fuelType,
      $color,
      $orNumber,
      $crNumber,
      $crIssueBind,
      $registeredOwner,
      $crPath,
      $orPathBind,
      $orExpiryBind,
      $submittedBy
    );
    $ok = $stmt->execute();
    $submissionId = (int)$stmt->insert_id;
    $stmt->close();
    if (!$ok) op_send(false, ['error' => 'Submission failed.'], 500);

    op_audit_event($db, $userId, $email, 'PUV_VEHICLE_SUBMITTED', 'VehicleSubmission', (string)$submissionId, [
      'plate_number' => $plate,
      'vehicle_type' => $type,
    ]);

    op_send(true, [
      'message' => 'Vehicle encoding submitted for admin verification.',
      'data' => ['submission_id' => $submissionId, 'plate_number' => $plate]
    ]);
  } catch (Throwable $e) {
    op_send(false, ['error' => $e instanceof Exception ? $e->getMessage() : 'Submission failed.'], 400);
  }
}

if ($action === 'get_fees') {
  $plates = op_user_plates($db, $userId);
  if (!$plates)
    op_send(true, ['data' => []]);
  $in = implode(',', array_fill(0, count($plates), '?'));
  $types = str_repeat('s', count($plates));

  // Also get fees linked directly to user_id (if any, though schema links plate)
  $sql = "SELECT * FROM operator_portal_fees WHERE user_id=? OR plate_number IN ($in) ORDER BY created_at DESC";
  // Binding is tricky with mixed types. Let's stick to plate-based or user-based.
  // Actually, let's just use user_id for simplicity as per requirements? 
  // But fees correspond to plates.
  // Let's simple query:
  $rows = [];
  $stmt = $db->prepare("SELECT id, plate_number, type, amount, status, created_at FROM operator_portal_fees WHERE user_id=? ORDER BY created_at DESC");
  if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc())
      $rows[] = $r;
    $stmt->close();
  }
  op_send(true, ['data' => $rows]);
}

if ($action === 'upload_payment') {
  op_require_csrf();
  op_require_approved($db, $userId);
  $feeId = (int) ($_POST['fee_id'] ?? 0);
  if ($feeId <= 0)
    op_send(false, ['error' => 'Invalid Fee ID'], 400);

  $proofPath = '';
  if (!empty($_FILES['payment_proof'])) {
    $file = $_FILES['payment_proof'];
    if ($file['error'] === UPLOAD_ERR_OK) {
      $targetDir = __DIR__ . '/uploads/';
      if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
      }
      $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
      $filename = 'pay_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
      if (@move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
        $proofPath = 'uploads/' . $filename;
      }
    }
  }

  if (!$proofPath)
    op_send(false, ['error' => 'No file uploaded'], 400);

  $stmt = $db->prepare("UPDATE operator_portal_fees SET status='Verification', proof_doc=? WHERE id=? AND user_id=?");
  $stmt->bind_param('sii', $proofPath, $feeId, $userId);
  $stmt->execute();
  if ($stmt->affected_rows > 0) {
    op_send(true, ['message' => 'Payment proof submitted.']);
  } else {
    op_send(false, ['error' => 'Update failed or invalid fee.'], 400);
  }
}

if ($action === 'puv_get_violation_types') {
  op_require_approved($db, $userId);
  // Philippine PUV Common Violations
  $types = [
      ['code' => 'COL', 'name' => 'Colorum (Unregistered)'],
      ['code' => 'RTC', 'name' => 'Refusal to Convey'],
      ['code' => 'OVC', 'name' => 'Overcharging / Undercharging'],
      ['code' => 'DWD', 'name' => 'Driving Without Driver\'s License'],
      ['code' => 'UVR', 'name' => 'Unregistered Vehicle'],
      ['code' => 'RDL', 'name' => 'Reckless Driving'],
      ['code' => 'OBS', 'name' => 'Obstruction of Traffic'],
      ['code' => 'DTS', 'name' => 'Disregarding Traffic Signs'],
      ['code' => 'OVL', 'name' => 'Overloading'],
      ['code' => 'SMK', 'name' => 'Smoke Belching'],
      ['code' => 'NPT', 'name' => 'No Plate Attached'],
      ['code' => 'FWE', 'name' => 'Failure to Wear Seatbelt'],
      ['code' => 'DUI', 'name' => 'Driving Under Influence'],
      ['code' => 'CUT', 'name' => 'Cutting Trip'],
      ['code' => 'OOS', 'name' => 'Out of Line / Out of Service Area'],
      ['code' => 'NOP', 'name' => 'No Permit to Operate'],
  ];
  op_send(true, ['data' => $types]);
}

if ($action === 'get_violations') {
  $plates = op_user_plates($db, $userId);
  if (!$plates)
    op_send(true, ['data' => []]);
  $in = implode(',', array_fill(0, count($plates), '?'));
  $types = str_repeat('s', count($plates));

  $rows = [];
  $stmt = $db->prepare("SELECT * FROM violations WHERE plate_number IN ($in) ORDER BY violation_date DESC");
  if ($stmt) {
    $stmt->bind_param($types, ...$plates);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
      $rows[] = [
        'ticket_no' => $r['id'],
        'plate' => $r['plate_number'],
        'violation' => $r['violation_type'],
        'amount' => $r['amount'],
        'status' => $r['status'],
        'date' => $r['violation_date']
      ];
    }
    $stmt->close();
  }
  op_send(true, ['data' => $rows]);
}

if ($action === 'get_notifications') {
  $rows = [];
  $stmt = $db->prepare("SELECT id, title, message, type, is_read, created_at FROM operator_portal_notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
  if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc())
      $rows[] = $r;
    $stmt->close();
  }
  op_send(true, ['data' => $rows]);
}

if ($action === 'get_downloads') {
  $items = [];

  $plates = op_user_plates($db, $userId);
  if ($plates) {
    $in = implode(',', array_fill(0, count($plates), '?'));
    $types = str_repeat('s', count($plates));
    $stmtV = $db->prepare("SELECT plate_number, inspection_status, inspection_passed_at, inspection_cert_ref FROM vehicles WHERE plate_number IN ($in)");
    if ($stmtV) {
      $stmtV->bind_param($types, ...$plates);
      $stmtV->execute();
      $resV = $stmtV->get_result();
      while ($resV && ($r = $resV->fetch_assoc())) {
        $st = (string)($r['inspection_status'] ?? '');
        if ($st !== 'Passed') continue;
        $ref = trim((string)($r['inspection_cert_ref'] ?? ''));
        if ($ref === '') continue;
        $items[] = [
          'title' => 'Inspection Certificate',
          'meta' => (string)($r['plate_number'] ?? '') . ' • ' . substr((string)($r['inspection_passed_at'] ?? ''), 0, 10),
          'value' => $ref,
        ];
      }
      $stmtV->close();
    }
  }

  $stmtA = $db->prepare("SELECT id, plate_number, type, status, documents, created_at FROM operator_portal_applications WHERE user_id=? AND status IN ('Approved','Endorsed') ORDER BY created_at DESC LIMIT 50");
  if ($stmtA) {
    $stmtA->bind_param('i', $userId);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    while ($resA && ($r = $resA->fetch_assoc())) {
      $docs = json_decode((string)($r['documents'] ?? '[]'), true);
      if (!is_array($docs)) $docs = [];
      foreach ($docs as $p) {
        $p = (string)$p;
        if ($p === '') continue;
        $items[] = [
          'title' => (string)($r['type'] ?? 'Document'),
          'meta' => (string)($r['plate_number'] ?? '') . ' • ' . (string)($r['status'] ?? ''),
          'href' => $p,
        ];
      }
    }
    $stmtA->close();
  }

  $stmtD = $db->prepare("SELECT doc_key, file_path, status FROM operator_portal_documents WHERE user_id=? AND status IN ('Valid','Approved','Pending') ORDER BY uploaded_at DESC LIMIT 20");
  if ($stmtD) {
    $stmtD->bind_param('i', $userId);
    $stmtD->execute();
    $resD = $stmtD->get_result();
    while ($resD && ($r = $resD->fetch_assoc())) {
      $path = (string)($r['file_path'] ?? '');
      if ($path === '') continue;
      $items[] = [
        'title' => 'Verification Document',
        'meta' => (string)($r['doc_key'] ?? '') . ' • ' . (string)($r['status'] ?? ''),
        'href' => $path,
      ];
    }
    $stmtD->close();
  }

  op_send(true, ['data' => $items]);
}

if ($action === 'mark_notification_read') {
  op_require_csrf();
  $nid = (int) ($_POST['id'] ?? 0);
  $stmt = $db->prepare("UPDATE operator_portal_notifications SET is_read=1 WHERE id=? AND user_id=?");
  $stmt->bind_param('ii', $nid, $userId);
  $stmt->execute();
  op_send(true, ['success' => true]);
}

op_send(false, ['error' => 'Unknown action'], 400);
