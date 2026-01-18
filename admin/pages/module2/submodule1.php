<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.view','module2.franchises.manage']);
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Franchise Application & Cooperative</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Intake and tracking of franchise endorsement applications, cooperative profiles, and documentation.</p>
    </div>
  </div>

  <!-- Toast Container -->
  <div id="toast-container" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <?php
    require_once __DIR__ . '/../../includes/db.php';
    require_once __DIR__ . '/../../includes/lptrp.php';
    $db = db();
    $prefillRepName = trim((string)($_GET['rep_name'] ?? ''));

    tmm_sync_lptrp_from_routes($db);

    $hasCons = false;
    $chkCons = $db->query("SHOW COLUMNS FROM coops LIKE 'consolidation_status'");
    if ($chkCons && $chkCons->num_rows > 0) $hasCons = true;
    $coopsRes = $db->query("SELECT id, coop_name, " . ($hasCons ? "consolidation_status" : "'' AS consolidation_status") . " FROM coops ORDER BY coop_name");
    $hasDescCol = false;
    $hasRouteNameCol = false;
    $hasApprovalCol = false;
    $hasStatusCol = false;
    $cc = $db->query("SHOW COLUMNS FROM lptrp_routes");
    if ($cc) {
      while ($c = $cc->fetch_assoc()) {
        $f = (string)($c['Field'] ?? '');
        if ($f === 'description') $hasDescCol = true;
        if ($f === 'route_name') $hasRouteNameCol = true;
        if ($f === 'approval_status') $hasApprovalCol = true;
        if ($f === 'status') $hasStatusCol = true;
      }
    }
    $descSel = $hasDescCol ? "description" : ($hasRouteNameCol ? "route_name" : "''");
    $statusSel = $hasApprovalCol ? "approval_status" : ($hasStatusCol ? "status" : "''");
    $routesRes = $db->query("SELECT id, route_code, $descSel AS route_desc, start_point, end_point, max_vehicle_capacity, current_vehicle_count, $statusSel AS route_status FROM lptrp_routes ORDER BY route_code");

    $coops = [];
    if ($coopsRes) {
      while ($row = $coopsRes->fetch_assoc()) {
        $coops[] = $row;
      }
    }

    $routes = [];
    if ($routesRes) {
      while ($row = $routesRes->fetch_assoc()) {
        $routes[] = $row;
      }
    }

    $q = trim($_GET['q'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');

    $sql = "SELECT fa.*, o.full_name AS operator, c.coop_name, r.route_code, $descSel AS route_desc, r.start_point, r.end_point 
            FROM franchise_applications fa 
            LEFT JOIN operators o ON fa.operator_id = o.id 
            LEFT JOIN coops c ON fa.coop_id = c.id 
            LEFT JOIN lptrp_routes r ON r.id = fa.route_ids";

    $conds = [];
    $params = [];
    $types = '';

    if ($q !== '') {
      $conds[] = "(fa.franchise_ref_number LIKE ? OR o.full_name LIKE ? OR c.coop_name LIKE ?)";
      $like = "%$q%";
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $types .= 'sss';
    }

    if ($statusFilter !== '' && $statusFilter !== 'All') {
      $conds[] = "fa.status = ?";
      $params[] = $statusFilter;
      $types .= 's';
    }

    if ($conds) {
      $sql .= " WHERE " . implode(" AND ", $conds);
    }

    $sql .= " ORDER BY fa.submitted_at DESC LIMIT 50";

    if ($params) {
      $stmt = $db->prepare($sql);
      if ($stmt && $types !== '') {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $appsRes = $stmt->get_result();
      } else {
        $appsRes = false;
      }
    } else {
      $appsRes = $db->query($sql);
    }
  ?>

  <!-- Application Form -->
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-3">
      <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
        <i data-lucide="file-plus" class="w-5 h-5"></i>
      </div>
      <div>
        <h2 class="text-base font-bold text-slate-900 dark:text-white">New Application</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Initiate franchise endorsement process</p>
      </div>
    </div>
    
    <div class="p-6">
      <form id="module2AppForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Cooperative</label>
            <div class="relative">
              <select name="coop_id" class="w-full pl-4 pr-10 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white">
                <option value="">Select cooperative</option>
                <?php foreach ($coops as $c): ?>
                  <?php
                    $label = $c['coop_name'] ?? 'Coop';
                  ?>
                  <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
              <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
            </div>
          </div>
          
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Representative Name</label>
            <input name="rep_name" value="<?php echo htmlspecialchars($prefillRepName); ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="e.g. Juan Dela Cruz">
          </div>
          
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">LTFRB Franchise Ref</label>
            <input id="ltfrbFranchiseRef" name="franchise_ref" maxlength="10" inputmode="numeric" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="e.g. 2024-00123">
          </div>
        </div>

        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Vehicle Count</label>
              <input name="vehicle_count" type="number" min="1" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="e.g. 10">
            </div>
            <div>
               <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Fee Receipt (Opt)</label>
               <input name="fee_receipt" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="OR Reference">
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Proposed LPTRP Route</label>
            <div class="relative">
              <select name="route_id" class="w-full pl-4 pr-10 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white">
                <option value="">Select route</option>
                <?php foreach ($routes as $r): ?>
                  <?php
                    $cap = (int)($r['max_vehicle_capacity'] ?? 0);
                    $curr = (int)($r['current_vehicle_count'] ?? 0);
                    $desc = (string)($r['route_desc'] ?? '');
                    if ($desc === '') {
                      $sp = (string)($r['start_point'] ?? '');
                      $ep = (string)($r['end_point'] ?? '');
                      $desc = ($sp !== '' || $ep !== '') ? trim($sp . ' → ' . $ep) : '';
                    }
                    $label = ($r['route_code'] ?? 'Route') . ' • ' . $desc . " ({$curr}/{$cap})";
                  ?>
                  <option value="<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
              <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
            </div>
          </div>
        </div>

        <!-- Document Uploads -->
        <div class="md:col-span-2 pt-4 border-t border-slate-100">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i data-lucide="paperclip" class="w-4 h-4 text-emerald-500"></i> Required Documents
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="group relative">
              <label class="block text-xs font-medium text-slate-500 mb-2">LTFRB Decision/Order</label>
              <div class="relative flex items-center justify-center w-full">
                  <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-200 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-emerald-50 hover:border-emerald-300 transition-all group-hover:shadow-sm">
                      <div class="flex flex-col items-center justify-center pt-5 pb-6">
                          <i data-lucide="upload-cloud" class="w-8 h-8 mb-2 text-slate-400 group-hover:text-emerald-500"></i>
                          <p class="text-xs text-slate-500 text-center px-2"><span class="font-semibold">Click to upload</span> PDF/Image</p>
                      </div>
                      <input name="doc_ltfrb" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="showFileName(this)">
                  </label>
              </div>
              <div class="file-name-display mt-2 text-xs text-emerald-600 font-medium text-center hidden"></div>
            </div>
            
            <div class="group relative">
              <label class="block text-xs font-medium text-slate-500 mb-2">Coop Registration</label>
              <div class="relative flex items-center justify-center w-full">
                  <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-200 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-emerald-50 hover:border-emerald-300 transition-all group-hover:shadow-sm">
                      <div class="flex flex-col items-center justify-center pt-5 pb-6">
                          <i data-lucide="file-badge" class="w-8 h-8 mb-2 text-slate-400 group-hover:text-emerald-500"></i>
                          <p class="text-xs text-slate-500 text-center px-2"><span class="font-semibold">Click to upload</span> PDF/Image</p>
                      </div>
                      <input name="doc_coop" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="showFileName(this)">
                  </label>
              </div>
              <div class="file-name-display mt-2 text-xs text-emerald-600 font-medium text-center hidden"></div>
            </div>
            
            <div class="group relative">
              <label class="block text-xs font-medium text-slate-500 mb-2">Member Vehicles List</label>
              <div class="relative flex items-center justify-center w-full">
                  <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-200 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-emerald-50 hover:border-emerald-300 transition-all group-hover:shadow-sm">
                      <div class="flex flex-col items-center justify-center pt-5 pb-6">
                          <i data-lucide="list" class="w-8 h-8 mb-2 text-slate-400 group-hover:text-emerald-500"></i>
                          <p class="text-xs text-slate-500 text-center px-2"><span class="font-semibold">Click to upload</span> PDF/Excel</p>
                      </div>
                      <input name="doc_members" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="showFileName(this)">
                  </label>
              </div>
              <div class="file-name-display mt-2 text-xs text-emerald-600 font-medium text-center hidden"></div>
            </div>
          </div>
          <?php if (getenv('TMM_AV_SCANNER')): ?>
            <p class="mt-4 text-[11px] text-slate-400 flex items-center gap-1"><i data-lucide="shield-check" class="w-3 h-3"></i> Files are scanned for viruses when uploaded.</p>
          <?php endif; ?>
        </div>

        <div class="md:col-span-2 flex flex-col md:flex-row items-center justify-between gap-4 border-t border-slate-200 dark:border-slate-700 pt-6">
          <div id="module2AppStatus" class="text-xs font-medium text-slate-500"></div>
          <button type="button" id="module2SubmitBtn" class="w-full md:w-auto px-6 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold shadow-sm transition-all active:scale-[0.98] flex items-center justify-center gap-2 text-sm">
            <span>Create Application</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-2">
      <i data-lucide="users" class="w-4 h-4 text-slate-500 dark:text-slate-300"></i>
      <h2 class="font-bold text-slate-900 dark:text-white text-sm">Operator & Cooperative</h2>
    </div>
    <div class="p-6 flex flex-col sm:flex-row gap-3">
      <button type="button" id="btnOpenOperatorModal" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-teal-50 dark:hover:bg-teal-900/20 text-slate-600 dark:text-slate-300 hover:text-teal-600 dark:hover:text-teal-400 text-sm font-bold rounded-lg transition-all">
        <i data-lucide="user-plus" class="w-4 h-4"></i>
        <span>Add Operator</span>
      </button>
      <button type="button" id="btnOpenCoopModal" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-teal-50 dark:hover:bg-teal-900/20 text-slate-600 dark:text-slate-300 hover:text-teal-600 dark:hover:text-teal-400 text-sm font-bold rounded-lg transition-all">
        <i data-lucide="building-2" class="w-4 h-4"></i>
        <span>Register Coop</span>
      </button>
    </div>
    <datalist id="coopNameList">
      <?php foreach ($coops as $c): ?>
        <option value="<?php echo htmlspecialchars((string)($c['coop_name'] ?? '')); ?>"></option>
      <?php endforeach; ?>
    </datalist>
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
                <input name="lgu_approval_number" class="w-full px-4 py-3 text-sm font-bold border-0 rounded-lg bg-slate-50 dark:bg-slate-800/50 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-teal-500 outline-none transition-all" placeholder="Required">
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

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-2">
      <i data-lucide="map" class="w-4 h-4 text-slate-500 dark:text-slate-300"></i>
      <h2 class="font-bold text-slate-900 dark:text-white text-sm">LPTRP Route Masterlist</h2>
    </div>
    <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
      <form id="module2LptrpForm" class="space-y-3 lg:col-span-1">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Route Code</label>
          <input name="route_code" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="e.g. ROUTE-01">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Route Name</label>
          <input name="description" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Optional">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Start Point</label>
          <input name="start_point" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Optional">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">End Point</label>
          <input name="end_point" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Optional">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Max Capacity</label>
            <input name="max_vehicle_capacity" type="number" min="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="0">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Approval</label>
            <div class="relative">
              <select name="status" class="w-full pl-4 pr-10 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white">
                <option value="Approved">Approved</option>
                <option value="Pending">Pending</option>
                <option value="Suspended">Suspended</option>
              </select>
              <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
            </div>
          </div>
        </div>
        <button type="button" id="module2LptrpSaveBtn" class="w-full px-6 py-2.5 rounded-md bg-emerald-700 hover:bg-emerald-800 text-white font-semibold shadow-sm transition-all active:scale-[0.98] flex items-center justify-center gap-2 text-sm">
          <span>Save Route</span>
          <i data-lucide="save" class="w-4 h-4"></i>
        </button>
      </form>

      <div class="lg:col-span-2 overflow-x-auto">
        <table class="min-w-full text-sm text-left border border-slate-100 rounded-xl overflow-hidden">
          <thead class="bg-slate-50 text-slate-500 font-medium border-b border-slate-100">
            <tr>
              <th class="py-2.5 px-3 text-xs uppercase tracking-wider">Route</th>
              <th class="py-2.5 px-3 text-xs uppercase tracking-wider">Name / Points</th>
              <th class="py-2.5 px-3 text-xs uppercase tracking-wider">Capacity</th>
              <th class="py-2.5 px-3 text-xs uppercase tracking-wider">Approval</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (!empty($routes)): ?>
              <?php foreach ($routes as $r): ?>
                <?php
                  $desc = (string)($r['route_desc'] ?? '');
                  if ($desc === '') {
                    $sp = (string)($r['start_point'] ?? '');
                    $ep = (string)($r['end_point'] ?? '');
                    $desc = ($sp !== '' || $ep !== '') ? trim($sp . ' → ' . $ep) : '';
                  }
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                  <td class="py-2.5 px-3 font-semibold text-slate-700"><?php echo htmlspecialchars((string)($r['route_code'] ?? '')); ?></td>
                  <td class="py-2.5 px-3 text-slate-600"><?php echo htmlspecialchars($desc); ?></td>
                  <td class="py-2.5 px-3 text-slate-600"><?php echo (int)($r['current_vehicle_count'] ?? 0); ?>/<?php echo (int)($r['max_vehicle_capacity'] ?? 0); ?></td>
                  <td class="py-2.5 px-3 text-slate-600"><?php echo htmlspecialchars((string)($r['route_status'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="py-6 text-center text-slate-400 text-sm">No LPTRP routes yet. Add your Caloocan routes here.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Cooperative Directory -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden lg:col-span-1 flex flex-col h-full">
      <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-2">
        <i data-lucide="users" class="w-4 h-4 text-slate-500 dark:text-slate-300"></i>
        <h2 class="font-bold text-slate-900 dark:text-white text-sm">Cooperative Status</h2>
      </div>
      <div class="overflow-y-auto max-h-[400px] p-2">
        <?php if (!empty($coops)): ?>
          <div class="space-y-2">
            <?php foreach ($coops as $c): ?>
              <?php $currentStatus = $c['consolidation_status'] ?? 'Not Consolidated'; ?>
              <div class="p-3 rounded-xl border border-slate-100 bg-white hover:border-emerald-100 hover:shadow-sm transition-all group">
                <div class="flex items-start justify-between mb-2">
                  <div class="font-medium text-sm text-slate-800"><?php echo htmlspecialchars($c['coop_name'] ?? ''); ?></div>
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide
                    <?php
                      if ($currentStatus === 'Consolidated') echo 'bg-emerald-50 text-emerald-600 border border-emerald-100';
                      elseif ($currentStatus === 'In Progress') echo 'bg-amber-50 text-amber-600 border border-amber-100';
                      else echo 'bg-slate-50 text-slate-500 border border-slate-100';
                    ?>
                  ">
                    <?php echo htmlspecialchars($currentStatus); ?>
                  </span>
                </div>
                
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-8 text-slate-400 text-xs">No cooperatives found.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Applications -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden lg:col-span-2 flex flex-col h-full">
      <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <i data-lucide="history" class="w-4 h-4 text-slate-500"></i>
          <h2 class="font-semibold text-slate-800 text-sm">Recent Applications</h2>
        </div>
        
        <form id="module2FilterForm" method="GET" class="flex items-center gap-2">
          <input type="hidden" name="page" value="module2/submodule1">
          <div class="relative">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400"></i>
            <input name="q" list="module2SearchList" value="<?php echo htmlspecialchars($q); ?>" class="pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg bg-white text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none w-40 md:w-56" placeholder="Search...">
            <?php
              $module2SearchOptions = [];
              $module2ResSearch = $db->query("SELECT DISTINCT fa.franchise_ref_number, o.full_name AS operator_name, c.coop_name FROM franchise_applications fa LEFT JOIN operators o ON fa.operator_id=o.id LEFT JOIN coops c ON fa.coop_id=c.id ORDER BY fa.submitted_at DESC LIMIT 100");
              if ($module2ResSearch) {
                while ($r = $module2ResSearch->fetch_assoc()) {
                  $ref = trim((string)($r['franchise_ref_number'] ?? ''));
                  $opn = trim((string)($r['operator_name'] ?? ''));
                  $coopn = trim((string)($r['coop_name'] ?? ''));
                  if ($ref !== '') $module2SearchOptions[$ref] = true;
                  if ($opn !== '') $module2SearchOptions[$opn] = true;
                  if ($coopn !== '') $module2SearchOptions[$coopn] = true;
                }
              }
            ?>
            <datalist id="module2SearchList">
              <?php foreach (array_keys($module2SearchOptions) as $opt): ?>
                <option value="<?php echo htmlspecialchars($opt); ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <select name="status" class="px-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900/50 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none">
            <option value="">Status</option>
            <?php
              $statuses = ['Pending','Under Review','Endorsed','Rejected'];
              foreach ($statuses as $st):
            ?>
              <option value="<?php echo $st; ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      
      <div class="overflow-x-auto flex-1">
        <table class="min-w-full text-sm text-left">
          <thead class="bg-slate-50 text-slate-500 font-medium border-b border-slate-100">
            <tr>
              <th class="py-3 px-4 text-xs uppercase tracking-wider">Reference</th>
              <th class="py-3 px-4 text-xs uppercase tracking-wider">Details</th>
              <th class="py-3 px-4 text-xs uppercase tracking-wider">Status</th>
              <th class="py-3 px-4 text-right text-xs uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if ($appsRes && $appsRes->num_rows > 0): ?>
              <?php while ($row = $appsRes->fetch_assoc()): ?>
                <?php
                  $ref = $row['franchise_ref_number'] ?? '';
                  $track = 'APP-' . (int)($row['application_id'] ?? 0);
                  $status = $row['status'] ?? 'Pending';
                  
                  $badgeClass = 'bg-slate-100 text-slate-600 border border-slate-200';
                  $dotClass = 'bg-slate-400';
                  
                  if ($status === 'Endorsed') {
                    $badgeClass = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                    $dotClass = 'bg-emerald-500';
                  } elseif ($status === 'Pending') {
                    $badgeClass = 'bg-amber-50 text-amber-700 border border-amber-100';
                    $dotClass = 'bg-amber-500';
                  } elseif ($status === 'Under Review') {
                    $badgeClass = 'bg-blue-50 text-blue-700 border border-blue-100';
                    $dotClass = 'bg-blue-500';
                  } elseif ($status === 'Rejected') {
                    $badgeClass = 'bg-rose-50 text-rose-700 border border-rose-100';
                    $dotClass = 'bg-rose-500';
                  }
                  
                  $lpStatus = $row['lptrp_status'] ?? '';
                  $coopStatus = $row['coop_status'] ?? '';
                  $routeCode = (string)($row['route_code'] ?? '');
                  $desc = (string)($row['route_desc'] ?? '');
                  if ($desc === '') {
                    $sp = (string)($row['start_point'] ?? '');
                    $ep = (string)($row['end_point'] ?? '');
                    $desc = ($sp !== '' || $ep !== '') ? trim($sp . ' → ' . $ep) : '';
                  }
                  $routeLabel = $routeCode !== '' ? ($routeCode . ($desc !== '' ? (' • ' . $desc) : '')) : (string)($row['route_ids'] ?? '');
                  $openHref = $ref !== '' ? '?page=module2/submodule2&q=' . urlencode($ref) : '';
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                  <td class="py-3 px-4 align-top">
                    <div class="font-medium text-slate-800"><?php echo htmlspecialchars($track); ?></div>
                    <div class="text-xs text-slate-500 font-mono mt-0.5"><?php echo htmlspecialchars($ref !== '' ? $ref : 'No Ref'); ?></div>
                  </td>
                  <td class="py-3 px-4 align-top">
                    <div class="font-medium text-slate-700 text-sm"><?php echo htmlspecialchars($row['coop_name'] ?? '—'); ?></div>
                    <div class="text-xs text-slate-500 mt-0.5">
                      Route: <span class="font-semibold text-slate-600"><?php echo htmlspecialchars($routeLabel !== '' ? $routeLabel : '—'); ?></span> • 
                      <?php echo (int)($row['vehicle_count'] ?? 0); ?> Units
                    </div>
                  </td>
                  <td class="py-3 px-4 align-top">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?php echo $dotClass; ?>"></span>
                      <?php echo htmlspecialchars($status); ?>
                    </span>
                    <div class="mt-1.5 flex gap-2">
                       <?php if ($lpStatus !== ''): ?>
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 border border-slate-200">LPTRP: <?php echo htmlspecialchars($lpStatus); ?></span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="py-3 px-4 align-top text-right">
                    <?php if ($openHref !== ''): ?>
                      <a href="<?php echo htmlspecialchars($openHref); ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-medium text-slate-600 hover:text-emerald-600 hover:border-emerald-200 hover:bg-emerald-50 transition-all shadow-sm">
                        <span>Validate</span>
                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                      </a>
                    <?php else: ?>
                      <span class="text-xs text-slate-400 italic">No Ref</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="py-8 text-center text-slate-400">
                  <div class="flex flex-col items-center gap-2">
                    <i data-lucide="inbox" class="w-8 h-8 stroke-1"></i>
                    <span class="text-sm">No franchise applications found.</span>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
// File Name Display Helper
function showFileName(input) {
  const display = input.parentElement.parentElement.nextElementSibling;
  if (input.files && input.files[0]) {
    display.textContent = input.files[0].name;
    display.classList.remove('hidden');
    // Change icon style to indicate success
    const icon = input.parentElement.querySelector('i');
    if(icon) {
        icon.classList.remove('text-slate-400');
        icon.classList.add('text-emerald-500');
        icon.setAttribute('data-lucide', 'check-circle');
        if(window.lucide) window.lucide.createIcons();
    }
  } else {
    display.textContent = '';
    display.classList.add('hidden');
  }
}

(function() {
  if (window.lucide) window.lucide.createIcons();

  function showToast(msg, type = 'success') {
    const container = document.getElementById('toast-container');
    if(!container) return;
    const toast = document.createElement('div');
    const colors = type === 'success' ? 'bg-emerald-500' : (type === 'error' ? 'bg-rose-500' : 'bg-amber-500');
    const icon = type === 'success' ? 'check-circle' : 'alert-circle';
    
    toast.className = `${colors} text-white px-4 py-3 rounded-xl shadow-lg shadow-black/5 flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px] backdrop-blur-md`;
    toast.innerHTML = `
      <i data-lucide="${icon}" class="w-5 h-5"></i>
      <span class="font-medium text-sm">${msg}</span>
    `;
    
    container.appendChild(toast);
    if (window.lucide) window.lucide.createIcons();
    requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
    setTimeout(() => { toast.classList.add('opacity-0', 'translate-x-full'); setTimeout(() => toast.remove(), 300); }, 3000);
  }
  window.showToast = showToast;

  var form = document.getElementById('module2AppForm');
  var btn = document.getElementById('module2SubmitBtn');
  var statusEl = document.getElementById('module2AppStatus');
  
  if (form && btn && statusEl) {
    btn.addEventListener('click', function() {
      if (btn.disabled) return;
      var coopId = form.elements['coop_id'] ? form.elements['coop_id'].value : '';
      
      // Basic validation
      if(!coopId) {
          showToast('Please select a cooperative', 'error');
          return;
      }

      var fd = new FormData(form);
      
      // Manually append files if needed, but FormData(form) handles it usually.
      // Explicitly check file inputs
      var docLtfrbInput = form.querySelector('input[name="doc_ltfrb"]');
      var docCoopInput = form.querySelector('input[name="doc_coop"]');
      var docMembersInput = form.querySelector('input[name="doc_members"]');

      btn.disabled = true;
      const originalBtnContent = btn.innerHTML;
      btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Processing...';
      if(window.lucide) window.lucide.createIcons();
      statusEl.textContent = 'Submitting application...';
      statusEl.className = 'text-xs font-medium text-slate-500';

      fetch('api/module2/save_application.php', {
        method: 'POST',
        body: fd
      })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data && data.ok) {
            var appId = data.application_id || 0;
            var hasLtfrb = docLtfrbInput && docLtfrbInput.files && docLtfrbInput.files.length > 0;
            var hasCoop = docCoopInput && docCoopInput.files && docCoopInput.files.length > 0;
            var hasMembers = docMembersInput && docMembersInput.files && docMembersInput.files.length > 0;
            var hasAnyDocs = hasLtfrb || hasCoop || hasMembers;

            if (appId && hasAnyDocs) {
              statusEl.textContent = 'Uploading documents...';
              
              var docsFd = new FormData();
              docsFd.append('application_id', appId);
              if (hasLtfrb) docsFd.append('doc_ltfrb', docLtfrbInput.files[0]);
              if (hasCoop) docsFd.append('doc_coop', docCoopInput.files[0]);
              if (hasMembers) docsFd.append('doc_members', docMembersInput.files[0]);

              return fetch('api/module2/upload_app_docs.php', {
                method: 'POST',
                body: docsFd
              })
                .then(function(r) { return r.json(); })
                .then(function(docRes) {
                  var hasErrors = docRes && Array.isArray(docRes.errors) && docRes.errors.length > 0;
                  if (hasErrors) {
                    showToast('Application saved, but some documents failed: ' + docRes.errors.join('; '), 'warning');
                  } else {
                    showToast('Application and documents submitted successfully!', 'success');
                  }
                  form.reset();
                  setTimeout(function() { window.location.reload(); }, 1500);
                });
            } else {
              showToast(data.message || 'Application submitted successfully!', 'success');
              form.reset();
              setTimeout(function() { window.location.reload(); }, 1000);
            }
          } else {
            showToast((data && data.error) ? data.error : 'Unable to submit application.', 'error');
          }
        })
        .catch(function(err) {
          showToast('Error: ' + err.message, 'error');
        })
        .finally(function() {
          btn.disabled = false;
          btn.innerHTML = originalBtnContent;
          if(window.lucide) window.lucide.createIcons();
          statusEl.textContent = '';
        });
    });
  }
})();

(function() {
  var lptrpForm = document.getElementById('module2LptrpForm');
  var lptrpBtn = document.getElementById('module2LptrpSaveBtn');

  if (lptrpForm && lptrpBtn) {
    lptrpBtn.addEventListener('click', function () {
      if (lptrpBtn.disabled) return;
      var fd = new FormData(lptrpForm);
      lptrpBtn.disabled = true;
      fetch((window.TMM_ROOT_URL || '') + '/admin/api/module2/save_lptrp_route.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.ok) {
            if (window.showToast) window.showToast('LPTRP route saved', 'success');
            lptrpForm.reset();
            setTimeout(function () { window.location.reload(); }, 700);
          } else {
            if (window.showToast) window.showToast((data && data.error) ? data.error : 'Failed to save route', 'error');
          }
        })
        .catch(function (e) { if (window.showToast) window.showToast('Error: ' + e.message, 'error'); })
        .finally(function () { lptrpBtn.disabled = false; });
    });
  }
})();

(function(){
  var form = document.getElementById('module2FilterForm');
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

(function () {
  var input = document.getElementById('ltfrbFranchiseRef');
  if (!input) return;
  function formatLtfrb(v) {
    var digits = String(v || '').replace(/\D/g, '').slice(0, 9);
    if (digits.length <= 4) return digits;
    return digits.slice(0, 4) + '-' + digits.slice(4);
  }
  input.addEventListener('input', function () {
    var next = formatLtfrb(input.value);
    if (input.value !== next) input.value = next;
  });
})();

(function () {
  function openModal(modalId, panelId) {
    var modal = document.getElementById(modalId);
    var panel = document.getElementById(panelId);
    if (!modal) return;
    modal.classList.remove('hidden');
    setTimeout(function () {
      modal.classList.remove('opacity-0');
      if (panel) {
        panel.classList.remove('scale-95');
        panel.classList.add('scale-100');
      }
    }, 10);
  }

  function closeModal(modalId, panelId) {
    var modal = document.getElementById(modalId);
    var panel = document.getElementById(panelId);
    if (!modal) return;
    modal.classList.add('opacity-0');
    if (panel) {
      panel.classList.remove('scale-100');
      panel.classList.add('scale-95');
    }
    setTimeout(function () {
      modal.classList.add('hidden');
    }, 200);
  }

  window.openOperatorFormModal = function () { openModal('operatorFormModal', 'operatorFormModalPanel'); };
  window.closeOperatorFormModal = function () { closeModal('operatorFormModal', 'operatorFormModalPanel'); };
  window.openCoopFormModal = function () { openModal('coopFormModal', 'coopFormModalPanel'); };
  window.closeCoopFormModal = function () { closeModal('coopFormModal', 'coopFormModalPanel'); };

  var btnOp = document.getElementById('btnOpenOperatorModal');
  if (btnOp) btnOp.addEventListener('click', function () {
    var form = document.getElementById('saveOperatorForm');
    if (form) form.reset();
    window.openOperatorFormModal();
  });

  var btnCoop = document.getElementById('btnOpenCoopModal');
  if (btnCoop) btnCoop.addEventListener('click', function () {
    var form = document.getElementById('saveCoopForm');
    if (form) form.reset();
    window.openCoopFormModal();
  });

  function bindAjaxForm(formId, submitBtnId, onSuccessClose) {
    var form = document.getElementById(formId);
    var btn = document.getElementById(submitBtnId);
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (btn && btn.disabled) return;
      var fd = new FormData(form);
      if (btn) btn.disabled = true;
      fetch(form.action, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.ok) {
            if (window.showToast) window.showToast('Saved successfully', 'success');
            if (typeof onSuccessClose === 'function') onSuccessClose();
            setTimeout(function () { window.location.reload(); }, 600);
          } else {
            if (window.showToast) window.showToast((data && data.error) ? data.error : 'Failed to save', 'error');
          }
        })
        .catch(function (err) {
          if (window.showToast) window.showToast('Error: ' + err.message, 'error');
        })
        .finally(function () {
          if (btn) btn.disabled = false;
        });
    });
  }

  bindAjaxForm('saveOperatorForm', 'btnSaveOperator', window.closeOperatorFormModal);
  bindAjaxForm('saveCoopForm', 'btnSaveCoop', window.closeCoopFormModal);

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' && e.key !== 'Esc') return;
    var opModal = document.getElementById('operatorFormModal');
    var coopModal = document.getElementById('coopFormModal');
    if (opModal && !opModal.classList.contains('hidden')) { window.closeOperatorFormModal(); return; }
    if (coopModal && !coopModal.classList.contains('hidden')) { window.closeCoopFormModal(); return; }
  });

  function bindBackdropClose(modalId, closeFn) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeFn();
    });
  }
  bindBackdropClose('operatorFormModal', window.closeOperatorFormModal);
  bindBackdropClose('coopFormModal', window.closeCoopFormModal);
})();

</script>
