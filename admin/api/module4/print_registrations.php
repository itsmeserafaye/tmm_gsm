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
$pb_name = trim((string)($_GET['pb_name'] ?? ''));
$pb_dept = trim((string)($_GET['pb_dept'] ?? ''));
$rc_name = trim((string)($_GET['rc_name'] ?? ''));
$rc_pos = trim((string)($_GET['rc_pos'] ?? ''));
$rc_dept = trim((string)($_GET['rc_dept'] ?? ''));
$rep_title = trim((string)($_GET['rep_title'] ?? 'Vehicle Registration Report'));
$office_addr = trim((string)(tmm_get_app_setting('office_address','1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.') ?? '1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.'));
$office_email = trim((string)(tmm_get_app_setting('office_email','helpdesk@tmm.gov.ph') ?? 'helpdesk@tmm.gov.ph'));
$office_contact = trim((string)(tmm_get_app_setting('office_contact','') ?? ''));
$public_site = trim((string)(tmm_get_app_setting('public_website','tmm.govservph.com') ?? 'tmm.govservph.com'));
$filterParts = [];
$filterParts[] = 'Status: ' . (($status !== '' && $status !== 'Status') ? $status : 'All');
$filterLabel = 'Filtered: ' . implode('. ', $filterParts) . '.';
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
    .rtitle .addr{font-size:12px;color:#64748b;font-weight:700;margin-top:2px}
    .rtitle .filters{font-size:12px;color:#475569;margin-top:4px}
    .ibox{margin-top:8px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden}
    .ibox table{margin:0;border:0}
    .ibox th,.ibox td{border:0;padding:6px 10px;font-size:12px}
    .ibox th{width:28%;text-align:left;background:#f8fafc;color:#334155;text-transform:uppercase;letter-spacing:.08em;font-weight:800}
    .ibox td{font-weight:700;color:#0f172a}
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
                <div class="sub"><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : 'Vehicle Registration Report'); ?></div>
                <?php if ($office_addr !== ''): ?>
                <div class="addr"><?php echo htmlspecialchars($office_addr); ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <td colspan="5" style="background:#fff;border:0;padding:6px 0 0 0">
            <div class="filters"><?php echo htmlspecialchars($filterLabel); ?></div>
          </td>
        </tr>
        <tr>
          <td colspan="5" style="background:#fff;border:0;padding:0">
            <div class="ibox">
              <table>
                <tr>
                  <th>Prepared by Department:</th>
                  <td><?php echo htmlspecialchars($pb_dept !== '' ? $pb_dept : '-'); ?></td>
                  <th>Report:</th>
                  <td><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : 'Summary Report'); ?></td>
                </tr>
                <tr>
                  <th>Name:</th>
                  <td><?php echo htmlspecialchars($pb_name !== '' ? $pb_name : '-'); ?></td>
                  <th>Date & Time:</th>
                  <td><?php echo htmlspecialchars($now); ?></td>
                </tr>
                <tr>
                  <th>Recipient Name:</th>
                  <td><?php echo htmlspecialchars($rc_name !== '' ? $rc_name : '-'); ?></td>
                  <th>Position:</th>
                  <td><?php echo htmlspecialchars($rc_pos !== '' ? $rc_pos : '-'); ?></td>
                </tr>
                <tr>
                  <th>Department:</th>
                  <td colspan="3"><?php echo htmlspecialchars($rc_dept !== '' ? $rc_dept : '-'); ?></td>
                </tr>
              </table>
            </div>
          </td>
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
  <div class="footer">
    Transport & Mobility Management • <?php echo htmlspecialchars($office_email); ?><?php if ($office_contact !== '') echo ' • ' . htmlspecialchars($office_contact); ?> • <?php echo htmlspecialchars($public_site); ?> • © <?php echo htmlspecialchars($year); ?>
  </div>
  <script>
    (function() {
      try { window.print(); } catch (e) {}
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
