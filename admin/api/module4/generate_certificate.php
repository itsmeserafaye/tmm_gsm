<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');
$schedule_id = (int)($_POST['schedule_id'] ?? 0);
$approved_by = (int)($_POST['approved_by'] ?? 0);
if ($schedule_id <= 0 || $approved_by <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
$sch = $db->prepare("SELECT status FROM inspection_schedules WHERE schedule_id=?");
$sch->bind_param('i', $schedule_id);
$sch->execute();
$srow = $sch->get_result()->fetch_assoc();
if (!$srow || ($srow['status'] ?? '') !== 'Completed') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'schedule_not_completed']); exit; }
$ap = $db->prepare("SELECT active_status FROM officers WHERE officer_id=?");
$ap->bind_param('i', $approved_by);
$ap->execute();
$apro = $ap->get_result()->fetch_assoc();
if (!$apro || (int)$apro['active_status'] !== 1) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'approver_inactive']); exit; }
$existing = $db->prepare("SELECT cert_id, certificate_number FROM inspection_certificates WHERE schedule_id=?");
$existing->bind_param('i', $schedule_id);
$existing->execute();
$ex = $existing->get_result()->fetch_assoc();
if ($ex) { echo json_encode(['ok'=>true,'certificate_number'=>$ex['certificate_number'],'cert_id'=>$ex['cert_id']]); exit; }
$rs = $db->prepare("SELECT result_id, overall_status FROM inspection_results WHERE schedule_id=? ORDER BY submitted_at DESC LIMIT 1");
$rs->bind_param('i', $schedule_id);
$rs->execute();
$res = $rs->get_result()->fetch_assoc();
if (!$res || ($res['overall_status'] ?? '') !== 'Passed') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'inspection_not_passed']); exit; }
$year = date('Y');
$tmp_num = 'CERT-' . $year . '-TMP';
$ins = $db->prepare("INSERT INTO inspection_certificates(certificate_number, schedule_id, approved_by) VALUES(?,?,?)");
$ins->bind_param('sii', $tmp_num, $schedule_id, $approved_by);
$ok = $ins->execute();
if (!$ok) { echo json_encode(['ok'=>false,'error'=>'insert_failed']); exit; }
$cid = $db->insert_id;
$cert_no = 'CERT-' . $year . '-' . str_pad((string)$cid, 4, '0', STR_PAD_LEFT);
$up = $db->prepare("UPDATE inspection_certificates SET certificate_number=? WHERE cert_id=?");
$up->bind_param('si', $cert_no, $cid);
$up->execute();
echo json_encode(['ok'=>true,'certificate_number'=>$cert_no,'cert_id'=>$cid]);
?> 
