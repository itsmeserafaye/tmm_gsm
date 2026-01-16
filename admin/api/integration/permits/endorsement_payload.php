<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/lptrp.php';
require_once __DIR__ . '/_auth.php';

$db = db();
header('Content-Type: application/json');
tmm_integration_authorize();

$ref = trim((string)($_GET['reference_no'] ?? ''));
$app = trim((string)($_GET['application_id'] ?? ''));
$q = $ref !== '' ? $ref : $app;

if ($q === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_reference_no']);
  exit;
}

$appId = null;
if (preg_match('/^APP-(\d+)/i', $q, $m)) $appId = (int)$m[1];
elseif (ctype_digit($q)) $appId = (int)$q;

$hasLptrp = (bool)($db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lptrp_routes' LIMIT 1")->fetch_row());
$hasDesc = $hasLptrp && tmm_table_has_column($db, 'lptrp_routes', 'description');
$hasRouteName = $hasLptrp && tmm_table_has_column($db, 'lptrp_routes', 'route_name');
$hasStart = $hasLptrp && tmm_table_has_column($db, 'lptrp_routes', 'start_point');
$hasEnd = $hasLptrp && tmm_table_has_column($db, 'lptrp_routes', 'end_point');
$descBase = $hasDesc ? "r.description" : ($hasRouteName ? "r.route_name" : "''");
$routeNameExpr = $descBase;
if ($hasStart && $hasEnd) {
  $routeNameExpr = "COALESCE(NULLIF($descBase,''), NULLIF(CONCAT_WS(' → ', r.start_point, r.end_point),''), r.route_code)";
} else {
  $routeNameExpr = "COALESCE(NULLIF($descBase,''), r.route_code)";
}

$sql = "SELECT fa.application_id, fa.franchise_ref_number, fa.submitted_at, fa.vehicle_count, fa.status,
               fa.validation_notes, fa.lptrp_status, fa.coop_status,
               fa.permits_case_id, fa.permit_status, fa.permit_number, fa.permit_issued_date, fa.permit_expiry_date, fa.permit_remarks,
               o.full_name AS operator_name, o.contact_info AS operator_contact, o.coop_name AS operator_coop_name,
               c.coop_name, c.address AS coop_address, c.lgu_approval_number, c.chairperson_name,
               r.route_code, $routeNameExpr AS route_name
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id = fa.operator_id
        LEFT JOIN coops c ON c.id = fa.coop_id
        LEFT JOIN lptrp_routes r ON r.id = fa.route_ids ";

if ($appId !== null) {
  $stmt = $db->prepare($sql . "WHERE fa.application_id=? LIMIT 1");
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param('i', $appId);
} else {
  $stmt = $db->prepare($sql . "WHERE fa.franchise_ref_number=? LIMIT 1");
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param('s', $q);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'application_not_found']);
  exit;
}

$applicationId = (int)($row['application_id'] ?? 0);
$referenceNo = (string)($row['franchise_ref_number'] ?? '');

$vehicles = [];
if ($referenceNo !== '') {
  $stmtV = $db->prepare("SELECT plate_number, vehicle_type, operator_name, status, inspection_status, route_id FROM vehicles WHERE franchise_id=? ORDER BY plate_number LIMIT 200");
  if ($stmtV) {
    $stmtV->bind_param('s', $referenceNo);
    $stmtV->execute();
    $resV = $stmtV->get_result();
    while ($resV && ($v = $resV->fetch_assoc())) {
      $vehicles[] = [
        'plate_no' => (string)($v['plate_number'] ?? ''),
        'type' => (string)($v['vehicle_type'] ?? ''),
        'operator_name' => (string)($v['operator_name'] ?? ''),
        'status' => (string)($v['status'] ?? ''),
        'inspection_status' => (string)($v['inspection_status'] ?? ''),
        'route_id' => (string)($v['route_id'] ?? ''),
      ];
    }
    $stmtV->close();
  }
}

$docs = [];
if ($applicationId > 0) {
  $stmtD = $db->prepare("SELECT id, type, file_path, verified, uploaded_at FROM documents WHERE application_id=? ORDER BY uploaded_at ASC");
  if ($stmtD) {
    $stmtD->bind_param('i', $applicationId);
    $stmtD->execute();
    $resD = $stmtD->get_result();
    while ($resD && ($d = $resD->fetch_assoc())) {
      $docs[] = [
        'id' => (int)($d['id'] ?? 0),
        'type' => (string)($d['type'] ?? ''),
        'ref' => (string)($d['file_path'] ?? ''),
        'verified' => (int)($d['verified'] ?? 0) === 1,
        'uploaded_at' => (string)($d['uploaded_at'] ?? ''),
      ];
    }
    $stmtD->close();
  }
}

$applicantType = ($row['coop_name'] ?? '') !== '' ? 'Cooperative' : 'Individual';
$applicantName = $applicantType === 'Cooperative' ? (string)($row['coop_name'] ?? '') : (string)($row['operator_name'] ?? '');
$applicantAddress = $applicantType === 'Cooperative' ? (string)($row['coop_address'] ?? '') : '';
$applicantContact = (string)($row['operator_contact'] ?? '');

$recommended = 'RECOMMENDED_APPROVAL';
if ((string)($row['lptrp_status'] ?? '') === 'Failed' || (string)($row['coop_status'] ?? '') === 'Failed') {
  $recommended = 'RECOMMENDED_REJECTION';
}

echo json_encode([
  'ok' => true,
  'transaction_type' => 'FRANCHISE_ENDORSEMENT',
  'reference_no' => $referenceNo !== '' ? $referenceNo : ('APP-' . $applicationId),
  'submitted_at' => (string)($row['submitted_at'] ?? ''),
  'applicant' => [
    'type' => $applicantType,
    'name' => $applicantName,
    'address' => $applicantAddress,
    'contact_no' => $applicantContact,
    'lgu_approval_number' => (string)($row['lgu_approval_number'] ?? ''),
  ],
  'route' => [
    'route_code' => (string)($row['route_code'] ?? ''),
    'route_name' => (string)($row['route_name'] ?? ''),
  ],
  'units' => $vehicles,
  'endorsement' => [
    'recommended_status' => $recommended,
    'notes' => (string)($row['validation_notes'] ?? ''),
    'supporting_documents' => $docs,
  ],
  'tmm_state' => [
    'application_id' => $applicationId,
    'application_status' => (string)($row['status'] ?? ''),
    'vehicle_count_requested' => (int)($row['vehicle_count'] ?? 0),
    'lptrp_status' => (string)($row['lptrp_status'] ?? ''),
    'coop_status' => (string)($row['coop_status'] ?? ''),
    'permits_case_id' => (string)($row['permits_case_id'] ?? ''),
    'permit' => [
      'status' => (string)($row['permit_status'] ?? ''),
      'permit_number' => (string)($row['permit_number'] ?? ''),
      'issued_date' => (string)($row['permit_issued_date'] ?? ''),
      'expiry_date' => (string)($row['permit_expiry_date'] ?? ''),
      'remarks' => (string)($row['permit_remarks'] ?? ''),
    ],
  ],
]);
