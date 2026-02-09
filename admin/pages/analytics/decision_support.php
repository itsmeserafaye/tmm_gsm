<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module3.analytics','analytics.view']);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Analytics & Decision Support</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">Trend-based forecasts using aggregated operational data (routes + terminals). Weather, current events, and traffic remain enabled.</p>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="flex flex-col lg:flex-row lg:items-end gap-4">
      <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Horizon</label>
          <select id="hoursAhead" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="12">Next 12 hours</option>
            <option value="24" selected>Next 24 hours</option>
            <option value="48">Next 48 hours</option>
            <option value="72">Next 72 hours</option>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Notes</label>
          <div class="px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300">
            Forecasts are network-level guidance; no individual commuter prediction.
          </div>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" id="btnRefresh" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Refresh</button>
      </div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="flex flex-col lg:flex-row lg:items-end gap-4">
      <div class="flex-1">
        <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Data Inputs</div>
        <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Log hourly demand observations (terminal or route). More observations = better forecasts.</div>
      </div>
    </div>
    <form id="demandLogForm" class="mt-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end" novalidate>
      <div>
        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Area Type</label>
        <select id="demandAreaType" name="area_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
          <option value="terminal" selected>Terminal</option>
          <option value="route">Route</option>
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Location</label>
        <select id="demandAreaRef" name="area_ref" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold"></select>
      </div>
      <div>
        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Hour</label>
        <input id="demandObservedAt" name="observed_at" type="datetime-local" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
      </div>
      <div>
        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Count</label>
        <input id="demandCount" name="demand_count" type="number" min="0" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="0">
      </div>
      <div class="md:col-span-5 flex items-center justify-between gap-3">
        <div id="demandLogMsg" class="text-xs font-bold text-slate-500 dark:text-slate-400 min-h-[1.5em]"></div>
        <button type="submit" id="btnSaveObs" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white text-sm font-bold">Save Observation</button>
      </div>
    </form>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700">
        <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Route Demand Forecast</div>
        <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Top predicted peak periods by route.</div>
      </div>
      <div class="p-6">
        <div id="routeMeta" class="text-xs font-semibold text-slate-500 dark:text-slate-400"></div>
        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
              <tr class="text-left text-slate-500 dark:text-slate-400">
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Route</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Peak Hour</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Peak</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Baseline</th>
              </tr>
            </thead>
            <tbody id="routeSpikes" class="divide-y divide-slate-200 dark:divide-slate-700">
              <tr><td colspan="4" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700">
        <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Terminal Congestion Forecast</div>
        <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Terminal surge alerts + traffic context.</div>
      </div>
      <div class="p-6">
        <div id="terminalMeta" class="text-xs font-semibold text-slate-500 dark:text-slate-400"></div>
        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
              <tr class="text-left text-slate-500 dark:text-slate-400">
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Terminal</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Peak Hour</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Peak</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Units</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Traffic</th>
              </tr>
            </thead>
            <tbody id="terminalAlerts" class="divide-y divide-slate-200 dark:divide-slate-700">
              <tr><td colspan="5" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700">
      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Vehicle Allocation Suggestions</div>
      <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Actionable playbook based on forecasts and constraints.</div>
    </div>
    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Over Demand</div>
        <ul id="playbookOver" class="space-y-2 text-sm font-semibold text-slate-700 dark:text-slate-200"></ul>
      </div>
      <div>
        <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Under Demand</div>
        <ul id="playbookUnder" class="space-y-2 text-sm font-semibold text-slate-700 dark:text-slate-200"></ul>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const hoursAhead = document.getElementById('hoursAhead');
    const btnRefresh = document.getElementById('btnRefresh');

    const demandLogForm = document.getElementById('demandLogForm');
    const demandAreaType = document.getElementById('demandAreaType');
    const demandAreaRef = document.getElementById('demandAreaRef');
    const demandObservedAt = document.getElementById('demandObservedAt');
    const demandCount = document.getElementById('demandCount');
    const demandLogMsg = document.getElementById('demandLogMsg');
    const btnSaveObs = document.getElementById('btnSaveObs');

    const routeMeta = document.getElementById('routeMeta');
    const routeSpikes = document.getElementById('routeSpikes');
    const terminalMeta = document.getElementById('terminalMeta');
    const terminalAlerts = document.getElementById('terminalAlerts');
    const playbookOver = document.getElementById('playbookOver');
    const playbookUnder = document.getElementById('playbookUnder');

    const esc = (s) => (s === null || s === undefined) ? '' : String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let areaLists = null;

    function initObservedAt() {
      if (!demandObservedAt) return;
      const d = new Date();
      d.setMinutes(0, 0, 0);
      const pad = (n) => String(n).padStart(2, '0');
      demandObservedAt.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':00';
    }

    function populateAreaRefs(type) {
      if (!demandAreaRef) return;
      demandAreaRef.innerHTML = '';
      const list = areaLists && areaLists[type] ? areaLists[type] : [];
      list.forEach((a) => {
        const opt = document.createElement('option');
        opt.value = String(a.ref || '');
        opt.textContent = String(a.label || a.ref || '');
        demandAreaRef.appendChild(opt);
      });
    }

    async function ensureAreaLists(hours) {
      if (areaLists && areaLists.terminal && areaLists.route) return;
      const res = await fetch(rootUrl + '/admin/api/analytics/demand_forecast.php?area_type=terminal&hours=' + encodeURIComponent(String(hours || 24)));
      const data = await res.json().catch(() => null);
      if (data && data.ok && data.area_lists) areaLists = data.area_lists;
    }

    const badge = (severity) => {
      const s = (severity || '').toString();
      const cls = s === 'critical' ? 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20'
        : s === 'high' ? 'bg-amber-100 text-amber-800 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20'
        : 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400';
      return `<span class="px-2.5 py-1 rounded-lg text-[11px] font-bold ring-1 ring-inset ${cls}">${esc(s || 'medium')}</span>`;
    };

    async function loadRouteForecast(hours) {
      routeSpikes.innerHTML = '<tr><td colspan="4" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>';
      const res = await fetch(rootUrl + '/admin/api/analytics/demand_forecast.php?area_type=route&hours=' + encodeURIComponent(String(hours)));
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'forecast_failed');
      const spikes = Array.isArray(data.spikes) ? data.spikes : [];
      if (data.area_lists) areaLists = data.area_lists;
      if (routeMeta) {
        const m = data.model || {};
        const wx = data.weather && data.weather.current ? data.weather.current : null;
        const wxTxt = wx && (wx.temperature !== undefined) ? (` • Weather now: ${wx.temperature}°C`) : '';
        const basis = (data.data_source === 'observations') ? 'Real observations' : 'Estimated (proxy activity)';
        routeMeta.textContent = `Based on: ${basis} • Accuracy (last 7 days): ${Math.round(Number(data.accuracy || 0))}% • Data points: ${Number(data.data_points || 0)}${wxTxt}`;
      }
      if (!spikes.length) {
        routeSpikes.innerHTML = '<tr><td colspan="4" class="py-10 text-center text-slate-500 font-medium italic">No significant spikes detected.</td></tr>';
        return;
      }
      routeSpikes.innerHTML = spikes.map((s) => {
        return `<tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
          <td class="py-3 px-3 font-black text-slate-900 dark:text-white">${esc(s.area_label || s.area_ref || '-')}</td>
          <td class="py-3 px-3 text-slate-600 dark:text-slate-300 font-semibold">${esc(s.peak_hour || '-')}</td>
          <td class="py-3 px-3 text-right font-black text-slate-900 dark:text-white">${esc(s.predicted_peak ?? 0)}</td>
          <td class="py-3 px-3 text-right font-semibold text-slate-600 dark:text-slate-300">${esc(s.baseline ?? 0)}</td>
        </tr>`;
      }).join('');
    }

    function summarizeTraffic(a) {
      const t = a && a.traffic && a.traffic.flow ? a.traffic.flow : null;
      if (!t) return '—';
      const cls = (t.congestion || 'unknown').toString();
      const pct = (t.congestion_pct !== null && t.congestion_pct !== undefined) ? `${t.congestion_pct}%` : '';
      return `${cls}${pct ? ' (' + pct + ')' : ''}`;
    }

    async function loadTerminalInsights(hours) {
      terminalAlerts.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>';
      const res = await fetch(rootUrl + '/admin/api/analytics/demand_insights.php?area_type=terminal&hours=' + encodeURIComponent(String(hours)));
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'insights_failed');

      const alerts = Array.isArray(data.alerts) ? data.alerts : [];
      const rd = data.readiness || {};
      if (data.area_lists) areaLists = data.area_lists;
      if (terminalMeta) {
        const ctx = data.context || {};
        const m = ctx.model || {};
        const basis = (rd.data_source === 'observations') ? 'Real observations' : 'Estimated (proxy activity)';
        const readiness = rd.ok ? 'Good' : 'Low data';
        terminalMeta.textContent = `Forecast health: ${readiness} • Based on: ${basis} • Accuracy (last 7 days): ${Math.round(Number(rd.accuracy || 0))}% • Data points: ${Number(rd.data_points || 0)}`;
      }

      const pb = data.playbook || {};
      const over = Array.isArray(pb.over_demand) ? pb.over_demand : [];
      const under = Array.isArray(pb.under_demand) ? pb.under_demand : [];
      const fmt = (t) => esc(t || '').replace(/\*\*(.+?)\*\*/g, '<strong class="text-slate-900 dark:text-white">$1</strong>').replace(/\n/g, '<br>');
      const isHeader = (t) => /^(LGU PLAYBOOK\s+—\s+|IMMEDIATE\s*\(|SAME-?DAY\s*\(|POLICY\s*\/\s*NEXT-?DAY)/i.test(String(t || '').trim());
      const renderPlaybook = (el, arr) => {
        if (!el) return;
        if (!arr || !arr.length) { el.innerHTML = '<li class="text-slate-500 italic">No suggestions.</li>'; return; }
        el.innerHTML = arr.map((x) => {
          if (isHeader(x)) {
            return `<li class="list-none pt-2"><div class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200">${fmt(x)}</div></li>`;
          }
          return `<li class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">${fmt(x)}</li>`;
        }).join('');
      };
      renderPlaybook(playbookOver, over);
      renderPlaybook(playbookUnder, under);

      if (!alerts.length) {
        terminalAlerts.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-500 font-medium italic">No significant alerts detected.</td></tr>';
        return;
      }

      terminalAlerts.innerHTML = alerts.map((a) => {
        const traffic = summarizeTraffic(a);
        const supply = (a.supply_units === null || a.supply_units === undefined) ? '—' : String(a.supply_units);
        return `<tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
          <td class="py-3 px-3">
            <div class="font-black text-slate-900 dark:text-white">${esc(a.area_label || a.area_ref || '-')}</div>
            <div class="mt-1">${badge(a.severity)}</div>
          </td>
          <td class="py-3 px-3 text-slate-600 dark:text-slate-300 font-semibold">${esc(a.peak_hour || '-')}</td>
          <td class="py-3 px-3 text-right font-black text-slate-900 dark:text-white">${esc(a.predicted_peak ?? 0)}</td>
          <td class="py-3 px-3 text-right font-black text-slate-900 dark:text-white">${esc(supply)}</td>
          <td class="py-3 px-3 text-slate-600 dark:text-slate-300 font-semibold">${esc(traffic)}</td>
        </tr>`;
      }).join('');
    }

    async function refreshAll() {
      const h = Number(hoursAhead && hoursAhead.value ? hoursAhead.value : 24);
      await Promise.all([
        loadRouteForecast(h),
        loadTerminalInsights(h),
      ]);
      await ensureAreaLists(h);
      populateAreaRefs((demandAreaType && demandAreaType.value) ? demandAreaType.value : 'terminal');
    }

    if (btnRefresh) btnRefresh.addEventListener('click', () => { refreshAll().catch(() => {}); });
    if (hoursAhead) hoursAhead.addEventListener('change', () => { refreshAll().catch(() => {}); });

    if (demandAreaType) demandAreaType.addEventListener('change', () => { populateAreaRefs(demandAreaType.value); });

    if (demandLogForm && btnSaveObs) {
      demandLogForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!demandLogForm.checkValidity()) { demandLogForm.reportValidity(); return; }
        btnSaveObs.disabled = true;
        btnSaveObs.textContent = 'Saving...';
        if (demandLogMsg) { demandLogMsg.className = 'text-xs font-bold text-slate-500 dark:text-slate-400 min-h-[1.5em]'; demandLogMsg.textContent = 'Saving...'; }
        try {
          const fd = new FormData(demandLogForm);
          const res = await fetch(rootUrl + '/admin/api/analytics/demand_observation_upsert.php', { method: 'POST', body: fd });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'save_failed');
          if (demandLogMsg) { demandLogMsg.className = 'text-xs font-bold text-emerald-600 min-h-[1.5em]'; demandLogMsg.textContent = 'Saved.'; }
          await refreshAll();
        } catch (err) {
          if (demandLogMsg) { demandLogMsg.className = 'text-xs font-bold text-rose-600 min-h-[1.5em]'; demandLogMsg.textContent = (err && err.message) ? String(err.message) : 'Failed'; }
        } finally {
          btnSaveObs.disabled = false;
          btnSaveObs.textContent = 'Save Observation';
        }
      });
    }

    initObservedAt();
    refreshAll().catch(() => {});
  })();
</script>
