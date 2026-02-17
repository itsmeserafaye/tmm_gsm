<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.read','module2.endorse','module2.approve','reports.export']);
$db = db();
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$sql = "SELECT fa.application_id, fa.franchise_ref_number, fa.operator_id,
               COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
               fa.route_ids, fa.approved_route_ids,
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
    .rtitle .filters{font-size:12px;color:#475569;margin-top:4px}
    .meta{font-size:11px;color:#64748b;padding:4px 0}
    .meta .left{text-align:left}
    .meta .center{text-align:center;font-weight:700}
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
            <div class="meta">
              <div class="left"><?php echo htmlspecialchars($now); ?></div>
              <div class="center">Franchise Applications Report</div>
            </div>
          </th>
        </tr>
        <tr>
          <th colspan="6" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub">Franchise Applications Report</div>
                <div class="filters">Generated: <?php echo htmlspecialchars($now); ?> • Search: <?php echo htmlspecialchars($q ?: '-'); ?> • Status: <?php echo htmlspecialchars($status ?: 'All'); ?></div>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
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
  <div class="footer">Transport & Mobility Management • LGU Permitted • © <?php echo htmlspecialchars($year); ?></div>
</body>
</html>
