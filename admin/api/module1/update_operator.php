<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');

require_any_permission(['module1.write', 'module1.vehicles.write']);

$operatorId = isset($_POST['operator_id']) ? (int) $_POST['operator_id'] : 0;
$name = trim((string) ($_POST['name'] ?? ''));
$operatorType = trim((string) ($_POST['operator_type'] ?? 'Individual'));
$address = trim((string) ($_POST['address'] ?? ''));
$contactNo = trim((string) ($_POST['contact_no'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

if ($operatorId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
    exit;
}

if ($name === '' || mb_strlen($name) < 3 || mb_strlen($name) > 120) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_name']);
    exit;
}

$allowedTypes = ['Individual', 'Cooperative', 'Corporation'];
if (!in_array($operatorType, $allowedTypes, true)) {
    $operatorType = 'Individual';
}

$stmt = $db->prepare("UPDATE operators SET operator_type=?, registered_name=?, name=?, full_name=?, address=?, contact_no=?, email=?, updated_at=NOW() WHERE id=?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}
$stmt->bind_param('sssssssi', $operatorType, $name, $name, $name, $address, $contactNo, $email, $operatorId);
$ok = $stmt->execute();
$errno = (int) ($stmt->errno ?? 0);
$stmt->close();

if (!$ok) {
    if ($errno === 1062) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'duplicate_name']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_update_failed']);
    exit;
}

$submittedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
if ($submittedByName === '') $submittedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
if ($submittedByName === '') $submittedByName = 'Admin';
if ($submittedByName !== '' && strpos($submittedByName, ' ') !== false) {
    $parts = preg_split('/\s+/', $submittedByName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts) $submittedByName = (string)$parts[0];
}
$stmtS = $db->prepare("UPDATE operators
                       SET submitted_by_name=COALESCE(NULLIF(submitted_by_name,''), ?),
                           submitted_at=COALESCE(submitted_at, NOW())
                       WHERE id=?
                         AND COALESCE(portal_user_id, 0)=0");
if ($stmtS) {
    $stmtS->bind_param('si', $submittedByName, $operatorId);
    $stmtS->execute();
    $stmtS->close();
}

echo json_encode(['ok' => true, 'operator_id' => $operatorId]);
