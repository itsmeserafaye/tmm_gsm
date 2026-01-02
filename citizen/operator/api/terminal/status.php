<?php
require_once __DIR__ . '/../common.php';
$applicant = trim($_GET['applicant'] ?? '');
$terminalName = trim($_GET['terminal_name'] ?? '');
$conds = [];
$params = [];
$types = '';
$sql = "SELECT p.id, p.terminal_id, p.application_no, p.applicant_name, p.status, p.issue_date, p.expiry_date, t.name AS terminal_name 
        FROM terminal_permits p 
        JOIN terminals t ON p.terminal_id=t.id";
if ($applicant !== '') { $conds[] = "p.applicant_name LIKE ?"; $params[] = "%$applicant%"; $types .= 's'; }
if ($terminalName !== '') { $conds[] = "t.name LIKE ?"; $params[] = "%$terminalName%"; $types .= 's'; }
if ($conds) { $sql .= " WHERE " . implode(' AND ', $conds); }
$sql .= " ORDER BY p.created_at DESC LIMIT 50";
if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); }
else { $res = $db->query($sql); }
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
json_ok(['items' => $rows]);
