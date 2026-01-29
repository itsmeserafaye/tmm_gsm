<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}
require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../includes/operator_portal.php';

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

  $sql = "SELECT plate_number, status, inspection_status, inspection_passed_at FROM vehicles WHERE plate_number IN ($in) ORDER BY plate_number ASC";
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

if ($action === 'add_vehicle') {
  op_require_csrf();
  op_require_approved($db, $userId);
  $plate = strtoupper(trim((string) ($_POST['plate_number'] ?? '')));
  if ($plate === '')
    op_send(false, ['error' => 'Plate number required'], 400);

  $orCrPath = '';
  if (!empty($_FILES['or_cr_doc'])) {
    $file = $_FILES['or_cr_doc'];
    if ($file['error'] === UPLOAD_ERR_OK) {
      $targetDir = __DIR__ . '/uploads/';
      if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
      }
      $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
      $filename = 'orcr_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
      if (@move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
        $orCrPath = 'uploads/' . $filename;
      }
    }
  }

  $u = op_get_user_row($db, $userId);
  $opName = '';
  if ($u) {
    $opName = trim((string) ($u['association_name'] ?? ''));
    if ($opName === '')
      $opName = trim((string) ($u['full_name'] ?? ''));
  }
  if ($opName === '')
    $opName = 'Operator';

  $stmtVeh = $db->prepare("INSERT IGNORE INTO vehicles (plate_number, operator_name, record_status, status) VALUES (?, ?, 'Encoded', 'Active')");
  if ($stmtVeh) {
    $stmtVeh->bind_param('ss', $plate, $opName);
    $stmtVeh->execute();
    $stmtVeh->close();
  }

  $docsJson = json_encode($orCrPath ? [$orCrPath] : []);
  $stmt = $db->prepare("INSERT INTO operator_portal_applications(user_id, plate_number, type, status, notes, documents) VALUES(?, ?, 'Vehicle Registration', 'Pending', 'New vehicle registration', ?)");
  if (!$stmt)
    op_send(false, ['error' => 'Submission failed'], 500);
  $stmt->bind_param('iss', $userId, $plate, $docsJson);
  $stmt->execute();
  $stmt->close();

  op_send(true, ['message' => 'Vehicle registration submitted for verification.']);
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
