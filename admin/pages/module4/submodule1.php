<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module4.read');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$statRegistered = (int)($db->query("SELECT COUNT(*) AS c FROM vehicle_registrations WHERE registration_status='Registered'")->fetch_assoc()['c'] ?? 0);
$statPending = (int)($db->query("SELECT COUNT(*) AS c FROM vehicle_registrations WHERE registration_status='Pending'")->fetch_assoc()['c'] ?? 0);
$statExpired = (int)($db->query("SELECT COUNT(*) AS c FROM vehicle_registrations WHERE registration_status='Expired'")->fetch_assoc()['c'] ?? 0);
$statActiveOps = (int)($db->query("SELECT COUNT(*) AS c FROM vehicles WHERE status='Active' AND COALESCE(record_status,'') <> 'Archived'")->fetch_assoc()['c'] ?? 0);
$statBlockedOps = (int)($db->query("SELECT COUNT(*) AS c FROM vehicles WHERE status='Blocked' AND COALESCE(record_status,'') <> 'Archived'")->fetch_assoc()['c'] ?? 0);

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};
$vrHasOrNo = $hasCol('vehicle_registrations', 'or_number');
$vrHasOrDate = $hasCol('vehicle_registrations', 'or_date');
$vrHasOrExp = $hasCol('vehicle_registrations', 'or_expiry_date');
$vrHasRegYear = $hasCol('vehicle_registrations', 'registration_year');

$orNoSel = $vrHasOrNo ? "COALESCE(NULLIF(vr.or_number,''), vr.orcr_no) AS or_number" : "vr.orcr_no AS or_number";
$orDateSel = $vrHasOrDate ? "COALESCE(NULLIF(vr.or_date,''), vr.orcr_date) AS or_date" : "vr.orcr_date AS or_date";
$orExpSel = $vrHasOrExp ? "vr.or_expiry_date AS or_expiry_date" : "'' AS or_expiry_date";
$regYearSel = $vrHasRegYear ? "vr.registration_year AS registration_year" : "'' AS registration_year";

$sql = "SELECT v.id AS vehicle_id, v.plate_number, v.operator_id, v.status AS vehicle_status,
               vr.registration_status, {$orNoSel}, {$orDateSel}, {$orExpSel}, {$regYearSel}, vr.created_at
        FROM vehicles v
        LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id";
$conds = [];
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $conds[] = "(v.plate_number LIKE '%$qv%' OR v.engine_no LIKE '%$qv%' OR v.chassis_no LIKE '%$qv%')";
}
if ($status !== '' && in_array($status, ['Registered','Pending','Expired'], true)) {
  $sv = $db->real_escape_string($status);
  $conds[] = "vr.registration_status='$sv'";
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY COALESCE(vr.created_at, v.created_at) DESC LIMIT 400";
$res = $db->query($sql);
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Vehicle Registration List</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Search by plate and filter by registration status.</p>
    </div>
    <div class="flex items-center gap-3">
      <?php if (has_permission('reports.export')): ?>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module4/export_registrations_csv.php?<?php echo http_build_query(['q'=>$q,'status'=>$status]); ?>"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="download" class="w-4 h-4"></i>
          Export CSV
        </a>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module4/export_registrations_csv.php?<?php echo http_build_query(['q'=>$q,'status'=>$status,'format'=>'excel']); ?>"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
          Export Excel
        </a>
      <?php endif; ?>
      <a href="?page=module4/submodule2" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="file-plus" class="w-4 h-4"></i>
        Register Vehicle
      </a>
      <a href="?page=module4/submodule3" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="calendar-plus" class="w-4 h-4"></i>
        Schedule Inspection
      </a>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Registered</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statRegistered; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statPending; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Expired</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statExpired; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Operating (Active)</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statActiveOps; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Blocked</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statBlockedOps; ?></div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <form class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between" method="GET">
      <input type="hidden" name="page" value="module4/submodule1">
      <div class="flex-1 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1 sm:max-w-sm group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder:text-slate-400" placeholder="Search plate...">
        </div>
        <div class="relative w-full sm:w-60">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Registration Status</option>
            <?php foreach (['Registered','Pending','Expired'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button class="inline-flex items-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="filter" class="w-4 h-4"></i>
          Apply
        </button>
        <a href="?page=module4/submodule1" class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          Reset
        </a>
      </div>
    </form>
    <div class="mt-4 flex flex-wrap items-center gap-2">
    <?php foreach (['Registered','Pending','Expired'] as $chip): ?>
        <a href="?<?php echo http_build_query(['page'=>'module4/submodule1','q'=>$q,'status'=>$chip]); ?>"
          class="<?php echo $status === $chip ? 'bg-slate-900 text-white border-slate-900 dark:bg-slate-100 dark:text-slate-900 dark:border-slate-100' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40'; ?> inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold border transition-colors">
          <?php echo htmlspecialchars($chip); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Plate</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">OR</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Registration</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Created</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
              <?php
                $reg = (string)($row['registration_status'] ?? '');
                $vehSt = (string)($row['vehicle_status'] ?? '');
                $badge = match($reg) {
                  'Registered', 'Recorded' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  'Expired' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  'Pending' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                  default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                };
                $vehBadge = match($vehSt) {
                  'Active' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  'Inactive' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                  'Blocked' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                };
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-6">
                  <div class="font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($row['plate_number'] ?? '')); ?></div>
                  <?php if ($vehSt === 'Blocked'): ?>
                    <div class="mt-1 inline-flex items-center gap-2 px-2.5 py-1 rounded-lg text-[11px] font-black bg-rose-50 text-rose-700 border border-rose-200">
                      <i data-lucide="octagon-alert" class="w-4 h-4"></i>
                      Operation blocked (OR expired)
                    </div>
                  <?php elseif ($vehSt === 'Inactive'): ?>
                    <div class="mt-1 inline-flex items-center gap-2 px-2.5 py-1 rounded-lg text-[11px] font-black bg-amber-50 text-amber-800 border border-amber-200">
                      <i data-lucide="triangle-alert" class="w-4 h-4"></i>
                      Inactive (missing OR)
                    </div>
                  <?php elseif ($vehSt !== ''): ?>
                    <div class="mt-1">
                      <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold ring-1 ring-inset <?php echo $vehBadge; ?>"><?php echo htmlspecialchars($vehSt); ?></span>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold">
                  <?php
                    $orNo = trim((string)($row['or_number'] ?? ''));
                    $od = (string)($row['or_date'] ?? '');
                    $oe = (string)($row['or_expiry_date'] ?? '');
                    $ry = trim((string)($row['registration_year'] ?? ''));
                    $parts = [];
                    if ($orNo !== '') $parts[] = $orNo;
                    if ($od !== '') $parts[] = $od;
                    if ($oe !== '') $parts[] = 'Exp: ' . $oe;
                    if ($ry !== '') $parts[] = 'Year: ' . $ry;
                    echo htmlspecialchars($parts ? implode(' • ', $parts) : '-');
                  ?>
                </td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($reg !== '' ? $reg : 'Not Registered'); ?></span>
                </td>
                <td class="py-4 px-4 hidden sm:table-cell text-xs text-slate-500 dark:text-slate-400 font-medium">
                  <?php echo htmlspecialchars(!empty($row['created_at']) ? date('M d, Y', strtotime((string)$row['created_at'])) : '-'); ?>
                </td>
                <td class="py-4 px-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <a href="?page=module4/submodule2&vehicle_id=<?php echo (int)($row['vehicle_id'] ?? 0); ?>" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" title="Register Vehicle">
                      <i data-lucide="file-plus" class="w-4 h-4"></i>
                    </a>
                    <a href="?page=module4/submodule3&vehicle_id=<?php echo (int)($row['vehicle_id'] ?? 0); ?>" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all" title="Schedule Inspection">
                      <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" class="py-12 text-center text-slate-500 font-medium italic">No vehicles found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
