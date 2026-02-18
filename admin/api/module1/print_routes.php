<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','reports.export']);
$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$vehicleType = trim((string)($_GET['vehicle_type'] ?? ''));
$routeCategory = trim((string)($_GET['route_category'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
$logo = $rootUrl . '/admin/includes/GSM_logo.png';

$conds = ["1=1"];
$params = [];
$types = '';
if ($q !== '') {
  $like = "%$q%";
  $conds[] = "(r.route_id LIKE ? OR r.route_code LIKE ? OR r.route_name LIKE ? OR r.origin LIKE ? OR r.destination LIKE ? OR r.via LIKE ?)";
  array_push($params, $like, $like, $like, $like, $like, $like);
  $types .= 'ssssss';
}
if ($status !== '' && $status !== 'Status') {
  $conds[] = "r.status=?";
  $params[] = $status;
  $types .= 's';
}
if ($routeCategory !== '') {
  $conds[] = "r.route_category=?";
  $params[] = $routeCategory;
  $types .= 's';
}

$hasAlloc = false;
$tAlloc = $db->query("SHOW TABLES LIKE 'route_vehicle_types'");
if ($tAlloc && $tAlloc->fetch_row()) $hasAlloc = true;

if ($hasAlloc) {
  if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
    $conds[] = "EXISTS (SELECT 1 FROM route_vehicle_types aa WHERE aa.route_id=r.id AND aa.vehicle_type=? AND aa.vehicle_type<>'Tricycle')";
    $params[] = $vehicleType;
    $types .= 's';
  }
  $sql = "SELECT
    r.id, r.route_id, r.route_code, r.route_name, r.route_category, r.origin, r.destination, r.via, r.structure, r.status,
    a.vehicle_type, a.authorized_units, a.fare_min, a.fare_max, a.status AS allocation_status,
    COALESCE(u.used_units,0) AS used_units
  FROM routes r
  LEFT JOIN route_vehicle_types a ON a.route_id=r.id AND a.vehicle_type<>'Tricycle'
  LEFT JOIN (
    SELECT route_id, vehicle_type, COALESCE(SUM(vehicle_count),0) AS used_units
    FROM franchise_applications
    WHERE status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')
    GROUP BY route_id, vehicle_type
  ) u ON u.route_id=r.id AND u.vehicle_type=a.vehicle_type
  WHERE " . implode(' AND ', $conds) . "
  ORDER BY r.status='Active' DESC, COALESCE(NULLIF(r.route_code,''), r.route_id) ASC, a.vehicle_type ASC, r.id DESC
  LIMIT 1000";
} else {
  if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
    $conds[] = "r.vehicle_type=?";
    $params[] = $vehicleType;
    $types .= 's';
  }
  $sql = "SELECT
    r.id, r.route_id, r.route_code, r.route_name, r.vehicle_type, r.route_category, r.origin, r.destination, r.via, r.structure,
    r.authorized_units, r.fare AS fare_min, r.fare AS fare_max, r.status, COALESCE(u.used_units,0) AS used_units
  FROM routes r
  LEFT JOIN (
    SELECT route_id, COALESCE(SUM(vehicle_count),0) AS used_units
    FROM franchise_applications
    WHERE status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')
    GROUP BY route_id
  ) u ON u.route_id=r.id
  WHERE " . implode(' AND ', $conds) . "
  ORDER BY r.status='Active' DESC, COALESCE(NULLIF(r.route_code,''), r.route_id) ASC, r.id DESC
  LIMIT 1000";
}

if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}
$now = date('M d, Y H:i');
$year = date('Y');
$pb_name = trim((string)($_GET['pb_name'] ?? ''));
$pb_dept = trim((string)($_GET['pb_dept'] ?? ''));
$rc_name = trim((string)($_GET['rc_name'] ?? ''));
$rc_pos = trim((string)($_GET['rc_pos'] ?? ''));
$rc_dept = trim((string)($_GET['rc_dept'] ?? ''));
$rep_title = trim((string)($_GET['rep_title'] ?? 'Routes Report'));
$office_addr = trim((string)(tmm_get_app_setting('office_address','') ?? ''));
$office_email = trim((string)(tmm_get_app_setting('office_email','helpdesk@tmm.gov.ph') ?? 'helpdesk@tmm.gov.ph'));
$office_contact = trim((string)(tmm_get_app_setting('office_contact','') ?? ''));
$public_site = trim((string)(tmm_get_app_setting('public_website','tmm.govservph.com') ?? 'tmm.govservph.com'));
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Routes Report</title>
  <style>
    *{box-sizing:border-box}
    :root{--footer-height:18mm}
    @page{margin:16mm 12mm 22mm 12mm}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;color:#0f172a;margin:0}
    .wrap{padding:16px 16px calc(var(--footer-height) + 12px) 16px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #e2e8f0;padding:8px;font-size:12px;vertical-align:top}
    th{background:#f8fafc;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#334155}
    .logo{width:40px;height:40px;border-radius:8px;object-fit:cover}
    thead{display:table-header-group}
    tbody tr{page-break-inside:avoid;break-inside:avoid}
    .footer{border-top:2px solid #e2e8f0;padding:6px 16px;font-size:12px;color:#475569;text-align:center;position:fixed;left:0;right:0;bottom:0;height:var(--footer-height);background:#fff}
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
                <div class="sub"><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : 'Routes Report'); ?></div>
                <?php if ($office_addr !== ''): ?>
                <div class="addr"><?php echo htmlspecialchars($office_addr); ?></div>
                <?php endif; ?>
                <div class="filters">
                  Generated: <?php echo htmlspecialchars($now); ?>
                  • Search: <?php echo htmlspecialchars($q ?: '-'); ?>
                  • Type: <?php echo htmlspecialchars($vehicleType ?: 'All'); ?>
                  • Category: <?php echo htmlspecialchars($routeCategory ?: 'All'); ?>
                  • Status: <?php echo htmlspecialchars($status ?: 'All'); ?>
                </div>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <td colspan="7" style="background:#fff;border:0;padding:0">
            <div class="ibox">
              <table>
                <tr>
                  <th>Prepared by Department</th>
                  <td><?php echo htmlspecialchars($pb_dept !== '' ? $pb_dept : '-'); ?></td>
                  <th>Report</th>
                  <td><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : 'Summary Report'); ?></td>
                </tr>
                <tr>
                  <th>Name</th>
                  <td><?php echo htmlspecialchars($pb_name !== '' ? $pb_name : '-'); ?></td>
                  <th>Date & Time</th>
                  <td><?php echo htmlspecialchars($now); ?></td>
                </tr>
                <tr>
                  <th>Recipient Name</th>
                  <td><?php echo htmlspecialchars($rc_name !== '' ? $rc_name : '-'); ?></td>
                  <th>Position</th>
                  <td><?php echo htmlspecialchars($rc_pos !== '' ? $rc_pos : '-'); ?></td>
                </tr>
                <tr>
                  <th>Department</th>
                  <td colspan="3"><?php echo htmlspecialchars($rc_dept !== '' ? $rc_dept : '-'); ?></td>
                </tr>
              </table>
            </div>
          </td>
        </tr>
        <tr>
          <th style="width:12%">Code</th>
          <th style="width:20%">Name</th>
          <th style="width:28%">Route</th>
          <?php if ($hasAlloc): ?>
            <th style="width:12%">Vehicle</th>
          <?php else: ?>
            <th style="width:12%">Vehicle</th>
          <?php endif; ?>
          <th style="width:10%">Units</th>
          <th style="width:10%">Remaining</th>
          <th style="width:8%">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($r = $res->fetch_assoc()): ?>
            <?php
              $code = trim((string)($r['route_code'] ?? ''));
              if ($code === '') $code = trim((string)($r['route_id'] ?? ''));
              $name = (string)($r['route_name'] ?? '');
              $route = trim((string)($r['origin'] ?? '')) . ' → ' . trim((string)($r['destination'] ?? ''));
              $veh = (string)($r['vehicle_type'] ?? ($r['vehicle_type'] ?? ''));
              $au = (int)($r['authorized_units'] ?? 0);
              $used = (int)($r['used_units'] ?? 0);
              $rem = $au > 0 ? max(0, $au - $used) : 0;
              $st = (string)($r['status'] ?? '');
            ?>
            <tr>
              <td><?php echo htmlspecialchars($code, ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($name !== '' ? $name : '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($route !== ' → ' ? $route : '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($veh !== '' ? $veh : '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($au > 0 ? (string)$au : '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)$rem, ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($st !== '' ? $st : '-', ENT_QUOTES); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7" style="text-align:center;color:#64748b;font-weight:700;padding:18px">No routes found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="footer">
    Transport & Mobility Management • <?php echo htmlspecialchars($office_email); ?><?php if ($office_contact !== '') echo ' • ' . htmlspecialchars($office_contact); ?> • <?php echo htmlspecialchars($public_site); ?> • © <?php echo htmlspecialchars($year); ?>
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
