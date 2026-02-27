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
$vehicle = null;
if ($vehicleId > 0) {
  $stmtV = $db->prepare("SELECT id, plate_number, operator_name, coop_name, franchise_id, route_id, inspection_status, inspection_cert_ref, inspection_passed_at FROM vehicles WHERE id=? LIMIT 1");
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

      <div class="card">
        <div class="sectionTitle">Document Presentation</div>
        <div class="muted" style="font-size:12px">Status reflects uploads in Vehicle Records.</div>
        <table>
          <thead>
            <tr><th style="width:28%">Document</th><th style="width:32%">Status</th><th>File</th></tr>
          </thead>
          <tbody>
            <?php
              $docRows = [
                'cr' => 'Certificate of Registration (CR)',
                'or' => 'Official Receipt (OR)',
                'emission' => 'CMVI / PMVIC Certificate',
                'insurance' => 'CTPL Insurance',
              ];
              foreach ($docRows as $k => $lbl):
                $m = $docMeta[$k];
                $on = !empty($docOnFile[$k]);
                $isV = $m && ((int)($m['is_verified'] ?? 0) === 1);
                $cls = $on ? ($isV ? 'pill docok' : 'pill docpend') : 'pill docmiss';
                $txt = $on ? ($isV ? 'On file • Verified' : 'On file • Pending verification') : 'Missing';
                $path = $m ? (string)($m['file_path'] ?? '') : '';
                $url = $path !== '' ? ($rootUrl . '/admin/uploads/' . ltrim($path, '/')) : '';
            ?>
              <tr>
                <td style="font-weight:900"><?php echo htmlspecialchars($lbl); ?></td>
                <td><span class="<?php echo $cls; ?>"><?php echo htmlspecialchars($txt); ?></span></td>
                <td><?php echo $url !== '' ? ('<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . htmlspecialchars(basename($path)) . '</a>') : '<span class="muted">-</span>'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

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

$title = 'Inspection Checklist & Result SCH-' . (int)$scheduleId;
$lines = [];
$lines[] = $title;
$lines[] = 'Generated: ' . date('Y-m-d H:i');
$lines[] = 'Plate: ' . ($vehiclePlate !== '' ? $vehiclePlate : '-');
$lines[] = 'Operator: ' . ($operatorName !== '' ? $operatorName : '-');
$lines[] = 'Route: ' . ($routeId !== '' ? $routeId : '-');
$lines[] = 'Inspector: ' . ($inspectorName !== '' ? $inspectorName : '-');
$lines[] = 'Schedule: ' . ($scheduleLabel !== '' ? $scheduleLabel : '-') . ' • Status: ' . ($status !== '' ? $status : '-');
$lines[] = 'Location: ' . ((string)($schedule['location'] ?? '-'));
$lines[] = 'Overall Result: ' . ($overall !== '' ? $overall : '-') . ' • Submitted: ' . ($submittedAt !== '' ? $submittedAt : '-');
if ($certRef !== '') $lines[] = 'Certificate Ref: ' . $certRef;
if ($certInfo && !empty($certInfo['valid_until'])) $lines[] = 'Valid Until: ' . (string)$certInfo['valid_until'];
$lines[] = str_repeat('-', 94);
$lines[] = 'DOCUMENT PRESENTATION';
$lines[] = str_repeat('-', 94);
$docRows = [
  'cr' => 'Certificate of Registration (CR)',
  'or' => 'Official Receipt (OR)',
  'emission' => 'CMVI / PMVIC Certificate',
  'insurance' => 'CTPL Insurance',
];
foreach ($docRows as $k => $lbl) {
  $m = $docMeta[$k];
  $on = !empty($docOnFile[$k]);
  $isV = $m && ((int)($m['is_verified'] ?? 0) === 1);
  $txt = $on ? ($isV ? 'ON FILE (VERIFIED)' : 'ON FILE (PENDING)') : 'MISSING';
  $lines[] = sprintf("%-60s %s", substr($lbl, 0, 60), $txt);
}
$lines[] = '';
$lines[] = str_repeat('-', 94);
$lines[] = 'CHECKLIST';
$lines[] = str_repeat('-', 94);
$lines[] = 'Rule: Overall PASSED requires required LGU items to be PASS (not N/A).';
$lines[] = 'If FAILED: Proceed to correction, then reschedule as REINSPECTION.';
$lines[] = '';
if ($checklist) {
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
    $lines[] = strtoupper($cat);
    foreach ($rows as $c) {
      $lbl = ((string)($c['item_label'] ?? '') !== '' ? (string)$c['item_label'] : (string)($c['item_code'] ?? ''));
      $st = (string)($c['status'] ?? '');
      $lines[] = sprintf("%-70s %s", substr($lbl, 0, 70), substr($st, 0, 20));
    }
    $lines[] = '';
  }
} else {
  $lines[] = 'No checklist data.';
}
$lines[] = str_repeat('-', 94);
$lines[] = 'REMARKS';
$lines[] = str_repeat('-', 94);
if ($remarks !== '') {
  foreach (preg_split("/\r\n|\n|\r/", $remarks) as $rl) {
    $lines[] = substr((string)$rl, 0, 94);
  }
} else {
  $lines[] = '-';
}

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
if (!$pages) $pages[] = ['No data.'];

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

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="inspection_report_sch_' . (int)$scheduleId . '_' . date('Ymd_His') . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
