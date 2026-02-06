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

function vstr($v): string { return trim((string)($v ?? '')); }

$rows = [];
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
  $orcrStatus = $orcrNo !== '' ? $orcrNo : (($hasOrcr || ($hasOr && $hasCr)) ? 'Attached' : 'Missing');
  $rows[] = [
    'plate_number' => $plate,
    'vehicle_type' => $vehType,
    'make_model' => trim($make . ' ' . $model),
    'year_model' => $year,
    'engine_no' => $engine,
    'chassis_no' => $chassis,
    'orcr' => $orcrStatus,
    'orcr_date' => $orcrDate,
    'inspection_status' => $insp !== '' ? $insp : '-',
    'registration_status' => $reg !== '' ? $reg : '-',
    'status' => $st !== '' ? $st : '-',
    'insurance' => $hasIns ? 'Yes' : 'No',
  ];
}

$uploadsDir = __DIR__ . '/../../uploads';
if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);
if (php_sapi_name() !== 'cli' && function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$commit = (string)($_POST['commit'] ?? '');
$token = trim((string)($_POST['token'] ?? ''));
$format = strtolower(trim((string)($_POST['format'] ?? 'pdf')));

$toWin1252 = function ($s) {
  $s = (string)$s;
  if (function_exists('iconv')) {
    $v = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
    if ($v !== false && $v !== null) return $v;
  }
  return $s;
};

$pdfEsc = function ($s) use ($toWin1252) {
  $s = $toWin1252($s);
  $s = str_replace("\\", "\\\\", $s);
  $s = str_replace("(", "\\(", $s);
  $s = str_replace(")", "\\)", $s);
  $s = preg_replace("/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/", "", $s);
  return $s;
};

$pdfFromLines = function (array $lines) use ($pdfEsc): string {
  $pageWidth = 595;
  $pageHeight = 842;
  $marginLeft = 36;
  $startY = 806;
  $leading = 10;
  $maxLines = 70;

  $pages = [];
  $cur = [];
  foreach ($lines as $ln) {
    $cur[] = (string)$ln;
    if (count($cur) >= $maxLines) {
      $pages[] = $cur;
      $cur = [];
    }
  }
  if ($cur) $pages[] = $cur;
  if (!$pages) $pages[] = ['No records.'];

  $objects = [];
  $addObj = function ($body) use (&$objects) {
    $objects[] = (string)$body;
    return count($objects);
  };

  $catalogId = $addObj('');
  $pagesId = $addObj('');
  $fontId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>");

  $pageObjIds = [];
  foreach ($pages as $pageLines) {
    $content = "BT\n/F1 9 Tf\n" . $leading . " TL\n1 0 0 1 " . $marginLeft . " " . $startY . " Tm\n";
    foreach ($pageLines as $ln) {
      $content .= "(" . $pdfEsc($ln) . ") Tj\nT*\n";
    }
    $content .= "ET\n";
    $contentObjId = $addObj("<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream");
    $pageObjId = $addObj("<< /Type /Page /Parent " . $pagesId . " 0 R /MediaBox [0 0 " . $pageWidth . " " . $pageHeight . "] /Resources << /Font << /F1 " . $fontId . " 0 R >> >> /Contents " . $contentObjId . " 0 R >>");
    $pageObjIds[] = $pageObjId;
  }

  $kids = implode(' ', array_map(function ($id) { return $id . " 0 R"; }, $pageObjIds));
  $objects[$pagesId - 1] = "<< /Type /Pages /Count " . count($pageObjIds) . " /Kids [ " . $kids . " ] >>";
  $objects[$catalogId - 1] = "<< /Type /Catalog /Pages " . $pagesId . " 0 R >>";

  $pdf = "%PDF-1.4\n";
  $offsets = [0];
  for ($i = 0; $i < count($objects); $i++) {
    $offsets[] = strlen($pdf);
    $pdf .= ($i + 1) . " 0 obj\n" . $objects[$i] . "\nendobj\n";
  }
  $xrefPos = strlen($pdf);
  $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
  $pdf .= "0000000000 65535 f \n";
  for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
  }
  $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root " . $catalogId . " 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";
  return $pdf;
};

$makeToken = function (): string {
  if (function_exists('random_bytes')) return bin2hex(random_bytes(16));
  return bin2hex(openssl_random_pseudo_bytes(16));
};

$writePreviewFiles = function () use ($rows, $uploadsDir, $operatorId, $opName, $opType, $opStatus, $now, $pdfFromLines): array {
  $suffix = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
  $pdfFile = 'declared_fleet_operator_' . $operatorId . '_' . $suffix . '.pdf';
  $csvFile = 'declared_fleet_operator_' . $operatorId . '_' . $suffix . '.csv';

  $lines = [];
  $lines[] = 'Declared Fleet Report';
  $lines[] = 'System-generated: ' . $now;
  $lines[] = 'Operator: ' . $opName;
  $lines[] = 'Type: ' . $opType . '   Status: ' . $opStatus;
  $lines[] = 'Total Vehicles: ' . (string)count($rows);
  $lines[] = str_repeat('-', 94);
  $lines[] = 'PLATE    TYPE       MAKE/MODEL          YEAR CHASSIS           OR/CR    STATUS   INS';
  $lines[] = str_repeat('-', 94);

  foreach ($rows as $r) {
    $plate = substr((string)($r['plate_number'] ?? ''), 0, 8);
    $type = substr((string)($r['vehicle_type'] ?? ''), 0, 10);
    $mm = substr((string)($r['make_model'] ?? ''), 0, 18);
    $year = substr((string)($r['year_model'] ?? ''), 0, 4);
    $ch = substr((string)($r['chassis_no'] ?? ''), 0, 17);
    $orcr = substr((string)($r['orcr'] ?? ''), 0, 8);
    $status = substr((string)($r['status'] ?? ''), 0, 8);
    $ins = substr((string)($r['insurance'] ?? ''), 0, 3);
    $lines[] = sprintf("%-8s %-10s %-18s %-4s %-17s %-8s %-8s %-3s", $plate, $type, $mm, $year, $ch, $orcr, $status, $ins);
  }

  $pdf = $pdfFromLines($lines);
  if (@file_put_contents($uploadsDir . '/' . $pdfFile, $pdf) === false) {
    throw new Exception('write_failed');
  }

  $fp = @fopen($uploadsDir . '/' . $csvFile, 'w');
  if (!$fp) {
    if (is_file($uploadsDir . '/' . $pdfFile)) @unlink($uploadsDir . '/' . $pdfFile);
    throw new Exception('write_failed');
  }
  fputcsv($fp, ['Plate No','Vehicle Type','Make / Model','Year','Engine No','Chassis No','OR/CR','OR/CR Date','Inspection','Registration','Status','Insurance']);
  foreach ($rows as $r) {
    fputcsv($fp, [
      (string)($r['plate_number'] ?? ''),
      (string)($r['vehicle_type'] ?? ''),
      (string)($r['make_model'] ?? ''),
      (string)($r['year_model'] ?? ''),
      (string)($r['engine_no'] ?? ''),
      (string)($r['chassis_no'] ?? ''),
      (string)($r['orcr'] ?? ''),
      (string)($r['orcr_date'] ?? ''),
      (string)($r['inspection_status'] ?? ''),
      (string)($r['registration_status'] ?? ''),
      (string)($r['status'] ?? ''),
      (string)($r['insurance'] ?? ''),
    ]);
  }
  fclose($fp);

  return ['pdf' => $pdfFile, 'excel' => $csvFile];
};

if ($commit !== '1') {
  $files = $writePreviewFiles();
  $token = $makeToken();
  if (!isset($_SESSION['tmm_declared_fleet_previews']) || !is_array($_SESSION['tmm_declared_fleet_previews'])) {
    $_SESSION['tmm_declared_fleet_previews'] = [];
  }
  $_SESSION['tmm_declared_fleet_previews'][$token] = [
    'operator_id' => $operatorId,
    'created_at' => time(),
    'pdf' => $files['pdf'],
    'excel' => $files['excel'],
  ];
  echo json_encode([
    'ok' => true,
    'token' => $token,
    'operator' => ['id' => $operatorId, 'name' => $opName, 'type' => $opType, 'status' => $opStatus],
    'generated_at' => $now,
    'files' => $files,
    'rows' => $rows,
  ]);
  exit;
}

if ($token === '' || !isset($_SESSION['tmm_declared_fleet_previews']) || !is_array($_SESSION['tmm_declared_fleet_previews']) || !isset($_SESSION['tmm_declared_fleet_previews'][$token])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'preview_required']);
  exit;
}
$prev = $_SESSION['tmm_declared_fleet_previews'][$token];
if ((int)($prev['operator_id'] ?? 0) !== $operatorId) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'preview_mismatch']);
  exit;
}
if ((time() - (int)($prev['created_at'] ?? 0)) > 20 * 60) {
  unset($_SESSION['tmm_declared_fleet_previews'][$token]);
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'preview_expired']);
  exit;
}

$chosen = $format === 'excel' ? (string)($prev['excel'] ?? '') : (string)($prev['pdf'] ?? '');
$chosen = basename($chosen);
if ($chosen === '' || !is_file($uploadsDir . '/' . $chosen)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'file_missing']);
  exit;
}

function tmm_operator_docs_status_value(mysqli $db): array {
  $res = $db->query("SHOW COLUMNS FROM operator_documents LIKE 'doc_status'");
  if (!$res || ($res->num_rows ?? 0) === 0) return ['has' => false, 'value' => null];
  $row = $res->fetch_assoc();
  $type = strtolower((string)($row['Type'] ?? ''));
  if (strpos($type, 'for review') !== false) return ['has' => true, 'value' => 'For Review'];
  if (strpos($type, 'pending') !== false) return ['has' => true, 'value' => 'Pending'];
  if (strpos($type, 'submitted') !== false) return ['has' => true, 'value' => 'Submitted'];
  return ['has' => true, 'value' => null];
}

$docStatus = tmm_operator_docs_status_value($db);
$remarks = 'Declared Fleet (Planned / Owned Vehicles) | System Generated';
if ($docStatus['has'] && $docStatus['value'] !== null) {
  $stmtIns = $db->prepare("INSERT INTO operator_documents (operator_id, doc_type, file_path, doc_status, remarks, is_verified)
                           VALUES (?, 'Others', ?, ?, ?, 0)");
} else {
  $stmtIns = $db->prepare("INSERT INTO operator_documents (operator_id, doc_type, file_path, remarks, is_verified)
                           VALUES (?, 'Others', ?, ?, 0)");
}
if (!$stmtIns) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
if ($docStatus['has'] && $docStatus['value'] !== null) {
  $st = (string)$docStatus['value'];
  $stmtIns->bind_param('isss', $operatorId, $chosen, $st, $remarks);
} else {
  $stmtIns->bind_param('iss', $operatorId, $chosen, $remarks);
}
if (!$stmtIns->execute()) {
  $stmtIns->close();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error', 'message' => (string)($db->error ?? '')]);
  exit;
}
$docId = (int)$db->insert_id;
$stmtIns->close();
unset($_SESSION['tmm_declared_fleet_previews'][$token]);

echo json_encode(['ok' => true, 'doc_id' => $docId, 'file_path' => $chosen]);
