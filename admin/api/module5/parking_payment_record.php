<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.parking_fees');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$plateRaw = strtoupper(trim((string)($_POST['plate_no'] ?? ($_POST['plate_number'] ?? ''))));
$plateRaw = preg_replace('/\s+/', '', $plateRaw);
$plateNoDash = preg_replace('/[^A-Z0-9]/', '', $plateRaw);
$plate = $plateRaw !== null ? (string)$plateRaw : '';
$plateNoDash = $plateNoDash !== null ? (string)$plateNoDash : '';
if ($plate !== '' && strpos($plate, '-') === false) {
  if (preg_match('/^([A-Z0-9]+)(\d{3,4})$/', $plateNoDash, $m)) {
    $plate = $m[1] . '-' . $m[2];
  }
}
$terminalId = (int)($_POST['terminal_id'] ?? 0);
$slotId = (int)($_POST['slot_id'] ?? 0);
$slotNoRaw = trim((string)($_POST['slot_no'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);
$orNo = trim((string)($_POST['or_no'] ?? ''));
$paidAtRaw = trim((string)($_POST['paid_at'] ?? ''));
$exportedToTreasury = isset($_POST['exported_to_treasury']) ? (int)($_POST['exported_to_treasury'] ?? 0) : 0;
$exportedToTreasury = $exportedToTreasury === 1 ? 1 : 0;
$exportedAtRaw = trim((string)($_POST['exported_at'] ?? ''));

$paidAt = null;
if ($paidAtRaw !== '') {
  $ts = strtotime($paidAtRaw);
  if ($ts !== false) $paidAt = date('Y-m-d H:i:s', $ts);
}

$exportedAt = null;
if ($exportedToTreasury === 1) {
  if ($exportedAtRaw !== '') {
    $ts2 = strtotime($exportedAtRaw);
    if ($ts2 !== false) $exportedAt = date('Y-m-d H:i:s', $ts2);
  }
  if ($exportedAt === null) $exportedAt = $paidAt !== null ? $paidAt : date('Y-m-d H:i:s');
}

// Fallback: resolve slot_id from slot_no if not provided
if ($slotId <= 0 && $terminalId > 0 && $slotNoRaw !== '') {
  $stmtResolveSlot = $db->prepare("SELECT slot_id FROM parking_slots WHERE terminal_id=? AND slot_no=? LIMIT 1");
  if ($stmtResolveSlot) {
    $stmtResolveSlot->bind_param('is', $terminalId, $slotNoRaw);
    $stmtResolveSlot->execute();
    $rowRS = $stmtResolveSlot->get_result()->fetch_assoc();
    $stmtResolveSlot->close();
    $slotId = (int)($rowRS['slot_id'] ?? 0);
  }
}

if ($slotId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'slot_required']);
  exit;
}
if ($plate === '' || $terminalId <= 0 || $amount <= 0 || $orNo === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}

$stmtV = $db->prepare("SELECT id FROM vehicles WHERE plate_number=? OR REPLACE(plate_number,'-','')=? LIMIT 1");
if (!$stmtV) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtV->bind_param('ss', $plate, $plateNoDash);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }
$vehicleId = (int)($veh['id'] ?? 0);

$db->begin_transaction();
try {
  $slotTerminalId = 0;
  // Try by slot_id first, then fallback by slot_no against terminal if not found
  $slot = null;
  $stmtS = $db->prepare("SELECT slot_id, status, terminal_id FROM parking_slots WHERE slot_id=? LIMIT 1 FOR UPDATE");
  if (!$stmtS) throw new Exception('db_prepare_failed');
  $stmtS->bind_param('i', $slotId);
  $stmtS->execute();
  $slot = $stmtS->get_result()->fetch_assoc();
  $stmtS->close();
  if (!$slot) {
    $slotNoCandidate = $slotNoRaw !== '' ? $slotNoRaw : (string)$slotId;
    if ($terminalId > 0 && $slotNoCandidate !== '') {
      $stmtS2 = $db->prepare("SELECT slot_id, status, terminal_id FROM parking_slots WHERE terminal_id=? AND slot_no=? LIMIT 1 FOR UPDATE");
      if (!$stmtS2) throw new Exception('db_prepare_failed');
      $stmtS2->bind_param('is', $terminalId, $slotNoCandidate);
      $stmtS2->execute();
      $slot = $stmtS2->get_result()->fetch_assoc();
      $stmtS2->close();
      if ($slot && isset($slot['slot_id'])) {
        $slotId = (int)$slot['slot_id'];
      }
    }
  }
  if (!$slot) throw new Exception('slot_not_found');
  $slotStatus = strtolower(trim((string)($slot['status'] ?? '')));
  if ($slotStatus === 'occupied') throw new Exception('slot_not_free');
  $slotTerminalId = (int)($slot['terminal_id'] ?? 0);
  if ($slotTerminalId <= 0 || $slotTerminalId !== $terminalId) throw new Exception('slot_terminal_mismatch');

  if ($slotTerminalId > 0) {
    $stmtAssign = $db->prepare("SELECT terminal_id FROM terminal_assignments WHERE vehicle_id=?");
    if ($stmtAssign) {
      $stmtAssign->bind_param('i', $vehicleId);
      $stmtAssign->execute();
      $resAssign = $stmtAssign->get_result();
      $assignedTerminals = [];
      while ($rowA = $resAssign->fetch_assoc()) {
        $assignedTerminals[] = (int)$rowA['terminal_id'];
      }
      $stmtAssign->close();
      if (!empty($assignedTerminals)) {
        if (!in_array($slotTerminalId, $assignedTerminals, true)) {
          throw new Exception('vehicle_restricted_to_assigned_terminals');
        }
      }
    }

    // Enforce route-based restriction: vehicle/operator must have approved route allowed in this terminal
    $operatorId = 0;
    $vehicleRouteRef = '';
    $stmtVehInfo = $db->prepare("SELECT operator_id, route_id FROM vehicles WHERE id=? LIMIT 1");
    if ($stmtVehInfo) {
      $stmtVehInfo->bind_param('i', $vehicleId);
      $stmtVehInfo->execute();
      $rowVI = $stmtVehInfo->get_result()->fetch_assoc();
      $stmtVehInfo->close();
      $operatorId = (int)($rowVI['operator_id'] ?? 0);
      $vehicleRouteRef = trim((string)($rowVI['route_id'] ?? ''));
    }

    $hasTable = function (string $table) use ($db): bool {
      $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
      if (!$stmt) return false;
      $stmt->bind_param('s', $table);
      $stmt->execute();
      $ok = (bool)($stmt->get_result()->fetch_row());
      $stmt->close();
      return $ok;
    };

    if ($operatorId > 0 && $hasTable('franchise_applications') && $hasTable('routes') && $hasTable('terminal_routes')) {
      $routeDbIds = [];
      $stmtF = $db->prepare("SELECT DISTINCT route_id FROM franchise_applications WHERE operator_id=? AND route_id IS NOT NULL AND route_id>0 AND status IN ('Approved','LTFRB-Approved')");
      if ($stmtF) {
        $stmtF->bind_param('i', $operatorId);
        $stmtF->execute();
        $resF = $stmtF->get_result();
        while ($resF && ($r = $resF->fetch_assoc())) {
          $rid = (int)($r['route_id'] ?? 0);
          if ($rid > 0) $routeDbIds[] = $rid;
        }
        $stmtF->close();
      }

      // Fallback: resolve vehicle's route reference to route DB id if no approved list found
      if (!$routeDbIds && $vehicleRouteRef !== '') {
        $stmtResolve = $db->prepare("SELECT id FROM routes WHERE route_id=? OR route_code=? LIMIT 1");
        if ($stmtResolve) {
          $stmtResolve->bind_param('ss', $vehicleRouteRef, $vehicleRouteRef);
          $stmtResolve->execute();
          $rowR = $stmtResolve->get_result()->fetch_assoc();
          $stmtResolve->close();
          $rid = (int)($rowR['id'] ?? 0);
          if ($rid > 0) $routeDbIds[] = $rid;
        }
      }

      $routeDbIds = array_values(array_unique($routeDbIds));
      if ($routeDbIds) {
        $idList = implode(',', array_map('intval', $routeDbIds));
        $routeRefs = [];
        $resR = $db->query("SELECT route_id, route_code FROM routes WHERE id IN ($idList)");
        if ($resR) {
          while ($r = $resR->fetch_assoc()) {
            $rid = trim((string)($r['route_id'] ?? ''));
            $rcode = trim((string)($r['route_code'] ?? ''));
            if ($rid !== '') $routeRefs[] = $rid;
            if ($rcode !== '') $routeRefs[] = $rcode;
          }
        }
        $routeRefs = array_values(array_unique(array_filter($routeRefs, fn($x) => $x !== '')));
        if ($routeRefs) {
          $in = implode(',', array_map(function ($s) use ($db) {
            return "'" . $db->real_escape_string($s) . "'";
          }, $routeRefs));
          $okTerm = false;
          $stmtTr = $db->prepare("SELECT 1 FROM terminal_routes WHERE terminal_id=? AND route_id IN ($in) LIMIT 1");
          if ($stmtTr) {
            $stmtTr->bind_param('i', $slotTerminalId);
            $stmtTr->execute();
            $resTr = $stmtTr->get_result();
            $okTerm = (bool)($resTr && $resTr->fetch_row());
            $stmtTr->close();
          }
          if (!$okTerm) {
            throw new Exception('route_not_allowed_in_terminal');
          }
        } else {
          throw new Exception('operator_no_approved_routes');
        }
      } else {
        throw new Exception('operator_no_approved_routes');
      }
    }
  }

  if ($paidAt !== null) {
    $stmtP = $db->prepare("INSERT INTO parking_payments (vehicle_id, slot_id, amount, or_no, paid_at, exported_to_treasury, exported_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmtP) throw new Exception('db_prepare_failed');
    $stmtP->bind_param('iidssis', $vehicleId, $slotId, $amount, $orNo, $paidAt, $exportedToTreasury, $exportedAt);
  } else {
    $stmtP = $db->prepare("INSERT INTO parking_payments (vehicle_id, slot_id, amount, or_no, paid_at, exported_to_treasury, exported_at) VALUES (?, ?, ?, ?, NOW(), ?, ?)");
    if (!$stmtP) throw new Exception('db_prepare_failed');
    $stmtP->bind_param('iidsis', $vehicleId, $slotId, $amount, $orNo, $exportedToTreasury, $exportedAt);
  }
  if (!$stmtP->execute()) throw new Exception('insert_failed');
  $paymentId = (int)$stmtP->insert_id;
  $stmtP->close();

  $stmtU = $db->prepare("UPDATE parking_slots SET status='Occupied' WHERE slot_id=? AND (status IS NULL OR LOWER(status) <> 'occupied')");
  if (!$stmtU) throw new Exception('db_prepare_failed');
  $stmtU->bind_param('i', $slotId);
  $stmtU->execute();
  $affected = (int)$stmtU->affected_rows;
  $stmtU->close();
  if ($affected !== 1) throw new Exception('slot_not_free');

  $actorUserId = (int)($_SESSION['user_id'] ?? 0);
  $actorName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
  if ($actorName === '') $actorName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
  if ($actorName === '') $actorName = 'Admin';
  $timeIn = $paidAt !== null ? $paidAt : date('Y-m-d H:i:s');
  $stmtE = $db->prepare("INSERT INTO parking_slot_events (terminal_id, slot_id, vehicle_id, plate_number, payment_id, amount, or_no, time_in, occupied_by_user_id, occupied_by_name)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  if ($stmtE) {
    $stmtE->bind_param('iiisidssis', $slotTerminalId, $slotId, $vehicleId, $plate, $paymentId, $amount, $orNo, $timeIn, $actorUserId, $actorName);
    $stmtE->execute();
    $stmtE->close();
  }

  $db->commit();
  echo json_encode(['ok' => true, 'payment_id' => $paymentId, 'slot_id' => $slotId]);
} catch (Throwable $e) {
  $db->rollback();
  $err = (string)$e->getMessage();
  $clientErrors = ['slot_required', 'slot_not_found', 'slot_not_free', 'slot_terminal_mismatch', 'vehicle_restricted_to_assigned_terminals', 'route_not_allowed_in_terminal', 'operator_no_approved_routes'];
  if (in_array($err, $clientErrors, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $err]);
  } else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
  }
}
