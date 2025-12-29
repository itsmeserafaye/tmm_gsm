<?php
  require_once __DIR__ . '/../../includes/db.php';
  $db = db();
?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Operator, Cooperative & Franchise Validation</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Maintains operator and cooperative profiles and validates franchise references through cross-checks with Franchise Management.</p>

  <!-- Toast Notification Container -->
  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

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
    <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-center gap-2">
        <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg text-purple-600 dark:text-purple-400">
          <i data-lucide="folder-open" class="w-5 h-5"></i>
        </div>
        <h2 class="font-semibold text-slate-800 dark:text-slate-100">Franchise Applications</h2>
      </div>
      <form class="flex items-center gap-2 w-full md:w-auto" method="GET">
        <input type="hidden" name="page" value="module1/submodule2">
        <div class="relative flex-1 md:w-64">
          <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
          <input name="q" value="<?php echo htmlspecialchars($_GET['q']??''); ?>" class="w-full pl-9 pr-4 py-2 text-sm border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all" placeholder="Search Ref or Operator...">
        </div>
        <select name="status" class="px-3 py-2 text-sm border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all">
          <option value="">Status</option>
          <option value="Pending" <?php echo ($_GET['status']??'')==='Pending'?'selected':''; ?>>Pending</option>
          <option value="Endorsed" <?php echo ($_GET['status']??'')==='Endorsed'?'selected':''; ?>>Endorsed</option>
          <option value="Rejected" <?php echo ($_GET['status']??'')==='Rejected'?'selected':''; ?>>Rejected</option>
        </select>
        <button type="submit" class="p-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
          <i data-lucide="filter" class="w-4 h-4"></i>
        </button>
      </form>
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
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($row['operator']); ?></td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($row['coop_name'] ?? '-'); ?></td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><?php echo $row['vehicle_count']; ?></td>
            <td class="py-3 px-4 text-slate-500 text-xs"><?php echo date('M d, Y', strtotime($row['submitted_at'])); ?></td>
            <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?php echo $sBadge; ?>"><?php echo $row['status']; ?></span></td>
            <td class="py-3 px-4 text-center">
              <div class="flex items-center justify-center gap-2">
                <button title="View Details" class="p-2 rounded-full text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"><i data-lucide="eye" class="w-4 h-4"></i></button>
                <button title="Update Status" class="p-2 rounded-full text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
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

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm">
      <h3 class="text-md font-semibold mb-4 flex items-center gap-2"><i data-lucide="file-text" class="w-5 h-5 text-blue-500"></i> Franchise Details</h3>
      <div id="franchiseResult" class="text-sm text-slate-500 italic py-4 text-center">Enter Franchise ID above to validate details.</div>
    </div>

    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-emerald-500 shadow-sm">
      <h3 class="text-md font-semibold mb-4 flex items-center gap-2"><i data-lucide="file-plus" class="w-5 h-5 text-emerald-500"></i> New Application Intake</h3>
      <form id="franchiseApplyForm" class="space-y-3" method="POST" action="/tmm/admin/api/franchise/apply.php">
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
            <input name="coop_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Coop Name (Opt)">
          </div>
        </div>
        <div>
           <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Requested Units</label>
           <input name="vehicle_count" type="number" min="1" value="1" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
        </div>
        <button type="submit" id="btnApply" class="flex items-center justify-center gap-2 px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg w-full transition-colors shadow-sm shadow-emerald-500/30">
          <span>Submit Application</span>
          <i data-lucide="send" class="w-4 h-4"></i>
        </button>
      </form>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-1 gap-6 mt-6">
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-teal-500 shadow-sm">
      <h3 class="text-md font-semibold mb-4 flex items-center gap-2"><i data-lucide="users" class="w-5 h-5 text-teal-500"></i> Operator & Cooperative</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 pb-6 border-b border-slate-100 dark:border-slate-800">
        <div class="flex gap-2">
          <input id="opViewName" class="flex-1 px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Operator full name">
          <button id="opViewBtn" class="px-4 py-2 border rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"><i data-lucide="eye" class="w-5 h-5 text-slate-500"></i></button>
        </div>
        <div class="flex gap-2">
          <input id="coopViewName" class="flex-1 px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Cooperative name">
          <button id="coopViewBtn" class="px-4 py-2 border rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"><i data-lucide="eye" class="w-5 h-5 text-slate-500"></i></button>
        </div>
      </div>

      <div class="space-y-6">
        <form id="saveOperatorForm" class="space-y-3" method="POST" action="/tmm/admin/api/module1/save_operator.php">
          <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">Add New Operator</h4>
          <div class="grid grid-cols-1 gap-3">
            <input name="full_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Full Name" required>
            <div class="grid grid-cols-2 gap-3">
              <input name="contact_info" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Contact" required>
              <input name="coop_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Cooperative">
            </div>
          </div>
          <button type="submit" id="btnSaveOperator" class="flex items-center justify-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded-lg w-full transition-colors shadow-sm shadow-teal-500/30">
            <span>Save Operator</span>
            <i data-lucide="save" class="w-4 h-4"></i>
          </button>
        </form>

        <form id="saveCoopForm" class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800" method="POST" action="/tmm/admin/api/module1/save_coop.php">
          <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">Register Cooperative</h4>
          <div class="grid grid-cols-1 gap-3">
            <input name="coop_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Cooperative Name" required>
            <input name="address" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Address" required>
            <div class="grid grid-cols-2 gap-3">
              <input name="chairperson_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Chairperson" required>
              <input name="lgu_approval_number" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="LGU Approval No.">
            </div>
          </div>
          <button type="submit" id="btnSaveCoop" class="flex items-center justify-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded-lg w-full transition-colors shadow-sm shadow-teal-500/30">
            <span>Save Cooperative</span>
            <i data-lucide="save" class="w-4 h-4"></i>
          </button>
        </form>

        <form id="linkVehicleForm" class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800" method="POST" action="/tmm/admin/api/module1/link_vehicle_operator.php">
          <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">Link Vehicle</h4>
          <div class="grid grid-cols-1 gap-3">
            <input name="plate_number" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all uppercase" placeholder="Plate number" required>
            <div class="grid grid-cols-2 gap-3">
              <input name="operator_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Operator Name" required>
              <input name="coop_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" placeholder="Coop Name">
            </div>
          </div>
          <button type="submit" id="btnLinkVehicle" class="flex items-center justify-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded-lg w-full transition-colors shadow-sm shadow-teal-500/30">
            <span>Link to Vehicle</span>
            <i data-lucide="link" class="w-4 h-4"></i>
          </button>
        </form>
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
      // Toast System
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
      
      document.getElementById('opViewBtn').addEventListener('click', function(){
        var name = document.getElementById('opViewName').value.trim();
        if (!name) return;
        fetch('/tmm/admin/api/module1/operator_html.php?name='+encodeURIComponent(name)).then(r=>r.text()).then(open);
      });
      document.getElementById('coopViewBtn').addEventListener('click', function(){
        var name = document.getElementById('coopViewName').value.trim();
        if (!name) return;
        fetch('/tmm/admin/api/module1/coop_html.php?name='+encodeURIComponent(name)).then(r=>r.text()).then(open);
      });

      // Generic Form Handler
      function handleForm(formId, btnId, successMsg) {
        const form = document.getElementById(formId);
        const btn = document.getElementById(btnId);
        if(!form || !btn) return;

        form.addEventListener('submit', async function(e) {
          e.preventDefault();
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
              setTimeout(() => location.reload(), 1000);
            } else {
              showToast(data.error || 'Operation failed', 'error');
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

      handleForm('franchiseApplyForm', 'btnApply', 'Application Submitted Successfully!');
      handleForm('saveOperatorForm', 'btnSaveOperator', 'Operator Saved!');
      handleForm('saveCoopForm', 'btnSaveCoop', 'Cooperative Saved!');
      handleForm('linkVehicleForm', 'btnLinkVehicle', 'Vehicle Linked Successfully!');

    })();
  </script>
</div>
