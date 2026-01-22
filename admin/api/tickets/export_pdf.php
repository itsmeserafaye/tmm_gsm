<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_permission('reports.export');

$format = strtolower(trim((string)($_GET['format'] ?? 'pdf')));

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

$sql = "SELECT ticket_number, external_ticket_number, ticket_source, violation_code, sts_violation_code, vehicle_plate, status, fine_amount, date_issued, issued_by, issued_by_badge FROM tickets";
$conds = [];
if ($status !== '' && in_array($status, ['Pending','Validated','Settled','Escalated'])) { $conds[] = "status='".$db->real_escape_string($status)."'"; }
if ($period === '30d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
if ($period === '90d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
if ($period === 'year') { $conds[] = "YEAR(date_issued) = YEAR(NOW())"; }
if ($q !== '') { $qv = $db->real_escape_string($q); $conds[] = "(vehicle_plate LIKE '%$qv%' OR ticket_number LIKE '%$qv%' OR external_ticket_number LIKE '%$qv%')"; }
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

if ($format !== 'html') {
  $title = $officer ? ('Officer Report: ' . (string)($officer['name'] ?? '') . ' — ' . (string)($officer['badge_no'] ?? '')) : 'Ticket Report';
  $generated = date('Y-m-d H:i');
  $filters = (($period ?: 'All') . ' / ' . ($status ?: 'All') . ' / ' . ($q ?: ''));

  $lines = [];
  $lines[] = $title;
  $lines[] = 'Generated: ' . $generated;
  $lines[] = 'Filters: ' . $filters;
  if ($officer_id > 0) {
    $rate = ($issued > 0) ? (string)round(($settled / $issued) * 100) . '%' : '0%';
    $lines[] = 'Issued: ' . $issued . '   Settled: ' . $settled . '   Settlement Rate: ' . $rate;
  }
  $lines[] = str_repeat('-', 94);
  $lines[] = 'TICKET#          PLATE      VIOLATION   STATUS     FINE     ISSUED             STS TICKET#';
  $lines[] = str_repeat('-', 94);

  if ($items && $items->num_rows > 0) {
    while ($r = $items->fetch_assoc()) {
      $ticketNo = (string)($r['ticket_number'] ?? '');
      $plate = (string)($r['vehicle_plate'] ?? '');
      $viol = (string)($r['violation_code'] ?? '');
      $st = (string)($r['status'] ?? '');
      $fine = number_format((float)($r['fine_amount'] ?? 0), 2);
      $issuedAt = (string)($r['date_issued'] ?? '');
      $stsTicket = (string)($r['external_ticket_number'] ?? '');

      $ticketNo = substr($ticketNo, 0, 16);
      $plate = substr($plate, 0, 10);
      $viol = substr($viol, 0, 10);
      $st = substr($st, 0, 9);
      $fine = substr($fine, 0, 8);
      $issuedAt = substr($issuedAt, 0, 18);
      $stsTicket = substr($stsTicket, 0, 16);

      $lines[] = sprintf(
        "%-16s %-10s %-10s %-9s %8s %-18s %-16s",
        $ticketNo,
        $plate,
        $viol,
        $st,
        $fine,
        $issuedAt,
        $stsTicket
      );
    }
  } else {
    $lines[] = 'No records.';
  }

  $pageWidth = 595;
  $pageHeight = 842;
  $marginLeft = 36;
  $startY = 806;
  $leading = 10;
  $maxLines = 70;

  $pages = [];
  $cur = [];
  foreach ($lines as $ln) {
    $cur[] = (string)$ln;
    if (count($cur) >= $maxLines) {
      $pages[] = $cur;
      $cur = [];
    }
  }
  if ($cur) $pages[] = $cur;
  if (!$pages) $pages[] = ['No records.'];

  $toWin1252 = function ($s) {
    $s = (string)$s;
    if (function_exists('iconv')) {
      $v = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
      if ($v !== false && $v !== null) return $v;
    }
    return $s;
  };

  $pdfEsc = function ($s) use ($toWin1252) {
    $s = $toWin1252($s);
    $s = str_replace("\\", "\\\\", $s);
    $s = str_replace("(", "\\(", $s);
    $s = str_replace(")", "\\)", $s);
    $s = preg_replace("/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/", "", $s);
    return $s;
  };

  $objects = [];
  $addObj = function ($body) use (&$objects) {
    $objects[] = (string)$body;
    return count($objects);
  };

  $catalogId = $addObj('');
  $pagesId = $addObj('');
  $fontId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>");

  $pageObjIds = [];
  $contentObjIds = [];
  foreach ($pages as $pageLines) {
    $content = "BT\n/F1 9 Tf\n" . $leading . " TL\n1 0 0 1 " . $marginLeft . " " . $startY . " Tm\n";
    foreach ($pageLines as $ln) {
      $content .= "(" . $pdfEsc($ln) . ") Tj\nT*\n";
    }
    $content .= "ET\n";
    $contentObjId = $addObj("<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream");
    $contentObjIds[] = $contentObjId;
    $pageObjId = $addObj("<< /Type /Page /Parent " . $pagesId . " 0 R /MediaBox [0 0 " . $pageWidth . " " . $pageHeight . "] /Resources << /Font << /F1 " . $fontId . " 0 R >> >> /Contents " . $contentObjId . " 0 R >>");
    $pageObjIds[] = $pageObjId;
  }

  $kids = implode(' ', array_map(function ($id) { return $id . " 0 R"; }, $pageObjIds));
  $objects[$pagesId - 1] = "<< /Type /Pages /Count " . count($pageObjIds) . " /Kids [ " . $kids . " ] >>";
  $objects[$catalogId - 1] = "<< /Type /Catalog /Pages " . $pagesId . " 0 R >>";

  $pdf = "%PDF-1.4\n";
  $offsets = [0];
  for ($i = 0; $i < count($objects); $i++) {
    $offsets[] = strlen($pdf);
    $pdf .= ($i + 1) . " 0 obj\n" . $objects[$i] . "\nendobj\n";
  }
  $xrefPos = strlen($pdf);
  $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
  $pdf .= "0000000000 65535 f \n";
  for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
  }
  $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root " . $catalogId . " 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="ticket_report_' . date('Ymd_His') . '.pdf"');
  header('Content-Length: ' . strlen($pdf));
  echo $pdf;
  exit;
}

header('Content-Type: text/html; charset=utf-8');
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
        <th>STS Ticket #</th>
        <th>Source</th>
        <th>Violation</th>
        <th>STS Code</th>
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
        <td><?php echo htmlspecialchars((string)($r['external_ticket_number'] ?? '')); ?></td>
        <td><?php echo htmlspecialchars((string)($r['ticket_source'] ?? '')); ?></td>
        <td><?php echo htmlspecialchars($r['violation_code']); ?></td>
        <td><?php echo htmlspecialchars((string)($r['sts_violation_code'] ?? '')); ?></td>
        <td><?php echo htmlspecialchars($r['vehicle_plate']); ?></td>
        <td><?php echo htmlspecialchars($r['status']); ?></td>
        <td><?php echo number_format((float)$r['fine_amount'], 2); ?></td>
        <td><?php echo htmlspecialchars($r['date_issued']); ?></td>
        <td><?php echo htmlspecialchars($r['issued_by']); ?></td>
        <td><?php echo htmlspecialchars($r['issued_by_badge']); ?></td>
      </tr>
      <?php endwhile; else: ?>
      <tr><td colspan="11">No records.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
