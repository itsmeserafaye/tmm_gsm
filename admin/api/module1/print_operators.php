<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write','reports.export']);
$db = db();
$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['operator_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
$sql = "SELECT id, operator_type, COALESCE(NULLIF(registered_name,''), NULLIF(name,''), full_name) AS display_name,
               address_street, address_barangay, address_city, address_province, address_postal_code,
               contact_no, email, workflow_status, created_at
        FROM operators";
$conds = [];
$params = [];
$typestr = '';
if ($q !== '') {
  $conds[] = "(name LIKE ? OR full_name LIKE ? OR contact_no LIKE ? OR email LIKE ?)";
  $like = "%$q%";
  $params = array_merge($params, [$like,$like,$like,$like]);
  $typestr .= 'ssss';
}
if ($type !== '' && $type !== 'Type') {
  $conds[] = "operator_type=?";
  $params[] = $type;
  $typestr .= 's';
}
if ($status !== '' && $status !== 'Status') {
  $allowed = ['Draft','Incomplete','Pending Validation','Returned','Rejected','Active','Inactive'];
  if (in_array($status, $allowed, true)) {
    $conds[] = "workflow_status=?";
    $params[] = $status;
    $typestr .= 's';
  }
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY created_at DESC LIMIT 1000";
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
$year = date('Y');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Operators Report</title>
  <style>
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;color:#0f172a;margin:0}
    .wrap{padding:24px 24px 96px 24px}
    .header{display:flex;flex-direction:column;align-items:center;gap:8px;border-bottom:2px solid #e2e8f0;padding-bottom:12px;margin-bottom:16px;text-align:center}
    .header h1{margin:0;font-weight:900;font-size:18px;letter-spacing:.08em;text-transform:uppercase}
    .sub{font-weight:700;color:#334155}
    .filters{font-size:12px;color:#475569;margin-top:4px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #e2e8f0;padding:8px;font-size:12px}
    th{background:#f8fafc;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#334155}
    .footer{border-top:2px solid #e2e8f0;padding:8px 24px;font-size:12px;color:#475569;text-align:center;position:fixed;left:0;right:0;bottom:0}
    .logo{width:40px;height:40px;border-radius:8px;object-fit:cover}
    @media print{.wrap{padding:0 24px 96px 24px}.footer{position:fixed}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="header">
      <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
      <div>
        <h1>Transport & Mobility Management</h1>
        <div class="sub">Operators Report</div>
        <div class="filters">Generated: <?php echo htmlspecialchars($now); ?> • Search: <?php echo htmlspecialchars($q ?: '-'); ?> • Type: <?php echo htmlspecialchars($type ?: 'All'); ?> • Status: <?php echo htmlspecialchars($status ?: 'All'); ?></div>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th style="width:28%">Operator</th>
          <th style="width:12%">Type</th>
          <th style="width:22%">Contact</th>
          <th style="width:26%">Address</th>
          <th style="width:12%">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($r = $res->fetch_assoc()): ?>
            <?php
              $addr = trim(implode(', ', array_filter([
                $r['address_street'] ?? '',
                $r['address_barangay'] ?? '',
                $r['address_city'] ?? '',
                $r['address_province'] ?? '',
                $r['address_postal_code'] ?? ''
              ])));
              $contact = trim($r['contact_no'] ?? '');
              $email = trim($r['email'] ?? '');
              $contact = $contact . ($contact && $email ? ' / ' : '') . $email;
              if ($contact === '') $contact = '-';
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($r['display_name'] ?? ''), ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)($r['operator_type'] ?? ''), ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($contact, ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars($addr !== '' ? $addr : '-', ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)($r['workflow_status'] ?? ''), ENT_QUOTES); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" style="text-align:center;color:#64748b;font-weight:700;padding:18px">No records found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="footer">Transport & Mobility Management • LGU Permitted • © <?php echo $year; ?></div>
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
