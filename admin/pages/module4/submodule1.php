<?php require_once __DIR__ . '/../../includes/db.php'; $db = db(); ?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg shadow-sm">
  <h1 class="text-2xl font-bold mb-2">Vehicle Verification & Inspection Scheduling</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Upload and verify CR/OR, check PUV and Franchise records, and schedule inspections with authorized inspectors.</p>

  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

  <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-green-500 shadow-sm mb-6 hover:shadow-md transition-shadow">
    <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
      <i data-lucide="file-check" class="w-5 h-5 text-green-500"></i> LTO Document Upload & Verification
    </h2>
    <form id="verifyForm" class="grid grid-cols-1 md:grid-cols-3 gap-4" enctype="multipart/form-data">
      <div class="space-y-1">
        <label class="text-xs font-medium text-slate-500 uppercase">Plate Number</label>
        <input name="plate_number" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all uppercase placeholder:normal-case" placeholder="ABC-1234" required>
      </div>
      <div class="space-y-1">
        <label class="text-xs font-medium text-slate-500 uppercase">Operator ID</label>
        <input name="operator_id" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all" placeholder="Optional">
      </div>
      <div class="hidden md:block"></div>
      <div class="space-y-1">
        <label class="text-xs font-medium text-slate-500 uppercase">CR Document</label>
        <input name="cr" type="file" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 transition-colors" accept=".pdf,.jpg,.jpeg,.png">
      </div>
      <div class="space-y-1">
        <label class="text-xs font-medium text-slate-500 uppercase">OR Document</label>
        <input name="or" type="file" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 transition-colors" accept=".pdf,.jpg,.jpeg,.png">
      </div>
      <div class="md:col-span-1 flex items-end">
        <button id="btnVerify" type="submit" class="w-full px-4 py-2 bg-[#4CAF50] hover:bg-[#45a049] text-white rounded-lg transition-colors shadow-sm flex items-center justify-center gap-2">
          <i data-lucide="check-circle" class="w-4 h-4"></i> Verify Documents
        </button>
      </div>
    </form>
    <div id="verifyStatus" class="mt-4 text-sm flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700">
      <span class="font-medium text-slate-600 dark:text-slate-400">Status:</span>
      <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm hover:shadow-md transition-shadow">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
        <i data-lucide="search" class="w-5 h-5 text-blue-500"></i> Cross-Checks
      </h2>
      <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="md:col-span-2">
            <input id="cc_plate" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all uppercase placeholder:normal-case" placeholder="Plate number">
          </div>
          <button id="btnCheckVehicle" class="w-full px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors text-sm font-medium">Check Vehicle</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="md:col-span-2">
            <input id="cc_fr_ref" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" placeholder="Franchise Ref / App ID">
          </div>
          <button id="btnCheckFranchise" class="w-full px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors text-sm font-medium">Check Franchise</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="md:col-span-2">
            <select id="cc_inspector" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
              <option value="">Select Inspector</option>
              <?php if ($ins && $ins->num_rows > 0): $ins->data_seek(0); while($i2 = $ins->fetch_assoc()): ?>
                <option value="<?php echo (int)$i2['officer_id']; ?>"><?php echo htmlspecialchars($i2['name'] . ' — ' . $i2['badge_no']); ?></option>
              <?php endwhile; endif; ?>
            </select>
          </div>
          <button id="btnValidateInspector" class="w-full px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors text-sm font-medium">Validate</button>
        </div>
        <div id="cc_results" class="p-4 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-sm min-h-[60px] text-slate-500 italic">Results will appear here...</div>
      </div>
    </div>
    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-purple-500 shadow-sm hover:shadow-md transition-shadow">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
        <i data-lucide="calendar" class="w-5 h-5 text-purple-500"></i> Inspection Scheduling
      </h2>
      <form id="scheduleForm" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-1">
            <label class="text-xs font-medium text-slate-500 uppercase">Date & Time</label>
            <input name="scheduled_at" type="datetime-local" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all">
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium text-slate-500 uppercase">Location</label>
            <input name="location" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all" placeholder="Inspection Center">
          </div>
        </div>
        <div class="space-y-1">
          <label class="text-xs font-medium text-slate-500 uppercase">Inspector</label>
          <?php $ins = $db->query("SELECT officer_id, name, badge_no FROM officers WHERE active_status=1 ORDER BY name"); ?>
          <select name="inspector_id" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all">
            <option value="">Select Inspector</option>
            <?php if ($ins && $ins->num_rows > 0): $ins->data_seek(0); while($i = $ins->fetch_assoc()): ?>
              <option value="<?php echo (int)$i['officer_id']; ?>"><?php echo htmlspecialchars($i['name'] . ' — ' . $i['badge_no']); ?></option>
            <?php endwhile; endif; ?>
          </select>
        </div>
        <div class="space-y-1">
          <label class="text-xs font-medium text-slate-500 uppercase">Plate Number</label>
          <input name="plate_number" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all uppercase placeholder:normal-case" placeholder="ABC-1234" required>
        </div>
        <button id="btnSchedule" type="submit" class="w-full px-4 py-2 bg-[#4CAF50] hover:bg-[#45a049] text-white rounded-lg transition-colors shadow-sm flex items-center justify-center gap-2 mt-2">
          <i data-lucide="calendar-plus" class="w-4 h-4"></i> Schedule Inspection
        </button>
      </form>
      <div id="schedStatus" class="mt-4 text-sm flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700">
        <span class="font-medium text-slate-600 dark:text-slate-400">Latest Schedule:</span>
        <span class="px-2 py-1 rounded-full text-xs font-medium bg-slate-200 text-slate-700">None</span>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  function toast(msg, type='success'){
    const c=document.getElementById('toast-container'); const t=document.createElement('div');
    const color = type==='success'?'bg-green-500':(type==='error'?'bg-red-500':'bg-blue-500');
    t.className=color+" text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px] z-50";
    t.innerHTML='<span class="font-medium text-sm">'+msg+'</span>'; c.appendChild(t);
    requestAnimationFrame(()=>t.classList.remove('translate-y-10','opacity-0')); setTimeout(()=>t.remove(),3000);
  }
  document.getElementById('verifyForm')?.addEventListener('submit', async function(e){
    e.preventDefault();
    const btn=document.getElementById('btnVerify'); const orig=btn.innerHTML; btn.disabled=true; btn.innerHTML='Verifying...';
    try{
      const plateInput = this.querySelector('input[name="plate_number"]');
      const crInput = this.querySelector('input[name="cr"]');
      const orInput = this.querySelector('input[name="or"]');
      if (!plateInput.value.trim()) { toast('Plate required','error'); throw new Error('stop'); }
      if (!crInput.files.length && !orInput.files.length) { toast('Upload CR or OR','error'); throw new Error('stop'); }
      plateInput.value = plateInput.value.toUpperCase();
      const fd=new FormData(this);
      const res=await fetch('/tmm/admin/api/module4/verify_documents.php',{method:'POST',body:fd});
      const data=await res.json();
      if(data.ok){ document.getElementById('verifyStatus').innerHTML='Verification: <span class="px-2 py-1 rounded bg-green-100 text-green-700">Verified</span>'; toast('Documents verified'); }
      else{ toast(data.error||'Verification failed','error'); }
    }catch(err){ toast('Network error','error'); } finally{ btn.disabled=false; btn.innerHTML=orig; }
  });
  document.getElementById('scheduleForm')?.addEventListener('submit', async function(e){
    e.preventDefault();
    const btn=document.getElementById('btnSchedule'); const orig=btn.innerHTML; btn.disabled=true; btn.innerHTML='Scheduling...';
    try{
      const dt = this.querySelector('input[name="scheduled_at"]');
      const loc = this.querySelector('input[name="location"]');
      const insp = this.querySelector('select[name="inspector_id"]');
      const plate = this.querySelector('input[name="plate_number"]');
      if (!dt.value) { toast('Date/time required','error'); throw new Error('stop'); }
      if (!loc.value.trim()) { toast('Location required','error'); throw new Error('stop'); }
      if (!insp.value) { toast('Inspector required','error'); throw new Error('stop'); }
      if (!plate.value.trim()) { toast('Plate required','error'); throw new Error('stop'); }
      plate.value = plate.value.toUpperCase();
      const fd=new FormData(this);
      const res=await fetch('/tmm/admin/api/module4/schedule_inspection.php',{method:'POST',body:fd});
      const data=await res.json();
      if(data.ok){ document.getElementById('schedStatus').innerHTML='Schedule: <span class="px-2 py-1 rounded bg-blue-100 text-blue-700">'+data.scheduled_at+'</span>'; toast('Inspection scheduled'); }
      else{ toast(data.error||'Scheduling failed','error'); }
    }catch(err){ toast('Network error','error'); } finally{ btn.disabled=false; btn.innerHTML=orig; }
  });
  const dtMin = document.querySelector('input[name="scheduled_at"]');
  if (dtMin) {
    const now = new Date();
    now.setMinutes(now.getMinutes()+5);
    const pad = n=>String(n).padStart(2,'0');
    const v = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
    dtMin.min = v;
  }
  document.getElementById('btnCheckVehicle')?.addEventListener('click', async function(){
    const plate = (document.getElementById('cc_plate').value || document.querySelector('input[name=\"plate_number\"]').value || '').trim();
    if (!plate) { toast('Enter plate number','error'); return; }
    try{
      const res = await fetch('/tmm/admin/api/module1/view_html.php?plate='+encodeURIComponent(plate));
      const html = await res.text();
      document.getElementById('cc_results').innerHTML = html;
    }catch(err){ toast('Vehicle lookup failed','error'); }
  });
  document.getElementById('btnCheckFranchise')?.addEventListener('click', async function(){
    const ref = (document.getElementById('cc_fr_ref').value || '').trim();
    if (!ref) { toast('Enter franchise ref or app id','error'); return; }
    try{
      const res = await fetch('/tmm/admin/api/franchise/validate.php?ref='+encodeURIComponent(ref));
      const data = await res.json();
      if (data.found) {
        const html = '<div class=\"space-y-1\"><div><span class=\"font-semibold\">Operator:</span> '+(data.data.operator||'')+'</div><div><span class=\"font-semibold\">Coop:</span> '+(data.data.coop||'')+'</div><div><span class=\"font-semibold\">Status:</span> '+(data.data.status||'')+'</div><div><span class=\"font-semibold\">Vehicles:</span> '+(data.data.vehicle_count||0)+'</div></div>';
        document.getElementById('cc_results').innerHTML = html;
      } else {
        document.getElementById('cc_results').innerHTML = '<div class=\"text-sm\">Application not found</div>';
      }
    }catch(err){ toast('Franchise lookup failed','error'); }
  });
  document.getElementById('btnValidateInspector')?.addEventListener('click', async function(){
    const id = (document.getElementById('cc_inspector').value || '').trim();
    if (!id) { toast('Choose inspector','error'); return; }
    try{
      const fd = new FormData(); fd.append('officer_id', id);
      const res = await fetch('/tmm/admin/api/module4/validate_inspector.php',{method:'POST',body:fd});
      const data = await res.json();
      if (data.ok) {
        document.getElementById('cc_results').innerHTML = '<div class=\"space-y-1\"><div><span class=\"font-semibold\">Inspector:</span> '+data.name+'</div><div><span class=\"font-semibold\">Badge:</span> '+data.badge_no+'</div><div><span class=\"px-2 py-1 rounded '+(data.active?'bg-green-100 text-green-700':'bg-red-100 text-red-700')+'\">'+(data.active?'Active':'Inactive')+'</span></div></div>';
      } else {
        document.getElementById('cc_results').innerHTML = '<div class=\"text-sm\">Inspector not valid</div>';
      }
    }catch(err){ toast('Inspector validation failed','error'); }
  });
})();
</script>
