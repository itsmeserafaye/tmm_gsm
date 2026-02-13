<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');

require_any_permission(['module1.write', 'module1.vehicles.write']);

$operatorId = isset($_POST['operator_id']) ? (int) $_POST['operator_id'] : 0;

if ($operatorId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
    exit;
}

$sets = [];
$params = [];
$types = '';

$hasKey = function (string $k): bool {
    return array_key_exists($k, $_POST);
};

if ($hasKey('operator_type')) {
    $operatorType = trim((string) ($_POST['operator_type'] ?? 'Individual'));
    $allowedTypes = ['Individual', 'Cooperative', 'Corporation'];
    if (!in_array($operatorType, $allowedTypes, true)) {
        $operatorType = 'Individual';
    }
    $sets[] = "operator_type=?";
    $params[] = $operatorType;
    $types .= 's';
}

if ($hasKey('name')) {
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '' || mb_strlen($name) < 3 || mb_strlen($name) > 120) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_name']);
        exit;
    }
    $sets[] = "registered_name=?";
    $params[] = $name;
    $types .= 's';
    $sets[] = "name=?";
    $params[] = $name;
    $types .= 's';
    $sets[] = "full_name=?";
    $params[] = $name;
    $types .= 's';
}

if ($hasKey('contact_no')) {
    $contactNoRaw = (string) ($_POST['contact_no'] ?? '');
    $contactNo = preg_replace('/\D+/', '', trim($contactNoRaw));
    $contactNo = substr($contactNo, 0, 20);
    $sets[] = "contact_no=?";
    $params[] = $contactNo;
    $types .= 's';
}

if ($hasKey('email')) {
    $email = trim((string) ($_POST['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_email']);
        exit;
    }
    $sets[] = "email=?";
    $params[] = $email;
    $types .= 's';
}

$addrKeys = ['address_street', 'address_barangay', 'address_city', 'address_province', 'address_postal_code'];
$hasAddrParts = false;
foreach ($addrKeys as $k) {
    if ($hasKey($k)) {
        $hasAddrParts = true;
        break;
    }
}

$legacyAddressProvided = $hasKey('address');
if ($legacyAddressProvided) {
    $address = trim((string) ($_POST['address'] ?? ''));
    $sets[] = "address=?";
    $params[] = $address;
    $types .= 's';
}

if ($hasAddrParts) {
    $street = trim((string) ($_POST['address_street'] ?? ''));
    $brgy = trim((string) ($_POST['address_barangay'] ?? ''));
    $city = trim((string) ($_POST['address_city'] ?? ''));
    $prov = trim((string) ($_POST['address_province'] ?? ''));
    $postal = preg_replace('/[^0-9A-Za-z\-]/', '', trim((string) ($_POST['address_postal_code'] ?? '')));
    $postal = substr($postal, 0, 10);

    $sets[] = "address_street=?";
    $params[] = $street;
    $types .= 's';
    $sets[] = "address_barangay=?";
    $params[] = $brgy;
    $types .= 's';
    $sets[] = "address_city=?";
    $params[] = $city;
    $types .= 's';
    $sets[] = "address_province=?";
    $params[] = $prov;
    $types .= 's';
    $sets[] = "address_postal_code=?";
    $params[] = $postal;
    $types .= 's';

    if (!$legacyAddressProvided) {
        $parts = array_values(array_filter([$street, $brgy, $city, $prov], fn($x) => $x !== ''));
        $addrLine = $parts ? implode(', ', $parts) : '';
        if ($postal !== '') $addrLine = trim($addrLine . ' ' . $postal);
        $sets[] = "address=?";
        $params[] = $addrLine;
        $types .= 's';
    }
}

if (!$sets) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'nothing_to_update']);
    exit;
}

$sql = "UPDATE operators SET " . implode(", ", $sets) . ", updated_at=NOW() WHERE id=?";
$params[] = $operatorId;
$types .= 'i';

$stmt = $db->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}
$stmt->bind_param($types, ...$params);
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
