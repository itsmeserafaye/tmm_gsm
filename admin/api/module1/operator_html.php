<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$name = trim($_GET['name'] ?? '');
header('Content-Type: text/html; charset=utf-8');
if ($name === '') { echo '<div class="text-sm">Enter operator full name.</div>'; exit; }
$stmt = $db->prepare("SELECT full_name, contact_info, coop_name, created_at FROM operators WHERE full_name=?");
$stmt->bind_param('s', $name);
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
echo '<h2 class="text-lg font-semibold">Operator '.htmlspecialchars($op['full_name']).'</h2>';
echo '<button type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-lg bg-teal-500 hover:bg-teal-600 text-white" onclick="if(window.__useOperatorInForm){window.__useOperatorInForm(\''.htmlspecialchars($op['full_name'], ENT_QUOTES).'\', \''.htmlspecialchars($op['contact_info'] ?? '', ENT_QUOTES).'\', \''.htmlspecialchars($op['coop_name'] ?? '', ENT_QUOTES).'\');}">Use in Add New Operator</button>';
echo '</div>';
echo '<div class="p-4 border rounded dark:border-slate-700"><div class="text-sm space-y-1"><div><span class="font-semibold">Contact:</span> '.htmlspecialchars($op['contact_info'] ?? '').'</div><div><span class="font-semibold">Cooperative:</span> '.htmlspecialchars($op['coop_name'] ?? '').'</div><div><span class="font-semibold">Created:</span> '.htmlspecialchars($op['created_at']).'</div></div></div>';
$stmtV = $db->prepare("SELECT plate_number, vehicle_type, status FROM vehicles WHERE operator_name=? ORDER BY created_at DESC LIMIT 10");
$stmtV->bind_param('s', $name);
$stmtV->execute();
$resV = $stmtV->get_result();
echo '<div class="p-4 border rounded dark:border-slate-700"><h3 class="text-md font-semibold mb-2">Vehicles</h3><div class="text-sm space-y-1">';
if ($resV->num_rows === 0) { echo '<div>No linked vehicles.</div>'; }
while ($v = $resV->fetch_assoc()) { echo '<div>'.htmlspecialchars($v['plate_number']).' • '.htmlspecialchars($v['vehicle_type']).' • '.htmlspecialchars($v['status']).'</div>'; }
echo '</div></div>';
echo '</div>';
?> 
