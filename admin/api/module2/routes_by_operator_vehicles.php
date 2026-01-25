<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.read', 'module2.endorse', 'module2.approve', 'module2.history', 'module2.apply']);

$operatorId = (int) ($_GET['operator_id'] ?? 0);

if ($operatorId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
    exit;
}

// Get distinct vehicle types for this operator
$stmt = $db->prepare("SELECT DISTINCT v.vehicle_type 
                      FROM vehicles v 
                      WHERE v.operator_id = ? 
                        AND COALESCE(v.record_status, '') <> 'Archived'
                        AND v.vehicle_type IS NOT NULL 
                        AND v.vehicle_type <> ''");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}

$stmt->bind_param('i', $operatorId);
$stmt->execute();
$result = $stmt->get_result();

$vehicleTypes = [];
while ($row = $result->fetch_assoc()) {
    $vt = trim((string) ($row['vehicle_type'] ?? ''));
    if ($vt !== '') {
        $vehicleTypes[] = $vt;
    }
}
$stmt->close();

// If operator has no vehicles, return empty list
if (empty($vehicleTypes)) {
    echo json_encode(['ok' => true, 'data' => [], 'vehicle_types' => [], 'message' => 'no_vehicles']);
    exit;
}

// Build query to get routes matching operator's vehicle types
$placeholders = implode(',', array_fill(0, count($vehicleTypes), '?'));
$types = str_repeat('s', count($vehicleTypes));

$sql = "SELECT
  r.id,
  r.route_id,
  COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code,
  r.route_name,
  r.vehicle_type,
  r.origin,
  r.destination,
  r.via,
  r.fare,
  r.distance_km,
  COALESCE(r.authorized_units, r.max_vehicle_limit, 0) AS authorized_units,
  COALESCE(COUNT(DISTINCT v.id), 0) AS active_units
FROM routes r
LEFT JOIN franchise_applications fa ON fa.route_id=r.id AND fa.status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')
LEFT JOIN vehicles v ON COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0), 0)=fa.operator_id AND COALESCE(v.record_status,'') <> 'Archived'
WHERE r.vehicle_type IN ($placeholders)
  AND COALESCE(r.status, '') = 'Active'
GROUP BY r.id
ORDER BY r.vehicle_type ASC, COALESCE(NULLIF(r.route_name,''), COALESCE(NULLIF(r.route_code,''), r.route_id)) ASC
LIMIT 1000";

$stmt = $db->prepare($sql);
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}

// Bind vehicle types dynamically
$stmt->bind_param($types, ...$vehicleTypes);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}
$stmt->close();

echo json_encode([
    'ok' => true,
    'data' => $rows,
    'vehicle_types' => $vehicleTypes,
    'total' => count($rows)
]);
?>