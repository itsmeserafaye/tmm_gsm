<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Inspection Execution & Certification</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Use checklist to record inspection findings, attach photos, and issue city inspection certificates.</p>
  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-indigo-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2"><i data-lucide="clipboard-check" class="w-5 h-5 text-indigo-500"></i> Inspection Checklist</h2>
      <div class="space-y-3 text-sm">
        <div class="flex items-center justify-between p-2 rounded hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-700">
          <span class="font-medium">Lights & Horn</span>
          <select name="item_LIGHTS" class="border rounded px-2 py-1 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none">
            <option value="Pass">Pass</option>
            <option value="Fail">Fail</option>
            <option value="NA">N/A</option>
          </select>
        </div>
        <div class="flex items-center justify-between p-2 rounded hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-700">
          <span class="font-medium">Brakes</span>
          <select name="item_BRAKES" class="border rounded px-2 py-1 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none">
            <option value="Pass">Pass</option>
            <option value="Fail">Fail</option>
            <option value="NA">N/A</option>
          </select>
        </div>
        <div class="flex items-center justify-between p-2 rounded hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-700">
          <span class="font-medium">Emission & Smoke Test</span>
          <select name="item_EMISSION" class="border rounded px-2 py-1 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none">
            <option value="Pass">Pass</option>
            <option value="Fail">Fail</option>
            <option value="NA">N/A</option>
          </select>
        </div>
        <div class="flex items-center justify-between p-2 rounded hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-700">
          <span class="font-medium">Tires & Wipers</span>
          <select name="item_TIRES" class="border rounded px-2 py-1 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none">
            <option value="Pass">Pass</option>
            <option value="Fail">Fail</option>
            <option value="NA">N/A</option>
          </select>
        </div>
        <div class="flex items-center justify-between p-2 rounded hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-700">
          <span class="font-medium">Interior Safety</span>
          <select name="item_INTERIOR" class="border rounded px-2 py-1 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none">
            <option value="Pass">Pass</option>
            <option value="Fail">Fail</option>
            <option value="NA">N/A</option>
          </select>
        </div>
        <div class="flex items-center justify-between p-2 rounded hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-700">
          <span class="font-medium">Documents & Plate</span>
          <select name="item_DOCS" class="border rounded px-2 py-1 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none">
            <option value="Pass">Pass</option>
            <option value="Fail">Fail</option>
            <option value="NA">N/A</option>
          </select>
        </div>
      </div>
      <div class="mt-4 pt-4 border-t dark:border-slate-700">
        <label class="block text-sm mb-2 font-medium text-slate-700 dark:text-slate-300">Evidence Photos</label>
        <input id="photoInput" name="photo" type="file" class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/30 dark:file:text-indigo-400 cursor-pointer">
      </div>
    </div>
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-emerald-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2"><i data-lucide="award" class="w-5 h-5 text-emerald-500"></i> Result & Certificate</h2>
      <form id="checklistForm" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1 uppercase">Vehicle Identification</label>
          <input name="plate_number" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 uppercase focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Plate number (e.g. ABC 123)">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1 uppercase">Inspection Outcome</label>
          <select name="overall_status" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
            <option value="">Auto-compute</option>
            <option value="Passed">Passed</option>
            <option value="Failed">Failed</option>
            <option value="Pending">Pending</option>
            <option value="For Reinspection">For Reinspection</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1 uppercase">Inspector Notes</label>
          <textarea name="remarks" rows="3" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Enter detailed remarks here..."></textarea>
        </div>
        <button type="submit" class="w-full px-4 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-medium rounded-lg transition-colors shadow-sm flex items-center justify-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i> Submit Checklist & Issue Certificate
        </button>
      </form>
      <div class="mt-4 flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border dark:border-slate-700">
        <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Certificate No.</span>
        <span id="certStatus" class="px-3 py-1 rounded-full text-xs font-bold bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300">Not Issued</span>
      </div>
      <div id="reinspectSection" class="mt-4 hidden">
        <div class="p-4 rounded-lg border dark:border-slate-700 bg-amber-50 dark:bg-amber-900/20">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-amber-700 dark:text-amber-300 flex items-center gap-2"><i data-lucide="alert-triangle" class="w-4 h-4"></i> Reinspection Required</h3>
            <span id="reinspectBadge" class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-200 text-amber-800">Failed</span>
          </div>
          <form id="reinspectForm" class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input name="plate_number" id="reinspectPlate" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 uppercase" placeholder="Plate number">
            <input type="datetime-local" id="reinspectDate" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700">
            <input name="location" id="reinspectLoc" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700" placeholder="Location">
            <select name="inspector_id" id="reinspectInspector" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700">
              <option value="">Select Inspector</option>
              <option value="1">Inspector Dela Cruz</option>
              <option value="2">Inspector Santos</option>
            </select>
            <div class="md:col-span-2">
              <button type="submit" class="w-full px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg transition-colors">Schedule Reinspection</button>
            </div>
          </form>
        </div>
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

  const form = document.getElementById('checklistForm');
  form?.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(form);
    const selects = [
      ['item_LIGHTS', 'Lights & Horn'],
      ['item_BRAKES', 'Brakes'],
      ['item_EMISSION', 'Emission & Smoke Test'],
      ['item_TIRES', 'Tires & Wipers'],
      ['item_INTERIOR', 'Interior Safety'],
      ['item_DOCS', 'Documents & Plate']
    ];
    selects.forEach(([name])=>{
      const el = document.querySelector('select[name="'+name+'"]');
      if (el) fd.append(name, el.value);
    });
    const photoEl = document.getElementById('photoInput');
    if (photoEl && photoEl.files && photoEl.files[0]) fd.append('photo', photoEl.files[0]);
    try {
      const res = await fetch('/tmm/admin/api/module4/submit_checklist.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        const c = document.getElementById('certStatus');
        if (c) { 
            c.className = 'px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/20'; 
            c.innerText = data.certificate_number || 'Issued'; 
        }
        const rs = document.getElementById('reinspectSection');
        const pb = document.getElementById('reinspectBadge');
        const plEl = form.querySelector('input[name="plate_number"]');
        if (rs && pb) {
          const status = (data.overall_status || '').toUpperCase();
          if (status === 'FAILED' || status === 'FOR REINSPECTION') {
            rs.classList.remove('hidden');
            pb.textContent = status === 'FAILED' ? 'Failed' : 'For Reinspection';
            const plateVal = plEl ? plEl.value : '';
            const rp = document.getElementById('reinspectPlate');
            if (rp && plateVal) rp.value = plateVal;
          } else {
            rs.classList.add('hidden');
          }
        }
        showToast('Checklist submitted successfully');
      } else {
        showToast(data.error || 'Submission failed', 'error');
      }
    } catch(err) {
      showToast('Network error', 'error');
    }
  });
  const reinspectForm = document.getElementById('reinspectForm');
  reinspectForm?.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(reinspectForm);
    const dtInput = document.getElementById('reinspectDate');
    fd.append('scheduled_at', dtInput?.value || '');
    try {
      const res = await fetch('/tmm/admin/api/module4/schedule_inspection.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        showToast('Reinspection scheduled successfully');
      } else {
        showToast(data.error || 'Reinspection scheduling failed', 'error');
      }
    } catch(err) {
      showToast('Network error', 'error');
    }
  });
})();
</script>
