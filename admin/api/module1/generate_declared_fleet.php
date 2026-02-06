<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module1.write');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$operatorId = isset($_POST['operator_id']) ? (int)$_POST['operator_id'] : 0;
if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
  exit;
}

$opStmt = $db->prepare("SELECT id, operator_type, COALESCE(NULLIF(name,''), full_name) AS display_name, status
                        FROM operators WHERE id=? LIMIT 1");
if (!$opStmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$opStmt->bind_param('i', $operatorId);
$opStmt->execute();
$op = $opStmt->get_result()->fetch_assoc();
$opStmt->close();
if (!$op) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
  exit;
}

$hasRegs = (bool)($db->query("SHOW TABLES LIKE 'vehicle_registrations'")?->fetch_row());

$sqlVeh = "SELECT v.id AS vehicle_id, v.plate_number, v.vehicle_type, v.make, v.model, v.year_model, v.engine_no, v.chassis_no,
                  COALESCE(v.status,'') AS status, COALESCE(v.record_status,'') AS record_status, COALESCE(v.inspection_status,'') AS inspection_status";
if ($hasRegs) {
  $sqlVeh .= ", COALESCE(vr.registration_status,'') AS registration_status,
               COALESCE(NULLIF(vr.orcr_no,''),'') AS orcr_no,
               vr.orcr_date";
} else {
  $sqlVeh .= ", '' AS registration_status, '' AS orcr_no, NULL AS orcr_date";
}
$sqlVeh .= " FROM vehicles v";
if ($hasRegs) $sqlVeh .= " LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id";
$sqlVeh .= " WHERE COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0), 0)=?
             AND COALESCE(v.record_status,'') <> 'Archived'
             ORDER BY v.plate_number ASC";

$stmtVeh = $db->prepare($sqlVeh);
if (!$stmtVeh) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtVeh->bind_param('i', $operatorId);
$stmtVeh->execute();
$resVeh = $stmtVeh->get_result();
$vehicles = [];
while ($resVeh && ($r = $resVeh->fetch_assoc())) $vehicles[] = $r;
$stmtVeh->close();

if (!$vehicles) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'no_linked_vehicles']);
  exit;
}

$vehicleIds = array_values(array_filter(array_map(fn($v) => (int)($v['vehicle_id'] ?? 0), $vehicles), fn($x) => $x > 0));
$docsByVehicle = [];
if ($vehicleIds) {
  $in = implode(',', array_fill(0, count($vehicleIds), '?'));
  $types = str_repeat('i', count($vehicleIds));
  $sqlDocs = "SELECT vehicle_id, doc_type, file_path, uploaded_at, is_verified
              FROM vehicle_documents
              WHERE vehicle_id IN ($in)
                AND COALESCE(NULLIF(file_path,''),'') <> ''
                AND LOWER(COALESCE(doc_type,'')) IN ('or','cr','orcr','insurance')";
  $stmtDocs = $db->prepare($sqlDocs);
  if ($stmtDocs) {
    $stmtDocs->bind_param($types, ...$vehicleIds);
    $stmtDocs->execute();
    $resDocs = $stmtDocs->get_result();
    while ($resDocs && ($d = $resDocs->fetch_assoc())) {
      $vid = (int)($d['vehicle_id'] ?? 0);
      if ($vid <= 0) continue;
      if (!isset($docsByVehicle[$vid])) $docsByVehicle[$vid] = [];
      $docsByVehicle[$vid][] = $d;
    }
    $stmtDocs->close();
  }
}

$now = date('Y-m-d H:i:s');
$opName = (string)($op['display_name'] ?? '');
$opType = (string)($op['operator_type'] ?? '');
$opStatus = (string)($op['status'] ?? '');

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES); }
function vstr($v): string { return trim((string)($v ?? '')); }
function yn($b): string { return $b ? 'Yes' : 'No'; }

$rowsHtml = '';
$attachHtml = '';
foreach ($vehicles as $v) {
  $vid = (int)($v['vehicle_id'] ?? 0);
  $plate = vstr($v['plate_number'] ?? '');
  $vehType = vstr($v['vehicle_type'] ?? '');
  $make = vstr($v['make'] ?? '');
  $model = vstr($v['model'] ?? '');
  $year = vstr($v['year_model'] ?? '');
  $engine = vstr($v['engine_no'] ?? '');
  $chassis = vstr($v['chassis_no'] ?? '');
  $st = vstr($v['status'] ?? '');
  $insp = vstr($v['inspection_status'] ?? '');
  $reg = vstr($v['registration_status'] ?? '');
  $orcrNo = vstr($v['orcr_no'] ?? '');
  $orcrDate = vstr($v['orcr_date'] ?? '');

  $docs = $vid > 0 && isset($docsByVehicle[$vid]) ? $docsByVehicle[$vid] : [];
  $hasOr = false;
  $hasCr = false;
  $hasOrcr = false;
  $hasIns = false;
  foreach ($docs as $d) {
    $dt = strtolower(vstr($d['doc_type'] ?? ''));
    if ($dt === 'or') $hasOr = true;
    if ($dt === 'cr') $hasCr = true;
    if ($dt === 'orcr') $hasOrcr = true;
    if ($dt === 'insurance') $hasIns = true;
  }
  $rowsHtml .= '<tr>'
    . '<td>' . h($plate) . '</td>'
    . '<td>' . h($vehType) . '</td>'
    . '<td>' . h(trim($make . ' ' . $model)) . '</td>'
    . '<td>' . h($year) . '</td>'
    . '<td>' . h($engine) . '</td>'
    . '<td>' . h($chassis) . '</td>'
    . '<td>' . h($orcrNo !== '' ? $orcrNo : (($hasOrcr || ($hasOr && $hasCr)) ? 'Attached' : 'Missing')) . '</td>'
    . '<td>' . h($orcrDate) . '</td>'
    . '<td>' . h($insp !== '' ? $insp : '-') . '</td>'
    . '<td>' . h($reg !== '' ? $reg : '-') . '</td>'
    . '<td>' . h($st !== '' ? $st : '-') . '</td>'
    . '<td>' . h(yn($hasIns)) . '</td>'
    . '</tr>';

  $docLinks = '';
  foreach ($docs as $d) {
    $fn = vstr($d['file_path'] ?? '');
    if ($fn === '') continue;
    $dt = vstr($d['doc_type'] ?? '');
    $url = rawurlencode($fn);
    $docLinks .= '<li><a href="' . h($url) . '" target="_blank">' . h($dt . ' - ' . $plate) . '</a></li>';
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
      $docLinks .= '<div class="preview"><img src="' . h($url) . '" alt="' . h($dt . ' - ' . $plate) . '"></div>';
    }
  }
  if ($docLinks !== '') {
    $attachHtml .= '<h3>' . h($plate) . '</h3><ul>' . $docLinks . '</ul>';
  } else {
    $attachHtml .= '<h3>' . h($plate) . '</h3><div class="muted">No attached OR/CR/Insurance files found.</div>';
  }
}

$html = '<!doctype html><html><head><meta charset="utf-8"><title>Declared Fleet</title>'
  . '<style>'
  . 'body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#0f172a;}'
  . '.muted{color:#64748b;font-size:12px;}'
  . 'h1{font-size:22px;margin:0 0 6px 0;}'
  . 'h2{font-size:14px;margin:18px 0 8px 0;text-transform:uppercase;letter-spacing:.08em;color:#334155;}'
  . 'table{width:100%;border-collapse:collapse;margin-top:8px;font-size:12px;}'
  . 'th,td{border:1px solid #cbd5e1;padding:6px;vertical-align:top;}'
  . 'th{background:#f1f5f9;text-align:left;}'
  . 'a{color:#2563eb;text-decoration:none;}'
  . 'a:hover{text-decoration:underline;}'
  . '.preview img{max-width:100%;border:1px solid #cbd5e1;border-radius:8px;margin:10px 0;}'
  . '</style></head><body>'
  . '<h1>Declared Fleet Report</h1>'
  . '<div class="muted">System-generated • ' . h($now) . '</div>'
  . '<h2>Operator</h2>'
  . '<div><strong>' . h($opName) . '</strong></div>'
  . '<div class="muted">Type: ' . h($opType) . ' • Status: ' . h($opStatus) . '</div>'
  . '<h2>Fleet Summary</h2>'
  . '<table><thead><tr>'
  . '<th>Plate No</th><th>Vehicle Type</th><th>Make / Model</th><th>Year</th><th>Engine No</th><th>Chassis No</th>'
  . '<th>OR/CR</th><th>OR/CR Date</th><th>Inspection</th><th>Registration</th><th>Status</th><th>Insurance</th>'
  . '</tr></thead><tbody>'
  . $rowsHtml
  . '</tbody></table>'
  . '<h2>Attachments</h2>'
  . '<div class="muted">Links open the uploaded vehicle documents (OR/CR and insurance) stored in the system.</div>'
  . $attachHtml
  . '</body></html>';

$uploadsDir = __DIR__ . '/../../uploads';
if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);
$filename = 'declared_fleet_operator_' . $operatorId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.html';
$dest = $uploadsDir . '/' . $filename;
if (@file_put_contents($dest, $html) === false) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'write_failed']);
  exit;
}

$stmtIns = $db->prepare("INSERT INTO operator_documents (operator_id, doc_type, file_path, doc_status, remarks, is_verified)
                         VALUES (?, 'Others', ?, 'For Review', 'Declared Fleet (System Generated)', 0)");
if (!$stmtIns) {
  if (is_file($dest)) @unlink($dest);
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtIns->bind_param('is', $operatorId, $filename);
if (!$stmtIns->execute()) {
  $stmtIns->close();
  if (is_file($dest)) @unlink($dest);
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
  exit;
}
$docId = (int)$db->insert_id;
$stmtIns->close();

echo json_encode(['ok' => true, 'doc_id' => $docId, 'file_path' => $filename]);
