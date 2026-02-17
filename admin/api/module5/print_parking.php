<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module5.manage_terminal','module5.parking_fees','reports.export']);
$db = db();
$terminalId = (int)($_GET['terminal_id'] ?? 0);
$tab = trim((string)($_GET['tab'] ?? 'slots'));
if (!in_array($tab, ['slots','payments'], true)) $tab = 'slots';
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
$termName = '';
if ($terminalId > 0) {
  $r = $db->query("SELECT name FROM terminals WHERE id=".(int)$terminalId." LIMIT 1");
  if ($r && ($x = $r->fetch_assoc())) $termName = (string)($x['name'] ?? '');
}
$res = null;
if ($tab === 'slots') {
  $stmt = $db->prepare("SELECT slot_no, status FROM parking_slots WHERE terminal_id=? ORDER BY slot_no ASC LIMIT 2000");
  if ($stmt) { $stmt->bind_param('i', $terminalId); $stmt->execute(); $res = $stmt->get_result(); }
} else {
  $stmt = $db->prepare("SELECT pp.paid_at, ps.slot_no, pp.plate_no, pp.or_no, pp.amount, pp.exported_to_treasury
                        FROM parking_payments pp
                        JOIN parking_slots ps ON ps.slot_id=pp.slot_id
                        WHERE ps.terminal_id=?
                        ORDER BY pp.paid_at DESC LIMIT 2000");
  if ($stmt) { $stmt->bind_param('i', $terminalId); $stmt->execute(); $res = $stmt->get_result(); }
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
  <title><?php echo $tab === 'payments' ? 'Parking Payments' : 'Parking Slots'; ?></title>
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
          <th colspan="<?php echo $tab === 'payments' ? 6 : 3; ?>" style="background:#fff;border:0;padding:0">
            <div class="meta">
              <div class="left"><?php echo htmlspecialchars($now); ?></div>
              <div class="center"><?php echo $tab === 'payments' ? 'Parking Payments' : 'Parking Slots'; ?></div>
            </div>
          </th>
        </tr>
        <tr>
          <th colspan="<?php echo $tab === 'payments' ? 6 : 3; ?>" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub"><?php echo $tab === 'payments' ? 'Parking Payments' : 'Parking Slots'; ?></div>
                <div class="filters">Generated: <?php echo htmlspecialchars($now); ?> • Parking: <?php echo htmlspecialchars($termName ?: ('ID ' . $terminalId)); ?></div>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <?php if ($tab === 'payments'): ?>
        <tr>
          <th style="width:18%">Paid</th>
          <th style="width:14%">Plate</th>
          <th style="width:14%">Slot</th>
          <th style="width:18%">OR No</th>
          <th style="width:12%">Amount</th>
          <th style="width:14%">Treasury</th>
        </tr>
        <?php else: ?>
        <tr>
          <th style="width:24%">Slot</th>
          <th style="width:18%">Status</th>
          <th style="width:58%">Notes</th>
        </tr>
        <?php endif; ?>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($row = $res->fetch_assoc()): ?>
            <?php if ($tab === 'payments'): ?>
            <tr>
              <td><?php echo htmlspecialchars(!empty($row['paid_at']) ? date('Y-m-d H:i', strtotime((string)$row['paid_at'])) : '-'); ?></td>
              <td><?php echo htmlspecialchars((string)($row['plate_no'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($row['slot_no'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($row['or_no'] ?? '')); ?></td>
              <td><?php echo number_format((float)($row['amount'] ?? 0), 2); ?></td>
              <td><?php echo ((int)($row['exported_to_treasury'] ?? 0) === 1) ? 'Exported' : 'Pending'; ?></td>
            </tr>
            <?php else: ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($row['slot_no'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($row['status'] ?? '')); ?></td>
              <td></td>
            </tr>
            <?php endif; ?>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="<?php echo $tab === 'payments' ? 6 : 3; ?>" class="py-6 text-center text-slate-500">No records found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="footer">Transport & Mobility Management • LGU Permitted • © <?php echo htmlspecialchars($year); ?></div>
</body>
</html>
