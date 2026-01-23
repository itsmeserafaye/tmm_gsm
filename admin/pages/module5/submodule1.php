<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module5.manage_terminal');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$initialTab = trim((string)($_GET['tab'] ?? 'terminals'));
if (!in_array($initialTab, ['terminals','parking'], true)) $initialTab = 'terminals';

$statTerminals = (int)($db->query("SELECT COUNT(*) AS c FROM terminals WHERE type <> 'Parking'")->fetch_assoc()['c'] ?? 0);
$statAssignments = (int)($db->query("SELECT COUNT(*) AS c FROM terminal_assignments WHERE terminal_id IS NOT NULL")->fetch_assoc()['c'] ?? 0);
$statTerminalSlotsFree = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Free' AND t.type <> 'Parking'")->fetch_assoc()['c'] ?? 0);
$statTerminalSlotsOccupied = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Occupied' AND t.type <> 'Parking'")->fetch_assoc()['c'] ?? 0);
$statTerminalPaymentsToday = (int)($db->query("SELECT COUNT(*) AS c FROM parking_payments pp JOIN parking_slots ps ON ps.slot_id=pp.slot_id JOIN terminals t ON t.id=ps.terminal_id WHERE DATE(pp.paid_at)=CURDATE() AND t.type <> 'Parking'")->fetch_assoc()['c'] ?? 0);

$statParkingAreas = (int)($db->query("SELECT COUNT(*) AS c FROM terminals WHERE type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingSlotsFree = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Free' AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingSlotsOccupied = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Occupied' AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingPaymentsToday = (int)($db->query("SELECT COUNT(*) AS c FROM parking_payments pp JOIN parking_slots ps ON ps.slot_id=pp.slot_id JOIN terminals t ON t.id=ps.terminal_id WHERE DATE(pp.paid_at)=CURDATE() AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);

$hasRouteCode = false;
$colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes' AND COLUMN_NAME IN ('route_code','vehicle_type')");
if ($colRes) {
  while ($c = $colRes->fetch_assoc()) {
    $cn = (string)($c['COLUMN_NAME'] ?? '');
    if ($cn === 'route_code') $hasRouteCode = true;
  }
}
$routeLabelExpr = $hasRouteCode ? "COALESCE(NULLIF(r.route_code,''), r.route_id)" : "r.route_id";

$terminalRows = [];
$res = $db->query("SELECT
  t.id,
  t.name,
  t.location,
  t.capacity,
  COALESCE(GROUP_CONCAT(DISTINCT COALESCE(NULLIF(r.route_name,''), $routeLabelExpr) ORDER BY COALESCE(NULLIF(r.route_name,''), $routeLabelExpr) SEPARATOR ', '), '') AS routes_served
FROM terminals t
LEFT JOIN terminal_routes tr ON tr.terminal_id=t.id
LEFT JOIN routes r ON r.route_id=tr.route_id
WHERE t.type <> 'Parking'
GROUP BY t.id
ORDER BY t.name ASC
LIMIT 500");
if ($res) while ($r = $res->fetch_assoc()) $terminalRows[] = $r;

$parkingRows = [];
$resP = $db->query("SELECT id, name, location, capacity FROM terminals WHERE type='Parking' ORDER BY name ASC LIMIT 500");
if ($resP) while ($r = $resP->fetch_assoc()) $parkingRows[] = $r;

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Terminal & Parking List</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Create terminals and view assignments, slots, and payments.</p>
    </div>
    <div class="flex items-center gap-3">
      <?php if (has_permission('reports.export')): ?>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module5/export_terminals_csv.php"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="download" class="w-4 h-4"></i>
          Export CSV
        </a>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module5/export_terminals_csv.php?format=excel"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
          Export Excel
        </a>
      <?php endif; ?>
      <a href="?page=module5/submodule2" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="link" class="w-4 h-4"></i>
        Assign Vehicle
      </a>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 pt-5 pb-0">
      <div class="flex items-center gap-6 border-b border-slate-200 dark:border-slate-700">
        <button type="button" id="tabBtnTerminals" role="tab" aria-selected="true" class="py-3 text-sm font-black uppercase tracking-widest border-b-2 border-blue-700 text-blue-700">
          Terminals
        </button>
        <button type="button" id="tabBtnParking" role="tab" aria-selected="false" class="py-3 text-sm font-black uppercase tracking-widest border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-200">
          Parking
        </button>
      </div>
    </div>

    <div id="tabPanelTerminals" role="tabpanel" class="p-6 space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Terminals</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statTerminals; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Assignments</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statAssignments; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Free Slots</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statTerminalSlotsFree; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Occupied Slots</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statTerminalSlotsOccupied; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Payments Today</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statTerminalPaymentsToday; ?></div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
          <div class="flex items-center gap-3">
            <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
              <i data-lucide="plus" class="w-5 h-5"></i>
            </div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Create Terminal</h2>
          </div>
        </div>
        <div class="p-6">
          <form id="formTerminal" class="grid grid-cols-1 md:grid-cols-12 gap-4" novalidate>
            <input type="hidden" name="type" value="Terminal">
            <div class="md:col-span-3">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Name</label>
              <input name="name" required minlength="3" maxlength="80" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Central Terminal">
            </div>
            <div class="md:col-span-5">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Location</label>
              <input name="location" required maxlength="120" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., City, Province">
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Address</label>
              <input name="address" maxlength="180" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Brgy. 1, Main Rd.">
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Capacity</label>
              <input name="capacity" type="number" min="0" max="5000" step="1" value="0" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 50">
            </div>
            <div class="md:col-span-12 flex items-center justify-end gap-2">
              <button id="btnSaveTerminal" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
            </div>
          </form>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
          <div class="relative max-w-sm group">
            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
            <input id="terminalSearchTerm" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-white dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder:text-slate-400" placeholder="Search terminal name or location...">
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
              <tr class="text-left text-slate-500 dark:text-slate-400">
                <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Name</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Location</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden lg:table-cell">Routes</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Capacity</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800" id="termBodyTerminals">
              <?php if ($terminalRows): ?>
                <?php foreach ($terminalRows as $t): ?>
                  <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="py-4 px-6 font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($t['name'] ?? '')); ?></td>
                    <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars((string)($t['location'] ?? '')); ?></td>
                    <td class="py-4 px-4 hidden lg:table-cell text-xs text-slate-600 dark:text-slate-300 font-semibold max-w-md">
                      <?php echo htmlspecialchars((string)($t['routes_served'] ?? '') ?: '-'); ?>
                    </td>
                    <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold"><?php echo (int)($t['capacity'] ?? 0); ?></td>
                    <td class="py-4 px-4 text-right">
                      <a title="Slots" aria-label="Slots" href="?page=module5/submodule4&<?php echo http_build_query(['terminal_id'=>(int)($t['id'] ?? 0),'tab'=>'slots']); ?>" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors mr-2">
                        <i data-lucide="layout-grid" class="w-4 h-4"></i>
                        <span class="sr-only">Slots</span>
                      </a>
                      <a title="Payments" aria-label="Payments" href="?page=module5/submodule4&<?php echo http_build_query(['terminal_id'=>(int)($t['id'] ?? 0),'tab'=>'payments']); ?>" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors mr-2">
                        <i data-lucide="credit-card" class="w-4 h-4"></i>
                        <span class="sr-only">Payments</span>
                      </a>
                      <a title="Assign" aria-label="Assign" href="?page=module5/submodule2&terminal_id=<?php echo (int)($t['id'] ?? 0); ?>" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                        <i data-lucide="link" class="w-4 h-4"></i>
                        <span class="sr-only">Assign</span>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="5" class="py-12 text-center text-slate-500 font-medium italic">No terminals yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="tabPanelParking" role="tabpanel" class="p-6 space-y-6 hidden">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Parking Areas</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingAreas; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Free Slots</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingSlotsFree; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Occupied Slots</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingSlotsOccupied; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Payments Today</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingPaymentsToday; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Slots</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo ($statParkingSlotsFree + $statParkingSlotsOccupied); ?></div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
          <div class="flex items-center gap-3">
            <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
              <i data-lucide="plus" class="w-5 h-5"></i>
            </div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Create Parking</h2>
          </div>
        </div>
        <div class="p-6">
          <form id="formParking" class="grid grid-cols-1 md:grid-cols-12 gap-4" novalidate>
            <input type="hidden" name="type" value="Parking">
            <div class="md:col-span-3">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Name</label>
              <input name="name" required minlength="3" maxlength="80" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., MCU Parking">
            </div>
            <div class="md:col-span-5">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Location</label>
              <input name="location" required maxlength="120" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Caloocan City">
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Address</label>
              <input name="address" maxlength="180" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., EDSA, Monumento">
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Capacity</label>
              <input name="capacity" type="number" min="0" max="5000" step="1" value="0" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 50">
            </div>
            <div class="md:col-span-12 flex items-center justify-end gap-2">
              <button id="btnSaveParking" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
            </div>
          </form>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
          <div class="relative max-w-sm group">
            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
            <input id="terminalSearchParking" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-white dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder:text-slate-400" placeholder="Search parking name or location...">
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
              <tr class="text-left text-slate-500 dark:text-slate-400">
                <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Name</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Location</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Capacity</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800" id="termBodyParking">
              <?php if ($parkingRows): ?>
                <?php foreach ($parkingRows as $t): ?>
                  <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="py-4 px-6 font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($t['name'] ?? '')); ?></td>
                    <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars((string)($t['location'] ?? '')); ?></td>
                    <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold"><?php echo (int)($t['capacity'] ?? 0); ?></td>
                    <td class="py-4 px-4 text-right">
                      <a title="Slots" aria-label="Slots" href="?page=module5/submodule3&<?php echo http_build_query(['terminal_id'=>(int)($t['id'] ?? 0),'tab'=>'slots']); ?>" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors mr-2">
                        <i data-lucide="layout-grid" class="w-4 h-4"></i>
                        <span class="sr-only">Slots</span>
                      </a>
                      <a title="Payments" aria-label="Payments" href="?page=module5/submodule3&<?php echo http_build_query(['terminal_id'=>(int)($t['id'] ?? 0),'tab'=>'payments']); ?>" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                        <i data-lucide="credit-card" class="w-4 h-4"></i>
                        <span class="sr-only">Payments</span>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="py-12 text-center text-slate-500 font-medium italic">No parking areas yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const initialTab = <?php echo json_encode($initialTab); ?>;

    const tabBtnTerminals = document.getElementById('tabBtnTerminals');
    const tabBtnParking = document.getElementById('tabBtnParking');
    const panelTerminals = document.getElementById('tabPanelTerminals');
    const panelParking = document.getElementById('tabPanelParking');

    const formTerminal = document.getElementById('formTerminal');
    const btnSaveTerminal = document.getElementById('btnSaveTerminal');
    const formParking = document.getElementById('formParking');
    const btnSaveParking = document.getElementById('btnSaveParking');

    const searchTerm = document.getElementById('terminalSearchTerm');
    const tbodyTerm = document.getElementById('termBodyTerminals');
    const searchParking = document.getElementById('terminalSearchParking');
    const tbodyParking = document.getElementById('termBodyParking');

    function showToast(message, type) {
      const container = document.getElementById('toast-container');
      if (!container) return;
      const t = (type || 'success').toString();
      const color = t === 'error' ? 'bg-rose-600' : 'bg-emerald-600';
      const el = document.createElement('div');
      el.className = `pointer-events-auto px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold ${color}`;
      el.textContent = message;
      container.appendChild(el);
      setTimeout(() => { el.classList.add('opacity-0'); el.style.transition = 'opacity 250ms'; }, 2600);
      setTimeout(() => { el.remove(); }, 3000);
    }

    function setActiveTab(tab) {
      const isTerm = tab === 'terminals';
      if (panelTerminals) panelTerminals.classList.toggle('hidden', !isTerm);
      if (panelParking) panelParking.classList.toggle('hidden', isTerm);
      if (tabBtnTerminals) {
        tabBtnTerminals.setAttribute('aria-selected', isTerm ? 'true' : 'false');
        tabBtnTerminals.className = isTerm
          ? 'py-3 text-sm font-black uppercase tracking-widest border-b-2 border-blue-700 text-blue-700'
          : 'py-3 text-sm font-black uppercase tracking-widest border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-200';
      }
      if (tabBtnParking) {
        tabBtnParking.setAttribute('aria-selected', isTerm ? 'false' : 'true');
        tabBtnParking.className = !isTerm
          ? 'py-3 text-sm font-black uppercase tracking-widest border-b-2 border-blue-700 text-blue-700'
          : 'py-3 text-sm font-black uppercase tracking-widest border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-200';
      }
      try { localStorage.setItem('module5_list_tab', tab); } catch (e) {}
    }

    if (tabBtnTerminals) tabBtnTerminals.addEventListener('click', () => setActiveTab('terminals'));
    if (tabBtnParking) tabBtnParking.addEventListener('click', () => setActiveTab('parking'));

    let saved = '';
    try { saved = localStorage.getItem('module5_list_tab') || ''; } catch (e) {}
    setActiveTab(saved === 'parking' || initialTab === 'parking' ? 'parking' : 'terminals');

    async function saveTerminal(formEl, btnEl) {
      if (!formEl || !btnEl) return;
      btnEl.disabled = true;
      btnEl.textContent = 'Saving...';
      try {
        const res = await fetch(rootUrl + '/admin/api/module5/save_terminal.php', { method: 'POST', body: new FormData(formEl) });
        const data = await res.json();
        if (!data || !data.ok) throw new Error((data && data.message) ? data.message : 'save_failed');
        showToast('Saved.');
        setTimeout(() => { window.location.reload(); }, 400);
      } catch (err) {
        showToast(err.message || 'Failed', 'error');
        btnEl.disabled = false;
        btnEl.textContent = 'Save';
      }
    }

    if (formTerminal && btnSaveTerminal) {
      formTerminal.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!formTerminal.checkValidity()) { formTerminal.reportValidity(); return; }
        await saveTerminal(formTerminal, btnSaveTerminal);
      });
    }

    if (formParking && btnSaveParking) {
      formParking.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!formParking.checkValidity()) { formParking.reportValidity(); return; }
        await saveTerminal(formParking, btnSaveParking);
      });
    }

    function filterRows(searchEl, tbodyEl) {
      if (!searchEl || !tbodyEl) return;
      const q = (searchEl.value || '').trim().toLowerCase();
      const rows = tbodyEl.querySelectorAll('tr');
      rows.forEach(function (tr) {
        const tds = tr.querySelectorAll('td');
        if (!tds || tds.length < 2) return;
        const name = (tds[0].textContent || '').toLowerCase();
        const loc = (tds[1].textContent || '').toLowerCase();
        const ok = q === '' || name.includes(q) || loc.includes(q);
        tr.style.display = ok ? '' : 'none';
      });
    }
    if (searchTerm) searchTerm.addEventListener('input', () => filterRows(searchTerm, tbodyTerm));
    if (searchParking) searchParking.addEventListener('input', () => filterRows(searchParking, tbodyParking));
  })();
</script>
