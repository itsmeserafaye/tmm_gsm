<?php
require_once __DIR__ . '/../includes/db.php';

$db = db();

// WARNING: Dev-only helper. Do not use in production.

// 1. Clear existing franchise applications and related endorsements
$db->query("DELETE FROM endorsement_records");
$db->query("DELETE FROM franchise_applications");

// Also clear obvious demo coops, operators, and vehicles if they exist
$db->query("DELETE FROM coops WHERE coop_name LIKE 'Demo %'");
$db->query("DELETE FROM operators WHERE full_name LIKE 'Demo %'");
$db->query("DELETE FROM vehicles WHERE plate_number LIKE 'DEMO-%'");
$db->query("DELETE FROM terminal_assignments");
$db->query("DELETE FROM routes");

// 2. Insert realistic cooperatives
// These mirror real-world LGU-recognized transport cooperatives with full details.
$coops = [
    ['Central City Transport Cooperative', 'Transport Terminal, City Hall Complex', 'Engr. Luis Navarro', 'LGU-2024-10001'],
    ['Metro Urban Jeepney MPC', 'Depot Compound, North National Highway', 'Salve Ramos', 'LGU-2024-10002'],
    ['Southside Route Operators TSC', 'South Integrated Public Market, Coastal Road', 'Arman Villanueva', 'LGU-2024-10003'],
];

$coopIds = [];
$stmtCoop = $db->prepare("INSERT IGNORE INTO coops (coop_name, address, chairperson_name, lgu_approval_number) VALUES (?,?,?,?)");
foreach ($coops as $c) {
    [$name, $addr, $chair, $lgu] = $c;
    $stmtCoop->bind_param('ssss', $name, $addr, $chair, $lgu);
    $stmtCoop->execute();
    $id = (int)$db->insert_id;
    if ($id <= 0) {
        $check = $db->prepare("SELECT id FROM coops WHERE coop_name=? LIMIT 1");
        $check->bind_param('s', $name);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        $id = (int)($row['id'] ?? 0);
    }
    $coopIds[$name] = $id;
}

// 3. Insert realistic operators linked by name to coops
$operators = [
    ['Mark Anthony Rivera', 'Blk 7 Lot 3, Brgy. San Isidro', '09171234567', 'Central City Transport Cooperative'],
    ['Liza Marie Soriano', '21 Mabini St, Brgy. Malinis', '09181234567', 'Metro Urban Jeepney MPC'],
    ['Rogelio “Jun” Bautista', '96 Rizal Ave, Brgy. Poblacion', '09191234567', 'Southside Route Operators TSC'],
    ['Catherine D. Flores', '15 PNR Road, Brgy. Sta. Cruz', '09201234567', 'Central City Transport Cooperative'],
];

$opIds = [];
$stmtOp = $db->prepare("INSERT IGNORE INTO operators (full_name, contact_info, coop_name) VALUES (?,?,?)");
foreach ($operators as $o) {
    [$name, $addr, $contact, $coopName] = $o;
    $stmtOp->bind_param('sss', $name, $contact, $coopName);
    $stmtOp->execute();
    $id = (int)$db->insert_id;
    if ($id <= 0) {
        $check = $db->prepare("SELECT id FROM operators WHERE full_name=? LIMIT 1");
        $check->bind_param('s', $name);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        $id = (int)($row['id'] ?? 0);
    }
    $opIds[$name] = $id;
}

// 4. Insert realistic franchise applications that follow the 2024-00123 template
$apps = [
    ['2024-00001', 'New',      'Pending',   10, 'Initial application for 10 modern jeepney units on central loop.', 'Mark Anthony Rivera', 'Central City Transport Cooperative'],
    ['2024-00002', 'Renewal',  'Endorsed',   8, 'Renewal of existing units with complete LGU and LTFRB documents.', 'Liza Marie Soriano', 'Metro Urban Jeepney MPC'],
    ['2024-00003', 'New',      'Rejected',   5, 'Rejected due to missing latest LTFRB franchise copy.', 'Rogelio “Jun” Bautista', 'Southside Route Operators TSC'],
    ['2025-00123', 'Renewal',  'Endorsed',  12, 'Franchise renewal aligned with updated LPTRP route and capacity.', 'Catherine D. Flores', 'Central City Transport Cooperative'],
];

$stmt = $db->prepare("INSERT INTO franchise_applications (franchise_ref_number, operator_id, coop_id, vehicle_count, status, submitted_at, operator_name, application_type, submission_date, notes) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, CURDATE(), ?)");

$frInfo = [];

foreach ($apps as $row) {
    [$ref, $type, $status, $count, $notes, $opName, $coopName] = $row;
    $opId = $opIds[$opName] ?? null;
    $coopId = $coopIds[$coopName] ?? null;
    if ($opId === null || $coopId === null) continue;
    $stmt->bind_param('siiissss', $ref, $opId, $coopId, $count, $status, $opName, $type, $notes);
    $stmt->execute();
    $frInfo[$ref] = [
        'operator_name' => $opName,
        'status' => $status
    ];
}

$routes = [
    ['R_001', 'Central City Loop', 'Central Terminal', 'City Hall Complex', 6.5, 14.00, 40, 'Active'],
    ['R_002', 'North Market – City Hall', 'North Public Market', 'City Hall Complex', 9.2, 16.00, 35, 'Active'],
    ['R_003', 'South Coastal Service', 'South Integrated Public Market', 'Coastal Road', 11.8, 18.00, 30, 'Active'],
    ['R_004', 'University – Mall Express', 'State University', 'North National Highway Mall', 7.4, 15.00, 25, 'Active'],
];

$stmtRoute = $db->prepare("INSERT INTO routes(route_id, route_name, origin, destination, distance_km, fare, max_vehicle_limit, status) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE route_name=VALUES(route_name), origin=VALUES(origin), destination=VALUES(destination), distance_km=VALUES(distance_km), fare=VALUES(fare), max_vehicle_limit=VALUES(max_vehicle_limit), status=VALUES(status)");

foreach ($routes as $r) {
    $stmtRoute->bind_param('ssssddis', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7]);
    $stmtRoute->execute();
}

// 5. Seed multiple realistic vehicles linked to the demo franchises
$vehicles = [
    // Endorsed franchises → Active vehicles
    ['plate' => 'ABC-1234', 'type' => 'Jeepney', 'fr_ref' => '2024-00002'],
    ['plate' => 'XYZ-5678', 'type' => 'Jeepney', 'fr_ref' => '2025-00123'],
    // Pending franchise → Suspended vehicle (awaiting endorsement)
    ['plate' => 'MNO-4321', 'type' => 'Jeepney', 'fr_ref' => '2024-00001'],
    // Non-endorsed franchise → Suspended vehicle
    ['plate' => 'JKL-9001', 'type' => 'Jeepney', 'fr_ref' => '2024-00003'],
];

$stmtVeh = $db->prepare("INSERT INTO vehicles(plate_number, vehicle_type, operator_name, franchise_id, route_id, status) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE vehicle_type=VALUES(vehicle_type), operator_name=VALUES(operator_name), franchise_id=VALUES(franchise_id), route_id=VALUES(route_id), status=VALUES(status)");

foreach ($vehicles as $v) {
    $ref = $v['fr_ref'];
    if (!isset($frInfo[$ref])) continue;
    $opName = $frInfo[$ref]['operator_name'];
    $frStatus = $frInfo[$ref]['status'] ?? '';
    $vehStatus = ($frStatus === 'Endorsed') ? 'Active' : 'Suspended';
    $routeId = '';
    $stmtVeh->bind_param(
        'ssssss',
        $v['plate'],
        $v['type'],
        $opName,
        $ref,
        $routeId,
        $vehStatus
    );
    $stmtVeh->execute();
}

// Vehicle without any franchise reference (automatically Suspended)
$noFrPlate = 'PQR-8888';
$noFrType = 'Jeepney';
$noFrOperator = 'Mark Anthony Rivera';
$noFrFranchise = '';
$noFrRoute = '';
$noFrStatus = 'Suspended';
$stmtVeh->bind_param(
    'ssssss',
    $noFrPlate,
    $noFrType,
    $noFrOperator,
    $noFrFranchise,
    $noFrRoute,
    $noFrStatus
);
$stmtVeh->execute();

echo "Realistic franchise applications, routes, and demo vehicles seeded.\n";
