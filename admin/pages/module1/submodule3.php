<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module1.write');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['operator_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$sql = "SELECT
  o.id,
  o.operator_type,
  COALESCE(NULLIF(o.registered_name,''), NULLIF(o.name,''), o.full_name) AS display_name,
  o.workflow_status,
  o.created_at,
  (
    SELECT d2.remarks
    FROM operator_documents d2
    WHERE d2.operator_id=o.id AND d2.doc_type='GovID'
    ORDER BY COALESCE(d2.uploaded_at, d2.verified_at) DESC, d2.doc_id DESC
    LIMIT 1
  ) AS govid_remarks,
  MAX(CASE WHEN d.doc_type='GovID' THEN d.is_verified ELSE 0 END) AS govid_verified,
  MAX(CASE WHEN d.doc_type='CDA' THEN d.is_verified ELSE 0 END) AS cda_verified,
  MAX(CASE WHEN d.doc_type='SEC' THEN d.is_verified ELSE 0 END) AS sec_verified,
  MAX(CASE WHEN d.doc_type='BarangayCert' THEN d.is_verified ELSE 0 END) AS brgy_verified,
  MAX(CASE WHEN d.doc_type='Others' THEN d.is_verified ELSE 0 END) AS others_verified,
  GROUP_CONCAT(
    DISTINCT
    CASE
      WHEN TRIM(SUBSTRING_INDEX(COALESCE(d.remarks,''), '|', 1)) <> '' THEN TRIM(SUBSTRING_INDEX(COALESCE(d.remarks,''), '|', 1))
      WHEN d.doc_type='GovID' THEN 'Valid Government ID'
      WHEN d.doc_type='BarangayCert' THEN 'Proof of Address'
      WHEN d.doc_type='CDA' THEN 'CDA'
      WHEN d.doc_type='SEC' THEN 'SEC'
      WHEN d.doc_type='Others' THEN 'Others'
      ELSE d.doc_type
    END
    ORDER BY d.doc_type
    SEPARATOR ', '
  ) AS uploaded_labels,
  SUM(CASE WHEN d.doc_id IS NULL THEN 0 ELSE 1 END) AS doc_count
FROM operators o
LEFT JOIN (
  SELECT od.*
  FROM operator_documents od
  JOIN (
    SELECT operator_id,
           doc_type,
           TRIM(SUBSTRING_INDEX(COALESCE(remarks,''), '|', 1)) AS head_label,
           MAX(doc_id) AS doc_id
    FROM operator_documents
    GROUP BY operator_id, doc_type, head_label
  ) x ON x.doc_id=od.doc_id
) d ON d.operator_id=o.id
WHERE 1=1";

$params = [];
$types = '';

if ($q !== '') {
  $sql .= " AND (o.registered_name LIKE ? OR o.name LIKE ? OR o.full_name LIKE ?)";
  $like = "%$q%";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'sss';
}
if ($type !== '' && $type !== 'Type') {
  $sql .= " AND o.operator_type=?";
  $params[] = $type;
  $types .= 's';
}
if ($status !== '' && $status !== 'Status') {
  if ($status === 'Incomplete') {
    $sql .= " AND o.workflow_status IN ('Incomplete','Pending Validation')";
  } elseif ($status === 'Returned') {
    $sql .= " AND o.workflow_status IN ('Returned','Rejected')";
  } else {
    $sql .= " AND o.workflow_status=?";
    $params[] = $status;
    $types .= 's';
  }
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT 300";

if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

function tmm_required_doc_list(string $operatorType): array {
  if ($operatorType === 'Cooperative') return ['CDA Registration', 'CDA Good Standing', 'Board Resolution'];
  if ($operatorType === 'Corporation') return ['SEC Registration', 'Articles/By-laws', 'Board Resolution'];
  return ['Government ID'];
}

function tmm_extract_gov_id_kind(?string $remarks): string {
  $remarks = trim((string)$remarks);
  if ($remarks === '') return '';
  $parts = explode('|', $remarks);
  if (count($parts) < 2) return '';
  $kind = trim((string)($parts[1] ?? ''));
  $kind = preg_replace('/\s+/', ' ', $kind);
  return trim((string)$kind);
}
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Operator Document Validation</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Review uploaded operator documents and mark each as Verified or Pending. Only Verified operators can apply for franchise.</p>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <?php if (has_permission('reports.export')): ?>
      <?php tmm_render_export_toolbar([
        [
          'href' => $rootUrl . '/admin/api/module1/export_operator_validation.php?' . http_build_query(['q' => $q, 'operator_type' => $type, 'status' => $status, 'format' => 'csv']),
          'label' => 'CSV',
          'icon' => 'download'
        ],
        [
          'href' => $rootUrl . '/admin/api/module1/export_operator_validation.php?' . http_build_query(['q' => $q, 'operator_type' => $type, 'status' => $status, 'format' => 'excel']),
          'label' => 'Excel',
          'icon' => 'file-spreadsheet'
        ]
      ]); ?>
    <?php endif; ?>
    <form class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" method="GET">
      <input type="hidden" name="page" value="module1/submodule3">
      <div class="flex-1 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1 sm:max-w-sm group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all placeholder:text-slate-400" placeholder="Search operator name...">
        </div>
        <div class="relative w-full sm:w-52">
          <select name="operator_type" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Types</option>
            <?php foreach (['Individual','Cooperative','Corporation'] as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $type === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
          </select>
          <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
          </span>
        </div>
        <div class="relative w-full sm:w-56">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Draft','Incomplete','Returned','Active','Inactive'] as $s): ?>
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
        <a href="?page=module1/submodule3" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          Reset
        </a>
      </div>
    </form>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
        <thead class="bg-slate-50 dark:bg-slate-900/40">
          <tr class="text-left text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6">Operator</th>
            <th class="py-4 px-4 hidden md:table-cell">Type</th>
            <th class="py-4 px-4">Uploaded</th>
            <th class="py-4 px-4">Docs</th>
            <th class="py-4 px-4">Status</th>
            <th class="py-4 px-4 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
              <?php
                $rid = (int)($row['id'] ?? 0);
                $opType = (string)($row['operator_type'] ?? 'Individual');
                $stRaw = (string)($row['workflow_status'] ?? 'Draft');
                $st = $stRaw === 'Rejected' ? 'Returned' : $stRaw;
                $badge = match($st) {
                  'Active' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  'Pending Validation' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                  'Draft' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400',
                  'Incomplete' => 'bg-violet-100 text-violet-700 ring-violet-600/20 dark:bg-violet-900/30 dark:text-violet-400 dark:ring-violet-500/20',
                  'Returned' => 'bg-orange-100 text-orange-700 ring-orange-600/20 dark:bg-orange-900/30 dark:text-orange-400 dark:ring-orange-500/20',
                  'Inactive' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                };
                $govIdKind = $opType === 'Individual' ? tmm_extract_gov_id_kind((string)($row['govid_remarks'] ?? '')) : '';
                $uploadedText = trim((string)($row['uploaded_labels'] ?? ''));
                $uploadedText = $uploadedText !== '' ? $uploadedText : '-';
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-6">
                  <div class="font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($row['display_name'] ?? '')); ?></div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ID: <?php echo (int)$rid; ?></div>
                </td>
                <td class="py-4 px-4 hidden md:table-cell">
                  <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-700/50 px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-300 ring-1 ring-inset ring-slate-500/10"><?php echo htmlspecialchars($opType); ?></span>
                </td>
                <td class="py-4 px-4 text-slate-600 dark:text-slate-300 font-semibold text-sm"><?php echo htmlspecialchars($uploadedText); ?></td>
                <td class="py-4 px-4 text-slate-600 dark:text-slate-300 font-semibold text-sm"><?php echo (int)($row['doc_count'] ?? 0); ?></td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
                </td>
                <td class="py-4 px-4 text-right">
                  <div class="inline-flex items-center gap-2">
                    <button type="button"
                      class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold transition-colors"
                      data-op-review="1"
                      data-operator-id="<?php echo (int)$rid; ?>"
                      data-operator-name="<?php echo htmlspecialchars((string)($row['display_name'] ?? ''), ENT_QUOTES); ?>">
                      <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                      Review
                    </button>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="py-10 px-6 text-center text-sm text-slate-500 dark:text-slate-400">No operators found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="modal" class="fixed inset-0 z-[120] hidden items-center justify-center p-4">
  <div class="absolute inset-0 bg-black/40" data-modal-backdrop="1"></div>
  <div class="relative w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
      <div class="min-w-0">
        <div id="modalTitle" class="text-sm font-black text-slate-900 dark:text-white truncate">Review</div>
      </div>
      <button type="button" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500" data-modal-close="1">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div id="modalBody" class="p-5 max-h-[70vh] overflow-auto"></div>
  </div>
</div>

<script>
  (function () {
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const modal = document.getElementById('modal');
    const body = document.getElementById('modalBody');
    const title = document.getElementById('modalTitle');
    const backdrop = modal ? modal.querySelector('[data-modal-backdrop="1"]') : null;
    const closeBtn = modal ? modal.querySelector('[data-modal-close="1"]') : null;

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

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] || c));

    function openModal(html, modalTitle) {
      if (!modal || !body || !title) return;
      title.textContent = modalTitle || 'Review';
      body.innerHTML = html || '';
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.classList.add('overflow-hidden');
      if (window.lucide) window.lucide.createIcons();
    }

    function closeModal() {
      if (!modal) return;
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.classList.remove('overflow-hidden');
      if (body) body.innerHTML = '';
    }

    if (backdrop) backdrop.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal(); });

    function showModalPrompt(title, label, callback) {
      const existing = document.getElementById('custom-prompt-modal');
      if (existing) existing.remove();

      const html = `
        <div id="custom-prompt-modal" class="fixed inset-0 z-[140] flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/50 backdrop-blur-sm z-0" data-prompt-backdrop="1"></div>
          <div class="relative z-10 w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 pointer-events-auto" data-prompt-panel="1">
            <div class="p-6">
              <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">${title}</h3>
              <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">${label}</label>
                <textarea id="prompt-input" rows="3" class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all" placeholder="Enter details here..."></textarea>
              </div>
              <div class="flex items-center justify-end gap-3">
                <button type="button" id="prompt-cancel" class="px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">Cancel</button>
                <button type="button" id="prompt-confirm" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors">Confirm</button>
              </div>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', html);
      
      const modal = document.getElementById('custom-prompt-modal');
      const input = document.getElementById('prompt-input');
      const cancel = document.getElementById('prompt-cancel');
      const confirm = document.getElementById('prompt-confirm');
      const backdrop = modal ? modal.querySelector('[data-prompt-backdrop="1"]') : null;
      const panel = modal ? modal.querySelector('[data-prompt-panel="1"]') : null;
      
      requestAnimationFrame(() => { if (input) input.focus(); });
      
      const close = () => {
        if (!modal) return;
        modal.classList.add('opacity-0');
        modal.style.transition = 'opacity 150ms';
        setTimeout(() => modal.remove(), 160);
      };
      
      if (backdrop) backdrop.addEventListener('click', close);
      if (panel) panel.addEventListener('click', (e) => e.stopPropagation());
      if (cancel) cancel.onclick = close;
      
      if (confirm) confirm.onclick = () => {
        const val = (input ? input.value : '').trim();
        close();
        callback(val);
      };

      if (input) input.onkeydown = (e) => {
        if (e.key === 'Enter' && e.ctrlKey && confirm) confirm.click();
        if (e.key === 'Escape') close();
      };
    }

    async function loadOperatorDocs(operatorId) {
      const res = await fetch(rootUrl + '/admin/api/module1/list_operator_documents.php?operator_id=' + encodeURIComponent(operatorId));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return { operator: (data.operator || null), rows: Array.isArray(data.data) ? data.data : [] };
    }

    function renderDocs(operatorId, operatorName, payload) {
      const rows = (payload && payload.rows) ? payload.rows : [];
      const opType = (payload && payload.operator && payload.operator.operator_type) ? String(payload.operator.operator_type || 'Individual') : 'Individual';
      const listHtml = rows.length ? `
        <div class="space-y-3">
          ${rows.map((d) => {
            const href = rootUrl + '/admin/uploads/' + encodeURIComponent(d.file_path || '');
            const dt = d.uploaded_at ? new Date(d.uploaded_at) : null;
            const date = dt && !isNaN(dt.getTime()) ? dt.toLocaleString() : '';
            const rawSt = String(d.doc_status || '');
            const st = rawSt !== '' ? rawSt : (Number(d.is_verified || 0) === 1 ? 'Verified' : 'For Review');
            const isVerified = st === 'Verified';
            const isRejected = st === 'Rejected';
            const isExpired = st === 'Expired';
            const rawRemarks = String(d.remarks || '');
            const reasonSplit = rawRemarks.split('| Reason:');
            const noteSplit = rawRemarks.split('| Note:');
            const labelPart = (reasonSplit[0] || noteSplit[0] || '').trim();
            const reasonPart = reasonSplit.length > 1 ? reasonSplit.slice(1).join('| Reason:').trim() : '';
            const notePart = noteSplit.length > 1 ? noteSplit.slice(1).join('| Note:').trim() : '';
            const displayTitle = labelPart || String(d.doc_type || '') || 'Document';
            const badge = isVerified
              ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
              : (isRejected
                ? 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20'
                : (isExpired
                  ? 'bg-slate-200 text-slate-700 ring-slate-600/20 dark:bg-slate-700 dark:text-slate-200 dark:ring-slate-500/20'
                  : 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20'));
            const verifiedBy = (d.verified_by_name || '').toString().trim() || (d.verified_by ? ('User #' + String(d.verified_by)) : '');
            const vdt = d.verified_at ? new Date(d.verified_at) : null;
            const verifiedAt = vdt && !isNaN(vdt.getTime()) ? vdt.toLocaleString() : '';
            return `
              <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-3 min-w-0">
                  <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500">
                    <i data-lucide="file" class="w-4 h-4"></i>
                  </div>
                  <div class="min-w-0">
                    <div class="text-sm font-black text-slate-800 dark:text-white">${displayTitle}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 truncate">${date}</div>
                    ${isRejected && (reasonPart || rawRemarks) ? `<div class="text-xs font-semibold text-rose-600 mt-1">Reason: ${reasonPart || rawRemarks}</div>` : ``}
                    ${isVerified && (verifiedBy || verifiedAt) ? `<div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Verified by ${verifiedBy || '-'} • ${verifiedAt || '-'}</div>` : ``}
                    ${notePart ? `<div class="text-xs font-semibold text-slate-600 dark:text-slate-300 mt-1">Notes: ${notePart}</div>` : ``}
                  </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ${badge}">${st}</span>
                  <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" data-doc-set="1" data-doc-id="${String(d.doc_id || '')}" data-doc-status="${isVerified ? 'For Review' : 'Verified'}">${isVerified ? 'Mark For Review' : 'Verify'}</button>
                  <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition-colors" data-doc-reject="1" data-doc-id="${String(d.doc_id || '')}">Reject</button>
                  <a href="${href}" target="_blank" class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-blue-600 transition-colors" title="Open">
                    <i data-lucide="external-link" class="w-4 h-4"></i>
                  </a>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      ` : `<div class="text-sm text-slate-500 dark:text-slate-400 italic">No documents uploaded.</div>`;

      const docMatrix = {
        Individual: {
          required: [
            { name: 'gov_id', docType: 'GovID', label: 'Valid Government ID', hint: 'Driver’s License / UMID / PhilSys ID' },
            { name: 'declared_fleet', docType: 'Others', label: 'Declared Fleet (Planned / Owned Vehicles)', hint: 'Upload PDF/Excel/CSV (planned/owned vehicles)' },
          ],
          optional: [
            { name: 'proof_address', docType: 'BarangayCert', label: 'Proof of Address', hint: 'Barangay Clearance or Utility Bill' },
            { name: 'nbi_clearance', docType: 'Others', label: 'NBI Clearance', hint: '' },
            { name: 'authorization_letter', docType: 'Others', label: 'Authorization Letter', hint: 'If represented' },
          ],
        },
        Cooperative: {
          required: [
            { name: 'cda_registration', docType: 'CDA', label: 'CDA Registration Certificate', hint: '' },
            { name: 'cda_good_standing', docType: 'CDA', label: 'CDA Certificate of Good Standing', hint: '' },
            { name: 'board_resolution', docType: 'Others', label: 'Board Resolution', hint: 'Authorizing application + naming representative' },
            { name: 'declared_fleet', docType: 'Others', label: 'Declared Fleet (Planned / Owned Vehicles)', hint: 'Upload PDF/Excel/CSV (planned/owned vehicles)' },
          ],
          optional: [
            { name: 'members_list', docType: 'Others', label: 'List of Members', hint: '' },
            { name: 'coop_articles_bylaws', docType: 'Others', label: 'Articles of Cooperation / By-laws', hint: '' },
          ],
        },
        Corporation: {
          required: [
            { name: 'sec_certificate', docType: 'SEC', label: 'SEC Certificate of Registration', hint: '' },
            { name: 'corp_articles_bylaws', docType: 'SEC', label: 'Articles of Incorporation / By-laws', hint: '' },
            { name: 'board_resolution', docType: 'Others', label: 'Board Resolution', hint: 'Authorizing operation + naming representative' },
            { name: 'declared_fleet', docType: 'Others', label: 'Declared Fleet (Planned / Owned Vehicles)', hint: 'Upload PDF/Excel/CSV (planned/owned vehicles)' },
          ],
          optional: [
            { name: 'mayors_permit', docType: 'Others', label: "Mayor’s Permit", hint: '' },
            { name: 'business_permit', docType: 'Others', label: 'Business Permit', hint: '' },
          ],
        },
      };
      const matrix = docMatrix[opType] || docMatrix.Individual;
      function findFieldDoc(f) {
        const label = (f && f.label) ? String(f.label).trim() : '';
        const want = label.toLowerCase();
        const wantAlt = (f && f.name === 'declared_fleet') ? 'declared fleet' : want;
        return (rows || []).find((d) => {
          const rem = (d && d.remarks) ? String(d.remarks) : '';
          const head = rem.split('|')[0].trim().toLowerCase();
          if (head && (head === want || head === wantAlt)) return true;
          if (wantAlt && rem.toLowerCase().includes(wantAlt)) return true;
          return false;
        }) || null;
      }
      function fieldState(f) {
        const match = findFieldDoc(f);
        if (!match) return 'Pending Upload';
        const st = (match.doc_status || '').toString();
        if (st) return st;
        return Number(match.is_verified || 0) === 1 ? 'Verified' : 'For Review';
      }
      const renderField = (f, isRequired) => {
        const nm = String(f.name || '');
        const accept = nm === 'declared_fleet'
          ? '.pdf,.xlsx,.xls,.csv,application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv'
          : '.pdf,.jpg,.jpeg,.png';
        const st = fieldState(f);
        const doc = findFieldDoc(f);
        const docHref = doc && doc.file_path ? (rootUrl + '/admin/uploads/' + encodeURIComponent(String(doc.file_path))) : '';
        const badge = st === 'Verified'
          ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
          : (st === 'Rejected'
            ? 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20'
            : (st === 'Pending Upload'
              ? 'bg-sky-100 text-sky-700 ring-sky-600/20 dark:bg-sky-900/30 dark:text-sky-300 dark:ring-sky-500/20'
              : 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20'));
        const inputClass = isRequired
          ? 'w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800'
          : 'w-full text-sm';
        const govIdKindInput = nm === 'gov_id'
          ? `<div class="mb-2 grid grid-cols-1 gap-2">
               <select name="gov_id_kind" class="w-full px-3 py-2 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 text-sm font-semibold">
                 <option value="" selected>Select Government ID type</option>
                 <option>Driver’s License</option>
                 <option>UMID</option>
                 <option>PhilSys ID</option>
                 <option>Passport</option>
                 <option>Postal ID</option>
                 <option>PRC ID</option>
                 <option>Senior Citizen ID</option>
                 <option>Voter’s ID</option>
                 <option>SSS ID</option>
                 <option>GSIS ID</option>
                 <option>Other</option>
               </select>
               <input name="gov_id_kind_other" type="text" maxlength="80" placeholder="If Other, specify here" class="hidden w-full px-3 py-2 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 text-sm font-semibold">
             </div>`
          : '';
        return `
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
          <label class="flex items-center justify-between gap-2 text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
            <span>${String(f.label || '')}</span>
            <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black ring-1 ring-inset ${badge}">${st}</span>
          </label>
          ${govIdKindInput}
          <input name="${nm}" type="file" accept="${accept}" class="${inputClass}">
          ${f.hint ? `<div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">${String(f.hint)}</div>` : ``}
        </div>
      `};

      const requiredTotal = Array.isArray(matrix.required) ? matrix.required.length : 0;
      const requiredVerified = (Array.isArray(matrix.required) ? matrix.required : []).filter((f) => fieldState(f) === 'Verified').length;
      const optionalTotal = Array.isArray(matrix.optional) ? matrix.optional.length : 0;
      const optionalVerified = (Array.isArray(matrix.optional) ? matrix.optional : []).filter((f) => fieldState(f) === 'Verified').length;
      const requiredPct = requiredTotal > 0 ? Math.round((requiredVerified / requiredTotal) * 100) : 0;

      const uploadHtml = `
        <div class="space-y-4">
          <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
              <div>
                <div class="text-sm font-black text-slate-900 dark:text-white">Upload Missing Documents</div>
                <div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">${opType} — upload only what is applicable (PDF/JPG/PNG).</div>
              </div>
              <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                <div><span class="font-black">Required:</span> ${esc(requiredVerified)} / ${esc(requiredTotal)} verified</div>
                ${optionalTotal ? `<div class="mt-1"><span class="font-black">Optional:</span> ${esc(optionalVerified)} / ${esc(optionalTotal)} verified</div>` : ``}
              </div>
            </div>
            <div class="mt-4">
              <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div class="h-2 bg-emerald-600" style="width:${esc(requiredPct)}%"></div>
              </div>
              <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Required verification progress: ${esc(requiredPct)}%</div>
            </div>
          </div>

          <form id="formUploadOperatorDocs" class="space-y-4" novalidate>
            <input type="hidden" name="operator_id" value="${String(operatorId || '')}">

            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 p-5">
              <div class="flex items-center justify-between gap-3">
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Required</div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">${esc(requiredVerified)} / ${esc(requiredTotal)} verified</div>
              </div>
              <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                ${matrix.required.map((f) => renderField(f, true)).join('')}
              </div>
            </div>

            ${matrix.optional.length ? `
              <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
                <div class="flex items-center justify-between gap-3">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Optional / Supporting</div>
                  <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">${esc(optionalVerified)} / ${esc(optionalTotal)} verified</div>
                </div>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                  ${matrix.optional.map((f) => renderField(f, false)).join('')}
                </div>
              </div>
            ` : ``}

            <div class="flex items-center justify-end gap-2">
              <button id="btnUploadOperatorDocs" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Upload</button>
            </div>
          </form>
        </div>
      `;

      const tabsHtml = `
        <div class="flex items-center gap-2">
          <button type="button" data-doc-tab="uploaded" class="px-3 py-2 rounded-lg text-xs font-black border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">Uploaded</button>
          <button type="button" data-doc-tab="upload" class="px-3 py-2 rounded-lg text-xs font-black border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 text-slate-600 dark:text-slate-300">Upload Missing</button>
        </div>
      `;

      const uploadedPane = `
        <div data-doc-pane="uploaded" class="space-y-3">
          <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
            <div class="text-sm font-black text-slate-900 dark:text-white">Uploaded Documents</div>
            <div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">Verify or reject documents from the list below.</div>
          </div>
          ${listHtml}
        </div>
      `;

      const uploadPane = `<div data-doc-pane="upload" class="hidden">${uploadHtml}</div>`;

      body.innerHTML = `<div class="space-y-4">${tabsHtml}${uploadedPane}${uploadPane}</div>`;
      if (window.lucide) window.lucide.createIcons();

      const tabs = Array.from(body.querySelectorAll('[data-doc-tab]'));
      const panes = Array.from(body.querySelectorAll('[data-doc-pane]'));
      const setTab = (key) => {
        try { body.setAttribute('data-doc-active-tab', key); } catch (_) {}
        tabs.forEach((t) => {
          const k = t.getAttribute('data-doc-tab');
          const active = k === key;
          t.className = active
            ? 'px-3 py-2 rounded-lg text-xs font-black border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200'
            : 'px-3 py-2 rounded-lg text-xs font-black border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 text-slate-600 dark:text-slate-300';
        });
        panes.forEach((p) => {
          const k = p.getAttribute('data-doc-pane');
          if (k === key) p.classList.remove('hidden');
          else p.classList.add('hidden');
        });
      };
      tabs.forEach((t) => t.addEventListener('click', () => setTab(t.getAttribute('data-doc-tab') || 'uploaded')));
      const preferredTab = (body.getAttribute('data-doc-active-tab') || '').toString();
      setTab(preferredTab !== '' ? preferredTab : (requiredVerified < requiredTotal ? 'upload' : 'uploaded'));

      const govKindSel = body.querySelector('select[name="gov_id_kind"]');
      const govKindOther = body.querySelector('input[name="gov_id_kind_other"]');
      if (govKindSel && govKindOther) {
        const syncGovKind = () => {
          const v = (govKindSel.value || '').trim();
          if (v === 'Other') govKindOther.classList.remove('hidden');
          else {
            govKindOther.classList.add('hidden');
            govKindOther.value = '';
          }
        };
        govKindSel.addEventListener('change', syncGovKind);
        syncGovKind();
      }

      body.querySelectorAll('[data-doc-set="1"]').forEach((b) => {
        b.addEventListener('click', async () => {
          const docId = b.getAttribute('data-doc-id');
          const next = b.getAttribute('data-doc-status') || 'For Review';

          const processVerification = async (note) => {
            try {
              const fd = new FormData();
              fd.append('doc_id', String(docId || ''));
              fd.append('doc_status', String(next));
              if (String(next) === 'Verified' && note) {
                fd.append('remarks', note);
              }
              const res = await fetch(rootUrl + '/admin/api/module1/verify_operator_document.php', { method: 'POST', body: fd });
              const data = await res.json();
              if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'update_failed');
              showToast(next === 'Verified' ? 'Document verified.' : 'Document marked for review.');
              const latest = await loadOperatorDocs(operatorId);
              try { body.setAttribute('data-doc-active-tab', 'uploaded'); } catch (_) {}
              renderDocs(operatorId, operatorName, latest);
            } catch (err) {
              showToast(err.message || 'Failed', 'error');
            }
          };

          if (String(next) === 'Verified') {
            showModalPrompt('Verify Document', 'Verification Notes (Optional):', (val) => {
              processVerification(val);
            });
          } else {
            processVerification('');
          }
        });
      });
      body.querySelectorAll('[data-doc-reject="1"]').forEach((b) => {
        b.addEventListener('click', async () => {
          const docId = b.getAttribute('data-doc-id');
          
          showModalPrompt('Reject Document', 'Rejection Reason (Required):', async (remark) => {
            if (!remark) { showToast('Remarks required for rejection.', 'error'); return; }
            try {
              const fd = new FormData();
              fd.append('doc_id', String(docId || ''));
              fd.append('doc_status', 'Rejected');
              fd.append('remarks', remark);
              const res = await fetch(rootUrl + '/admin/api/module1/verify_operator_document.php', { method: 'POST', body: fd });
              const data = await res.json();
              if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'reject_failed');
              showToast('Document rejected.');
              const latest = await loadOperatorDocs(operatorId);
              try { body.setAttribute('data-doc-active-tab', 'uploaded'); } catch (_) {}
              renderDocs(operatorId, operatorName, latest);
            } catch (err) {
              showToast(err.message || 'Failed', 'error');
            }
          });
        });
      });

      const form = document.getElementById('formUploadOperatorDocs');
      const btn = document.getElementById('btnUploadOperatorDocs');
      if (form && btn) {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const fd = new FormData(form);
          const hasDocs = Array.from(form.querySelectorAll('input[type="file"]')).some((inp) => inp && inp.files && inp.files.length);
          if (!hasDocs) { showToast('Select at least one file to upload.', 'error'); return; }
          const orig = btn.textContent;
          btn.disabled = true;
          btn.textContent = 'Uploading...';
          try {
            const res = await fetch(rootUrl + '/admin/api/module1/upload_operator_docs.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'upload_failed');
            showToast('Documents uploaded.');
            const latest = await loadOperatorDocs(operatorId);
            renderDocs(operatorId, operatorName, latest);
          } catch (err) {
            showToast(err.message || 'Upload failed', 'error');
            btn.disabled = false;
            btn.textContent = orig;
          }
        });
      }
    }

    document.querySelectorAll('[data-op-review="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-operator-id');
        const name = btn.getAttribute('data-operator-name') || 'Operator';
        openModal(`<div class="text-sm text-slate-500 dark:text-slate-400">Loading...</div>`, 'Review • ' + name);
        try {
          const payload = await loadOperatorDocs(id);
          const opName = (payload && payload.operator && (payload.operator.display_name || payload.operator.name || payload.operator.full_name)) ? (payload.operator.display_name || payload.operator.name || payload.operator.full_name) : name;
          renderDocs(id, opName, payload);
        } catch (err) {
          body.innerHTML = `<div class="text-sm text-rose-600">${(err && err.message) ? err.message : 'Failed to load documents'}</div>`;
        }
      });
    });

    const sp = new URLSearchParams(window.location.search || '');
    const reviewId = (sp.get('review_operator_id') || '').toString().trim();
    if (reviewId) {
      const btn = document.querySelector('[data-op-review="1"][data-operator-id="' + reviewId.replace(/"/g, '\\"') + '"]');
      if (btn) {
        btn.click();
      } else {
        openModal(`<div class="text-sm text-slate-500 dark:text-slate-400">Loading...</div>`, 'Review • Operator #' + reviewId);
        loadOperatorDocs(reviewId).then((payload) => {
          const opName = (payload && payload.operator && (payload.operator.display_name || payload.operator.name || payload.operator.full_name)) ? (payload.operator.display_name || payload.operator.name || payload.operator.full_name) : ('Operator #' + reviewId);
          renderDocs(reviewId, opName, payload);
        }).catch((err) => {
          body.innerHTML = `<div class="text-sm text-rose-600">${(err && err.message) ? err.message : 'Failed to load documents'}</div>`;
        });
      }
    }
  })();
</script>
