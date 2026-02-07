<?php

function tmm_violation_get_severity(mysqli $db, string $code): string
{
  $code = trim($code);
  if ($code === '')
    return 'Minor';

  $sev = '';
  $cat = '';
  $stmt = $db->prepare("SELECT COALESCE(NULLIF(severity,''),''), COALESCE(NULLIF(category,''),'') FROM violation_types WHERE violation_code=? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    if ($row) {
      $sev = (string)($row[0] ?? '');
      $cat = (string)($row[1] ?? '');
    }
  }

  $sev = ucfirst(strtolower(trim($sev)));
  if (in_array($sev, ['Minor', 'Severe', 'Critical'], true))
    return $sev;

  $criticalCodes = ['RD', 'DRK', 'NDL', 'EXR', 'UUT'];
  if (in_array($code, $criticalCodes, true))
    return 'Critical';

  $catNorm = strtolower(trim($cat));
  if (in_array($catNorm, ['safety', 'registration', 'licensing'], true))
    return 'Severe';

  return 'Minor';
}

function tmm_violation_count_vehicle_window(mysqli $db, string $plate, string $sinceSql): int
{
  $plate = trim($plate);
  if ($plate === '')
    return 0;

  $count = 0;

  $stmtV = $db->prepare("SELECT COUNT(*) FROM violations WHERE plate_number=? AND COALESCE(violation_date, created_at) >= ?");
  if ($stmtV) {
    $stmtV->bind_param('ss', $plate, $sinceSql);
    $stmtV->execute();
    $row = $stmtV->get_result()->fetch_row();
    $stmtV->close();
    $count += (int)($row[0] ?? 0);
  }

  $stmtT = $db->prepare("SELECT COUNT(*) FROM tickets WHERE vehicle_plate=? AND date_issued >= ?");
  if ($stmtT) {
    $stmtT->bind_param('ss', $plate, $sinceSql);
    $stmtT->execute();
    $row = $stmtT->get_result()->fetch_row();
    $stmtT->close();
    $count += (int)($row[0] ?? 0);
  }

  return $count;
}

function tmm_violation_count_operator_window(mysqli $db, int $operatorId, string $sinceSql): int
{
  if ($operatorId <= 0)
    return 0;

  $count = 0;

  $stmtV = $db->prepare("SELECT COUNT(*) FROM violations WHERE operator_id=? AND COALESCE(violation_date, created_at) >= ?");
  if ($stmtV) {
    $stmtV->bind_param('is', $operatorId, $sinceSql);
    $stmtV->execute();
    $row = $stmtV->get_result()->fetch_row();
    $stmtV->close();
    $count += (int)($row[0] ?? 0);
  }

  $stmtT = $db->prepare("SELECT COUNT(*) FROM tickets WHERE operator_id=? AND date_issued >= ?");
  if ($stmtT) {
    $stmtT->bind_param('is', $operatorId, $sinceSql);
    $stmtT->execute();
    $row = $stmtT->get_result()->fetch_row();
    $stmtT->close();
    $count += (int)($row[0] ?? 0);
  }

  return $count;
}

function tmm_violation_compute_vehicle_compliance(string $severity, int $windowCount): array
{
  $severity = ucfirst(strtolower(trim($severity)));
  if ($severity === 'Critical') {
    return ['level' => 3, 'status' => 'Suspended'];
  }

  if ($windowCount >= 7)
    return ['level' => 4, 'status' => 'For Review'];
  if ($windowCount >= 5)
    return ['level' => 3, 'status' => 'Suspended'];
  if ($windowCount >= 3)
    return ['level' => 2, 'status' => 'Flagged'];

  return ['level' => 1, 'status' => 'Active'];
}

function tmm_violation_compute_operator_risk(int $windowCount): array
{
  if ($windowCount >= 7)
    return ['score' => $windowCount, 'level' => 'High'];
  if ($windowCount >= 3)
    return ['score' => $windowCount, 'level' => 'Medium'];
  return ['score' => $windowCount, 'level' => 'Low'];
}

function tmm_apply_progressive_violation_policy(mysqli $db, array $ctx): array
{
  $plate = strtoupper(trim((string)($ctx['plate_number'] ?? $ctx['vehicle_plate'] ?? '')));
  $code = trim((string)($ctx['violation_code'] ?? $ctx['violation_type'] ?? ''));
  $operatorId = (int)($ctx['operator_id'] ?? 0);
  $vehicleId = (int)($ctx['vehicle_id'] ?? 0);
  $franchiseRef = trim((string)($ctx['franchise_ref_number'] ?? $ctx['franchise_id'] ?? ''));
  $observedAtSql = trim((string)($ctx['observed_at'] ?? ''));

  $baseTs = $observedAtSql !== '' ? strtotime($observedAtSql) : time();
  if ($baseTs === false)
    $baseTs = time();

  $since30 = date('Y-m-d H:i:s', $baseTs - (30 * 86400));
  $since90 = date('Y-m-d H:i:s', $baseTs - (90 * 86400));

  $severity = tmm_violation_get_severity($db, $code);
  $count30 = tmm_violation_count_vehicle_window($db, $plate, $since30);
  $vehPolicy = tmm_violation_compute_vehicle_compliance($severity, $count30);

  $riskCount = tmm_violation_count_operator_window($db, $operatorId, $since90);
  $opRisk = tmm_violation_compute_operator_risk($riskCount);

  if ($vehicleId > 0) {
    $reason = substr("{$code} {$severity} ({$count30}/30d)", 0, 255);
    $stmt = $db->prepare("UPDATE vehicles SET compliance_status=?, compliance_updated_at=NOW(), compliance_reason=? WHERE id=?");
    if ($stmt) {
      $stmt->bind_param('ssi', $vehPolicy['status'], $reason, $vehicleId);
      $stmt->execute();
      $stmt->close();
    }
  } elseif ($plate !== '') {
    $reason = substr("{$code} {$severity} ({$count30}/30d)", 0, 255);
    $stmt = $db->prepare("UPDATE vehicles SET compliance_status=?, compliance_updated_at=NOW(), compliance_reason=? WHERE plate_number=?");
    if ($stmt) {
      $stmt->bind_param('sss', $vehPolicy['status'], $reason, $plate);
      $stmt->execute();
      $stmt->close();
    }
  }

  if ($operatorId > 0) {
    $stmt = $db->prepare("UPDATE operators SET risk_score=?, risk_level=? WHERE id=?");
    if ($stmt) {
      $stmt->bind_param('isi', $opRisk['score'], $opRisk['level'], $operatorId);
      $stmt->execute();
      $stmt->close();
    }
  }

  if ($vehPolicy['status'] === 'For Review' && $franchiseRef !== '') {
    $stmt = $db->prepare("INSERT INTO compliance_cases (franchise_ref_number, violation_type, status) VALUES (?, ?, 'Open')");
    if ($stmt) {
      $vt = substr("Revocation Candidate: {$code}", 0, 100);
      $stmt->bind_param('ss', $franchiseRef, $vt);
      $stmt->execute();
      $stmt->close();
    }
  }

  return [
    'vehicle' => ['count_30d' => $count30, 'severity' => $severity, 'policy' => $vehPolicy],
    'operator' => ['count_90d' => $riskCount, 'policy' => $opRisk],
  ];
}

