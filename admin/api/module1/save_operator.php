<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.write','module1.vehicles.write']);

$operatorType = trim((string)($_POST['operator_type'] ?? ''));
$name = trim((string)($_POST['name'] ?? ($_POST['full_name'] ?? '')));
$contactNo = trim((string)($_POST['contact_no'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$addrStreet = trim((string)($_POST['address_street'] ?? ''));
$addrBarangay = trim((string)($_POST['address_barangay'] ?? ''));
$addrCity = trim((string)($_POST['address_city'] ?? ''));
$addrProvince = trim((string)($_POST['address_province'] ?? ''));
$addrPostal = preg_replace('/[^0-9A-Za-z\-]/', '', trim((string)($_POST['address_postal_code'] ?? '')));
$addrPostal = substr($addrPostal, 0, 10);
$address = trim((string)($_POST['address'] ?? ''));
$addressProvided = array_key_exists('address', $_POST);
$hasAddrParts = ($addrStreet !== '' || $addrBarangay !== '' || $addrCity !== '' || $addrProvince !== '' || $addrPostal !== '');
if (!$addressProvided && $hasAddrParts) {
    $parts = array_values(array_filter([$addrStreet, $addrBarangay, $addrCity, $addrProvince], fn($x) => $x !== ''));
    $address = $parts ? implode(', ', $parts) : '';
    if ($addrPostal !== '') $address = trim($address . ' ' . $addrPostal);
}
$status = trim((string)($_POST['status'] ?? ''));
$contactLegacy = trim((string)($_POST['contact_info'] ?? ''));
$_workflow = trim((string)($_POST['workflow_status'] ?? ''));
$assisted = (int)($_POST['assisted'] ?? 0) === 1;

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
if (strcasecmp($status, 'Inactive') === 0) $status = 'Inactive';
else $status = 'Pending';

if ($contactNo === '' && $email === '' && $contactLegacy !== '') {
    if (strpos($contactLegacy, '@') !== false) $email = $contactLegacy;
    else $contactNo = $contactLegacy;
}
$contactNo = preg_replace('/\D+/', '', $contactNo);
$contactNo = substr($contactNo, 0, 20);
if ($email !== '' && !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_email']);
    exit;
}

$now = date('Y-m-d H:i:s');
$verificationStatus = 'Draft';
$workflowStatus = 'Draft';
$submittedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
if ($submittedByName === '') $submittedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
if ($submittedByName === '') $submittedByName = 'Admin';
if ($submittedByName !== '' && strpos($submittedByName, ' ') !== false) {
    $parts = preg_split('/\s+/', $submittedByName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts) $submittedByName = (string)$parts[0];
}

$stmt = $db->prepare("INSERT INTO operators (full_name, contact_info, operator_type, registered_name, name, address, address_street, address_barangay, address_city, address_province, address_postal_code, contact_no, email, status, verification_status, workflow_status, updated_at, submitted_by_name, submitted_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                        contact_info=VALUES(contact_info),
                        operator_type=VALUES(operator_type),
                        registered_name=VALUES(registered_name),
                        name=VALUES(name),
                        address=VALUES(address),
                        address_street=VALUES(address_street),
                        address_barangay=VALUES(address_barangay),
                        address_city=VALUES(address_city),
                        address_province=VALUES(address_province),
                        address_postal_code=VALUES(address_postal_code),
                        contact_no=VALUES(contact_no),
                        email=VALUES(email),
                        status=VALUES(status),
                        verification_status=IF(verification_status='Inactive','Inactive',verification_status),
                        workflow_status=IF(workflow_status='Inactive','Inactive',workflow_status),
                        updated_at=VALUES(updated_at),
                        submitted_by_name=COALESCE(NULLIF(submitted_by_name,''), VALUES(submitted_by_name)),
                        submitted_at=COALESCE(submitted_at, VALUES(submitted_at))");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}
$contactInfo = trim(($contactNo !== '' ? $contactNo : '') . (($contactNo !== '' && $email !== '') ? ' / ' : '') . ($email !== '' ? $email : ''));
$submitNameBind = $assisted ? $submittedByName : null;
$submitAtBind = $assisted ? $now : null;
$stmt->bind_param('sssssssssssssssssss', $name, $contactInfo, $operatorType, $name, $name, $address, $addrStreet, $addrBarangay, $addrCity, $addrProvince, $addrPostal, $contactNo, $email, $status, $verificationStatus, $workflowStatus, $now, $submitNameBind, $submitAtBind);
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
    if ($assisted) {
        require_once __DIR__ . '/../../includes/util.php';
        tmm_audit_event($db, 'PUV_ASSISTED_OPERATOR_ENCODED', 'Operator', (string)$id, ['assisted' => true]);
    }
    echo json_encode(['ok' => true, 'operator_id' => $id, 'assisted' => $assisted]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'save_failed']);
}
?>
