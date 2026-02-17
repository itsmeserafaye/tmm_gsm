<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module4.read','reports.export']);
$db = db();
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};
$vrHasOrNo = $hasCol('vehicle_registrations', 'or_number');
$vrHasOrDate = $hasCol('vehicle_registrations', 'or_date');
$vrHasOrExp = $hasCol('vehicle_registrations', 'or_expiry_date');
$vrHasRegYear = $hasCol('vehicle_registrations', 'registration_year');

$orNoSel = $vrHasOrNo ? "COALESCE(NULLIF(vr.or_number,''), vr.orcr_no) AS or_number" : "vr.orcr_no AS or_number";
$orDateSel = $vrHasOrDate ? "COALESCE(NULLIF(vr.or_date,''), vr.orcr_date) AS or_date" : "vr.orcr_date AS or_date";
$orExpSel = $vrHasOrExp ? "vr.or_expiry_date AS or_expiry_date" : "'' AS or_expiry_date";
$regYearSel = $vrHasRegYear ? "vr.registration_year AS registration_year" : "'' AS registration_year";

$sql = "SELECT v.plate_number, v.status AS vehicle_status, vr.registration_status, {$orNoSel}, {$orDateSel}, {$orExpSel}, {$regYearSel}, vr.created_at
        FROM vehicles v
        LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id";
$conds = [];
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $conds[] = "(v.plate_number LIKE '%$qv%' OR v.engine_no LIKE '%$qv%' OR v.chassis_no LIKE '%$qv%')";
}
if ($status === 'Not Registered') {
  $conds[] = "(vr.registration_status IS NULL OR vr.registration_status='')";
} elseif ($status !== '' && in_array($status, ['Registered','Pending','Expired'], true)) {
  $sv = $db->real_escape_string($status);
  $conds[] = "vr.registration_status='$sv'";
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY COALESCE(vr.created_at, v.created_at) DESC LIMIT 1000";
$res = $db->query($sql);

header('Content-Type: text/html; charset=utf-8');
$logo = $rootUrl . '/admin/includes/GSM_logo.png';
$now = date('M d, Y H:i');
$year = date('Y');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Vehicle Registration Report</title>
  <style>
    *{box-sizing:border-box}
    :root{--footer-height:18mm}
    @page{margin:16mm 12mm 22mm 12mm}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;color:#0f172a;margin:0}
    .wrap{padding:16px 16px calc(var(--footer-height) + 12px) 16px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #e2e8f0;padding:8px;font-size:12px}
    th{background:#f8fafc;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#334155}
    thead{display:table-header-group}
    tbody tr{page-break-inside:avoid;break-inside:avoid}
    .footer{border-top:2px solid #e2e8f0;padding:6px 16px;font-size:12px;color:#475569;text-align:center;position:fixed;left:0;right:0;bottom:0;height:var(--footer-height);background:#fff}
    .logo{width:40px;height:40px;border-radius:8px;object-fit:cover}
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
          <th colspan="5" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub">Vehicle Registration Report</div>
                <div class="filters">Generated: <?php echo htmlspecialchars($now); ?> • Search: <?php echo htmlspecialchars($q ?: '-'); ?> • Status: <?php echo htmlspecialchars($status ?: 'All'); ?></div>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <th style="width:16%">Plate</th>
          <th style="width:26%">OR / Dates</th>
          <th style="width:18%">Registration</th>
          <th style="width:18%">Vehicle Status</th>
          <th style="width:22%">Created</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($row = $res->fetch_assoc()): ?>
            <?php
              $orNo = trim((string)($row['or_number'] ?? ''));
              $od = (string)($row['or_date'] ?? '');
              $oe = (string)($row['or_expiry_date'] ?? '');
              $ry = trim((string)($row['registration_year'] ?? ''));
              $parts = [];
              if ($orNo !== '') $parts[] = $orNo;
              if ($od !== '') $parts[] = $od;
              if ($oe !== '') $parts[] = 'Exp: ' . $oe;
              if ($ry !== '') $parts[] = 'Year: ' . $ry;
              $created = !empty($row['created_at']) ? date('M d, Y', strtotime((string)$row['created_at'])) : '-';
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($row['plate_number'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars($parts ? implode(' • ', $parts) : '-'); ?></td>
              <td><?php echo htmlspecialchars((string)($row['registration_status'] ?? 'Not Registered')); ?></td>
              <td><?php echo htmlspecialchars((string)($row['vehicle_status'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars($created); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" class="py-6 text-center text-slate-500">No records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="footer">Transport & Mobility Management • LGU Permitted • © <?php echo htmlspecialchars($year); ?></div>
</body>
</html>

