<?php require_once __DIR__ . '/../../includes/db.php'; $db = db(); ?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg shadow-sm">
  <h1 class="text-2xl font-bold mb-2">Inspection Execution & Certification</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Use checklist to record inspection findings, attach photos, and issue city inspection certificates.</p>

  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm hover:shadow-md transition-shadow">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
        <i data-lucide="clipboard-list" class="w-5 h-5 text-blue-500"></i> Checklist
      </h2>
      <form id="checklistForm" class="space-y-4 text-sm" enctype="multipart/form-data">
        <div class="space-y-1">
          <label class="text-xs font-medium text-slate-500 uppercase">Schedule ID</label>
          <input name="schedule_id" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" placeholder="Enter Schedule ID" required>
        </div>
        
        <div class="space-y-2 border-t border-b py-4 dark:border-slate-700">
          <?php 
          $items = [
            'LIGHTS_HORN' => 'Lights & Horn',
            'BRAKES' => 'Brakes',
            'EMISSION' => 'Emission & Smoke Test',
            'TIRES_WIPERS' => 'Tires & Wipers',
            'INTERIOR_SAFETY' => 'Interior Safety',
            'DOCS_PLATE' => 'Documents & Plate'
          ];
          foreach($items as $key => $label): 
          ?>
          <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
            <span class="font-medium text-slate-700 dark:text-slate-300"><?php echo $label; ?></span>
            <select name="items[<?php echo $key; ?>]" class="border rounded-md px-2 py-1 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
              <option>Pass</option>
              <option>Fail</option>
              <option>NA</option>
            </select>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="space-y-1">
          <label class="text-xs font-medium text-slate-500 uppercase">Remarks</label>
          <input name="remarks" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" placeholder="Additional notes...">
        </div>
        <button id="btnChecklist" type="submit" class="w-full px-4 py-2 bg-[#4CAF50] hover:bg-[#45a049] text-white rounded-lg transition-colors shadow-sm flex items-center justify-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i> Submit Checklist
        </button>
      </form>
    </div>
    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-green-500 shadow-sm hover:shadow-md transition-shadow">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
        <i data-lucide="award" class="w-5 h-5 text-green-500"></i> Result & Certificate
      </h2>
      <form id="certForm" class="space-y-4">
        <?php $off = $db->query("SELECT officer_id, name FROM officers WHERE active_status=1 ORDER BY name"); ?>
        <div class="space-y-1">
          <label class="text-xs font-medium text-slate-500 uppercase">Schedule ID</label>
          <input name="schedule_id" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all" placeholder="Enter Schedule ID" required>
        </div>
        <div class="space-y-1">
          <label class="text-xs font-medium text-slate-500 uppercase">Approved By</label>
          <select name="approved_by" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all">
            <option value="">Select Approver</option>
            <?php if ($off && $off->num_rows > 0): while($o = $off->fetch_assoc()): ?>
              <option value="<?php echo (int)$o['officer_id']; ?>"><?php echo htmlspecialchars($o['name']); ?></option>
            <?php endwhile; endif; ?>
          </select>
        </div>
        <button id="btnCert" type="submit" class="w-full px-4 py-2 bg-[#4CAF50] hover:bg-[#45a049] text-white rounded-lg transition-colors shadow-sm flex items-center justify-center gap-2 mt-4">
          <i data-lucide="file-signature" class="w-4 h-4"></i> Generate Certificate
        </button>
      </form>
      <div id="certStatus" class="mt-4 text-sm flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700">
        <span class="font-medium text-slate-600 dark:text-slate-400">Certificate:</span>
        <span class="px-2 py-1 rounded-full text-xs font-medium bg-slate-200 text-slate-700">None</span>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  function toast(msg,type='success'){const c=document.getElementById('toast-container');const t=document.createElement('div');const color=type==='success'?'bg-green-500':(type==='error'?'bg-red-500':'bg-blue-500');t.className=color+" text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px]";t.innerHTML='<span class="font-medium text-sm">'+msg+'</span>';c.appendChild(t);requestAnimationFrame(()=>t.classList.remove('translate-y-10','opacity-0'));setTimeout(()=>t.remove(),3000);}
  document.getElementById('checklistForm')?.addEventListener('submit',async function(e){e.preventDefault();const btn=document.getElementById('btnChecklist');const orig=btn.innerHTML;btn.disabled=true;btn.innerHTML='Saving...';try{const fd=new FormData(this);const res=await fetch('/tmm/admin/api/module4/submit_checklist.php',{method:'POST',body:fd});const data=await res.json();if(data.ok){toast('Checklist saved');}else{toast(data.error||'Save failed','error');}}catch(err){toast('Network error','error');}finally{btn.disabled=false;btn.innerHTML=orig;}});
  document.getElementById('certForm')?.addEventListener('submit',async function(e){e.preventDefault();const btn=document.getElementById('btnCert');const orig=btn.innerHTML;btn.disabled=true;btn.innerHTML='Issuing...';try{const fd=new FormData(this);const res=await fetch('/tmm/admin/api/module4/generate_certificate.php',{method:'POST',body:fd});const data=await res.json();if(data.ok){document.getElementById('certStatus').innerHTML='Certificate: <span class="px-2 py-1 rounded bg-green-100 text-green-700">'+data.certificate_number+'</span>';toast('Certificate issued');}else{toast(data.error||'Issue failed','error');}}catch(err){toast('Network error','error');}finally{btn.disabled=false;btn.innerHTML=orig;}});
})();
</script>
