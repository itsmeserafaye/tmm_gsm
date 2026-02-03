<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.read','module2.endorse','module2.approve','module2.history']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Route Assignment</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">View assigned operators per route and vehicles linked to them.</p>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center justify-between gap-3">
      <div class="relative max-w-sm group w-full">
        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
        <input id="routeSearch" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-white dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder:text-slate-400" placeholder="Search route name/code/origin/destination...">
      </div>
      <button id="btnRefreshRoutes" type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">
        Refresh
      </button>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Route</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Origin</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Destination</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Fare</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Units</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Action</th>
          </tr>
        </thead>
        <tbody id="routesBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <tr><td colspan="6" class="py-12 text-center text-slate-500 font-medium italic">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="routeModal" class="fixed inset-0 z-[200] hidden">
  <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-5xl rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden">
      <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
        <div>
          <div id="routeModalTitle" class="text-sm font-black text-slate-900 dark:text-white">Route</div>
          <div id="routeModalSub" class="text-xs text-slate-500 dark:text-slate-400 font-semibold"></div>
        </div>
        <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <div class="p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="p-4 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Authorized Units</div>
            <div id="routeModalAuthorized" class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">0</div>
          </div>
          <div class="p-4 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Active Units</div>
            <div id="routeModalActive" class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">0</div>
          </div>
          <div class="p-4 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Fare</div>
            <input id="routeFareInput" type="text" disabled class="mt-2 w-full px-3 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="₱ 0.00">
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40 flex items-center justify-between gap-3">
              <div>
                <div class="font-black text-slate-900 dark:text-white">Assigned Operators & Vehicles</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1">Operators assigned through approved franchises and their linked vehicles.</div>
              </div>
              <button id="btnRefreshAssigned" type="button" class="px-4 py-2 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold">Refresh</button>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                  <tr class="text-left text-slate-500 dark:text-slate-400">
                    <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Plate</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Vehicle</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Operator</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Operator Type</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Franchise</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Status</th>
                  </tr>
                </thead>
                <tbody id="assignedBody" class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr><td colspan="6" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
                </tbody>
              </table>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const autoRouteId = Number((new URLSearchParams(window.location.search)).get('route_id') || 0);
    let autoOpened = false;

    function showToast(message, type) {
      const container = document.getElementById('toast-container');
      if (!container) return;
      const t = (type || 'success').toString();
      const color = t === 'error' ? 'bg-rose-600' : 'bg-emerald-600';
      const el = document.createElement('div');
      el.className = `pointer-events-auto px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold ${color}`;
      el.textContent = message;
      container.appendChild(el);
      setTimeout(() => { el.classList.add('opacity-0'); el.style.transition = 'opacity 250ms'; }, 2600);
      setTimeout(() => { el.remove(); }, 3000);
    }

    const routesBody = document.getElementById('routesBody');
    const routeSearch = document.getElementById('routeSearch');
    const btnRefreshRoutes = document.getElementById('btnRefreshRoutes');

    let allRoutes = [];
    function renderRoutes(rows) {
      if (!routesBody) return;
      if (!rows.length) {
        routesBody.innerHTML = '<tr><td colspan="6" class="py-12 text-center text-slate-500 font-medium italic">No routes.</td></tr>';
        return;
      }
      routesBody.innerHTML = rows.map(r => {
        const code = (r.route_code || r.route_id || '').toString();
        const name = (r.route_name || '').toString();
        const origin = (r.origin || '-').toString();
        const dest = (r.destination || '-').toString();
        let fare = '-';
        if (r.fare !== null && r.fare !== undefined && String(r.fare).trim() !== '') {
          const fv = String(r.fare).trim();
          const n = Number(fv);
          fare = Number.isNaN(n) ? ('₱' + fv) : ('₱' + n.toFixed(2));
        }
        const auth = Number(r.authorized_units || 0);
        const active = Number(r.active_units || 0);
        return `
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors" data-route-row>
            <td class="py-4 px-6">
              <div class="font-black text-slate-900 dark:text-white">${name || code}</div>
              <div class="text-xs text-slate-500 font-semibold">${code}</div>
            </td>
            <td class="py-4 px-4 hidden md:table-cell text-slate-700 dark:text-slate-200 font-semibold">${origin}</td>
            <td class="py-4 px-4 hidden md:table-cell text-slate-700 dark:text-slate-200 font-semibold">${dest}</td>
            <td class="py-4 px-4 text-slate-900 dark:text-white font-bold">${fare}</td>
            <td class="py-4 px-4">
              <span class="text-slate-900 dark:text-white font-black">${active}</span>
              <span class="text-slate-500 font-semibold">/ ${auth}</span>
            </td>
            <td class="py-4 px-4 text-right">
              <button type="button" class="btnViewRoute inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20" data-route-id="${r.id}" title="View assignment">
                <i data-lucide="eye" class="w-4 h-4"></i>
              </button>
            </td>
          </tr>
        `;
      }).join('');
      Array.from(document.querySelectorAll('.btnViewRoute')).forEach(btn => {
        btn.addEventListener('click', () => {
          const id = Number(btn.getAttribute('data-route-id') || 0);
          if (id) openRouteModal(id);
        });
      });
      if (window.lucide) window.lucide.createIcons();
    }

    function applyFilter() {
      const q = (routeSearch && routeSearch.value || '').toString().trim().toLowerCase();
      const rows = allRoutes.filter(r => {
        const hay = [
          r.route_code, r.route_id, r.route_name, r.origin, r.destination
        ].map(v => (v || '').toString().toLowerCase()).join(' ');
        return !q || hay.indexOf(q) !== -1;
      });
      renderRoutes(rows);
    }

    async function loadRoutes() {
      if (!routesBody) return;
      routesBody.innerHTML = '<tr><td colspan="6" class="py-12 text-center text-slate-500 font-medium italic">Loading...</td></tr>';
      try {
        const res = await fetch(rootUrl + '/admin/api/module2/routes_list.php');
        const data = await res.json();
        if (!data || !data.ok) throw new Error('load_failed');
        allRoutes = Array.isArray(data.data) ? data.data : [];
        applyFilter();
        if (!autoOpened && autoRouteId) {
          const exists = allRoutes.some(r => Number(r.id) === Number(autoRouteId));
          if (exists) {
            openRouteModal(autoRouteId);
            autoOpened = true;
            try {
              const url = new URL(window.location.href);
              url.searchParams.delete('route_id');
              window.history.replaceState({}, '', url.toString());
            } catch (e) {}
          }
        }
      } catch (e) {
        routesBody.innerHTML = '<tr><td colspan="6" class="py-12 text-center text-rose-600 font-semibold">Failed to load routes.</td></tr>';
      }
    }

    if (routeSearch) routeSearch.addEventListener('input', () => applyFilter());
    if (btnRefreshRoutes) btnRefreshRoutes.addEventListener('click', () => loadRoutes());

    const routeModal = document.getElementById('routeModal');
    const routeModalTitle = document.getElementById('routeModalTitle');
    const routeModalSub = document.getElementById('routeModalSub');
    const routeModalAuthorized = document.getElementById('routeModalAuthorized');
    const routeModalActive = document.getElementById('routeModalActive');
    const routeFareInput = document.getElementById('routeFareInput');
    const assignedBody = document.getElementById('assignedBody');
    const btnRefreshAssigned = document.getElementById('btnRefreshAssigned');

    let activeRouteId = 0;

    function openModal() { if (routeModal) routeModal.classList.remove('hidden'); }
    function closeModal() { if (routeModal) routeModal.classList.add('hidden'); }
    if (routeModal) {
      const closeBtn = routeModal.querySelector('[data-modal-close]');
      const backdrop = routeModal.querySelector('[data-modal-backdrop]');
      if (closeBtn) closeBtn.addEventListener('click', closeModal);
      if (backdrop) backdrop.addEventListener('click', closeModal);
    }

    async function loadAssigned(routeId) {
      if (!assignedBody) return;
      assignedBody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>';
      const res = await fetch(rootUrl + '/admin/api/module2/route_assigned_vehicles.php?route_id=' + encodeURIComponent(String(routeId)));
      const data = await res.json();
      if (!data || !data.ok) throw new Error('load_failed');
      const rows = Array.isArray(data.data) ? data.data : [];
      if (!rows.length) {
        assignedBody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-500 font-medium italic">No assigned operators/vehicles yet.</td></tr>';
        return;
      }
      assignedBody.innerHTML = rows.map(r => {
        const plate = (r.plate_number || '-').toString();
        const vtype = (r.vehicle_type || '-').toString();
        const vstatus = (r.vehicle_status || '').toString();
        const op = (r.operator_name || '-').toString();
        const opType = (r.operator_type || '-').toString();
        const fr = (r.franchise_ref_number || '-').toString();
        const st = (r.franchise_status || '-').toString();
        const badge = (st === 'LTFRB-Approved' || st === 'Approved')
          ? 'bg-emerald-100 text-emerald-700'
          : ((st === 'Endorsed' || st === 'LGU-Endorsed') ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-700');
        const vWarn = vstatus === 'Blocked'
          ? '<div class="mt-1 inline-flex items-center gap-2 px-2 py-0.5 rounded-lg text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200">Operation blocked</div>'
          : (vstatus === 'Inactive'
            ? '<div class="mt-1 inline-flex items-center gap-2 px-2 py-0.5 rounded-lg text-[10px] font-black bg-amber-50 text-amber-800 border border-amber-200">Inactive</div>'
            : '');
        return `
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
            <td class="py-3 px-4">
              <div class="font-black text-slate-900 dark:text-white">${plate}</div>
              ${vWarn}
            </td>
            <td class="py-3 px-3 text-slate-700 dark:text-slate-200 font-semibold">${vtype}</td>
            <td class="py-3 px-3 text-slate-700 dark:text-slate-200 font-semibold">${op}</td>
            <td class="py-3 px-3 text-slate-700 dark:text-slate-200 font-semibold">${opType}</td>
            <td class="py-3 px-3 text-slate-700 dark:text-slate-200 font-semibold">${fr}</td>
            <td class="py-3 px-3"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ${badge}">${st}</span></td>
          </tr>
        `;
      }).join('');
    }

    function openRouteModal(routeId) {
      activeRouteId = routeId;
      const r = allRoutes.find(x => Number(x.id) === Number(routeId));
      if (routeModalTitle) routeModalTitle.textContent = r ? ((r.route_name || r.route_code || r.route_id) || 'Route') : 'Route';
      if (routeModalSub) routeModalSub.textContent = r ? ((r.origin || '-') + ' → ' + (r.destination || '-') ) : '';
      if (routeModalAuthorized) routeModalAuthorized.textContent = String(Number(r && r.authorized_units || 0));
      if (routeModalActive) routeModalActive.textContent = String(Number(r && r.active_units || 0));
      if (routeFareInput) {
        if (r && r.fare !== null && r.fare !== undefined && String(r.fare).trim() !== '') {
          const fv = String(r.fare).trim();
          const n = Number(fv);
          routeFareInput.value = Number.isNaN(n) ? ('₱ ' + fv) : ('₱ ' + n.toFixed(2));
        } else {
          routeFareInput.value = '';
        }
      }
      openModal();
      loadAssigned(routeId).catch(() => {});
      if (window.lucide) window.lucide.createIcons();
    }

    if (btnRefreshAssigned) btnRefreshAssigned.addEventListener('click', () => { if (activeRouteId) loadAssigned(activeRouteId).catch(() => {}); });

    loadRoutes().catch(() => {});
  })();
</script>
