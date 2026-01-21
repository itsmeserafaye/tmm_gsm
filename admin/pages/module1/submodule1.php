<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$statTotal = (int)($db->query("SELECT COUNT(*) AS c FROM operators")->fetch_assoc()['c'] ?? 0);
$statApproved = (int)($db->query("SELECT COUNT(*) AS c FROM operators WHERE status='Approved'")->fetch_assoc()['c'] ?? 0);
$statPending = (int)($db->query("SELECT COUNT(*) AS c FROM operators WHERE status='Pending'")->fetch_assoc()['c'] ?? 0);
$statInactive = (int)($db->query("SELECT COUNT(*) AS c FROM operators WHERE status='Inactive'")->fetch_assoc()['c'] ?? 0);

$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['operator_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$highlightId = (int)($_GET['highlight_operator_id'] ?? 0);

$sql = "SELECT id, operator_type, COALESCE(NULLIF(name,''), full_name) AS display_name, address, contact_no, email, status, created_at
        FROM operators";
$conds = [];
$params = [];
$types = '';
if ($q !== '') {
  $conds[] = "(name LIKE ? OR full_name LIKE ? OR contact_no LIKE ? OR email LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types .= 'ssss';
}
if ($type !== '' && $type !== 'Type') {
  $conds[] = "operator_type=?";
  $params[] = $type;
  $types .= 's';
}
if ($status !== '' && $status !== 'Status') {
  $conds[] = "status=?";
  $params[] = $status;
  $types .= 's';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY created_at DESC LIMIT 300";

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
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Operators</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Register and maintain operator records (Individual, Cooperative, Corporation) as the single source of truth.</p>
    </div>
    <div class="flex items-center gap-3">
      <?php if (has_permission('reports.export')): ?>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module1/export_operators_csv.php?<?php echo http_build_query(['q'=>$q,'operator_type'=>$type,'status'=>$status]); ?>"
          class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="download" class="w-4 h-4"></i>
          Export CSV
        </a>
      <?php endif; ?>
      <?php if (has_permission('module1.write')): ?>
        <button id="btnOpenAddOperator" type="button" class="inline-flex items-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
          <i data-lucide="user-plus" class="w-4 h-4"></i>
          Add Operator
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($statTotal); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Approved</div>
      <div class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?php echo number_format($statApproved); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending</div>
      <div class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400"><?php echo number_format($statPending); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Inactive</div>
      <div class="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400"><?php echo number_format($statInactive); ?></div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <form class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" method="GET">
      <input type="hidden" name="page" value="module1/submodule1">
      <div class="flex-1 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1 sm:max-w-sm group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder:text-slate-400" placeholder="Search name, phone, email...">
        </div>
        <div class="relative w-full sm:w-52">
          <select name="operator_type" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Types</option>
            <?php foreach (['Individual','Cooperative','Corporation'] as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $type === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
        <div class="relative w-full sm:w-44">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Pending','Approved','Inactive'] as $s): ?>
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
        <a href="?page=module1/submodule1" class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          Reset
        </a>
      </div>
    </form>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Operator</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Type</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden lg:table-cell">Address</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Contact</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Status</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Created</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
              <?php
                $rid = (int)($row['id'] ?? 0);
                $isHighlight = $highlightId > 0 && $highlightId === $rid;
                $st = (string)($row['status'] ?? '');
                $badge = match($st) {
                  'Approved' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  'Pending' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                  'Inactive' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                };
                $contactLine = trim((string)($row['contact_no'] ?? ''));
                $emailLine = trim((string)($row['email'] ?? ''));
                $displayContact = $contactLine;
                if ($displayContact !== '' && $emailLine !== '') $displayContact .= ' / ';
                $displayContact .= $emailLine;
                $displayContact = trim($displayContact) !== '' ? $displayContact : '-';
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group <?php echo $isHighlight ? 'bg-emerald-50/70 dark:bg-emerald-900/15 ring-1 ring-inset ring-emerald-200/70 dark:ring-emerald-900/30' : ''; ?>" <?php echo $isHighlight ? 'id="op-row-highlight"' : ''; ?>>
                <td class="py-4 px-6">
                  <div class="font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($row['display_name'] ?? '')); ?></div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ID: <?php echo (int)$rid; ?></div>
                </td>
                <td class="py-4 px-4 hidden md:table-cell">
                  <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-700/50 px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-300 ring-1 ring-inset ring-slate-500/10"><?php echo htmlspecialchars((string)($row['operator_type'] ?? '')); ?></span>
                </td>
                <td class="py-4 px-4 hidden lg:table-cell text-slate-600 dark:text-slate-300 font-medium">
                  <?php echo htmlspecialchars((string)($row['address'] ?? '')); ?>
                </td>
                <td class="py-4 px-4 text-slate-600 dark:text-slate-300 font-medium">
                  <?php echo htmlspecialchars($displayContact); ?>
                </td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
                </td>
                <td class="py-4 px-4 text-slate-500 font-medium text-xs hidden sm:table-cell">
                  <?php echo htmlspecialchars(date('M d, Y', strtotime((string)($row['created_at'] ?? 'now')))); ?>
                </td>
                <td class="py-4 px-4 text-right">
                  <div class="flex items-center justify-end gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                    <button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" data-op-view="1" data-operator-id="<?php echo (int)$rid; ?>" data-operator-name="<?php echo htmlspecialchars((string)($row['display_name'] ?? ''), ENT_QUOTES); ?>" title="View Operator">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" data-op-docs="1" data-operator-id="<?php echo (int)$rid; ?>" data-operator-name="<?php echo htmlspecialchars((string)($row['display_name'] ?? ''), ENT_QUOTES); ?>" title="View Documents">
                      <i data-lucide="folder-open" class="w-4 h-4"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="py-12 text-center text-slate-500 font-medium italic">No operators found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="modalOp" class="fixed inset-0 z-[200] hidden">
  <div id="modalOpBackdrop" class="absolute inset-0 bg-slate-900/50 opacity-0 transition-opacity"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div id="modalOpPanel" class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl transform scale-95 opacity-0 transition-all">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div class="font-black text-slate-900 dark:text-white" id="modalOpTitle">Add Operator</div>
        <button type="button" id="modalOpClose" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div id="modalOpBody" class="p-6"></div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const canWrite = <?php echo json_encode(has_permission('module1.vehicles.write')); ?>;

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

    const modal = document.getElementById('modalOp');
    const backdrop = document.getElementById('modalOpBackdrop');
    const panel = document.getElementById('modalOpPanel');
    const body = document.getElementById('modalOpBody');
    const title = document.getElementById('modalOpTitle');
    const closeBtn = document.getElementById('modalOpClose');

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

    const btnAdd = document.getElementById('btnOpenAddOperator');
    if (btnAdd && canWrite) {
      btnAdd.addEventListener('click', () => {
        openModal(`
          <form id="formAddOperator" class="space-y-5" novalidate>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Operator Type</label>
                <select name="operator_type" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option>Individual</option>
                  <option>Cooperative</option>
                  <option>Corporation</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Name</label>
                <input name="name" required minlength="3" maxlength="120" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Juan Dela Cruz / ABC Cooperative / XYZ Transport Corp">
              </div>
            </div>

            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Address</label>
              <input name="address" maxlength="180" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Brgy. 123, City, Province">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contact No</label>
                <input name="contact_no" type="tel" inputmode="tel" minlength="7" maxlength="20" pattern="^[0-9+()\\-\\s]{7,20}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 09XXXXXXXXX or +63 9XXXXXXXXX">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Email</label>
                <input name="email" type="email" maxlength="120" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., name@email.com">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
                <select name="status" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option>Approved</option>
                  <option>Pending</option>
                  <option>Inactive</option>
                </select>
              </div>
              <div class="flex items-end">
                <div class="text-xs text-slate-500 dark:text-slate-400">Upload docs after saving (optional): ID, CDA, SEC, Others.</div>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">ID</label>
                <input name="id_doc" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">CDA</label>
                <input name="cda_doc" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">SEC</label>
                <input name="sec_doc" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Others</label>
                <input name="others_doc" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
              <button type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold" data-op-cancel="1">Cancel</button>
              <button id="btnSaveOperator" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
            </div>
          </form>
        `, 'Add Operator');

        const cancel = body.querySelector('[data-op-cancel="1"]');
        if (cancel) cancel.addEventListener('click', closeModal);

        const form = document.getElementById('formAddOperator');
        const btn = document.getElementById('btnSaveOperator');
        if (!form || !btn) return;

        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          if (!form.checkValidity()) { form.reportValidity(); return; }

          const orig = btn.textContent;
          btn.disabled = true;
          btn.textContent = 'Saving...';

          try {
            const fd = new FormData(form);
            const saveFd = new FormData();
            ['operator_type','name','address','contact_no','email','status'].forEach((k) => saveFd.append(k, fd.get(k) || ''));

            const res = await fetch(rootUrl + '/admin/api/module1/save_operator.php', { method: 'POST', body: saveFd });
            const data = await res.json();
            if (!data || !data.ok || !data.operator_id) throw new Error((data && data.error) ? data.error : 'save_failed');

            const operatorId = Number(data.operator_id);
            const hasDocs = (fd.get('id_doc') && fd.get('id_doc').name) || (fd.get('cda_doc') && fd.get('cda_doc').name) || (fd.get('sec_doc') && fd.get('sec_doc').name) || (fd.get('others_doc') && fd.get('others_doc').name);

            if (hasDocs) {
              const docsFd = new FormData();
              docsFd.append('operator_id', String(operatorId));
              ['id_doc','cda_doc','sec_doc','others_doc'].forEach((k) => {
                const f = fd.get(k);
                if (f && f.name) docsFd.append(k, f);
              });
              const res2 = await fetch(rootUrl + '/admin/api/module1/upload_operator_docs.php', { method: 'POST', body: docsFd });
              const data2 = await res2.json();
              if (!data2 || !data2.ok) throw new Error((data2 && data2.error) ? data2.error : 'upload_failed');
            }

            showToast('Operator saved successfully.');
            const params = new URLSearchParams(window.location.search || '');
            params.set('page', 'module1/submodule1');
            params.set('highlight_operator_id', String(operatorId));
            window.location.search = params.toString();
          } catch (err) {
            showToast(err.message || 'Failed', 'error');
            btn.disabled = false;
            btn.textContent = orig;
          }
        });
      });
    }

    document.querySelectorAll('[data-op-docs="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-operator-id');
        const name = btn.getAttribute('data-operator-name') || 'Operator';
        openModal(`<div class="text-sm text-slate-500 dark:text-slate-400">Loading...</div>`, 'Documents • ' + name);
        try {
          const res = await fetch(rootUrl + '/admin/api/module1/list_operator_documents.php?operator_id=' + encodeURIComponent(id));
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
          const rows = Array.isArray(data.data) ? data.data : [];
          if (!rows.length) {
            body.innerHTML = `<div class="text-sm text-slate-500 dark:text-slate-400 italic">No documents uploaded.</div>`;
            if (window.lucide) window.lucide.createIcons();
            return;
          }
          body.innerHTML = `
            <div class="space-y-3">
              ${rows.map((d) => {
                const href = rootUrl + '/admin/uploads/' + encodeURIComponent(d.file_path || '');
                const dt = d.uploaded_at ? new Date(d.uploaded_at) : null;
                const date = dt && !isNaN(dt.getTime()) ? dt.toLocaleString() : '';
                return `
                  <a href="${href}" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-all">
                    <div class="flex items-center gap-3">
                      <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500">
                        <i data-lucide="file" class="w-4 h-4"></i>
                      </div>
                      <div>
                        <div class="text-sm font-black text-slate-800 dark:text-white">${(d.doc_type || '').toString()}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">${date}</div>
                      </div>
                    </div>
                    <div class="text-slate-400 hover:text-blue-600"><i data-lucide="external-link" class="w-4 h-4"></i></div>
                  </a>
                `;
              }).join('')}
            </div>
          `;
          if (window.lucide) window.lucide.createIcons();
        } catch (err) {
          body.innerHTML = `<div class="text-sm text-rose-600">${(err && err.message) ? err.message : 'Failed to load documents'}</div>`;
        }
      });
    });

    document.querySelectorAll('[data-op-view="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-operator-id');
        const name = btn.getAttribute('data-operator-name') || 'Operator';
        openModal(`<div class="text-sm text-slate-500 dark:text-slate-400">Loading...</div>`, 'Operator • ' + name);
        try {
          const res = await fetch(rootUrl + '/admin/api/module1/operator_view.php?operator_id=' + encodeURIComponent(id));
          const html = await res.text();
          body.innerHTML = html || `<div class="text-sm text-slate-500 dark:text-slate-400">No details.</div>`;
          if (window.lucide) window.lucide.createIcons();
        } catch (err) {
          body.innerHTML = `<div class="text-sm text-rose-600">${(err && err.message) ? err.message : 'Failed to load operator details'}</div>`;
        }
      });
    });

    const highlight = document.getElementById('op-row-highlight');
    if (highlight) {
      setTimeout(() => { highlight.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 300);
    }
  })();
</script>
