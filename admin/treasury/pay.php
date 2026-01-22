<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/treasury.php';

require_any_permission(['module3.settle', 'module5.parking_fees', 'module5.manage_terminal']);

$db = db();
$kind = strtolower(trim((string)($_GET['kind'] ?? 'ticket')));
$transactionId = trim((string)($_GET['transaction_id'] ?? ($_GET['ticket_number'] ?? ($_GET['parking_transaction_id'] ?? ''))));

$payload = tmm_treasury_build_digital_payload($db, $kind, $transactionId);
if (!($payload['ok'] ?? false)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'Unable to start Treasury payment: ' . (string)($payload['error'] ?? 'unknown_error');
  exit;
}

$digitalUrl = tmm_treasury_digital_url();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Treasury Payment</title>
</head>
<body>
  <form id="treasuryPayForm" method="POST" action="<?php echo htmlspecialchars($digitalUrl); ?>">
    <input type="hidden" name="system" value="<?php echo htmlspecialchars((string)$payload['system']); ?>">
    <input type="hidden" name="ref" value="<?php echo htmlspecialchars((string)$payload['ref']); ?>">
    <input type="hidden" name="amount" value="<?php echo htmlspecialchars((string)$payload['amount']); ?>">
    <input type="hidden" name="purpose" value="<?php echo htmlspecialchars((string)$payload['purpose']); ?>">
    <input type="hidden" name="callback" value="<?php echo htmlspecialchars((string)$payload['callback']); ?>">
    <noscript>
      <button type="submit">Continue to Treasury</button>
    </noscript>
  </form>
  <script>
    document.getElementById('treasuryPayForm').submit();
  </script>
</body>
</html>
