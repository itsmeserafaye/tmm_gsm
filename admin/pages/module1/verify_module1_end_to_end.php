<?php
define('TMM_TEST', true);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/util.php';
require_once __DIR__ . '/setup_module1_db.php';
$db = db();
echo "=== Module 1 (PUV Database) End-to-End Verification ===\n";

// 1. Cleanup Test Data
echo "\n--- Cleanup ---\n";
$db->query("DELETE FROM terminal_assignments WHERE plate_number = 'TEST-E2E-001'");
$db->query("DELETE FROM vehicles WHERE plate_number = 'TEST-E2E-001'");
$db->query("DELETE FROM franchise_applications WHERE operator_name = 'TEST_E2E_OP'");
$db->query("DELETE FROM operators WHERE full_name = 'TEST_E2E_OP'");
$db->query("DELETE FROM coops WHERE coop_name = 'TEST_E2E_COOP'");
$db->query("DELETE FROM routes WHERE route_id = 'TEST-R-E2E'");
$db->query("DELETE FROM vehicles WHERE plate_number = 'TEST-E2E-002'");
$db->query("DELETE FROM terminal_assignments WHERE plate_number = 'TEST-E2E-002'");
$db->query("DELETE FROM franchise_applications WHERE franchise_ref_number = 'FR-TEST-1'");
// Cleanup Module 4 inspection artifacts
$db->query("DELETE FROM inspection_checklist_items");
$db->query("DELETE FROM inspection_results");
$db->query("DELETE FROM inspection_certificates");
$db->query("DELETE FROM inspection_schedules WHERE plate_number = 'TEST-E2E-001'");

// 2. Cooperative Registration
echo "\n--- Cooperative Registration ---\n";
$sql = "INSERT INTO coops (coop_name, address, chairperson_name, lgu_approval_number, accreditation_date) 
        VALUES ('TEST_E2E_COOP', '123 Test St', 'Test Chair', 'LGU-123', CURDATE())";
if (!$db->query($sql)) {
    echo "Error: " . $db->error . "\n";
    exit(1);
}
$coop_id = $db->insert_id;
assert_true($coop_id > 0, "Created Cooperative (ID: $coop_id)");

// 3. Operator Registration
echo "\n--- Operator Registration ---\n";
// Note: operators table might not have coop_id based on previous file checks, let's check structure or just insert basic
// Assuming operators table exists from core or Module 1 setup.
// Let's verify if 'coop_id' column exists in operators. 
// If not, we just link via name or separate table if needed.
// Based on submodule2, it links via name or ID?
// Let's just insert basic operator for now.
$sql = "INSERT INTO operators (full_name, address, contact_number, email, status) 
        VALUES ('TEST_E2E_OP', '456 Op St', '09123456789', 'test@op.com', 'Active')";
if (!$db->query($sql)) {
    echo "Error: " . $db->error . "\n";
    exit(1);
}
$op_id = $db->insert_id;
assert_true($op_id > 0, "Created Operator (ID: $op_id)");

// 4. Franchise Application
echo "\n--- Franchise Application ---\n";
$sql = "INSERT INTO franchise_applications (operator_id, operator_name, application_type, status, submission_date, notes) 
        VALUES ($op_id, 'TEST_E2E_OP', 'New', 'Pending', CURDATE(), 'TEST_E2E Application')";
if (!$db->query($sql)) {
    echo "Error: " . $db->error . "\n";
    exit(1);
}
$app_id = $db->insert_id;
assert_true($app_id > 0, "Created Franchise Application (ID: $app_id)");

// 5. Route Creation
echo "\n--- Route Creation ---\n";
$sql = "INSERT INTO routes (route_id, route_name, origin, destination, distance_km, fare, status) 
        VALUES ('TEST-R-E2E', 'Test Route E2E', 'Origin A', 'Dest B', 10.5, 15.00, 'Active')";
if (!$db->query($sql)) {
    echo "Error: " . $db->error . "\n";
    exit(1);
}
assert_true($db->affected_rows > 0, "Created Route (ID: TEST-R-E2E)");

// 6. Vehicle Registration via API (RBAC allowed)
echo "\n--- Vehicle Registration (API, RBAC) ---\n";
$_SERVER['HTTP_X_USER_ROLE'] = 'Admin';
$_POST = [
  'plate_number' => 'TEST-E2E-001',
  'vehicle_type' => 'Jeepney',
  'operator_name' => 'TEST_E2E_OP',
  'franchise_id' => '',
  'route_id' => '',
  'status' => 'Active',
  'inspection_status' => 'Pending',
  'make' => 'Toyota',
  'model' => 'Hilux',
  'year_model' => '2023'
];
ob_start();
include __DIR__ . '/../../api/module1/create_vehicle.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $m1);
$resp = isset($m1[0]) ? json_decode($m1[0], true) : null;
assert_true($resp['ok'] === true, "API create_vehicle ok");

// 7. Route Assignment (should be blocked before inspection Passed)
echo "\n--- Route Assignment (Blocked before inspection) ---\n";
$_POST = [
  'plate_number' => 'TEST-E2E-001',
  'route_id' => 'TEST-R-E2E',
  'terminal_name' => 'Central Terminal',
  'status' => 'Authorized'
];
ob_start();
include __DIR__ . '/../../api/module1/assign_route.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $m2);
$respAssign = isset($m2[0]) ? json_decode($m2[0], true) : null;
assert_true(($respAssign['ok'] ?? false) === false && ($respAssign['error'] ?? '') === 'inspection_not_passed', "Assign blocked until inspection Passed");

// 8. Module 4 Flow: Officer, Schedule, Checklist, Certificate
echo "\n--- Module 4 Inspection Flow ---\n";
$db->query("INSERT INTO officers (full_name, active_status) VALUES ('Inspector E2E', 1)");
$officer_id = $db->insert_id;
assert_true($officer_id > 0, "Created Officer (ID: $officer_id)");
$future = date('Y-m-d H:i:s', time() + 600);
$_POST = [
  'plate_number' => 'TEST-E2E-001',
  'scheduled_at' => $future,
  'location' => 'City Yard',
  'inspector_id' => $officer_id
];
ob_start();
include __DIR__ . '/../../api/module4/schedule_inspection.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mSI);
$respSI = isset($mSI[0]) ? json_decode($mSI[0], true) : null;
assert_true(($respSI['ok'] ?? false) === true, "Scheduled Inspection");
$schedule_id = (int)($respSI['schedule_id'] ?? 0);
assert_true($schedule_id > 0, "Schedule ID captured");
$_POST = [
  'schedule_id' => $schedule_id,
  'remarks' => 'All good',
  'items' => [
    'LIGHTS' => 'PASS',
    'BRAKES' => 'PASS',
    'EMISSION' => 'PASS'
  ]
];
ob_start();
include __DIR__ . '/../../api/module4/submit_checklist.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mSC);
$respSC = isset($mSC[0]) ? json_decode($mSC[0], true) : null;
assert_true(($respSC['ok'] ?? false) === true && ($respSC['overall_status'] ?? '') === 'Passed', "Checklist submitted Passed");
$_POST = [
  'schedule_id' => $schedule_id,
  'approved_by' => $officer_id
];
ob_start();
include __DIR__ . '/../../api/module4/generate_certificate.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mGC);
$respGC = isset($mGC[0]) ? json_decode($mGC[0], true) : null;
assert_true(($respGC['ok'] ?? false) === true && isset($respGC['certificate_number']), "Certificate generated");
$cert_no = $respGC['certificate_number'];
$vrow = $db->query("SELECT inspection_status, inspection_cert_ref FROM vehicles WHERE plate_number='TEST-E2E-001'")->fetch_assoc();
assert_true(strtoupper($vrow['inspection_status'] ?? '') === 'PASSED', "Vehicle inspection_status updated to Passed");
assert_true(($vrow['inspection_cert_ref'] ?? '') === $cert_no, "Vehicle cert_ref updated");

// 9. Route Assignment (After inspection Passed)
echo "\n--- Route Assignment (After inspection Passed) ---\n";
$_POST = [
  'plate_number' => 'TEST-E2E-001',
  'route_id' => 'TEST-R-E2E',
  'terminal_name' => 'Central Terminal',
  'status' => 'Authorized'
];
ob_start();
include __DIR__ . '/../../api/module1/assign_route.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $m2a);
$respAssign2 = isset($m2a[0]) ? json_decode($m2a[0], true) : null;
assert_true(($respAssign2['ok'] ?? false) === true, "API assign_route ok after inspection");

// 8. Validation Query
echo "\n--- Validation ---\n";
$res = $db->query("SELECT v.plate_number, v.operator_name, r.route_name, ta.terminal_name 
                   FROM vehicles v 
                   JOIN routes r ON v.route_id = r.route_id 
                   JOIN terminal_assignments ta ON v.plate_number = ta.plate_number 
                   WHERE v.plate_number = 'TEST-E2E-001'");
$row = $res->fetch_assoc();
assert_true($row['plate_number'] === 'TEST-E2E-001', "Vehicle Plate Match");
assert_true($row['operator_name'] === 'TEST_E2E_OP', "Operator Match");
assert_true($row['route_name'] === 'Test Route E2E', "Route Match");
assert_true($row['terminal_name'] === 'Central Terminal', "Terminal Match");

// 9. Route Capacity Enforcement
echo "\n--- Route Capacity Enforcement ---\n";
$db->query("UPDATE routes SET max_vehicle_limit = 1 WHERE route_id = 'TEST-R-E2E'");
$_SERVER['HTTP_X_USER_ROLE'] = 'Admin';
$_POST = [
  'plate_number' => 'TEST-E2E-002',
  'vehicle_type' => 'Jeepney',
  'operator_name' => 'TEST_E2E_OP'
];
ob_start();
include __DIR__ . '/../../api/module1/create_vehicle.php';
ob_end_clean();
// Ensure inspection_status Passed for capacity test
$db->query("UPDATE vehicles SET inspection_status='Passed' WHERE plate_number='TEST-E2E-002'");
$_POST = [
  'plate_number' => 'TEST-E2E-002',
  'route_id' => 'TEST-R-E2E',
  'terminal_name' => 'Central Terminal',
  'status' => 'Authorized'
];
ob_start();
include __DIR__ . '/../../api/module1/assign_route.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $m4);
$respCap = isset($m4[0]) ? json_decode($m4[0], true) : null;
assert_true(($respCap['ok'] ?? false) === false && ($respCap['error'] ?? '') === 'route_over_capacity', "Capacity limit enforced");

// 12. Franchise Validity Enforcement
echo "\n--- Franchise Validity Enforcement ---\n";
// Reset route capacity to avoid capacity interference
$db->query("UPDATE routes SET max_vehicle_limit = 10 WHERE route_id = 'TEST-R-E2E'");
$db->query("INSERT INTO franchise_applications (franchise_ref_number, operator_id, operator_name, application_type, status, submission_date) VALUES ('FR-TEST-1', $op_id, 'TEST_E2E_OP', 'Renewal', 'Rejected', CURDATE())");
$_POST = [
  'plate_number' => 'TEST-E2E-002',
  'vehicle_type' => 'Jeepney',
  'operator_name' => 'TEST_E2E_OP',
  'franchise_id' => 'FR-TEST-1',
  'status' => 'Active'
];
ob_start();
include __DIR__ . '/../../api/module1/create_vehicle.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $m5);
$respV = isset($m5[0]) ? json_decode($m5[0], true) : null;
assert_true(($respV['status'] ?? '') === 'Suspended', "Vehicle suspended when franchise invalid");
// Ensure inspection_status Passed to isolate franchise blocking
$db->query("UPDATE vehicles SET inspection_status='Passed' WHERE plate_number='TEST-E2E-002'");
$_POST = [
  'plate_number' => 'TEST-E2E-002',
  'route_id' => 'TEST-R-E2E',
  'terminal_name' => 'Central Terminal',
  'status' => 'Authorized'
];
ob_start();
include __DIR__ . '/../../api/module1/assign_route.php';
$out = ob_get_clean();
echo $out . "\n";
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $m6);
$respF = isset($m6[0]) ? json_decode($m6[0], true) : null;
assert_true(($respF['ok'] ?? false) === false && ($respF['error'] ?? '') === 'franchise_invalid', "Assign blocked when franchise invalid");

// 13. Additional Module 1 API Coverage
echo "\n--- Module 1 API Coverage ---\n";
$_SERVER['HTTP_X_USER_ROLE'] = 'Admin';
// save_coop
$_POST = [
  'coop_name' => 'TEST_API_COOP',
  'address' => 'API Addr',
  'chairperson_name' => 'API Chair',
  'lgu_approval_number' => 'LGU-API'
];
ob_start();
include __DIR__ . '/../../api/module1/save_coop.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mSC2);
$respSC2 = isset($mSC2[0]) ? json_decode($mSC2[0], true) : null;
assert_true(($respSC2['ok'] ?? false) === true, "API save_coop ok");
$rowCoop = $db->query("SELECT coop_name FROM coops WHERE coop_name='TEST_API_COOP'")->fetch_assoc();
assert_true(($rowCoop['coop_name'] ?? '') === 'TEST_API_COOP', "Coop saved");
// save_operator
$_POST = [
  'full_name' => 'TEST_API_OP',
  'contact_info' => '09999999999',
  'coop_name' => 'TEST_API_COOP'
];
ob_start();
include __DIR__ . '/../../api/module1/save_operator.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mSO);
$respSO = isset($mSO[0]) ? json_decode($mSO[0], true) : null;
assert_true(($respSO['ok'] ?? false) === true, "API save_operator ok");
$rowOp = $db->query("SELECT full_name FROM operators WHERE full_name='TEST_API_OP'")->fetch_assoc();
assert_true(($rowOp['full_name'] ?? '') === 'TEST_API_OP', "Operator saved");
// save_route
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
  'route_id' => 'API-ROUTE-1',
  'route_name' => 'API Route One',
  'origin' => 'Origin Z',
  'destination' => 'Dest Z',
  'distance_km' => '5.5',
  'fare' => '10.00',
  'status' => 'Active'
];
ob_start();
include __DIR__ . '/../../api/module1/save_route.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mSR);
$respSR = isset($mSR[0]) ? json_decode($mSR[0], true) : null;
assert_true(($respSR['ok'] ?? false) === true, "API save_route ok");
$rowRt = $db->query("SELECT route_id FROM routes WHERE route_id='API-ROUTE-1'")->fetch_assoc();
assert_true(($rowRt['route_id'] ?? '') === 'API-ROUTE-1', "Route saved");
// save_franchise
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
  'operator_name' => 'TEST_API_OP',
  'application_type' => 'New',
  'status' => 'Pending',
  'notes' => 'API Test'
];
ob_start();
include __DIR__ . '/../../api/module1/save_franchise.php';
$out = ob_get_clean();
echo $out . "\n";
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mSF);
$respSF = isset($mSF[0]) ? json_decode($mSF[0], true) : null;
assert_true(($respSF['ok'] ?? false) === true, "API save_franchise ok");
// link_vehicle_operator
$_POST = [
  'plate_number' => 'TEST-E2E-001',
  'operator_name' => 'TEST_API_OP',
  'coop_name' => 'TEST_API_COOP'
];
ob_start();
include __DIR__ . '/../../api/module1/link_vehicle_operator.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mLVO);
$respLVO = isset($mLVO[0]) ? json_decode($mLVO[0], true) : null;
assert_true(($respLVO['ok'] ?? false) === true, "API link_vehicle_operator ok");
$rowV1 = $db->query("SELECT operator_name, coop_name FROM vehicles WHERE plate_number='TEST-E2E-001'")->fetch_assoc();
assert_true(($rowV1['operator_name'] ?? '') === 'TEST_API_OP', "Vehicle linked to operator");
// update_vehicle
$_POST = [
  'plate_number' => 'TEST-E2E-001',
  'status' => 'Suspended',
  'vehicle_type' => 'Taxi'
];
ob_start();
include __DIR__ . '/../../api/module1/update_vehicle.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mUV);
$respUV = isset($mUV[0]) ? json_decode($mUV[0], true) : null;
assert_true(($respUV['ok'] ?? false) === true, "API update_vehicle ok");
$rowV2 = $db->query("SELECT status, vehicle_type FROM vehicles WHERE plate_number='TEST-E2E-001'")->fetch_assoc();
assert_true(($rowV2['status'] ?? '') === 'Suspended', "Vehicle status updated");
assert_true(($rowV2['vehicle_type'] ?? '') === 'Taxi', "Vehicle type updated");
// list_vehicles
$_SERVER['HTTP_X_USER_ROLE'] = 'Admin';
$_GET = ['q' => 'TEST-E2E'];
ob_start();
include __DIR__ . '/../../api/module1/list_vehicles.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mLV);
$respLV = isset($mLV[0]) ? json_decode($mLV[0], true) : null;
assert_true(($respLV['ok'] ?? false) === true && is_array($respLV['data'] ?? null), "API list_vehicles ok");
// list_assignments
$_GET = ['route_id' => 'TEST-R-E2E'];
ob_start();
include __DIR__ . '/../../api/module1/list_assignments.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mLA);
$respLA = isset($mLA[0]) ? json_decode($mLA[0], true) : null;
assert_true(($respLA['ok'] ?? false) === true && is_array($respLA['data'] ?? null), "API list_assignments ok");
// upload_docs
$tmp1 = tempnam(sys_get_temp_dir(), 'e2e');
file_put_contents($tmp1, "%PDF-1.4 test");
$tmp2 = tempnam(sys_get_temp_dir(), 'e2e');
file_put_contents($tmp2, "\x89PNG\r\n\x1a\n");
$_FILES = [
  'or' => ['name'=>'or.pdf','type'=>'application/pdf','tmp_name'=>$tmp1,'error'=>UPLOAD_ERR_OK,'size'=>filesize($tmp1)],
  'cr' => ['name'=>'cr.png','type'=>'image/png','tmp_name'=>$tmp2,'error'=>UPLOAD_ERR_OK,'size'=>filesize($tmp2)]
];
$_POST = ['plate_number' => 'TEST-E2E-001'];
$_SERVER['HTTP_X_USER_ROLE'] = 'Admin';
ob_start();
include __DIR__ . '/../../api/module1/upload_docs.php';
$out = ob_get_clean();
preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $mUD);
$respUD = isset($mUD[0]) ? json_decode($mUD[0], true) : null;
assert_true(($respUD['ok'] ?? false) === true && count($respUD['files'] ?? []) >= 1, "API upload_docs ok");
// export CSVs (basic invocation)
$_SERVER['HTTP_X_USER_ROLE'] = 'Admin';
ob_start();
include __DIR__ . '/../../api/module1/export_vehicles_csv.php';
$csvV = ob_get_clean();
assert_true(strpos($csvV, 'plate_number,vehicle_type') !== false, "API export_vehicles_csv ok");
ob_start();
include __DIR__ . '/../../api/module1/export_assignments_csv.php';
$csvA = ob_get_clean();
assert_true(strpos($csvA, 'plate_number,route_id') !== false, "API export_assignments_csv ok");
// HTML endpoints
ob_start();
$_GET = ['plate' => 'TEST-E2E-001'];
include __DIR__ . '/../../api/module1/view_html.php';
$htmlV = ob_get_clean();
assert_true(strpos($htmlV, 'Vehicle Details') !== false || strpos($htmlV, 'PUV') !== false, "API view_html ok");
$_GET = ['name' => 'TEST_API_OP'];
ob_start();
include __DIR__ . '/../../api/module1/operator_html.php';
$htmlO = ob_get_clean();
assert_true(strpos($htmlO, 'Operator') !== false, "API operator_html ok");
$_GET = ['name' => 'TEST_API_COOP'];
ob_start();
include __DIR__ . '/../../api/module1/coop_html.php';
$htmlC = ob_get_clean();
assert_true(strpos($htmlC, 'Cooperative') !== false, "API coop_html ok");

echo "\n=== All Module 1 Tests Passed (with Module 4 gating) ===\n";

// 11. RBAC Denial (will exit)
echo "\n--- RBAC Denial ---\n";
$_SERVER['HTTP_X_USER_ROLE'] = 'Inspector';
$_POST = [
  'plate_number' => 'TEST-E2E-002',
  'vehicle_type' => 'Jeepney',
  'operator_name' => 'TEST_E2E_OP'
];
try {
  ob_start();
  include __DIR__ . '/../../api/module1/create_vehicle.php';
  $out = ob_get_clean();
  preg_match('/\\{[\\s\\S]*\\}\\s*$/', $out, $m7);
  $rf = isset($m7[0]) ? json_decode($m7[0], true) : null;
  assert_true(($rf['ok'] ?? false) === false && ($rf['error'] ?? '') === 'forbidden', "Inspector blocked by RBAC");
} catch (Exception $e) {
  assert_true($e->getMessage() === 'forbidden', "Inspector blocked by RBAC (exception)");
}
?>
