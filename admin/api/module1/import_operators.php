<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/import.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.write','module1.vehicles.write']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

[$tmp, $err] = tmm_import_get_uploaded_csv('file');
if ($err) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $err]);
  exit;
}

[, $rows, $err2] = tmm_import_read_csv($tmp);
if ($err2) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $err2]);
  exit;
}

$submittedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
if ($submittedByName === '') $submittedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
if ($submittedByName === '') $submittedByName = 'Admin';
if ($submittedByName !== '' && strpos($submittedByName, ' ') !== false) {
  $parts = preg_split('/\s+/', $submittedByName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  if ($parts) $submittedByName = (string)$parts[0];
}

$now = date('Y-m-d H:i:s');
$allowedTypes = ['Individual','Cooperative','Corporation'];
$allowedStatus = ['Pending','Approved','Inactive'];

$sql = "INSERT INTO operators (full_name, contact_info, operator_type, registered_name, name, address, contact_no, email, status, verification_status, workflow_status, updated_at, submitted_by_name, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', 'Draft', ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          contact_info=VALUES(contact_info),
          operator_type=VALUES(operator_type),
          registered_name=VALUES(registered_name),
          name=VALUES(name),
          address=VALUES(address),
          contact_no=VALUES(contact_no),
          email=VALUES(email),
          status=VALUES(status),
          updated_at=VALUES(updated_at),
          submitted_by_name=COALESCE(NULLIF(submitted_by_name,''), VALUES(submitted_by_name)),
          submitted_at=COALESCE(submitted_at, VALUES(submitted_at))";

$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}

$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];

$db->begin_transaction();
try {
  foreach ($rows as $idx => $r) {
    $name = trim((string)($r['name'] ?? ''));
    if ($name === '') $name = trim((string)($r['full_name'] ?? ''));
    if ($name === '') $name = trim((string)($r['display_name'] ?? ''));
    if ($name === '') { $skipped++; continue; }

    $operatorType = trim((string)($r['operator_type'] ?? 'Individual'));
    if (!in_array($operatorType, $allowedTypes, true)) $operatorType = 'Individual';

    $address = trim((string)($r['address'] ?? ''));
    $contactNo = trim((string)($r['contact_no'] ?? ''));
    $email = trim((string)($r['email'] ?? ''));
    if ($email !== '' && !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
      $errors[] = ['row' => $idx + 2, 'error' => 'invalid_email', 'value' => $email];
      $skipped++;
      continue;
    }

    $status = trim((string)($r['status'] ?? 'Pending'));
    if (!in_array($status, $allowedStatus, true)) $status = 'Pending';
    if (strcasecmp($status, 'Inactive') === 0) $status = 'Inactive';
    else $status = 'Pending';

    $contactInfo = trim(($contactNo !== '' ? $contactNo : '') . (($contactNo !== '' && $email !== '') ? ' / ' : '') . ($email !== '' ? $email : ''));

    $stmt->bind_param(
      'ssssssssssss',
      $name,
      $contactInfo,
      $operatorType,
      $name,
      $name,
      $address,
      $contactNo,
      $email,
      $status,
      $now,
      $submittedByName,
      $now
    );

    $ok = $stmt->execute();
    if (!$ok) {
      $errors[] = ['row' => $idx + 2, 'error' => 'save_failed'];
      $skipped++;
      continue;
    }
    $aff = (int)$stmt->affected_rows;
    if ($aff >= 2) $updated++;
    else $inserted++;
  }
  $db->commit();
} catch (Throwable $e) {
  $db->rollback();
  $stmt->close();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'import_failed']);
  exit;
}

$stmt->close();
echo json_encode([
  'ok' => true,
  'inserted' => $inserted,
  'updated' => $updated,
  'skipped' => $skipped,
  'errors' => $errors
]);

