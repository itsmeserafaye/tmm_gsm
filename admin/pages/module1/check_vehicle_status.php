<?php
require_once __DIR__ . '/../../includes/db.php';
if (php_sapi_name() !== 'cli') {
  require_once __DIR__ . '/../../includes/auth.php';
  require_any_permission(['module1.view','module1.vehicles.write','module1.routes.write','module1.coops.write']);
}
$db = db();
$plate = $_GET['plate'] ?? '';
if ($plate === '' && php_sapi_name() === 'cli') {
  global $argv;
  foreach ($argv as $arg) {
    if (strpos($arg, 'plate=') === 0) { $plate = substr($arg, 6); break; }
  }
}
if ($plate === '') { echo "missing_plate"; exit; }
$res = $db->prepare("SELECT status, franchise_id FROM vehicles WHERE plate_number=?");
$res->bind_param('s', $plate);
$res->execute();
$row = $res->get_result()->fetch_assoc();
if (!$row) { echo "not_found"; exit; }
echo $row['status'] . "|" . ($row['franchise_id'] ?? '');
