<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();

header('Content-Type: application/json');
require_permission('module2.franchises.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$operator_id = (int)($_POST['operator_id'] ?? 0);
$route_id = (int)($_POST['route_id'] ?? 0);
$vehicle_count = (int)($_POST['vehicle_count'] ?? 0);
$representative_name = trim((string)($_POST['representative_name'] ?? ''));

if ($operator_id <= 0 || $route_id <= 0 || $vehicle_count <= 0) {
    echo json_encode(['ok' => false, 'error' => 'missing_required_fields']);
    exit;
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

    $opType = (string)(($op['operator_type'] ?? '') ?: 'Individual');
    $slots = [];
    if ($opType === 'Cooperative') {
        $slots = [
            ['doc_type' => 'CDA', 'keywords' => ['registration'], 'label' => 'CDA Registration Certificate'],
            ['doc_type' => 'CDA', 'keywords' => ['good standing', 'good_standing', 'standing'], 'label' => 'CDA Certificate of Good Standing'],
            ['doc_type' => 'Others', 'keywords' => ['board resolution', 'resolution'], 'label' => 'Board Resolution'],
        ];
    } elseif ($opType === 'Corporation') {
        $slots = [
            ['doc_type' => 'SEC', 'keywords' => ['certificate', 'registration'], 'label' => 'SEC Certificate of Registration'],
            ['doc_type' => 'SEC', 'keywords' => ['articles', 'by-laws', 'bylaws', 'incorporation'], 'label' => 'Articles of Incorporation / By-laws'],
            ['doc_type' => 'Others', 'keywords' => ['board resolution', 'resolution'], 'label' => 'Board Resolution'],
        ];
    } else {
        $slots = [
            ['doc_type' => 'GovID', 'keywords' => ['gov', 'id', 'driver', 'license', 'umid', 'philsys'], 'label' => 'Valid Government ID'],
        ];
    }

    $stmtD = $db->prepare("SELECT doc_id, doc_type, doc_status, is_verified, remarks FROM operator_documents WHERE operator_id=? ORDER BY uploaded_at DESC, doc_id DESC");
    if (!$stmtD) throw new Exception('db_prepare_failed');
    $stmtD->bind_param('i', $operator_id);
    $stmtD->execute();
    $resD = $stmtD->get_result();
    $docs = [];
    while ($r = $resD->fetch_assoc()) $docs[] = $r;
    $stmtD->close();
    if (!$docs) {
        echo json_encode(['ok' => false, 'error' => 'operator_docs_missing']);
        exit;
    }

    $used = [];
    $slotOk = array_fill(0, count($slots), false);
    $matchRemarks = function (string $remarks, array $keywords): bool {
        $t = strtolower($remarks);
        foreach ($keywords as $kw) {
            $k = strtolower((string)$kw);
            if ($k !== '' && strpos($t, $k) !== false) return true;
        }
        return false;
    };
    for ($i = 0; $i < count($slots); $i++) {
        $s = $slots[$i];
        foreach ($docs as $drow) {
            $did = (int)($drow['doc_id'] ?? 0);
            if ($did <= 0 || isset($used[$did])) continue;
            if ((string)($drow['doc_type'] ?? '') !== (string)$s['doc_type']) continue;
            $rem = (string)($drow['remarks'] ?? '');
            if ($rem !== '' && $matchRemarks($rem, (array)($s['keywords'] ?? []))) {
                $used[$did] = true;
                $slotOk[$i] = true;
                break;
            }
        }
    }
    for ($i = 0; $i < count($slots); $i++) {
        if ($slotOk[$i]) continue;
        $s = $slots[$i];
        foreach ($docs as $drow) {
            $did = (int)($drow['doc_id'] ?? 0);
            if ($did <= 0 || isset($used[$did])) continue;
            if ((string)($drow['doc_type'] ?? '') !== (string)$s['doc_type']) continue;
            $used[$did] = true;
            $slotOk[$i] = true;
            break;
        }
    }
    $missing = [];
    for ($i = 0; $i < count($slots); $i++) {
        if (!$slotOk[$i]) $missing[] = (string)($slots[$i]['label'] ?? '');
    }
    $missing = array_values(array_filter($missing, fn($x) => trim((string)$x) !== ''));
    if ($missing) {
        echo json_encode(['ok' => false, 'error' => 'operator_docs_incomplete', 'missing' => $missing]);
        exit;
    }

    $stmtR = $db->prepare("SELECT id, route_id, status FROM routes WHERE id=? LIMIT 1");
    if (!$stmtR) throw new Exception('db_prepare_failed');
    $stmtR->bind_param('i', $route_id);
    $stmtR->execute();
    $route = $stmtR->get_result()->fetch_assoc();
    $stmtR->close();
    if (!$route) {
        echo json_encode(['ok' => false, 'error' => 'route_not_found']);
        exit;
    }
    if (($route['status'] ?? '') !== 'Active') {
        echo json_encode(['ok' => false, 'error' => 'route_inactive']);
        exit;
    }

    $franchise_ref = 'APP-' . date('Ymd') . '-' . substr(uniqid(), -6);
    $route_ids_val = (string)$route_id;

    $stmt = $db->prepare("INSERT INTO franchise_applications (franchise_ref_number, operator_id, route_id, route_ids, vehicle_count, representative_name, status, submitted_at)
                          VALUES (?, ?, ?, ?, ?, ?, 'Submitted', NOW())");
    if (!$stmt) throw new Exception('db_prepare_failed');
    $stmt->bind_param('siisis', $franchise_ref, $operator_id, $route_id, $route_ids_val, $vehicle_count, $representative_name);
    $execOk = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        echo json_encode(['ok' => false, 'error' => 'duplicate_reference']);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
}

if ($execOk) {
    $app_id = $db->insert_id;
    echo json_encode([
        'ok' => true,
        'application_id' => $app_id,
        'franchise_ref_number' => $franchise_ref,
        'message' => "Application submitted. ID: APP-$app_id"
    ]);
} else {
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
?>
