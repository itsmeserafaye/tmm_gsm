<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}
require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../includes/operator_portal.php';
require_once __DIR__ . '/../../admin/includes/security.php';
require_once __DIR__ . '/../../admin/includes/util.php';

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
  $stmt = $db->prepare("SELECT id, email, full_name, contact_info, association_name, operator_type, approval_status, verification_submitted_at, approval_remarks, status FROM operator_portal_users WHERE id=? LIMIT 1");
  if (!$stmt)
    return null;
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return is_array($row) ? $row : null;
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

  $sql = "SELECT plate_number, status, record_status, operator_id, current_operator_id, inspection_status, inspection_passed_at
          FROM vehicles
          WHERE plate_number IN ($in)
          ORDER BY plate_number ASC";
  $stmt = $db->prepare($sql);
  if (!$stmt)
    op_send(false, ['error' => 'Query failed'], 500);
  $stmt->bind_param($types, ...$plates);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $rows[] = [
        'plate_number' => $row['plate_number'],
        'status' => $row['status'] ?? 'Active',
        'record_status' => $row['record_status'] ?? null,
        'operator_id' => isset($row['operator_id']) ? (int)$row['operator_id'] : null,
        'current_operator_id' => isset($row['current_operator_id']) ? (int)$row['current_operator_id'] : null,
        'inspection_status' => $row['inspection_status'] ?? null,
        'inspection_last_date' => $row['inspection_passed_at'] ? substr((string) $row['inspection_passed_at'], 0, 10) : null,
      ];
    }
  }
  $stmt->close();
  op_send(true, ['data' => $rows]);
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

  $plate = $activePlate;
  if ($plate === '')
    op_send(false, ['error' => 'No active plate in session'], 400);
  $plates = op_user_plates($db, $userId);
  if (!in_array($plate, $plates, true))
    op_send(false, ['error' => 'Active plate is not assigned to this account'], 403);

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

  $operatorType = trim((string)($_POST['operator_type'] ?? ($u['operator_type'] ?? 'Individual')));
  if (!in_array($operatorType, ['Individual','Cooperative','Corporation'], true)) $operatorType = 'Individual';

  $registeredName = trim((string)($_POST['registered_name'] ?? ''));
  $name = trim((string)($_POST['name'] ?? ''));
  $address = trim((string)($_POST['address'] ?? ''));
  $contactNo = trim((string)($_POST['contact_no'] ?? ($_POST['contact_info'] ?? ($u['contact_info'] ?? ''))));
  $email = strtolower(trim((string)($u['email'] ?? '')));
  $coopName = trim((string)($_POST['coop_name'] ?? ($_POST['association_name'] ?? ($u['association_name'] ?? ''))));

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
  $u = op_get_user_row($db, $userId);
  if (!$u) op_send(false, ['error' => 'Unable to load operator account.'], 400);
  $operatorId = (int)($u['puv_operator_id'] ?? 0);
  if ($operatorId <= 0) op_send(true, ['data' => []]);

  $rows = [];
  $stmt = $db->prepare("SELECT id AS vehicle_id, UPPER(plate_number) AS plate_number
                        FROM vehicles
                        WHERE COALESCE(NULLIF(current_operator_id,0), NULLIF(operator_id,0), 0)=?
                          AND COALESCE(NULLIF(plate_number,''),'') <> ''
                        ORDER BY plate_number ASC");
  if ($stmt) {
    $stmt->bind_param('i', $operatorId);
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
  $yearModel = trim((string)($_POST['year_model'] ?? ''));
  $fuelType = trim((string)($_POST['fuel_type'] ?? ''));
  $color = trim((string)($_POST['color'] ?? ''));

  $orNumberRaw = (string)($_POST['or_number'] ?? '');
  $orNumber = preg_replace('/[^0-9]/', '', trim($orNumberRaw));
  $orNumber = substr($orNumber, 0, 12);
  if ($orNumber !== '' && !preg_match('/^[0-9]{6,12}$/', $orNumber)) {
    op_send(false, ['error' => 'Invalid OR number.'], 400);
  }
  $crNumberRaw = (string)($_POST['cr_number'] ?? '');
  $crNumber = strtoupper(preg_replace('/\s+/', '', trim($crNumberRaw)));
  $crNumber = preg_replace('/[^A-Z0-9\-]/', '', $crNumber);
  $crNumber = substr($crNumber, 0, 64);
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
