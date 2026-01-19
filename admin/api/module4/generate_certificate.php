<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module4.inspections.manage');
$tmm_norm_plate = function (string $plate): string {
    $p = strtoupper(trim($plate));
    $p = preg_replace('/[^A-Z0-9]/', '', $p);
    return $p !== null ? $p : '';
};
$tmm_resolve_plate = function (mysqli $db, string $plate) use ($tmm_norm_plate): string {
    $clean = strtoupper(trim($plate));
    $norm = $tmm_norm_plate($clean);
    if ($norm === '') return $clean;
    $stmt = $db->prepare("SELECT plate_number FROM vehicles WHERE REPLACE(REPLACE(UPPER(plate_number), '-', ''), ' ', '') = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $norm);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && isset($row['plate_number']) && (string)$row['plate_number'] !== '') return (string)$row['plate_number'];
    }
    return $clean;
};
$schedule_id = (int)($_POST['schedule_id'] ?? 0);
$approved_by = isset($_POST['approved_by']) ? (int)$_POST['approved_by'] : 0;
$approved_name = trim($_POST['approved_name'] ?? '');
if ($schedule_id <= 0 || ($approved_by <= 0 && $approved_name === '')) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
$sch = $db->prepare("SELECT status FROM inspection_schedules WHERE schedule_id=?");
$sch->bind_param('i', $schedule_id);
$sch->execute();
$srow = $sch->get_result()->fetch_assoc();
if (!$srow || ($srow['status'] ?? '') !== 'Completed') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'schedule_not_completed']); exit; }
$approverId = 0;
if ($approved_by > 0) {
    $ap = $db->prepare("SELECT officer_id, active_status FROM officers WHERE officer_id=?");
    $ap->bind_param('i', $approved_by);
    $ap->execute();
    $apro = $ap->get_result()->fetch_assoc();
    if (!$apro || (int)$apro['active_status'] !== 1) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'approver_inactive']); exit; }
    $approverId = (int)$approved_by;
} else {
    $name = $approved_name;
    $stmtFind = $db->prepare("SELECT officer_id, active_status FROM officers WHERE name=? LIMIT 1");
    if ($stmtFind) {
        $stmtFind->bind_param('s', $name);
        $stmtFind->execute();
        $rowOff = $stmtFind->get_result()->fetch_assoc();
        if ($rowOff && (int)($rowOff['active_status'] ?? 0) === 1) {
            $approverId = (int)$rowOff['officer_id'];
        }
    }
    if ($approverId <= 0) {
        $insOff = $db->prepare("INSERT INTO officers(name, role, badge_no, station_id, active_status) VALUES(?, 'Inspector', NULL, NULL, 1)");
        if (!$insOff) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'approver_create_failed']); exit; }
        $insOff->bind_param('s', $name);
        if (!$insOff->execute()) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'approver_create_failed']); exit; }
        $approverId = (int)$db->insert_id;
    }
}
$existing = $db->prepare("SELECT cert_id, certificate_number FROM inspection_certificates WHERE schedule_id=?");
$existing->bind_param('i', $schedule_id);
$existing->execute();
$ex = $existing->get_result()->fetch_assoc();
if ($ex) {
    $plateStmt = $db->prepare("SELECT plate_number FROM inspection_schedules WHERE schedule_id=?");
    $plateStmt->bind_param('i', $schedule_id);
    $plateStmt->execute();
    $prow = $plateStmt->get_result()->fetch_assoc();
    $plate = '';
    if ($prow && ($prow['plate_number'] ?? '') !== '') {
        $plateOrig = (string)$prow['plate_number'];
        $plate = $tmm_resolve_plate($db, $plateOrig);
        if ($plateOrig !== '' && $plate !== '' && $plate !== $plateOrig) {
            $upSch = $db->prepare("UPDATE inspection_schedules SET plate_number=? WHERE schedule_id=?");
            if ($upSch) { $upSch->bind_param('si', $plate, $schedule_id); $upSch->execute(); $upSch->close(); }
        }
        $upVeh = $db->prepare("UPDATE vehicles SET inspection_status='Passed', inspection_cert_ref=? WHERE plate_number=?");
        $upVeh->bind_param('ss', $ex['certificate_number'], $plate);
        $upVeh->execute();
    }
    $qrPayload = '';
    $qrUrl = '';
    if ($plate !== '' && ($ex['certificate_number'] ?? '') !== '') {
        $qrPayload = 'CITY-INSPECTION|' . $plate . '|' . $ex['certificate_number'];
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($qrPayload);
    }
    echo json_encode([
        'ok' => true,
        'certificate_number' => $ex['certificate_number'],
        'cert_id' => $ex['cert_id'],
        'qr_payload' => $qrPayload,
        'qr_url' => $qrUrl
    ]);
    exit;
}
$rs = $db->prepare("SELECT result_id, overall_status FROM inspection_results WHERE schedule_id=? ORDER BY submitted_at DESC LIMIT 1");
$rs->bind_param('i', $schedule_id);
$rs->execute();
$res = $rs->get_result()->fetch_assoc();
if (!$res || ($res['overall_status'] ?? '') !== 'Passed') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'inspection_not_passed']); exit; }
$year = date('Y');
$tmp_num = 'CERT-' . $year . '-TMP';
$ins = $db->prepare("INSERT INTO inspection_certificates(certificate_number, schedule_id, approved_by) VALUES(?,?,?)");
$ins->bind_param('sii', $tmp_num, $schedule_id, $approverId);
$ok = $ins->execute();
if (!$ok) { echo json_encode(['ok'=>false,'error'=>'insert_failed']); exit; }
$cid = $db->insert_id;
$cert_no = 'CERT-' . $year . '-' . str_pad((string)$cid, 4, '0', STR_PAD_LEFT);
$up = $db->prepare("UPDATE inspection_certificates SET certificate_number=? WHERE cert_id=?");
$up->bind_param('si', $cert_no, $cid);
$up->execute();

$plateStmt2 = $db->prepare("SELECT plate_number FROM inspection_schedules WHERE schedule_id=?");
$plateStmt2->bind_param('i', $schedule_id);
$plateStmt2->execute();
$prow2 = $plateStmt2->get_result()->fetch_assoc();
$plate2 = '';
if ($prow2 && ($prow2['plate_number'] ?? '') !== '') {
    $plate2Orig = (string)$prow2['plate_number'];
    $plate2 = $tmm_resolve_plate($db, $plate2Orig);
    if ($plate2Orig !== '' && $plate2 !== '' && $plate2 !== $plate2Orig) {
        $upSch2 = $db->prepare("UPDATE inspection_schedules SET plate_number=? WHERE schedule_id=?");
        if ($upSch2) { $upSch2->bind_param('si', $plate2, $schedule_id); $upSch2->execute(); $upSch2->close(); }
    }
    $upVeh2 = $db->prepare("UPDATE vehicles SET inspection_status='Passed', inspection_cert_ref=? WHERE plate_number=?");
    $upVeh2->bind_param('ss', $cert_no, $plate2);
    $upVeh2->execute();
}
$qrPayload = '';
$qrUrl = '';
if ($plate2 !== '' && $cert_no !== '') {
    $qrPayload = 'CITY-INSPECTION|' . $plate2 . '|' . $cert_no;
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($qrPayload);
}
echo json_encode([
    'ok' => true,
    'certificate_number' => $cert_no,
    'cert_id' => $cid,
    'qr_payload' => $qrPayload,
    'qr_url' => $qrUrl
]);
?> 
