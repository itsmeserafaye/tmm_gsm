<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

function spo_send(bool $ok, array $payload = [], int $code = 200): never {
  if ($code !== 200) http_response_code($code);
  echo json_encode(array_merge(['ok' => $ok], $payload));
  exit;
}

try {
  $db = db();
  require_role(['SuperAdmin']);

  $stmt = $db->prepare("SELECT id, email, full_name, contact_info, association_name, operator_type, address, approval_status, status, puv_operator_id, approved_at, approved_by, created_at
                        FROM operator_portal_users
                        WHERE approval_status='Approved' AND status='Active'");
  if (!$stmt) spo_send(false, ['error' => 'db_prepare_failed'], 500);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
  $stmt->close();

  $processed = 0;
  $created = 0;
  $updated = 0;
  $linked = 0;
  $skipped = 0;
  $failed = 0;

  foreach ($rows as $user) {
    $processed++;
    $portalUserId = (int)($user['id'] ?? 0);
    if ($portalUserId <= 0) {
      $skipped++;
      continue;
    }

    $approvalStatus = (string)($user['approval_status'] ?? '');
    $status = (string)($user['status'] ?? '');
    if ($approvalStatus !== 'Approved' || $status !== 'Active') {
      $skipped++;
      continue;
    }

    $operatorId = (int)($user['puv_operator_id'] ?? 0);

    try {
      $db->begin_transaction();

      if ($operatorId > 0) {
        $stmtCheck = $db->prepare("SELECT id FROM operators WHERE id=? LIMIT 1");
        if ($stmtCheck) {
          $stmtCheck->bind_param('i', $operatorId);
          $stmtCheck->execute();
          $rowOp = $stmtCheck->get_result()->fetch_assoc();
          $stmtCheck->close();
          if (!$rowOp) {
            $operatorId = 0;
          }
        } else {
          $operatorId = 0;
        }
      }

      $email = strtolower(trim((string)($user['email'] ?? '')));
      $fullName = trim((string)($user['full_name'] ?? ''));
      $contactInfo = trim((string)($user['contact_info'] ?? ''));
      $association = trim((string)($user['association_name'] ?? ''));
      $portalAddress = trim((string)($user['address'] ?? ''));
      $createdAt = trim((string)($user['created_at'] ?? ''));

      $operatorTypePortal = (string)($user['operator_type'] ?? 'Individual');
      $operatorType = $operatorTypePortal;
      if ($operatorType === 'Coop') $operatorType = 'Cooperative';
      else if ($operatorType === 'Corp') $operatorType = 'Corporation';
      if ($operatorType === '') $operatorType = 'Individual';

      $registeredName = $association !== '' ? $association : $fullName;
      $name = $registeredName !== '' ? $registeredName : $fullName;
      $displayName = $name !== '' ? $name : $fullName;
      if ($displayName === '') $displayName = 'Operator';

      $coopName = ($operatorType === 'Individual') ? null : ($association !== '' ? $association : null);
      $contactNo = $contactInfo;
      $addressStreet = '';
      $submittedByName = $fullName !== '' ? $fullName : ($association !== '' ? $association : 'Operator');
      $submittedAt = $createdAt !== '' ? $createdAt : date('Y-m-d H:i:s');

      if ($addressStreet === '' && $portalAddress !== '') $addressStreet = $portalAddress;

      $addrStreet = null;
      $addrBarangay = null;
      $addrCity = null;
      $addrProvince = null;
      $addrPostal = null;

      $line = trim((string)$addressStreet);
      if ($line !== '') {
        $parts = array_map('trim', explode(',', $line));
        if (isset($parts[0]) && $parts[0] !== '') $addrStreet = $parts[0];
        if (isset($parts[1]) && $parts[1] !== '') $addrBarangay = $parts[1];
        if (isset($parts[2]) && $parts[2] !== '') $addrCity = $parts[2];
        if (isset($parts[3]) && $parts[3] !== '') $addrProvince = $parts[3];
        if (isset($parts[4]) && $parts[4] !== '') {
          $candidate = $parts[4];
          if (preg_match('/(\d{4,5})$/', $candidate, $m)) {
            $addrPostal = $m[1];
          } else {
            $addrPostal = $candidate !== '' ? $candidate : null;
          }
        } else {
          if (preg_match('/(\d{4,5})$/', $line, $m)) {
            $addrPostal = $m[1];
          }
        }
        $addressStreet = $addrStreet ?? $line;
      }

      if ($operatorId <= 0 && $email !== '') {
        $stmtFindEmail = $db->prepare("SELECT id FROM operators WHERE email=? ORDER BY id DESC LIMIT 1");
        if ($stmtFindEmail) {
          $stmtFindEmail->bind_param('s', $email);
          $stmtFindEmail->execute();
          $rowEmail = $stmtFindEmail->get_result()->fetch_assoc();
          $stmtFindEmail->close();
          if ($rowEmail) $operatorId = (int)($rowEmail['id'] ?? 0);
        }
      }

      $now = date('Y-m-d H:i:s');
      $approvedByUserId = (int)($user['approved_by'] ?? 0);
      $approvedAt = trim((string)($user['approved_at'] ?? ''));
      if ($approvedAt === '') $approvedAt = $now;

      $approvedByName = 'System Backfill';
      if ($approvedByUserId > 0) {
        $stmtAdmin = $db->prepare("SELECT first_name, last_name, email FROM rbac_users WHERE id=? LIMIT 1");
        if ($stmtAdmin) {
          $stmtAdmin->bind_param('i', $approvedByUserId);
          $stmtAdmin->execute();
          $rowAdmin = $stmtAdmin->get_result()->fetch_assoc();
          $stmtAdmin->close();
          if ($rowAdmin) {
            $fn = trim((string)($rowAdmin['first_name'] ?? ''));
            $ln = trim((string)($rowAdmin['last_name'] ?? ''));
            $nm = trim($fn . ' ' . $ln);
            if ($nm !== '') $approvedByName = $nm;
            else {
              $em = trim((string)($rowAdmin['email'] ?? ''));
              if ($em !== '') $approvedByName = $em;
            }
          }
        }
      }

      if ($operatorId > 0) {
        $stmtOp = $db->prepare("UPDATE operators
                                SET operator_type=?, registered_name=?, name=?, full_name=?, address_street=?, address_barangay=?, address_city=?, address_province=?, address_postal_code=?, contact_no=?, email=?, coop_name=?,
                                    portal_user_id=?, approved_by_user_id=?, approved_by_name=?, approved_at=?,
                                    verification_status='Verified', workflow_status='Active'
                                WHERE id=?");
        if (!$stmtOp) throw new Exception('db_prepare_failed');
        $stmtOp->bind_param(
          'ssssssssssssssiii',
          $operatorType, $registeredName, $name, $displayName,
          $addressStreet, $addrBarangay, $addrCity, $addrProvince, $addrPostal,
          $contactNo, $email, $coopName,
          $portalUserId, $approvedByUserId, $approvedByName, $approvedAt,
          $operatorId
        );
        $stmtOp->execute();
        $stmtOp->close();
        $updated++;
      } else {
        $stmtIns = $db->prepare("INSERT INTO operators (operator_type, registered_name, name, full_name, address_street, address_barangay, address_city, address_province, address_postal_code, contact_no, email, coop_name, status, verification_status, workflow_status,
                                                       portal_user_id, submitted_by_name, submitted_at, approved_by_user_id, approved_by_name, approved_at, created_at)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved', 'Verified', 'Active', ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmtIns) throw new Exception('db_prepare_failed');
        $stmtIns->bind_param(
          'ssssssssssssississ',
          $operatorType, $registeredName, $name, $displayName,
          $addressStreet, $addrBarangay, $addrCity, $addrProvince, $addrPostal,
          $contactNo, $email, $coopName,
          $portalUserId, $submittedByName, $submittedAt,
          $approvedByUserId, $approvedByName, $approvedAt
        );
        $stmtIns->execute();
        $operatorId = (int)$db->insert_id;
        $stmtIns->close();
        if ($operatorId > 0) $created++;
      }

      if ($operatorId > 0) {
        $stmtLink = $db->prepare("UPDATE operator_portal_users SET puv_operator_id=? WHERE id=?");
        if ($stmtLink) {
          $stmtLink->bind_param('ii', $operatorId, $portalUserId);
          $stmtLink->execute();
          $stmtLink->close();
          $linked++;
        }
      } else {
        $skipped++;
      }

      $db->commit();
    } catch (Throwable $e) {
      $failed++;
      if ($db->errno) {
        try { $db->rollback(); } catch (Throwable $e2) {}
      }
    }
  }

  spo_send(true, [
    'stats' => [
      'processed' => $processed,
      'created' => $created,
      'updated' => $updated,
      'linked' => $linked,
      'skipped' => $skipped,
      'failed' => $failed,
    ],
  ]);
} catch (Throwable $e) {
  spo_send(false, ['error' => 'server_error'], 500);
}

