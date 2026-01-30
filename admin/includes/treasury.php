<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';

function tmm_treasury_system_code(): string {
  $systemCode = trim((string)getenv('TMM_TREASURY_SYSTEM_CODE'));
  return $systemCode !== '' ? $systemCode : 'tmm';
}

function tmm_treasury_digital_url(): string {
  $url = trim((string)getenv('TMM_TREASURY_DIGITAL_URL'));
  if ($url === '') $url = 'https://revenuetreasury.goserveph.com/citizen_dashboard/digital/index.php';
  return $url;
}

function tmm_treasury_callback_url(): string {
  $base = tmm_public_base_url();
  $callback = $base !== '' ? ($base . '/admin/api/integration/treasury/digital_payment_callback.php') : '/admin/api/integration/treasury/digital_payment_callback.php';
  $key = (string)getenv('TMM_TREASURY_INTEGRATION_KEY');
  if ($key !== '') $callback .= (strpos($callback, '?') === false ? '?' : '&') . 'integration_key=' . rawurlencode($key);
  $token = (string)getenv('TMM_TREASURY_CALLBACK_TOKEN');
  if ($token !== '') $callback .= (strpos($callback, '?') === false ? '?' : '&') . 'token=' . rawurlencode($token);
  return $callback;
}

function tmm_treasury_upsert_pending_request(mysqli $db, string $ref, string $kind, string $transactionId, float $amount, string $purpose): void {
  if ($ref === '' || $kind === '' || $transactionId === '') return;
  $stmtSel = $db->prepare("SELECT id, status FROM treasury_payment_requests WHERE ref=? LIMIT 1");
  if (!$stmtSel) return;
  $stmtSel->bind_param('s', $ref);
  $stmtSel->execute();
  $row = $stmtSel->get_result()->fetch_assoc();
  $stmtSel->close();

  if ($row) {
    if ((string)($row['status'] ?? '') === 'paid') return;
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) return;
    $stmtUp = $db->prepare("UPDATE treasury_payment_requests SET kind=?, transaction_id=?, amount=?, purpose=?, status='pending' WHERE id=?");
    if (!$stmtUp) return;
    $stmtUp->bind_param('ssdsi', $kind, $transactionId, $amount, $purpose, $id);
    $stmtUp->execute();
    $stmtUp->close();
    return;
  }

  $stmtIns = $db->prepare("INSERT INTO treasury_payment_requests(ref, kind, transaction_id, amount, purpose, status) VALUES(?,?,?,?,?,'pending')");
  if (!$stmtIns) return;
  $stmtIns->bind_param('ssdds', $ref, $kind, $transactionId, $amount, $purpose);
  $stmtIns->execute();
  $stmtIns->close();
}

function tmm_treasury_build_digital_payload(mysqli $db, string $kind, string $transactionId): array {
  $kind = strtolower(trim($kind));
  $transactionId = trim($transactionId);
  if ($kind === '') $kind = 'ticket';
  if ($transactionId === '') return ['ok' => false, 'error' => 'missing_transaction_id'];

  if ($kind === 'ticket') {
    $stmt = $db->prepare("SELECT t.ticket_number, t.external_ticket_number, t.fine_amount, t.status, t.driver_name, t.vehicle_plate, vt.description AS violation_desc
                          FROM tickets t
                          LEFT JOIN violation_types vt ON vt.violation_code = t.violation_code
                          WHERE t.ticket_number=? OR t.external_ticket_number=? LIMIT 1");
    if (!$stmt) return ['ok' => false, 'error' => 'db_prepare_failed'];
    $stmt->bind_param('ss', $transactionId, $transactionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return ['ok' => false, 'error' => 'transaction_not_found'];

    $ref = (string)($row['ticket_number'] ?? '');
    if ($ref === '') $ref = $transactionId;
    $amount = (float)($row['fine_amount'] ?? 0);
    $desc = trim((string)($row['violation_desc'] ?? 'Traffic violation'));
    $payerName = trim((string)($row['driver_name'] ?? ''));
    $plate = trim((string)($row['vehicle_plate'] ?? ''));

    $purposeParts = [];
    $purposeParts[] = 'Traffic Fine';
    if ($desc !== '') $purposeParts[] = $desc;
    if ($ref !== '') $purposeParts[] = 'Ticket ' . $ref;
    if ($plate !== '') $purposeParts[] = $plate;
    if ($payerName !== '') $purposeParts[] = $payerName;
    $purpose = implode(': ', array_filter($purposeParts, fn($v) => $v !== ''));

    tmm_treasury_upsert_pending_request($db, $ref, 'ticket', $ref, $amount, $purpose);

    return [
      'ok' => true,
      'system' => tmm_treasury_system_code(),
      'ref' => $ref,
      'amount' => $amount,
      'purpose' => $purpose,
      'callback' => tmm_treasury_callback_url(),
    ];
  }

  if ($kind === 'parking') {
    $id = (int)$transactionId;
    if ($id <= 0) return ['ok' => false, 'error' => 'invalid_transaction_id'];

    $stmt = $db->prepare("SELECT t.id, t.amount, t.status, t.vehicle_plate, t.transaction_type, t.created_at, a.name AS area_name, term.name AS terminal_name
                          FROM parking_transactions t
                          LEFT JOIN parking_areas a ON t.parking_area_id = a.id
                          LEFT JOIN terminals term ON term.id = t.terminal_id
                          WHERE t.id=? LIMIT 1");
    if (!$stmt) return ['ok' => false, 'error' => 'db_prepare_failed'];
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return ['ok' => false, 'error' => 'transaction_not_found'];

    $amount = (float)($row['amount'] ?? 0);
    $plate = strtoupper(trim((string)($row['vehicle_plate'] ?? '')));
    $area = trim((string)($row['area_name'] ?? ''));
    $terminalName = trim((string)($row['terminal_name'] ?? ''));
    $type = trim((string)($row['transaction_type'] ?? 'Parking Fee'));
    $ref = 'PARK-' . (string)$id;

    $purposeParts = [];
    $purposeParts[] = $type !== '' ? $type : 'Parking Fee';
    if ($plate !== '') $purposeParts[] = $plate;
    if ($area !== '') $purposeParts[] = $area;
    if ($area === '' && $terminalName !== '') $purposeParts[] = $terminalName;
    $purposeParts[] = 'Tx ' . (string)$id;
    $purpose = implode(': ', array_filter($purposeParts, fn($v) => $v !== ''));

    tmm_treasury_upsert_pending_request($db, $ref, 'parking', (string)$id, $amount, $purpose);

    return [
      'ok' => true,
      'system' => tmm_treasury_system_code(),
      'ref' => $ref,
      'amount' => $amount,
      'purpose' => $purpose,
      'callback' => tmm_treasury_callback_url(),
    ];
  }

  return ['ok' => false, 'error' => 'unsupported_kind'];
}
