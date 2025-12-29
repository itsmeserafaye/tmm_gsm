<?php
  require_once __DIR__ . '/../../includes/db.php';
  $db = db();
?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Franchise Application & Cooperative Management</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Intake and tracking of franchise endorsement applications, cooperative profiles, consolidation status, and documentation.</p>

  <!-- Toast Notification Container -->
  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

  <!-- Search & Filter -->
  <div class="mb-8 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-center gap-2">
        <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg text-purple-600 dark:text-purple-400">
          <i data-lucide="folder-search" class="w-5 h-5"></i>
        </div>
        <h2 class="font-semibold text-slate-800 dark:text-slate-100">Application Registry</h2>
      </div>
      <form class="flex items-center gap-2 w-full md:w-auto" method="GET">
        <input type="hidden" name="page" value="module2/submodule1">
        <div class="relative flex-1 md:w-64">
          <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
          <input name="q" value="<?php echo htmlspecialchars($_GET['q']??''); ?>" class="w-full pl-9 pr-4 py-2 text-sm border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all" placeholder="Search Ref or Operator...">
        </div>
        <select name="status" class="px-3 py-2 text-sm border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all">
          <option value="">Status</option>
          <option value="Pending" <?php echo ($_GET['status']??'')==='Pending'?'selected':''; ?>>Pending</option>
          <option value="Under Review" <?php echo ($_GET['status']??'')==='Under Review'?'selected':''; ?>>Under Review</option>
          <option value="Endorsed" <?php echo ($_GET['status']??'')==='Endorsed'?'selected':''; ?>>Endorsed</option>
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
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Tracking #</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Operator</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Cooperative</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Franchise Ref</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Units</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Status</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
          <?php
            $q = trim($_GET['q'] ?? '');
            $st = trim($_GET['status'] ?? '');
            $sql = "SELECT fa.*, o.full_name, c.coop_name 
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
                'Under Review' => 'bg-yellow-100 text-yellow-700 ring-1 ring-yellow-600/20',
                'Rejected' => 'bg-red-100 text-red-700 ring-1 ring-red-600/20',
                default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-600/20'
              };
          ?>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="py-3 px-4 font-medium text-slate-900 dark:text-slate-100">APP-<?php echo str_pad($row['application_id'], 4, '0', STR_PAD_LEFT); ?></td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($row['full_name']); ?></td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($row['coop_name'] ?? '-'); ?></td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($row['franchise_ref_number']); ?></td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><?php echo $row['vehicle_count']; ?></td>
            <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?php echo $sBadge; ?>"><?php echo $row['status']; ?></span></td>
            <td class="py-3 px-4 text-center">
              <div class="flex items-center justify-center gap-2">
                <button title="View Details" class="p-2 rounded-full text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"><i data-lucide="eye" class="w-4 h-4"></i></button>
                <a href="?page=module2/submodule2&id=<?php echo $row['application_id']; ?>" title="Process" class="p-2 rounded-full text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors"><i data-lucide="arrow-right-circle" class="w-4 h-4"></i></a>
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
    <!-- Application Form -->
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2"><i data-lucide="plus-circle" class="w-5 h-5 text-blue-500"></i> Submit New Application</h2>
      <form id="createAppForm" class="space-y-4">
        <div>
           <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Operator Name</label>
           <input name="operator_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" placeholder="Full Name" required>
        </div>
        <div>
           <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Cooperative (Optional)</label>
           <input name="coop_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" placeholder="Coop Name">
        </div>
        <div class="grid grid-cols-2 gap-4">
           <div>
             <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Franchise Ref No.</label>
             <input name="franchise_ref" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all uppercase" placeholder="FR-XXXX" required>
           </div>
           <div>
             <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Vehicle Count</label>
             <input name="vehicle_count" type="number" min="1" value="1" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
           </div>
        </div>
        <div class="pt-2">
          <button type="submit" id="btnSubmitApp" class="flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg w-full transition-colors shadow-sm shadow-blue-500/30">
            <span>Create Application</span>
            <i data-lucide="send" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>

    <!-- Uploads -->
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-slate-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2"><i data-lucide="upload-cloud" class="w-5 h-5 text-slate-500"></i> Initial Requirements</h2>
      <div class="space-y-4">
         <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-dashed border-slate-300 dark:border-slate-700 text-center">
            <i data-lucide="file-text" class="w-8 h-8 text-slate-400 mx-auto mb-2"></i>
            <p class="text-sm text-slate-600 dark:text-slate-400">Drag and drop LTFRB Endorsement or click to browse</p>
         </div>
         <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-dashed border-slate-300 dark:border-slate-700 text-center">
            <i data-lucide="users" class="w-8 h-8 text-slate-400 mx-auto mb-2"></i>
            <p class="text-sm text-slate-600 dark:text-slate-400">Drag and drop Cooperative Certification</p>
         </div>
         <button disabled class="w-full py-2 bg-slate-100 dark:bg-slate-800 text-slate-400 rounded-lg text-sm font-medium cursor-not-allowed">Upload Documents (Create App First)</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // Toast Helper
  function showToast(msg, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const colors = type === 'success' ? 'bg-green-500' : (type === 'error' ? 'bg-red-500' : 'bg-blue-500');
    const icon = type === 'success' ? 'check-circle' : (type === 'error' ? 'alert-circle' : 'info');
    
    toast.className = `${colors} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px] z-50`;
    toast.innerHTML = `
      <i data-lucide="${icon}" class="w-5 h-5"></i>
      <span class="font-medium text-sm">${msg}</span>
    `;
    
    container.appendChild(toast);
    if (window.lucide) window.lucide.createIcons();
    
    requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
    setTimeout(() => {
      toast.classList.add('opacity-0', 'translate-x-full');
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // Handle Form
  const form = document.getElementById('createAppForm');
  const btn = document.getElementById('btnSubmitApp');
  
  if(form){
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Processing...';
      btn.disabled = true;
      if (window.lucide) window.lucide.createIcons();

      try {
        const formData = new FormData(this);
        const res = await fetch('/tmm/admin/api/franchise/apply.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        
        if(data.ok) {
          showToast('Application submitted successfully!');
          this.reset();
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showToast(data.error || 'Submission failed', 'error');
        }
      } catch(err) {
        showToast('Network error occurred', 'error');
        console.error(err);
      } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
        if (window.lucide) window.lucide.createIcons();
      }
    });
  }
})();
</script>
