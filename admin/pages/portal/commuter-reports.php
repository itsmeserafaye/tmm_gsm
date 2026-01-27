<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['SuperAdmin', 'Admin', 'Franchise Officer']);
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Commuter Reports</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Issues submitted from the Public/Commuter portal. Update status so commuters can track progress using their reference number.</p>
    </div>
    <div class="flex items-center gap-2">
      <button id="btnRefreshComplaints" type="button" class="inline-flex items-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
        Refresh
      </button>
    </div>
  </div>

  <div id="complaintsToast" class="hidden fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:w-[420px] z-[120]">
    <div id="complaintsToastInner" class="pointer-events-auto px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold"></div>
  </div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div class="flex flex-col sm:flex-row gap-3 flex-1">
        <div class="relative w-full sm:w-60">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
          <select id="complaintsStatus" class="w-full px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All</option>
            <option value="Submitted">Submitted</option>
            <option value="Under Review">Under Review</option>
            <option value="Resolved">Resolved</option>
            <option value="Dismissed">Dismissed</option>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-[2.35rem] w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>

        <div class="relative flex-1">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Search</label>
          <i data-lucide="search" class="absolute left-4 top-[2.35rem] w-4 h-4 text-slate-400"></i>
          <input id="complaintsSearch" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder:text-slate-400" placeholder="Ref no, plate, location, description...">
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-200">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Ref</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Type</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden lg:table-cell">Route</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Plate</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Status</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Created</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="complaintsTbody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <tr>
            <td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function () {
    const rootUrl = (window.TMM_ROOT_URL || '').toString();
    const listUrl = rootUrl + '/admin/api/complaints/list.php';
    const updateUrl = rootUrl + '/admin/api/complaints/update_status.php';

    const tbody = document.getElementById('complaintsTbody');
    const statusSel = document.getElementById('complaintsStatus');
    const qInput = document.getElementById('complaintsSearch');
    const btnRefresh = document.getElementById('btnRefreshComplaints');

    const toast = document.getElementById('complaintsToast');
    const toastInner = document.getElementById('complaintsToastInner');
    function showToast(msg, kind) {
      if (!toast || !toastInner) return;
      toastInner.className = 'pointer-events-auto px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold ' + (kind === 'error' ? 'bg-rose-600' : 'bg-emerald-600');
      toastInner.textContent = msg;
      toast.classList.remove('hidden');
      setTimeout(() => toast.classList.add('hidden'), 2200);
    }

    let allRows = [];
    let loadDebounce = null;

    function esc(s) {
      return (s === null || s === undefined) ? '' : String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function badgeClass(status) {
      switch (String(status || '')) {
        case 'Submitted': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 'Under Review': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'Resolved': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
        case 'Dismissed': return 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300';
        default: return 'bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-200';
      }
    }

    function render() {
      if (!tbody) return;
      const q = (qInput && qInput.value ? qInput.value : '').toString().trim().toLowerCase();
        const filtered = allRows.filter((r) => {
        if (!q) return true;
        const hay = [
          r.ref_number, r.type, r.description, r.status, r.route_name, r.plate_number, r.location, r.terminal_name, r.ai_tags
        ].map((x) => (x || '').toString().toLowerCase()).join(' ');
        return hay.includes(q);
      });

      if (!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="py-12 text-center text-slate-500 font-medium italic">No reports found.</td></tr>';
        return;
      }

      tbody.innerHTML = filtered.map((r) => {
        const created = r.created_at ? esc(r.created_at) : '';
        const media = r.media_url ? `<a href="${esc(r.media_url)}" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:underline font-semibold"><i data-lucide="image" class="w-4 h-4"></i>Attachment</a>` : `<span class="text-slate-400">None</span>`;

        return `
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
            <td class="py-4 px-6">
              <div class="font-black text-slate-900 dark:text-white">${esc(r.ref_number)}</div>
              <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">${media}</div>
            </td>
            <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${esc(r.type)}</td>
            <td class="py-4 px-4 hidden lg:table-cell text-slate-600 dark:text-slate-300">${esc(r.route_name || 'N/A')}</td>
            <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-mono">${esc(r.plate_number || '')}</td>
            <td class="py-4 px-4">
              <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-black ${badgeClass(r.status)}">${esc(r.status)}</span>
            </td>
            <td class="py-4 px-4 hidden md:table-cell text-slate-500 font-medium text-xs">${created}</td>
            <td class="py-4 px-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button type="button" class="px-3 py-2 rounded-lg text-xs font-black bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" data-open="${esc(r.id)}">View</button>
                <select class="px-3 py-2 rounded-lg text-xs font-black bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200" data-status="${esc(r.id)}">
                  ${['Submitted','Under Review','Resolved','Dismissed'].map((s) => `<option value="${s}" ${s===r.status?'selected':''}>${s}</option>`).join('')}
                </select>
              </div>
            </td>
          </tr>
          <tr class="hidden" id="row-details-${esc(r.id)}">
            <td colspan="7" class="px-6 pb-6">
              <div class="mt-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Terminal</div>
                    <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">${esc(r.terminal_name || (r.terminal_id ? ('ID ' + r.terminal_id) : '—'))}</div>
                  </div>
                  <div>
                    <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">User</div>
                    <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">${r.user_id ? ('User ID ' + esc(r.user_id)) : 'Anonymous (not logged in)'}</div>
                  </div>
                  <div>
                    <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">AI Tags</div>
                    <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">${esc(r.ai_tags || '—')}</div>
                  </div>
                </div>
                <div class="mt-4">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Location Details</div>
                  <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">${esc(r.location || '—')}</div>
                </div>
                <div class="mt-4">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Description</div>
                  <div class="mt-2 whitespace-pre-wrap text-sm font-semibold text-slate-700 dark:text-slate-200">${esc(r.description || '')}</div>
                </div>
              </div>
            </td>
          </tr>
        `;
      }).join('');

      if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();

      tbody.querySelectorAll('[data-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = btn.getAttribute('data-open');
          const row = document.getElementById('row-details-' + id);
          if (!row) return;
          row.classList.toggle('hidden');
        });
      });

      tbody.querySelectorAll('select[data-status]').forEach((sel) => {
        sel.addEventListener('change', async () => {
          const id = sel.getAttribute('data-status');
          const next = sel.value;
          try {
            const res = await fetch(updateUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id: Number(id), status: next })
            });
            const data = await res.json().catch(() => null);
            if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'update_failed');
            const idx = allRows.findIndex((r) => String(r.id) === String(id));
            if (idx >= 0) allRows[idx].status = next;
            showToast('Status updated.');
            render();
          } catch (e) {
            showToast((e && e.message) ? e.message : 'Failed to update.', 'error');
          }
        });
      });
    }

    async function load() {
      if (!tbody) return;
      tbody.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>';
      const status = statusSel && statusSel.value ? statusSel.value : '';
      const url = status ? (listUrl + '?status=' + encodeURIComponent(status)) : listUrl;
      try {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await res.json().catch(() => null);
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
        allRows = Array.isArray(data.data) ? data.data : [];
        render();
      } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-rose-600 font-semibold">Failed to load reports.</td></tr>';
      }
    }

    if (statusSel) statusSel.addEventListener('change', () => load());
    if (qInput) qInput.addEventListener('input', () => {
      if (loadDebounce) clearTimeout(loadDebounce);
      loadDebounce = setTimeout(() => render(), 150);
    });
    if (btnRefresh) btnRefresh.addEventListener('click', () => load());

    load();
  })();
</script>
