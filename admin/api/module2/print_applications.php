<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.read','module2.endorse','module2.approve','reports.export']);
$db = db();
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$basis = trim((string)($_GET['basis'] ?? 'submitted'));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$coverage = trim((string)($_GET['coverage'] ?? ''));
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$sql = "SELECT fa.application_id, fa.franchise_ref_number, fa.operator_id,
               COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
               fa.route_ids, fa.approved_route_ids,
               fa.service_area_id, fa.route_id,
               fa.vehicle_count, fa.status, fa.submitted_at, fa.endorsed_at, fa.approved_at
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id=fa.operator_id";
$conds = [];
$params = [];
$types = '';
if ($q !== '') {
  $conds[] = "(fa.franchise_ref_number LIKE ? OR COALESCE(NULLIF(o.name,''), o.full_name) LIKE ?)";
  $like = "%$q%";
  $params[] = $like; $params[] = $like;
  $types .= 'ss';
}
if ($status !== '' && $status !== 'Status') {
  if ($status === 'LGU-Endorsed' || $status === 'Endorsed') {
    $conds[] = "fa.status IN ('LGU-Endorsed','Endorsed')";
  } elseif ($status === 'LTFRB-Approved' || $status === 'Approved' || $status === 'PA Issued' || $status === 'CPC Issued') {
    $conds[] = "fa.status IN ('LTFRB-Approved','Approved','PA Issued','CPC Issued')";
  } else {
    $conds[] = "fa.status=?";
    $params[] = $status;
    $types .= 's';
  }
}
if ($coverage === 'route') {
  $conds[] = "COALESCE(fa.service_area_id,0)=0 AND COALESCE(fa.route_id,0)<>0";
}
if ($coverage === 'service_area') {
  $conds[] = "COALESCE(fa.service_area_id,0)<>0";
}
if ($from !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $from)) {
  $col = ($basis === 'endorsed') ? 'fa.endorsed_at' : (($basis === 'approved') ? 'fa.approved_at' : 'fa.submitted_at');
  $conds[] = "DATE($col) >= ?";
  $params[] = $from;
  $types .= 's';
}
if ($to !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $to)) {
  $col = ($basis === 'endorsed') ? 'fa.endorsed_at' : (($basis === 'approved') ? 'fa.approved_at' : 'fa.submitted_at');
  $conds[] = "DATE($col) <= ?";
  $params[] = $to;
  $types .= 's';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY fa.submitted_at DESC LIMIT 1000";
$res = null;
if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

header('Content-Type: text/html; charset=utf-8');
$logo = $rootUrl . '/admin/includes/GSM_logo.png';
$now = date('M d, Y H:i');
$year = date('Y');
$pb_name = trim((string)($_GET['pb_name'] ?? ''));
$pb_dept = trim((string)($_GET['pb_dept'] ?? ''));
$rc_name = trim((string)($_GET['rc_name'] ?? ''));
$rc_pos = trim((string)($_GET['rc_pos'] ?? ''));
$rc_dept = trim((string)($_GET['rc_dept'] ?? ''));
$rep_title = trim((string)($_GET['rep_title'] ?? 'Franchise Applications Report'));
$office_addr = trim((string)(tmm_get_app_setting('office_address','1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.') ?? '1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.'));
$office_email = trim((string)(tmm_get_app_setting('office_email','helpdesk@tmm.gov.ph') ?? 'helpdesk@tmm.gov.ph'));
$office_contact = trim((string)(tmm_get_app_setting('office_contact','') ?? ''));
$public_site = trim((string)(tmm_get_app_setting('public_website','tmm.govservph.com') ?? 'tmm.govservph.com'));
$filterParts = [];
$filterParts[] = 'Status: ' . (($status !== '' && $status !== 'Status') ? $status : 'All');
if ($coverage === 'route') $filterParts[] = 'Coverage: Routes';
elseif ($coverage === 'service_area') $filterParts[] = 'Coverage: Service Areas';
if ($from !== '') $filterParts[] = 'From: ' . $from;
if ($to !== '') $filterParts[] = 'To: ' . $to;
if (in_array($basis, ['submitted','endorsed','approved'], true)) {
  $filterParts[] = 'Date Basis: ' . ucfirst($basis);
}
$filterLabel = 'Filtered: ' . implode('. ', $filterParts) . '.';

function tmm_extract_ids(string $csv): array {
  $out = [];
  if ($csv === '') return $out;
  if (preg_match_all('/\\d+/', $csv, $m)) {
    foreach ($m[0] as $x) { $id = (int)$x; if ($id > 0) $out[] = $id; }
  }
  return $out;
}

$routeMap = [];
$needIds = [];
if ($res) {
  $res->data_seek(0);
  while ($r = $res->fetch_assoc()) {
    $csv = trim((string)($r['approved_route_ids'] ?? ''));
    if ($csv === '') $csv = trim((string)($r['route_ids'] ?? ''));
    foreach (tmm_extract_ids($csv) as $id) $needIds[$id] = true;
  }
  $res->data_seek(0);
}
if ($needIds) {
  $ids = implode(',', array_map('intval', array_keys($needIds)));
  $qr = $db->query("SELECT id, COALESCE(NULLIF(route_code,''), route_id) AS code, origin, destination FROM routes WHERE id IN ($ids)");
  if ($qr) while ($x = $qr->fetch_assoc()) $routeMap[(int)$x['id']] = $x;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Franchise Applications Report</title>
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
                <div class="sub"><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : 'Franchise Applications Report'); ?></div>
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
          <th style="width:16%">Ref No</th>
          <th style="width:26%">Operator</th>
          <th style="width:28%">Routes</th>
          <th style="width:8%">Units</th>
          <th style="width:10%">Status</th>
          <th style="width:12%">Dates</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($r = $res->fetch_assoc()): ?>
            <?php
              $csv = trim((string)($r['approved_route_ids'] ?? ''));
              if ($csv === '') $csv = trim((string)($r['route_ids'] ?? ''));
              $labels = [];
              foreach (tmm_extract_ids($csv) as $id) {
                if (!isset($routeMap[$id])) continue;
                $rx = $routeMap[$id];
                $labels[] = trim(($rx['code'] ?? '-') . ' • ' . ($rx['origin'] ?? '') . ' → ' . ($rx['destination'] ?? ''));
              }
              $dates = [];
              if (!empty($r['submitted_at'])) $dates[] = 'Submitted ' . date('Y-m-d', strtotime((string)$r['submitted_at']));
              if (!empty($r['endorsed_at'])) $dates[] = 'Endorsed ' . date('Y-m-d', strtotime((string)$r['endorsed_at']));
              if (!empty($r['approved_at'])) $dates[] = 'Approved ' . date('Y-m-d', strtotime((string)$r['approved_at']));
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($r['franchise_ref_number'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars((string)($r['operator_name'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars($labels ? implode(' | ', $labels) : '-'); ?></td>
              <td><?php echo (int)($r['vehicle_count'] ?? 0); ?></td>
              <td><?php echo htmlspecialchars((string)($r['status'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars($dates ? implode(' • ', $dates) : '-'); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" class="py-6 text-center text-slate-500">No applications found.</td></tr>
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
