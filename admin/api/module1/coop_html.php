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
if ($name === '') { echo '<div class="text-sm">Enter cooperative name.</div>'; exit; }
$stmt = $db->prepare("SELECT coop_name, address, chairperson_name, lgu_approval_number, created_at FROM coops WHERE coop_name=?");
$stmt->bind_param('s', $name);
$stmt->execute();
$coop = $stmt->get_result()->fetch_assoc();
if (!$coop) { echo '<div class="text-sm">Cooperative not found.</div>'; exit; }
echo '<div class="space-y-4">';
echo '<div class="flex items-center justify-between">';
echo '<h2 class="text-lg font-semibold">Cooperative '.htmlspecialchars($coop['coop_name']).'</h2>';
echo '<button type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-lg bg-teal-500 hover:bg-teal-600 text-white" onclick="if(window.__useCoopInForm){window.__useCoopInForm(\''.htmlspecialchars($coop['coop_name'], ENT_QUOTES).'\', \''.htmlspecialchars($coop['address'] ?? '', ENT_QUOTES).'\', \''.htmlspecialchars($coop['chairperson_name'] ?? '', ENT_QUOTES).'\', \''.htmlspecialchars($coop['lgu_approval_number'] ?? '', ENT_QUOTES).'\');}">Use in Register Cooperative</button>';
echo '</div>';
echo '<div class="p-4 border rounded dark:border-slate-700"><div class="text-sm space-y-1"><div><span class="font-semibold">Address:</span> '.htmlspecialchars($coop['address'] ?? '').'</div><div><span class="font-semibold">Chairperson:</span> '.htmlspecialchars($coop['chairperson_name'] ?? '').'</div><div><span class="font-semibold">LGU Approval No.:</span> '.htmlspecialchars($coop['lgu_approval_number'] ?? '').'</div><div><span class="font-semibold">Created:</span> '.htmlspecialchars($coop['created_at']).'</div></div></div>';
$stmtV = $db->prepare("SELECT plate_number, vehicle_type, status FROM vehicles WHERE coop_name=? ORDER BY created_at DESC LIMIT 10");
$stmtV->bind_param('s', $name);
$stmtV->execute();
$resV = $stmtV->get_result();
echo '<div class="p-4 border rounded dark:border-slate-700"><h3 class="text-md font-semibold mb-2">Member Vehicles</h3><div class="text-sm space-y-1">';
if ($resV->num_rows === 0) { echo '<div>No linked vehicles.</div>'; }
while ($v = $resV->fetch_assoc()) { echo '<div>'.htmlspecialchars($v['plate_number']).' • '.htmlspecialchars($v['vehicle_type']).' • '.htmlspecialchars($v['status']).'</div>'; }
echo '</div></div>';
echo '</div>';
?> 
