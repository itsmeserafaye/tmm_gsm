<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../includes/operator_portal.php';

header('Content-Type: application/json');

if (empty($_SESSION['operator_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

if (empty($_SESSION['operator_csrf'])) {
  $_SESSION['operator_csrf'] = bin2hex(random_bytes(32));
}

$db = db();
$action = (string)($_REQUEST['action'] ?? '');
$userId = (int)$_SESSION['operator_user_id'];
$activePlate = strtoupper((string)($_SESSION['operator_plate'] ?? ''));

function op_send(bool $ok, array $payload = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok' => $ok], $payload));
  exit;
}

function op_require_csrf(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
  $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
  $sess = (string)($_SESSION['operator_csrf'] ?? '');
  if ($token === '' || $sess === '' || !hash_equals($sess, $token)) {
    op_send(false, ['error' => 'Invalid request. Please refresh and try again.'], 403);
  }
}

function op_user_plates(mysqli $db, int $userId): array {
  $plates = [];
  $stmt = $db->prepare("SELECT plate_number FROM operator_portal_user_plates WHERE user_id=?");
  if (!$stmt) return $plates;
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $plates[] = (string)$row['plate_number'];
    }
  }
  $stmt->close();
  return $plates;
}

if ($action === 'get_session') {
  $plates = op_user_plates($db, $userId);
  op_send(true, ['data' => [
    'active_plate' => $activePlate,
    'plates' => $plates,
    'csrf_token' => (string)($_SESSION['operator_csrf'] ?? ''),
  ]]);
}

if ($action === 'set_active_plate') {
  op_require_csrf();
  $plate = strtoupper(trim((string)($_POST['plate_number'] ?? '')));
  if ($plate === '') op_send(false, ['error' => 'Missing plate number'], 400);
  $plates = op_user_plates($db, $userId);
  if (!in_array($plate, $plates, true)) op_send(false, ['error' => 'Plate is not assigned to this account.'], 403);
  $_SESSION['operator_plate'] = $plate;
  op_send(true, ['data' => ['active_plate' => $plate]]);
}

if ($action === 'get_dashboard_stats') {
  $plates = op_user_plates($db, $userId);
  if (!$plates) op_send(true, ['data' => ['pending_apps' => 0, 'active_vehicles' => 0, 'compliance_alerts' => 0]]);

  $in = implode(',', array_fill(0, count($plates), '?'));
  $types = str_repeat('s', count($plates));

  $pending = 0;
  $stmt = $db->prepare("SELECT COUNT(*) AS c FROM operator_portal_applications WHERE user_id=? AND status='Pending'");
  if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $pending = (int)(($res && ($r = $res->fetch_assoc())) ? ($r['c'] ?? 0) : 0);
    $stmt->close();
  }

  $activeVehicles = 0;
  $sql = "SELECT COUNT(*) AS c FROM vehicles WHERE plate_number IN ($in) AND status='Active'";
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$plates);
    $stmt->execute();
    $res = $stmt->get_result();
    $activeVehicles = (int)(($res && ($r = $res->fetch_assoc())) ? ($r['c'] ?? 0) : 0);
    $stmt->close();
  }

  $alerts = 0;
  $sql = "SELECT COUNT(*) AS c FROM vehicles WHERE plate_number IN ($in) AND (inspection_status IS NULL OR inspection_status <> 'Passed')";
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$plates);
    $stmt->execute();
    $res = $stmt->get_result();
    $alerts = (int)(($res && ($r = $res->fetch_assoc())) ? ($r['c'] ?? 0) : 0);
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
      $bad = (int)(($res && ($r = $res->fetch_assoc())) ? ($r['c'] ?? 0) : 0);
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
      while ($resT && ($row = $resT->fetch_assoc())) $terminals[] = (string)($row['terminal_name'] ?? '');
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
        $insightsPayload = json_decode((string)$raw, true);
      } catch (Throwable $e) {
        $insightsPayload = null;
      }

      if (is_array($insightsPayload) && ($insightsPayload['ok'] ?? false) && !empty($insightsPayload['hotspots']) && is_array($insightsPayload['hotspots'])) {
        foreach ($insightsPayload['hotspots'] as $h) {
          if (!is_array($h)) continue;
          $label = (string)($h['area_label'] ?? '');
          if ($label === '' || !in_array($label, $terminals, true)) continue;
          $sev = (string)($h['severity'] ?? 'medium');
          $extra = $h['recommended_extra_units'] ?? null;
          $drivers = $h['drivers'] ?? [];
          $driversText = (is_array($drivers) && $drivers) ? (' Drivers: ' . implode(' • ', array_slice($drivers, 0, 2)) . '.') : '';
          $extraText = (is_numeric($extra) && (int)$extra > 0) ? (' Suggested: +' . (int)$extra . ' units.') : '';
          $insights[] = [
            'title' => 'Demand Hotspot: ' . $label,
            'desc' => 'Predicted spike at ' . (string)($h['peak_hour'] ?? '') . '. ' . $extraText . $driversText,
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
  if (!$plates) op_send(true, ['data' => []]);
  $in = implode(',', array_fill(0, count($plates), '?'));
  $types = str_repeat('s', count($plates));

  $sql = "SELECT plate_number, status, inspection_status, inspection_passed_at FROM vehicles WHERE plate_number IN ($in) ORDER BY plate_number ASC";
  $stmt = $db->prepare($sql);
  if (!$stmt) op_send(false, ['error' => 'Query failed'], 500);
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
        'inspection_last_date' => $row['inspection_passed_at'] ? substr((string)$row['inspection_passed_at'], 0, 10) : null,
      ];
    }
  }
  $stmt->close();
  op_send(true, ['data' => $rows]);
}

if ($action === 'get_applications') {
  $rows = [];
  $stmt = $db->prepare("SELECT id, plate_number, type, status, notes, created_at FROM operator_portal_applications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
  if (!$stmt) op_send(false, ['error' => 'Query failed'], 500);
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($res && ($r = $res->fetch_assoc())) {
    $rows[] = [
      'id' => (int)($r['id'] ?? 0),
      'plate_number' => (string)($r['plate_number'] ?? ''),
      'type' => (string)($r['type'] ?? ''),
      'status' => (string)($r['status'] ?? ''),
      'notes' => (string)($r['notes'] ?? ''),
      'created_at' => (string)($r['created_at'] ?? ''),
    ];
  }
  $stmt->close();
  op_send(true, ['data' => $rows]);
}

if ($action === 'get_profile') {
  $stmt = $db->prepare("SELECT email, full_name, contact_info, association_name FROM operator_portal_users WHERE id=? LIMIT 1");
  if (!$stmt) op_send(false, ['error' => 'Query failed'], 500);
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  op_send(true, ['data' => [
    'name' => $row['full_name'] ?? 'Operator',
    'email' => $row['email'] ?? ($_SESSION['operator_email'] ?? ''),
    'contact_info' => $row['contact_info'] ?? '',
    'association_name' => $row['association_name'] ?? '',
    'plate_number' => $activePlate,
  ]]);
}

if ($action === 'update_profile') {
  op_require_csrf();
  $name = trim((string)($_POST['name'] ?? ''));
  $email = strtolower(trim((string)($_POST['email'] ?? '')));
  $contact = trim((string)($_POST['contact_info'] ?? ''));
  $currentPass = (string)($_POST['current_password'] ?? '');
  $newPass = (string)($_POST['new_password'] ?? '');

  $stmt = $db->prepare("SELECT email, password_hash FROM operator_portal_users WHERE id=? LIMIT 1");
  if (!$stmt) op_send(false, ['error' => 'Update failed'], 500);
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!$user) op_send(false, ['error' => 'Update failed'], 500);
  if ($currentPass === '' || !password_verify($currentPass, (string)($user['password_hash'] ?? ''))) {
    op_send(false, ['error' => 'Current password is incorrect.'], 400);
  }

  $setPwd = false;
  $pwdHash = '';
  if ($newPass !== '') {
    if (strlen($newPass) < 10) op_send(false, ['error' => 'New password must be at least 10 characters.'], 400);
    $pwdHash = password_hash($newPass, PASSWORD_DEFAULT);
    if ($pwdHash === false) op_send(false, ['error' => 'Failed to set password.'], 500);
    $setPwd = true;
  }

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) op_send(false, ['error' => 'Invalid email.'], 400);

  if ($setPwd) {
    $stmt = $db->prepare("UPDATE operator_portal_users SET full_name=?, email=?, contact_info=?, password_hash=? WHERE id=?");
    if (!$stmt) op_send(false, ['error' => 'Update failed'], 500);
    $stmt->bind_param('ssssi', $name, $email, $contact, $pwdHash, $userId);
  } else {
    $stmt = $db->prepare("UPDATE operator_portal_users SET full_name=?, email=?, contact_info=? WHERE id=?");
    if (!$stmt) op_send(false, ['error' => 'Update failed'], 500);
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
  $type = trim((string)($_POST['type'] ?? ''));
  $notes = trim((string)($_POST['notes'] ?? ''));
  if ($type === '') op_send(false, ['error' => 'Application type required'], 400);

  $plate = $activePlate;
  if ($plate === '') op_send(false, ['error' => 'No active plate in session'], 400);
  $plates = op_user_plates($db, $userId);
  if (!in_array($plate, $plates, true)) op_send(false, ['error' => 'Active plate is not assigned to this account'], 403);

  $filePaths = [];
  if (!empty($_FILES)) {
    $targetDir = __DIR__ . '/uploads/';
    if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
    foreach ($_FILES as $file) {
      if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
      if ((int)($file['size'] ?? 0) > (5 * 1024 * 1024)) continue;
      $original = (string)($file['name'] ?? '');
      $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
      $allowedExt = ['jpg','jpeg','png','pdf'];
      if ($ext !== '' && !in_array($ext, $allowedExt, true)) continue;
      $tmp = (string)($file['tmp_name'] ?? '');
      if ($tmp === '' || !is_uploaded_file($tmp)) continue;
      $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
      $mime = $finfo ? finfo_file($finfo, $tmp) : null;
      if ($finfo) finfo_close($finfo);
      $allowedMime = ['image/jpeg','image/png','application/pdf'];
      if ($mime && !in_array($mime, $allowedMime, true)) continue;
      $ext = pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION);
      $filename = 'app_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext !== '' ? ('.' . $ext) : '');
      if (@move_uploaded_file((string)($file['tmp_name'] ?? ''), $targetDir . $filename)) {
        $filePaths[] = 'uploads/' . $filename;
      }
    }
  }

  $docsJson = json_encode($filePaths);
  $stmt = $db->prepare("INSERT INTO operator_portal_applications(user_id, plate_number, type, status, notes, documents) VALUES(?, ?, ?, 'Pending', ?, ?)");
  if (!$stmt) op_send(false, ['error' => 'Submission failed'], 500);
  $stmt->bind_param('issss', $userId, $plate, $type, $notes, $docsJson);
  $stmt->execute();
  $stmt->close();
  op_send(true, ['ref' => 'OP-' . strtoupper(bin2hex(random_bytes(4)))]);
}

op_send(false, ['error' => 'Unknown action'], 400);
