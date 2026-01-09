<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'invalid_method']);
  exit;
}

$plate = strtoupper(trim($_POST['plate'] ?? ''));
$franchiseRef = trim($_POST['franchise_ref_number'] ?? '');
$mode = strtolower(trim($_POST['mode'] ?? 'auto'));

$updated = [
  'vehicles_suspended' => [],
  'vehicles_unsuspended' => [],
  'franchises_suspended' => [],
  'franchises_unsuspended' => [],
  'cases_opened' => []
];

function unresolved_count_for_plate(mysqli $db, string $plate): int {
  $stmt = $db->prepare("SELECT COUNT(*) AS c FROM tickets WHERE vehicle_plate=? AND status IN ('Pending','Validated','Escalated')");
  $stmt->bind_param('s', $plate);
  $stmt->execute();
  $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  return $c;
}

function unresolved_count_for_franchise(mysqli $db, string $frRef): int {
  $stmt = $db->prepare("SELECT COUNT(*) AS c FROM tickets WHERE franchise_id=? AND status IN ('Pending','Validated','Escalated')");
  $stmt->bind_param('s', $frRef);
  $stmt->execute();
  $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  return $c;
}

function vehicles_suspended_under_franchise(mysqli $db, string $frRef): int {
  $stmt = $db->prepare("SELECT COUNT(*) AS c FROM vehicles WHERE franchise_id=? AND status='Suspended'");
  $stmt->bind_param('s', $frRef);
  $stmt->execute();
  $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  return $c;
}

if ($plate !== '') {
  $stmtV = $db->prepare("SELECT franchise_id, status FROM vehicles WHERE plate_number=?");
  $stmtV->bind_param('s', $plate);
  $stmtV->execute();
  $veh = $stmtV->get_result()->fetch_assoc();
  if (!$veh) { echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }
  $frId = trim($veh['franchise_id'] ?? '');
  $uc = unresolved_count_for_plate($db, $plate);
  if ($uc >= 3) {
    $stmtSV = $db->prepare("UPDATE vehicles SET status='Suspended' WHERE plate_number=? AND status<>'Suspended'");
    $stmtSV->bind_param('s', $plate);
    $stmtSV->execute();
    $updated['vehicles_suspended'][] = $plate;
    $stmtCS = $db->prepare("INSERT INTO compliance_summary(vehicle_plate, franchise_id, violation_count, last_violation_date, compliance_status) VALUES (?, NULLIF(?, ''), ?, CURDATE(), 'Suspended') ON DUPLICATE KEY UPDATE violation_count=VALUES(violation_count), last_violation_date=CURDATE(), compliance_status='Suspended'");
    $vc = $uc;
    $stmtCS->bind_param('ssi', $plate, $frId, $vc);
    $stmtCS->execute();
    if ($frId !== '') {
      $stmtF = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
      $stmtF->bind_param('s', $frId);
      $stmtF->execute();
      $frow = $stmtF->get_result()->fetch_assoc();
      $current = $frow ? ($frow['status'] ?? '') : '';
      if ($current !== 'Suspended') {
        $stmtUF = $db->prepare("UPDATE franchise_applications SET status='Suspended' WHERE franchise_ref_number=?");
        $stmtUF->bind_param('s', $frId);
        $stmtUF->execute();
        $updated['franchises_suspended'][] = $frId;
        $desc = "Vehicle $plate suspended due to >=3 unresolved tickets; franchise endorsement suspended.";
        $stmtC = $db->prepare("INSERT INTO compliance_cases (franchise_ref_number, violation_type, penalty_amount, violation_details, status, reported_at) VALUES (?, 'Repeat Violations Suspension', 0.00, ?, 'Open', NOW())");
        $stmtC->bind_param('ss', $frId, $desc);
        $stmtC->execute();
        $updated['cases_opened'][] = $frId;
      }
    }
  } else {
    $stmtRV = $db->prepare("UPDATE vehicles SET status='Active' WHERE plate_number=? AND status='Suspended'");
    $stmtRV->bind_param('s', $plate);
    $stmtRV->execute();
    $updated['vehicles_unsuspended'][] = $plate;
    $stmtNC = $db->prepare("UPDATE compliance_summary SET compliance_status='Normal' WHERE vehicle_plate=? AND compliance_status<>'Normal'");
    $stmtNC->bind_param('s', $plate);
    $stmtNC->execute();
    if ($frId !== '') {
      $vf = vehicles_suspended_under_franchise($db, $frId);
      $fc = unresolved_count_for_franchise($db, $frId);
      if ($vf === 0 && $fc < 3) {
        $stmtF2 = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
        $stmtF2->bind_param('s', $frId);
        $stmtF2->execute();
        $cur = $stmtF2->get_result()->fetch_assoc();
        $st = $cur ? ($cur['status'] ?? '') : '';
        if ($st === 'Suspended') {
          $stmtUS = $db->prepare("UPDATE franchise_applications SET status='Endorsed' WHERE franchise_ref_number=?");
          $stmtUS->bind_param('s', $frId);
          $stmtUS->execute();
          $updated['franchises_unsuspended'][] = $frId;
        }
      }
    }
  }
}

if ($franchiseRef !== '') {
  $ucf = unresolved_count_for_franchise($db, $franchiseRef);
  $vsf = vehicles_suspended_under_franchise($db, $franchiseRef);
  if ($ucf >= 3 || $vsf >= 1) {
    $stmtF3 = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
    $stmtF3->bind_param('s', $franchiseRef);
    $stmtF3->execute();
    $curF = $stmtF3->get_result()->fetch_assoc();
    $stF = $curF ? ($curF['status'] ?? '') : '';
    if ($stF !== 'Suspended') {
      $stmtSF = $db->prepare("UPDATE franchise_applications SET status='Suspended' WHERE franchise_ref_number=?");
      $stmtSF->bind_param('s', $franchiseRef);
      $stmtSF->execute();
      $updated['franchises_suspended'][] = $franchiseRef;
      $desc = "Franchise $franchiseRef suspended due to repeat violations.";
      $stmtC2 = $db->prepare("INSERT INTO compliance_cases (franchise_ref_number, violation_type, penalty_amount, violation_details, status, reported_at) VALUES (?, 'Repeat Violations Suspension', 0.00, ?, 'Open', NOW())");
      $stmtC2->bind_param('ss', $franchiseRef, $desc);
      $stmtC2->execute();
      $updated['cases_opened'][] = $franchiseRef;
    }
    $stmtList = $db->prepare("SELECT DISTINCT vehicle_plate FROM tickets WHERE franchise_id=? AND status IN ('Pending','Validated','Escalated')");
    $stmtList->bind_param('s', $franchiseRef);
    $stmtList->execute();
    $rs = $stmtList->get_result();
    while ($r = $rs->fetch_assoc()) {
      $vp = $r['vehicle_plate'];
      $c = unresolved_count_for_plate($db, $vp);
      if ($c >= 3) {
        $stmtSV2 = $db->prepare("UPDATE vehicles SET status='Suspended' WHERE plate_number=? AND status<>'Suspended'");
        $stmtSV2->bind_param('s', $vp);
        $stmtSV2->execute();
        $updated['vehicles_suspended'][] = $vp;
        $stmtCS2 = $db->prepare("INSERT INTO compliance_summary(vehicle_plate, franchise_id, violation_count, last_violation_date, compliance_status) VALUES (?, ?, ?, CURDATE(), 'Suspended') ON DUPLICATE KEY UPDATE violation_count=VALUES(violation_count), last_violation_date=CURDATE(), compliance_status='Suspended'");
        $stmtCS2->bind_param('ssi', $vp, $franchiseRef, $c);
        $stmtCS2->execute();
      }
    }
  } else {
    $stmtF4 = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
    $stmtF4->bind_param('s', $franchiseRef);
    $stmtF4->execute();
    $curF2 = $stmtF4->get_result()->fetch_assoc();
    $stF2 = $curF2 ? ($curF2['status'] ?? '') : '';
    if ($stF2 === 'Suspended') {
      $stmtUF2 = $db->prepare("UPDATE franchise_applications SET status='Endorsed' WHERE franchise_ref_number=?");
      $stmtUF2->bind_param('s', $franchiseRef);
      $stmtUF2->execute();
      $updated['franchises_unsuspended'][] = $franchiseRef;
    }
  }
}

if ($mode === 'auto_all') {
  $resV = $db->query("SELECT plate_number, franchise_id FROM vehicles");
  if ($resV) {
    while ($rowV = $resV->fetch_assoc()) {
      $_POST['plate'] = $rowV['plate_number'];
      $_POST['franchise_ref_number'] = '';
      $_POST['mode'] = 'auto';
      $plate2 = strtoupper(trim($rowV['plate_number'] ?? ''));
      if ($plate2 !== '') {
        $stmtV2 = $db->prepare("SELECT franchise_id, status FROM vehicles WHERE plate_number=?");
        $stmtV2->bind_param('s', $plate2);
        $stmtV2->execute();
        $veh2 = $stmtV2->get_result()->fetch_assoc();
        if ($veh2) {
          $frId2 = trim($veh2['franchise_id'] ?? '');
          $uc2 = unresolved_count_for_plate($db, $plate2);
          if ($uc2 >= 3) {
            $stmtSVx = $db->prepare("UPDATE vehicles SET status='Suspended' WHERE plate_number=? AND status<>'Suspended'");
            $stmtSVx->bind_param('s', $plate2);
            $stmtSVx->execute();
            $updated['vehicles_suspended'][] = $plate2;
            $stmtCSx = $db->prepare("INSERT INTO compliance_summary(vehicle_plate, franchise_id, violation_count, last_violation_date, compliance_status) VALUES (?, NULLIF(?, ''), ?, CURDATE(), 'Suspended') ON DUPLICATE KEY UPDATE violation_count=VALUES(violation_count), last_violation_date=CURDATE(), compliance_status='Suspended'");
            $stmtCSx->bind_param('ssi', $plate2, $frId2, $uc2);
            $stmtCSx->execute();
            if ($frId2 !== '') {
              $stmtFx = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
              $stmtFx->bind_param('s', $frId2);
              $stmtFx->execute();
              $frowx = $stmtFx->get_result()->fetch_assoc();
              $currx = $frowx ? ($frowx['status'] ?? '') : '';
              if ($currx !== 'Suspended') {
                $stmtUFx = $db->prepare("UPDATE franchise_applications SET status='Suspended' WHERE franchise_ref_number=?");
                $stmtUFx->bind_param('s', $frId2);
                $stmtUFx->execute();
                $updated['franchises_suspended'][] = $frId2;
                $descx = "Vehicle $plate2 suspended due to >=3 unresolved tickets; franchise endorsement suspended.";
                $stmtCx = $db->prepare("INSERT INTO compliance_cases (franchise_ref_number, violation_type, penalty_amount, violation_details, status, reported_at) VALUES (?, 'Repeat Violations Suspension', 0.00, ?, 'Open', NOW())");
                $stmtCx->bind_param('ss', $frId2, $descx);
                $stmtCx->execute();
                $updated['cases_opened'][] = $frId2;
              }
            }
          } else {
            $stmtRVx = $db->prepare("UPDATE vehicles SET status='Active' WHERE plate_number=? AND status='Suspended'");
            $stmtRVx->bind_param('s', $plate2);
            $stmtRVx->execute();
            $updated['vehicles_unsuspended'][] = $plate2;
            $stmtNCx = $db->prepare("UPDATE compliance_summary SET compliance_status='Normal' WHERE vehicle_plate=? AND compliance_status<>'Normal'");
            $stmtNCx->bind_param('s', $plate2);
            $stmtNCx->execute();
          }
        }
      }
    }
  }
  $resF = $db->query("SELECT franchise_ref_number FROM franchise_applications");
  if ($resF) {
    while ($rowF = $resF->fetch_assoc()) {
      $frRefAll = trim($rowF['franchise_ref_number'] ?? '');
      if ($frRefAll === '') continue;
      $ucf2 = unresolved_count_for_franchise($db, $frRefAll);
      $vsf2 = vehicles_suspended_under_franchise($db, $frRefAll);
      if ($ucf2 >= 3 || $vsf2 >= 1) {
        $stmtF3x = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
        $stmtF3x->bind_param('s', $frRefAll);
        $stmtF3x->execute();
        $curFx = $stmtF3x->get_result()->fetch_assoc();
        $stFx = $curFx ? ($curFx['status'] ?? '') : '';
        if ($stFx !== 'Suspended') {
          $stmtSFx = $db->prepare("UPDATE franchise_applications SET status='Suspended' WHERE franchise_ref_number=?");
          $stmtSFx->bind_param('s', $frRefAll);
          $stmtSFx->execute();
          $updated['franchises_suspended'][] = $frRefAll;
          $desc2 = "Franchise $frRefAll suspended due to repeat violations.";
          $stmtC2x = $db->prepare("INSERT INTO compliance_cases (franchise_ref_number, violation_type, penalty_amount, violation_details, status, reported_at) VALUES (?, 'Repeat Violations Suspension', 0.00, ?, 'Open', NOW())");
          $stmtC2x->bind_param('ss', $frRefAll, $desc2);
          $stmtC2x->execute();
          $updated['cases_opened'][] = $frRefAll;
        }
      } else {
        $stmtF4x = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
        $stmtF4x->bind_param('s', $frRefAll);
        $stmtF4x->execute();
        $curF2x = $stmtF4x->get_result()->fetch_assoc();
        $stF2x = $curF2x ? ($curF2x['status'] ?? '') : '';
        if ($stF2x === 'Suspended') {
          $stmtUF2x = $db->prepare("UPDATE franchise_applications SET status='Endorsed' WHERE franchise_ref_number=?");
          $stmtUF2x->bind_param('s', $frRefAll);
          $stmtUF2x->execute();
          $updated['franchises_unsuspended'][] = $frRefAll;
        }
      }
    }
  }
}

echo json_encode(['ok'=>true,'updated'=>$updated,'mode'=>$mode]);
?> 
