<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module3.read','module3.analytics','reports.export']);
$db = db();
$wf = trim((string)($_GET['workflow_status'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$sql = "SELECT violation_date, plate_number, violation_type, location, workflow_status
        FROM violations";
$conds = [];
$params = [];
$types = '';
if ($wf !== '') {
  $conds[] = "workflow_status=?";
  $params[] = $wf;
  $types .= 's';
}
if ($from !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from)) {
  $conds[] = "DATE(violation_date) >= ?";
  $params[] = $from;
  $types .= 's';
}
if ($to !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to)) {
  $conds[] = "DATE(violation_date) <= ?";
  $params[] = $to;
  $types .= 's';
}
if ($q !== '') {
  $conds[] = "(plate_number LIKE ? OR violation_type LIKE ? OR location LIKE ?)";
  $like = "%$q%";
  $params = array_merge($params, [$like,$like,$like]);
  $types .= 'sss';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY violation_date DESC LIMIT 1000";
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
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Violations Report</title>
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
          <th colspan="6" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub">Violations Report</div>
                <div class="filters">Generated: <?php echo htmlspecialchars($now); ?> • Status: <?php echo htmlspecialchars($wf ?: 'All'); ?> • From: <?php echo htmlspecialchars($from ?: '—'); ?> • To: <?php echo htmlspecialchars($to ?: '—'); ?> • Search: <?php echo htmlspecialchars($q ?: '-'); ?></div>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <th style="width:16%">Date</th>
          <th style="width:12%">Plate</th>
          <th style="width:26%">Type</th>
          <th style="width:24%">Location</th>
          <th style="width:12%">Status</th>
          <th style="width:10%">Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($row = $res->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars(!empty($row['violation_date']) ? date('Y-m-d H:i', strtotime((string)$row['violation_date'])) : '-'); ?></td>
              <td><?php echo htmlspecialchars((string)($row['plate_number'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($row['violation_type'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($row['location'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($row['workflow_status'] ?? '')); ?></td>
              <td></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" class="py-6 text-center text-slate-500">No violations found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="footer">Transport & Mobility Management • LGU Permitted • © <?php echo htmlspecialchars($year); ?></div>
</body>
</html>

