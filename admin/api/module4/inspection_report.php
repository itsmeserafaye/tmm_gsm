<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_any_permission(['module4.read','module4.inspect','module4.certify','module4.schedule']);

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$scheduleId = (int)($_GET['schedule_id'] ?? 0);
if ($scheduleId <= 0) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'missing_schedule_id';
  exit;
}

$stmtS = $db->prepare("SELECT schedule_id, plate_number, vehicle_id, scheduled_at, schedule_date, location, inspection_type, inspector_id, inspector_label, status
                       FROM inspection_schedules WHERE schedule_id=? LIMIT 1");
if (!$stmtS) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'db_prepare_failed';
  exit;
}
$stmtS->bind_param('i', $scheduleId);
$stmtS->execute();
$schedule = $stmtS->get_result()->fetch_assoc();
$stmtS->close();
if (!$schedule) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'schedule_not_found';
  exit;
}

$plate = (string)($schedule['plate_number'] ?? '');
$vehicleId = (int)($schedule['vehicle_id'] ?? 0);
$hasColSimple = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};
$vehicle = null;
if ($vehicleId > 0) {
  $vehHasVehicleType = $hasColSimple('vehicles', 'vehicle_type');
  $vehHasEngine = $hasColSimple('vehicles', 'engine_no');
  $vehHasChassis = $hasColSimple('vehicles', 'chassis_no');
  $vehHasMake = $hasColSimple('vehicles', 'make');
  $vehHasModel = $hasColSimple('vehicles', 'model');
  $vehHasYear = $hasColSimple('vehicles', 'year_model');
  $vehHasColor = $hasColSimple('vehicles', 'color');
  $vehHasOwner = $hasColSimple('vehicles', 'registered_owner');
  $stmtV = $db->prepare("SELECT id, plate_number, operator_name, coop_name, franchise_id, route_id, inspection_status, inspection_cert_ref, inspection_passed_at" .
                        ($vehHasVehicleType ? ", vehicle_type" : ", '' AS vehicle_type") .
                        ($vehHasEngine ? ", engine_no" : ", '' AS engine_no") .
                        ($vehHasChassis ? ", chassis_no" : ", '' AS chassis_no") .
                        ($vehHasMake ? ", make" : ", '' AS make") .
                        ($vehHasModel ? ", model" : ", '' AS model") .
                        ($vehHasYear ? ", year_model" : ", '' AS year_model") .
                        ($vehHasColor ? ", color" : ", '' AS color") .
                        ($vehHasOwner ? ", registered_owner" : ", '' AS registered_owner") .
                        " FROM vehicles WHERE id=? LIMIT 1");
  if ($stmtV) {
    $stmtV->bind_param('i', $vehicleId);
    $stmtV->execute();
    $vehicle = $stmtV->get_result()->fetch_assoc();
    $stmtV->close();
  }
}

$reg = null;
if ($vehicleId > 0) {
  $stmtR = $db->prepare("SELECT registration_status, orcr_no, orcr_date FROM vehicle_registrations WHERE vehicle_id=? LIMIT 1");
  if ($stmtR) {
    $stmtR->bind_param('i', $vehicleId);
    $stmtR->execute();
    $reg = $stmtR->get_result()->fetch_assoc();
    $stmtR->close();
  }
}

$inspectorName = '';
if (!empty($schedule['inspector_label'])) $inspectorName = (string)$schedule['inspector_label'];
if ($inspectorName === '' && !empty($schedule['inspector_id'])) {
  $iid = (int)$schedule['inspector_id'];
  $stmtI = $db->prepare("SELECT COALESCE(NULLIF(name,''), NULLIF(full_name,'')) AS name, badge_no FROM officers WHERE officer_id=? LIMIT 1");
  if ($stmtI) {
    $stmtI->bind_param('i', $iid);
    $stmtI->execute();
    $irow = $stmtI->get_result()->fetch_assoc();
    $stmtI->close();
    if ($irow) {
      $inspectorName = (string)($irow['name'] ?? '');
      $badge = (string)($irow['badge_no'] ?? '');
      if ($badge !== '') $inspectorName .= ' (' . $badge . ')';
    }
  }
}

$result = null;
$stmtRes = $db->prepare("SELECT result_id, overall_status, remarks, submitted_at FROM inspection_results WHERE schedule_id=? ORDER BY submitted_at DESC, result_id DESC LIMIT 1");
if ($stmtRes) {
  $stmtRes->bind_param('i', $scheduleId);
  $stmtRes->execute();
  $result = $stmtRes->get_result()->fetch_assoc();
  $stmtRes->close();
}

$checklist = [];
$photos = [];
if ($result && isset($result['result_id'])) {
  $rid = (int)$result['result_id'];
  $stmtC = $db->prepare("SELECT item_code, item_label, status FROM inspection_checklist_items WHERE result_id=? ORDER BY item_id ASC");
  if ($stmtC) {
    $stmtC->bind_param('i', $rid);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    while ($row = $resC->fetch_assoc()) $checklist[] = $row;
    $stmtC->close();
  }
  if (!$checklist) {
    $stmtCF = $db->prepare("SELECT i.item_code, i.item_label, i.status
                            FROM inspection_checklist_items i
                            JOIN inspection_results r ON r.result_id=i.result_id
                            WHERE r.schedule_id=? ORDER BY i.item_id ASC");
    if ($stmtCF) {
      $stmtCF->bind_param('i', $scheduleId);
      $stmtCF->execute();
      $resCF = $stmtCF->get_result();
      while ($row = $resCF->fetch_assoc()) $checklist[] = $row;
      $stmtCF->close();
    }
  }

  $stmtP = $db->prepare("SELECT photo_id, file_path, uploaded_at FROM inspection_photos WHERE result_id=? ORDER BY photo_id ASC");
  if ($stmtP) {
    $stmtP->bind_param('i', $rid);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    while ($row = $resP->fetch_assoc()) $photos[] = $row;
    $stmtP->close();
  }
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$dt = (string)($schedule['schedule_date'] ?? $schedule['scheduled_at'] ?? '');
$scheduleLabel = $dt !== '' ? date('M d, Y H:i', strtotime($dt)) : '';

$status = (string)($schedule['status'] ?? '');
$overall = $result ? (string)($result['overall_status'] ?? '') : '';
$remarks = $result ? (string)($result['remarks'] ?? '') : '';
$submittedAt = $result ? (string)($result['submitted_at'] ?? '') : '';

$vehiclePlate = $vehicle ? (string)($vehicle['plate_number'] ?? $plate) : $plate;
$operatorName = $vehicle ? (string)($vehicle['operator_name'] ?? '') : '';
$routeId = $vehicle ? (string)($vehicle['route_id'] ?? '') : '';
$certRef = $vehicle ? (string)($vehicle['inspection_cert_ref'] ?? '') : '';
$certInfo = null;
if ($certRef !== '') {
  $hasValidUntil = false;
  $chk = $db->query("SHOW COLUMNS FROM inspection_certificates LIKE 'valid_until'");
  if ($chk && $chk->num_rows > 0) $hasValidUntil = true;
  $stmtC2 = $db->prepare("SELECT certificate_number" . ($hasValidUntil ? ", valid_until" : "") . " FROM inspection_certificates WHERE schedule_id=? LIMIT 1");
  if ($stmtC2) {
    $stmtC2->bind_param('i', $scheduleId);
    $stmtC2->execute();
    $certInfo = $stmtC2->get_result()->fetch_assoc();
    $stmtC2->close();
  }
}

$catFor = function (string $code): string {
  $c = strtoupper(trim($code));
  if (strpos($c, 'RW_') === 0) return 'Roadworthiness (Visual Check)';
  if (strpos($c, 'PS_') === 0) return 'Passenger Safety';
  if (strpos($c, 'SE_') === 0) return 'Safety Equipment (LGU Check)';
  if (strpos($c, 'LGU_') === 0) return 'Operational Compliance (LGU)';
  return 'Other / Legacy Items';
};

$docOnFile = ['cr' => false, 'or' => false, 'insurance' => false, 'emission' => false];
$docMeta = ['cr' => null, 'or' => null, 'insurance' => null, 'emission' => null];
if ($vehicleId > 0 || $plate !== '') {
  $vd = $db->query("SHOW TABLES LIKE 'vehicle_documents'");
  if ($vd && $vd->fetch_row()) {
    $schema = '';
    $schRes = $db->query("SELECT DATABASE() AS db");
    if ($schRes) $schema = (string)(($schRes->fetch_assoc()['db'] ?? '') ?: '');
    $hasCol = function(string $table, string $col) use ($db, $schema): bool {
      if ($schema === '') return false;
      $t = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
      if (!$t) return false;
      $t->bind_param('sss', $schema, $table, $col);
      $t->execute();
      $res = $t->get_result();
      $ok = (bool)($res && $res->fetch_row());
      $t->close();
      return $ok;
    };
    $idCol = $hasCol('vehicle_documents','doc_id') ? 'doc_id' : ($hasCol('vehicle_documents','id') ? 'id' : '');
    $typeCol = $hasCol('vehicle_documents','doc_type') ? 'doc_type' : ($hasCol('vehicle_documents','type') ? 'type' : '');
    $pathCol = $hasCol('vehicle_documents','file_path') ? 'file_path' : '';
    $verCol = $hasCol('vehicle_documents','is_verified') ? 'is_verified'
      : ($hasCol('vehicle_documents','verified') ? 'verified'
      : ($hasCol('vehicle_documents','isApproved') ? 'isApproved' : ''));
    $hasVehId = $hasCol('vehicle_documents','vehicle_id');
    $hasPlate = $hasCol('vehicle_documents','plate_number');
    if ($idCol !== '' && $typeCol !== '' && $pathCol !== '' && ($hasVehId || $hasPlate)) {
      $where = $hasVehId ? "vehicle_id=?" : "plate_number=?";
      $plateKey = $vehiclePlate !== '' ? $vehiclePlate : $plate;
      $stmtD = $db->prepare("SELECT {$idCol} AS id, {$typeCol} AS doc_type, {$pathCol} AS file_path, " . ($verCol !== '' ? "COALESCE({$verCol},0)" : "0") . " AS is_verified FROM vehicle_documents WHERE {$where} ORDER BY {$idCol} DESC");
      if ($stmtD) {
        if ($hasVehId) $stmtD->bind_param('i', $vehicleId);
        else $stmtD->bind_param('s', $plateKey);
        $stmtD->execute();
        $r = $stmtD->get_result();
        while ($r && ($row = $r->fetch_assoc())) {
          $t = strtoupper(trim((string)($row['doc_type'] ?? '')));
          $slot = null;
          if ($t === 'CR' || $t === 'ORCR') $slot = 'cr';
          if ($t === 'OR' || $t === 'ORCR') $slot = 'or';
          if ($t === 'INSURANCE') $slot = 'insurance';
          if ($t === 'EMISSION') $slot = 'emission';
          if ($slot === null) continue;
          if (!$docMeta[$slot]) {
            $docOnFile[$slot] = true;
            $docMeta[$slot] = [
              'id' => (int)($row['id'] ?? 0),
              'doc_type' => $t,
              'file_path' => (string)($row['file_path'] ?? ''),
              'is_verified' => (int)($row['is_verified'] ?? 0),
            ];
          }
        }
        $stmtD->close();
      }
    }
  }
}

if ($format !== 'pdf') {
  header('Content-Type: text/html; charset=utf-8');
  $title = 'Inspection Checklist & Result - SCH-' . (int)$scheduleId;
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($title); ?></title>
    <style>
      body{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f8fafc;color:#0f172a}
      .page{max-width:980px;margin:24px auto;padding:0 20px 32px 20px}
      .topbar{display:flex;align-items:flex-end;justify-content:space-between;gap:12px}
      .title{font-size:22px;font-weight:900;margin:0}
      .sub{color:#64748b;font-size:12px}
      .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px;margin-top:14px}
      .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
      .col{grid-column:span 4}
      .label{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#64748b;font-weight:800}
      .value{margin-top:4px;font-size:13px;font-weight:700;color:#0f172a}
      .badge{display:inline-flex;align-items:center;gap:6px;padding:2px 10px;border-radius:999px;background:#e2e8f0;font-size:12px;font-weight:800;color:#0f172a}
      .badge-pass{background:#dcfce7;color:#166534}
      .badge-fail{background:#fee2e2;color:#b91c1c}
      .badge-pend{background:#fef3c7;color:#92400e}
      a{color:#2563eb;text-decoration:none}
      a:hover{text-decoration:underline}
      table{border-collapse:separate;border-spacing:0;width:100%;margin-top:10px}
      th,td{border-bottom:1px solid #e2e8f0;padding:10px 10px;font-size:13px;vertical-align:top}
      th{background:#f8fafc;text-align:left;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#64748b;font-weight:900}
      .sectionTitle{margin:0 0 6px 0;font-size:14px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#0f172a}
      .muted{color:#64748b}
      .pill{display:inline-flex;align-items:center;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:900}
      .pass{background:#dcfce7;color:#166534}
      .fail{background:#fee2e2;color:#b91c1c}
      .na{background:#e5e7eb;color:#374151}
      .docok{background:#dcfce7;color:#166534}
      .docpend{background:#fef3c7;color:#92400e}
      .docmiss{background:#e5e7eb;color:#374151}
      @media print{
        body{background:#fff}
        .page{margin:0;max-width:none;padding:0 16px 24px 16px}
        .card{break-inside:avoid-page}
      }
    </style>
  </head>
  <body>
    <div class="page">
      <div class="topbar">
        <div>
          <h1 class="title"><?php echo htmlspecialchars($title); ?></h1>
          <div class="sub">Generated: <?php echo htmlspecialchars(date('Y-m-d H:i')); ?></div>
        </div>
        <div>
          <?php
            $bClass = 'badge';
            $ov = strtolower($overall);
            if ($ov === 'passed') $bClass .= ' badge-pass';
            elseif ($ov === 'failed') $bClass .= ' badge-fail';
            elseif ($ov !== '') $bClass .= ' badge-pend';
          ?>
          <span class="<?php echo $bClass; ?>"><?php echo htmlspecialchars($overall !== '' ? $overall : '-'); ?></span>
        </div>
      </div>

      <div class="card">
        <div class="grid">
          <div class="col">
            <div class="label">Schedule</div>
            <div class="value">SCH-<?php echo (int)$scheduleId; ?> <?php if ($scheduleLabel !== ''): ?> <span class="muted">• <?php echo htmlspecialchars($scheduleLabel); ?></span><?php endif; ?></div>
            <div class="label" style="margin-top:10px">Schedule Status</div>
            <div class="value"><span class="badge"><?php echo htmlspecialchars($status !== '' ? $status : '-'); ?></span></div>
          </div>
          <div class="col">
            <div class="label">Plate</div>
            <div class="value"><?php echo htmlspecialchars($vehiclePlate !== '' ? $vehiclePlate : '-'); ?></div>
            <div class="label" style="margin-top:10px">Operator</div>
            <div class="value"><?php echo htmlspecialchars($operatorName !== '' ? $operatorName : '-'); ?></div>
          </div>
          <div class="col">
            <div class="label">Route</div>
            <div class="value"><?php echo htmlspecialchars($routeId !== '' ? $routeId : '-'); ?></div>
            <div class="label" style="margin-top:10px">Inspector</div>
            <div class="value"><?php echo htmlspecialchars($inspectorName !== '' ? $inspectorName : '-'); ?></div>
          </div>
          <div class="col">
            <div class="label">Location</div>
            <div class="value"><?php echo htmlspecialchars((string)($schedule['location'] ?? '-') ?: '-'); ?></div>
            <div class="label" style="margin-top:10px">Inspection Type</div>
            <div class="value"><?php echo htmlspecialchars((string)($schedule['inspection_type'] ?? '-') ?: '-'); ?></div>
          </div>
          <div class="col">
            <div class="label">Submitted At</div>
            <div class="value"><?php echo htmlspecialchars($submittedAt !== '' ? $submittedAt : '-'); ?></div>
            <div class="label" style="margin-top:10px">Certificate Ref</div>
            <div class="value"><?php echo htmlspecialchars($certRef !== '' ? $certRef : '-'); ?></div>
          </div>
          <div class="col">
            <div class="label">Valid Until</div>
            <div class="value"><?php echo htmlspecialchars(($certInfo && !empty($certInfo['valid_until'])) ? (string)$certInfo['valid_until'] : '-'); ?></div>
            <div class="label" style="margin-top:10px">OR/CR</div>
            <div class="value"><?php echo htmlspecialchars($reg ? (string)($reg['orcr_no'] ?? '-') : '-'); ?><?php if ($reg): ?> <span class="muted">• <?php echo htmlspecialchars((string)($reg['orcr_date'] ?? '-') ); ?></span><?php endif; ?></div>
          </div>
        </div>
      </div>

      <!-- Document Presentation intentionally removed; view documents in Vehicle Records -->

      <div class="card">
        <div class="sectionTitle">Checklist</div>
    <?php if (!$checklist): ?>
      <div class="muted">No checklist data.</div>
    <?php else: ?>
      <div class="muted" style="font-size:12px">LGU operational inspection checklist record.</div>
      <?php if (strtolower($overall) === 'failed'): ?>
        <div style="margin-top:10px"><a href="<?php echo htmlspecialchars($rootUrl . '/admin/index.php?page=module4/submodule3&reinspect_of=' . (int)$scheduleId); ?>">Reschedule Reinspection</a></div>
      <?php endif; ?>
      <table>
        <thead>
          <tr><th>Item</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php
            $grouped = [];
            foreach ($checklist as $c) {
              $code = (string)($c['item_code'] ?? '');
              if (strpos(strtoupper(trim($code)), 'DOC_') === 0) continue;
              $cat = $catFor($code);
              if (!isset($grouped[$cat])) $grouped[$cat] = [];
              $grouped[$cat][] = $c;
            }
            $order = [
              'Roadworthiness (Visual Check)',
              'Passenger Safety',
              'Safety Equipment (LGU Check)',
              'Operational Compliance (LGU)'
            ];
            // Sort grouped keys based on $order, putting others at the end
            uksort($grouped, function($a, $b) use ($order) {
              $ia = array_search($a, $order);
              $ib = array_search($b, $order);
              if ($ia !== false && $ib !== false) return $ia - $ib;
              if ($ia !== false) return -1;
              if ($ib !== false) return 1;
              return strcasecmp($a, $b);
            });
          ?>
          <?php foreach ($grouped as $cat => $rows): ?>
            <tr><th colspan="2" style="background:#f8fafc"><?php echo htmlspecialchars($cat); ?></th></tr>
            <?php foreach ($rows as $c): ?>
              <?php
                $label = (string)($c['item_label'] ?? '');
                if ($label === '') $label = (string)($c['item_code'] ?? '');
                $stRaw = strtoupper(trim((string)($c['status'] ?? '')));
                $stClass = 'pill na';
                $stText = $stRaw !== '' ? $stRaw : 'NA';
                if ($stRaw === 'PASS') $stClass = 'pill pass';
                elseif ($stRaw === 'FAIL') $stClass = 'pill fail';
              ?>
              <tr>
                <td><?php echo htmlspecialchars($label); ?></td>
                <td><span class="<?php echo $stClass; ?>"><?php echo htmlspecialchars($stText); ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
      </div>

      <div class="card">
        <div class="sectionTitle">Remarks</div>
        <div style="font-size:13px;line-height:1.5"><?php echo nl2br(htmlspecialchars($remarks !== '' ? $remarks : '-')); ?></div>
      </div>

      <div class="card">
        <div class="sectionTitle">Photos</div>
        <?php if (!$photos): ?>
          <div class="muted">No photos uploaded.</div>
        <?php else: ?>
          <table>
            <thead>
              <tr><th style="width:70%">File</th><th>Uploaded</th></tr>
            </thead>
            <tbody>
              <?php foreach ($photos as $p): ?>
                <?php
                  $path = (string)($p['file_path'] ?? '');
                  $url = $path !== '' ? ($rootUrl . '/admin/uploads/' . ltrim($path, '/')) : '';
                  $at = (string)($p['uploaded_at'] ?? '');
                ?>
                <tr>
                  <td><?php echo $url !== '' ? ('<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . htmlspecialchars(basename($path)) . '</a>') : htmlspecialchars(basename($path)); ?></td>
                  <td><?php echo htmlspecialchars($at !== '' ? $at : '-'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </body>
  </html>
  <?php
  exit;
}

$title = 'Inspection Checklist & Result - SCH-' . (int)$scheduleId;
$org = 'Transport & Mobility Management';
$logoFs = __DIR__ . '/../../includes/GSM_logo.png';
$officeAddr = trim((string)(tmm_get_app_setting('office_address', '1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.') ?? ''));
$generatedAt = date('M d, Y H:i');

$vehType = $vehicle ? trim((string)($vehicle['vehicle_type'] ?? '')) : '';
$vehMake = $vehicle ? trim((string)($vehicle['make'] ?? '')) : '';
$vehModel = $vehicle ? trim((string)($vehicle['model'] ?? '')) : '';
$vehYear = $vehicle ? trim((string)($vehicle['year_model'] ?? '')) : '';
$vehColor = $vehicle ? trim((string)($vehicle['color'] ?? '')) : '';
$vehEngine = $vehicle ? trim((string)($vehicle['engine_no'] ?? '')) : '';
$vehChassis = $vehicle ? trim((string)($vehicle['chassis_no'] ?? '')) : '';
$vehOwner = $vehicle ? trim((string)($vehicle['registered_owner'] ?? '')) : '';
$vehCoop = $vehicle ? trim((string)($vehicle['coop_name'] ?? '')) : '';
$vehFranchise = $vehicle ? trim((string)($vehicle['franchise_id'] ?? '')) : '';

$vehDescParts = [];
if ($vehType !== '') $vehDescParts[] = $vehType;
if ($vehMake !== '' || $vehModel !== '') $vehDescParts[] = trim($vehMake . ' ' . $vehModel);
if ($vehYear !== '') $vehDescParts[] = $vehYear;
if ($vehColor !== '') $vehDescParts[] = $vehColor;
$vehDesc = $vehDescParts ? implode(' • ', $vehDescParts) : '';

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

$logoJpeg = null;
$logoW = 0;
$logoH = 0;
if (is_file($logoFs) && function_exists('imagecreatefrompng')) {
  $im = @imagecreatefrompng($logoFs);
  if ($im) {
    $logoW = (int)imagesx($im);
    $logoH = (int)imagesy($im);
    ob_start();
    imagejpeg($im, null, 85);
    $logoJpeg = ob_get_clean();
    imagedestroy($im);
  }
}

$pageWidth = 595;
$pageHeight = 842;
$mLeft = 40;
$mRight = 40;
$mTop = 40;
$mBottom = 42;
$contentPages = [];
$pageNo = 0;
$content = '';
$y = $pageHeight - $mTop;

$setFill = function (int $r, int $g, int $b) {
  return (sprintf('%.3f %.3f %.3f rg', $r / 255, $g / 255, $b / 255)) . "\n";
};
$setStroke = function (int $r, int $g, int $b) {
  return (sprintf('%.3f %.3f %.3f RG', $r / 255, $g / 255, $b / 255)) . "\n";
};
$drawLine = function (float $x1, float $y1, float $x2, float $y2, float $w = 1.0) {
  return $w . " w\n" . $x1 . " " . $y1 . " m\n" . $x2 . " " . $y2 . " l\nS\n";
};
$drawRect = function (float $x, float $y, float $w, float $h, bool $fill = false, bool $stroke = true) {
  $op = '';
  if ($fill && $stroke) $op = "B";
  elseif ($fill) $op = "f";
  else $op = "S";
  return $x . " " . $y . " " . $w . " " . $h . " re\n" . $op . "\n";
};
$wrap = function (string $text, int $maxChars): array {
  $t = trim(preg_replace('/\s+/', ' ', (string)$text));
  if ($t === '') return ['-'];
  $words = preg_split('/\s+/', $t) ?: [];
  $lines = [];
  $cur = '';
  foreach ($words as $w) {
    $test = $cur === '' ? $w : ($cur . ' ' . $w);
    if (strlen($test) <= $maxChars) {
      $cur = $test;
      continue;
    }
    if ($cur !== '') $lines[] = $cur;
    $cur = $w;
  }
  if ($cur !== '') $lines[] = $cur;
  return $lines ?: ['-'];
};
$text = function (float $x, float $y, string $font, int $size, string $txt, int $r = 15, int $g = 23, int $b = 42) use ($pdfEsc, $setFill) {
  return $setFill($r, $g, $b) . "BT\n/{$font} {$size} Tf\n1 0 0 1 {$x} {$y} Tm\n(" . $pdfEsc($txt) . ") Tj\nET\n";
};

$startPage = function () use (&$contentPages, &$content, &$y, &$pageNo, $pageHeight, $mTop, $mLeft, $pageWidth, $mRight, $drawLine, $setStroke, $text, $logoJpeg, $logoW, $logoH, $org, $title, $officeAddr, $generatedAt, $overall) {
  if ($content !== '') $contentPages[] = $content;
  $content = '';
  $pageNo++;
  $y = $pageHeight - $mTop;
  $content .= $setStroke(226, 232, 240);
  $logoBox = 40;
  if ($logoJpeg && $logoW > 0 && $logoH > 0) {
    $content .= "q\n{$logoBox} 0 0 {$logoBox} {$mLeft} " . ($y - $logoBox) . " cm\n/Im1 Do\nQ\n";
  }
  $xText = $mLeft + ($logoJpeg ? ($logoBox + 12) : 0);
  $content .= $text($xText, $y - 18, 'F2', 14, $org);
  if ($officeAddr !== '') $content .= $text($xText, $y - 32, 'F1', 9, $officeAddr, 71, 85, 105);
  $content .= $text($xText, $y - 52, 'F2', 16, $title);
  $badge = strtoupper($overall !== '' ? $overall : 'N/A');
  $badgeColor = $badge === 'PASSED' ? [22, 101, 52] : ($badge === 'FAILED' ? [185, 28, 28] : [146, 64, 14]);
  $content .= $text($pageWidth - $mRight - 140, $y - 52, 'F2', 12, $badge, $badgeColor[0], $badgeColor[1], $badgeColor[2]);
  $content .= $text($pageWidth - $mRight - 140, $y - 66, 'F1', 9, 'Generated: ' . $generatedAt, 71, 85, 105);
  $content .= $drawLine($mLeft, $y - 76, $pageWidth - $mRight, $y - 76, 1);
  $y = $y - 92;
};

$startPage();

$sectionTitle = function (string $label) use (&$content, &$y, $mLeft, $pageWidth, $mRight, $mBottom, $setFill, $setStroke, $drawRect, $text, $wrap, $startPage) {
  if ($y < $mBottom + 60) $startPage();
  $content .= $setStroke(226, 232, 240);
  $content .= $setFill(248, 250, 252);
  $content .= $drawRect($mLeft, $y - 22, ($pageWidth - $mLeft - $mRight), 22, true, true);
  $content .= $text($mLeft + 10, $y - 16, 'F2', 10, strtoupper($label), 30, 41, 59);
  $y -= 34;
};

$kvBlock = function (array $rows, int $cols = 2) use (&$content, &$y, $mLeft, $pageWidth, $mRight, $mBottom, $setStroke, $setFill, $drawRect, $text, $wrap, $startPage) {
  $w = $pageWidth - $mLeft - $mRight;
  $colW = $cols > 1 ? ($w / $cols) : $w;
  $lineH = 13;
  $maxChars = (int)floor(($colW - 20) / 5.6);
  $rowLines = [];
  $maxRowH = 0;
  foreach ($rows as $row) {
    $valLines = $wrap((string)($row[1] ?? ''), max(20, $maxChars));
    $rowLines[] = $valLines;
    $rh = max(1, count($valLines)) * $lineH + 16;
    if ($rh > $maxRowH) $maxRowH = $rh;
  }
  $needH = $maxRowH * (int)ceil(count($rows) / $cols) + 14;
  if ($y < $mBottom + $needH) $startPage();
  $content .= $setStroke(226, 232, 240);
  $content .= $setFill(255, 255, 255);
  $content .= $drawRect($mLeft, $y - $needH, $w, $needH, true, true);
  $x0 = $mLeft + 10;
  $y0 = $y - 18;
  for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];
    $c = $i % $cols;
    $rr = (int)floor($i / $cols);
    $x = $x0 + $c * $colW;
    $yy = $y0 - $rr * $maxRowH;
    $content .= $text($x, $yy, 'F2', 8, strtoupper((string)($r[0] ?? '')), 100, 116, 139);
    $valLines = $rowLines[$i];
    $vy = $yy - 12;
    foreach ($valLines as $ln) {
      $content .= $text($x, $vy, 'F1', 11, (string)$ln);
      $vy -= $lineH;
    }
  }
  $y -= ($needH + 16);
};

$sectionTitle('Operator and Vehicle');
$kv = [];
$kv[] = ['Operator Name', $operatorName !== '' ? $operatorName : '-'];
if ($vehCoop !== '') $kv[] = ['Cooperative', $vehCoop];
if ($vehFranchise !== '') $kv[] = ['Franchise ID', $vehFranchise];
if ($routeId !== '') $kv[] = ['Route', $routeId];
if ($vehOwner !== '') $kv[] = ['Registered Owner', $vehOwner];
$kv[] = ['Plate Number', $vehiclePlate !== '' ? $vehiclePlate : '-'];
if ($vehDesc !== '') $kv[] = ['Vehicle Details', $vehDesc];
if ($vehEngine !== '') $kv[] = ['Engine No.', $vehEngine];
if ($vehChassis !== '') $kv[] = ['Chassis No.', $vehChassis];
$kvBlock($kv, 2);

$sectionTitle('Inspection Details');
$det = [];
$det[] = ['Schedule', 'SCH-' . (int)$scheduleId . ($scheduleLabel !== '' ? (' • ' . $scheduleLabel) : '')];
$det[] = ['Location', (string)($schedule['location'] ?? '-') ?: '-'];
$det[] = ['Inspection Type', (string)($schedule['inspection_type'] ?? '-') ?: '-'];
$det[] = ['Inspector', $inspectorName !== '' ? $inspectorName : '-'];
$det[] = ['Schedule Status', $status !== '' ? $status : '-'];
$det[] = ['Submitted At', $submittedAt !== '' ? $submittedAt : '-'];
if ($certRef !== '') $det[] = ['Certificate Ref', $certRef];
if ($certInfo && !empty($certInfo['valid_until'])) $det[] = ['Valid Until', (string)$certInfo['valid_until']];
$kvBlock($det, 2);

$sectionTitle('Checklist and Result');
if (!$checklist) {
  $kvBlock([['Checklist', 'No checklist data.']], 1);
} else {
  $grouped = [];
  foreach ($checklist as $c) {
    $code = (string)($c['item_code'] ?? '');
    if (strpos(strtoupper(trim($code)), 'DOC_') === 0) continue;
    $cat = $catFor($code);
    if (!isset($grouped[$cat])) $grouped[$cat] = [];
    $grouped[$cat][] = $c;
  }
  $order = [
    'Roadworthiness (Visual Check)',
    'Passenger Safety',
    'Safety Equipment (LGU Check)',
    'Operational Compliance (LGU)'
  ];
  uksort($grouped, function($a, $b) use ($order) {
    $ia = array_search($a, $order);
    $ib = array_search($b, $order);
    if ($ia !== false && $ib !== false) return $ia - $ib;
    if ($ia !== false) return -1;
    if ($ib !== false) return 1;
    return strcasecmp($a, $b);
  });

  foreach ($grouped as $cat => $rows) {
    $sectionTitle($cat);
    foreach ($rows as $c) {
      $label = (string)($c['item_label'] ?? '');
      if ($label === '') $label = (string)($c['item_code'] ?? '');
      $stRaw = strtoupper(trim((string)($c['status'] ?? '')));
      if ($stRaw === '') $stRaw = 'NA';
      $stColor = $stRaw === 'PASS' ? [22, 101, 52] : ($stRaw === 'FAIL' ? [185, 28, 28] : [55, 65, 81]);
      $w = $pageWidth - $mLeft - $mRight;
      $labelMaxChars = (int)floor(($w - 120) / 5.6);
      $labelLines = $wrap($label, max(24, $labelMaxChars));
      $rowH = max(1, count($labelLines)) * 13 + 10;
      if ($y < $mBottom + $rowH + 20) $startPage();
      $content .= $setStroke(226, 232, 240);
      $content .= $drawLine($mLeft, $y, $pageWidth - $mRight, $y, 1);
      $yy = $y - 18;
      foreach ($labelLines as $ln) {
        $content .= $text($mLeft + 10, $yy, 'F1', 10, (string)$ln);
        $yy -= 13;
      }
      $content .= $text($pageWidth - $mRight - 70, $y - 18, 'F2', 10, $stRaw, $stColor[0], $stColor[1], $stColor[2]);
      $y -= $rowH;
    }
    $content .= $setStroke(226, 232, 240);
    $content .= $drawLine($mLeft, $y, $pageWidth - $mRight, $y, 1);
    $y -= 14;
  }
}

$sectionTitle('Remarks');
$rem = $remarks !== '' ? $remarks : '-';
$remLines = $wrap($rem, 110);
foreach ($remLines as $ln) {
  if ($y < $mBottom + 24) $startPage();
  $content .= $text($mLeft + 10, $y, 'F1', 10, (string)$ln, 30, 41, 59);
  $y -= 13;
}

if ($content !== '') $contentPages[] = $content;
if (!$contentPages) $contentPages[] = $text($mLeft, $pageHeight - $mTop - 40, 'F1', 12, 'No data.');

$objects = [];
$addObj = function ($body) use (&$objects) {
  $objects[] = (string)$body;
  return count($objects);
};

$catalogId = $addObj('');
$pagesId = $addObj('');
$font1Id = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
$font2Id = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>");
$imgId = 0;
if ($logoJpeg && $logoW > 0 && $logoH > 0) {
  $imgId = $addObj("<< /Type /XObject /Subtype /Image /Width {$logoW} /Height {$logoH} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($logoJpeg) . " >>\nstream\n" . $logoJpeg . "\nendstream");
}

$pageObjIds = [];
foreach ($contentPages as $c) {
  $contentObjId = $addObj("<< /Length " . strlen($c) . " >>\nstream\n" . $c . "endstream");
  $resParts = [];
  $resParts[] = "/Font << /F1 {$font1Id} 0 R /F2 {$font2Id} 0 R >>";
  if ($imgId > 0) $resParts[] = "/XObject << /Im1 {$imgId} 0 R >>";
  $resources = "<< " . implode(' ', $resParts) . " >>";
  $pageObjId = $addObj("<< /Type /Page /Parent {$pagesId} 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources {$resources} /Contents {$contentObjId} 0 R >>");
  $pageObjIds[] = $pageObjId;
}

$kids = implode(' ', array_map(fn($id) => $id . " 0 R", $pageObjIds));
$objects[$pagesId - 1] = "<< /Type /Pages /Count " . count($pageObjIds) . " /Kids [ {$kids} ] >>";
$objects[$catalogId - 1] = "<< /Type /Catalog /Pages {$pagesId} 0 R >>";

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
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalogId} 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="inspection_report_sch_' . (int)$scheduleId . '_' . date('Ymd_His') . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
