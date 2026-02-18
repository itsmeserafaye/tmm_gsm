<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/util.php';
require_once __DIR__ . '/../../includes/vehicle_types.php';
require_once __DIR__ . '/../../includes/franchise_gate.php';
$db = db();

header('Content-Type: application/json');
require_any_permission(['module2.apply','module2.franchises.manage']);

function tmm_normalize_vehicle_category($v) {
    $s = trim((string)$v);
    if ($s === '') return '';
    if (in_array($s, ['Tricycle','Jeepney','UV','Bus'], true)) return $s;
    $l = strtolower($s);
    if (str_contains($l, 'tricycle') || str_contains($l, 'e-trike') || str_contains($l, 'pedicab')) return 'Tricycle';
    if (str_contains($l, 'jeepney')) return 'Jeepney';
    if (str_contains($l, 'bus') || str_contains($l, 'mini-bus')) return 'Bus';
    if (str_contains($l, 'uv') || str_contains($l, 'van') || str_contains($l, 'shuttle')) return 'UV';
    return '';
}

function tmm_is_tricycle_like($v) {
    return tmm_normalize_vehicle_category($v) === 'Tricycle';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$operator_id = (int)($_POST['operator_id'] ?? 0);
$route_id = (int)($_POST['route_id'] ?? 0);
$service_area_id = (int)($_POST['service_area_id'] ?? 0);
$vehicle_type = trim((string)($_POST['vehicle_type'] ?? ''));
$vehicle_count = (int)($_POST['vehicle_count'] ?? 0);
$representative_name = trim((string)($_POST['representative_name'] ?? ''));
$assisted = (int)($_POST['assisted'] ?? 0) === 1;

if ($operator_id <= 0 || $route_id <= 0 || $vehicle_count <= 0) {
    if ($operator_id <= 0 || $vehicle_count <= 0) {
        echo json_encode(['ok' => false, 'error' => 'missing_required_fields']);
        exit;
    }
}

try {
    $stmtO = $db->prepare("SELECT id, operator_type, status, verification_status, workflow_status FROM operators WHERE id=? LIMIT 1");
    if (!$stmtO) throw new Exception('db_prepare_failed');
    $stmtO->bind_param('i', $operator_id);
    $stmtO->execute();
    $op = $stmtO->get_result()->fetch_assoc();
    $stmtO->close();
    if (!$op) {
        echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
        exit;
    }
    $opStatus = (string)($op['status'] ?? '');
    $wfStatus = (string)($op['workflow_status'] ?? '');
    $vsStatus = (string)($op['verification_status'] ?? '');
    if ($opStatus === 'Inactive' || $wfStatus === 'Inactive' || $vsStatus === 'Inactive') {
        echo json_encode(['ok' => false, 'error' => 'operator_inactive']);
        exit;
    }

    $allowedVehicleTypes = vehicle_types();
    if (!in_array('UV', $allowedVehicleTypes, true)) $allowedVehicleTypes[] = 'UV';
    if ($vehicle_type === '' || strlen($vehicle_type) > 60) {
        echo json_encode(['ok' => false, 'error' => 'invalid_vehicle_type']);
        exit;
    }
    if (!in_array($vehicle_type, $allowedVehicleTypes, true) && tmm_normalize_vehicle_category($vehicle_type) === '') {
        echo json_encode(['ok' => false, 'error' => 'invalid_vehicle_type']);
        exit;
    }

    $isTricycle = tmm_is_tricycle_like($vehicle_type);
    if (!$isTricycle) {
        echo json_encode(['ok' => false, 'error' => 'tricycle_only']);
        exit;
    }
    $vehicle_type = 'Tricycle';
    $slots = tmm_operator_required_doc_slots((string)($op['operator_type'] ?? ''));
    $docCheck = tmm_operator_docs_verified($db, $operator_id, $slots);
    if (!$docCheck['ok']) {
        $missing = [];
        if (($docCheck['error'] ?? '') === 'operator_docs_missing') {
            foreach ($slots as $s) {
                if (!empty($s['optional'])) continue;
                $lbl = trim((string)($s['label'] ?? ''));
                if ($lbl !== '') $missing[] = $lbl;
            }
        } else {
            $missing = (array)($docCheck['missing'] ?? []);
        }
        $missing = array_values(array_filter(array_map(fn($x) => trim((string)$x), $missing), fn($x) => $x !== ''));
        echo json_encode(['ok' => false, 'error' => 'operator_docs_not_verified', 'missing' => $missing]);
        exit;
    }
    if ($isTricycle) {
        if ($service_area_id <= 0) { echo json_encode(['ok' => false, 'error' => 'missing_required_fields']); exit; }
        $stmtA = $db->prepare("SELECT id, status, COALESCE(authorized_units,0) AS authorized_units FROM tricycle_service_areas WHERE id=? LIMIT 1");
        if (!$stmtA) throw new Exception('db_prepare_failed');
        $stmtA->bind_param('i', $service_area_id);
        $stmtA->execute();
        $area = $stmtA->get_result()->fetch_assoc();
        $stmtA->close();
        if (!$area) {
            echo json_encode(['ok' => false, 'error' => 'Service area / TODA zone not found. Please select a valid area.']);
            exit;
        }
        if (($area['status'] ?? '') !== 'Active') {
            echo json_encode(['ok' => false, 'error' => 'Selected service area / TODA zone is not active. Please choose another area.']);
            exit;
        }

        $stmtU = $db->prepare("SELECT COALESCE(SUM(vehicle_count),0) AS used_units
                               FROM franchise_applications
                               WHERE service_area_id=? AND COALESCE(vehicle_type,'')='Tricycle'
                                 AND status IN ('Pending Review','Approved','Active','Endorsed','LGU-Endorsed','LTFRB-Approved','PA Issued','CPC Issued')");
        if ($stmtU) {
            $stmtU->bind_param('i', $service_area_id);
            $stmtU->execute();
            $u = $stmtU->get_result()->fetch_assoc();
            $stmtU->close();
            $limit = (int)($area['authorized_units'] ?? 0);
            $used = (int)($u['used_units'] ?? 0);
            $remaining = max(0, $limit - $used);
            if ($limit > 0 && $vehicle_count > $remaining) {
                echo json_encode([
                    'ok' => false,
                    'error' => 'Requested number of units exceeds available slots in this service area.',
                    'remaining_slots' => $remaining,
                ]);
                exit;
            }
        }
        $route_id = 0;
    } else {
        if ($route_id <= 0) { echo json_encode(['ok' => false, 'error' => 'missing_required_fields']); exit; }
        $stmtR = $db->prepare("SELECT id, route_id, status FROM routes WHERE id=? LIMIT 1");
        if (!$stmtR) throw new Exception('db_prepare_failed');
        $stmtR->bind_param('i', $route_id);
        $stmtR->execute();
        $route = $stmtR->get_result()->fetch_assoc();
        $stmtR->close();
        if (!$route) { echo json_encode(['ok' => false, 'error' => 'route_not_found']); exit; }
        if (($route['status'] ?? '') !== 'Active') { echo json_encode(['ok' => false, 'error' => 'route_inactive']); exit; }

        $useAlloc = false;
        $tAlloc = $db->query("SHOW TABLES LIKE 'route_vehicle_types'");
        if ($tAlloc && $tAlloc->num_rows > 0) {
            $cAlloc = $db->query("SELECT COUNT(*) AS c FROM route_vehicle_types");
            if ($cAlloc && (int)($cAlloc->fetch_assoc()['c'] ?? 0) > 0) $useAlloc = true;
        }
        if ($useAlloc) {
            $allocType = $vehicle_type;
            $allocLimit = null;

            $stmtA = $db->prepare("SELECT vehicle_type, COALESCE(authorized_units,0) AS authorized_units FROM route_vehicle_types WHERE route_id=? AND vehicle_type=? AND status='Active' LIMIT 1");
            if ($stmtA) {
                $stmtA->bind_param('is', $route_id, $allocType);
                $stmtA->execute();
                $alloc = $stmtA->get_result()->fetch_assoc();
                $stmtA->close();
                if ($alloc) {
                    $allocType = (string)($alloc['vehicle_type'] ?? $allocType);
                    $allocLimit = (int)($alloc['authorized_units'] ?? 0);
                }
            }

            if ($allocLimit === null) {
                $cat = tmm_normalize_vehicle_category($vehicle_type);
                if ($cat === '') { echo json_encode(['ok' => false, 'error' => 'allocation_not_found']); exit; }

                $stmtAll = $db->prepare("SELECT vehicle_type, COALESCE(authorized_units,0) AS authorized_units FROM route_vehicle_types WHERE route_id=? AND status='Active' AND vehicle_type<>'Tricycle' ORDER BY id ASC");
                if ($stmtAll) {
                    $stmtAll->bind_param('i', $route_id);
                    $stmtAll->execute();
                    $resAll = $stmtAll->get_result();
                    $candidates = [];
                    while ($rr = $resAll->fetch_assoc()) {
                        $vt = (string)($rr['vehicle_type'] ?? '');
                        if ($vt === '') continue;
                        if (tmm_normalize_vehicle_category($vt) !== $cat) continue;
                        $candidates[] = $rr;
                    }
                    $stmtAll->close();
                    if (!$candidates) { echo json_encode(['ok' => false, 'error' => 'allocation_not_found']); exit; }
                    $picked = $candidates[0];
                    foreach ($candidates as $cand) {
                        if ((string)($cand['vehicle_type'] ?? '') === $cat) { $picked = $cand; break; }
                    }
                    $allocType = (string)($picked['vehicle_type'] ?? $allocType);
                    $allocLimit = (int)($picked['authorized_units'] ?? 0);
                } else {
                    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
                    exit;
                }
            }

            $vehicle_type = $allocType;

            $stmtU = $db->prepare("SELECT COALESCE(SUM(vehicle_count),0) AS used_units
                                   FROM franchise_applications
                                   WHERE route_id=? AND vehicle_type=?
                                     AND status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued')");
            if ($stmtU) {
                $stmtU->bind_param('is', $route_id, $vehicle_type);
                $stmtU->execute();
                $u = $stmtU->get_result()->fetch_assoc();
                $stmtU->close();
                $limit = (int)($allocLimit ?? 0);
                $used = (int)($u['used_units'] ?? 0);
                $remaining = max(0, $limit - $used);
                if ($limit > 0 && $vehicle_count > $remaining) { echo json_encode(['ok' => false, 'error' => 'capacity_exceeded']); exit; }
            }
        }
        $service_area_id = 0;
    }

    $franchise_ref = 'APP-' . date('Ymd') . '-' . substr(uniqid(), -6);
    $route_ids_val = $isTricycle ? ('AREA:' . (string)$service_area_id) : (string)$route_id;
    $submittedByUserId = (int)($_SESSION['user_id'] ?? 0);
    $submittedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
    if ($submittedByName === '') $submittedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
    if ($submittedByName === '') $submittedByName = 'Admin';
    $submittedChannel = $assisted ? 'admin_assisted' : 'admin';

    $db->begin_transaction();

    $routeIdBind = $route_id > 0 ? $route_id : null;
    $areaIdBind = $service_area_id > 0 ? $service_area_id : null;
    $stmt = $db->prepare("INSERT INTO franchise_applications
                          (franchise_ref_number, operator_id, route_id, service_area_id, vehicle_type, route_ids, vehicle_count, representative_name, status, submitted_at, submitted_by_user_id, submitted_by_name, submitted_channel)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending Review', NOW(), ?, ?, ?)");
    if (!$stmt) throw new Exception('db_prepare_failed');
    $stmt->bind_param('siiissisiss', $franchise_ref, $operator_id, $routeIdBind, $areaIdBind, $vehicle_type, $route_ids_val, $vehicle_count, $representative_name, $submittedByUserId, $submittedByName, $submittedChannel);
    $execOk = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    $db->rollback();
    if ($e->getCode() === 1062) {
        echo json_encode(['ok' => false, 'error' => 'duplicate_reference']);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
}

if ($execOk) {
    try {
        $app_id = $db->insert_id;

        $db->commit();
        tmm_audit_event($db, 'FRANCHISE_APPLICATION_SUBMITTED', 'FranchiseApplication', (string)$app_id, ['channel' => $submittedChannel, 'operator_id' => $operator_id, 'route_id' => $route_id, 'vehicle_count' => $vehicle_count]);
        echo json_encode([
            'ok' => true,
            'application_id' => $app_id,
            'franchise_ref_number' => $franchise_ref,
            'message' => "Application submitted. ID: APP-$app_id"
        ]);
    } catch (Throwable $e) {
        $db->rollback();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage() ?: 'submit_failed']);
    }
} else {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
?>
