<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
$db = db();

header('Content-Type: application/json');
require_permission('tickets.issue');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// 1. Get Input
$violation_code = $db->real_escape_string($_POST['violation_code'] ?? '');
$plate_number = $db->real_escape_string($_POST['plate_number'] ?? '');
$driver_name = $db->real_escape_string($_POST['driver_name'] ?? '');
$location = $db->real_escape_string($_POST['location'] ?? '');
$notes = $db->real_escape_string($_POST['notes'] ?? '');
$issued_at = $_POST['issued_at'] ?? date('Y-m-d H:i:s');
$issued_by = 'Officer Admin';
$issued_by_badge = null;
$officer_name_input = trim($_POST['officer_name'] ?? '');
$ticket_source = strtoupper(trim((string)($_POST['ticket_source'] ?? 'LOCAL_STS_COMPAT')));
$external_ticket_number = trim((string)($_POST['external_ticket_number'] ?? ''));

$allowedSources = ['LOCAL_STS_COMPAT','STS_PAPER','STS_EXTERNAL'];
if (!in_array($ticket_source, $allowedSources, true)) $ticket_source = 'LOCAL_STS_COMPAT';
if ($external_ticket_number !== '') {
    $external_ticket_number = preg_replace('/\s+/', '', $external_ticket_number);
    if (!preg_match('/^[A-Za-z0-9\-\/]{3,64}$/', $external_ticket_number)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid STS ticket number format']);
        exit;
    }
}
if (($ticket_source === 'STS_PAPER' || $ticket_source === 'STS_EXTERNAL') && $external_ticket_number === '') {
    echo json_encode(['ok' => false, 'error' => 'STS ticket number is required for paper/external tickets']);
    exit;
}

if ($officer_name_input !== '') {
    $issued_by = $db->real_escape_string($officer_name_input);
}

if (!$violation_code || !$plate_number) {
    echo json_encode(['ok' => false, 'error' => 'Violation Code and Plate Number are required']);
    exit;
}

// 2. Validate against PUV/Franchise Database
$franchise_id = null;
$coop_id = null;
$status = 'Pending'; // Default to Pending (requires manual validation)

// Check Vehicle
$veh_check = $db->query("SELECT * FROM vehicles WHERE plate_number = '$plate_number' LIMIT 1");
if ($veh_check && $veh_check->num_rows > 0) {
    $veh = $veh_check->fetch_assoc();
    $franchise_id = $veh['franchise_id'];
    $coop_name = $veh['coop_name'];
    
    // Check Coop ID
    if ($coop_name) {
        $coop_res = $db->query("SELECT id FROM coops WHERE coop_name = '$coop_name' LIMIT 1");
        if ($coop_res && $coop_res->num_rows > 0) {
            $coop_id = $coop_res->fetch_assoc()['id'];
        }
    }
    
    // If found in PUV DB, auto-validate
    $status = 'Validated';
}

// 3. Get Fine Amount
$fine = 0.00;
$sts_violation_code = null;
$v_res = $db->query("SELECT fine_amount, sts_equivalent_code FROM violation_types WHERE violation_code = '$violation_code' LIMIT 1");
if ($v_res && $v_res->num_rows > 0) {
    $vr = $v_res->fetch_assoc();
    $fine = (float)($vr['fine_amount'] ?? 0);
    $sts_violation_code = $vr['sts_equivalent_code'] ?? null;
}
if (!$sts_violation_code || trim((string)$sts_violation_code) === '') $sts_violation_code = $violation_code;

// 4. Generate Ticket Number
$year = date('Y');
$month = date('m');
$count_res = $db->query("SELECT COUNT(*) as c FROM tickets WHERE YEAR(date_issued) = $year");
$count = $count_res->fetch_assoc()['c'] + 1;
$ticket_number = sprintf("TCK-%s-%s-%04d", $year, $month, $count);

// 5. Check Escalation Rule (Repeat Offenders)
if ($franchise_id) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $esc_check = $db->query("SELECT COUNT(*) as c FROM tickets WHERE franchise_id = '$franchise_id' AND date_issued >= '$start_date'");
    $violation_count = $esc_check->fetch_assoc()['c'];
    
    if ($violation_count >= 5) { // 5 prior + this one = >5
        $status = 'Escalated';
        // Automatic Compliance Case Creation
        $case_desc = "Automatic escalation: Franchise accumulated >5 tickets in 30 days.";
        $c_stmt = $db->prepare("INSERT INTO compliance_cases (franchise_ref_number, violation_type, status, violation_details) VALUES (?, 'Traffic Violation Escalation', 'Open', ?)");
        if ($c_stmt) {
            $c_stmt->bind_param('ss', $franchise_id, $case_desc);
            $c_stmt->execute();
        }
    }
}

// 6. Insert Ticket
$issued_by_sql = $db->real_escape_string($issued_by);
$issued_by_badge_sql = $issued_by_badge !== null ? "'" . $issued_by_badge . "'" : "NULL";
$extSql = $external_ticket_number !== '' ? "'" . $db->real_escape_string($external_ticket_number) . "'" : "NULL";
$srcSql = $db->real_escape_string($ticket_source);
$stsSql = $db->real_escape_string((string)$sts_violation_code);

$sql = "INSERT INTO tickets (ticket_number, violation_code, sts_violation_code, external_ticket_number, ticket_source, vehicle_plate, franchise_id, coop_id, driver_name, location, fine_amount, date_issued, issued_by, issued_by_badge, status) 
        VALUES ('$ticket_number', '$violation_code', '$stsSql', $extSql, '$srcSql', '$plate_number', " . ($franchise_id ? "'$franchise_id'" : "NULL") . ", " . ($coop_id ? "$coop_id" : "NULL") . ", '$driver_name', '$location', $fine, '$issued_at', '$issued_by_sql', $issued_by_badge_sql, '$status')";

if ($db->query($sql)) {
    $ticket_id = $db->insert_id;
    
    // 7. Handle File Uploads
    $uploadDir = __DIR__ . '/../../uploads/evidence/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $uploaded_files = [];
    foreach (['photo', 'video'] as $type) {
        if (isset($_FILES[$type]) && $_FILES[$type]['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES[$type]['tmp_name'];

            // Basic server-side type check to prevent non-photos/videos being saved
            $mime = @mime_content_type($tmp);
            if ($type === 'photo') {
                if ($mime && strpos($mime, 'image/') !== 0) {
                    continue;
                }
            } else {
                if ($mime && strpos($mime, 'video/') !== 0) {
                    continue;
                }
            }

            $name = time() . '_' . basename($_FILES[$type]['name']);
            $target = $uploadDir . $name;
            
            if (!move_uploaded_file($tmp, $target)) {
                continue;
            }

            $safe = tmm_scan_file_for_viruses($target);
            if (!$safe) {
                if (is_file($target)) { @unlink($target); }
                continue;
            }

            $db_path = 'uploads/evidence/' . $name;
            $stmt = $db->prepare("INSERT INTO evidence (ticket_id, file_path, file_type) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $ticket_id, $db_path, $type);
            $stmt->execute();
            $uploaded_files[] = $name;
        }
    }
    
    echo json_encode([
        'ok' => true, 
        'message' => 'Ticket generated successfully',
        'ticket_number' => $ticket_number,
        'external_ticket_number' => $external_ticket_number,
        'ticket_source' => $ticket_source,
        'fine' => $fine
    ]);
} else {
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
?>
