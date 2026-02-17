<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module5.manage_terminal','reports.export']);
$db = db();
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$sql = "SELECT t.id, t.name, t.location, t.address, t.capacity, t.category,
               COUNT(DISTINCT tr.route_id) AS route_count
        FROM terminals t
        LEFT JOIN terminal_routes tr ON tr.terminal_id=t.id
        WHERE t.type <> 'Parking'
        GROUP BY t.id, t.name, t.location, t.address, t.capacity, t.category
        ORDER BY t.name ASC LIMIT 1000";
$res = $db->query($sql);

header('Content-Type: text/html; charset=utf-8');
$logo = $rootUrl . '/admin/includes/GSM_logo.png';
$now = date('M d, Y H:i');
$year = date('Y');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Terminal List</title>
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
          <th colspan="5" style="background:#fff;border:0;padding:0">
            <div class="meta">
              <div class="left"><?php echo htmlspecialchars($now); ?></div>
              <div class="center">Terminal List</div>
            </div>
          </th>
        </tr>
        <tr>
          <th colspan="5" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub">Terminal List</div>
                <div class="filters">Generated: <?php echo htmlspecialchars($now); ?></div>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <th style="width:28%">Name</th>
          <th style="width:24%">Location</th>
          <th style="width:18%">Routes</th>
          <th style="width:14%">Capacity</th>
          <th style="width:16%">Address</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($t = $res->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($t['name'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($t['location'] ?? '')); ?></td>
              <td><?php echo (int)($t['route_count'] ?? 0); ?></td>
              <td><?php echo (int)($t['capacity'] ?? 0); ?></td>
              <td><?php echo htmlspecialchars((string)($t['address'] ?? '')); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" class="py-6 text-center text-slate-500">No terminals found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="footer">Transport & Mobility Management • LGU Permitted • © <?php echo htmlspecialchars($year); ?></div>
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
