<?php
  require_once __DIR__ . '/../../includes/db.php';
  $db = db();
?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Validation, Endorsement & Compliance Engine</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Document verification, LPTRP capacity enforcement, endorsement generation, and compliance workflows.</p>

  <!-- Toast Container -->
  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Validation Section -->
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-orange-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2"><i data-lucide="search-check" class="w-5 h-5 text-orange-500"></i> Validate Application</h2>
      <form id="validateForm" class="space-y-4">
        <div>
           <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Franchise Reference / App ID</label>
           <div class="flex gap-2">
             <input name="ref_number" id="val_ref" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all uppercase" placeholder="FR-XXXX or ID" required>
             <button type="submit" id="btnValidate" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors"><i data-lucide="search" class="w-4 h-4"></i></button>
           </div>
        </div>
        
        <div id="validationResult" class="hidden space-y-3 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-200 dark:border-slate-700">
           <div class="flex justify-between items-center pb-2 border-b border-slate-200 dark:border-slate-700">
             <span class="font-semibold text-sm">Validation Status</span>
             <span id="valStatusBadge" class="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-200 text-slate-700">Checking...</span>
           </div>
           <div id="valDetails" class="space-y-2 text-sm">
             <!-- Dynamic Content -->
           </div>
        </div>
      </form>
    </div>

    <!-- Endorsement Generation -->
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-emerald-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2"><i data-lucide="file-signature" class="w-5 h-5 text-emerald-500"></i> Generate Endorsement / Permit</h2>
      <form id="endorseForm" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Target Application ID</label>
          <input name="app_id" id="endorse_app_id" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="ID" required>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Issue Type</label>
          <select name="issue_type" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
            <option value="Endorsement">Endorsement to LTFRB</option>
            <option value="Permit">Local Provisional Permit</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Generated Permit #</label>
          <input name="permit_no" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all uppercase" placeholder="PER-2025-XXXX" required>
        </div>
        <button type="submit" id="btnEndorse" class="flex items-center justify-center gap-2 px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-medium rounded-lg w-full transition-colors shadow-sm shadow-emerald-500/30">
          <span>Generate & Save</span>
          <i data-lucide="printer" class="w-4 h-4"></i>
        </button>
      </form>
    </div>
  </div>

  <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-red-500 shadow-sm mt-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold flex items-center gap-2"><i data-lucide="gavel" class="w-5 h-5 text-red-500"></i> Compliance Cases</h2>
      <button class="px-3 py-1.5 text-sm bg-red-50 text-red-600 rounded-lg border border-red-200 hover:bg-red-100 transition-colors">Report Violation</button>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
      <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded border border-slate-200 dark:border-slate-700">
        <div class="text-xs text-slate-500 uppercase">Active Cases</div>
        <div class="text-2xl font-bold text-slate-800 dark:text-slate-200">
          <?php echo $db->query("SELECT COUNT(*) as c FROM compliance_cases WHERE status='Open'")->fetch_assoc()['c'] ?? 0; ?>
        </div>
      </div>
      <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded border border-slate-200 dark:border-slate-700">
        <div class="text-xs text-slate-500 uppercase">Resolved (30d)</div>
        <div class="text-2xl font-bold text-slate-800 dark:text-slate-200">
           <?php echo $db->query("SELECT COUNT(*) as c FROM compliance_cases WHERE status='Resolved'")->fetch_assoc()['c'] ?? 0; ?>
        </div>
      </div>
    </div>
    
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
          <tr>
             <th class="py-2 px-3 text-left">Case ID</th>
             <th class="py-2 px-3 text-left">Ref Number</th>
             <th class="py-2 px-3 text-left">Violation</th>
             <th class="py-2 px-3 text-left">Status</th>
             <th class="py-2 px-3 text-left">Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
          <?php
            $res = $db->query("SELECT * FROM compliance_cases ORDER BY reported_at DESC LIMIT 5");
            if($res->num_rows > 0):
              while($r = $res->fetch_assoc()):
          ?>
          <tr>
            <td class="py-2 px-3">CASE-<?php echo $r['case_id']; ?></td>
            <td class="py-2 px-3"><?php echo htmlspecialchars($r['franchise_ref_number']); ?></td>
            <td class="py-2 px-3"><?php echo htmlspecialchars($r['violation_type']); ?></td>
            <td class="py-2 px-3"><span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-700"><?php echo $r['status']; ?></span></td>
            <td class="py-2 px-3"><?php echo date('M d', strtotime($r['reported_at'])); ?></td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="5" class="py-4 text-center text-slate-500">No active compliance cases.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function(){
  function showToast(msg, type='success'){
     const container = document.getElementById('toast-container');
     const toast = document.createElement('div');
     const colors = type === 'success' ? 'bg-green-500' : (type === 'error' ? 'bg-red-500' : 'bg-blue-500');
     toast.className = `${colors} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px] z-50`;
     toast.innerHTML = `<span class="font-medium text-sm">${msg}</span>`;
     container.appendChild(toast);
     requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
     setTimeout(() => toast.remove(), 3000);
  }

  // Real Validation
  document.getElementById('validateForm')?.addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = document.getElementById('btnValidate');
    const input = document.getElementById('val_ref');
    const resultDiv = document.getElementById('validationResult');
    const statusBadge = document.getElementById('valStatusBadge');
    const detailsDiv = document.getElementById('valDetails');
    
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
    if(window.lucide) window.lucide.createIcons();

    try {
      const res = await fetch('/tmm/admin/api/franchise/validate.php?ref=' + encodeURIComponent(input.value));
      const data = await res.json();
      
      resultDiv.classList.remove('hidden');
      
      if(data.found) {
         statusBadge.textContent = data.data.status;
         if(data.data.status === 'Endorsed') {
             statusBadge.className = 'px-2 py-0.5 rounded-full text-xs font-bold bg-green-200 text-green-800';
         } else if(data.data.status === 'Pending') {
             statusBadge.className = 'px-2 py-0.5 rounded-full text-xs font-bold bg-yellow-200 text-yellow-800';
         } else {
             statusBadge.className = 'px-2 py-0.5 rounded-full text-xs font-bold bg-slate-200 text-slate-700';
         }
         
         detailsDiv.innerHTML = `
           <ul class="space-y-2">
             <li class="flex items-center gap-2"><i data-lucide="user" class="w-4 h-4 text-slate-500"></i> <span>${data.data.operator}</span></li>
             <li class="flex items-center gap-2"><i data-lucide="users" class="w-4 h-4 text-slate-500"></i> <span>${data.data.coop}</span></li>
             <li class="flex items-center gap-2"><i data-lucide="car" class="w-4 h-4 text-slate-500"></i> <span>${data.data.vehicle_count} Unit(s)</span></li>
           </ul>
         `;
         
         // Auto-fill endorse form if valid
         if(data.data.status === 'Pending' || data.data.status === 'Under Review' || data.data.status === 'Endorsed') {
            document.getElementById('endorse_app_id').value = data.data.application_id;
         }
      } else {
         statusBadge.textContent = 'Not Found';
         statusBadge.className = 'px-2 py-0.5 rounded-full text-xs font-bold bg-red-200 text-red-800';
         detailsDiv.innerHTML = '<p class="text-red-500">No application found with this reference.</p>';
      }
    } catch(err) {
      showToast('Validation error', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="search" class="w-4 h-4"></i>';
      if(window.lucide) window.lucide.createIcons();
    }
  });

  // Real Endorsement
  document.getElementById('endorseForm')?.addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = document.getElementById('btnEndorse');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Processing...';
    if(window.lucide) window.lucide.createIcons();
    
    try {
      const formData = new FormData(this);
      const res = await fetch('/tmm/admin/api/franchise/endorse.php', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      
      if(data.ok) {
        showToast('Endorsement generated successfully!');
        this.reset();
        // Optional: refresh page or update validation status
      } else {
        showToast(data.error || 'Failed to generate endorsement', 'error');
      }
    } catch(err) {
      showToast('Network error', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
      if(window.lucide) window.lucide.createIcons();
    }
  });
})();
</script>