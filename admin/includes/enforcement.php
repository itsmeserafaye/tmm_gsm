<?php

function tmm_enforcement_table_exists(mysqli $db, string $table): bool
{
  $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  if (!$stmt)
    return false;
  $stmt->bind_param('s', $table);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = (bool) ($res && $res->fetch_row());
  $stmt->close();
  return $ok;
}

function tmm_enforcement_get_block_reasons(mysqli $db, array $ctx): array
{
  $vehicleId = (int) ($ctx['vehicle_id'] ?? 0);
  $operatorId = (int) ($ctx['operator_id'] ?? 0);
  $plate = strtoupper(trim((string) ($ctx['plate_number'] ?? $ctx['plate'] ?? '')));

  $reasons = [];

  $vehCompliance = '';
  if ($vehicleId > 0) {
    $stmt = $db->prepare("SELECT COALESCE(NULLIF(compliance_status,''),'Active') AS compliance_status FROM vehicles WHERE id=? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('i', $vehicleId);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      $vehCompliance = (string) ($row['compliance_status'] ?? '');
    }
  } elseif ($plate !== '') {
    $stmt = $db->prepare("SELECT COALESCE(NULLIF(compliance_status,''),'Active') AS compliance_status FROM vehicles WHERE plate_number=? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('s', $plate);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      $vehCompliance = (string) ($row['compliance_status'] ?? '');
    }
  }

  if (in_array($vehCompliance, ['Suspended', 'For Review'], true)) {
    $reasons[] = ['code' => 'vehicle_suspended', 'detail' => $vehCompliance];
  }

  if (tmm_enforcement_table_exists($db, 'violations') && $plate !== '') {
    $stmtV = $db->prepare("SELECT COUNT(*) AS c FROM violations WHERE plate_number=? AND COALESCE(status,'Unpaid')='Unpaid'");
    if ($stmtV) {
      $stmtV->bind_param('s', $plate);
      $stmtV->execute();
      $row = $stmtV->get_result()->fetch_assoc();
      $stmtV->close();
      $c = (int) ($row['c'] ?? 0);
      if ($c > 0)
        $reasons[] = ['code' => 'unpaid_violations', 'detail' => $c];
    }
  }

  if (tmm_enforcement_table_exists($db, 'sts_tickets') && tmm_enforcement_table_exists($db, 'violations')) {
    if ($plate !== '') {
      $stmtT = $db->prepare("SELECT COUNT(*) AS c
                             FROM sts_tickets t
                             JOIN violations v ON v.id=t.linked_violation_id
                             WHERE v.plate_number=? AND t.status='Pending Payment'");
      if ($stmtT) {
        $stmtT->bind_param('s', $plate);
        $stmtT->execute();
        $row = $stmtT->get_result()->fetch_assoc();
        $stmtT->close();
        $c = (int) ($row['c'] ?? 0);
        if ($c > 0)
          $reasons[] = ['code' => 'unpaid_tickets', 'detail' => $c];
      }
    } elseif ($operatorId > 0) {
      $stmtT = $db->prepare("SELECT COUNT(*) AS c
                             FROM sts_tickets t
                             JOIN violations v ON v.id=t.linked_violation_id
                             WHERE v.operator_id=? AND t.status='Pending Payment'");
      if ($stmtT) {
        $stmtT->bind_param('i', $operatorId);
        $stmtT->execute();
        $row = $stmtT->get_result()->fetch_assoc();
        $stmtT->close();
        $c = (int) ($row['c'] ?? 0);
        if ($c > 0)
          $reasons[] = ['code' => 'unpaid_tickets', 'detail' => $c];
      }
    }
  }

  if (tmm_enforcement_table_exists($db, 'tickets')) {
    if ($plate !== '') {
      $stmt = $db->prepare("SELECT COUNT(*) AS c FROM tickets WHERE vehicle_plate=? AND COALESCE(status,'Unpaid') IN ('Unpaid','Pending Payment')");
      if ($stmt) {
        $stmt->bind_param('s', $plate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $c = (int) ($row['c'] ?? 0);
        if ($c > 0)
          $reasons[] = ['code' => 'unpaid_tickets', 'detail' => $c];
      }
    } elseif ($operatorId > 0) {
      $stmt = $db->prepare("SELECT COUNT(*) AS c FROM tickets WHERE operator_id=? AND COALESCE(status,'Unpaid') IN ('Unpaid','Pending Payment')");
      if ($stmt) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $c = (int) ($row['c'] ?? 0);
        if ($c > 0)
          $reasons[] = ['code' => 'unpaid_tickets', 'detail' => $c];
      }
    }
  }

  if (!$reasons)
    return ['ok' => true];
  return ['ok' => false, 'error' => 'blocked_by_enforcement', 'reasons' => $reasons];
}

