<?php
function tmm_operator_required_doc_slots(string $operatorType): array {
  $opType = $operatorType !== '' ? $operatorType : 'Individual';
  if ($opType === 'Cooperative') {
    return [
      ['doc_type' => 'CDA', 'keywords' => ['registration'], 'label' => 'CDA Registration Certificate'],
      ['doc_type' => 'CDA', 'keywords' => ['good standing', 'good_standing', 'standing'], 'label' => 'CDA Certificate of Good Standing'],
      ['doc_type' => 'Others', 'keywords' => ['board resolution', 'resolution'], 'label' => 'Board Resolution'],
    ];
  }
  if ($opType === 'Corporation') {
    return [
      ['doc_type' => 'SEC', 'keywords' => ['certificate', 'registration'], 'label' => 'SEC Certificate of Registration'],
      ['doc_type' => 'SEC', 'keywords' => ['articles', 'by-laws', 'bylaws', 'incorporation'], 'label' => 'Articles of Incorporation / By-laws'],
      ['doc_type' => 'Others', 'keywords' => ['board resolution', 'resolution'], 'label' => 'Board Resolution'],
    ];
  }
  return [
    ['doc_type' => 'GovID', 'keywords' => ['gov', 'id', 'driver', 'license', 'umid', 'philsys'], 'label' => 'Valid Government ID'],
  ];
}

function tmm_operator_is_valid_row(array $opRow): bool {
  $opStatus = (string)($opRow['status'] ?? '');
  $wfStatus = (string)($opRow['workflow_status'] ?? '');
  $vsStatus = (string)($opRow['verification_status'] ?? '');
  if ($opStatus === 'Inactive' || $wfStatus === 'Inactive' || $vsStatus === 'Inactive') return false;
  return ($wfStatus === 'Active' && $vsStatus === 'Verified');
}

function tmm_operator_docs_verified(mysqli $db, int $operatorId, array $slots): array {
  $hasUploadedAt = false;
  $chk = $db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_documents' AND COLUMN_NAME='uploaded_at' LIMIT 1");
  if ($chk && $chk->fetch_row()) $hasUploadedAt = true;
  $orderBy = $hasUploadedAt ? "uploaded_at DESC, doc_id DESC" : "doc_id DESC";
  $stmtD = $db->prepare("SELECT doc_id, doc_type, doc_status, is_verified, remarks FROM operator_documents WHERE operator_id=? ORDER BY $orderBy");
  if (!$stmtD) return ['ok' => false, 'error' => 'db_prepare_failed'];
  $stmtD->bind_param('i', $operatorId);
  $stmtD->execute();
  $resD = $stmtD->get_result();
  $docs = [];
  while ($resD && ($r = $resD->fetch_assoc())) $docs[] = $r;
  $stmtD->close();
  if (!$docs) return ['ok' => false, 'error' => 'operator_docs_missing'];

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
  if ($missing) return ['ok' => false, 'error' => 'operator_docs_not_verified', 'missing' => $missing];
  return ['ok' => true];
}

function tmm_route_capacity_check(mysqli $db, int $routeDbId, int $wantUnits, int $excludeApplicationId = 0): array {
  if ($routeDbId <= 0) return ['ok' => true];
  $stmtR = $db->prepare("SELECT authorized_units, status FROM routes WHERE id=? LIMIT 1");
  if (!$stmtR) return ['ok' => false, 'error' => 'db_prepare_failed'];
  $stmtR->bind_param('i', $routeDbId);
  $stmtR->execute();
  $route = $stmtR->get_result()->fetch_assoc();
  $stmtR->close();
  if (!$route) return ['ok' => false, 'error' => 'route_not_found'];
  if (((string)($route['status'] ?? '')) !== 'Active') return ['ok' => false, 'error' => 'route_inactive'];
  $cap = (int)($route['authorized_units'] ?? 0);
  if ($cap <= 0) return ['ok' => true];

  $want = $wantUnits > 0 ? $wantUnits : 1;
  if ($excludeApplicationId > 0) {
    $stmtC = $db->prepare("SELECT COALESCE(SUM(vehicle_count),0) AS c
                           FROM franchise_applications
                           WHERE route_id=? AND application_id<>? AND status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')");
    if (!$stmtC) return ['ok' => false, 'error' => 'db_prepare_failed'];
    $stmtC->bind_param('ii', $routeDbId, $excludeApplicationId);
  } else {
    $stmtC = $db->prepare("SELECT COALESCE(SUM(vehicle_count),0) AS c
                           FROM franchise_applications
                           WHERE route_id=? AND status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')");
    if (!$stmtC) return ['ok' => false, 'error' => 'db_prepare_failed'];
    $stmtC->bind_param('i', $routeDbId);
  }
  $stmtC->execute();
  $cur = $stmtC->get_result()->fetch_assoc();
  $stmtC->close();
  $curCount = (int)($cur['c'] ?? 0);
  if ($curCount + $want > $cap) return ['ok' => false, 'error' => 'route_over_capacity', 'cap' => $cap, 'used' => $curCount, 'want' => $want];
  return ['ok' => true, 'cap' => $cap, 'used' => $curCount, 'want' => $want];
}

function tmm_can_endorse_application(mysqli $db, int $operatorId, int $routeDbId, int $vehicleCount, int $excludeApplicationId = 0): array {
  $stmtO = $db->prepare("SELECT status, operator_type, verification_status, workflow_status FROM operators WHERE id=? LIMIT 1");
  if (!$stmtO) return ['ok' => false, 'error' => 'db_prepare_failed'];
  $stmtO->bind_param('i', $operatorId);
  $stmtO->execute();
  $op = $stmtO->get_result()->fetch_assoc();
  $stmtO->close();
  if (!$op) return ['ok' => false, 'error' => 'operator_not_found'];
  if (!tmm_operator_is_valid_row($op)) return ['ok' => false, 'error' => 'operator_invalid'];

  $slots = tmm_operator_required_doc_slots((string)($op['operator_type'] ?? ''));
  $docCheck = tmm_operator_docs_verified($db, $operatorId, $slots);
  if (!$docCheck['ok']) return $docCheck;

  $capCheck = tmm_route_capacity_check($db, $routeDbId, $vehicleCount, $excludeApplicationId);
  if (!$capCheck['ok']) return $capCheck;

  return ['ok' => true];
}

