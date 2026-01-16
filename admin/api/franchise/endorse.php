<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

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
    
    // Check if already endorsed
    // (Optional: Allow re-issuance? For now, let's assume one endorsement per app)
    // $check = $db->query("SELECT * FROM endorsement_records WHERE application_id = $realAppId");
    // if ($check->num_rows > 0) { ... }

    // Create Endorsement Record
    $stmtIns = $db->prepare("INSERT INTO endorsement_records (application_id, permit_number, issued_date) VALUES (?, ?, CURDATE())");
    $stmtIns->bind_param('is', $realAppId, $permitNo);
    
    if ($stmtIns->execute()) {
        $endorsementId = $db->insert_id;
        
        // Update Application Status
        $db->query("UPDATE franchise_applications SET status = 'Endorsed' WHERE application_id = $realAppId");
        
        echo json_encode(['ok' => true, 'endorsement_id' => $endorsementId, 'message' => 'Endorsement generated successfully']);
    } else {
        echo json_encode(['error' => 'Failed to create endorsement record']);
    }
} else {
    echo json_encode(['error' => 'Application not found']);
}
?>
