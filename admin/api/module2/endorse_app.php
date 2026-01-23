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

$app_id = (int)($_POST['application_id'] ?? 0);
$notes = trim((string)($_POST['notes'] ?? ''));

if ($app_id === 0) {
    echo json_encode(['ok' => false, 'error' => 'missing_application_id']);
    exit;
}

$db->begin_transaction();
try {
    $stmtA = $db->prepare("SELECT application_id, franchise_ref_number, operator_id, route_id, vehicle_count, status FROM franchise_applications WHERE application_id=? FOR UPDATE");
    if (!$stmtA) {
        throw new Exception('db_prepare_failed');
    }
    $stmtA->bind_param('i', $app_id);
    $stmtA->execute();
    $app = $stmtA->get_result()->fetch_assoc();
    $stmtA->close();

    if (!$app) {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'application_not_found']);
        exit;
    }

    $curStatus = (string)($app['status'] ?? '');
    if ($curStatus === 'Endorsed' || $curStatus === 'LGU-Endorsed') {
        $db->commit();
        echo json_encode(['ok' => true, 'message' => 'Application already endorsed']);
        exit;
    }
    if ($curStatus !== 'Submitted') {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'invalid_status']);
        exit;
    }

    $opId = (int)($app['operator_id'] ?? 0);
    $stmtO = $db->prepare("SELECT status, operator_type, verification_status, workflow_status FROM operators WHERE id=? LIMIT 1");
    if (!$stmtO) throw new Exception('db_prepare_failed');
    $stmtO->bind_param('i', $opId);
    $stmtO->execute();
    $op = $stmtO->get_result()->fetch_assoc();
    $stmtO->close();
    if (!$op) {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
        exit;
    }
    $opStatus = (string)($op['status'] ?? '');
    $wfStatus = (string)($op['workflow_status'] ?? '');
    $vsStatus = (string)($op['verification_status'] ?? '');
    if ($opStatus === 'Inactive' || $wfStatus === 'Inactive' || $vsStatus === 'Inactive') {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'operator_inactive']);
        exit;
    }
    $okOperator = ($wfStatus === 'Active') || ($vsStatus === 'Verified');
    if (!$okOperator) {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'operator_invalid']);
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
    $stmtD->bind_param('i', $opId);
    $stmtD->execute();
    $resD = $stmtD->get_result();
    $docs = [];
    while ($r = $resD->fetch_assoc()) $docs[] = $r;
    $stmtD->close();
    if (!$docs) {
        $db->rollback();
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
    $isVerifiedDoc = function (array $drow): bool {
        $st = (string)($drow['doc_status'] ?? '');
        if ($st === 'Verified') return true;
        return ((int)($drow['is_verified'] ?? 0)) === 1;
    };
    for ($i = 0; $i < count($slots); $i++) {
        $s = $slots[$i];
        foreach ($docs as $drow) {
            $did = (int)($drow['doc_id'] ?? 0);
            if ($did <= 0 || isset($used[$did])) continue;
            if ((string)($drow['doc_type'] ?? '') !== (string)$s['doc_type']) continue;
            if (!$isVerifiedDoc($drow)) continue;
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
            if (!$isVerifiedDoc($drow)) continue;
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
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'operator_docs_not_verified', 'missing' => $missing]);
        exit;
    }

    $routeId = (int)($app['route_id'] ?? 0);
    if ($routeId > 0) {
        $stmtR = $db->prepare("SELECT authorized_units, status FROM routes WHERE id=? LIMIT 1");
        if (!$stmtR) throw new Exception('db_prepare_failed');
        $stmtR->bind_param('i', $routeId);
        $stmtR->execute();
        $route = $stmtR->get_result()->fetch_assoc();
        $stmtR->close();
        if (!$route) {
            $db->rollback();
            echo json_encode(['ok' => false, 'error' => 'route_not_found']);
            exit;
        }
        if (($route['status'] ?? '') !== 'Active') {
            $db->rollback();
            echo json_encode(['ok' => false, 'error' => 'route_inactive']);
            exit;
        }
        $cap = (int)($route['authorized_units'] ?? 0);
        if ($cap > 0) {
            $stmtC = $db->prepare("SELECT COALESCE(SUM(vehicle_count),0) AS c FROM franchise_applications WHERE route_id=? AND status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')");
            if (!$stmtC) throw new Exception('db_prepare_failed');
            $stmtC->bind_param('i', $routeId);
            $stmtC->execute();
            $cur = $stmtC->get_result()->fetch_assoc();
            $stmtC->close();
            $curCount = (int)($cur['c'] ?? 0);
            $want = (int)($app['vehicle_count'] ?? 0);
            if ($want <= 0) $want = 1;
            if ($curCount + $want > $cap) {
                $db->rollback();
                echo json_encode(['ok' => false, 'error' => 'route_over_capacity']);
                exit;
            }
        }
    }

    $permit_no = "PERMIT-" . date('Y') . "-" . str_pad((string)$app_id, 4, '0', STR_PAD_LEFT);
    $stmtIns = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number)
                             VALUES (?, CURDATE(), ?)
                             ON DUPLICATE KEY UPDATE issued_date=VALUES(issued_date), permit_number=VALUES(permit_number)");
    if (!$stmtIns) throw new Exception('db_prepare_failed');
    $stmtIns->bind_param('is', $app_id, $permit_no);
    if (!$stmtIns->execute()) throw new Exception('insert_failed');
    $stmtIns->close();

    $stmtU = $db->prepare("UPDATE franchise_applications SET status='LGU-Endorsed', endorsed_at=NOW(), remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END WHERE application_id=?");
    if (!$stmtU) throw new Exception('db_prepare_failed');
    $stmtU->bind_param('ssi', $notes, $notes, $app_id);
    $stmtU->execute();
    $stmtU->close();

    $db->commit();
    echo json_encode(['ok' => true, 'message' => 'Endorsement issued successfully', 'permit_number' => $permit_no]);
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
?>
