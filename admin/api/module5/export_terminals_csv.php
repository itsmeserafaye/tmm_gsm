<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';
$db = db();
require_permission('reports.export');

$format = tmm_export_format();
tmm_send_export_headers($format, 'terminals');

$type = trim((string)($_GET['type'] ?? 'Terminal'));
$type = $type === 'Parking' ? 'Parking' : 'Terminal';
$q = trim((string)($_GET['q'] ?? ''));
$ownerFilter = trim((string)($_GET['owner'] ?? ''));
$location = trim((string)($_GET['location'] ?? ''));
$city = trim((string)($_GET['city'] ?? ''));
$cat = trim((string)($_GET['category'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$permitPresence = trim((string)($_GET['permit'] ?? ''));
$capacity = trim((string)($_GET['capacity'] ?? ''));

$termCols = [];
$termColRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminals'");
if ($termColRes) while ($c = $termColRes->fetch_assoc()) $termCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
$hasCityCol = isset($termCols['city']);
$hasCategoryCol = isset($termCols['category']);
$hasStatusCol = isset($termCols['status']);

$facilityOwnerExpr = "NULL";
$faExists = (bool)($db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_agreements' LIMIT 1")?->fetch_row());
$foExists = (bool)($db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_owners' LIMIT 1")?->fetch_row());
$faTidCol = '';
if ($faExists && $foExists) {
  $faCols = [];
  $resCols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_agreements'");
  if ($resCols) while ($c = $resCols->fetch_assoc()) $faCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
  $faTidCol = isset($faCols['terminal_id']) ? 'terminal_id' : (isset($faCols['facility_id']) ? 'facility_id' : '');
  $statusCol = isset($faCols['status']) ? 'status' : '';
  $createdCol = isset($faCols['created_at']) ? 'created_at' : '';
  if ($faTidCol !== '') {
    $order = $statusCol !== '' ? "FIELD(fa.$statusCol, 'Active', 'Expiring Soon', 'Expired', 'Terminated'), " : '';
    $order .= $createdCol !== '' ? "fa.$createdCol DESC" : "fa.id DESC";
    $facilityOwnerExpr = "(SELECT fo.name FROM facility_agreements fa JOIN facility_owners fo ON fa.owner_id = fo.id WHERE fa.$faTidCol = t.id ORDER BY $order LIMIT 1)";
  }
}

$permitAnyExpr = '';
try {
  $parts = [];
  $chkDocs = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_documents' LIMIT 1");
  if ($chkDocs && $chkDocs->fetch_row()) {
    $cols = [];
    $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_documents'");
    if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
    $dTidCol = isset($cols['terminal_id']) ? 'terminal_id' : (isset($cols['facility_id']) ? 'facility_id' : '');
    $dTypeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['type']) ? 'type' : (isset($cols['document_type']) ? 'document_type' : ''));
    if ($dTidCol !== '' && $dTypeCol !== '') $parts[] = "EXISTS (SELECT 1 FROM facility_documents d WHERE d.$dTidCol=t.id AND LOWER(COALESCE(d.$dTypeCol,'')) LIKE '%permit%')";
  }
  $chkPerm = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits' LIMIT 1");
  if ($chkPerm && $chkPerm->fetch_row()) {
    $cols = [];
    $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits'");
    if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
    $pTidCol = isset($cols['terminal_id']) ? 'terminal_id' : (isset($cols['facility_id']) ? 'facility_id' : '');
    if ($pTidCol !== '') $parts[] = "EXISTS (SELECT 1 FROM terminal_permits p2 WHERE p2.$pTidCol=t.id)";
  }
  if ($parts) $permitAnyExpr = '(' . implode(' OR ', $parts) . ')';
} catch (Throwable $e) {}

$where = $type === 'Parking' ? "t.type='Parking'" : "t.type <> 'Parking'";
$params = [];
$types = '';
if ($q !== '') {
  $qLike = '%' . $q . '%';
  $where .= " AND (t.name LIKE ? OR COALESCE(t.location,'') LIKE ? OR COALESCE(t.address,'') LIKE ?)";
  $types .= 'sss';
  $params[] = $qLike; $params[] = $qLike; $params[] = $qLike;
}
if ($ownerFilter !== '' && $facilityOwnerExpr !== "NULL" && $faTidCol !== '') {
  $where .= " AND EXISTS (SELECT 1 FROM facility_agreements fa JOIN facility_owners fo ON fa.owner_id=fo.id WHERE fa.$faTidCol=t.id AND fo.name = ?)";
  $types .= 's';
  $params[] = $ownerFilter;
}
if ($location !== '') { $where .= " AND COALESCE(t.location,'') = ?"; $types .= 's'; $params[] = $location; }
if ($city !== '' && $hasCityCol) { $where .= " AND COALESCE(t.city,'') = ?"; $types .= 's'; $params[] = $city; }
if ($cat !== '' && $hasCategoryCol) { $where .= " AND COALESCE(t.category,'') = ?"; $types .= 's'; $params[] = $cat; }
if ($status !== '' && $hasStatusCol) {
  if (strcasecmp($status, 'Active') === 0) $where .= " AND COALESCE(NULLIF(TRIM(t.status),''),'Active')='Active'";
  else { $where .= " AND COALESCE(NULLIF(TRIM(t.status),''),'Active') = ?"; $types .= 's'; $params[] = $status; }
}
if ($capacity !== '') { $where .= " AND COALESCE(t.capacity,0) = ?"; $types .= 'i'; $params[] = (int)$capacity; }
if ($permitPresence !== '' && $permitAnyExpr !== '') {
  $pv = strtolower($permitPresence);
  if ($pv === 'yes' || $pv === 'permitted' || $pv === 'permit') $where .= " AND $permitAnyExpr";
  if ($pv === 'no' || $pv === 'not_permitted' || $pv === 'n/a') $where .= " AND NOT $permitAnyExpr";
}

$select = [
  "t.id AS terminal_id",
  "t.name",
  "t.location",
  "t.address",
  "COALESCE(t.capacity,0) AS capacity",
  "t.type",
  ($hasCityCol ? "t.city" : "NULL AS city"),
  ($hasCategoryCol ? "t.category" : "NULL AS category"),
  ($hasStatusCol ? "COALESCE(NULLIF(TRIM(t.status),''),'Active') AS status" : "NULL AS status"),
  ($facilityOwnerExpr !== "NULL" ? "$facilityOwnerExpr AS owner_name" : "NULL AS owner_name"),
  ($permitAnyExpr !== '' ? "CASE WHEN $permitAnyExpr THEN 'Yes' ELSE 'No' END AS permitted" : "NULL AS permitted"),
];

$sql = "SELECT " . implode(', ', $select) . " FROM terminals t WHERE $where ORDER BY t.name ASC";
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

$headers = ['terminal_id','name','location','address','capacity','type','city','category','status','owner_name','permitted'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'terminal_id' => $r['terminal_id'] ?? '',
    'name' => $r['name'] ?? '',
    'location' => $r['location'] ?? '',
    'address' => $r['address'] ?? '',
    'capacity' => $r['capacity'] ?? '',
    'type' => $r['type'] ?? '',
    'city' => $r['city'] ?? '',
    'category' => $r['category'] ?? '',
    'status' => $r['status'] ?? '',
    'owner_name' => $r['owner_name'] ?? '',
    'permitted' => $r['permitted'] ?? '',
  ];
});
