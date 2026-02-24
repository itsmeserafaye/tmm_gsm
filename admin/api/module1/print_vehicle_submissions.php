<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write','reports.export']);
$db = db();

$status = trim((string)($_GET['status'] ?? 'Submitted'));
$q = trim((string)($_GET['q'] ?? ''));
$vehType = trim((string)($_GET['vehicle_type'] ?? ''));
$month = (int)($_GET['month'] ?? 0);
$year = (int)($_GET['year'] ?? 0);
$operatorId = (int)($_GET['operator_id'] ?? 0);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$allowedStatus = ['Submitted','Approved','Rejected'];
if (!in_array($status, $allowedStatus, true)) $status = 'Submitted';

$sql = "SELECT s.submitted_at, s.submission_id, s.plate_number, s.vehicle_type, s.cr_number, s.chassis_no,
               s.status, s.submitted_by_name, s.approved_at, s.approved_by_name,
               u.full_name AS portal_full_name, u.association_name AS portal_association_name,
               u.puv_operator_id AS operator_id
        FROM vehicle_record_submissions s
        LEFT JOIN operator_portal_users u ON s.portal_user_id=u.id";
$conds = [];
$params = [];
$types = '';

if ($status !== '') { $conds[] = "s.status=?"; $params[] = $status; $types .= 's'; }
if ($q !== '') {
  $conds[] = "(s.plate_number LIKE ? OR s.vehicle_type LIKE ? OR s.submitted_by_name LIKE ? OR s.cr_number LIKE ? OR s.chassis_no LIKE ?)";
  $like = '%' . $q . '%';
  $params = array_merge($params, [$like,$like,$like,$like,$like]);
  $types .= 'sssss';
}
if ($vehType !== '') { $conds[] = "s.vehicle_type=?"; $params[] = $vehType; $types .= 's'; }
if ($month >= 1 && $month <= 12) { $conds[] = "MONTH(s.submitted_at)=?"; $params[] = $month; $types .= 'i'; }
if ($year >= 2000 && $year <= 2100) { $conds[] = "YEAR(s.submitted_at)=?"; $params[] = $year; $types .= 'i'; }
if ($operatorId > 0) { $conds[] = "COALESCE(u.puv_operator_id,0)=?"; $params[] = $operatorId; $types .= 'i'; }
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY s.submitted_at DESC, s.submission_id DESC LIMIT 1000";

$res = null;
if ($params) {
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $res = null;
  }
} else {
  $res = $db->query($sql);
}

header('Content-Type: text/html; charset=utf-8');
$logo = $rootUrl . '/admin/includes/GSM_logo.png';
$now = date('M d, Y H:i');
$yearNow = date('Y');
$pb_name = trim((string)($_GET['pb_name'] ?? ''));
$pb_dept = trim((string)($_GET['pb_dept'] ?? ''));
$rc_name = trim((string)($_GET['rc_name'] ?? ''));
$rc_pos = trim((string)($_GET['rc_pos'] ?? ''));
$rc_dept = trim((string)($_GET['rc_dept'] ?? ''));
$rep_title = trim((string)($_GET['rep_title'] ?? 'Vehicle Encoding Submissions'));
$office_addr = trim((string)(tmm_get_app_setting('office_address','1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.') ?? '1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.'));
$office_email = trim((string)(tmm_get_app_setting('office_email','helpdesk@tmm.gov.ph') ?? 'helpdesk@tmm.gov.ph'));
$office_contact = trim((string)(tmm_get_app_setting('office_contact','') ?? ''));
$public_site = trim((string)(tmm_get_app_setting('public_website','tmm.govservph.com') ?? 'tmm.govservph.com'));
$filterParts = [];
$filterParts[] = 'Status: ' . $status;
$filterParts[] = 'Type: ' . ($vehType !== '' ? $vehType : 'All');
$filterParts[] = 'Month: ' . ($month >= 1 && $month <= 12 ? date('F', mktime(0,0,0,$month,1)) : 'All');
$filterParts[] = 'Year: ' . ($year >= 2000 && $year <= 2100 ? $year : 'All');
if ($operatorId > 0) $filterParts[] = 'Operator ID: ' . $operatorId;
$filterLabel = 'Filtered: ' . implode('. ', $filterParts) . '.';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Vehicle Encoding Submissions</title>
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
          <th colspan="7" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub"><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : 'Vehicle Encoding Submissions'); ?></div>
                <?php if ($office_addr !== ''): ?>
                <div class="addr"><?php echo htmlspecialchars($office_addr); ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <td colspan="7" style="background:#fff;border:0;padding:6px 0 0 0">
            <div class="filters"><?php echo htmlspecialchars($filterLabel); ?></div>
          </td>
        </tr>
        <tr>
          <td colspan="7" style="background:#fff;border:0;padding:0">
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
          <th style="width:16%">Submitted</th>
          <th style="width:12%">Plate</th>
          <th style="width:14%">Type</th>
          <th style="width:18%">Submitted By</th>
          <th style="width:12%">Status</th>
          <th style="width:28%">Operator/Association</th>
          <th style="width:10%">CR</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($r = $res->fetch_assoc()): ?>
            <?php
              $assoc = trim((string)($r['portal_full_name'] ?? ''));
              $an = trim((string)($r['portal_association_name'] ?? ''));
              if ($an !== '') $assoc = $assoc !== '' ? ($assoc . ' / ' . $an) : $an;
              if ($assoc === '') $assoc = '-';
            ?>
            <tr>
              <td><?php echo htmlspecialchars(!empty($r['submitted_at']) ? date('Y-m-d H:i', strtotime((string)$r['submitted_at'])) : '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)($r['plate_number'] ?? ''), ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)($r['vehicle_type'] ?? ''), ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)($r['submitted_by_name'] ?? ''), ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($assoc, ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)($r['cr_number'] ?? ''), ENT_QUOTES); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7" style="text-align:center;color:#64748b;font-weight:700;padding:18px">No records found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="footer">
    Transport & Mobility Management • <?php echo htmlspecialchars($office_email); ?><?php if ($office_contact !== '') echo ' • ' . htmlspecialchars($office_contact); ?> • <?php echo htmlspecialchars($public_site); ?> • © <?php echo htmlspecialchars($yearNow); ?>
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
