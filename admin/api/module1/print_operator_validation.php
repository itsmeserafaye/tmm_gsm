<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module1.write');
$db = db();
$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['operator_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
$sql = "SELECT
  o.id,
  o.operator_type,
  COALESCE(NULLIF(o.registered_name,''), NULLIF(o.name,''), o.full_name) AS display_name,
  o.workflow_status,
  MAX(CASE WHEN d.doc_type='GovID' THEN d.is_verified ELSE 0 END) AS govid_verified,
  MAX(CASE WHEN d.doc_type='CDA' THEN d.is_verified ELSE 0 END) AS cda_verified,
  MAX(CASE WHEN d.doc_type='SEC' THEN d.is_verified ELSE 0 END) AS sec_verified,
  MAX(CASE WHEN d.doc_type='BarangayCert' THEN d.is_verified ELSE 0 END) AS brgy_verified
FROM operators o
LEFT JOIN operator_documents d ON d.operator_id=o.id";
$conds = [];
$params = [];
$typestr = '';
if ($q !== '') {
  $conds[] = "(o.name LIKE ? OR o.full_name LIKE ?)";
  $like = "%$q%";
  $params = array_merge($params, [$like,$like]);
  $typestr .= 'ss';
}
if ($type !== '') {
  $conds[] = "o.operator_type=?";
  $params[] = $type;
  $typestr .= 's';
}
if ($status !== '') {
  $conds[] = "o.workflow_status=?";
  $params[] = $status;
  $typestr .= 's';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " GROUP BY o.id, o.operator_type, o.registered_name, o.name, o.full_name, o.workflow_status ORDER BY o.created_at DESC LIMIT 1000";
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
  <title>Operator Document Validation Report</title>
  <style>
    *{box-sizing:border-box}
    :root{--footer-height:18mm}
    @page{margin:16mm 12mm 22mm 12mm}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;color:#0f172a;margin:0}
    .wrap{padding:16px 16px calc(var(--footer-height) + 12px) 16px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #e2e8f0;padding:8px;font-size:12px}
    th{background:#f8fafc;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#334155}
    .ok{color:#059669;font-weight:800}
    .no{color:#ef4444;font-weight:800}
    .logo{width:40px;height:40px;border-radius:8px;object-fit:cover}
    thead{display:table-header-group}
    tbody tr{page-break-inside:avoid;break-inside:avoid}
    .footer{border-top:2px solid #e2e8f0;padding:6px 16px;font-size:12px;color:#475569;text-align:center;position:fixed;left:0;right:0;bottom:0;height:var(--footer-height);background:#fff}
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
                <div class="sub">Operator Document Validation Report</div>
                <div class="filters">Generated: <?php echo htmlspecialchars($now); ?> • Search: <?php echo htmlspecialchars($q ?: '-'); ?> • Type: <?php echo htmlspecialchars($type ?: 'All'); ?> • Status: <?php echo htmlspecialchars($status ?: 'All'); ?></div>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <th style="width:32%">Operator</th>
          <th style="width:12%">Type</th>
          <th style="width:14%">GovID</th>
          <th style="width:14%">CDA</th>
          <th style="width:14%">SEC</th>
          <th style="width:14%">Brgy Cert</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($r = $res->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($r['display_name'] ?? ''), ENT_QUOTES); ?></td>
              <td><?php echo htmlspecialchars((string)($r['operator_type'] ?? ''), ENT_QUOTES); ?></td>
              <td class="<?php echo ((int)($r['govid_verified'] ?? 0) === 1) ? 'ok' : 'no'; ?>"><?php echo ((int)($r['govid_verified'] ?? 0) === 1) ? 'Verified' : 'Pending'; ?></td>
              <td class="<?php echo ((int)($r['cda_verified'] ?? 0) === 1) ? 'ok' : 'no'; ?>"><?php echo ((int)($r['cda_verified'] ?? 0) === 1) ? 'Verified' : 'Pending'; ?></td>
              <td class="<?php echo ((int)($r['sec_verified'] ?? 0) === 1) ? 'ok' : 'no'; ?>"><?php echo ((int)($r['sec_verified'] ?? 0) === 1) ? 'Verified' : 'Pending'; ?></td>
              <td class="<?php echo ((int)($r['brgy_verified'] ?? 0) === 1) ? 'ok' : 'no'; ?>"><?php echo ((int)($r['brgy_verified'] ?? 0) === 1) ? 'Verified' : 'Pending'; ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align:center;color:#64748b;font-weight:700;padding:18px">No records found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="footer">
    <div>Transport & Mobility Management • LGU Permitted • © <?php echo date('Y'); ?></div>
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
