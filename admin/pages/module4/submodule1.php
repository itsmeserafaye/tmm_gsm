<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Vehicle Verification & Inspection Scheduling</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Upload and verify CR/OR, check PUV and Franchise records, and schedule inspections with authorized inspectors.</p>
  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

  <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-amber-500 shadow-sm mb-6">
    <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="file-check" class="w-5 h-5 text-amber-500"></i> LTO Document Upload & Verification</h2>
    <form id="verifyForm" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <input name="plate_number" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 uppercase focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all" placeholder="Plate number">
      <input name="operator_id" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all" placeholder="Operator ID">
      <div class="hidden md:block"></div>
      <div>
        <label class="block text-sm mb-1 text-slate-600 dark:text-slate-400">CR Document</label>
        <input name="cr" type="file" class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 dark:file:bg-amber-900/30 dark:file:text-amber-400">
      </div>
      <div>
        <label class="block text-sm mb-1 text-slate-600 dark:text-slate-400">OR Document</label>
        <input name="or" type="file" class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 dark:file:bg-amber-900/30 dark:file:text-amber-400">
      </div>
      <div class="md:col-span-1 flex items-end">
        <button type="submit" class="w-full px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-medium rounded-lg transition-colors shadow-sm">Verify Documents</button>
      </div>
    </form>
    <div class="mt-4 text-sm flex items-center gap-2">
      <span class="text-slate-500">Verification Status:</span> 
      <span id="verifyStatus" class="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 ring-1 ring-slate-400/30">Pending</span>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="database" class="w-5 h-5 text-blue-500"></i> Cross-Checks</h2>
      <div class="grid grid-cols-1 gap-3">
        <button class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-left text-sm flex items-center justify-between group">
          <span>Check Vehicle Record</span>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-blue-500"></i>
        </button>
        <button class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-left text-sm flex items-center justify-between group">
          <span>Check Franchise Status</span>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-blue-500"></i>
        </button>
        <button class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-left text-sm flex items-center justify-between group">
          <span>Validate Inspector Auth</span>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-blue-500"></i>
        </button>
      </div>
    </div>
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-emerald-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="calendar-clock" class="w-5 h-5 text-emerald-500"></i> Inspection Scheduling</h2>
      <form id="scheduleForm" class="space-y-3">
        <input name="plate_number" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 uppercase focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Plate number">
        <input type="datetime-local" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
        <input name="location" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Location">
        <select name="inspector_id" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
          <option value="">Select Inspector</option>
          <option value="1">Inspector Dela Cruz</option>
          <option value="2">Inspector Santos</option>
        </select>
        <button type="submit" class="w-full px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white font-medium rounded-lg transition-colors shadow-sm">Schedule Inspection</button>
      </form>
      <div class="mt-4 text-sm flex items-center gap-2">
        <span class="text-slate-500">Schedule Status:</span> 
        <span id="scheduleStatus" class="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 ring-1 ring-slate-400/30">Pending</span>
        <span id="docFlags" class="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 ring-1 ring-slate-400/30">CR: -, OR: -</span>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  function showToast(msg, type='success'){
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const colors = type === 'success' ? 'bg-emerald-500' : (type === 'error' ? 'bg-red-500' : 'bg-blue-500');
    toast.className = colors + " text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px]";
    toast.innerHTML = '<span class="font-medium text-sm">'+msg+'</span>';
    container.appendChild(toast);
    requestAnimationFrame(()=>toast.classList.remove('translate-y-10','opacity-0'));
    setTimeout(()=>{
      toast.classList.add('opacity-0','translate-y-10');
      setTimeout(()=>toast.remove(), 300);
    },3000);
  }
  
  // Re-initialize Lucide icons if the library is available
  if(window.lucide) window.lucide.createIcons();

  const verifyForm = document.getElementById('verifyForm');
  verifyForm?.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(verifyForm);
    const plateInput = verifyForm.querySelector('input[name="plate_number"]');
    const plate = (plateInput?.value || '').toUpperCase().trim();
    try {
      const uploadRes = await fetch('/tmm/admin/api/module1/upload_docs.php', { method: 'POST', body: fd });
      let uploadData = {};
      try { uploadData = await uploadRes.json(); } catch(_) {}
      const verFd = new URLSearchParams({ plate_number: plate });
      const verRes = await fetch('/tmm/admin/api/module4/verify_documents.php', { method: 'POST', body: verFd });
      const verData = await verRes.json();
      if (verData.ok) {
        const v = document.getElementById('verifyStatus');
        if (v) {
          v.className = 'px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/20';
          v.innerText = 'Verified';
        }
        showToast('CR/OR verified successfully');
      } else {
        const err = verData.error || uploadData.error || 'Verification failed';
        showToast(err, 'error');
      }
    } catch(err) {
      showToast('Network error', 'error');
    }
  });

  const scheduleForm = document.getElementById('scheduleForm');
  scheduleForm?.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(scheduleForm);
    const dtInput = scheduleForm.querySelector('input[type="datetime-local"]');
    fd.append('scheduled_at', dtInput?.value || '');
    try {
      const res = await fetch('/tmm/admin/api/module4/schedule_inspection.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        const s = document.getElementById('scheduleStatus');
        if (s) { 
            s.className = 'px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/20';
            s.innerText = 'Scheduled: ' + (data.scheduled_at || ''); 
        }
        const d = document.getElementById('docFlags');
        if (d) {
          const cr = data.cr_verified === 1 ? '✓' : '×';
          const or = data.or_verified === 1 ? '✓' : '×';
          d.className = (data.cr_verified === 1 && data.or_verified === 1)
            ? 'px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/20'
            : 'px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 ring-1 ring-amber-600/20';
          d.innerText = 'CR: ' + cr + ', OR: ' + or;
        }
        showToast('Inspection scheduled successfully');
      } else {
        showToast(data.error || 'Scheduling failed', 'error');
      }
    } catch(err) {
      showToast('Network error', 'error');
    }
  });
})();
</script>
