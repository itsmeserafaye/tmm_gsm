<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$vehicleType = trim((string)($_GET['vehicle_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$canManage = has_any_permission(['module1.routes.write','module1.write']);

$conds = ["1=1"];
$params = [];
$types = '';
if ($q !== '') {
  $like = "%$q%";
  $conds[] = "(r.route_id LIKE ? OR r.route_code LIKE ? OR r.route_name LIKE ? OR r.origin LIKE ? OR r.destination LIKE ?)";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'sssss';
}
if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
  $conds[] = "r.vehicle_type=?";
  $params[] = $vehicleType;
  $types .= 's';
}
if ($status !== '' && $status !== 'Status') {
  $conds[] = "r.status=?";
  $params[] = $status;
  $types .= 's';
}

$sql = "SELECT
  r.id,
  r.route_id,
  r.route_code,
  r.route_name,
  r.vehicle_type,
  r.origin,
  r.destination,
  r.via,
  r.structure,
  r.authorized_units,
  r.status,
  r.approved_by,
  r.approved_date,
  r.created_at,
  r.updated_at,
  COALESCE(u.used_units,0) AS used_units
FROM routes r
LEFT JOIN (
  SELECT route_id, COALESCE(SUM(vehicle_count),0) AS used_units
  FROM franchise_applications
  WHERE status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')
  GROUP BY route_id
) u ON u.route_id=r.id
WHERE " . implode(' AND ', $conds) . "
ORDER BY r.status='Active' DESC, COALESCE(NULLIF(r.route_code,''), r.route_id) ASC, r.id DESC
LIMIT 1000";

$routes = [];
if ($params) {
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $routes[] = $r;
    $stmt->close();
  }
} else {
  $res = $db->query($sql);
  if ($res) while ($r = $res->fetch_assoc()) $routes[] = $r;
}
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Routes & LPTRP</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">Define authorized routes, set capacity limits, and use route availability as the basis for franchise endorsement.</p>
    </div>
    <div class="flex items-center gap-2">
      <?php if (has_permission('reports.export')): ?>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module1/export_routes.php?<?php echo http_build_query(['q'=>$q,'vehicle_type'=>$vehicleType,'status'=>$status,'format'=>'csv']); ?>"
          class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="download" class="w-4 h-4"></i>
          Export CSV
        </a>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module1/export_routes.php?<?php echo http_build_query(['q'=>$q,'vehicle_type'=>$vehicleType,'status'=>$status,'format'=>'excel']); ?>"
          class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
          Export Excel
        </a>
      <?php endif; ?>
      <?php if ($canManage): ?>
        <button type="button" id="btnAddRoute" class="inline-flex items-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="plus" class="w-4 h-4"></i>
          Add Route
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[120] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <form class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" method="GET">
      <input type="hidden" name="page" value="module1/submodule6">
      <div class="flex-1 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1 sm:max-w-md group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all placeholder:text-slate-400" placeholder="Search route code/name...">
        </div>
        <div class="relative w-full sm:w-52">
          <select name="vehicle_type" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Vehicle Types</option>
            <?php foreach (['Tricycle','Jeepney','UV','Bus'] as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $vehicleType === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
        <div class="relative w-full sm:w-44">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Active','Inactive'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="filter" class="w-4 h-4"></i>
          Apply
        </button>
        <a href="?page=module1/submodule6" class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          Reset
        </a>
      </div>
    </form>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-200">
            <th class="py-4 px-6">Route</th>
            <th class="py-4 px-4 hidden md:table-cell">Vehicle Type</th>
            <th class="py-4 px-4 hidden md:table-cell">Authorized</th>
            <th class="py-4 px-4 hidden md:table-cell">Used</th>
            <th class="py-4 px-4 hidden md:table-cell">Remaining</th>
            <th class="py-4 px-4">Status</th>
            <th class="py-4 px-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-800">
          <?php if (!$routes): ?>
            <tr><td colspan="7" class="py-10 px-6 text-sm text-slate-500 dark:text-slate-400 italic">No routes found.</td></tr>
          <?php endif; ?>
          <?php foreach ($routes as $r): ?>
            <?php
              $code = trim((string)($r['route_code'] ?? ''));
              if ($code === '') $code = trim((string)($r['route_id'] ?? ''));
              $name = trim((string)($r['route_name'] ?? ''));
              $vt = trim((string)($r['vehicle_type'] ?? ''));
              $au = (int)($r['authorized_units'] ?? 0);
              $used = (int)($r['used_units'] ?? 0);
              $rem = $au > 0 ? max(0, $au - $used) : 0;
              $st = trim((string)($r['status'] ?? 'Active'));
              $badge = $st === 'Active'
                ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
                : 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-700/40 dark:text-slate-300 dark:ring-slate-500/20';
              $fullRoute = trim((string)($r['origin'] ?? '') . ' → ' . (string)($r['destination'] ?? ''));
              $rowPayload = [
                'id' => (int)($r['id'] ?? 0),
                'route_code' => $code,
                'route_name' => $name,
                'vehicle_type' => $vt,
                'origin' => (string)($r['origin'] ?? ''),
                'destination' => (string)($r['destination'] ?? ''),
                'via' => (string)($r['via'] ?? ''),
                'structure' => (string)($r['structure'] ?? ''),
                'authorized_units' => (int)($r['authorized_units'] ?? 0),
                'approved_by' => (string)($r['approved_by'] ?? ''),
                'approved_date' => (string)($r['approved_date'] ?? ''),
                'status' => $st,
                'used_units' => $used,
              ];
            ?>
            <tr>
              <td class="py-4 px-6">
                <div class="text-sm font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($code); ?></div>
                <div class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($name !== '' ? $name : '-'); ?></div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 hidden sm:block"><?php echo htmlspecialchars($fullRoute !== ' → ' ? $fullRoute : '-'); ?></div>
              </td>
              <td class="py-4 px-4 text-sm font-semibold text-slate-700 dark:text-slate-200 hidden md:table-cell"><?php echo htmlspecialchars($vt !== '' ? $vt : '-'); ?></td>
              <td class="py-4 px-4 text-sm font-semibold text-slate-700 dark:text-slate-200 hidden md:table-cell"><?php echo (int)$au; ?></td>
              <td class="py-4 px-4 text-sm font-semibold text-slate-700 dark:text-slate-200 hidden md:table-cell"><?php echo (int)$used; ?></td>
              <td class="py-4 px-4 text-sm font-semibold text-slate-700 dark:text-slate-200 hidden md:table-cell"><?php echo (int)$rem; ?></td>
              <td class="py-4 px-4">
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
              </td>
              <td class="py-4 px-4 text-right">
                <div class="inline-flex items-center gap-2">
                  <button type="button" class="px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" data-route-view="1" data-route="<?php echo htmlspecialchars(json_encode($rowPayload), ENT_QUOTES); ?>">View</button>
                  <?php if ($canManage): ?>
                    <button type="button" class="px-3 py-2 rounded-lg bg-slate-900 dark:bg-slate-700 text-xs font-bold text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors" data-route-edit="1" data-route="<?php echo htmlspecialchars(json_encode($rowPayload), ENT_QUOTES); ?>">Edit</button>
                    <button type="button" class="px-3 py-2 rounded-lg bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 transition-colors" data-route-toggle="1" data-route="<?php echo htmlspecialchars(json_encode($rowPayload), ENT_QUOTES); ?>"><?php echo $st === 'Active' ? 'Deactivate' : 'Activate'; ?></button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="modalRoute" class="fixed inset-0 z-[200] hidden">
  <div id="modalRouteBackdrop" class="absolute inset-0 bg-slate-900/50 opacity-0 transition-opacity"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div id="modalRoutePanel" class="w-full max-w-3xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl transform scale-95 opacity-0 transition-all">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div class="font-black text-slate-900 dark:text-white" id="modalRouteTitle">Route</div>
        <button type="button" id="modalRouteClose" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div id="modalRouteBody" class="p-6 max-h-[80vh] overflow-y-auto"></div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const canManage = <?php echo json_encode($canManage); ?>;

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

    const modal = document.getElementById('modalRoute');
    const backdrop = document.getElementById('modalRouteBackdrop');
    const panel = document.getElementById('modalRoutePanel');
    const body = document.getElementById('modalRouteBody');
    const title = document.getElementById('modalRouteTitle');
    const closeBtn = document.getElementById('modalRouteClose');

    function openModal(html, t) {
      if (t) title.textContent = t;
      body.innerHTML = html;
      modal.classList.remove('hidden');
      requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('scale-95','opacity-0');
      });
      if (window.lucide) window.lucide.createIcons();
    }
    function closeModal() {
      panel.classList.add('scale-95','opacity-0');
      backdrop.classList.add('opacity-0');
      setTimeout(() => {
        modal.classList.add('hidden');
        body.innerHTML = '';
      }, 200);
    }
    if (backdrop) backdrop.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });

    function parsePayload(el) {
      try { return JSON.parse(el.getAttribute('data-route') || '{}'); } catch (e) { return {}; }
    }
    function esc(s) {
      return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');
    }

    function renderForm(r) {
      const id = r && r.id ? Number(r.id) : 0;
      const isEdit = id > 0;
      const routeCode = r && r.route_code ? String(r.route_code) : '';
      const routeName = r && r.route_name ? String(r.route_name) : '';
      const vt = r && r.vehicle_type ? String(r.vehicle_type) : '';
      const origin = r && r.origin ? String(r.origin) : '';
      const destination = r && r.destination ? String(r.destination) : '';
      const via = r && r.via ? String(r.via) : '';
      const structure = r && r.structure ? String(r.structure) : '';
      const au = r && r.authorized_units ? Number(r.authorized_units) : 0;
      const approvedBy = r && r.approved_by ? String(r.approved_by) : '';
      const approvedDate = r && r.approved_date ? String(r.approved_date) : '';
      const status = r && r.status ? String(r.status) : 'Active';

      return `
        <form id="formRouteSave" class="space-y-5" novalidate>
          ${isEdit ? `<input type="hidden" name="id" value="${id}">` : ``}
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route Code</label>
              <input name="route_code" required maxlength="64" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" value="${esc(routeCode)}" placeholder="e.g., TR-01">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route Name</label>
              <input name="route_name" required maxlength="128" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${esc(routeName)}" placeholder="e.g., Poblacion Loop">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle Type</label>
              <select name="vehicle_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                <option value="">Select</option>
                ${['Tricycle','Jeepney','UV','Bus'].map((t) => `<option value="${t}" ${t===vt?'selected':''}>${t}</option>`).join('')}
              </select>
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route Structure</label>
              <select name="structure" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                <option value="">Select</option>
                ${['Loop','Point-to-Point'].map((t) => `<option value="${t}" ${t===structure?'selected':''}>${t}</option>`).join('')}
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Origin</label>
              <input name="origin" maxlength="100" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${esc(origin)}" placeholder="Starting point">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Destination</label>
              <input name="destination" maxlength="100" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${esc(destination)}" placeholder="End point">
            </div>
          </div>

          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Via</label>
            <textarea name="via" rows="3" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Major streets / barangays">${esc(via)}</textarea>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Authorized Units</label>
              <input name="authorized_units" type="number" min="0" max="9999" step="1" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${au || 0}">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Approved By</label>
              <input name="approved_by" maxlength="128" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${esc(approvedBy)}" placeholder="Ordinance ref">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Approved Date</label>
              <input name="approved_date" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${esc(approvedDate)}">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
              <select name="status" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                ${['Active','Inactive'].map((t) => `<option value="${t}" ${t===status?'selected':''}>${t}</option>`).join('')}
              </select>
            </div>
            <div class="flex items-end justify-end gap-2">
              <button type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold" data-close="1">Cancel</button>
              <button id="btnRouteSave" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">${isEdit ? 'Save Changes' : 'Create Route'}</button>
            </div>
          </div>
        </form>
      `;
    }

    function openView(r) {
      const au = Number(r.authorized_units || 0);
      const used = Number(r.used_units || 0);
      const rem = au > 0 ? Math.max(0, au - used) : 0;
      openModal(`
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Route Code</div>
            <div class="mt-1 text-lg font-black text-slate-900 dark:text-white">${esc(r.route_code || '')}</div>
            <div class="mt-1 text-sm text-slate-600 dark:text-slate-300 font-semibold">${esc(r.route_name || '')}</div>
          </div>
          <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Capacity</div>
            <div class="mt-2 grid grid-cols-3 gap-2">
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Authorized</div>
                <div class="text-lg font-black text-slate-900 dark:text-white">${au}</div>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Used</div>
                <div class="text-lg font-black text-slate-900 dark:text-white">${used}</div>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Remaining</div>
                <div class="text-lg font-black ${rem>0?'text-emerald-700 dark:text-emerald-400':'text-rose-700 dark:text-rose-400'}">${rem}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Vehicle Type</div>
              <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">${esc(r.vehicle_type || '-')}</div>
            </div>
            <div>
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Structure</div>
              <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">${esc(r.structure || '-')}</div>
            </div>
            <div>
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Origin</div>
              <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">${esc(r.origin || '-')}</div>
            </div>
            <div>
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Destination</div>
              <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">${esc(r.destination || '-')}</div>
            </div>
            <div class="sm:col-span-2">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Via</div>
              <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200 whitespace-pre-wrap">${esc(r.via || '-')}</div>
            </div>
            <div>
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Approved By</div>
              <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">${esc(r.approved_by || '-')}</div>
            </div>
            <div>
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Approved Date</div>
              <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">${esc(r.approved_date || '-')}</div>
            </div>
          </div>
          <div class="mt-4 flex items-center justify-end gap-2">
            <button type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold" data-close="1">Close</button>
          </div>
        </div>

        <div class="mt-4 p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Assigned Operators</div>
              <div class="mt-1 text-sm text-slate-600 dark:text-slate-300 font-semibold">Operators with endorsed/approved franchises on this route.</div>
            </div>
          </div>
          <div id="routeOperatorsBox" class="mt-3 overflow-hidden rounded-md border border-slate-200 dark:border-slate-700">
            <div class="px-4 py-3 text-sm text-slate-500 italic">Loading...</div>
          </div>
        </div>
      `, 'Route • ' + (r.route_code || ''));
      const c = body.querySelector('[data-close="1"]');
      if (c) c.addEventListener('click', closeModal);

      const box = body.querySelector('#routeOperatorsBox');
      const routeId = Number(r.id || 0);
      if (box && routeId > 0) {
        fetch(rootUrl + '/admin/api/module1/route_operators.php?route_id=' + encodeURIComponent(String(routeId)), { headers: { 'Accept': 'application/json' } })
          .then((rr) => rr.json())
          .then((data) => {
            if (!data || !data.ok) { box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500 italic">Failed to load.</div>'; return; }
            const ops = Array.isArray(data.operators) ? data.operators : [];
            if (!ops.length) { box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500 italic">No assigned operators yet.</div>'; return; }
            box.innerHTML = `
              <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Operator</th>
                    <th class="px-4 py-3 text-right text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Units</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                  ${ops.map((o) => `
                    <tr>
                      <td class="px-4 py-3">
                        <div class="text-sm font-bold text-slate-900 dark:text-white">${esc(o.operator_name || '')}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold">${esc(o.operator_type || '')}${o.statuses ? ' • ' + esc(o.statuses) : ''}</div>
                      </td>
                      <td class="px-4 py-3 text-right font-black text-slate-900 dark:text-white">${Number(o.total_units || 0)}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            `;
          })
          .catch(() => { box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500 italic">Failed to load.</div>'; });
      }
    }

    async function saveRoute(form) {
      const fd = new FormData(form);
      const res = await fetch(rootUrl + '/admin/api/module1/save_route.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'save_failed');
      return data;
    }

    if (canManage) {
      const btnAdd = document.getElementById('btnAddRoute');
      if (btnAdd) {
        btnAdd.addEventListener('click', () => {
          openModal(renderForm({ status: 'Active' }), 'Add Route');
          const close = body.querySelector('[data-close="1"]');
          if (close) close.addEventListener('click', closeModal);
          const form = document.getElementById('formRouteSave');
          const btn = document.getElementById('btnRouteSave');
          if (form && btn) {
            form.addEventListener('submit', async (e) => {
              e.preventDefault();
              if (!form.checkValidity()) { form.reportValidity(); return; }
              btn.disabled = true;
              btn.textContent = 'Saving...';
              try {
                await saveRoute(form);
                showToast('Route saved.');
                const params = new URLSearchParams(window.location.search || '');
                params.set('page', 'module1/submodule6');
                window.location.search = params.toString();
              } catch (err) {
                showToast((err && err.message) ? err.message : 'Failed', 'error');
                btn.disabled = false;
                btn.textContent = 'Create Route';
              }
            });
          }
        });
      }
    }

    document.querySelectorAll('[data-route-view="1"]').forEach((btn) => {
      btn.addEventListener('click', () => openView(parsePayload(btn)));
    });

    if (canManage) {
      document.querySelectorAll('[data-route-edit="1"]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const r = parsePayload(btn);
          openModal(renderForm(r), 'Edit Route • ' + (r.route_code || ''));
          const close = body.querySelector('[data-close="1"]');
          if (close) close.addEventListener('click', closeModal);
          const form = document.getElementById('formRouteSave');
          const btnSave = document.getElementById('btnRouteSave');
          if (form && btnSave) {
            form.addEventListener('submit', async (e) => {
              e.preventDefault();
              if (!form.checkValidity()) { form.reportValidity(); return; }
              btnSave.disabled = true;
              btnSave.textContent = 'Saving...';
              try {
                await saveRoute(form);
                showToast('Route saved.');
                const params = new URLSearchParams(window.location.search || '');
                params.set('page', 'module1/submodule6');
                window.location.search = params.toString();
              } catch (err) {
                showToast((err && err.message) ? err.message : 'Failed', 'error');
                btnSave.disabled = false;
                btnSave.textContent = 'Save Changes';
              }
            });
          }
        });
      });

      document.querySelectorAll('[data-route-toggle="1"]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const r = parsePayload(btn);
          if (!r || !r.route_code) return;
          const next = (String(r.status || 'Active') === 'Active') ? 'Inactive' : 'Active';
          try {
            const fd = new FormData();
            fd.append('id', String(r.id || 0));
            fd.append('route_code', String(r.route_code || ''));
            fd.append('route_name', String(r.route_name || ''));
            fd.append('vehicle_type', String(r.vehicle_type || ''));
            fd.append('origin', String(r.origin || ''));
            fd.append('destination', String(r.destination || ''));
            fd.append('via', String(r.via || ''));
            fd.append('structure', String(r.structure || ''));
            fd.append('authorized_units', String(r.authorized_units || 0));
            fd.append('approved_by', String(r.approved_by || ''));
            fd.append('approved_date', String(r.approved_date || ''));
            fd.append('status', next);
            const res = await fetch(rootUrl + '/admin/api/module1/save_route.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'save_failed');
            showToast(next === 'Active' ? 'Route activated.' : 'Route deactivated.');
            const params = new URLSearchParams(window.location.search || '');
            params.set('page', 'module1/submodule6');
            window.location.search = params.toString();
          } catch (err) {
            showToast((err && err.message) ? err.message : 'Failed', 'error');
          }
        });
      });
    }
  })();
</script>
