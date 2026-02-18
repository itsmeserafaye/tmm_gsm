<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.read','module2.endorse','module2.approve','module2.history','reports.export']);
$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$kind = trim((string)($_GET['kind'] ?? '')); // '', 'route', 'service_area'
$remainingOnly = isset($_GET['remaining_only']) && (string)$_GET['remaining_only'] !== '0';

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$rows = [];

// Detect allocation model
$useAlloc = false;
$tAlloc = $db->query("SHOW TABLES LIKE 'route_vehicle_types'");
if ($tAlloc && $tAlloc->num_rows > 0) {
  $cAlloc = $db->query("SELECT COUNT(*) AS c FROM route_vehicle_types");
  if ($cAlloc && (int)($cAlloc->fetch_assoc()['c'] ?? 0) > 0) $useAlloc = true;
}

if ($kind === '' || $kind === 'route') {
  if ($useAlloc) {
    $res = $db->query("SELECT
      r.id AS route_db_id,
      r.route_id,
      COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code,
      r.route_name,
      r.origin,
      r.destination,
      a.vehicle_type,
      CASE
        WHEN a.fare_min IS NULL AND a.fare_max IS NULL THEN NULL
        WHEN a.fare_max IS NULL OR ABS(a.fare_min - a.fare_max) < 0.001 THEN COALESCE(a.fare_min, a.fare_max)
        ELSE CONCAT(a.fare_min, ' - ', a.fare_max)
      END AS fare,
      COALESCE(a.authorized_units, 0) AS authorized_units,
      COALESCE(u.used_units, 0) AS used_units,
      GREATEST(COALESCE(a.authorized_units,0) - COALESCE(u.used_units,0), 0) AS remaining_units
    FROM routes r
    JOIN route_vehicle_types a ON a.route_id=r.id AND a.status='Active' AND a.vehicle_type<>'Tricycle'
    LEFT JOIN (
      SELECT route_id, vehicle_type, COALESCE(SUM(vehicle_count),0) AS used_units
      FROM franchise_applications
      WHERE status IN ('Pending Review','Approved','Active','Endorsed','LGU-Endorsed','LTFRB-Approved','PA Issued','CPC Issued')
      GROUP BY route_id, vehicle_type
    ) u ON u.route_id=r.id AND u.vehicle_type=a.vehicle_type
    WHERE r.status='Active'
    ORDER BY COALESCE(NULLIF(r.route_name,''), COALESCE(NULLIF(r.route_code,''), r.route_id)) ASC, a.vehicle_type ASC
    LIMIT 2000");
  } else {
    $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes' AND COLUMN_NAME IN ('fare_min','fare_max')");
    $hasFareMin = false; $hasFareMax = false;
    if ($colRes) while ($c = $colRes->fetch_assoc()) { $cn = (string)($c['COLUMN_NAME'] ?? ''); if ($cn === 'fare_min') $hasFareMin = true; if ($cn === 'fare_max') $hasFareMax = true; }
    $fareMinExpr = $hasFareMin ? "COALESCE(r.fare_min, r.fare)" : "r.fare";
    $fareMaxExpr = $hasFareMax ? "COALESCE(r.fare_max, r.fare)" : "r.fare";
    $res = $db->query("SELECT
      r.id AS route_db_id,
      r.route_id,
      COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code,
      r.route_name,
      r.origin,
      r.destination,
      COALESCE(r.vehicle_type,'') AS vehicle_type,
      $fareMinExpr AS fare_min,
      $fareMaxExpr AS fare_max,
      CASE
        WHEN $fareMinExpr IS NULL THEN NULL
        WHEN ABS($fareMinExpr - $fareMaxExpr) < 0.001 THEN $fareMinExpr
        ELSE CONCAT($fareMinExpr, ' - ', $fareMaxExpr)
      END AS fare,
      COALESCE(r.authorized_units, r.max_vehicle_limit, 0) AS authorized_units,
      COALESCE(SUM(fa.vehicle_count),0) AS used_units,
      GREATEST(COALESCE(r.authorized_units, r.max_vehicle_limit, 0) - COALESCE(SUM(fa.vehicle_count),0), 0) AS remaining_units
    FROM routes r
    LEFT JOIN franchise_applications fa ON fa.route_id=r.id AND fa.status IN ('Pending Review','Approved','Active','Endorsed','LGU-Endorsed','LTFRB-Approved','PA Issued','CPC Issued')
    WHERE r.status='Active' AND COALESCE(r.vehicle_type,'')<>'Tricycle'
    GROUP BY r.id
    ORDER BY COALESCE(NULLIF(r.route_name,''), COALESCE(NULLIF(r.route_code,''), r.route_id)) ASC
    LIMIT 2000");
  }
  if ($res) while ($r = $res->fetch_assoc()) { $r['kind'] = 'route'; $rows[] = $r; }
}

if ($kind === '' || $kind === 'service_area') {
  if ($db->query("SHOW TABLES LIKE 'tricycle_service_areas'") && ($db->query("SHOW TABLES LIKE 'tricycle_service_areas'")->num_rows ?? 0) > 0) {
    $resA = $db->query("SELECT
      a.id AS service_area_id,
      a.area_code,
      a.area_name,
      a.barangay,
      a.fare_min,
      a.fare_max,
      COALESCE(a.authorized_units,0) AS authorized_units,
      COALESCE(u.used_units,0) AS used_units,
      GREATEST(COALESCE(a.authorized_units,0) - COALESCE(u.used_units,0), 0) AS remaining_units,
      COALESCE(p.points, '') AS points
    FROM tricycle_service_areas a
    LEFT JOIN (
      SELECT service_area_id, COALESCE(SUM(vehicle_count),0) AS used_units
      FROM franchise_applications
      WHERE status IN ('Pending Review','Approved','Active','Endorsed','LGU-Endorsed','LTFRB-Approved','PA Issued','CPC Issued')
        AND COALESCE(vehicle_type,'')='Tricycle'
        AND service_area_id IS NOT NULL
      GROUP BY service_area_id
    ) u ON u.service_area_id=a.id
    LEFT JOIN (
      SELECT area_id, GROUP_CONCAT(point_name ORDER BY sort_order ASC, point_id ASC SEPARATOR ' • ') AS points
      FROM tricycle_service_area_points
      GROUP BY area_id
    ) p ON p.area_id=a.id
    WHERE a.status='Active'
    ORDER BY a.area_name ASC, a.id DESC
    LIMIT 2000");
    if ($resA) while ($a = $resA->fetch_assoc()) { $a['kind'] = 'service_area'; $rows[] = $a; }
  }
}

// Apply filters
$rows = array_values(array_filter($rows, function($r) use ($q, $kind, $remainingOnly) {
  if ($kind !== '' && ($r['kind'] ?? '') !== $kind) return false;
  if ($remainingOnly) {
    $rem = (int)($r['remaining_units'] ?? 0);
    if ($rem <= 0) return false;
  }
  if ($q !== '') {
    $hay = strtolower(implode(' ', [
      (string)($r['route_code'] ?? ($r['area_code'] ?? '')),
      (string)($r['route_id'] ?? ''),
      (string)($r['route_name'] ?? ($r['area_name'] ?? '')),
      (string)($r['origin'] ?? ($r['points'] ?? '')),
      (string)($r['destination'] ?? ''),
    ]));
    if (strpos($hay, strtolower($q)) === false) return false;
  }
  return true;
}));

header('Content-Type: text/html; charset=utf-8');
$logo = $rootUrl . '/admin/includes/GSM_logo.png';
$now = date('M d, Y H:i');
$year = date('Y');
$pb_name = trim((string)($_GET['pb_name'] ?? ''));
$pb_dept = trim((string)($_GET['pb_dept'] ?? ''));
$rc_name = trim((string)($_GET['rc_name'] ?? ''));
$rc_pos = trim((string)($_GET['rc_pos'] ?? ''));
$rc_dept = trim((string)($_GET['rc_dept'] ?? ''));
$rep_title = trim((string)($_GET['rep_title'] ?? 'Route Assignment Report'));
$office_addr = trim((string)(tmm_get_app_setting('office_address','1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.') ?? '1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.'));
$office_email = trim((string)(tmm_get_app_setting('office_email','helpdesk@tmm.gov.ph') ?? 'helpdesk@tmm.gov.ph'));
$office_contact = trim((string)(tmm_get_app_setting('office_contact','') ?? ''));
$public_site = trim((string)(tmm_get_app_setting('public_website','tmm.govservph.com') ?? 'tmm.govservph.com'));

$filterParts = [];
if ($q !== '') $filterParts[] = 'Query: ' . $q;
if ($kind === 'route') $filterParts[] = 'Type: Routes';
elseif ($kind === 'service_area') $filterParts[] = 'Type: Service Areas';
if ($remainingOnly) $filterParts[] = 'Remaining only';
$filterLabel = $filterParts ? ('Filtered: ' . implode('. ', $filterParts) . '.') : 'All records.';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Route Assignment Report</title>
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
                <div class="sub"><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : 'Route Assignment Report'); ?></div>
                <?php if ($office_addr !== ''): ?>
                <div class="addr"><?php echo htmlspecialchars($office_addr); ?></div>
                <?php endif; ?>
                <div class="filters"><?php echo htmlspecialchars($filterLabel); ?></div>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <th style="width:20%">Route / Area</th>
          <th style="width:16%">Origin / Points</th>
          <th style="width:16%">Destination</th>
          <th style="width:10%">Vehicle Type</th>
          <th style="width:10%">Fare</th>
          <th style="width:10%">Authorized</th>
          <th style="width:10%">Active</th>
          <th style="width:8%">Remain</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows): ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($r['route_name'] ?? ($r['area_name'] ?? ($r['route_code'] ?? $r['area_code'] ?? '-')))); ?><br><span style="color:#64748b"><?php echo htmlspecialchars((string)($r['route_code'] ?? $r['area_code'] ?? '')); ?></span></td>
              <td><?php echo htmlspecialchars((string)($r['origin'] ?? $r['points'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars((string)($r['destination'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($r['vehicle_type'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars($r['fare'] !== null ? (string)$r['fare'] : '-'); ?></td>
              <td><?php echo (int)($r['authorized_units'] ?? 0); ?></td>
              <td><?php echo (int)($r['used_units'] ?? 0); ?></td>
              <td><?php echo max(0, (int)($r['remaining_units'] ?? 0)); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" class="py-6" style="text-align:center;color:#64748b">No routes or service areas found.</td></tr>
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
