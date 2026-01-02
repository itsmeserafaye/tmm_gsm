<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');
$schedule_id = (int)($_POST['schedule_id'] ?? 0);
$remarks = trim($_POST['remarks'] ?? '');
if ($schedule_id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'schedule_required']); exit; }
$chk = $db->prepare("SELECT schedule_id, status FROM inspection_schedules WHERE schedule_id=?");
$chk->bind_param('i', $schedule_id);
$chk->execute();
$sched = $chk->get_result()->fetch_assoc();
if (!$sched) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'schedule_not_found']); exit; }
if (($sched['status'] ?? '') === 'Completed') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'already_completed']); exit; }
$items = $_POST['items'] ?? [];
$overall = 'Pending';
if (!is_array($items)) { $items = []; }
$allowed = ['PASS','FAIL','NA'];
$countValid = 0; $hasFail = false; $hasPass = false;
foreach ($items as $code => $status) {
  $val = strtoupper(trim($status));
  if (!in_array($val, $allowed)) { continue; }
  $countValid++;
  if ($val === 'FAIL') { $hasFail = true; }
  if ($val === 'PASS') { $hasPass = true; }
}
if ($countValid < 3) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'insufficient_items']); exit; }
$overall = $hasFail ? 'Failed' : ($hasPass ? 'Passed' : 'Pending');
$stmt = $db->prepare("INSERT INTO inspection_results(schedule_id, overall_status, remarks) VALUES(?,?,?)");
$stmt->bind_param('iss', $schedule_id, $overall, $remarks);
$ok = $stmt->execute();
if (!$ok) { echo json_encode(['ok'=>false,'error'=>'save_result_failed']); exit; }
$result_id = $db->insert_id;
if (!empty($items)) {
  $ins = $db->prepare("INSERT INTO inspection_checklist_items(result_id, item_code, item_label, status) VALUES(?,?,?,?)");
  foreach ($items as $code => $status) {
    $label = $code;
    $st = ucfirst(strtolower($status));
    $ins->bind_param('isss', $result_id, $code, $label, $st);
    $ins->execute();
  }
}
$upd = $db->prepare("UPDATE inspection_schedules SET status='Completed' WHERE schedule_id=?");
$upd->bind_param('i', $schedule_id);
$upd->execute();
echo json_encode(['ok'=>true,'result_id'=>$result_id,'overall_status'=>$overall]);
?> 
