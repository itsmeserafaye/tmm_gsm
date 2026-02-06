<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.read','module2.apply','module2.endorse','module2.approve','module2.history']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$hasFranchises = (bool)($db->query("SHOW TABLES LIKE 'franchises'")?->fetch_row());
if ($hasFranchises) {
  @$db->query("UPDATE franchises SET status='Expired' WHERE status='Active' AND expiry_date IS NOT NULL AND expiry_date < CURDATE()");
  @$db->query("UPDATE franchise_applications fa
               JOIN franchises f ON f.application_id=fa.application_id
               SET fa.status='Expired'
               WHERE f.status='Expired'
                 AND fa.status IN ('PA Issued','CPC Issued','LTFRB-Approved','Approved')");
}

$statTotal = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications")->fetch_assoc()['c'] ?? 0);
$statSubmitted = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Submitted'")->fetch_assoc()['c'] ?? 0);
$statEndorsed = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status IN ('Endorsed','LGU-Endorsed')")->fetch_assoc()['c'] ?? 0);
$statApproved = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status IN ('Approved','LTFRB-Approved','PA Issued','CPC Issued')")->fetch_assoc()['c'] ?? 0);
$statExpired = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Expired'")->fetch_assoc()['c'] ?? 0);
$statRevoked = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Revoked'")->fetch_assoc()['c'] ?? 0);

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$highlightAppId = (int)($_GET['highlight_application_id'] ?? 0);

$sql = "SELECT fa.application_id, fa.franchise_ref_number, fa.operator_id,
               COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
               fa.route_id,
               r.route_id AS route_code,
               r.origin, r.destination,
               fa.vehicle_count, fa.representative_name,
               fa.status, fa.submitted_at, fa.endorsed_at, fa.approved_at
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id=fa.operator_id
        LEFT JOIN routes r ON r.id=fa.route_id";
$conds = [];
$params = [];
$types = '';
if ($q !== '') {
  $conds[] = "(fa.franchise_ref_number LIKE ? OR COALESCE(NULLIF(o.name,''), o.full_name) LIKE ? OR r.route_id LIKE ? OR r.origin LIKE ? OR r.destination LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types .= 'sssss';
}
if ($status !== '' && $status !== 'Status') {
  if ($status === 'LGU-Endorsed' || $status === 'Endorsed') {
    $conds[] = "fa.status IN ('LGU-Endorsed','Endorsed')";
  } elseif ($status === 'LTFRB-Approved' || $status === 'Approved') {
    $conds[] = "fa.status IN ('LTFRB-Approved','Approved')";
  } else {
    $conds[] = "fa.status=?";
    $params[] = $status;
    $types .= 's';
  }
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY fa.submitted_at DESC LIMIT 300";

if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Franchise Applications</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Applications are operator-based and move from Submitted → LGU-Endorsed → LTFRB PA/CPC Issued (validity starts at LTFRB issue date).</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module2/submodule2" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="file-plus" class="w-4 h-4"></i>
        Submit Application
      </a>
      <a href="?page=module2/submodule3" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="badge-check" class="w-4 h-4"></i>
        Endorse / Approve
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total</div>
        <i data-lucide="layers" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statTotal; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Submitted</div>
        <i data-lucide="send" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statSubmitted; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">LGU-Endorsed</div>
        <i data-lucide="check-circle-2" class="w-4 h-4 text-violet-600 dark:text-violet-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statEndorsed; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">LTFRB Issued</div>
        <i data-lucide="badge-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statApproved; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Expired</div>
        <i data-lucide="clock" class="w-4 h-4 text-slate-600 dark:text-slate-300"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statExpired; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Revoked</div>
        <i data-lucide="ban" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statRevoked; ?></div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <?php if (has_permission('reports.export')): ?>
      <?php tmm_render_export_toolbar([
        [
          'href' => $rootUrl . '/admin/api/module2/export_applications_csv.php?' . http_build_query(['q' => $q, 'status' => $status]),
          'label' => 'CSV',
          'icon' => 'download'
        ],
        [
          'href' => $rootUrl . '/admin/api/module2/export_applications_csv.php?' . http_build_query(['q' => $q, 'status' => $status, 'format' => 'excel']),
          'label' => 'Excel',
          'icon' => 'file-spreadsheet'
        ]
      ]); ?>
    <?php endif; ?>
    <form class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between" method="GET">
      <input type="hidden" name="page" value="module2/submodule1">
      <div class="flex-1 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1 sm:max-w-sm group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder:text-slate-400" placeholder="Search operator or route...">
        </div>
        <div class="relative w-full sm:w-52">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Submitted','LGU-Endorsed','PA Issued','CPC Issued','Rejected','Expired','Revoked'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button class="inline-flex items-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="filter" class="w-4 h-4"></i>
          Apply
        </button>
        <a href="?page=module2/submodule1" class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          Reset
        </a>
      </div>
    </form>
    <div class="mt-4 flex flex-wrap items-center gap-2">
      <?php foreach (['Submitted','LGU-Endorsed','PA Issued','CPC Issued','Rejected','Expired','Revoked'] as $chip): ?>
        <a href="?<?php echo http_build_query(['page'=>'module2/submodule1','q'=>$q,'status'=>$chip]); ?>"
          class="<?php echo $status === $chip ? 'bg-slate-900 text-white border-slate-900 dark:bg-slate-100 dark:text-slate-900 dark:border-slate-100' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40'; ?> inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold border transition-colors">
          <?php echo htmlspecialchars($chip); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Application</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Operator</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Route</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Units</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Status</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Submitted</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
              <?php
                $appId = (int)($row['application_id'] ?? 0);
                $isHighlight = $highlightAppId > 0 && $highlightAppId === $appId;
                $st = (string)($row['status'] ?? '');
                $badge = match($st) {
                  'Approved', 'LTFRB-Approved' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  'Endorsed', 'LGU-Endorsed' => 'bg-violet-100 text-violet-700 ring-violet-600/20 dark:bg-violet-900/30 dark:text-violet-400 dark:ring-violet-500/20',
                  'PA Issued', 'CPC Issued' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  'Submitted' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                  'Rejected' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  'Expired' => 'bg-slate-200 text-slate-700 ring-slate-600/20 dark:bg-slate-700 dark:text-slate-200 dark:ring-slate-500/20',
                  'Revoked' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                };
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group <?php echo $isHighlight ? 'bg-emerald-50/70 dark:bg-emerald-900/15 ring-1 ring-inset ring-emerald-200/70 dark:ring-emerald-900/30' : ''; ?>" <?php echo $isHighlight ? 'id="app-row-highlight"' : ''; ?>>
                <td class="py-4 px-6">
                  <div class="font-black text-slate-900 dark:text-white">APP-<?php echo $appId; ?></div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars((string)($row['franchise_ref_number'] ?? '')); ?></div>
                </td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">
                  <?php echo htmlspecialchars((string)($row['operator_name'] ?? '')); ?>
                  <?php if (!empty($row['representative_name'] ?? '')): ?>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Rep: <?php echo htmlspecialchars((string)$row['representative_name']); ?></div>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-medium">
                  <?php
                    $rc = trim((string)($row['route_code'] ?? ''));
                    $ro = trim((string)($row['origin'] ?? ''));
                    $rd = trim((string)($row['destination'] ?? ''));
                    $label = $rc !== '' ? $rc : '-';
                    if ($ro !== '' || $rd !== '') $label .= ' • ' . trim($ro . ' → ' . $rd);
                    echo htmlspecialchars($label);
                  ?>
                </td>
                <td class="py-4 px-4 hidden sm:table-cell font-black text-slate-700 dark:text-slate-200"><?php echo (int)($row['vehicle_count'] ?? 0); ?></td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
                </td>
                <td class="py-4 px-4 hidden sm:table-cell text-xs text-slate-500 dark:text-slate-400 font-medium">
                  <?php echo htmlspecialchars(date('M d, Y', strtotime((string)($row['submitted_at'] ?? 'now')))); ?>
                </td>
                <td class="py-4 px-4 text-right">
                  <div class="flex items-center justify-end gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                    <button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" data-app-view="1" data-app-id="<?php echo (int)$appId; ?>" title="View Details">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <?php if (has_permission('module2.franchises.manage') && $st === 'Submitted'): ?>
                      <button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-violet-600 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-all" data-app-endorse="1" data-app-id="<?php echo (int)$appId; ?>" title="Endorse">
                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                      </button>
                    <?php endif; ?>
                    <?php if (has_permission('module2.franchises.manage') && ($st === 'Endorsed' || $st === 'LGU-Endorsed' || $st === 'Approved' || $st === 'LTFRB-Approved')): ?>
                      <button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all" data-app-approve="1" data-app-id="<?php echo (int)$appId; ?>" title="LTFRB Approval Entry">
                        <i data-lucide="badge-check" class="w-4 h-4"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="py-12 text-center text-slate-500 font-medium italic">No applications found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="modalApp" class="fixed inset-0 z-[200] hidden">
  <div id="modalAppBackdrop" class="absolute inset-0 bg-slate-900/50 opacity-0 transition-opacity"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div id="modalAppPanel" class="w-full max-w-4xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl transform scale-95 opacity-0 transition-all">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div class="font-black text-slate-900 dark:text-white" id="modalAppTitle">Application</div>
        <button type="button" id="modalAppClose" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div id="modalAppBody" class="p-6 max-h-[80vh] overflow-y-auto"></div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const canManage = <?php echo json_encode(has_permission('module2.franchises.manage')); ?>;

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

    const modal = document.getElementById('modalApp');
    const backdrop = document.getElementById('modalAppBackdrop');
    const panel = document.getElementById('modalAppPanel');
    const body = document.getElementById('modalAppBody');
    const title = document.getElementById('modalAppTitle');
    const closeBtn = document.getElementById('modalAppClose');

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

    function formatDate(v) {
      if (!v) return '-';
      const d = new Date(v);
      if (isNaN(d.getTime())) return String(v);
      return d.toLocaleString();
    }

    function prettyMissing(list) {
      if (!Array.isArray(list) || !list.length) return '';
      return list.filter(Boolean).join(', ');
    }

    async function loadApp(appId) {
      const res = await fetch(rootUrl + '/admin/api/module2/get_application.php?application_id=' + encodeURIComponent(appId));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return data.data;
    }

    async function loadOperatorDocs(operatorId) {
      const res = await fetch(rootUrl + '/admin/api/module2/list_operator_verified_docs.php?operator_id=' + encodeURIComponent(operatorId));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return Array.isArray(data.data) ? data.data : [];
    }

    let routesCache = null;
    async function loadRoutes() {
      if (routesCache) return routesCache;
      const res = await fetch(rootUrl + '/admin/api/module2/routes_list.php');
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'routes_load_failed');
      routesCache = Array.isArray(data.data) ? data.data : [];
      return routesCache;
    }
    function routeOptionLabel(r) {
      const code = (r.route_code || r.route_id || '-').toString();
      const name = (r.route_name || '').toString();
      const od = ((r.origin || '') + (r.destination ? (' → ' + r.destination) : '')).trim();
      return [code, name && name !== code ? name : '', od].filter(Boolean).join(' • ');
    }

    function operatorDocLabel(d) {
      const remarks = (d && d.remarks) ? String(d.remarks) : '';
      const labelPart = remarks.split('|')[0].trim();
      if (labelPart) return labelPart;
      const dt = (d && (d.doc_type || d.type)) ? String(d.doc_type || d.type) : '';
      const map = {
        GovID: 'Valid Government ID',
        CDA: 'CDA Document',
        SEC: 'SEC Document',
        BarangayCert: 'Proof of Address',
        Others: 'Supporting Document',
      };
      return map[dt] || dt || 'Document';
    }

    async function loadApplicationDocs(appId) {
      const res = await fetch(rootUrl + '/admin/api/module2/list_application_docs.php?application_id=' + encodeURIComponent(appId));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return Array.isArray(data.data) ? data.data : [];
    }

    document.querySelectorAll('[data-app-view="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const appId = btn.getAttribute('data-app-id');
        openModal('<div class="text-sm text-slate-500 dark:text-slate-400">Loading...</div>', 'Application');
        try {
          const a = await loadApp(appId);
          const docs = a && a.operator_id ? await loadOperatorDocs(a.operator_id) : [];
          const appDocs = a && a.application_id ? await loadApplicationDocs(a.application_id) : [];
          const routeLabel = (a.route_code || '-') + ((a.origin || a.destination) ? (' • ' + (a.origin || '') + ' → ' + (a.destination || '')) : '');
          body.innerHTML = `
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <div class="lg:col-span-2 space-y-4">
                <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Application</div>
                  <div class="mt-2 text-lg font-black text-slate-900 dark:text-white">APP-${a.application_id}</div>
                  <div class="mt-1 text-sm text-slate-600 dark:text-slate-300">${(a.franchise_ref_number || '').toString()}</div>
                </div>
                <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                  <div class="flex items-center justify-between gap-3 mb-3">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Details</div>
                    <button type="button" id="btnEditApp" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" title="Edit">
                      <i data-lucide="pencil" class="w-4 h-4"></i>
                    </button>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="appDetailsView">
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Operator</div>
                      <div class="mt-1 font-bold text-slate-900 dark:text-white">${(a.operator_name || '').toString()}</div>
                      <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Type: ${(a.operator_type || '-').toString()} • Status: ${(a.operator_status || '-').toString()}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Route</div>
                      <div class="mt-1 font-bold text-slate-900 dark:text-white">${routeLabel}</div>
                      <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Route status: ${(a.route_status || '-').toString()}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Vehicle Count</div>
                      <div class="mt-1 font-bold text-slate-900 dark:text-white">${Number(a.vehicle_count || 0)}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Status</div>
                      <div class="mt-1 font-bold text-slate-900 dark:text-white">${(a.status || '-').toString()}</div>
                    </div>
                    <div class="sm:col-span-2">
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Representative Name</div>
                      <div class="mt-1 font-bold text-slate-900 dark:text-white">${(a.representative_name || '-').toString()}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Submitted</div>
                      <div class="mt-1 text-sm text-slate-700 dark:text-slate-200">${formatDate(a.submitted_at)}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">LGU-Endorsed</div>
                      <div class="mt-1 text-sm text-slate-700 dark:text-slate-200">${formatDate(a.endorsed_at)}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">LTFRB-Approved</div>
                      <div class="mt-1 text-sm text-slate-700 dark:text-slate-200">${formatDate(a.approved_at)}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">LTFRB Ref</div>
                      <div class="mt-1 text-sm text-slate-700 dark:text-slate-200">${(a.ltfrb_ref_no || '-').toString()}</div>
                    </div>
                    <div class="sm:col-span-2">
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Remarks</div>
                      <div class="mt-1 text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap">${(a.remarks || '').toString() || '-'}</div>
                    </div>
                  </div>
                  <form id="appDetailsEdit" class="hidden space-y-4" novalidate>
                    <input type="hidden" name="application_id" value="${Number(a.application_id || 0)}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div class="sm:col-span-2">
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route</label>
                        <select name="route_id" id="editRouteId" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold"></select>
                      </div>
                      <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle Count</label>
                        <input name="vehicle_count" type="number" min="1" max="5000" value="${Number(a.vehicle_count || 0)}" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                      </div>
                      <div class="sm:col-span-2">
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Representative Name</label>
                        <input name="representative_name" maxlength="120" value="${(a.representative_name || '').toString()}" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                      </div>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                      <button type="button" id="btnCancelEditApp" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">Cancel</button>
                      <button id="btnSaveEditApp" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
              <div class="space-y-4">
                <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Supporting Documents (Application)</div>
                  <div class="mt-3 space-y-2">
                    ${appDocs.length ? appDocs.map((d) => {
                      const href = rootUrl + '/admin/uploads/' + encodeURIComponent((d.file_path || '').toString());
                      return `<a href="${href}" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-all">
                        <div>
                          <div class="text-sm font-black text-slate-800 dark:text-white">${(d.type || '').toString()}</div>
                          <div class="text-xs text-slate-500 dark:text-slate-400">${formatDate(d.uploaded_at)}</div>
                        </div>
                        <div class="text-slate-400 hover:text-blue-600"><i data-lucide="external-link" class="w-4 h-4"></i></div>
                      </a>`;
                    }).join('') : `<div class="text-sm text-slate-500 dark:text-slate-400 italic">No application documents uploaded.</div>`}
                  </div>
                </div>
                <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Verified Operator Documents</div>
                  <div class="mt-3 space-y-2">
                    ${docs.length ? docs.map((d) => {
                      const href = rootUrl + '/admin/uploads/' + encodeURIComponent((d.file_path || '').toString());
                      const vdt = d.verified_at ? new Date(d.verified_at) : null;
                      const vdate = vdt && !isNaN(vdt.getTime()) ? vdt.toLocaleString() : '';
                      const vby = (d.verified_by_name || '').toString().trim();
                      return `<a href="${href}" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-all">
                        <div>
                          <div class="text-sm font-black text-slate-800 dark:text-white">${operatorDocLabel(d)}</div>
                          <div class="text-xs text-slate-500 dark:text-slate-400">${formatDate(d.uploaded_at)}</div>
                          ${(vby || vdate) ? `<div class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">Verified by ${vby || '-'} • ${vdate || '-'}</div>` : ``}
                        </div>
                        <div class="text-slate-400 hover:text-blue-600"><i data-lucide="external-link" class="w-4 h-4"></i></div>
                      </a>`;
                    }).join('') : `<div class="text-sm text-slate-500 dark:text-slate-400 italic">No verified operator documents found.</div>`}
                  </div>
                </div>
              </div>
            </div>
          `;
          if (window.lucide) window.lucide.createIcons();

          const btnEdit = body.querySelector('#btnEditApp');
          const viewBox = body.querySelector('#appDetailsView');
          const editForm = body.querySelector('#appDetailsEdit');
          const routeSel = body.querySelector('#editRouteId');
          const btnCancel = body.querySelector('#btnCancelEditApp');
          const btnSave = body.querySelector('#btnSaveEditApp');

          async function openEdit() {
            if (!editForm || !viewBox) return;
            editForm.classList.remove('hidden');
            viewBox.classList.add('hidden');
            if (routeSel) {
              routeSel.innerHTML = `<option value="">Loading...</option>`;
              const routes = await loadRoutes();
              routeSel.innerHTML = routes.map((r) => {
                const rid = Number(r.id || 0);
                const selected = rid === Number(a.route_id || 0) ? 'selected' : '';
                return `<option value="${rid}" ${selected}>${routeOptionLabel(r)}</option>`;
              }).join('');
            }
          }
          function closeEdit() {
            if (!editForm || !viewBox) return;
            editForm.classList.add('hidden');
            viewBox.classList.remove('hidden');
          }

          if (btnEdit) btnEdit.addEventListener('click', () => { openEdit().catch((e) => showToast(e.message || 'Failed', 'error')); });
          if (btnCancel) btnCancel.addEventListener('click', closeEdit);
          if (editForm) {
            editForm.addEventListener('submit', async (e) => {
              e.preventDefault();
              if (!btnSave) return;
              btnSave.disabled = true;
              const oldText = btnSave.textContent;
              btnSave.textContent = 'Saving...';
              try {
                const fd = new FormData(editForm);
                const res = await fetch(rootUrl + '/admin/api/module2/update_application.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data || !data.ok) throw new Error((data && (data.message || data.error)) ? (data.message || data.error) : 'update_failed');
                showToast('Application updated.');
                window.location.reload();
              } catch (err) {
                showToast(err.message || 'Failed', 'error');
              } finally {
                btnSave.disabled = false;
                btnSave.textContent = oldText;
              }
            });
          }
        } catch (err) {
          body.innerHTML = `<div class="text-sm text-rose-600">${(err && err.message) ? err.message : 'Failed to load.'}</div>`;
        }
      });
    });

    document.querySelectorAll('[data-app-endorse="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!canManage) return;
        const appId = btn.getAttribute('data-app-id');
        if (!appId) return;
        const params = new URLSearchParams(window.location.search || '');
        params.set('page', 'module2/submodule3');
        params.set('application_id', String(appId));
        window.location.search = params.toString();
      });
    });

    document.querySelectorAll('[data-app-approve="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!canManage) return;
        const appId = btn.getAttribute('data-app-id');
        if (!appId) return;
        const params = new URLSearchParams(window.location.search || '');
        params.set('page', 'module2/submodule3');
        params.set('application_id', String(appId));
        window.location.search = params.toString();
      });
    });

    const highlight = document.getElementById('app-row-highlight');
    if (highlight) setTimeout(() => { highlight.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 300);
  })();
</script>
