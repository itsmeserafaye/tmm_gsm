<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
require_any_permission(['module2.view','module2.franchises.manage']);
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$limit = (int)($_GET['page_size'] ?? 50);
$page = (int)($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

$sql = "SELECT fa.*, COALESCE(NULLIF(o.name,''), o.full_name) as operator, c.coop_name 
        FROM franchise_applications fa 
        LEFT JOIN operators o ON fa.operator_id = o.id 
        LEFT JOIN coops c ON fa.coop_id = c.id";

$conds = [];
$params = [];
$types = '';

if ($q !== '') {
    $conds[] = "(fa.franchise_ref_number LIKE ? OR o.full_name LIKE ? OR o.name LIKE ? OR c.coop_name LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
    $params[] = "%$q%";
    $types .= 'ssss';
}
if ($status !== '' && $status !== 'Status') {
    $conds[] = "fa.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($conds) {
    $sql .= " WHERE " . implode(" AND ", $conds);
}

// Count total for pagination
$countSql = "SELECT COUNT(*) as c FROM franchise_applications fa 
             LEFT JOIN operators o ON fa.operator_id = o.id 
             LEFT JOIN coops c ON fa.coop_id = c.id";
if ($conds) {
    $countSql .= " WHERE " . implode(" AND ", $conds);
}
if ($params) {
    $stmtC = $db->prepare($countSql);
    $stmtC->bind_param($types, ...$params);
    $stmtC->execute();
    $total = $stmtC->get_result()->fetch_assoc()['c'];
} else {
    $total = $db->query($countSql)->fetch_assoc()['c'];
}

$sql .= " ORDER BY fa.submitted_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $db->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'data' => $rows,
    'meta' => [
        'total' => $total,
        'page' => $page,
        'page_size' => $limit,
        'total_pages' => ceil($total / $limit)
    ]
]);
