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
$pb_name = trim((string)($_GET['pb_name'] ?? ''));
$pb_dept = trim((string)($_GET['pb_dept'] ?? ''));
$rc_name = trim((string)($_GET['rc_name'] ?? ''));
$rc_pos = trim((string)($_GET['rc_pos'] ?? ''));
$rc_dept = trim((string)($_GET['rc_dept'] ?? ''));
$rep_title = trim((string)($_GET['rep_title'] ?? ($tab === 'payments' ? 'Parking Payments' : 'Parking Slots')));
$office_addr = trim((string)(tmm_get_app_setting('office_address','1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.') ?? '1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.'));
$office_email = trim((string)(tmm_get_app_setting('office_email','helpdesk@tmm.gov.ph') ?? 'helpdesk@tmm.gov.ph'));
$office_contact = trim((string)(tmm_get_app_setting('office_contact','') ?? ''));
$public_site = trim((string)(tmm_get_app_setting('public_website','tmm.govservph.com') ?? 'tmm.govservph.com'));
$filterParts = [];
$filterParts[] = 'Terminal: ' . ($termName !== '' ? $termName : ('ID ' . (int)$terminalId));
$filterLabel = 'Filtered: ' . implode('. ', $filterParts) . '.';
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
          <th colspan="<?php echo $tab === 'payments' ? 6 : 3; ?>" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub"><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : ($tab === 'payments' ? 'Parking Payments' : 'Parking Slots')); ?></div>
                <?php if ($office_addr !== ''): ?>
                <div class="addr"><?php echo htmlspecialchars($office_addr); ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <td colspan="<?php echo $tab === 'payments' ? 6 : 3; ?>" style="background:#fff;border:0;padding:6px 0 0 0">
            <div class="filters"><?php echo htmlspecialchars($filterLabel); ?></div>
          </td>
        </tr>
        <tr>
          <td colspan="<?php echo $tab === 'payments' ? 6 : 3; ?>" style="background:#fff;border:0;padding:0">
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
