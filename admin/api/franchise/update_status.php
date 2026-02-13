<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/franchise_gate.php';
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');
$id = (int)($_POST['application_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$permit = trim($_POST['permit_number'] ?? '');
if ($id <= 0 || $status === '') { echo json_encode(['error'=>'missing_fields']); exit; }

$st = $status;
$s = strtolower($status);
if ($s === 'endorsed') $st = 'LGU-Endorsed';
elseif ($s === 'approved') $st = 'LTFRB-Approved';

$allowed = ['Submitted','Pending','Under Review','Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','Rejected'];
if (!in_array($st, $allowed, true)) {
  echo json_encode(['ok' => false, 'error' => 'invalid_status']);
  exit;
}

if ($st === 'LTFRB-Approved' || $st === 'Approved' || $st === 'LGU-Endorsed' || $st === 'Endorsed') {
  $stmtA = $db->prepare("SELECT application_id, operator_id, route_id, vehicle_count, status FROM franchise_applications WHERE application_id=? LIMIT 1");
  if ($stmtA) {
    $stmtA->bind_param('i', $id);
    $stmtA->execute();
    $app = $stmtA->get_result()->fetch_assoc();
    $stmtA->close();
    if (!$app) { echo json_encode(['ok' => false, 'error' => 'application_not_found']); exit; }
    $cur = (string)($app['status'] ?? '');
    $operatorId = (int)($app['operator_id'] ?? 0);
    $routeDbId = (int)($app['route_id'] ?? 0);
    $need = (int)($app['vehicle_count'] ?? 0);
    if ($need <= 0) $need = 1;

    if ($st === 'LGU-Endorsed' || $st === 'Endorsed') {
      if ($cur === 'Endorsed' || $cur === 'LGU-Endorsed') {
        echo json_encode(['ok' => true, 'application_id' => $id, 'status' => $cur, 'permit_number' => $permit]);
        exit;
      }
      if ($cur !== 'Submitted') { echo json_encode(['ok' => false, 'error' => 'invalid_status_transition']); exit; }
      $gate = tmm_can_endorse_application($db, $operatorId, $routeDbId, $need, $id);
      if (!$gate['ok']) { echo json_encode($gate); exit; }
    }

    if ($st === 'LTFRB-Approved' || $st === 'Approved') {
      if (!in_array($cur, ['Endorsed','LGU-Endorsed','Approved','LTFRB-Approved'], true)) {
        echo json_encode(['ok' => false, 'error' => 'invalid_status_transition']);
        exit;
      }
      $gate = tmm_can_endorse_application($db, $operatorId, $routeDbId, $need, $id);
      if (!$gate['ok']) { echo json_encode($gate); exit; }
    }
    if ($st === 'LTFRB-Approved' || $st === 'Approved') {
      if ($operatorId > 0) {
        $hasCol = function (string $table, string $col) use ($db): bool {
          $table = trim($table);
          $col = trim($col);
          if ($table === '' || $col === '') return false;
          $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($col) . "'");
          return $res && ($res->num_rows ?? 0) > 0;
        };

        $vdTypeCol = $hasCol('vehicle_documents', 'doc_type') ? 'doc_type'
          : ($hasCol('vehicle_documents', 'document_type') ? 'document_type'
          : ($hasCol('vehicle_documents', 'type') ? 'type' : 'doc_type'));
        $vdVerifiedCol = $hasCol('vehicle_documents', 'is_verified') ? 'is_verified'
          : ($hasCol('vehicle_documents', 'verified') ? 'verified'
          : ($hasCol('vehicle_documents', 'isApproved') ? 'isApproved' : 'is_verified'));
        $vdHasVehicleId = $hasCol('vehicle_documents', 'vehicle_id');
        $vdHasPlate = $hasCol('vehicle_documents', 'plate_number');
        $docsHasExpiry = $hasCol('documents', 'expiry_date');
        $legacyOrValidCond = $docsHasExpiry ? "(d.expiry_date IS NULL OR d.expiry_date >= CURDATE())" : "1=1";
        $join = $vdHasVehicleId && $vdHasPlate
          ? "(vd.vehicle_id=v.id OR ((vd.vehicle_id IS NULL OR vd.vehicle_id=0) AND vd.plate_number=v.plate_number))"
          : ($vdHasVehicleId ? "vd.vehicle_id=v.id" : ($vdHasPlate ? "vd.plate_number=v.plate_number" : "0=1"));

        $orcrCond = "LOWER(vd.`{$vdTypeCol}`) IN ('orcr','or/cr')";
        $orCond = "LOWER(vd.`{$vdTypeCol}`)='or'";
        $crCond = "LOWER(vd.`{$vdTypeCol}`)='cr'";
        $verCond = "COALESCE(vd.`{$vdVerifiedCol}`,0)=1";

        $sql = "SELECT COUNT(*) AS c
                FROM (
                  SELECT v.id,
                         MAX(CASE WHEN {$orcrCond} AND {$verCond} THEN 1 ELSE 0 END) AS orcr_ok,
                         MAX(CASE WHEN {$orCond} AND {$verCond} THEN 1 ELSE 0 END) AS or_ok,
                         MAX(CASE WHEN {$crCond} AND {$verCond} THEN 1 ELSE 0 END) AS cr_ok,
                         MAX(CASE WHEN LOWER(d.type)='or' AND COALESCE(d.verified,0)=1 AND {$legacyOrValidCond} THEN 1 ELSE 0 END) AS legacy_or_ok,
                         MAX(CASE WHEN LOWER(d.type)='cr' AND COALESCE(d.verified,0)=1 THEN 1 ELSE 0 END) AS legacy_cr_ok,
                         MAX(CASE WHEN LOWER(d.type) IN ('orcr','or/cr') AND COALESCE(d.verified,0)=1 THEN 1 ELSE 0 END) AS legacy_orcr_ok
                  FROM vehicles v
                  LEFT JOIN vehicle_documents vd ON {$join}
                  LEFT JOIN documents d ON d.plate_number=v.plate_number
                  WHERE v.operator_id=? AND (COALESCE(v.record_status,'') <> 'Archived')
                  GROUP BY v.id
                ) x
                WHERE (x.orcr_ok=1 OR x.legacy_orcr_ok=1 OR ((x.or_ok=1 OR x.legacy_or_ok=1) AND (x.cr_ok=1 OR x.legacy_cr_ok=1)))";
        $stmtVeh = $db->prepare($sql);
        if ($stmtVeh) {
          $stmtVeh->bind_param('i', $operatorId);
          $stmtVeh->execute();
          $row = $stmtVeh->get_result()->fetch_assoc();
          $stmtVeh->close();
          $have = (int)($row['c'] ?? 0);
          if ($have < $need) {
            echo json_encode(['ok' => false, 'error' => 'orcr_required_for_approval', 'need' => $need, 'have' => $have]);
            exit;
          }
        }
      }
    }
  }
}

// If attempting to endorse, ensure the linked cooperative has an LGU approval number.
if ($st === 'LGU-Endorsed' || $st === 'Endorsed') {
  $stmtCheck = $db->prepare("SELECT c.lgu_approval_number, c.coop_name FROM franchise_applications fa LEFT JOIN coops c ON fa.coop_id = c.id WHERE fa.application_id = ?");
  $stmtCheck->bind_param('i', $id);
  $stmtCheck->execute();
  $resCheck = $stmtCheck->get_result();
  $rowCheck = $resCheck ? $resCheck->fetch_assoc() : null;
  $lguNo = $rowCheck['lgu_approval_number'] ?? '';
  if ($lguNo === null) $lguNo = '';
  $lguNo = trim($lguNo);
  if ($lguNo === '') {
    $coopName = $rowCheck['coop_name'] ?? '';
    echo json_encode([
      'ok' => false,
      'error' => 'Cannot endorse application because the cooperative has no LGU approval number.',
      'error_code' => 'coop_missing_lgu_approval',
      'coop_name' => $coopName
    ]);
    exit;
  }
}

$stmt = $db->prepare("UPDATE franchise_applications SET status=? WHERE application_id=?");
$stmt->bind_param('si', $st, $id);
$ok = $stmt->execute();
if (!$ok) { echo json_encode(['error'=>'update_failed']); exit; }
if ($st === 'LGU-Endorsed' || $st === 'Endorsed') {
  $stmt2 = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number)
                         VALUES (?, CURDATE(), ?)
                         ON DUPLICATE KEY UPDATE issued_date=issued_date, permit_number=IF(permit_number IS NULL OR permit_number='', VALUES(permit_number), permit_number)");
  if ($stmt2) {
    $stmt2->bind_param('is', $id, $permit);
    $stmt2->execute();
    $stmt2->close();
  }
}
echo json_encode(['ok'=>true, 'application_id'=>$id, 'status'=>$st, 'permit_number'=>$permit]);
?> 
