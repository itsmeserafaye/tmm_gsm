<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module3.read','module3.issue','module3.analytics','reports.export']);
$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$allowed = ['Pending Payment','Paid','Closed'];
if ($status !== '' && !in_array($status, $allowed, true)) $status = '';

$sql = "SELECT t.sts_ticket_id, t.sts_ticket_no, t.issued_by, t.date_issued, t.fine_amount, t.status,
               t.verification_notes, t.ticket_scan_path, t.linked_violation_id,
               v.plate_number, v.violation_type, vt.description AS violation_desc, v.location, v.violation_date
        FROM sts_tickets t
        LEFT JOIN violations v ON v.id=t.linked_violation_id
        LEFT JOIN violation_types vt ON vt.violation_code=v.violation_type";
$conds = [];
$params = [];
$types = '';
if ($status !== '') {
  $conds[] = "t.status=?";
  $params[] = $status;
  $types .= 's';
}
if ($from !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $from)) {
  $conds[] = "COALESCE(t.date_issued, DATE(t.created_at)) >= ?";
  $params[] = $from;
  $types .= 's';
}
if ($to !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $to)) {
  $conds[] = "COALESCE(t.date_issued, DATE(t.created_at)) <= ?";
  $params[] = $to;
  $types .= 's';
}
if ($q !== '') {
  $conds[] = "(t.sts_ticket_no LIKE ? OR t.issued_by LIKE ? OR v.plate_number LIKE ? OR vt.description LIKE ?)";
  $like = '%' . $q . '%';
  for ($i=0;$i<4;$i++) { $params[] = $like; $types .= 's'; }
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY COALESCE(t.date_issued, DATE(t.created_at)) DESC, t.sts_ticket_id DESC LIMIT 1000";

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
$rep_title = trim((string)($_GET['rep_title'] ?? 'STS Tickets Report'));
$office_addr = trim((string)(tmm_get_app_setting('office_address','1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.') ?? '1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.'));
$office_email = trim((string)(tmm_get_app_setting('office_email','helpdesk@tmm.gov.ph') ?? 'helpdesk@tmm.gov.ph'));
$office_contact = trim((string)(tmm_get_app_setting('office_contact','') ?? ''));
$public_site = trim((string)(tmm_get_app_setting('public_website','tmm.govservph.com') ?? 'tmm.govservph.com'));

$filterParts = [];
$filterParts[] = 'Status: ' . ($status !== '' ? $status : 'All');
if ($from !== '') $filterParts[] = 'From: ' . $from;
if ($to !== '') $filterParts[] = 'To: ' . $to;
if ($q !== '') $filterParts[] = 'Query: ' . $q;
$filterLabel = 'Filtered: ' . implode('. ', $filterParts) . '.';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>STS Tickets Report</title>
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
          <th colspan="7" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub"><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : 'STS Tickets Report'); ?></div>
                <?php if ($office_addr !== ''): ?>
                <div class="addr"><?php echo htmlspecialchars($office_addr); ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <td colspan="7" style="background:#fff;border:0;padding:6px 0 0 0">
            <div class="filters"><?php echo htmlspecialchars($filterLabel); ?></div>
          </td>
        </tr>
        <tr>
          <td colspan="7" style="background:#fff;border:0;padding:0">
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
          <th style="width:12%">Date</th>
          <th style="width:22%">Ticket No</th>
          <th style="width:12%">Plate</th>
          <th style="width:26%">Violation</th>
          <th style="width:10%">Fine</th>
          <th style="width:10%">Status</th>
          <th style="width:8%">Issued By</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($r = $res->fetch_assoc()): ?>
            <?php
              $dt = (string)($r['date_issued'] ?? '');
              if ($dt === '' && !empty($r['violation_date'])) $dt = (string)$r['violation_date'];
              $dt = $dt !== '' ? date('Y-m-d', strtotime($dt)) : '';
              $viol = trim((string)($r['violation_type'] ?? ''));
              $vdesc = trim((string)($r['violation_desc'] ?? ''));
              $violLabel = $viol . ($vdesc !== '' ? (' • ' . $vdesc) : '');
            ?>
            <tr>
              <td><?php echo htmlspecialchars($dt !== '' ? $dt : '-'); ?></td>
              <td><?php echo htmlspecialchars((string)($r['sts_ticket_no'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars((string)($r['plate_number'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars($violLabel !== '' ? $violLabel : '-'); ?></td>
              <td>₱<?php echo number_format((float)($r['fine_amount'] ?? 0), 2); ?></td>
              <td><?php echo htmlspecialchars((string)($r['status'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars((string)($r['issued_by'] ?? '-')); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7" class="py-6 text-center text-slate-500">No tickets found.</td></tr>
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
