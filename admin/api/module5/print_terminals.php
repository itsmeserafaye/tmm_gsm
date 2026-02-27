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

$type = trim((string)($_GET['type'] ?? 'Terminal'));
$type = $type === 'Parking' ? 'Parking' : 'Terminal';
$q = trim((string)($_GET['q'] ?? ''));
$city = trim((string)($_GET['city'] ?? ''));
$cat = trim((string)($_GET['category'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$ownerFilter = trim((string)($_GET['owner'] ?? ''));
$operatorFilter = trim((string)($_GET['operator'] ?? ''));
$permitStatusFilter = trim((string)($_GET['permit_status'] ?? ''));
$agreementTypeFilter = trim((string)($_GET['agreement_type'] ?? ''));
$validFromFilter = trim((string)($_GET['valid_from'] ?? ''));
$validToFilter = trim((string)($_GET['valid_to'] ?? ''));

// Discover owner/operator columns in terminals table
$termCols = [];
$termColRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminals'");
if ($termColRes) while ($c = $termColRes->fetch_assoc()) $termCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
$ownerCol = isset($termCols['owner_name']) ? 'owner_name' : (isset($termCols['owner']) ? 'owner' : (isset($termCols['owned_by']) ? 'owned_by' : ''));
$operatorCol = isset($termCols['operator_name']) ? 'operator_name' : (isset($termCols['operator']) ? 'operator' : (isset($termCols['managed_by']) ? 'managed_by' : ''));
$hasCityCol = isset($termCols['city']);
$hasCategoryCol = isset($termCols['category']);
$hasStatusCol = isset($termCols['status']);

// Discover permit columns in terminal_permits
$permCols = [];
$permColRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits'");
if ($permColRes) while ($c = $permColRes->fetch_assoc()) $permCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
$permStatusCol = isset($permCols['status']) ? 'status' : '';
$permTypeCol = isset($permCols['doc_type']) ? 'doc_type' : (isset($permCols['document_type']) ? 'document_type' : (isset($permCols['type']) ? 'type' : ''));
$permIssueCol = isset($permCols['issue_date']) ? 'issue_date' : (isset($permCols['issued_at']) ? 'issued_at' : (isset($permCols['start_date']) ? 'start_date' : ''));
$permExpiryCol = isset($permCols['expiry_date']) ? 'expiry_date' : (isset($permCols['expires_at']) ? 'expires_at' : (isset($permCols['valid_until']) ? 'valid_until' : ''));
$permCreatedCol = isset($permCols['created_at']) ? 'created_at' : '';
$orderParts = [];
if ($permExpiryCol !== '') $orderParts[] = "p.$permExpiryCol";
if ($permIssueCol !== '') $orderParts[] = "p.$permIssueCol";
if ($permCreatedCol !== '') $orderParts[] = "p.$permCreatedCol";
$permOrderExpr = $orderParts ? ('COALESCE(' . implode(',', $orderParts) . ')') : '1';
$ownerExpr = $ownerCol !== '' ? "t.$ownerCol" : "NULL";
$operatorExpr = $operatorCol !== '' ? "t.$operatorCol" : "NULL";
$permTypeExpr = $permTypeCol !== '' ? "(SELECT p.$permTypeCol FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1)" : "NULL";
$permStatusExpr = $permStatusCol !== '' ? "(SELECT p.$permStatusCol FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1)" : "NULL";
$permIssueExpr = $permIssueCol !== '' ? "(SELECT p.$permIssueCol FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1)" : "NULL";
$permExpiryExpr = $permExpiryCol !== '' ? "(SELECT p.$permExpiryCol FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1)" : "NULL";

$where = $type === 'Parking' ? "t.type='Parking'" : "t.type <> 'Parking'";
$params = [];
$types = '';
if ($q !== '') {
  $qLike = '%' . $q . '%';
  $where .= " AND (t.name LIKE ? OR COALESCE(t.location,'') LIKE ? " . ($hasCityCol ? "OR COALESCE(t.city,'') LIKE ? " : "") . ($hasCategoryCol ? "OR COALESCE(t.category,'') LIKE ? " : "") . ")";
  if ($hasCityCol && $hasCategoryCol) { $types .= 'ssss'; $params[] = $qLike; $params[] = $qLike; $params[] = $qLike; $params[] = $qLike; }
  elseif ($hasCityCol || $hasCategoryCol) { $types .= 'sss'; $params[] = $qLike; $params[] = $qLike; $params[] = $qLike; }
  else { $types .= 'ss'; $params[] = $qLike; $params[] = $qLike; }
}
if ($city !== '' && $hasCityCol) {
  $where .= " AND COALESCE(t.city,'') = ?";
  $types .= 's';
  $params[] = $city;
}
if ($cat !== '' && $hasCategoryCol) {
  $where .= " AND COALESCE(t.category,'') = ?";
  $types .= 's';
  $params[] = $cat;
}
if ($status !== '' && $hasStatusCol) {
  $where .= " AND COALESCE(t.status,'') = ?";
  $types .= 's';
  $params[] = $status;
}
if ($ownerFilter !== '' && $ownerCol !== '') { $where .= " AND COALESCE(t.$ownerCol,'') LIKE ?"; $types .= 's'; $params[] = '%' . $ownerFilter . '%'; }
if ($operatorFilter !== '' && $operatorCol !== '') { $where .= " AND COALESCE(t.$operatorCol,'') LIKE ?"; $types .= 's'; $params[] = '%' . $operatorFilter . '%'; }
if ($permitStatusFilter !== '' && $permStatusCol !== '') { $where .= " AND (SELECT p.$permStatusCol FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1) LIKE ?"; $types .= 's'; $params[] = '%' . $permitStatusFilter . '%'; }
if ($agreementTypeFilter !== '' && $permTypeCol !== '') { $where .= " AND (SELECT p.$permTypeCol FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1) LIKE ?"; $types .= 's'; $params[] = '%' . $agreementTypeFilter . '%'; }
if ($validFromFilter !== '' && ($permExpiryCol !== '' || $permIssueCol !== '')) { $vc = $permExpiryCol !== '' ? $permExpiryCol : $permIssueCol; $where .= " AND (SELECT p.$vc FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1) >= ?"; $types .= 's'; $params[] = $validFromFilter; }
if ($validToFilter !== '' && ($permIssueCol !== '' || $permExpiryCol !== '')) { $vc2 = $permIssueCol !== '' ? $permIssueCol : $permExpiryCol; $where .= " AND (SELECT p.$vc2 FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1) <= ?"; $types .= 's'; $params[] = $validToFilter; }

$sql = "SELECT t.id, t.name, t.location, t.address, t.capacity, " . ($hasCategoryCol ? "t.category" : "NULL") . " AS category,
               $ownerExpr AS owner_name,
               $operatorExpr AS operator_name,
               $permTypeExpr AS permit_type,
               $permStatusExpr AS permit_status,
               $permIssueExpr AS permit_issue_date,
               $permExpiryExpr AS permit_expiry_date" . ($type === 'Parking' ? "" : ",
               COUNT(DISTINCT tr.route_id) AS route_count") . "
        FROM terminals t
        " . ($type === 'Parking' ? "" : "LEFT JOIN terminal_routes tr ON tr.terminal_id=t.id") . "
        WHERE $where
        " . ($type === 'Parking' ? "" : "GROUP BY t.id, t.name, t.location, t.address, t.capacity, category, owner_name, operator_name, permit_type, permit_status, permit_issue_date, permit_expiry_date") . "
        ORDER BY t.name ASC LIMIT 1000";
$res = null;
if ($types !== '') {
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
  }
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
$rep_title = trim((string)($_GET['rep_title'] ?? ($type === 'Parking' ? 'Parking Areas' : 'Terminal List')));
$office_addr = trim((string)(tmm_get_app_setting('office_address','1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.') ?? '1071 Brgy. Kaligayahan, Quirino Highway, Novaliches, Quezon City.'));
$office_email = trim((string)(tmm_get_app_setting('office_email','helpdesk@tmm.gov.ph') ?? 'helpdesk@tmm.gov.ph'));
$office_contact = trim((string)(tmm_get_app_setting('office_contact','') ?? ''));
$public_site = trim((string)(tmm_get_app_setting('public_website','tmm.govservph.com') ?? 'tmm.govservph.com'));
$filterParts = [];
$filterParts[] = 'Type: ' . $type;
if ($q !== '') $filterParts[] = 'Search: ' . $q;
if ($city !== '') $filterParts[] = 'City: ' . $city;
if ($cat !== '') $filterParts[] = 'Category: ' . $cat;
if ($status !== '') $filterParts[] = 'Status: ' . $status;
if ($ownerFilter !== '') $filterParts[] = 'Owner: ' . $ownerFilter;
if ($operatorFilter !== '') $filterParts[] = 'Operator: ' . $operatorFilter;
if ($permitStatusFilter !== '') $filterParts[] = 'Permit Status: ' . $permitStatusFilter;
if ($agreementTypeFilter !== '') $filterParts[] = 'Agreement: ' . $agreementTypeFilter;
if ($validFromFilter !== '') $filterParts[] = 'Valid From: ' . $validFromFilter;
if ($validToFilter !== '') $filterParts[] = 'Valid To: ' . $validToFilter;
$filterLabel = 'Filtered: ' . ($filterParts ? implode('. ', $filterParts) . '.' : 'All.');
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
          <th colspan="5" style="background:#fff;border:0;padding:0">
            <div class="rhead">
              <img class="logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>">
              <div class="rtitle">
                <div class="title">Transport & Mobility Management</div>
                <div class="sub"><?php echo htmlspecialchars($rep_title !== '' ? $rep_title : 'Terminal List'); ?></div>
                <?php if ($office_addr !== ''): ?>
                <div class="addr"><?php echo htmlspecialchars($office_addr); ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div style="border-bottom:2px solid #e2e8f0;margin-top:4px"></div>
          </th>
        </tr>
        <tr>
          <td colspan="5" style="background:#fff;border:0;padding:6px 0 0 0">
            <div class="filters"><?php echo htmlspecialchars($filterLabel); ?></div>
          </td>
        </tr>
        <tr>
          <td colspan="5" style="background:#fff;border:0;padding:0">
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
          <th style="width:22%">Name</th>
          <th style="width:18%">Owner</th>
          <th style="width:18%">Operator</th>
          <th style="width:18%">Permit</th>
          <th style="width:12%"><?php echo $type === 'Parking' ? 'Capacity' : 'Routes'; ?></th>
          <th style="width:12%">Validity</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
          <?php while ($t = $res->fetch_assoc()): ?>
            <tr>
              <td>
                <div style="font-weight:900"><?php echo htmlspecialchars((string)($t['name'] ?? '')); ?></div>
                <div style="font-size:11px;color:#475569"><?php echo htmlspecialchars((string)($t['location'] ?? ($t['address'] ?? ''))); ?></div>
              </td>
              <td><?php echo htmlspecialchars(trim((string)($t['owner_name'] ?? '')) ?: '-'); ?></td>
              <td><?php echo htmlspecialchars(trim((string)($t['operator_name'] ?? '')) ?: '-'); ?></td>
              <td><?php
                $ptype = trim((string)($t['permit_type'] ?? ''));
                $pstat = trim((string)($t['permit_status'] ?? ''));
                echo htmlspecialchars(trim($ptype . ($pstat !== '' ? (' • ' . $pstat) : '')) ?: '-');
              ?></td>
              <td style="text-align:right"><?php echo $type === 'Parking' ? (int)($t['capacity'] ?? 0) : (int)($t['route_count'] ?? 0); ?></td>
              <td><?php
                $pi = trim((string)($t['permit_issue_date'] ?? ''));
                $pe = trim((string)($t['permit_expiry_date'] ?? ''));
                $val = trim($pi . (($pi !== '' || $pe !== '') ? ' → ' : '') . $pe);
                echo htmlspecialchars($val !== '' ? $val : '-');
              ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" class="py-6 text-center text-slate-500">No records found.</td></tr>
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
