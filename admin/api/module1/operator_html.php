<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
$name = trim($_GET['name'] ?? '');
header('Content-Type: text/html; charset=utf-8');
require_login();
if (!has_any_permission(['module1.view','module1.vehicles.write','module1.coops.write','module2.view','module2.franchises.manage'])) {
  http_response_code(403);
  echo '<div class="text-sm">Forbidden.</div>';
  exit;
}
if ($name === '') { echo '<div class="text-sm">Enter operator full name.</div>'; exit; }
$stmt = $db->prepare("SELECT id, full_name, name, operator_type, address, contact_no, email, status, created_at FROM operators WHERE full_name=? OR name=? LIMIT 1");
$stmt->bind_param('ss', $name, $name);
$stmt->execute();
$op = $stmt->get_result()->fetch_assoc();
if (!$op) {
  $stmt2 = $db->prepare("SELECT operator_name, coop_name, MIN(created_at) AS first_seen FROM vehicles WHERE operator_name=? GROUP BY operator_name, coop_name ORDER BY first_seen ASC LIMIT 1");
  $stmt2->bind_param('s', $name);
  $stmt2->execute();
  $veh = $stmt2->get_result()->fetch_assoc();
  if ($veh) {
    $op = [
      'full_name' => $veh['operator_name'],
      'contact_info' => '',
      'coop_name' => $veh['coop_name'],
      'created_at' => $veh['first_seen'] ?? ''
    ];
  } else {
    echo '<div class="text-sm">Operator not found.</div>';
    exit;
  }
}
echo '<div class="space-y-4">';
echo '<div class="flex items-center justify-between">';
echo '<h2 class="text-lg font-semibold">Operator '.htmlspecialchars($op['name'] ?? $op['full_name']).'</h2>';
echo '<button type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-lg bg-teal-500 hover:bg-teal-600 text-white" onclick="if(window.__useOperatorInForm){window.__useOperatorInForm(\''.htmlspecialchars(($op['name'] ?? $op['full_name']), ENT_QUOTES).'\', \''.htmlspecialchars(($op['contact_no'] ?? ($op['contact_info'] ?? '')), ENT_QUOTES).'\', \'\');}">Use in Add New Operator</button>';
echo '</div>';
echo '<div class="p-4 border rounded dark:border-slate-700"><div class="text-sm space-y-1">';
echo '<div><span class="font-semibold">Type:</span> '.htmlspecialchars($op['operator_type'] ?? 'Individual').'</div>';
echo '<div><span class="font-semibold">Status:</span> '.htmlspecialchars($op['status'] ?? 'Approved').'</div>';
if (isset($op['address'])) echo '<div><span class="font-semibold">Address:</span> '.htmlspecialchars($op['address'] ?? '').'</div>';
if (isset($op['contact_no']) || isset($op['email'])) {
  $contactLine = trim((string)($op['contact_no'] ?? ''));
  if ($contactLine !== '' && ($op['email'] ?? '') !== '') $contactLine .= ' / ';
  $contactLine .= trim((string)($op['email'] ?? ''));
  echo '<div><span class="font-semibold">Contact:</span> '.htmlspecialchars($contactLine).'</div>';
} else {
  echo '<div><span class="font-semibold">Contact:</span> '.htmlspecialchars($op['contact_info'] ?? '').'</div>';
}
echo '<div><span class="font-semibold">Created:</span> '.htmlspecialchars($op['created_at'] ?? '').'</div>';
echo '</div></div>';

$resV = null;
if (isset($op['id']) && (int)$op['id'] > 0) {
  $oid = (int)$op['id'];
  $stmtV = $db->prepare("SELECT plate_number, vehicle_type, status FROM vehicles WHERE operator_id=? ORDER BY created_at DESC LIMIT 10");
  if ($stmtV) {
    $stmtV->bind_param('i', $oid);
    $stmtV->execute();
    $resV = $stmtV->get_result();
  }
}
if (!$resV) {
  $stmtV2 = $db->prepare("SELECT plate_number, vehicle_type, status FROM vehicles WHERE operator_name=? ORDER BY created_at DESC LIMIT 10");
  $stmtV2->bind_param('s', $name);
  $stmtV2->execute();
  $resV = $stmtV2->get_result();
}
echo '<div class="p-4 border rounded dark:border-slate-700"><h3 class="text-md font-semibold mb-2">Vehicles</h3><div class="text-sm space-y-1">';
if ($resV->num_rows === 0) { echo '<div>No linked vehicles.</div>'; }
while ($v = $resV->fetch_assoc()) { echo '<div>'.htmlspecialchars($v['plate_number']).' • '.htmlspecialchars($v['vehicle_type']).' • '.htmlspecialchars($v['status']).'</div>'; }
echo '</div></div>';
echo '</div>';
?> 
