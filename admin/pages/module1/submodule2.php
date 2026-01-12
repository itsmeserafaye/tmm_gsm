<?php
  require_once __DIR__ . '/../../includes/db.php';
  $db = db();
?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Operator, Cooperative & Franchise Validation</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Maintains operator and cooperative profiles and validates franchise references through cross-checks with Franchise Management.</p>

  <!-- Toast Notification Container -->
  <div id="toast-container" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <!-- Summary Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="p-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="file-text" class="w-16 h-16 text-blue-500"></i>
      </div>
      <div class="relative z-10">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Applications</p>
        <?php
          $resT = $db->query("SELECT COUNT(*) as c FROM franchise_applications");
          $total = $resT->fetch_assoc()['c'] ?? 0;
        ?>
        <h3 class="text-3xl font-bold text-slate-800 dark:text-slate-100 mt-1"><?php echo $total; ?></h3>
        <div class="flex items-center mt-2 text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 w-fit px-2 py-1 rounded-full">
          <i data-lucide="trending-up" class="w-3 h-3 mr-1"></i>
          <span>All records</span>
        </div>
      </div>
    </div>

    <div class="p-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="check-circle-2" class="w-16 h-16 text-emerald-500"></i>
      </div>
      <div class="relative z-10">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Endorsed / Valid</p>
        <?php
          $resE = $db->query("SELECT COUNT(*) as c FROM franchise_applications WHERE status='Endorsed'");
          $endorsed = $resE->fetch_assoc()['c'] ?? 0;
        ?>
        <h3 class="text-3xl font-bold text-slate-800 dark:text-slate-100 mt-1"><?php echo $endorsed; ?></h3>
        <div class="flex items-center mt-2 text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 w-fit px-2 py-1 rounded-full">
          <i data-lucide="check" class="w-3 h-3 mr-1"></i>
          <span>Permits Issued</span>
        </div>
      </div>
    </div>

    <div class="p-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="clock" class="w-16 h-16 text-amber-500"></i>
      </div>
      <div class="relative z-10">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pending Review</p>
        <?php
          $resP = $db->query("SELECT COUNT(*) as c FROM franchise_applications WHERE status='Pending' OR status='Under Review'");
          $pending = $resP->fetch_assoc()['c'] ?? 0;
        ?>
        <h3 class="text-3xl font-bold text-slate-800 dark:text-slate-100 mt-1"><?php echo $pending; ?></h3>
        <div class="flex items-center mt-2 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 w-fit px-2 py-1 rounded-full">
          <i data-lucide="alert-circle" class="w-3 h-3 mr-1"></i>
          <span>Needs Action</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Registry Table -->
  <div class="mb-8 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200 dark:border-slate-700 space-y-4">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg text-purple-600 dark:text-purple-400">
            <i data-lucide="folder-open" class="w-5 h-5"></i>
          </div>
          <h2 class="font-semibold text-slate-800 dark:text-slate-100">Franchise Applications</h2>
        </div>
        <div class="flex flex-col md:flex-row items-stretch md:items-center gap-2 w-full md:w-auto justify-end">
          <button type="button" onclick="openFranchiseFormModal()" class="inline-flex items-center justify-center gap-2 px-3 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg shadow-sm shadow-emerald-500/30">
            <i data-lucide="file-plus" class="w-4 h-4"></i>
            <span>New Application</span>
          </button>
          <form id="franchiseFilterForm" class="flex items-center gap-2 w-full md:w-auto" method="GET">
            <input type="hidden" name="page" value="module1/submodule2">
            <div class="relative flex-1 md:w-64">
              <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
              <input name="q" list="franchiseSearchList" value="<?php echo htmlspecialchars($_GET['q']??''); ?>" class="w-full pl-9 pr-4 py-2 text-sm border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all" placeholder="Search Ref or Operator...">
              <?php
                $searchOptions = [];
                $resSearch = $db->query("SELECT DISTINCT franchise_ref_number, operator_name FROM franchise_applications ORDER BY submitted_at DESC LIMIT 100");
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
            <select name="status" class="px-3 py-2 text-sm border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all">
              <option value="">Status</option>
              <option value="Pending" <?php echo ($_GET['status']??'')==='Pending'?'selected':''; ?>>Pending</option>
              <option value="Endorsed" <?php echo ($_GET['status']??'')==='Endorsed'?'selected':''; ?>>Endorsed</option>
              <option value="Rejected" <?php echo ($_GET['status']??'')==='Rejected'?'selected':''; ?>>Rejected</option>
            </select>
          </form>
        </div>
      </div>
    </div>
    
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-600 dark:text-slate-400">
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Reference #</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Operator</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Cooperative</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Units</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Submitted</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Status</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
          <?php
            $q = trim($_GET['q'] ?? '');
            $st = trim($_GET['status'] ?? '');
            $sql = "SELECT fa.*, o.full_name as operator, c.coop_name 
                    FROM franchise_applications fa 
                    LEFT JOIN operators o ON fa.operator_id = o.id 
                    LEFT JOIN coops c ON fa.coop_id = c.id";
            $conds = []; $params = []; $types = '';
            if ($q !== '') { $conds[] = "(fa.franchise_ref_number LIKE ? OR o.full_name LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; $types.='ss'; }
            if ($st !== '') { $conds[] = "fa.status = ?"; $params[]=$st; $types.='s'; }
            if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
            $sql .= " ORDER BY fa.submitted_at DESC LIMIT 50";
            
            if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); }
            else { $res = $db->query($sql); }
            
            if ($res->num_rows > 0):
            while ($row = $res->fetch_assoc()):
              $sBadge = match($row['status']) {
                'Endorsed' => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/20',
                'Pending' => 'bg-amber-100 text-amber-700 ring-1 ring-amber-600/20',
                'Rejected' => 'bg-red-100 text-red-700 ring-1 ring-red-600/20',
                default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-600/20'
              };
          ?>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="py-3 px-4 font-medium text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars($row['franchise_ref_number']); ?></td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400">
              <?php if (!empty($row['operator'])): ?>
                <button
                  type="button"
                  class="text-slate-700 dark:text-slate-200 hover:text-teal-600 hover:underline"
                  data-op-name="<?php echo htmlspecialchars($row['operator']); ?>"
                >
                  <?php echo htmlspecialchars($row['operator']); ?>
                </button>
              <?php else: ?>
                <span class="text-slate-400">-</span>
              <?php endif; ?>
            </td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400">
              <?php $coopName = $row['coop_name'] ?? ''; ?>
              <?php if ($coopName): ?>
                <button
                  type="button"
                  class="text-slate-700 dark:text-slate-200 hover:text-teal-600 hover:underline"
                  data-coop-name="<?php echo htmlspecialchars($coopName); ?>"
                >
                  <?php echo htmlspecialchars($coopName); ?>
                </button>
              <?php else: ?>
                <span class="text-slate-400">-</span>
              <?php endif; ?>
            </td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><?php echo (int)$row['vehicle_count']; ?></td>
            <td class="py-3 px-4 text-slate-500 text-xs"><?php echo date('M d, Y', strtotime($row['submitted_at'])); ?></td>
            <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?php echo $sBadge; ?>"><?php echo $row['status']; ?></span></td>
            <td class="py-3 px-4 text-center">
              <div class="flex items-center justify-center gap-2">
                <button
                  title="View Details"
                  class="p-2 rounded-full text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"
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
                  class="p-2 rounded-full text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors inline-flex items-center justify-center"
                  title="Register Vehicle"
                >
                  <i data-lucide="plus-circle" class="w-4 h-4"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="7" class="py-8 text-center text-slate-500 italic">No applications found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm">
      <h3 class="text-md font-semibold mb-4 flex items-center gap-2"><i data-lucide="file-text" class="w-5 h-5 text-blue-500"></i> Franchise Details</h3>
      <div class="flex flex-col md:flex-row gap-2 mb-4 items-stretch md:items-center">
        <div class="relative flex-1">
          <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
          <input
            id="franchiseLookup"
            class="w-full pl-9 pr-4 py-2 text-sm border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all uppercase"
            placeholder="Enter Franchise Ref # (e.g. 2024-00123)"
          >
        </div>
        <button
          id="franchiseLookupBtn"
          type="button"
          class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-500 hover:bg-blue-600 text-white flex items-center gap-2"
        >
          <i data-lucide="shield-check" class="w-4 h-4"></i>
          <span>Validate</span>
        </button>
      </div>
      <div id="franchiseResult" class="text-sm text-slate-500 italic py-4 text-center">Enter a Franchise Ref above to validate details.</div>
    </div>

    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-teal-500 shadow-sm">
      <h3 class="text-md font-semibold mb-4 flex items-center gap-2"><i data-lucide="users" class="w-5 h-5 text-teal-500"></i> Operator & Cooperative</h3>
      <?php
        $opNames = [];
        $resOp = $db->query("
          SELECT name FROM (
            SELECT DISTINCT full_name AS name FROM operators
            UNION
            SELECT DISTINCT operator_name AS name FROM vehicles WHERE operator_name <> ''
          ) AS names
          ORDER BY name ASC
          LIMIT 300
        ");
        if ($resOp) {
          while ($r = $resOp->fetch_assoc()) {
            if (!empty($r['name'])) $opNames[] = $r['name'];
          }
        }
        $coopNames = [];
        $resCoop = $db->query("SELECT DISTINCT coop_name FROM coops ORDER BY coop_name ASC LIMIT 200");
        if ($resCoop) {
          while ($r = $resCoop->fetch_assoc()) {
            if (!empty($r['coop_name'])) $coopNames[] = $r['coop_name'];
          }
        }
        $plateMap = [];
        $resPlates = $db->query("SELECT plate_number, operator_name FROM vehicles WHERE plate_number <> '' ORDER BY plate_number ASC LIMIT 200");
        if ($resPlates) {
          while ($r = $resPlates->fetch_assoc()) {
            $plate = strtoupper(trim($r['plate_number'] ?? ''));
            if ($plate === '') continue;
            if (!isset($plateMap[$plate])) {
              $plateMap[$plate] = trim((string)($r['operator_name'] ?? ''));
            }
          }
        }
      ?>
      <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <input
              id="opViewName"
              list="opNameList"
              class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all"
              placeholder="Search operator name..."
            >
            <datalist id="opNameList">
              <?php foreach ($opNames as $name): ?>
                <option value="<?php echo htmlspecialchars($name); ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <input
              id="coopViewName"
              list="coopNameList"
              class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all"
              placeholder="Search cooperative name..."
            >
            <datalist id="coopNameList">
              <?php foreach ($coopNames as $name): ?>
                <option value="<?php echo htmlspecialchars($name); ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" onclick="openOperatorFormModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded-lg transition-colors shadow-sm shadow-teal-500/30">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span>Add New Operator</span>
          </button>
          <button type="button" onclick="openCoopFormModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded-lg transition-colors shadow-sm shadow-teal-500/30">
            <i data-lucide="users" class="w-4 h-4"></i>
            <span>Register Cooperative</span>
          </button>
          <button type="button" onclick="openVehicleLinkFormModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded-lg transition-colors shadow-sm shadow-teal-500/30">
            <i data-lucide="link-2" class="w-4 h-4"></i>
            <span>Link Vehicle</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-orange-500 shadow-sm mt-6">
    <h3 class="text-md font-semibold mb-4 flex items-center gap-2"><i data-lucide="alert-circle" class="w-5 h-5 text-orange-500"></i> Validation Rules</h3>
    <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
      <li class="flex items-start gap-2"><i data-lucide="check-circle-2" class="w-4 h-4 text-green-500 mt-0.5"></i> Only LGU-verified franchises are stored.</li>
      <li class="flex items-start gap-2"><i data-lucide="check-circle-2" class="w-4 h-4 text-green-500 mt-0.5"></i> Franchise must match LTFRB-issued reference.</li>
      <li class="flex items-start gap-2"><i data-lucide="check-circle-2" class="w-4 h-4 text-green-500 mt-0.5"></i> COOP without LGU approval cannot register vehicles.</li>
    </ul>
  </div>

  <div id="franchiseFormModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-[60] transition-opacity opacity-0">
    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="franchiseFormModalPanel">
      <form id="franchiseApplyForm" class="space-y-0" method="POST" action="api/franchise/apply.php">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between bg-slate-50 dark:bg-slate-800">
          <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">New Franchise Application</h3>
          <button type="button" onclick="closeFranchiseFormModal()" class="p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
            <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
          </button>
        </div>
        <div class="p-6 space-y-3 max-h-[70vh] overflow-y-auto">
          <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">LTFRB Case No. / Ref #</label>
            <input name="franchise_ref" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all uppercase" placeholder="e.g. 2024-00123" required>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Operator</label>
              <input name="operator_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Name" required>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Cooperative</label>
              <?php
                $applyCoopNames = [];
                $resApplyCoop = $db->query("SELECT DISTINCT coop_name FROM coops ORDER BY coop_name ASC LIMIT 200");
                if ($resApplyCoop) {
                  while ($r = $resApplyCoop->fetch_assoc()) {
                    if (!empty($r['coop_name'])) $applyCoopNames[] = $r['coop_name'];
                  }
                }
              ?>
              <input name="coop_name" list="applyCoopNameList" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Existing Cooperative (must be registered)">
              <datalist id="applyCoopNameList">
                <?php foreach ($applyCoopNames as $name): ?>
                  <option value="<?php echo htmlspecialchars($name); ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                Select a cooperative that is already registered with an LGU approval number, or leave blank if not under a coop.
                If you enter a new cooperative name here, submitting this form will open the Register Cooperative dialog so you can capture its details and LGU approval number.
              </p>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Requested Units</label>
            <input name="vehicle_count" type="number" min="1" value="1" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
          </div>
        </div>
        <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2">
          <button type="button" onclick="closeFranchiseFormModal()" class="px-4 py-2 text-sm font-medium rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">
            Cancel
          </button>
          <button type="submit" id="btnApply" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors shadow-sm shadow-emerald-500/30">
            <span>Submit Application</span>
            <i data-lucide="send" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div id="operatorFormModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-[60] transition-opacity opacity-0">
    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="operatorFormModalPanel">
      <form id="saveOperatorForm" class="space-y-0" method="POST" action="/tmm/admin/api/module1/save_operator.php">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between bg-slate-50 dark:bg-slate-800">
          <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Add New Operator</h3>
          <button type="button" onclick="closeOperatorFormModal()" class="p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
            <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
          </button>
        </div>
        <div class="p-6 space-y-3 max-h-[70vh] overflow-y-auto">
          <div class="grid grid-cols-1 gap-3">
            <input name="full_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Full Name" required>
            <div class="grid grid-cols-2 gap-3">
              <input name="contact_info" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Contact" required>
              <input name="coop_name" list="coopNameList" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Cooperative">
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2">
          <button type="button" onclick="closeOperatorFormModal()" class="px-4 py-2 text-sm font-medium rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">
            Cancel
          </button>
          <button type="submit" id="btnSaveOperator" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded-lg transition-colors shadow-sm shadow-teal-500/30">
            <span>Save Operator</span>
            <i data-lucide="save" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div id="coopFormModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-[60] transition-opacity opacity-0">
    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="coopFormModalPanel">
      <form id="saveCoopForm" class="space-y-0" method="POST" action="/tmm/admin/api/module1/save_coop.php">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between bg-slate-50 dark:bg-slate-800">
          <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Register Cooperative</h3>
          <button type="button" onclick="closeCoopFormModal()" class="p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
            <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
          </button>
        </div>
        <div class="p-6 space-y-3 max-h-[70vh] overflow-y-auto">
          <div id="coopContextMessage" class="mb-3 px-3 py-2 rounded-lg bg-amber-50 text-amber-800 text-xs flex items-start gap-2 hidden">
            <i data-lucide="info" class="w-4 h-4 mt-0.5"></i>
            <span>This cooperative was opened from a franchise endorsement attempt. Please enter the LGU approval number to continue.</span>
          </div>
          <div class="grid grid-cols-1 gap-3">
            <input name="coop_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Cooperative Name" required>
            <input name="address" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Address" required>
            <div class="grid grid-cols-2 gap-3">
              <input name="chairperson_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Chairperson" required>
              <input name="lgu_approval_number" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="LGU Approval No.">
            </div>
            <div>
              <select name="consolidation_status" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none text-sm">
                <option value="Not Consolidated">Not Consolidated</option>
                <option value="In Progress">In Progress</option>
                <option value="Consolidated">Consolidated</option>
              </select>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2">
          <button type="button" onclick="closeCoopFormModal()" class="px-4 py-2 text-sm font-medium rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">
            Cancel
          </button>
          <button type="submit" id="btnSaveCoop" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded-lg transition-colors shadow-sm shadow-teal-500/30">
            <span>Save Cooperative</span>
            <i data-lucide="save" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div id="vehicleLinkFormModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-[60] transition-opacity opacity-0">
    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="vehicleLinkFormModalPanel">
      <form id="linkVehicleForm" class="space-y-0" method="POST" action="/tmm/admin/api/module1/link_vehicle_operator.php">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between bg-slate-50 dark:bg-slate-800">
          <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Link Vehicle</h3>
          <button type="button" onclick="closeVehicleLinkFormModal()" class="p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
            <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
          </button>
        </div>
        <div class="p-6 space-y-3 max-h-[70vh] overflow-y-auto">
          <div class="grid grid-cols-1 gap-3">
            <input name="plate_number" list="plateList" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all uppercase" placeholder="Plate number" required>
            <datalist id="plateList">
              <?php foreach ($plateMap as $plate => $opName): ?>
                <option value="<?php echo htmlspecialchars($plate); ?>" data-operator="<?php echo htmlspecialchars($opName); ?>"></option>
              <?php endforeach; ?>
            </datalist>
            <div class="grid grid-cols-2 gap-3">
              <input name="operator_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Operator Name" required>
              <input name="coop_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Coop Name">
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2">
          <button type="button" onclick="closeVehicleLinkFormModal()" class="px-4 py-2 text-sm font-medium rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">
            Cancel
          </button>
          <button type="submit" id="btnLinkVehicle" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded-lg transition-colors shadow-sm shadow-teal-500/30">
            <span>Link to Vehicle</span>
            <i data-lucide="link" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div id="entityModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="w-full max-w-3xl bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 transform transition-all">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 rounded-t-xl">
          <div class="text-lg font-semibold text-slate-800 dark:text-slate-100">Details</div>
          <button id="entityModalClose" class="p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"><i data-lucide="x" class="w-5 h-5 text-slate-500"></i></button>
        </div>
        <div id="entityModalBody" class="p-6 max-h-[70vh] overflow-y-auto"></div>
      </div>
    </div>
  </div>
  <script>
    (function(){
      function showToast(msg, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        const colors = type === 'success' ? 'bg-emerald-500' : 'bg-red-500';
        const icon = type === 'success' ? 'check-circle' : 'alert-circle';
        toast.className = `${colors} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px]`;
        toast.innerHTML = `
          <i data-lucide="${icon}" class="w-5 h-5"></i>
          <span class="font-medium text-sm">${msg}</span>
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
      if(modal) modal.addEventListener('click', function(e){ if (e.target === modal || e.target.classList.contains('bg-black/50')) close(); });
      
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
          container.className = 'text-sm text-red-600 py-4 text-center';
          container.textContent = (data && data.error) ? data.error : 'Unable to validate franchise.';
          return;
        }
        var valid = !!data.valid;
        var badgeClasses = valid
          ? 'inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 ring-1 ring-emerald-500/30'
          : 'inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 ring-1 ring-red-500/30';
        var badgeLabel = valid ? 'Valid' : 'Not Valid';
        var html = ''
          + '<div class="space-y-3 text-left">'
          +   '<div class="flex items-center justify-between gap-2 flex-wrap">'
          +     '<div>'
          +       '<div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Franchise Reference</div>'
          +       '<div class="text-base font-semibold text-slate-800 dark:text-slate-100">' + (data.franchise_id || '') + '</div>'
          +     '</div>'
          +     '<span class="' + badgeClasses + '">'
          +       '<span class="w-2 h-2 rounded-full ' + (valid ? 'bg-emerald-500' : 'bg-red-500') + '"></span>'
          +       '<span>' + badgeLabel + '</span>'
          +     '</span>'
          +   '</div>'
          +   '<div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">'
          +     '<div class="p-3 rounded-lg border border-slate-200 dark:border-slate-700">'
          +       '<div class="text-xs text-slate-500 mb-1">Operator</div>'
          +       '<div class="font-medium text-slate-800 dark:text-slate-100">' + (data.operator || 'N/A') + '</div>'
          +       '<div class="text-xs text-slate-500 mt-1">Cooperative: ' + (data.coop || 'N/A') + '</div>'
          +     '</div>'
          +     '<div class="p-3 rounded-lg border border-slate-200 dark:border-slate-700">'
          +       '<div class="text-xs text-slate-500 mb-1">Validity</div>'
          +       '<div class="text-slate-700 dark:text-slate-200">Status: <span class="font-semibold">' + (data.status || 'Unknown') + '</span></div>'
          +       '<div class="text-xs text-slate-500 mt-1">Valid Until: ' + (data.valid_until || 'N/A') + '</div>'
          +     '</div>'
          +   '</div>'
          + '</div>';
        container.className = 'text-sm text-slate-700 dark:text-slate-200 py-2';
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
            + '<div class="space-y-4">'
            +   '<div class="flex items-center justify-between">'
            +     '<div>'
            +       '<div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Franchise Application</div>'
            +       '<div class="text-lg font-semibold text-slate-800 dark:text-slate-100">' + ref + '</div>'
            +     '</div>'
            +     '<span class="px-2 py-1 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200">' + status + '</span>'
            +   '</div>'
            +   '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">'
            +     '<div class="p-3 rounded-lg border border-slate-200 dark:border-slate-700">'
            +       '<div class="text-xs text-slate-500 mb-1">Operator</div>'
            +       '<div class="font-medium text-slate-800 dark:text-slate-100">' + operator + '</div>'
            +       '<div class="text-xs text-slate-500 mt-1">Cooperative: ' + coop + '</div>'
            +     '</div>'
            +     '<div class="p-3 rounded-lg border border-slate-200 dark:border-slate-700">'
            +       '<div class="text-xs text-slate-500 mb-1">Summary</div>'
            +       '<div class="text-slate-700 dark:text-slate-200">Units Requested: <span class="font-semibold">' + vehicles + '</span></div>'
            +       (type ? '<div class="text-slate-700 dark:text-slate-200">Type: <span class="font-semibold">' + type + '</span></div>' : '')
            +       '<div class="text-xs text-slate-500 mt-1">Submitted: ' + submitted + '</div>'
            +     '</div>'
            +   '</div>'
            +   (notes ? '<div class="p-3 rounded-lg border border-dashed border-slate-200 dark:border-slate-700 text-sm"><div class="text-xs text-slate-500 mb-1">Notes</div><div class="text-slate-700 dark:text-slate-200 whitespace-pre-line">' + notes + '</div></div>' : '')
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
        if (!franchiseModal) return;
        if (franchiseForm) franchiseForm.reset();
        franchiseModal.classList.remove('hidden');
        setTimeout(function() {
          franchiseModal.classList.remove('opacity-0');
          if (franchiseModalPanel) {
            franchiseModalPanel.classList.remove('scale-95');
            franchiseModalPanel.classList.add('scale-100');
          }
        }, 10);
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
        var lgu = (form.elements['lgu_approval_number'].value || '').trim();
        if (!lgu) return 'LGU approval number is required for transport cooperatives.';
        if (!/^LGU-[0-9]{4}-[0-9]{5}$/.test(lgu)) return 'LGU approval number must look like LGU-2024-00001.';
        return null;
      }

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
          case 'LGU approval number should be alphanumeric (with - or /)':
            return 'LGU approval number should be alphanumeric; dashes (-) and slashes (/) allowed.';
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
