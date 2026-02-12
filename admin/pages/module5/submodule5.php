<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module5.read','module5.manage_terminal','module5.parking_fees']);
 
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>
 
<div class="mx-auto max-w-6xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Operations Dashboard</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">Real-time terminal occupancy, queue counts, and congestion levels.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="?page=module5/submodule4" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="layout-grid" class="w-4 h-4"></i>
        Terminal Slots
      </a>
      <button type="button" id="btnRefreshOps" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
        <i data-lucide="refresh-ccw" class="w-4 h-4"></i>
        Refresh
      </button>
    </div>
  </div>
 
  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="sm:col-span-2">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Search</label>
          <input id="opsQ" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Search terminal/category/city">
        </div>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Type</label>
          <select id="opsType" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="Terminal" selected>Terminal</option>
            <option value="Parking">Parking</option>
          </select>
        </div>
      </div>
 
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Total</div>
          <div id="kpiTotal" class="mt-1 text-2xl font-black text-slate-900 dark:text-white">0</div>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Occupied</div>
          <div id="kpiOcc" class="mt-1 text-2xl font-black text-slate-900 dark:text-white">0</div>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Free</div>
          <div id="kpiFree" class="mt-1 text-2xl font-black text-slate-900 dark:text-white">0</div>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Queued</div>
          <div id="kpiQueue" class="mt-1 text-2xl font-black text-slate-900 dark:text-white">0</div>
        </div>
      </div>
    </div>
  </div>
 
  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 flex items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
      <div class="font-black text-slate-900 dark:text-white">Occupancy</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Auto-refresh every 5 seconds</div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Terminal</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Capacity</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Occupied</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Free</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Congestion</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Queue</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Action</th>
          </tr>
        </thead>
        <tbody id="opsBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
 
<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const q = document.getElementById('opsQ');
    const type = document.getElementById('opsType');
    const body = document.getElementById('opsBody');
    const btnRefresh = document.getElementById('btnRefreshOps');
    const kpiTotal = document.getElementById('kpiTotal');
    const kpiOcc = document.getElementById('kpiOcc');
    const kpiFree = document.getElementById('kpiFree');
    const kpiQueue = document.getElementById('kpiQueue');
 
    let timer = null;
    let inflight = null;
 
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmtPct = (n) => (Math.round((Number(n) || 0) * 10) / 10).toFixed(1) + '%';
    const badge = (pct) => {
      const p = Number(pct) || 0;
      if (p >= 80) return 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-300';
      if (p >= 50) return 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-300';
      return 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-300';
    };
 
    async function load() {
      if (!body) return;
      if (inflight) try { inflight.abort(); } catch (_) {}
      inflight = new AbortController();
      const qs = new URLSearchParams();
      qs.set('type', (type && type.value) ? type.value : 'Terminal');
      if (q && q.value.trim() !== '') qs.set('q', q.value.trim());
      const url = rootUrl + '/admin/api/module5/occupancy_dashboard.php?' + qs.toString();
      try {
        const res = await fetch(url, { signal: inflight.signal });
        const data = await res.json();
        if (!data || !data.ok || !Array.isArray(data.data)) throw new Error('load_failed');
        const rows = data.data;
 
        let sumTotal = 0, sumOcc = 0, sumFree = 0, sumQ = 0;
        rows.forEach(r => { sumTotal += (r.total || 0); sumOcc += (r.occupied || 0); sumFree += (r.free || 0); sumQ += (r.queue_len || 0); });
        if (kpiTotal) kpiTotal.textContent = String(sumTotal);
        if (kpiOcc) kpiOcc.textContent = String(sumOcc);
        if (kpiFree) kpiFree.textContent = String(sumFree);
        if (kpiQueue) kpiQueue.textContent = String(sumQ);
 
        if (rows.length === 0) {
          body.innerHTML = `<tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">No results.</td></tr>`;
          return;
        }
 
        body.innerHTML = rows.map((r) => {
          const id = Number(r.id) || 0;
          const name = esc(r.name || '');
          const cap = Number(r.capacity) || 0;
          const occ = Number(r.occupied) || 0;
          const free = Number(r.free) || 0;
          const pct = Number(r.congestion_pct) || 0;
          const qlen = Number(r.queue_len) || 0;
          const qprio = Number(r.queue_priority_len) || 0;
          const qTxt = qprio > 0 ? `${qlen} (${qprio} priority)` : `${qlen}`;
          const link = `?page=module5/submodule4&terminal_id=${encodeURIComponent(String(id))}&tab=slots`;
          return `
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
              <td class="py-4 px-6 font-bold text-slate-900 dark:text-white">${name}</td>
              <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${cap}</td>
              <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${occ}</td>
              <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${free}</td>
              <td class="py-4 px-4">
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ${badge(pct)}">${fmtPct(pct)}</span>
              </td>
              <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${esc(qTxt)}</td>
              <td class="py-4 px-4 text-right">
                <a href="${link}" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-3 py-2 text-xs font-bold text-white transition-colors">Manage</a>
              </td>
            </tr>
          `;
        }).join('');
      } catch (e) {
        if (e && e.name === 'AbortError') return;
        body.innerHTML = `<tr><td colspan="7" class="py-10 text-center text-rose-600 font-semibold">Failed to load dashboard.</td></tr>`;
      }
    }
 
    function schedule() {
      if (timer) clearInterval(timer);
      timer = setInterval(load, 5000);
    }
 
    if (btnRefresh) btnRefresh.addEventListener('click', load);
    if (q) q.addEventListener('input', () => { load(); });
    if (type) type.addEventListener('change', () => { load(); });
    load();
    schedule();
  })();
</script>
