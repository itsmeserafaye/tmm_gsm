<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.manage_terminal');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$terminalId = isset($_POST['terminal_id']) ? (int)$_POST['terminal_id'] : 0;
if ($terminalId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Terminal ID is required']);
    exit;
}

$contractId = isset($_POST['contract_id']) ? (int)$_POST['contract_id'] : 0;
$ownerName = trim((string)($_POST['owner_name'] ?? ''));

if ($ownerName === '') {
    echo json_encode(['success' => false, 'message' => 'Owner Name is required']);
    exit;
}

// Upload Handling
$uploadDir = __DIR__ . '/../../uploads/contracts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
$fileFields = ['moa_file', 'contract_file', 'permit_file'];
$newFiles = [];

foreach ($fileFields as $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES[$field];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExt)) {
            $filename = 'TERM' . $terminalId . '_' . strtoupper($field) . '_' . time() . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], $uploadDir . $filename)) {
                $newFiles[$field . '_url'] = 'uploads/contracts/' . $filename;
            }
        }
    }
}

// Variables
$ownerType = $_POST['owner_type'] ?? 'Other';
$ownerContact = $_POST['owner_contact'] ?? '';
$agreementType = $_POST['agreement_type'] ?? 'Other';
$agreementRef = $_POST['agreement_reference_no'] ?? '';
$rentAmount = (float)($_POST['rent_amount'] ?? 0);
$rentFrequency = $_POST['rent_frequency'] ?? 'Monthly';
$termsSummary = $_POST['terms_summary'] ?? '';
$startDate = empty($_POST['start_date']) ? null : $_POST['start_date'];
$endDate = empty($_POST['end_date']) ? null : $_POST['end_date'];
$permitType = $_POST['permit_type'] ?? 'Other';
$permitNumber = $_POST['permit_number'] ?? '';
$permitValidUntil = empty($_POST['permit_valid_until']) ? null : $_POST['permit_valid_until'];
$status = $_POST['status'] ?? 'Active';

$otherJson = '[]'; // Default

if ($contractId > 0) {
    // Update
    $stmt = $db->prepare("SELECT moa_file_url, contract_file_url, permit_file_url, other_attachments FROM terminal_contracts WHERE id=?");
    $stmt->bind_param('i', $contractId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    $moaUrl = $newFiles['moa_file_url'] ?? ($existing['moa_file_url'] ?? null);
    $contractUrl = $newFiles['contract_file_url'] ?? ($existing['contract_file_url'] ?? null);
    $permitUrl = $newFiles['permit_file_url'] ?? ($existing['permit_file_url'] ?? null);
    // TODO: Handle appending other attachments properly
    $otherJson = $existing['other_attachments'] ?? '[]'; 

    $upd = $db->prepare("UPDATE terminal_contracts SET 
        owner_type=?, owner_name=?, owner_contact=?, 
        agreement_type=?, agreement_reference_no=?, rent_amount=?, rent_frequency=?, 
        terms_summary=?, start_date=?, end_date=?, 
        permit_type=?, permit_number=?, permit_valid_until=?, 
        moa_file_url=?, contract_file_url=?, permit_file_url=?, other_attachments=?, 
        status=? 
        WHERE id=?");
    
    $upd->bind_param('sssssdssssssssssssi', 
        $ownerType, $ownerName, $ownerContact,
        $agreementType, $agreementRef, $rentAmount, $rentFrequency,
        $termsSummary, $startDate, $endDate,
        $permitType, $permitNumber, $permitValidUntil,
        $moaUrl, $contractUrl, $permitUrl, $otherJson,
        $status, $contractId
    );
    
    if ($upd->execute()) {
        echo json_encode(['success' => true, 'id' => $contractId]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $db->error]);
    }

} else {
    // Insert
    $moaUrl = $newFiles['moa_file_url'] ?? null;
    $contractUrl = $newFiles['contract_file_url'] ?? null;
    $permitUrl = $newFiles['permit_file_url'] ?? null;
    
    $ins = $db->prepare("INSERT INTO terminal_contracts (
        terminal_id, owner_type, owner_name, owner_contact, 
        agreement_type, agreement_reference_no, rent_amount, rent_frequency, 
        terms_summary, start_date, end_date, 
        permit_type, permit_number, permit_valid_until, 
        moa_file_url, contract_file_url, permit_file_url, other_attachments, 
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $ins->bind_param('isssssdssssssssssss', 
        $terminalId, $ownerType, $ownerName, $ownerContact,
        $agreementType, $agreementRef, $rentAmount, $rentFrequency,
        $termsSummary, $startDate, $endDate,
        $permitType, $permitNumber, $permitValidUntil,
        $moaUrl, $contractUrl, $permitUrl, $otherJson,
        $status
    );

    if ($ins->execute()) {
        echo json_encode(['success' => true, 'id' => $ins->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $db->error]);
    }
}
?>
