<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write','reports.export']);
$db = db();
$q = trim((string)($_GET['q'] ?? ''));
$vehicleType = trim((string)($_GET['vehicle_type'] ?? ''));
$recordStatus = trim((string)($_GET['record_status'] ?? ''));
$docuStatus = trim((string)($_GET['docu_status'] ?? ''));
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
$schema = '';
$schRes = $db->query("SELECT DATABASE() AS db");
if ($schRes) $schema = (string)(($schRes->fetch_assoc()['db'] ?? '') ?: '');
$hasCol = function (string $table, string $col) use ($db, $schema): bool {
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
$vdHasVehicleId = $hasCol('vehicle_documents', 'vehicle_id');
$vdHasPlate = $hasCol('vehicle_documents', 'plate_number');
$vdHasIsVerified = $hasCol('vehicle_documents', 'is_verified');
$vdHasVerifiedLegacy = $hasCol('vehicle_documents', 'verified');
$vdHasExpiry = $hasCol('vehicle_documents', 'expiry_date');
$vdVerifiedCol = $vdHasIsVerified ? 'is_verified' : ($vdHasVerifiedLegacy ? 'verified' : '');
$vdMatch = "1=0";
if ($vdHasVehicleId && $vdHasPlate) $vdMatch = "(vd2.vehicle_id=v.id OR ((vd2.vehicle_id IS NULL OR vd2.vehicle_id=0) AND vd2.plate_number=v.plate_number))";
elseif ($vdHasVehicleId) $vdMatch = "vd2.vehicle_id=v.id";
elseif ($vdHasPlate) $vdMatch = "vd2.plate_number=v.plate_number";
$vdVerifiedExpr = $vdVerifiedCol !== '' ? "COALESCE(vd2.$vdVerifiedCol,0)" : "0";
$expiredExpr = $vdHasExpiry ? "MAX(CASE WHEN UPPER(vd2.doc_type) IN ('OR','INSURANCE') AND vd2.expiry_date IS NOT NULL AND vd2.expiry_date < CURDATE() THEN 1 ELSE 0 END)" : "0";
$docuStatusExpr = ($vdHasVehicleId || $vdHasPlate) ? "(SELECT
  CASE
    WHEN MAX(CASE WHEN UPPER(vd2.doc_type)='CR' THEN 1 ELSE 0 END)=0
      OR MAX(CASE WHEN UPPER(vd2.doc_type)='OR' THEN 1 ELSE 0 END)=0
      OR MAX(CASE WHEN UPPER(vd2.doc_type)='INSURANCE' THEN 1 ELSE 0 END)=0
    THEN 'Pending Upload'
    WHEN $expiredExpr=1 THEN 'Expired'
    WHEN MAX(CASE WHEN UPPER(vd2.doc_type)='CR' THEN $vdVerifiedExpr ELSE 0 END)=1
      AND MAX(CASE WHEN UPPER(vd2.doc_type)='OR' THEN $vdVerifiedExpr ELSE 0 END)=1
      AND MAX(CASE WHEN UPPER(vd2.doc_type)='INSURANCE' THEN $vdVerifiedExpr ELSE 0 END)=1
    THEN 'Verified'
    ELSE 'For Review'
  END
  FROM vehicle_documents vd2
  WHERE $vdMatch
)" : "'-'";
$sql = "SELECT v.id AS vehicle_id,
               v.plate_number,
               v.vehicle_type,
               v.operator_id,
               COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '') AS operator_display,
               v.record_status,
               CASE WHEN v.record_status='Linked' AND (COALESCE(v.operator_id,0)=0 OR o.id IS NULL) THEN 'Encoded' ELSE v.record_status END AS record_status_effective,
               $docuStatusExpr AS docu_status,
               v.inspection_status,
               v.franchise_id,
               vr.registration_status,
               vr.orcr_no,
               vr.orcr_date,
               fa.status AS franchise_app_status
        FROM vehicles v
        LEFT JOIN operators o ON o.id=v.operator_id
        LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id
        LEFT JOIN franchise_applications fa ON fa.franchise_ref_number=v.franchise_id
        WHERE 1=1";
$params = [];
$typestr = '';
if ($q !== '') {
  $sql .= " AND (v.plate_number LIKE ? OR v.operator_name LIKE ? OR o.name LIKE ? OR o.full_name LIKE ?)";
  $like = "%$q%";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $typestr .= 'ssss';
}
if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
  $sql .= " AND v.vehicle_type=?";
  $params[] = $vehicleType;
  $typestr .= 's';
}
if ($recordStatus !== '' && $recordStatus !== 'Record status') {
  if ($recordStatus === 'Linked') {
    $sql .= " AND v.record_status='Linked' AND COALESCE(v.operator_id,0)<>0 AND o.id IS NOT NULL";
  } elseif ($recordStatus === 'Encoded') {
    $sql .= " AND (v.record_status='Encoded' OR (v.record_status='Linked' AND (COALESCE(v.operator_id,0)=0 OR o.id IS NULL)))";
  } else {
    $sql .= " AND v.record_status=?";
    $params[] = $recordStatus;
    $typestr .= 's';
  }
}
if ($docuStatus !== '' && $docuStatus !== 'Docu status') {
  $sql .= " AND $docuStatusExpr=?";
  $params[] = $docuStatus;
  $typestr .= 's';
}
$sql .= " ORDER BY v.created_at DESC LIMIT 1000";
$res = null;
if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($typestr, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}
header('Content-Type: text/html; charset=utf-8');
$logo = $rootUrl . '/admin/includes/GSM_logo.png';
$now = date('M d, Y H:i');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Vehicles Report</title>
  <style>
    *{box-sizing:border-box}
    :root{--footer-height:18mm}
    @page{margin:16mm 12mm 22mm 12mm}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;color:#0f172a;margin:0}
    .wrap{padding:16px 16px calc(var(--footer-height) + 12px) 16px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #e2e8f0;padding:8px;font-size:12px}
    th{background:#f8fafc;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#334155}
    .logo{width:40px;height:40px;border-radius:8px;object-fit:cover}
    thead{display:table-header-group}
    tbody tr{page-break-inside:avoid;break-inside:avoid}
    .footer{border-top:2px solid #e2e8f0;padding:6px 16px;font-size:12px;color:#475569;text-align:center;position:fixed;left:0;right:0;bottom:0;height:var(--footer-height);background:#fff}
    .rhead{display:flex;align-items:center;justify-content:center;gap:12px;text-align:center;padding:8px 0}
    .rtitle{display:flex;flex-direction:column;align-items:center}
    .rtitle .title{margin:0;font-weight:900;font-size:18px;letter-spacing:.08em;text-transform:uppercase}
    .rtitle .sub{font-weight:700;color:#334155}
    .rtitle .filters{font-size:12px;color:#475569;margin-top:4px}
    @media print{
      body{margin:0}
      .wrap{padding:0 12mm calc(var(--footer-height) + 4mm) 12mm}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <table>
      <thead>
        <tr>
          <th colspan="6" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub">Vehicles Report</div>
                <div class="filters">Generated: <?php echo htmlspecialchars($now); ?> • Search: <?php echo htmlspecialchars($q ?: '-'); ?> • Type: <?php echo htmlspecialchars($vehicleType ?: 'All'); ?> • Record Status: <?php echo htmlspecialchars($recordStatus ?: 'All'); ?> • DOCU: <?php echo htmlspecialchars($docuStatus ?: 'All'); ?></div>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <th style="width:12%">Plate</th>
          <th style="width:12%">Type</th>
          <th style="width:30%">Operator</th>
          <th style="width:16%">Status</th>
          <th style="width:16%">Record Status</th>
          <th style="width:14%">Docu</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($row = $res->fetch_assoc()): ?>
            <?php
              $rs = (string)($row['record_status_effective'] ?? ($row['record_status'] ?? ''));
              if ($rs === '') $rs = 'Encoded';
              $insp = (string)($row['inspection_status'] ?? '');
              $frAppSt = (string)($row['franchise_app_status'] ?? '');
              $regSt = (string)($row['registration_status'] ?? '');
              $orcrNo = trim((string)($row['orcr_no'] ?? ''));
              $orcrDate = trim((string)($row['orcr_date'] ?? ''));
              $frOk = in_array($frAppSt, ['Approved', 'LTFRB-Approved'], true);
              $inspOk = $insp === 'Passed';
              $regOk = in_array($regSt, ['Registered', 'Recorded'], true) && $orcrNo !== '' && $orcrDate !== '';
              $st = 'Declared';
              if ($rs === 'Archived') $st = 'Archived';
              elseif ($frOk && $inspOk && $regOk) $st = 'Active';
              elseif ($inspOk && $regOk) $st = 'Registered';
              elseif ($inspOk) $st = 'Inspected';
              elseif ($rs === 'Linked') $st = 'Pending Inspection';
              $docu = trim((string)($row['docu_status'] ?? ''));
              if ($docu === '') $docu = '-';
            ?>
            <tr>
              <td><?php echo htmlspecialchars(strtoupper((string)($row['plate_number'] ?? '')), ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)($row['vehicle_type'] ?? ''), ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)($row['operator_display'] ?? ''), ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($st, ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($rs, ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($docu, ENT_QUOTES); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align:center;color:#64748b;font-weight:700;padding:18px">No records found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="footer">
    <div>Transport & Mobility Management • LGU Permitted • © <?php echo date('Y'); ?></div>
  </div>
  <script>
    (function(){
      try{ window.print(); }catch(e){}
      function tryClose(){ try{ if (window.opener && !window.opener.closed) window.close(); }catch(e){} }
      if ('onafterprint' in window) window.addEventListener('afterprint', function(){ setTimeout(tryClose, 50); });
      if (window.matchMedia) {
        var mql = window.matchMedia('print');
        if (mql) {
          if (mql.addEventListener) mql.addEventListener('change', function(e){ if (!e.matches) setTimeout(tryClose, 50); });
          else if (mql.addListener) mql.addListener(function(m){ if (!m.matches) setTimeout(tryClose, 50); });
        }
      }
    })();
  </script>
</body>
</html>
