<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$permitId = $_POST['permit_id'] ?? 0;
$status = $_POST['status'] ?? '';
$expiry = $_POST['expiry_date'] ?? null;
$conditions = $_POST['conditions'] ?? '';
$feeAmount = isset($_POST['fee_amount']) ? (float)$_POST['fee_amount'] : null;
$payReceipt = $_POST['payment_receipt'] ?? null;
$payVerifiedParam = isset($_POST['payment_verified']) ? (int)$_POST['payment_verified'] : null;

if (!$permitId || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

$cur = null;
$check = $db->prepare("SELECT status, payment_verified, terminal_id FROM terminal_permits WHERE id=?");
$check->bind_param('i', $permitId);
$check->execute();
$cur = $check->get_result()->fetch_assoc();

if (!$cur) {
    echo json_encode(['success' => false, 'message' => 'Permit not found']);
    exit;
}

if ($status === 'Active') {
    $isVerified = (int)($cur['payment_verified'] ?? 0);
    if ($payVerifiedParam !== null) {
        $isVerified = $payVerifiedParam;
    }
    if ($isVerified !== 1) {
        echo json_encode(['success' => false, 'message' => 'payment_required']);
        exit;
    }
    if (!$expiry) {
        echo json_encode(['success' => false, 'message' => 'expiry_required']);
        exit;
    }
    $terminalId = (int)($cur['terminal_id'] ?? 0);
    if ($terminalId > 0) {
        $stmtTN = $db->prepare("SELECT name FROM terminals WHERE id=?");
        $stmtTN->bind_param('i', $terminalId);
        $stmtTN->execute();
        $trow = $stmtTN->get_result()->fetch_assoc();
        if ($trow) {
            $terminalName = $trow['name'] ?? '';
            if ($terminalName !== '') {
                $stmtRoutes = $db->prepare("SELECT route_id FROM terminal_assignments WHERE terminal_name=? GROUP BY route_id");
                $stmtRoutes->bind_param('s', $terminalName);
                $stmtRoutes->execute();
                $resRoutes = $stmtRoutes->get_result();
                $oversupply = false;
                while ($rr = $resRoutes->fetch_assoc()) {
                    $rid = trim($rr['route_id'] ?? '');
                    if ($rid === '') continue;
                    $cstmt = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=?");
                    $cstmt->bind_param('s', $rid);
                    $cstmt->execute();
                    $assignedCnt = (int)($cstmt->get_result()->fetch_assoc()['c'] ?? 0);
                    $rstmt = $db->prepare("SELECT max_vehicle_limit FROM routes WHERE route_id=?");
                    $rstmt->bind_param('s', $rid);
                    $rstmt->execute();
                    $rrow = $rstmt->get_result()->fetch_assoc();
                    $staticCap = (int)($rrow['max_vehicle_limit'] ?? 0);
                    $limit = $staticCap > 0 ? $staticCap : -1;

                    $dstmt = $db->prepare("SELECT cap FROM route_cap_schedule WHERE route_id=? AND ts<=NOW() ORDER BY ts DESC LIMIT 1");
                    $dstmt->bind_param('s', $rid);
                    $dstmt->execute();
                    $drow = $dstmt->get_result()->fetch_assoc();

                    if ($drow) {
                        $dcap = isset($drow['cap']) ? (int)$drow['cap'] : -1;
                        if ($dcap >= 0) {
                            if ($limit === -1 || $dcap < $limit) { $limit = $dcap; }
                        }
                    }
                    if ($limit !== -1 && $assignedCnt >= $limit) { $oversupply = true; break; }
                }
                if ($oversupply) {
                    echo json_encode(['success' => false, 'message' => 'oversupply_flagged']);
                    exit;
                }
            }
        }
    }
}

$sql = "UPDATE terminal_permits SET status=?, conditions=?";
$types = 'ss';
$params = [$status, $conditions];

if ($expiry) {
    $sql .= ", expiry_date=?, issue_date=CURRENT_DATE";
    $types .= 's';
    $params[] = $expiry;
}

$setExtra = [];
if ($feeAmount !== null) {
    $sql .= ", fee_amount=?";
    $types .= 'd';
    $params[] = $feeAmount;
}
if ($payReceipt !== null) {
    $sql .= ", payment_receipt=?";
    $types .= 's';
    $params[] = $payReceipt;
}
if ($payVerifiedParam !== null) {
    $sql .= ", payment_verified=?";
    $types .= 'i';
    $params[] = $payVerifiedParam;
}

$sql .= " WHERE id=?";
$types .= 'i';
$params[] = $permitId;

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $db->error]);
}
?>
