<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module5.read','module5.manage_terminal','module3.read','module3.analytics','reports.export']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$terminals = [];
$resT = $db->query("SELECT id, name, type FROM terminals ORDER BY type ASC, name ASC LIMIT 2000");
if ($resT) while ($r = $resT->fetch_assoc()) $terminals[] = $r;

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Reports & Analytics</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">Auto-generated PDFs and operational analytics for terminals, payments, and enforcement.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="?page=module5/submodule5" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Back to Ops
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
        <div class="font-black text-slate-900 dark:text-white">Auto-generated PDFs</div>
        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1">Generates server-side PDF exports you can archive or share.</div>
      </div>
      <div class="p-6 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
          <div class="sm:col-span-2">
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Declared Fleet (Operator ID)</label>
            <input id="pdfFleetOperatorId" inputmode="numeric" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 12">
          </div>
          <button type="button" id="btnPdfFleet" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Generate</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
          <div class="sm:col-span-2">
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Inspection Report (Schedule ID)</label>
            <input id="pdfInspectionScheduleId" inputmode="numeric" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 1001">
          </div>
          <button type="button" id="btnPdfInspection" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-semibold">Open PDF</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
          <div class="sm:col-span-3">
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Payment Summary (Terminal)</label>
            <select id="pdfPayTerminalId" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              <option value="0" selected>All Terminals</option>
              <?php foreach ($terminals as $t): ?>
                <option value="<?php echo (int)($t['id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($t['name'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">From</label>
            <input id="pdfPayFrom" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">To</label>
            <input id="pdfPayTo" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
          </div>
          <button type="button" id="btnPdfPay" class="px-4 py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Download</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
          <div class="sm:col-span-3">
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Violation History (Plate)</label>
            <input id="pdfViolPlate" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="ABC-1234">
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">From</label>
            <input id="pdfViolFrom" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">To</label>
            <input id="pdfViolTo" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
          </div>
          <button type="button" id="btnPdfViol" class="px-4 py-2.5 rounded-md bg-rose-600 hover:bg-rose-700 text-white font-semibold">Download</button>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
        <div class="font-black text-slate-900 dark:text-white">Peak Hours (Forecast)</div>
        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1">Uses the demand forecast model to highlight potential surges.</div>
      </div>
      <div class="p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
          <select id="peakHoursAhead" class="w-full sm:w-auto px-3 py-2 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="12">Next 12 hours</option>
            <option value="24" selected>Next 24 hours</option>
            <option value="48">Next 48 hours</option>
            <option value="72">Next 72 hours</option>
          </select>
          <button type="button" id="btnReloadPeak" class="px-4 py-2 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-semibold">Reload</button>
        </div>
        <div id="peakList" class="space-y-2"></div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center justify-between gap-3">
        <div>
          <div class="font-black text-slate-900 dark:text-white">Top Congested Terminals</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1">Based on current slot occupancy (occupied / total).</div>
        </div>
        <button type="button" id="btnReloadCongestion" class="px-4 py-2 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-semibold">Reload</button>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
            <tr class="text-left text-slate-500 dark:text-slate-400">
              <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Terminal</th>
              <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Utilization</th>
              <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Occupied</th>
              <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Free</th>
              <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Queue</th>
            </tr>
          </thead>
          <tbody id="congestionBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
            <tr><td colspan="5" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center justify-between gap-3">
        <div>
          <div class="font-black text-slate-900 dark:text-white">Most Common Violations</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1">Counts and total fines for the selected period.</div>
        </div>
        <div class="flex items-center gap-2">
          <input id="violFrom" type="date" class="px-3 py-2 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm font-semibold">
          <input id="violTo" type="date" class="px-3 py-2 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm font-semibold">
          <button type="button" id="btnReloadViolStats" class="px-4 py-2 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-semibold">Reload</button>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
            <tr class="text-left text-slate-500 dark:text-slate-400">
              <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Code</th>
              <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Description</th>
              <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Count</th>
              <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Total Fine</th>
            </tr>
          </thead>
          <tbody id="violStatsBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
            <tr><td colspan="4" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const toastContainer = document.getElementById('toast-container');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function toast(msg, type) {
      if (!toastContainer) return;
      const t = (type || 'success').toString();
      const color = t === 'error' ? 'bg-rose-600' : 'bg-emerald-600';
      const el = document.createElement('div');
      el.className = `pointer-events-auto px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold ${color}`;
      el.textContent = msg;
      toastContainer.appendChild(el);
      setTimeout(() => { el.classList.add('opacity-0'); el.style.transition = 'opacity 250ms'; }, 2600);
      setTimeout(() => { el.remove(); }, 3000);
    }

    const pdfFleetOperatorId = document.getElementById('pdfFleetOperatorId');
    const btnPdfFleet = document.getElementById('btnPdfFleet');
    const pdfInspectionScheduleId = document.getElementById('pdfInspectionScheduleId');
    const btnPdfInspection = document.getElementById('btnPdfInspection');
    const pdfPayTerminalId = document.getElementById('pdfPayTerminalId');
    const pdfPayFrom = document.getElementById('pdfPayFrom');
    const pdfPayTo = document.getElementById('pdfPayTo');
    const btnPdfPay = document.getElementById('btnPdfPay');
    const pdfViolPlate = document.getElementById('pdfViolPlate');
    const pdfViolFrom = document.getElementById('pdfViolFrom');
    const pdfViolTo = document.getElementById('pdfViolTo');
    const btnPdfViol = document.getElementById('btnPdfViol');

    if (pdfViolPlate) pdfViolPlate.addEventListener('input', () => { pdfViolPlate.value = (pdfViolPlate.value || '').toString().toUpperCase().replace(/\s+/g, ''); });

    if (btnPdfFleet) {
      btnPdfFleet.addEventListener('click', async () => {
        const opId = Number((pdfFleetOperatorId && pdfFleetOperatorId.value) ? pdfFleetOperatorId.value : 0);
        if (!opId) { toast('Enter Operator ID.', 'error'); return; }
        btnPdfFleet.disabled = true;
        const old = btnPdfFleet.textContent;
        btnPdfFleet.textContent = 'Generating...';
        try {
          const fd = new FormData();
          fd.append('operator_id', String(opId));
          const res = await fetch(rootUrl + '/admin/api/module1/generate_declared_fleet.php', { method: 'POST', body: fd });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && (data.message || data.error)) ? String(data.message || data.error) : 'generate_failed');
          const pdfFile = (data.files && data.files.pdf) ? String(data.files.pdf) : '';
          if (!pdfFile) throw new Error('pdf_not_generated');
          const pdfUrl = rootUrl + '/admin/uploads/' + encodeURIComponent(pdfFile);
          window.open(pdfUrl, '_blank', 'noopener');
          toast('Declared fleet PDF generated.');
        } catch (e) {
          toast(e.message || 'Failed', 'error');
        } finally {
          btnPdfFleet.disabled = false;
          btnPdfFleet.textContent = old;
        }
      });
    }

    if (btnPdfInspection) {
      btnPdfInspection.addEventListener('click', () => {
        const sid = Number((pdfInspectionScheduleId && pdfInspectionScheduleId.value) ? pdfInspectionScheduleId.value : 0);
        if (!sid) { toast('Enter Schedule ID.', 'error'); return; }
        window.open(rootUrl + '/admin/api/module4/inspection_report.php?format=pdf&schedule_id=' + encodeURIComponent(String(sid)), '_blank', 'noopener');
      });
    }

    if (btnPdfPay) {
      btnPdfPay.addEventListener('click', () => {
        const qs = new URLSearchParams();
        qs.set('terminal_id', String(Number((pdfPayTerminalId && pdfPayTerminalId.value) ? pdfPayTerminalId.value : 0) || 0));
        if (pdfPayFrom && pdfPayFrom.value) qs.set('from', pdfPayFrom.value);
        if (pdfPayTo && pdfPayTo.value) qs.set('to', pdfPayTo.value);
        window.open(rootUrl + '/admin/api/module5/payment_summary_pdf.php?' + qs.toString(), '_blank', 'noopener');
      });
    }

    if (btnPdfViol) {
      btnPdfViol.addEventListener('click', () => {
        const plate = (pdfViolPlate && pdfViolPlate.value) ? pdfViolPlate.value.trim().toUpperCase() : '';
        if (!plate) { toast('Enter Plate.', 'error'); return; }
        const qs = new URLSearchParams();
        qs.set('plate', plate);
        if (pdfViolFrom && pdfViolFrom.value) qs.set('from', pdfViolFrom.value);
        if (pdfViolTo && pdfViolTo.value) qs.set('to', pdfViolTo.value);
        window.open(rootUrl + '/admin/api/module3/violation_history_pdf.php?' + qs.toString(), '_blank', 'noopener');
      });
    }

    const congestionBody = document.getElementById('congestionBody');
    const btnReloadCongestion = document.getElementById('btnReloadCongestion');
    async function loadCongestion() {
      if (!congestionBody) return;
      congestionBody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>`;
      try {
        const res = await fetch(rootUrl + '/admin/api/module5/occupancy_dashboard.php?type=Terminal&limit=200');
        const data = await res.json().catch(() => null);
        if (!data || !data.ok || !Array.isArray(data.data)) throw new Error('load_failed');
        const rows = data.data.slice().sort((a, b) => (Number(b.congestion_pct || 0) - Number(a.congestion_pct || 0))).slice(0, 12);
        if (!rows.length) {
          congestionBody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-slate-500 font-medium italic">No data.</td></tr>`;
          return;
        }
        const badge = (pct) => {
          const p = Number(pct) || 0;
          if (p >= 80) return 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-300';
          if (p >= 50) return 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-300';
          return 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-300';
        };
        const fmt = (n) => (Math.round((Number(n) || 0) * 10) / 10).toFixed(1) + '%';
        congestionBody.innerHTML = rows.map((r) => `
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
            <td class="py-4 px-6 font-bold text-slate-900 dark:text-white">${esc(r.name || '')}</td>
            <td class="py-4 px-4"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ${badge(r.congestion_pct)}">${fmt(r.congestion_pct)}</span></td>
            <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${Number(r.occupied || 0)}</td>
            <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${Number(r.free || 0)}</td>
            <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${Number(r.queue_len || 0)}</td>
          </tr>
        `).join('');
      } catch (e) {
        congestionBody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-rose-600 font-semibold">Failed to load.</td></tr>`;
      }
    }
    if (btnReloadCongestion) btnReloadCongestion.addEventListener('click', loadCongestion);
    loadCongestion();

    const violFrom = document.getElementById('violFrom');
    const violTo = document.getElementById('violTo');
    const btnReloadViolStats = document.getElementById('btnReloadViolStats');
    const violStatsBody = document.getElementById('violStatsBody');
    async function loadCommonViolations() {
      if (!violStatsBody) return;
      violStatsBody.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>`;
      try {
        const qs = new URLSearchParams();
        qs.set('limit', '10');
        if (violFrom && violFrom.value) qs.set('from', violFrom.value);
        if (violTo && violTo.value) qs.set('to', violTo.value);
        const res = await fetch(rootUrl + '/admin/api/analytics/common_violations.php?' + qs.toString());
        const data = await res.json().catch(() => null);
        if (!data || !data.ok || !Array.isArray(data.data)) throw new Error('load_failed');
        const rows = data.data;
        if (!rows.length) {
          violStatsBody.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-slate-500 font-medium italic">No data.</td></tr>`;
          return;
        }
        violStatsBody.innerHTML = rows.map((r) => `
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
            <td class="py-4 px-6 font-black text-slate-900 dark:text-white">${esc(r.violation_code || '')}</td>
            <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${esc(r.description || '')}</td>
            <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${esc(r.total_count || 0)}</td>
            <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-bold">₱${Number(r.total_amount || 0).toFixed(2)}</td>
          </tr>
        `).join('');
      } catch (e) {
        violStatsBody.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-rose-600 font-semibold">Failed to load.</td></tr>`;
      }
    }
    if (btnReloadViolStats) btnReloadViolStats.addEventListener('click', loadCommonViolations);
    if (violFrom) violFrom.addEventListener('change', loadCommonViolations);
    if (violTo) violTo.addEventListener('change', loadCommonViolations);
    loadCommonViolations();

    const peakHoursAhead = document.getElementById('peakHoursAhead');
    const btnReloadPeak = document.getElementById('btnReloadPeak');
    const peakList = document.getElementById('peakList');
    async function loadPeakHours() {
      if (!peakList) return;
      peakList.innerHTML = `<div class="py-6 text-center text-slate-500 font-medium italic">Loading...</div>`;
      try {
        const hrs = Number((peakHoursAhead && peakHoursAhead.value) ? peakHoursAhead.value : 24) || 24;
        const res = await fetch(rootUrl + '/admin/api/analytics/demand_forecast.php?area_type=terminal&hours=' + encodeURIComponent(String(hrs)));
        const data = await res.json().catch(() => null);
        if (!data || !data.ok || !Array.isArray(data.spikes)) throw new Error('load_failed');
        const spikes = data.spikes || [];
        if (!spikes.length) {
          peakList.innerHTML = `<div class="py-6 text-center text-slate-500 font-medium italic">No spikes detected.</div>`;
          return;
        }
        peakList.innerHTML = spikes.slice(0, 8).map((s) => `
          <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/40">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="font-black text-slate-900 dark:text-white truncate">${esc(s.area_label || '')}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1">Peak: ${esc(s.peak_hour || '')} • Baseline: ${esc(s.baseline || '')}</div>
              </div>
              <div class="text-right">
                <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Predicted</div>
                <div class="text-2xl font-black text-rose-600 dark:text-rose-300">${esc(s.predicted_peak || 0)}</div>
              </div>
            </div>
          </div>
        `).join('');
      } catch (e) {
        peakList.innerHTML = `<div class="py-6 text-center text-rose-600 font-semibold">Failed to load.</div>`;
      }
    }
    if (btnReloadPeak) btnReloadPeak.addEventListener('click', loadPeakHours);
    if (peakHoursAhead) peakHoursAhead.addEventListener('change', loadPeakHours);
    loadPeakHours();
  })();
</script>

