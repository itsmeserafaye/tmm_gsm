<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.write','module1.vehicles.write']);

$operatorType = trim((string)($_POST['operator_type'] ?? ''));
$name = trim((string)($_POST['name'] ?? ($_POST['full_name'] ?? '')));
$address = trim((string)($_POST['address'] ?? ''));
$contactNo = trim((string)($_POST['contact_no'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$status = trim((string)($_POST['status'] ?? ''));
$contactLegacy = trim((string)($_POST['contact_info'] ?? ''));

if ($name === '' || strlen($name) < 3) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_name']);
    exit;
}

if ($operatorType === '') $operatorType = 'Individual';
$allowedTypes = ['Individual','Cooperative','Corporation'];
$typeOk = false;
foreach ($allowedTypes as $t) {
    if (strcasecmp($operatorType, $t) === 0) { $operatorType = $t; $typeOk = true; break; }
}
if (!$typeOk) $operatorType = 'Individual';

if ($status === '') $status = 'Pending';
$allowedStatus = ['Pending','Approved','Inactive'];
$statusOk = false;
foreach ($allowedStatus as $s) {
    if (strcasecmp($status, $s) === 0) { $status = $s; $statusOk = true; break; }
}
if (!$statusOk) $status = 'Pending';

if ($contactNo === '' && $email === '' && $contactLegacy !== '') {
    if (strpos($contactLegacy, '@') !== false) $email = $contactLegacy;
    else $contactNo = $contactLegacy;
}
if ($email !== '' && !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_email']);
    exit;
}

$now = date('Y-m-d H:i:s');
$verificationStatus = 'Draft';
$stmt = $db->prepare("INSERT INTO operators (full_name, contact_info, operator_type, registered_name, name, address, contact_no, email, status, verification_status, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                        contact_info=VALUES(contact_info),
                        operator_type=VALUES(operator_type),
                        registered_name=VALUES(registered_name),
                        name=VALUES(name),
                        address=VALUES(address),
                        contact_no=VALUES(contact_no),
                        email=VALUES(email),
                        status=VALUES(status),
                        verification_status=IF(verification_status='Inactive','Inactive',verification_status),
                        updated_at=VALUES(updated_at)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}
$contactInfo = trim(($contactNo !== '' ? $contactNo : '') . (($contactNo !== '' && $email !== '') ? ' / ' : '') . ($email !== '' ? $email : ''));
$stmt->bind_param('sssssssssss', $name, $contactInfo, $operatorType, $name, $name, $address, $contactNo, $email, $status, $verificationStatus, $now);
if ($stmt->execute()) {
    $id = (int)($db->insert_id ?: ($stmt->insert_id ?? 0));
    if ($id <= 0) {
        $stmt2 = $db->prepare("SELECT id FROM operators WHERE full_name=? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param('s', $name);
            $stmt2->execute();
            $row = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            $id = (int)($row['id'] ?? 0);
        }
    }
    echo json_encode(['ok' => true, 'operator_id' => $id]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'save_failed']);
}
?>
