<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$appId = $_POST['app_id'] ?? '';
$permitNo = trim($_POST['permit_no'] ?? '');
$issueType = $_POST['issue_type'] ?? 'Endorsement';

if (empty($appId) || empty($permitNo)) {
    echo json_encode(['error' => 'Application ID and Permit Number are required']);
    exit;
}

// Verify Application exists
$stmt = $db->prepare("SELECT application_id, status FROM franchise_applications WHERE application_id = ? OR franchise_ref_number = ?");
$stmt->bind_param('ss', $appId, $appId);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $realAppId = $row['application_id'];
    $currentStatus = (string)($row['status'] ?? '');
    
    if (strcasecmp($currentStatus, 'Endorsed') === 0) {
        $stmtEx = $db->prepare("SELECT endorsement_id, permit_number FROM endorsement_records WHERE application_id=? ORDER BY endorsement_id DESC LIMIT 1");
        $endorsementId = 0;
        $permit = '';
        if ($stmtEx) {
            $stmtEx->bind_param('i', $realAppId);
            $stmtEx->execute();
            $ex = $stmtEx->get_result()->fetch_assoc();
            $stmtEx->close();
            if ($ex) {
                $endorsementId = (int)($ex['endorsement_id'] ?? 0);
                $permit = (string)($ex['permit_number'] ?? '');
            }
        }
        echo json_encode(['ok' => true, 'endorsement_id' => $endorsementId, 'permit_no' => $permit, 'message' => 'Application already endorsed']);
        exit;
    }

    // Create Endorsement Record
    $db->begin_transaction();
    $stmtIns = $db->prepare("INSERT INTO endorsement_records (application_id, permit_number, issued_date)
                             VALUES (?, ?, CURDATE())
                             ON DUPLICATE KEY UPDATE issued_date=issued_date");
    $stmtIns->bind_param('is', $realAppId, $permitNo);
    
    if ($stmtIns->execute()) {
        $endorsementId = (int)$db->insert_id;
        if ($endorsementId <= 0) {
            $stmtGet = $db->prepare("SELECT endorsement_id FROM endorsement_records WHERE application_id=? ORDER BY endorsement_id DESC LIMIT 1");
            if ($stmtGet) {
                $stmtGet->bind_param('i', $realAppId);
                $stmtGet->execute();
                $er = $stmtGet->get_result()->fetch_assoc();
                $stmtGet->close();
                $endorsementId = (int)($er['endorsement_id'] ?? 0);
            }
        }
        
        // Update Application Status
        $db->query("UPDATE franchise_applications SET status = 'Endorsed' WHERE application_id = $realAppId");
        $db->commit();
        
        echo json_encode(['ok' => true, 'endorsement_id' => $endorsementId, 'message' => 'Endorsement generated successfully']);
    } else {
        $db->rollback();
        echo json_encode(['error' => 'Failed to create endorsement record']);
    }
} else {
    echo json_encode(['error' => 'Application not found']);
}
?>
