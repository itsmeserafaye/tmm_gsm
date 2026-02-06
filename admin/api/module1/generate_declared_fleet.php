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

$systemName = tmm_get_app_setting('system_name', 'LGU PUV Management System');
$lguName = tmm_get_app_setting('lgu_name', $systemName);
$operatorCode = 'OP-' . str_pad((string)$operatorId, 5, '0', STR_PAD_LEFT);

$applicationId = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
$franchiseApplicationId = 0;
$franchiseRef = '';
$hasFa = (bool)($db->query("SHOW TABLES LIKE 'franchise_applications'")?->fetch_row());
if ($hasFa) {
  if ($applicationId > 0) {
    $stmtFa = $db->prepare("SELECT application_id, franchise_ref_number FROM franchise_applications WHERE application_id=? AND operator_id=? LIMIT 1");
    if ($stmtFa) {
      $stmtFa->bind_param('ii', $applicationId, $operatorId);
      $stmtFa->execute();
      $fa = $stmtFa->get_result()->fetch_assoc();
      $stmtFa->close();
      if ($fa) {
        $franchiseApplicationId = (int)($fa['application_id'] ?? 0);
        $franchiseRef = trim((string)($fa['franchise_ref_number'] ?? ''));
      }
    }
  }
  if ($franchiseRef === '') {
    $stmtFa2 = $db->prepare("SELECT application_id, franchise_ref_number FROM franchise_applications WHERE operator_id=? ORDER BY submitted_at DESC, application_id DESC LIMIT 1");
    if ($stmtFa2) {
      $stmtFa2->bind_param('i', $operatorId);
      $stmtFa2->execute();
      $fa2 = $stmtFa2->get_result()->fetch_assoc();
      $stmtFa2->close();
      if ($fa2) {
        $franchiseApplicationId = (int)($fa2['application_id'] ?? 0);
        $franchiseRef = trim((string)($fa2['franchise_ref_number'] ?? ''));
      }
    }
  }
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && ($r->num_rows ?? 0) > 0;
};
$ensureVehicleCols = function () use ($db, $hasCol): void {
  if (!$hasCol('vehicles', 'or_number')) { @$db->query("ALTER TABLE vehicles ADD COLUMN or_number VARCHAR(12) NULL"); }
  if (!$hasCol('vehicles', 'cr_number')) { @$db->query("ALTER TABLE vehicles ADD COLUMN cr_number VARCHAR(64) NULL"); }
  if (!$hasCol('vehicles', 'cr_issue_date')) { @$db->query("ALTER TABLE vehicles ADD COLUMN cr_issue_date DATE NULL"); }
  if (!$hasCol('vehicles', 'registered_owner')) { @$db->query("ALTER TABLE vehicles ADD COLUMN registered_owner VARCHAR(150) NULL"); }
  if (!$hasCol('vehicles', 'inspection_cert_ref')) { @$db->query("ALTER TABLE vehicles ADD COLUMN inspection_cert_ref VARCHAR(64) DEFAULT NULL"); }
};
$ensureVehicleCols();

$hasRegs = (bool)($db->query("SHOW TABLES LIKE 'vehicle_registrations'")?->fetch_row());
$hasRegOrNumber = $hasRegs && $hasCol('vehicle_registrations', 'or_number');

$sqlVeh = "SELECT v.id AS vehicle_id, v.plate_number, v.vehicle_type, v.make, v.model, v.year_model, v.engine_no, v.chassis_no,
                  COALESCE(v.or_number,'') AS or_number, COALESCE(v.cr_number,'') AS cr_number, COALESCE(v.inspection_cert_ref,'') AS inspection_cert_ref,
                  COALESCE(v.status,'') AS status, COALESCE(v.record_status,'') AS record_status, COALESCE(v.inspection_status,'') AS inspection_status";
if ($hasRegs) {
  $sqlVeh .= ", COALESCE(vr.registration_status,'') AS registration_status,
               COALESCE(NULLIF(vr.orcr_no,''),'') AS orcr_no,
               vr.orcr_date" . ($hasRegOrNumber ? ", COALESCE(NULLIF(vr.or_number,''),'') AS reg_or_number" : ", '' AS reg_or_number");
} else {
  $sqlVeh .= ", '' AS registration_status, '' AS orcr_no, NULL AS orcr_date, '' AS reg_or_number";
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

$plates = array_values(array_filter(array_map(fn($v) => trim((string)($v['plate_number'] ?? '')), $vehicles), fn($x) => $x !== ''));
$docsByPlate = [];
$hasDocsTable = (bool)($db->query("SHOW TABLES LIKE 'documents'")?->fetch_row());
if ($hasDocsTable && $plates) {
  $inP = implode(',', array_fill(0, count($plates), '?'));
  $typesP = str_repeat('s', count($plates));
  $sqlPDocs = "SELECT plate_number, LOWER(type) AS doc_type, file_path, uploaded_at
               FROM documents
               WHERE plate_number IN ($inP)
                 AND LOWER(type) IN ('or','cr')
                 AND COALESCE(NULLIF(file_path,''),'') <> ''
               ORDER BY uploaded_at DESC, id DESC";
  $stmtPDocs = $db->prepare($sqlPDocs);
  if ($stmtPDocs) {
    $stmtPDocs->bind_param($typesP, ...$plates);
    $stmtPDocs->execute();
    $resPDocs = $stmtPDocs->get_result();
    while ($resPDocs && ($d = $resPDocs->fetch_assoc())) {
      $p = trim((string)($d['plate_number'] ?? ''));
      $dt = trim((string)($d['doc_type'] ?? ''));
      $fp = trim((string)($d['file_path'] ?? ''));
      if ($p === '' || $dt === '' || $fp === '') continue;
      if (!isset($docsByPlate[$p])) $docsByPlate[$p] = [];
      if (!isset($docsByPlate[$p][$dt])) $docsByPlate[$p][$dt] = $fp;
    }
    $stmtPDocs->close();
  }
}

$vehicleIds = array_values(array_filter(array_map(fn($v) => (int)($v['vehicle_id'] ?? 0), $vehicles), fn($x) => $x > 0));
$docsByVehicle = [];
if ($vehicleIds) {
  $in = implode(',', array_fill(0, count($vehicleIds), '?'));
  $types = str_repeat('i', count($vehicleIds));
  $sqlDocs = "SELECT doc_id, vehicle_id, doc_type, file_path, uploaded_at, is_verified
              FROM vehicle_documents
              WHERE vehicle_id IN ($in)
                AND COALESCE(NULLIF(file_path,''),'') <> ''
                AND LOWER(COALESCE(doc_type,'')) IN ('or','cr','orcr','insurance')
              ORDER BY uploaded_at DESC, doc_id DESC";
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
$breakdown = [];
foreach ($vehicles as $v) {
  $vid = (int)($v['vehicle_id'] ?? 0);
  $plate = vstr($v['plate_number'] ?? '');
  $vehType = vstr($v['vehicle_type'] ?? '');
  $make = vstr($v['make'] ?? '');
  $model = vstr($v['model'] ?? '');
  $year = vstr($v['year_model'] ?? '');
  $engine = vstr($v['engine_no'] ?? '');
  $chassis = vstr($v['chassis_no'] ?? '');
  $orNumber = vstr($v['or_number'] ?? '');
  if ($orNumber === '') $orNumber = vstr($v['reg_or_number'] ?? '');
  $crNumber = vstr($v['cr_number'] ?? '');
  $inspectionCert = vstr($v['inspection_cert_ref'] ?? '');
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
  $insuranceFile = '';
  $orFile = ($plate !== '' && isset($docsByPlate[$plate]['or'])) ? (string)$docsByPlate[$plate]['or'] : '';
  $crFile = ($plate !== '' && isset($docsByPlate[$plate]['cr'])) ? (string)$docsByPlate[$plate]['cr'] : '';
  foreach ($docs as $d) {
    $dt = strtolower(vstr($d['doc_type'] ?? ''));
    if ($dt === 'or') $hasOr = true;
    if ($dt === 'cr') $hasCr = true;
    if ($dt === 'orcr') $hasOrcr = true;
    if ($dt === 'insurance') $hasIns = true;
    $fp = vstr($d['file_path'] ?? '');
    if ($fp !== '' && $dt === 'insurance' && $insuranceFile === '') $insuranceFile = $fp;
    if ($fp !== '' && $dt === 'or' && $orFile === '') $orFile = $fp;
    if ($fp !== '' && $dt === 'cr' && $crFile === '') $crFile = $fp;
  }
  $orcrStatus = $orcrNo !== '' ? $orcrNo : (($hasOrcr || ($hasOr && $hasCr)) ? 'Attached' : 'Missing');
  $bt = $vehType !== '' ? $vehType : 'Unknown';
  $breakdown[$bt] = (int)($breakdown[$bt] ?? 0) + 1;
  $rows[] = [
    'plate_number' => $plate,
    'vehicle_type' => $vehType,
    'make' => $make,
    'model' => $model,
    'year_model' => $year,
    'engine_no' => $engine,
    'chassis_no' => $chassis,
    'or_number' => $orNumber,
    'cr_number' => $crNumber,
    'orcr' => $orcrStatus,
    'orcr_date' => $orcrDate,
    'inspection_status' => $insp !== '' ? $insp : '-',
    'registration_status' => $reg !== '' ? $reg : '-',
    'status' => $st !== '' ? $st : '-',
    'insurance' => $hasIns ? 'Yes' : 'No',
    'attachments' => [
      'or_file' => $orFile,
      'cr_file' => $crFile,
      'insurance_file' => $insuranceFile,
      'inspection_cert_ref' => $inspectionCert,
    ],
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

$writePreviewFiles = function () use ($rows, $breakdown, $uploadsDir, $operatorId, $operatorCode, $opName, $opType, $opStatus, $now, $pdfFromLines, $lguName, $systemName, $franchiseRef): array {
  $suffix = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
  $pdfFile = 'declared_fleet_operator_' . $operatorId . '_' . $suffix . '.pdf';
  $csvFile = 'declared_fleet_operator_' . $operatorId . '_' . $suffix . '.csv';

  $fmtDate = function (string $dt): string {
    $ts = strtotime($dt);
    if ($ts === false) return $dt;
    return date('M d, Y H:i', $ts);
  };
  $trunc = function (string $s, int $max): string {
    $s = (string)$s;
    if ($max <= 0) return '';
    if (strlen($s) <= $max) return $s;
    if ($max <= 3) return substr($s, 0, $max);
    return substr($s, 0, $max - 3) . '...';
  };
  $appendixLabel = function (int $n): string {
    $n = $n + 1;
    $out = '';
    while ($n > 0) {
      $n--;
      $out = chr(65 + ($n % 26)) . $out;
      $n = intdiv($n, 26);
    }
    return $out;
  };

  $lines = [];
  $lines[] = $lguName;
  $lines[] = 'DECLARED FLEET REPORT';
  $lines[] = 'Operator: ' . $opName;
  $lines[] = 'Operator Type: ' . $opType;
  $lines[] = 'Operator ID: ' . $operatorCode . ' (' . (string)$operatorId . ')';
  if ($franchiseRef !== '') $lines[] = 'Franchise Application ID: ' . $franchiseRef;
  $lines[] = 'Date Generated: ' . $fmtDate($now);
  $lines[] = 'Generated by: ' . $systemName;
  $lines[] = '';
  $lines[] = 'FLEET SUMMARY';
  $lines[] = 'Total Vehicles: ' . (string)count($rows);
  $lines[] = 'Breakdown:';
  arsort($breakdown);
  foreach ($breakdown as $k => $c) {
    $lines[] = '- ' . (string)$k . ': ' . (string)$c;
  }
  $lines[] = '';
  $lines[] = 'VEHICLE LIST';
  $lines[] = str_repeat('-', 110);
  $lines[] = sprintf("%-8s %-10s %-8s %-8s %-4s %-10s %-17s %-12s %-12s", 'PLATE', 'TYPE', 'MAKE', 'MODEL', 'YEAR', 'ENGINE', 'CHASSIS', 'OR NO', 'CR NO');
  $lines[] = str_repeat('-', 110);

  foreach ($rows as $r) {
    $plate = $trunc((string)($r['plate_number'] ?? ''), 8);
    $type = $trunc((string)($r['vehicle_type'] ?? ''), 10);
    $make = $trunc((string)($r['make'] ?? ''), 8);
    $model = $trunc((string)($r['model'] ?? ''), 8);
    $year = $trunc((string)($r['year_model'] ?? ''), 4);
    $engine = $trunc((string)($r['engine_no'] ?? ''), 10);
    $ch = $trunc((string)($r['chassis_no'] ?? ''), 17);
    $orNo = $trunc((string)($r['or_number'] ?? ''), 12);
    $crNo = $trunc((string)($r['cr_number'] ?? ''), 12);
    $lines[] = sprintf("%-8s %-10s %-8s %-8s %-4s %-10s %-17s %-12s %-12s", $plate, $type, $make, $model, $year, $engine, $ch, $orNo, $crNo);
  }

  $lines[] = '';
  $lines[] = 'ATTACHED SUPPORTING DOCUMENTS (AUTO-PULLED)';
  $lines[] = 'Appendix entries reference vehicle documents stored in the system uploads registry.';
  $lines[] = '';
  $idx = 0;
  foreach ($rows as $r) {
    $plate = (string)($r['plate_number'] ?? '');
    $att = is_array($r['attachments'] ?? null) ? $r['attachments'] : [];
    $orFile = $trunc((string)($att['or_file'] ?? ''), 60);
    $crFile = $trunc((string)($att['cr_file'] ?? ''), 60);
    $insFile = $trunc((string)($att['insurance_file'] ?? ''), 60);
    $certRef = $trunc((string)($att['inspection_cert_ref'] ?? ''), 30);
    $lbl = $appendixLabel($idx);
    $lines[] = 'Appendix ' . $lbl . ' – OR/CR: ' . $plate;
    $lines[] = '  OR No: ' . (string)($r['or_number'] ?? '') . ' | OR File: ' . ($orFile !== '' ? $orFile : 'Missing');
    $lines[] = '  CR No: ' . (string)($r['cr_number'] ?? '') . ' | CR File: ' . ($crFile !== '' ? $crFile : 'Missing');
    if ($insFile !== '') $lines[] = '  Insurance File: ' . $insFile;
    if ($certRef !== '') $lines[] = '  Inspection Certificate Ref: ' . $certRef;
    $lines[] = '';
    $idx++;
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
  fputcsv($fp, ['Plate No','Vehicle Type','Make','Model','Year','Engine No','Chassis No','OR No','CR No']);
  foreach ($rows as $r) {
    fputcsv($fp, [
      (string)($r['plate_number'] ?? ''),
      (string)($r['vehicle_type'] ?? ''),
      (string)($r['make'] ?? ''),
      (string)($r['model'] ?? ''),
      (string)($r['year_model'] ?? ''),
      (string)($r['engine_no'] ?? ''),
      (string)($r['chassis_no'] ?? ''),
      (string)($r['or_number'] ?? ''),
      (string)($r['cr_number'] ?? ''),
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
    'operator' => ['id' => $operatorId, 'code' => $operatorCode, 'name' => $opName, 'type' => $opType, 'status' => $opStatus],
    'franchise_application' => ['application_id' => $franchiseApplicationId, 'franchise_ref_number' => $franchiseRef],
    'system' => ['name' => $systemName, 'lgu_name' => $lguName],
    'summary' => ['total_vehicles' => count($rows), 'breakdown' => $breakdown],
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
