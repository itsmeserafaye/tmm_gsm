<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg shadow-sm">
  <h1 class="text-2xl font-bold mb-2">Route Validation & Compliance Reporting</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Validate terminals and routes against LPTRP capacity and produce inspection compliance reports.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm hover:shadow-md transition-shadow">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
        <i data-lucide="map-pin" class="w-5 h-5 text-blue-500"></i> Route Capacity
      </h2>
      <form id="routeForm" class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input name="route_id" class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" placeholder="Route ID" required>
        <select class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
          <option>Terminal</option>
          <option>Central Terminal</option>
          <option>East Hub</option>
        </select>
        <button id="btnRoute" type="submit" class="px-4 py-2 bg-[#4CAF50] hover:bg-[#45a049] text-white rounded-lg transition-colors shadow-sm">Validate</button>
      </form>
      <div class="mt-4 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700">
        <div id="routeMax" class="text-sm font-medium text-slate-700 dark:text-slate-300">Max Vehicles: —</div>
        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 mt-3 mb-1">
          <div id="routeBar" class="bg-[#4CAF50] h-2.5 rounded-full transition-all duration-500" style="width: 0%"></div>
        </div>
        <div id="routeStat" class="text-xs text-slate-500 dark:text-slate-400 mt-1">—</div>
      </div>
    </div>

    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-purple-500 shadow-sm hover:shadow-md transition-shadow">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
        <i data-lucide="bar-chart-3" class="w-5 h-5 text-purple-500"></i> Compliance Reporting
      </h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <select class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all">
          <option>Period</option>
          <option>30d</option>
          <option>90d</option>
          <option>Year</option>
        </select>
        <select class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all">
          <option>Status</option>
          <option>Passed</option>
          <option>Failed</option>
          <option>Pending</option>
        </select>
        <select class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all">
          <option>Coop</option>
          <option>United Transport</option>
          <option>Bayanihan</option>
        </select>
        <button type="button" class="md:col-span-3 px-4 py-2 bg-[#4CAF50] hover:bg-[#45a049] text-white rounded-lg transition-colors shadow-sm flex items-center justify-center gap-2 mt-2">
          <i data-lucide="file-bar-chart" class="w-4 h-4"></i> Generate Report
        </button>
      </form>
    </div>
  </div>

  <div class="overflow-x-auto mt-8 rounded-xl ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 shadow-sm">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
        <tr class="text-left text-slate-700 dark:text-slate-200">
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Vehicle</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Route</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Terminal</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Inspection Status</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Certificate</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors duration-150">
          <td class="py-3 px-4 font-medium text-slate-900 dark:text-slate-100">ABC-1234</td>
          <td class="py-3 px-4 text-slate-600 dark:text-slate-400">R-12</td>
          <td class="py-3 px-4 text-slate-600 dark:text-slate-400">Central Terminal</td>
          <td class="py-3 px-4"><span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Passed</span></td>
          <td class="py-3 px-4 text-slate-600 dark:text-slate-400 font-mono text-xs">CERT-2025-8801</td>
        </tr>
        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors duration-150">
          <td class="py-3 px-4 font-medium text-slate-900 dark:text-slate-100">XYZ-5678</td>
          <td class="py-3 px-4 text-slate-600 dark:text-slate-400">R-08</td>
          <td class="py-3 px-4 text-slate-600 dark:text-slate-400">East Hub</td>
          <td class="py-3 px-4"><span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span></td>
          <td class="py-3 px-4 text-slate-400">—</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<script>
(function(){
  document.getElementById('routeForm')?.addEventListener('submit', async function(e){
    e.preventDefault();
    const btn=document.getElementById('btnRoute'); const orig=btn.innerHTML; btn.disabled=true; btn.innerHTML='Checking...';
    try{
      const fd=new FormData(this);
      const res=await fetch('/tmm/admin/api/module4/route_validate.php',{method:'POST',body:fd});
      const data=await res.json();
      if(data.ok){
        document.getElementById('routeMax').innerText='Max Vehicles: '+data.max_limit;
        const pct = data.max_limit>0 ? Math.min(100, Math.round((data.assigned/data.max_limit)*100)) : 0;
        document.getElementById('routeBar').style.width=pct+'%';
        document.getElementById('routeStat').innerText=data.assigned+'/'+data.max_limit+' assigned • '+(data.within_limit?'Within limit':'Over capacity');
      } else {
        document.getElementById('routeStat').innerText = 'Error';
      }
    }catch(err){
      document.getElementById('routeStat').innerText = 'Network error';
    }finally{
      btn.disabled=false; btn.innerHTML=orig;
    }
  });
})();
</script>
