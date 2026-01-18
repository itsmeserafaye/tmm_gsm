<?php
  require_once __DIR__ . '/../../includes/auth.php';
  require_any_permission(['module1.view','module1.vehicles.write','module1.routes.write','module1.coops.write']);
  require_once __DIR__ . '/../../includes/db.php';
  $db = db();
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div>
    <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Operator & Franchise Validation</h1>
    <p class="mt-2 text-sm font-medium text-slate-500 dark:text-slate-400 max-w-3xl">Maintain operator profiles, cooperative registries, and validate franchise references against LGU records.</p>
  </div>

  <!-- Toast Notification Container -->
  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <!-- Summary Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Applications</div>
        <i data-lucide="file-text" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
      </div>
        <?php
          $resT = $db->query("SELECT COUNT(*) as c FROM franchise_applications WHERE COALESCE(operator_name,'') <> 'TEST_E2E_OP'");
          $total = $resT->fetch_assoc()['c'] ?? 0;
        ?>
        <h3 class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $total; ?></h3>
    </div>

    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Endorsed / Valid</div>
        <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
      </div>
        <?php
          $resE = $db->query("SELECT COUNT(*) as c FROM franchise_applications WHERE status='Endorsed' AND COALESCE(operator_name,'') <> 'TEST_E2E_OP'");
          $endorsed = $resE->fetch_assoc()['c'] ?? 0;
        ?>
        <h3 class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $endorsed; ?></h3>
    </div>

    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Review</div>
        <i data-lucide="clock" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
      </div>
        <?php
          $resP = $db->query("SELECT COUNT(*) as c FROM franchise_applications WHERE (status='Pending' OR status='Under Review') AND COALESCE(operator_name,'') <> 'TEST_E2E_OP'");
          $pending = $resP->fetch_assoc()['c'] ?? 0;
        ?>
        <h3 class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $pending; ?></h3>
    </div>
  </div>

  <!-- Registry Table -->
  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 space-y-4 bg-slate-50 dark:bg-slate-700/30">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center text-violet-600 dark:text-violet-400">
            <i data-lucide="folder-open" class="w-5 h-5"></i>
          </div>
          <h2 class="text-lg font-black text-slate-800 dark:text-white">Franchise Applications</h2>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto justify-end">
          <a href="?page=module2/submodule1" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-md shadow-sm transition-all active:scale-[0.98]">
            <i data-lucide="file-plus" class="w-4 h-4"></i>
            <span>New Application</span>
          </a>
          <form id="franchiseFilterForm" class="flex flex-col sm:flex-row sm:items-center gap-3 w-full md:w-auto" method="GET">
            <input type="hidden" name="page" value="module1/submodule2">
            <div class="relative flex-1 sm:w-64 group">
              <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
              <input name="q" list="franchiseSearchList" value="<?php echo htmlspecialchars($_GET['q']??''); ?>" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-white dark:bg-slate-900/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder:text-slate-400" placeholder="Search Ref or Operator...">
              <?php
                $searchOptions = [];
                $resSearch = $db->query("SELECT DISTINCT franchise_ref_number, operator_name FROM franchise_applications WHERE COALESCE(operator_name,'') <> 'TEST_E2E_OP' ORDER BY submitted_at DESC LIMIT 100");
                if ($resSearch) {
                  while ($r = $resSearch->fetch_assoc()) {
                    $ref = trim((string)($r['franchise_ref_number'] ?? ''));
                    $opn = trim((string)($r['operator_name'] ?? ''));
                    if ($ref !== '') $searchOptions[$ref] = true;
                    if ($opn !== '') $searchOptions[$opn] = true;
                  }
                }
              ?>
              <datalist id="franchiseSearchList">
                <?php foreach (array_keys($searchOptions) as $opt): ?>
                  <option value="<?php echo htmlspecialchars($opt); ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="relative w-full sm:w-44">
              <select name="status" class="px-4 py-2.5 pl-4 pr-10 text-sm font-semibold border-0 rounded-md bg-white dark:bg-slate-900/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all appearance-none cursor-pointer">
                <option value="">Status</option>
                <option value="Pending" <?php echo ($_GET['status']??'')==='Pending'?'selected':''; ?>>Pending</option>
                <option value="Endorsed" <?php echo ($_GET['status']??'')==='Endorsed'?'selected':''; ?>>Endorsed</option>
                <option value="Rejected" <?php echo ($_GET['status']??'')==='Rejected'?'selected':''; ?>>Rejected</option>
              </select>
              <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Reference #</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Operator</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden lg:table-cell">Cooperative</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Units</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Submitted</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Status</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php
            $q = trim($_GET['q'] ?? '');
            $st = trim($_GET['status'] ?? '');
            $sql = "SELECT fa.*, o.full_name as operator, c.coop_name 
                    FROM franchise_applications fa 
                    LEFT JOIN operators o ON fa.operator_id = o.id 
                    LEFT JOIN coops c ON fa.coop_id = c.id";
            $conds = []; $params = []; $types = '';
            $conds[] = "COALESCE(fa.operator_name,'') <> ?";
            $params[] = 'TEST_E2E_OP';
            $types .= 's';
            if ($q !== '') { $conds[] = "(fa.franchise_ref_number LIKE ? OR o.full_name LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; $types.='ss'; }
            if ($st !== '') { $conds[] = "fa.status = ?"; $params[]=$st; $types.='s'; }
            if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
            $sql .= " ORDER BY fa.submitted_at DESC LIMIT 50";
            
            if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); }
            else { $res = $db->query($sql); }
            
            if ($res->num_rows > 0):
            while ($row = $res->fetch_assoc()):
              $sBadge = match($row['status']) {
                'Endorsed' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                'Pending' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                'Rejected' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
              };
          ?>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group">
            <td class="py-4 px-6 font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($row['franchise_ref_number']); ?></td>
            <td class="py-4 px-4 text-slate-600 dark:text-slate-300 font-medium">
              <?php if (!empty($row['operator'])): ?>
                <button
                  type="button"
                  class="hover:text-violet-600 hover:underline transition-colors text-left"
                  data-op-name="<?php echo htmlspecialchars($row['operator']); ?>"
                >
                  <?php echo htmlspecialchars($row['operator']); ?>
                </button>
              <?php else: ?>
                <span class="text-slate-400">-</span>
              <?php endif; ?>
              <?php if (!empty($row['coop_name'] ?? '')): ?>
                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400 font-medium lg:hidden">
                  <?php echo htmlspecialchars((string)($row['coop_name'] ?? '')); ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="py-4 px-4 text-slate-600 dark:text-slate-300 font-medium hidden lg:table-cell">
              <?php $coopName = $row['coop_name'] ?? ''; ?>
              <?php if ($coopName): ?>
                <button
                  type="button"
                  class="hover:text-violet-600 hover:underline transition-colors text-left"
                  data-coop-name="<?php echo htmlspecialchars($coopName); ?>"
                >
                  <?php echo htmlspecialchars($coopName); ?>
                </button>
              <?php else: ?>
                <span class="text-slate-400">-</span>
              <?php endif; ?>
            </td>
            <td class="py-4 px-4 font-bold text-slate-700 dark:text-slate-300 hidden sm:table-cell"><?php echo (int)$row['vehicle_count']; ?></td>
            <td class="py-4 px-4 text-slate-500 font-medium text-xs hidden md:table-cell"><?php echo date('M d, Y', strtotime($row['submitted_at'])); ?></td>
            <td class="py-4 px-4"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $sBadge; ?>"><?php echo $row['status']; ?></span></td>
            <td class="py-4 px-4 text-right">
              <div class="flex items-center justify-end gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                <button
                  title="View Details"
                  class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all"
                  data-app-view="1"
                  data-ref="<?php echo htmlspecialchars($row['franchise_ref_number']); ?>"
                  data-status="<?php echo htmlspecialchars($row['status']); ?>"
                  data-operator="<?php echo htmlspecialchars($row['operator']); ?>"
                  data-coop="<?php echo htmlspecialchars($row['coop_name'] ?? '-'); ?>"
                  data-vehicles="<?php echo (int)$row['vehicle_count']; ?>"
                  data-submitted="<?php echo htmlspecialchars(date('M d, Y', strtotime($row['submitted_at']))); ?>"
                  data-type="<?php echo htmlspecialchars($row['application_type'] ?? ''); ?>"
                  data-notes="<?php echo htmlspecialchars($row['notes'] ?? ''); ?>"
                >
                  <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
                <a
                  href="?page=module1/submodule1&fr_ref=<?php echo urlencode($row['franchise_ref_number']); ?>&op=<?php echo urlencode($row['operator'] ?? ''); ?>"
                  class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all inline-flex items-center justify-center"
                  title="Register Vehicle"
                >
                  <i data-lucide="plus-circle" class="w-4 h-4"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="7" class="py-12 text-center text-slate-500 font-medium italic">No applications found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="p-5 sm:p-8 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <h3 class="text-lg font-bold mb-6 flex items-center gap-3 text-slate-900 dark:text-white">
        <div class="h-8 w-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
          <i data-lucide="shield-check" class="w-4 h-4"></i>
        </div>
        Franchise Lookup
      </h3>
      <div class="flex flex-col md:flex-row gap-3 mb-6 items-stretch md:items-center">
        <div class="relative flex-1 group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
          <input
            id="franchiseLookup"
            class="w-full pl-10 pr-4 py-3 text-sm font-semibold border border-slate-200 dark:border-slate-600 rounded-md bg-slate-50 dark:bg-slate-900/50 dark:text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all uppercase placeholder:normal-case"
            placeholder="Enter Franchise Ref # (e.g. 2024-00123)"
          >
        </div>
        <button
            id="franchiseLookupBtn"
            type="button"
            class="w-full md:w-auto px-6 py-3 text-sm font-bold rounded-md bg-blue-700 hover:bg-blue-800 text-white shadow-sm transition-all flex items-center justify-center gap-2 active:scale-[0.98]"
          >
          <span>Validate</span>
          <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
      </div>
      <div id="franchiseResult" class="min-h-[60px] flex items-center justify-center rounded-lg bg-slate-50 dark:bg-slate-900/50 border border-dashed border-slate-200 dark:border-slate-700">
        <span class="text-sm font-medium text-slate-400">Enter a Franchise Ref above to validate details.</span>
      </div>
    </div>

    <div class="p-5 sm:p-8 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <h3 class="text-lg font-bold mb-6 flex items-center gap-3 text-slate-900 dark:text-white">
        <div class="h-8 w-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
          <i data-lucide="users" class="w-4 h-4"></i>
        </div>
        Cooperative Status
      </h3>
      <a href="?page=module2/submodule1&open=coop" class="mb-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-600/20 hover:bg-emerald-700 active:scale-[0.99] transition-all">
        <i data-lucide="user-plus" class="w-4 h-4"></i>
        <span>Register Cooperative</span>
      </a>
      <div class="mb-4 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
        Consolidation is updated in Franchise Management after LGU verification. Status flow: Not Consolidated → In Progress → Consolidated.
      </div>
      <?php
        $hasConsCol = false;
        $chkCons = $db->query("SHOW COLUMNS FROM coops LIKE 'consolidation_status'");
        if ($chkCons && $chkCons->num_rows > 0) $hasConsCol = true;
        $coopsStatus = [];
        $resCoopsStatus = $db->query("SELECT coop_name, " . ($hasConsCol ? "consolidation_status" : "'' AS consolidation_status") . " FROM coops ORDER BY coop_name ASC LIMIT 300");
        if ($resCoopsStatus) {
          while ($r = $resCoopsStatus->fetch_assoc()) {
            $name = trim((string)($r['coop_name'] ?? ''));
            if ($name === '') continue;
            $coopsStatus[] = [
              'name' => $name,
              'status' => trim((string)($r['consolidation_status'] ?? 'Not Consolidated'))
            ];
          }
        }
      ?>
      <div class="overflow-y-auto max-h-[340px] pr-1">
        <?php if (!empty($coopsStatus)): ?>
          <div class="space-y-2">
            <?php foreach ($coopsStatus as $c): ?>
              <?php $currentStatus = $c['status'] !== '' ? $c['status'] : 'Not Consolidated'; ?>
              <div class="p-3 rounded-xl border border-slate-100 bg-white dark:bg-slate-900/30 dark:border-slate-700 hover:border-emerald-200 transition-all">
                <div class="flex items-start justify-between gap-3">
                  <div class="font-semibold text-sm text-slate-800 dark:text-white"><?php echo htmlspecialchars($c['name']); ?></div>
                  <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide
                    <?php
                      if ($currentStatus === 'Consolidated') echo 'bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-900/40';
                      elseif ($currentStatus === 'In Progress') echo 'bg-amber-50 text-amber-600 border border-amber-100 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-900/40';
                      else echo 'bg-slate-50 text-slate-500 border border-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
                    ?>
                  ">
                    <?php echo htmlspecialchars($currentStatus); ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-10 text-slate-400 text-sm font-medium">No cooperatives found.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="p-6 rounded-lg bg-orange-50/50 dark:bg-orange-900/10 border border-orange-100 dark:border-orange-900/30">
    <h3 class="text-sm font-bold mb-4 flex items-center gap-2 text-orange-800 dark:text-orange-200 uppercase tracking-widest">
      <i data-lucide="alert-circle" class="w-4 h-4"></i> Validation Rules
    </h3>
    <ul class="space-y-3 text-sm font-medium text-slate-600 dark:text-slate-400">
      <li class="flex items-start gap-3">
        <div class="mt-0.5 h-5 w-5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
          <i data-lucide="check" class="w-3 h-3 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <span>Only LGU-verified franchises are stored in the system.</span>
      </li>
      <li class="flex items-start gap-3">
        <div class="mt-0.5 h-5 w-5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
          <i data-lucide="check" class="w-3 h-3 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <span>Franchise references must strictly match the LTFRB-issued format.</span>
      </li>
      <li class="flex items-start gap-3">
        <div class="mt-0.5 h-5 w-5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
          <i data-lucide="check" class="w-3 h-3 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <span>Cooperatives without LGU approval numbers cannot register vehicles.</span>
      </li>
    </ul>
  </div>

  <!-- Modals -->
  <div id="franchiseFormModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-[60] transition-opacity opacity-0 p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform border border-slate-100 dark:border-slate-700" id="franchiseFormModalPanel">
      <form id="franchiseApplyForm" class="space-y-0" method="POST" action="api/franchise/apply.php">
        <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
          <h3 class="text-lg font-black text-slate-800 dark:text-white">New Franchise Application</h3>
          <button type="button" onclick="closeFranchiseFormModal()" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-500 transition-all">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>
        <div class="p-5 sm:p-8 space-y-5 max-h-[70vh] overflow-y-auto">
          <div>
            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">LTFRB Case No. / Ref #</label>
            <input name="franchise_ref" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 outline-none transition-all uppercase placeholder:normal-case" placeholder="e.g. 2024-00123" required>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">Operator</label>
              <input name="operator_name" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 outline-none transition-all" placeholder="Name" required>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">Cooperative</label>
              <?php
                $applyCoopNames = [];
                $resApplyCoop = $db->query("SELECT DISTINCT coop_name FROM coops ORDER BY coop_name ASC LIMIT 200");
                if ($resApplyCoop) {
                  while ($r = $resApplyCoop->fetch_assoc()) {
                    if (!empty($r['coop_name'])) $applyCoopNames[] = $r['coop_name'];
                  }
                }
              ?>
              <input name="coop_name" list="applyCoopNameList" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 outline-none transition-all" placeholder="Existing Coop">
              <datalist id="applyCoopNameList">
                <?php foreach ($applyCoopNames as $name): ?>
                  <option value="<?php echo htmlspecialchars($name); ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>
          </div>
          <p class="text-xs font-medium text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-lg border border-dashed border-slate-200 dark:border-slate-700">
            Select a registered cooperative. If you enter a new name, you'll be prompted to register it.
          </p>
          <div>
            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">Requested Units</label>
            <input name="vehicle_count" type="number" min="1" value="1" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
          </div>
        </div>
        <div class="p-6 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row justify-end gap-3">
          <button type="button" onclick="closeFranchiseFormModal()" class="px-5 py-2.5 text-sm font-bold rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
            Cancel
          </button>
          <button type="submit" id="btnApply" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold rounded-lg transition-all shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/40 active:scale-[0.98]">
            <span>Submit Application</span>
            <i data-lucide="send" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div id="operatorFormModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-[60] transition-opacity opacity-0 p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform border border-slate-100 dark:border-slate-700" id="operatorFormModalPanel">
      <form id="saveOperatorForm" class="space-y-0" method="POST" action="<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/api/module1/save_operator.php">
        <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
          <h3 class="text-lg font-black text-slate-800 dark:text-white">Add New Operator</h3>
          <button type="button" onclick="closeOperatorFormModal()" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-500 transition-all">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>
        <div class="p-5 sm:p-8 space-y-5 max-h-[70vh] overflow-y-auto">
          <div class="grid grid-cols-1 gap-5">
            <div>
              <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">Full Name</label>
              <input name="full_name" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-teal-500 outline-none transition-all" placeholder="Full Name" required>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">Contact Info</label>
                <input name="contact_info" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-teal-500 outline-none transition-all" placeholder="Phone / Email" required>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">Cooperative</label>
                <input name="coop_name" list="coopNameList" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-teal-500 outline-none transition-all" placeholder="Optional">
              </div>
            </div>
          </div>
        </div>
        <div class="p-6 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row justify-end gap-3">
          <button type="button" onclick="closeOperatorFormModal()" class="px-5 py-2.5 text-sm font-bold rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
            Cancel
          </button>
          <button type="submit" id="btnSaveOperator" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-lg transition-all shadow-lg shadow-teal-500/30 hover:shadow-teal-500/40 active:scale-[0.98]">
            <span>Save Operator</span>
            <i data-lucide="save" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div id="coopFormModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-[60] transition-opacity opacity-0 p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform border border-slate-100 dark:border-slate-700" id="coopFormModalPanel">
      <form id="saveCoopForm" class="space-y-0" method="POST" action="<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/api/module1/save_coop.php">
        <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
          <h3 class="text-lg font-black text-slate-800 dark:text-white">Register Cooperative</h3>
          <button type="button" onclick="closeCoopFormModal()" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-500 transition-all">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>
        <div class="p-5 sm:p-8 space-y-5 max-h-[70vh] overflow-y-auto">
          <div id="coopContextMessage" class="mb-3 px-4 py-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300 text-xs font-bold flex items-start gap-2 hidden">
            <i data-lucide="info" class="w-4 h-4 mt-0.5 shrink-0"></i>
            <span>This cooperative was opened from a franchise endorsement attempt. Please enter the LGU approval number to continue.</span>
          </div>
          <div class="grid grid-cols-1 gap-5">
            <div>
              <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">Coop Name</label>
              <input name="coop_name" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-teal-500 outline-none transition-all" placeholder="Cooperative Name" required>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">Address</label>
              <input name="address" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-teal-500 outline-none transition-all" placeholder="Official Address" required>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">Chairperson</label>
                <input name="chairperson_name" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-teal-500 outline-none transition-all" placeholder="Name" required>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">LGU Approval #</label>
                <input name="lgu_approval_number" maxlength="20" inputmode="text" autocomplete="off" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-teal-500 outline-none transition-all uppercase" placeholder="e.g. CAL-COOP-2026-001">
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-widest">Consolidation</label>
              <div class="relative">
                <select name="consolidation_status" class="w-full pl-4 pr-10 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-teal-500 outline-none appearance-none cursor-pointer">
                  <option value="Not Consolidated">Not Consolidated</option>
                  <option value="In Progress">In Progress</option>
                  <option value="Consolidated">Consolidated</option>
                </select>
                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="p-6 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row justify-end gap-3">
          <button type="button" onclick="closeCoopFormModal()" class="px-5 py-2.5 text-sm font-bold rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
            Cancel
          </button>
          <button type="submit" id="btnSaveCoop" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-lg transition-all shadow-lg shadow-teal-500/30 hover:shadow-teal-500/40 active:scale-[0.98]">
            <span>Save Cooperative</span>
            <i data-lucide="save" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function(){
      function showToast(msg, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        const colors = type === 'success' ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white';
        const icon = type === 'success' ? 'check-circle' : 'alert-circle';
        toast.className = `${colors} px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[320px] backdrop-blur-md`;
        toast.innerHTML = `
          <div class="p-1 rounded-full bg-white/20">
            <i data-lucide="${icon}" class="w-5 h-5"></i>
          </div>
          <span class="font-bold text-sm tracking-wide">${msg}</span>
        `;
        container.appendChild(toast);
        if (window.lucide) window.lucide.createIcons();
        requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
        setTimeout(() => { toast.classList.add('opacity-0', 'translate-x-full'); setTimeout(() => toast.remove(), 300); }, 3000);
      }

      var modal = document.getElementById('entityModal');
      var body = document.getElementById('entityModalBody');
      var closeBtn = document.getElementById('entityModalClose');
      function open(html){ body.innerHTML = html; modal.classList.remove('hidden'); if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(); }
      function close(){ modal.classList.add('hidden'); body.innerHTML = ''; }
      if(closeBtn) closeBtn.addEventListener('click', close);
      if(modal) modal.addEventListener('click', function(e){ if (e.target === modal || e.target.classList.contains('backdrop-blur-sm')) close(); });
      
      function attachEntityAutoView(inputId, urlBase) {
        var input = document.getElementById(inputId);
        if (!input) return;
        var timeout = null;
        input.addEventListener('input', function() {
          var name = input.value.trim();
          if (!name) return;
          if (timeout) clearTimeout(timeout);
          timeout = setTimeout(function() {
            fetch(urlBase + encodeURIComponent(name))
              .then(function(r){ return r.text(); })
              .then(open);
          }, 300);
        });
      }

      attachEntityAutoView('opViewName', 'api/module1/operator_html.php?name=');
      attachEntityAutoView('coopViewName', 'api/module1/coop_html.php?name=');

      window.__useOperatorInForm = function(fullName, contactInfo, coopName) {
        var form = document.getElementById('saveOperatorForm');
        if (!form) return;
        var nameInput = form.elements['full_name'];
        var contactInput = form.elements['contact_info'];
        var coopInput = form.elements['coop_name'];
        if (nameInput && fullName) nameInput.value = fullName;
        if (contactInput) contactInput.value = contactInfo || '';
        if (coopInput) coopInput.value = coopName || '';
        if (typeof window.openOperatorFormModal === 'function') {
          window.openOperatorFormModal();
        }
        close();
      };

      window.__useCoopInForm = function(coopName, address, chairperson, lguApproval) {
        var form = document.getElementById('saveCoopForm');
        if (!form) return;
        var nameInput = form.elements['coop_name'];
        var addressInput = form.elements['address'];
        var chairInput = form.elements['chairperson_name'];
        var lguInput = form.elements['lgu_approval_number'];
        if (nameInput && coopName) nameInput.value = coopName;
        if (addressInput) addressInput.value = address || '';
        if (chairInput) chairInput.value = chairperson || '';
        if (lguInput) lguInput.value = lguApproval || '';
        if (typeof window.openCoopFormModal === 'function') {
          window.openCoopFormModal();
        }
        close();
      };

      function renderFranchiseResult(data) {
        var container = document.getElementById('franchiseResult');
        if (!container) return;
        if (!data || data.ok === false) {
          container.className = 'min-h-[60px] flex items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30 text-red-600 dark:text-red-400 text-sm font-bold p-4';
          container.textContent = (data && data.error) ? data.error : 'Unable to validate franchise.';
          return;
        }
        var valid = !!data.valid;
        var badgeClasses = valid
          ? 'inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold bg-emerald-100 text-emerald-700 ring-1 ring-emerald-500/30 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
          : 'inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold bg-red-100 text-red-700 ring-1 ring-red-500/30 dark:bg-red-900/30 dark:text-red-400 dark:ring-red-500/20';
        var badgeLabel = valid ? 'Valid' : 'Not Valid';
        var html = ''
          + '<div class="w-full p-4 space-y-4">'
          +   '<div class="flex items-center justify-between gap-2 flex-wrap">'
          +     '<div>'
          +       '<div class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-1">Franchise Reference</div>'
          +       '<div class="text-lg font-black text-slate-800 dark:text-white">' + (data.franchise_id || '') + '</div>'
          +     '</div>'
          +     '<span class="' + badgeClasses + '">'
          +       '<span class="w-2 h-2 rounded-full ' + (valid ? 'bg-emerald-500' : 'bg-red-500') + '"></span>'
          +       '<span>' + badgeLabel + '</span>'
          +     '</span>'
          +   '</div>'
          +   '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">'
          +     '<div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700">'
          +       '<div class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Operator</div>'
          +       '<div class="font-bold text-slate-800 dark:text-white text-base">' + (data.operator || 'N/A') + '</div>'
          +       '<div class="text-xs font-medium text-slate-500 mt-1">Coop: ' + (data.coop || 'N/A') + '</div>'
          +     '</div>'
          +     '<div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700">'
          +       '<div class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Validity</div>'
          +       '<div class="text-slate-700 dark:text-slate-200 font-medium">Status: <span class="font-bold text-slate-900 dark:text-white">' + (data.status || 'Unknown') + '</span></div>'
          +       '<div class="text-xs font-medium text-slate-500 mt-1">Valid Until: ' + (data.valid_until || 'N/A') + '</div>'
          +     '</div>'
          +   '</div>'
          + '</div>';
        container.className = 'rounded-lg bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700 overflow-hidden';
        container.innerHTML = html;
      }

      function lookupFranchise(ref) {
        var v = (ref || '').trim().toUpperCase();
        if (!v) return;
        var refPattern = /^[0-9]{4}-[0-9]{3,5}$/;
        if (!refPattern.test(v)) {
          renderFranchiseResult({ ok: false, error: 'Franchise reference must look like 2024-00123.' });
          return;
        }
        var input = document.getElementById('franchiseLookup');
        if (input) input.value = v;
        fetch('api/franchise/validate.php?franchise_id=' + encodeURIComponent(v))
          .then(function(r){ return r.json(); })
          .then(renderFranchiseResult)
          .catch(function(err){
            renderFranchiseResult({ ok: false, error: 'Error: ' + err.message });
          });
      }

      var franchiseLookupInput = document.getElementById('franchiseLookup');
      var franchiseLookupBtn = document.getElementById('franchiseLookupBtn');
      if (franchiseLookupBtn && franchiseLookupInput) {
        franchiseLookupBtn.addEventListener('click', function() {
          lookupFranchise(franchiseLookupInput.value);
        });
        franchiseLookupInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            lookupFranchise(franchiseLookupInput.value);
          }
        });
      }

      document.querySelectorAll('[data-op-name]').forEach(function(el){
        el.addEventListener('click', function(){
          var name = el.getAttribute('data-op-name') || '';
          if (!name) return;
          fetch('api/module1/operator_html.php?name='+encodeURIComponent(name)).then(function(r){ return r.text(); }).then(open);
        });
      });

      document.querySelectorAll('[data-coop-name]').forEach(function(el){
        el.addEventListener('click', function(){
          var name = el.getAttribute('data-coop-name') || '';
          if (!name) return;
          fetch('api/module1/coop_html.php?name='+encodeURIComponent(name)).then(function(r){ return r.text(); }).then(open);
        });
      });

      document.querySelectorAll('button[data-app-view]').forEach(function(btn){
        btn.addEventListener('click', function(){
          var ref = btn.getAttribute('data-ref') || '';
          var status = btn.getAttribute('data-status') || '';
          var operator = btn.getAttribute('data-operator') || '';
          var coop = btn.getAttribute('data-coop') || '-';
          var vehicles = btn.getAttribute('data-vehicles') || '0';
          var submitted = btn.getAttribute('data-submitted') || '';
          var type = btn.getAttribute('data-type') || '';
          var notes = btn.getAttribute('data-notes') || '';
          var html = ''
            + '<div class="space-y-6 p-6">'
            +   '<div class="flex items-center justify-between">'
            +     '<div>'
            +       '<div class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-1">Franchise Application</div>'
            +       '<div class="text-2xl font-black text-slate-800 dark:text-white">' + ref + '</div>'
            +     '</div>'
            +     '<span class="px-3 py-1 rounded-lg text-sm font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">' + status + '</span>'
            +   '</div>'
            +   '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">'
            +     '<div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700">'
            +       '<div class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Operator</div>'
            +       '<div class="font-bold text-lg text-slate-800 dark:text-white mb-1">' + operator + '</div>'
            +       '<div class="text-sm font-medium text-slate-500">Cooperative: ' + coop + '</div>'
            +     '</div>'
            +     '<div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700">'
            +       '<div class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Summary</div>'
            +       '<div class="text-slate-700 dark:text-slate-200 font-medium">Units Requested: <span class="font-bold text-slate-900 dark:text-white">' + vehicles + '</span></div>'
            +       (type ? '<div class="text-slate-700 dark:text-slate-200 font-medium">Type: <span class="font-bold text-slate-900 dark:text-white">' + type + '</span></div>' : '')
            +       '<div class="text-sm font-medium text-slate-500 mt-1">Submitted: ' + submitted + '</div>'
            +     '</div>'
            +   '</div>'
            +   (notes ? '<div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800 border border-dashed border-slate-200 dark:border-slate-700 text-sm"><div class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Notes</div><div class="text-slate-700 dark:text-slate-200 font-medium whitespace-pre-line">' + notes + '</div></div>' : '')
            + '</div>';
          open(html);
        });
      });

      (function(){
        var form = document.getElementById('franchiseFilterForm');
        if (!form) return;
        var qInput = form.querySelector('input[name="q"]');
        var statusSelect = form.querySelector('select[name="status"]');
        var debounceTimer = null;
        function scheduleSubmit() {
          if (!form) return;
          if (debounceTimer) clearTimeout(debounceTimer);
          debounceTimer = setTimeout(function(){ form.submit(); }, 400);
        }
        if (qInput) {
          qInput.addEventListener('input', scheduleSubmit);
        }
        if (statusSelect) {
          statusSelect.addEventListener('change', function(){
            form.submit();
          });
        }
      })();

      var franchiseModal = document.getElementById('franchiseFormModal');
      var franchiseModalPanel = document.getElementById('franchiseFormModalPanel');
      var franchiseForm = document.getElementById('franchiseApplyForm');
      var operatorModal = document.getElementById('operatorFormModal');
      var operatorModalPanel = document.getElementById('operatorFormModalPanel');
      var operatorForm = document.getElementById('saveOperatorForm');
      var coopModal = document.getElementById('coopFormModal');
      var coopModalPanel = document.getElementById('coopFormModalPanel');
      var coopForm = document.getElementById('saveCoopForm');
      var coopContextMessage = document.getElementById('coopContextMessage');
      var vehicleLinkModal = document.getElementById('vehicleLinkFormModal');
      var vehicleLinkModalPanel = document.getElementById('vehicleLinkFormModalPanel');
      var vehicleLinkForm = document.getElementById('linkVehicleForm');

      if (franchiseModal) {
        franchiseModal.addEventListener('click', function(e) {
          if (e.target === franchiseModal) {
            window.closeFranchiseFormModal();
          }
        });
      }

      if (operatorModal) {
        operatorModal.addEventListener('click', function(e) {
          if (e.target === operatorModal) {
            window.closeOperatorFormModal();
          }
        });
      }

      if (coopModal) {
        coopModal.addEventListener('click', function(e) {
          if (e.target === coopModal) {
            window.closeCoopFormModal();
          }
        });
      }

      if (vehicleLinkModal) {
        vehicleLinkModal.addEventListener('click', function(e) {
          if (e.target === vehicleLinkModal) {
            window.closeVehicleLinkFormModal();
          }
        });
      }

      document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape' && e.key !== 'Esc') return;
        if (franchiseModal && !franchiseModal.classList.contains('hidden')) {
          window.closeFranchiseFormModal();
          return;
        }
        if (operatorModal && !operatorModal.classList.contains('hidden')) {
          window.closeOperatorFormModal();
          return;
        }
        if (coopModal && !coopModal.classList.contains('hidden')) {
          window.closeCoopFormModal();
          return;
        }
        if (vehicleLinkModal && !vehicleLinkModal.classList.contains('hidden')) {
          window.closeVehicleLinkFormModal();
          return;
        }
        if (modal && !modal.classList.contains('hidden')) {
          close();
        }
      });

      window.openFranchiseFormModal = function() {
        window.location.href = '?page=module2/submodule1';
      };

      window.closeFranchiseFormModal = function() {
        if (!franchiseModal) return;
        franchiseModal.classList.add('opacity-0');
        if (franchiseModalPanel) {
          franchiseModalPanel.classList.remove('scale-100');
          franchiseModalPanel.classList.add('scale-95');
        }
        setTimeout(function() {
          franchiseModal.classList.add('hidden');
        }, 300);
      };

      window.openOperatorFormModal = function() {
        if (!operatorModal) return;
        if (operatorForm) operatorForm.reset();
        operatorModal.classList.remove('hidden');
        setTimeout(function() {
          operatorModal.classList.remove('opacity-0');
          if (operatorModalPanel) {
            operatorModalPanel.classList.remove('scale-95');
            operatorModalPanel.classList.add('scale-100');
          }
        }, 10);
      };

      window.closeOperatorFormModal = function() {
        if (!operatorModal) return;
        operatorModal.classList.add('opacity-0');
        if (operatorModalPanel) {
          operatorModalPanel.classList.remove('scale-100');
          operatorModalPanel.classList.add('scale-95');
        }
        setTimeout(function() {
          operatorModal.classList.add('hidden');
        }, 300);
      };

      window.openCoopFormModal = function() {
        if (!coopModal) return;
        if (coopForm) coopForm.reset();
        if (coopContextMessage) {
          coopContextMessage.classList.add('hidden');
        }
        coopModal.classList.remove('hidden');
        setTimeout(function() {
          coopModal.classList.remove('opacity-0');
          if (coopModalPanel) {
            coopModalPanel.classList.remove('scale-95');
            coopModalPanel.classList.add('scale-100');
          }
        }, 10);
      };

      window.closeCoopFormModal = function() {
        if (!coopModal) return;
        coopModal.classList.add('opacity-0');
        if (coopModalPanel) {
          coopModalPanel.classList.remove('scale-100');
          coopModalPanel.classList.add('scale-95');
        }
        setTimeout(function() {
          coopModal.classList.add('hidden');
        }, 300);
      };

      window.openVehicleLinkFormModal = function() {
        if (!vehicleLinkModal) return;
        if (vehicleLinkForm) vehicleLinkForm.reset();
        vehicleLinkModal.classList.remove('hidden');
        setTimeout(function() {
          vehicleLinkModal.classList.remove('opacity-0');
          if (vehicleLinkModalPanel) {
            vehicleLinkModalPanel.classList.remove('scale-95');
            vehicleLinkModalPanel.classList.add('scale-100');
          }
        }, 10);
      };

      window.closeVehicleLinkFormModal = function() {
        if (!vehicleLinkModal) return;
        vehicleLinkModal.classList.add('opacity-0');
        if (vehicleLinkModalPanel) {
          vehicleLinkModalPanel.classList.remove('scale-100');
          vehicleLinkModalPanel.classList.add('scale-95');
        }
        setTimeout(function() {
          vehicleLinkModal.classList.add('hidden');
        }, 300);
      };

      function validateFranchiseForm(form) {
        var ref = (form.elements['franchise_ref'].value || '').trim().toUpperCase();
        if (!ref) return 'Franchise reference is required.';
        var refPattern = /^[0-9]{4}-[0-9]{3,5}$/;
        if (!refPattern.test(ref)) return 'Franchise reference must look like 2024-00123.';
        var op = (form.elements['operator_name'].value || '').trim();
        if (!op || op.length < 5) return 'Operator name must be at least 5 characters.';
        if (!/^[A-Za-z\s'.-]+$/.test(op)) return 'Operator name should contain letters and spaces.';
        var unitsRaw = (form.elements['vehicle_count'].value || '').trim();
        var units = parseInt(unitsRaw, 10);
        if (!unitsRaw || isNaN(units) || units < 1 || units > 1000) return 'Requested units must be between 1 and 1000.';
        return null;
      }

      function validatePersonName(value) {
        var v = (value || '').trim();
        if (v.length < 3) return false;
        return /^[A-Za-z\s'.-]+$/.test(v);
      }

      function validateContact(value) {
        var v = (value || '').trim();
        if (!v || v.length < 7) return false;
        if (v.indexOf('@') !== -1) {
          return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
        }
        return /^[0-9+\-\s()]{7,20}$/.test(v);
      }

      function validateOperatorFormValues(form) {
        var name = form.elements['full_name'].value;
        if (!validatePersonName(name)) return 'Operator full name should be realistic human name.';
        var contact = form.elements['contact_info'].value;
        if (!validateContact(contact)) return 'Contact should be a valid phone number or email.';
        return null;
      }

      function validateCoopForm(form) {
        var coop = (form.elements['coop_name'].value || '').trim();
        if (coop.length < 3) return 'Cooperative name is too short.';
        var address = (form.elements['address'].value || '').trim();
        if (address.length < 5) return 'Address should look like a real street address.';
        var chair = form.elements['chairperson_name'].value;
        if (!validatePersonName(chair)) return 'Chairperson name should be a realistic human name.';
        var lgu = (form.elements['lgu_approval_number'].value || '').trim().toUpperCase();
        if (!lgu) return 'LGU approval number is required for transport cooperatives.';
        if (!/^[A-Z]{2,6}-COOP-[0-9]{4}-[0-9]{3}$/.test(lgu)) return 'LGU approval number must look like CAL-COOP-2026-001.';
        return null;
      }

      (function () {
        var form = document.getElementById('saveCoopForm');
        if (!form) return;
        var input = form.querySelector('input[name="lgu_approval_number"]');
        if (!input) return;
        function formatLguApproval(v) {
          var raw = String(v || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
          var prefixMatch = raw.match(/^[A-Z]{0,6}/);
          var prefix = prefixMatch ? prefixMatch[0] : '';
          var rest = raw.slice(prefix.length);
          if (rest.startsWith('COOP')) rest = rest.slice(4);
          var digits = rest.replace(/[^0-9]/g, '').slice(0, 7);
          var year = digits.slice(0, 4);
          var seq = digits.slice(4, 7);
          if (!prefix) return '';
          if (raw.length <= prefix.length) return prefix;
          var out = prefix + '-COOP-';
          if (year) out += year;
          if (digits.length > 4) out += '-' + seq;
          return out;
        }
        input.addEventListener('input', function () {
          var next = formatLguApproval(input.value);
          if (input.value !== next) input.value = next;
        });
      })();

      function validateVehicleLinkForm(form) {
        var plate = (form.elements['plate_number'].value || '').trim().toUpperCase();
        if (!plate) return 'Plate number is required.';
        var platePattern = /^[A-Z]{3}-?[0-9]{3,4}$/;
        if (!platePattern.test(plate)) return 'Plate number should look like ABC-1234.';
        var op = form.elements['operator_name'].value;
        if (!validatePersonName(op)) return 'Operator name should be a realistic human name.';
        return null;
      }

      function mapSaveOperatorError(msg) {
        var m = (msg || '').toString();
        switch (m) {
          case 'Operator full name should be a realistic human name':
            return 'Operator full name should be a realistic human name.';
          case 'Contact should be a valid phone number or email':
            return 'Contact should be a valid phone number or email.';
          case 'Contact should be a valid email address':
            return 'Contact should be a valid email address.';
          case 'Contact should be a valid phone number':
            return 'Contact should be a valid phone number.';
          default:
            return m || 'Operation failed';
        }
      }

      function mapSaveCoopError(msg) {
        var m = (msg || '').toString();
        switch (m) {
          case 'Cooperative name cannot start with TEST in production':
            return 'Cooperative name cannot start with TEST in production.';
          case 'Cooperative name is too short':
            return 'Cooperative name is too short; use at least 3 characters.';
          case 'Address should look like a real street address':
            return 'Address should look like a real street address.';
          case 'Chairperson name should be a realistic human name':
            return 'Chairperson name should be a realistic human name.';
          case 'LGU approval number is required for transport cooperatives':
            return 'LGU approval number is required for transport cooperatives.';
          case 'LGU approval number must match format like CAL-COOP-2026-001':
            return 'LGU approval number must look like CAL-COOP-2026-001.';
          case 'LGU approval number is already used by another cooperative':
            return 'LGU approval number is already linked to another cooperative.';
          default:
            return m || 'Operation failed';
        }
      }

      function handleForm(formId, btnId, successMsg, onSuccess, validateFn, onError) {
        const form = document.getElementById(formId);
        const btn = document.getElementById(btnId);
        if(!form || !btn) return;

        form.addEventListener('submit', async function(e) {
          e.preventDefault();
          if (typeof validateFn === 'function') {
            const error = validateFn(form);
            if (error) {
              showToast(error, 'error');
              return;
            }
          }
          const originalContent = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Processing...`;
          if (window.lucide) window.lucide.createIcons();

          try {
            const formData = new FormData(form);
            const res = await fetch(form.action, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.ok) {
              showToast(successMsg);
              form.reset();
              if (typeof onSuccess === 'function') {
                onSuccess();
              }
              setTimeout(() => location.reload(), 1000);
            } else {
              var errMsg = (data && data.error) ? data.error : 'Operation failed';
              if (formId === 'saveCoopForm') {
                errMsg = mapSaveCoopError(errMsg);
              } else if (formId === 'saveOperatorForm') {
                errMsg = mapSaveOperatorError(errMsg);
              }
              showToast(errMsg, 'error');
              if (typeof onError === 'function') {
                onError(data, form);
              }
            }
          } catch (err) {
            showToast('Error: ' + err.message, 'error');
          } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
            if (window.lucide) window.lucide.createIcons();
          }
        });
      }

      handleForm('franchiseApplyForm', 'btnApply', 'Application Submitted Successfully!', window.closeFranchiseFormModal, validateFranchiseForm, function(data) {
        if (!data) return;
        if (data.error_code === 'coop_not_found' || data.error_code === 'coop_missing_lgu_approval') {
          if (typeof window.openCoopFormModal === 'function') {
            var franchiseForm = document.getElementById('franchiseApplyForm');
            if (franchiseForm) {
              window.__pendingFranchiseFields = {
                franchise_ref: franchiseForm.elements['franchise_ref'] ? franchiseForm.elements['franchise_ref'].value : '',
                operator_name: franchiseForm.elements['operator_name'] ? franchiseForm.elements['operator_name'].value : '',
                coop_name: franchiseForm.elements['coop_name'] ? franchiseForm.elements['coop_name'].value : '',
                vehicle_count: franchiseForm.elements['vehicle_count'] ? franchiseForm.elements['vehicle_count'].value : ''
              };
            } else {
              window.__pendingFranchiseFields = null;
            }
            window.openCoopFormModal();
            window.__pendingFranchiseCoopName = data.coop_name || '';
            setTimeout(function() {
              var coopForm = document.getElementById('saveCoopForm');
              if (!coopForm) return;
              var nameInput = coopForm.elements['coop_name'];
              if (nameInput && data.coop_name) {
                nameInput.value = data.coop_name;
              }
              var ctx = document.getElementById('coopContextMessage');
              if (ctx) {
                ctx.classList.remove('hidden');
              }
              var lguInput = coopForm.elements['lgu_approval_number'];
              if (lguInput) {
                lguInput.focus();
              }
            }, 300);
          }
        }
      });
      handleForm('saveOperatorForm', 'btnSaveOperator', 'Operator Saved!', window.closeOperatorFormModal, validateOperatorFormValues);
      handleForm('saveCoopForm', 'btnSaveCoop', 'Cooperative Saved!', function() {
        if (typeof window.closeCoopFormModal === 'function') {
          window.closeCoopFormModal();
        }
        if (window.__pendingFranchiseFields || window.__pendingFranchiseCoopName) {
          if (typeof window.openFranchiseFormModal === 'function') {
            window.openFranchiseFormModal();
            setTimeout(function() {
              var franchiseForm = document.getElementById('franchiseApplyForm');
              if (!franchiseForm) return;
              var fields = window.__pendingFranchiseFields || {};
              var refInput = franchiseForm.elements['franchise_ref'];
              var opInput = franchiseForm.elements['operator_name'];
              var coopInput = franchiseForm.elements['coop_name'];
              var countInput = franchiseForm.elements['vehicle_count'];
              if (refInput && fields.franchise_ref) refInput.value = fields.franchise_ref;
              if (opInput && fields.operator_name) opInput.value = fields.operator_name;
              var finalCoopName = window.__pendingFranchiseCoopName || fields.coop_name || '';
              if (coopInput && finalCoopName) coopInput.value = finalCoopName;
              if (countInput && fields.vehicle_count) countInput.value = fields.vehicle_count;
            }, 300);
          }
          window.__pendingFranchiseFields = null;
          window.__pendingFranchiseCoopName = '';
        }
      }, validateCoopForm, function(data, form) {
        if (!data || !form) return;
        var msg = (data.error || '').toString();
        if (msg.indexOf('LGU approval number is already used by another cooperative') !== -1) {
          var lguInput = form.elements['lgu_approval_number'];
          if (lguInput) {
            lguInput.focus();
            lguInput.classList.add('border-red-500', 'ring-2', 'ring-red-400/40');
            setTimeout(function() {
              lguInput.classList.remove('ring-2', 'ring-red-400/40');
            }, 1500);
          }
          var ctx = document.getElementById('coopContextMessage');
          if (ctx) {
            ctx.classList.remove('hidden');
            ctx.textContent = 'This LGU approval number already belongs to another cooperative. Please double-check the number or use that cooperative instead.';
          }
        }
      });
      handleForm('linkVehicleForm', 'btnLinkVehicle', 'Vehicle Linked Successfully!', window.closeVehicleLinkFormModal, validateVehicleLinkForm);

      (function() {
        var plateInput = document.querySelector('#linkVehicleForm input[name="plate_number"]');
        var operatorInput = document.querySelector('#linkVehicleForm input[name="operator_name"]');
        var plateList = document.getElementById('plateList');
        if (!plateInput || !operatorInput || !plateList) return;
        function applyOperator() {
          var value = (plateInput.value || '').toUpperCase();
          if (!value) return;
          var options = plateList.options;
          for (var i = 0; i < options.length; i++) {
            if ((options[i].value || '').toUpperCase() === value) {
              var opName = options[i].getAttribute('data-operator') || '';
              if (opName) operatorInput.value = opName;
              break;
            }
          }
        }
        plateInput.addEventListener('change', applyOperator);
      })();

    })();
  </script>
</div>
