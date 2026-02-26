<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

// 1. Terminal Info
$stmt = $db->prepare("SELECT * FROM terminals WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$terminal = $stmt->get_result()->fetch_assoc();
if (!$terminal) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

// 2. Active Agreement & Owner
// Fetch the latest active agreement, or just the latest one created if none active.
$sql = "SELECT 
            fa.*, 
            fo.name as owner_name, fo.type as owner_type, fo.contact_info as owner_contact 
        FROM facility_agreements fa 
        JOIN facility_owners fo ON fa.owner_id = fo.id 
        WHERE fa.terminal_id = ? 
        ORDER BY FIELD(fa.status, 'Active', 'Expiring Soon', 'Expired', 'Terminated'), fa.created_at DESC 
        LIMIT 1";
$stmtA = $db->prepare($sql);
$stmtA->bind_param('i', $id);
$stmtA->execute();
$agreement = $stmtA->get_result()->fetch_assoc();

// 3. Documents
// Fetch docs linked to this terminal (and optionally specific agreement)
$docs = [];
$sqlD = "SELECT * FROM facility_documents WHERE terminal_id = ? ORDER BY uploaded_at DESC";
$stmtD = $db->prepare($sqlD);
$stmtD->bind_param('i', $id);
$stmtD->execute();
$resD = $stmtD->get_result();
while ($r = $resD->fetch_assoc()) {
    $docs[] = $r;
}

// Compute duration string if agreement exists
if ($agreement) {
    $s = $agreement['start_date'];
    $e = $agreement['end_date'];
    if ($s && $e) {
        try {
            $d1 = new DateTime($s);
            $d2 = new DateTime($e);
            $diff = $d1->diff($d2);
            $months = ($diff->y * 12) + $diff->m;
            $days = $diff->d;
            $agreement['duration_computed'] = "$months months" . ($days > 0 ? ", $days days" : "");
        } catch (Exception $e) {
            $agreement['duration_computed'] = 'Invalid Dates';
        }
    } else {
        $agreement['duration_computed'] = 'N/A';
    }
}

echo json_encode([
    'success' => true,
    'terminal' => $terminal,
    'agreement' => $agreement,
    'documents' => $docs
]);
?>