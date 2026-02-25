<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.endorse','module2.approve','reports.export']);
$db = db();

$q = trim((string)($_GET['q'] ?? ''));

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$erHasStatus = false;
$erHasConditions = false;
$colEr = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='endorsement_records' AND COLUMN_NAME IN ('endorsement_status','conditions')");
if ($colEr) {
  while ($c = $colEr->fetch_assoc()) {
    $cn = (string)($c['COLUMN_NAME'] ?? '');
    if ($cn === 'endorsement_status') $erHasStatus = true;
    if ($cn === 'conditions') $erHasConditions = true;
  }
}
$endorsementStatusExpr = $erHasStatus ? "er.endorsement_status" : "NULL";
$endorsementConditionsExpr = $erHasConditions ? "er.conditions" : "NULL";

$sql = "SELECT fa.application_id, fa.franchise_ref_number, fa.status AS app_status, fa.endorsed_at,
               COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
               r.route_id AS route_code, r.origin, r.destination,
               $endorsementStatusExpr AS endorsement_status,
               $endorsementConditionsExpr AS conditions
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id=fa.operator_id
        LEFT JOIN routes r ON r.id=fa.route_id
        LEFT JOIN endorsement_records er ON er.application_id=fa.application_id
        WHERE fa.status IN ('LGU-Endorsed','Endorsed','Rejected')";
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $sql .= " AND (fa.franchise_ref_number LIKE '%$qv%' OR COALESCE(NULLIF(o.name,''), o.full_name) LIKE '%$qv%' OR r.route_id LIKE '%$qv%' OR r.origin LIKE '%$qv%' OR r.destination LIKE '%$qv%')";
}
$sql .= " ORDER BY COALESCE(fa.endorsed_at, fa.submitted_at) DESC LIMIT 1000";
$rows = [];
$res = $db->query($sql);
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

header('Content-Type: text/html; charset=utf-8');
$logo = $rootUrl . '/admin/includes/GSM_logo.png';
$now = date('M d, Y H:i');
$year = date('Y');
$pb_name = trim((string)($_GET['pb_name'] ?? ''));
$pb_dept = trim((string)($_GET['pb_dept'] ?? ''));
$rc_name = trim((string)($_GET['rc_name'] ?? ''));
$rc_pos = trim((string)($_GET['rc_pos'] ?? ''));
$rc_dept = trim((string)($_GET['rc_dept'] ?? ''));
$rep_title = trim((string)($_GET['rep_title'] ?? 'Endorsed Applications Report'));
$office_addr = trim((string)(tmm_get_app_setting('office_address','1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.') ?? '1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.'));
$office_email = trim((string)(tmm_get_app_setting('office_email','helpdesk@tmm.gov.ph') ?? 'helpdesk@tmm.gov.ph'));
$office_contact = trim((string)(tmm_get_app_setting('office_contact','') ?? ''));
$public_site = trim((string)(tmm_get_app_setting('public_website','tmm.govservph.com') ?? 'tmm.govservph.com'));

$filterLabel = 'Filtered: ' . ($q !== '' ? ('Query: ' . $q) : 'All records') . '.';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Endorsed Applications Report</title>
  <style>
    *{box-sizing:border-box}
    :root{--footer-height:18mm}
    @page{margin:16mm 12mm 22mm 12mm}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;color:#0f172a;margin:0}
    .wrap{padding:16px 16px calc(var(--footer-height) + 12px) 16px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #e2e8f0;padding:8px;font-size:12px;vertical-align:top}
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
          <th colspan="6" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub"><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : 'Endorsed Applications Report'); ?></div>
                <?php if ($office_addr !== ''): ?>
                <div class="addr"><?php echo htmlspecialchars($office_addr); ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <td colspan="6" style="background:#fff;border:0;padding:6px 0 0 0">
            <div class="filters"><?php echo htmlspecialchars($filterLabel); ?></div>
          </td>
        </tr>
        <tr>
          <td colspan="6" style="background:#fff;border:0;padding:0">
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
          <th style="width:16%">Ref No.</th>
          <th style="width:22%">Operator</th>
          <th style="width:24%">Route</th>
          <th style="width:16%">Endorsement</th>
          <th style="width:12%">Endorsed</th>
          <th style="width:10%">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows): ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $appId = (int)($row['application_id'] ?? 0);
              $ref = (string)($row['franchise_ref_number'] ?? '');
              $op = (string)($row['operator_name'] ?? '');
              $rc = (string)($row['route_code'] ?? '');
              $ro = (string)($row['origin'] ?? '');
              $rd = (string)($row['destination'] ?? '');
              $appSt = (string)($row['app_status'] ?? '');
              $esRaw = trim((string)($row['endorsement_status'] ?? ''));
              if ($esRaw === '') $esRaw = ($appSt === 'Rejected') ? 'Rejected' : 'Endorsed (Complete)';
              $es = (strcasecmp($esRaw, 'Rejected') === 0) ? 'Rejected' : 'Approved';
              $dt = (string)($row['endorsed_at'] ?? '');
              $routeLabel = $rc . (($ro !== '' || $rd !== '') ? (' • ' . trim($ro . ' → ' . $rd)) : '');
            ?>
            <tr>
              <td><?php echo htmlspecialchars($ref !== '' ? $ref : 'APP-' . $appId); ?></td>
              <td><?php echo htmlspecialchars($op !== '' ? $op : '-'); ?></td>
              <td><?php echo htmlspecialchars($routeLabel !== '' ? $routeLabel : '-'); ?></td>
              <td><?php echo htmlspecialchars($es); ?></td>
              <td><?php echo htmlspecialchars($dt !== '' ? date('Y-m-d', strtotime($dt)) : '-'); ?></td>
              <td><?php echo htmlspecialchars($appSt !== '' ? $appSt : '-'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="py-6 text-center" style="color:#64748b">No endorsed applications found.</td></tr>
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
