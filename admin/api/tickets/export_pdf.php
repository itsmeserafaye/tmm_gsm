<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: text/html; charset=utf-8');

$period = strtolower(trim($_GET['period'] ?? ''));
$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$officer_id = isset($_GET['officer_id']) ? (int)$_GET['officer_id'] : 0;

$officer = null;
if ($officer_id > 0) {
  $stmtO = $db->prepare("SELECT name, badge_no FROM officers WHERE officer_id=?");
  $stmtO->bind_param('i', $officer_id);
  $stmtO->execute();
  $officer = $stmtO->get_result()->fetch_assoc();
}

$sql = "SELECT ticket_number, violation_code, vehicle_plate, status, fine_amount, date_issued, issued_by, issued_by_badge FROM tickets";
$conds = [];
if ($status !== '' && in_array($status, ['Pending','Validated','Settled','Escalated'])) { $conds[] = "status='".$db->real_escape_string($status)."'"; }
if ($period === '30d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
if ($period === '90d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
if ($period === 'year') { $conds[] = "YEAR(date_issued) = YEAR(NOW())"; }
if ($q !== '') { $qv = $db->real_escape_string($q); $conds[] = "(vehicle_plate LIKE '%$qv%' OR ticket_number LIKE '%$qv%')"; }
if ($officer_id > 0) { $conds[] = "officer_id=".$officer_id; }
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY date_issued DESC LIMIT 500";
$items = $db->query($sql);

$issued = 0; $settled = 0; $validated = 0;
if ($officer_id > 0) {
  $issued = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE officer_id=".$officer_id)->fetch_assoc()['c'] ?? 0);
  $settled = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE officer_id=".$officer_id." AND status='Settled'")->fetch_assoc()['c'] ?? 0);
  $validated = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE officer_id=".$officer_id." AND status='Validated'")->fetch_assoc()['c'] ?? 0);
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Ticket Report</title>
<style>
  body { font-family: Arial, sans-serif; color: #111; }
  h1 { font-size: 20px; margin: 0 0 8px; }
  .meta { font-size: 12px; color: #555; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; font-size: 12px; }
  th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
  th { background: #f2f2f2; }
  .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 12px 0; }
  .card { border: 1px solid #ccc; padding: 8px; }
  .small { font-size: 11px; color: #777; }
  @media print { .noprint { display: none; } }
</style>
</head>
<body>
  <div class="noprint" style="text-align:right;margin-bottom:10px;">
    <button onclick="window.print()">Print</button>
  </div>
  <h1><?php echo $officer ? 'Officer Report: '.htmlspecialchars($officer['name']).' — '.htmlspecialchars($officer['badge_no']) : 'Ticket Report'; ?></h1>
  <div class="meta">
    Generated: <?php echo date('Y-m-d H:i'); ?> |
    Filters: <?php echo htmlspecialchars(($period?:'All').' / '.($status?:'All').' / '.($q?:'')); ?>
  </div>
  <?php if ($officer_id > 0): ?>
    <div class="grid">
      <div class="card"><div class="small">Issued</div><div style="font-weight:bold;"><?php echo $issued; ?></div></div>
      <div class="card"><div class="small">Settled</div><div style="font-weight:bold;"><?php echo $settled; ?></div></div>
      <div class="card"><div class="small">Settlement Rate</div><div style="font-weight:bold;"><?php echo ($issued>0)? round(($settled/$issued)*100) : 0; ?>%</div></div>
    </div>
  <?php endif; ?>
  <table>
    <thead>
      <tr>
        <th>Ticket #</th>
        <th>Violation</th>
        <th>Plate</th>
        <th>Status</th>
        <th>Fine</th>
        <th>Issued</th>
        <th>Officer</th>
        <th>Badge</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($items && $items->num_rows > 0): while($r = $items->fetch_assoc()): ?>
      <tr>
        <td><?php echo htmlspecialchars($r['ticket_number']); ?></td>
        <td><?php echo htmlspecialchars($r['violation_code']); ?></td>
        <td><?php echo htmlspecialchars($r['vehicle_plate']); ?></td>
        <td><?php echo htmlspecialchars($r['status']); ?></td>
        <td><?php echo number_format((float)$r['fine_amount'], 2); ?></td>
        <td><?php echo htmlspecialchars($r['date_issued']); ?></td>
        <td><?php echo htmlspecialchars($r['issued_by']); ?></td>
        <td><?php echo htmlspecialchars($r['issued_by_badge']); ?></td>
      </tr>
      <?php endwhile; else: ?>
      <tr><td colspan="8">No records.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
