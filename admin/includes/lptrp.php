<?php
function tmm_table_has_column(mysqli $db, string $table, string $column): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('ss', $table, $column);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_row();
  $stmt->close();
  return $ok;
}

function tmm_get_lptrp_route(mysqli $db, string $routeCode): ?array {
  $routeCode = trim($routeCode);
  if ($routeCode === '') return null;
  $stmt = $db->prepare("SELECT * FROM lptrp_routes WHERE route_code=? LIMIT 1");
  if (!$stmt) return null;
  $stmt->bind_param('s', $routeCode);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ?: null;
}

function tmm_lptrp_is_approved(?array $row): bool {
  if (!$row) return false;
  $v = '';
  if (isset($row['approval_status'])) $v = (string)$row['approval_status'];
  elseif (isset($row['status'])) $v = (string)$row['status'];
  $v = strtolower(trim($v));
  if ($v === '') return true;
  return in_array($v, ['approved', 'active'], true);
}

function tmm_sync_routes_from_lptrp(mysqli $db, ?string $routeCode = null): bool {
  $hasDesc = tmm_table_has_column($db, 'lptrp_routes', 'description');
  $hasRouteName = tmm_table_has_column($db, 'lptrp_routes', 'route_name');
  $hasStart = tmm_table_has_column($db, 'lptrp_routes', 'start_point');
  $hasEnd = tmm_table_has_column($db, 'lptrp_routes', 'end_point');
  $hasMax = tmm_table_has_column($db, 'lptrp_routes', 'max_vehicle_capacity');
  $hasCurr = tmm_table_has_column($db, 'lptrp_routes', 'current_vehicle_count');
  $hasApproval = tmm_table_has_column($db, 'lptrp_routes', 'approval_status');
  $hasStatus = tmm_table_has_column($db, 'lptrp_routes', 'status');

  $descExpr = $hasDesc ? "lr.description" : ($hasRouteName ? "lr.route_name" : "''");
  if ($hasStart && $hasEnd) {
    $descExpr = "COALESCE(NULLIF($descExpr,''), NULLIF(CONCAT_WS(' → ', lr.start_point, lr.end_point),''), lr.route_code)";
  } else {
    $descExpr = "COALESCE(NULLIF($descExpr,''), lr.route_code)";
  }

  $statusExprBase = $hasApproval ? "lr.approval_status" : ($hasStatus ? "lr.status" : "''");
  $statusExpr = "CASE WHEN LOWER(TRIM($statusExprBase)) IN ('', 'approved', 'active') THEN 'Active' ELSE 'Inactive' END";

  $routesHasOrigin = tmm_table_has_column($db, 'routes', 'origin');
  $routesHasDest = tmm_table_has_column($db, 'routes', 'destination');
  $routesHasDistance = tmm_table_has_column($db, 'routes', 'distance_km');
  $routesHasFare = tmm_table_has_column($db, 'routes', 'fare');

  $cols = ['route_id', 'route_name', 'max_vehicle_limit', 'status'];
  $selects = ["lr.route_code AS route_id", "$descExpr AS route_name", ($hasMax ? "lr.max_vehicle_capacity" : "0") . " AS max_vehicle_limit", "$statusExpr AS status"];
  $updates = [
    "route_name=VALUES(route_name)",
    "max_vehicle_limit=VALUES(max_vehicle_limit)",
    "status=VALUES(status)"
  ];

  if ($routesHasOrigin) {
    $cols[] = 'origin';
    $selects[] = $hasStart ? "lr.start_point AS origin" : "'' AS origin";
    $updates[] = "origin=VALUES(origin)";
  }
  if ($routesHasDest) {
    $cols[] = 'destination';
    $selects[] = $hasEnd ? "lr.end_point AS destination" : "'' AS destination";
    $updates[] = "destination=VALUES(destination)";
  }
  if ($routesHasDistance) {
    $cols[] = 'distance_km';
    $selects[] = "NULL AS distance_km";
    $updates[] = "distance_km=COALESCE(distance_km, VALUES(distance_km))";
  }
  if ($routesHasFare) {
    $cols[] = 'fare';
    $selects[] = "NULL AS fare";
    $updates[] = "fare=COALESCE(fare, VALUES(fare))";
  }

  $where = '';
  if ($routeCode !== null && trim($routeCode) !== '') {
    $where = " WHERE lr.route_code = '" . $db->real_escape_string(trim($routeCode)) . "'";
  }

  $sql = "INSERT INTO routes (" . implode(',', $cols) . ")
    SELECT " . implode(',', $selects) . "
    FROM lptrp_routes lr" . $where . "
    ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

  return (bool)$db->query($sql);
}

