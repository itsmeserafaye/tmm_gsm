<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
if ($status !== '' && !in_array($status, ['Active','Inactive'], true)) $status = '';

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
  $conds[] = "(a.area_code LIKE ? OR a.area_name LIKE ? OR COALESCE(a.barangay,'') LIKE ?)";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'sss';
}
if ($status !== '') {
  $conds[] = "a.status=?";
  $params[] = $status;
  $types .= 's';
}

$sql = "SELECT
  a.id,
  a.area_code,
  a.area_name,
  a.barangay,
  a.authorized_units,
  a.fare_min,
  a.fare_max,
  a.coverage_notes,
  a.status,
  COALESCE(p.points_count,0) AS points_count,
  COALESCE(p.points, '') AS points
FROM tricycle_service_areas a
LEFT JOIN (
  SELECT area_id,
         COUNT(*) AS points_count,
         GROUP_CONCAT(point_name ORDER BY sort_order ASC, point_id ASC SEPARATOR '\n') AS points
  FROM tricycle_service_area_points
  GROUP BY area_id
) p ON p.area_id=a.id
WHERE " . implode(' AND ', $conds) . "
ORDER BY a.status='Active' DESC, a.area_name ASC, a.id DESC
LIMIT 2000";

$areas = [];
if ($params) {
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) $areas[] = $r;
    $stmt->close();
  }
} else {
  $res = $db->query($sql);
  if ($res) while ($r = $res->fetch_assoc()) $areas[] = $r;
}
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Tricycle Service Areas (TODA Zones)</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">Manage tricycle coverage zones using terminals/landmarks instead of long origin–destination corridors.</p>
    </div>
    <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full md:w-auto">
      <a href="?page=puv-database/routes-lptrp" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Back to Routes
      </a>
      <?php if ($canManage): ?>
        <button type="button" id="btnAddArea" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="plus" class="w-4 h-4"></i>
          Add Service Area
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[120] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <form class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" method="GET">
      <input type="hidden" name="page" value="puv-database/tricycle-service-areas">
      <div class="flex-1 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1 sm:max-w-md group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all placeholder:text-slate-400" placeholder="Search area code/name/barangay...">
        </div>
        <div class="relative w-full sm:w-44">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Active','Inactive'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
          <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
          </span>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="filter" class="w-4 h-4"></i>
          Apply
        </button>
        <a href="?page=puv-database/tricycle-service-areas" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
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
            <th class="py-4 px-6">Service Area</th>
            <th class="py-4 px-4 hidden md:table-cell">Points</th>
            <th class="py-4 px-4 hidden md:table-cell">Authorized</th>
            <th class="py-4 px-4 hidden md:table-cell">Fare</th>
            <th class="py-4 px-4">Status</th>
            <th class="py-4 px-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y-2 divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-800">
          <?php if (!$areas): ?>
            <tr><td colspan="6" class="py-10 px-6 text-sm text-slate-500 dark:text-slate-400 italic">No service areas found.</td></tr>
          <?php endif; ?>
          <?php foreach ($areas as $a): ?>
            <?php
              $id = (int)($a['id'] ?? 0);
              $code = trim((string)($a['area_code'] ?? ''));
              $name = trim((string)($a['area_name'] ?? ''));
              $barangay = trim((string)($a['barangay'] ?? ''));
              $au = (int)($a['authorized_units'] ?? 0);
              $st = trim((string)($a['status'] ?? 'Active'));
              $badge = $st === 'Active'
                ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
                : 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-700/40 dark:text-slate-300 dark:ring-slate-500/20';
              $fareMin = $a['fare_min'] === null || $a['fare_min'] === '' ? null : (float)$a['fare_min'];
              $fareMax = $a['fare_max'] === null || $a['fare_max'] === '' ? null : (float)$a['fare_max'];
              if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;
              $fareText = '-';
              if ($fareMin !== null) {
                if ($fareMax !== null && abs($fareMin - $fareMax) >= 0.001) $fareText = '₱ ' . number_format($fareMin, 2) . ' – ' . number_format($fareMax, 2);
                else $fareText = '₱ ' . number_format((float)$fareMin, 2);
              }
              $points = (string)($a['points'] ?? '');
              $payload = [
                'id' => $id,
                'area_code' => $code,
                'area_name' => $name,
                'barangay' => $barangay,
                'authorized_units' => $au,
                'fare_min' => $fareMin,
                'fare_max' => $fareMax,
                'coverage_notes' => (string)($a['coverage_notes'] ?? ''),
                'status' => $st,
                'points' => $points,
              ];
            ?>
            <tr>
              <td class="py-4 px-6">
                <div class="text-sm font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($code); ?></div>
                <div class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($name !== '' ? $name : '-'); ?></div>
                <?php if ($barangay !== ''): ?>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars($barangay); ?></div>
                <?php endif; ?>
              </td>
              <td class="py-4 px-4 text-sm font-semibold text-slate-700 dark:text-slate-200 hidden md:table-cell">
                <div class="line-clamp-3 whitespace-pre-line"><?php echo htmlspecialchars($points !== '' ? $points : '-'); ?></div>
              </td>
              <td class="py-4 px-4 text-sm font-semibold text-slate-700 dark:text-slate-200 hidden md:table-cell"><?php echo (int)$au; ?></td>
              <td class="py-4 px-4 text-sm font-black text-slate-900 dark:text-white hidden md:table-cell"><?php echo htmlspecialchars($fareText); ?></td>
              <td class="py-4 px-4">
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
              </td>
              <td class="py-4 px-4 text-right">
                <div class="inline-flex items-center gap-2">
                  <button type="button" title="View / Edit" class="inline-flex items-center justify-center p-2 rounded-lg bg-slate-900 dark:bg-slate-700 text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors" data-area-edit="1" data-area="<?php echo htmlspecialchars(json_encode($payload), ENT_QUOTES); ?>">
                    <i data-lucide="pencil" class="w-4 h-4"></i>
                  </button>
                  <?php if ($canManage): ?>
                    <button type="button" title="Delete" class="inline-flex items-center justify-center p-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition-colors" data-area-del="1" data-id="<?php echo (int)$id; ?>" data-code="<?php echo htmlspecialchars($code, ENT_QUOTES); ?>">
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
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

<div id="modalArea" class="fixed inset-0 z-[200] hidden">
  <div id="modalAreaBackdrop" class="absolute inset-0 bg-slate-900/50 opacity-0 transition-opacity"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div id="modalAreaPanel" class="w-full max-w-3xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl transform scale-95 opacity-0 transition-all">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div class="font-black text-slate-900 dark:text-white" id="modalAreaTitle">Service Area</div>
        <button type="button" id="modalAreaClose" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div id="modalAreaBody" class="p-6 max-h-[80vh] overflow-y-auto"></div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const canManage = <?php echo json_encode($canManage); ?>;
    const modal = document.getElementById('modalArea');
    const panel = document.getElementById('modalAreaPanel');
    const backdrop = document.getElementById('modalAreaBackdrop');
    const title = document.getElementById('modalAreaTitle');
    const body = document.getElementById('modalAreaBody');
    const closeBtn = document.getElementById('modalAreaClose');

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

    function esc(s) {
      return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');
    }
    function parsePayload(el) {
      try { return JSON.parse(el.getAttribute('data-area') || '{}'); } catch (e) { return {}; }
    }

    function openModal(html, t) {
      if (!modal || !panel || !backdrop || !body || !title) return;
      title.textContent = t || 'Service Area';
      body.innerHTML = html;
      modal.classList.remove('hidden');
      requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('scale-95','opacity-0');
      });
      try { document.body.style.overflow = 'hidden'; } catch (e) { }
      if (window.lucide) window.lucide.createIcons();
    }
    function closeModal() {
      if (!modal || !panel || !backdrop) return;
      backdrop.classList.add('opacity-0');
      panel.classList.add('scale-95','opacity-0');
      setTimeout(() => modal.classList.add('hidden'), 180);
      try { document.body.style.overflow = ''; } catch (e) { }
    }
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', (e) => { if (e && e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e && e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal(); });

    function renderForm(a) {
      const id = a && a.id ? Number(a.id) : 0;
      const isEdit = id > 0;
      const code = a && a.area_code ? String(a.area_code) : '';
      const name = a && a.area_name ? String(a.area_name) : '';
      const barangay = a && a.barangay ? String(a.barangay) : '';
      const au = a && a.authorized_units ? Number(a.authorized_units) : 0;
      const fareMin = (a && a.fare_min !== null && a.fare_min !== undefined && a.fare_min !== '') ? Number(a.fare_min) : '';
      const fareMax = (a && a.fare_max !== null && a.fare_max !== undefined && a.fare_max !== '') ? Number(a.fare_max) : '';
      const status = a && a.status ? String(a.status) : 'Active';
      const coverage = a && a.coverage_notes ? String(a.coverage_notes) : '';
      const points = a && a.points ? String(a.points) : '';

      return `
        <form id="formAreaSave" class="space-y-5" novalidate>
          ${isEdit ? `<input type="hidden" name="id" value="${id}">` : ``}
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Area Code</label>
              <input name="area_code" required maxlength="64" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" value="${esc(code)}" placeholder="e.g., TODA-BAGUMBONG">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Area Name</label>
              <input name="area_name" required maxlength="128" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${esc(name)}" placeholder="e.g., Bagumbong TODA Zone">
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Barangay (optional)</label>
              <input name="barangay" maxlength="128" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${esc(barangay)}" placeholder="e.g., Brgy 176">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Authorized Units</label>
              <input name="authorized_units" type="number" min="0" max="9999" step="1" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${au || 0}">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
              <select name="status" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                ${['Active','Inactive'].map((t) => `<option value="${t}" ${t===status?'selected':''}>${t}</option>`).join('')}
              </select>
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Fare Min (₱)</label>
              <input name="fare_min" type="number" min="0" step="0.01" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${fareMin}">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Fare Max (₱)</label>
              <input name="fare_max" type="number" min="0" step="0.01" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${fareMax}">
            </div>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Coverage Notes (optional)</label>
            <textarea name="coverage_notes" rows="3" maxlength="800" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Describe boundaries, TODA notes, restrictions...">${esc(coverage)}</textarea>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Coverage Points (one per line)</label>
            <textarea name="points" rows="6" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Terminal / landmarks / pickup points...">${esc(points)}</textarea>
          </div>
          <div class="flex items-center justify-end gap-2">
            <button type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold" data-close="1">Cancel</button>
            <button id="btnAreaSave" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">${isEdit ? 'Save Changes' : 'Create Service Area'}</button>
          </div>
        </form>
      `;
    }

    async function saveArea(form) {
      const fd = new FormData(form);
      const res = await fetch(rootUrl + '/admin/api/module1/save_tricycle_service_area.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'save_failed');
      return data;
    }

    function bindFormHandlers() {
      const form = document.getElementById('formAreaSave');
      const btn = document.getElementById('btnAreaSave');
      const close = body ? body.querySelector('[data-close="1"]') : null;
      if (close) close.addEventListener('click', closeModal);
      if (form && btn) {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          if (!form.checkValidity()) { form.reportValidity(); return; }
          btn.disabled = true;
          btn.textContent = 'Saving...';
          try {
            await saveArea(form);
            showToast('Service area saved.');
            const params = new URLSearchParams(window.location.search || '');
            params.set('page', 'puv-database/tricycle-service-areas');
            window.location.search = params.toString();
          } catch (err) {
            showToast((err && err.message) ? err.message : 'Failed', 'error');
            btn.disabled = false;
            btn.textContent = 'Save';
          }
        });
      }
    }

    const btnAdd = document.getElementById('btnAddArea');
    if (btnAdd && canManage) {
      btnAdd.addEventListener('click', () => {
        openModal(renderForm({ status: 'Active' }), 'Add Service Area');
        bindFormHandlers();
      });
    }
    document.querySelectorAll('[data-area-edit="1"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const a = parsePayload(btn);
        openModal(renderForm(a), 'Edit • ' + (a.area_code || ''));
        bindFormHandlers();
      });
    });

    document.querySelectorAll('[data-area-del="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!canManage) return;
        const id = Number(btn.getAttribute('data-id') || 0);
        const code = (btn.getAttribute('data-code') || '').toString();
        if (!id) return;
        if (!confirm('Delete ' + code + '?')) return;
        btn.disabled = true;
        try {
          const fd = new FormData();
          fd.append('id', String(id));
          const res = await fetch(rootUrl + '/admin/api/module1/delete_tricycle_service_area.php', { method: 'POST', body: fd });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'delete_failed');
          showToast('Deleted.');
          setTimeout(() => window.location.reload(), 300);
        } catch (e) {
          showToast(e.message || 'Failed', 'error');
          btn.disabled = false;
        }
      });
    });
  })();
</script>

