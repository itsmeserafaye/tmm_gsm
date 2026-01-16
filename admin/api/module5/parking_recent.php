<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$kind = trim((string)($_GET['kind'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$range = trim((string)($_GET['range'] ?? ''));

$rangeDays = null;
$todayOnly = false;
if ($range === 'today') $todayOnly = true;
elseif ($range === '7d') $rangeDays = 7;
elseif ($range === '30d') $rangeDays = 30;

if ($kind === 'payments') {
  $sql = "SELECT t.vehicle_plate, t.parking_area_id, t.amount, t.status, t.created_at, p.name AS area_name
          FROM parking_transactions t
          LEFT JOIN parking_areas p ON t.parking_area_id = p.id";
  $conds = [];
  $params = [];
  $types = '';

  if ($q !== '') {
    $conds[] = "(t.vehicle_plate LIKE ? OR p.name LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $types .= 'ss';
  }
  if ($status !== '') {
    $conds[] = "t.status = ?";
    $params[] = $status;
    $types .= 's';
  }
  if ($todayOnly) {
    $conds[] = "DATE(t.created_at) = CURDATE()";
  } elseif ($rangeDays !== null) {
    $conds[] = "t.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = $rangeDays;
    $types .= 'i';
  }
  if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
  $sql .= " ORDER BY t.created_at DESC LIMIT 50";

  if ($params) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $res = $db->query($sql);
  }

  $rows = [];
  if ($res) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
  }
  echo json_encode(['ok' => true, 'kind' => 'payments', 'rows' => $rows]);
  exit;
}

if ($kind === 'violations') {
  $sql = "SELECT v.vehicle_plate, v.parking_area_id, v.violation_type, v.penalty_amount, v.status, v.created_at, p.name AS area_name
          FROM parking_violations v
          LEFT JOIN parking_areas p ON v.parking_area_id = p.id";
  $conds = [];
  $params = [];
  $types = '';

  if ($q !== '') {
    $conds[] = "(v.vehicle_plate LIKE ? OR v.violation_type LIKE ? OR p.name LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $types .= 'sss';
  }
  if ($status !== '') {
    $conds[] = "v.status = ?";
    $params[] = $status;
    $types .= 's';
  }
  if ($todayOnly) {
    $conds[] = "DATE(v.created_at) = CURDATE()";
  } elseif ($rangeDays !== null) {
    $conds[] = "v.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = $rangeDays;
    $types .= 'i';
  }
  if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
  $sql .= " ORDER BY v.created_at DESC LIMIT 50";

  if ($params) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $res = $db->query($sql);
  }

  $rows = [];
  if ($res) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
  }
  echo json_encode(['ok' => true, 'kind' => 'violations', 'rows' => $rows]);
  exit;
}

echo json_encode(['ok' => false, 'error' => 'invalid_kind']);
