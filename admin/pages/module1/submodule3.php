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
  MAX(CASE WHEN d.doc_type='GovID' THEN d.is_verified ELSE 0 END) AS govid_verified,
  MAX(CASE WHEN d.doc_type='CDA' THEN d.is_verified ELSE 0 END) AS cda_verified,
  MAX(CASE WHEN d.doc_type='SEC' THEN d.is_verified ELSE 0 END) AS sec_verified,
  MAX(CASE WHEN d.doc_type='BarangayCert' THEN d.is_verified ELSE 0 END) AS brgy_verified,
  MAX(CASE WHEN d.doc_type='Others' THEN d.is_verified ELSE 0 END) AS others_verified,
  SUM(CASE WHEN d.doc_id IS NULL THEN 0 ELSE 1 END) AS doc_count
FROM operators o
LEFT JOIN operator_documents d ON d.operator_id=o.id
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
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
        <div class="relative w-full sm:w-56">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Draft','Incomplete','Returned','Active','Inactive'] as $s): ?>
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
        <a href="?page=module1/submodule3" class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
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
            <th class="py-4 px-4">Required</th>
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
                  'Incomplete' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400',
                  'Returned' => 'bg-orange-100 text-orange-700 ring-orange-600/20 dark:bg-orange-900/30 dark:text-orange-400 dark:ring-orange-500/20',
                  'Inactive' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                };
                $required = tmm_required_doc_list($opType);
                $requiredText = implode(', ', $required);
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-6">
                  <div class="font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($row['display_name'] ?? '')); ?></div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ID: <?php echo (int)$rid; ?></div>
                </td>
                <td class="py-4 px-4 hidden md:table-cell">
                  <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-700/50 px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-300 ring-1 ring-inset ring-slate-500/10"><?php echo htmlspecialchars($opType); ?></span>
                </td>
                <td class="py-4 px-4 text-slate-600 dark:text-slate-300 font-semibold text-sm"><?php echo htmlspecialchars($requiredText); ?></td>
                <td class="py-4 px-4 text-slate-600 dark:text-slate-300 font-semibold text-sm"><?php echo (int)($row['doc_count'] ?? 0); ?></td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
                </td>
                <td class="py-4 px-4 text-right">
                  <div class="inline-flex items-center gap-2">
                    <a href="?page=puv-database/link-vehicle-to-operator&highlight_operator_id=<?php echo (int)$rid; ?>" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors" title="Link Vehicle to Operator">
                      <i data-lucide="link-2" class="w-4 h-4"></i>
                      Link Vehicle
                    </a>
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
            { name: 'declared_fleet', docType: 'Others', label: 'Declared Fleet (Planned / Owned Vehicles)', hint: 'Generate from linked vehicles (system-generated report)' },
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
            { name: 'declared_fleet', docType: 'Others', label: 'Declared Fleet (Planned / Owned Vehicles)', hint: 'Generate from linked vehicles (system-generated report)' },
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
            { name: 'declared_fleet', docType: 'Others', label: 'Declared Fleet (Planned / Owned Vehicles)', hint: 'Generate from linked vehicles (system-generated report)' },
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
      const renderField = (f) => {
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
              ? 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-300'
              : 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20'));
        if (nm === 'declared_fleet') {
          return `
          <div>
            <label class="flex items-center justify-between gap-2 text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
              <span>${String(f.label || '')}</span>
              <span class="px-2 py-0.5 rounded-md text-[10px] font-black ring-1 ring-inset ${badge}">${st}</span>
            </label>
            <div class="flex items-center gap-2">
              <button type="button" data-generate-fleet="1" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold text-sm">Generate Declared Fleet</button>
              ${docHref ? `<a href="${docHref}" target="_blank" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-sm">Open Latest</a>` : ``}
            </div>
            ${f.hint ? `<div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">${String(f.hint)}</div>` : ``}
            <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Includes linked vehicles, OR/CR & insurance attachments (if uploaded).</div>
          </div>
        `;
        }
        return `
        <div>
          <label class="flex items-center justify-between gap-2 text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
            <span>${String(f.label || '')}</span>
            <span class="px-2 py-0.5 rounded-md text-[10px] font-black ring-1 ring-inset ${badge}">${st}</span>
          </label>
          <input name="${nm}" type="file" accept="${accept}" class="w-full text-sm">
          ${f.hint ? `<div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">${String(f.hint)}</div>` : ``}
        </div>
      `};

      const uploadHtml = `
        <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-700">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Upload Missing</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">${opType} — upload only the relevant documents (PDF/JPG/PNG).</div>
          <form id="formUploadOperatorDocs" class="space-y-4 mt-4" novalidate>
            <input type="hidden" name="operator_id" value="${String(operatorId || '')}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2">
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Required</div>
              </div>
              ${matrix.required.map(renderField).join('')}
              ${matrix.optional.length ? `
                <div class="sm:col-span-2 pt-2">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Optional / Supporting</div>
                </div>
                ${matrix.optional.map(renderField).join('')}
              ` : ``}
            </div>
            <div class="flex items-center justify-end gap-2">
              <button id="btnUploadOperatorDocs" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Upload</button>
            </div>
          </form>
        </div>
      `;

      body.innerHTML = `<div>${listHtml}${uploadHtml}</div>`;
      if (window.lucide) window.lucide.createIcons();

      body.querySelectorAll('[data-doc-set="1"]').forEach((b) => {
        b.addEventListener('click', async () => {
          const docId = b.getAttribute('data-doc-id');
          const next = b.getAttribute('data-doc-status') || 'For Review';
          try {
            const fd = new FormData();
            fd.append('doc_id', String(docId || ''));
            fd.append('doc_status', String(next));
            if (String(next) === 'Verified') {
              const note = (prompt('Verification notes (optional):') || '').trim();
              if (note) fd.append('remarks', note);
            }
            const res = await fetch(rootUrl + '/admin/api/module1/verify_operator_document.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'update_failed');
            showToast(next === 'Verified' ? 'Document verified.' : 'Document marked for review.');
            const latest = await loadOperatorDocs(operatorId);
            renderDocs(operatorId, operatorName, latest);
          } catch (err) {
            showToast(err.message || 'Failed', 'error');
          }
        });
      });
      body.querySelectorAll('[data-doc-reject="1"]').forEach((b) => {
        b.addEventListener('click', async () => {
          const docId = b.getAttribute('data-doc-id');
          const remark = (prompt('Reject remarks (required):') || '').trim();
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
            renderDocs(operatorId, operatorName, latest);
          } catch (err) {
            showToast(err.message || 'Failed', 'error');
          }
        });
      });

      body.querySelectorAll('[data-generate-fleet="1"]').forEach((b) => {
        b.addEventListener('click', async () => {
          const orig = b.textContent;
          b.disabled = true;
          b.textContent = 'Generating...';
          try {
            const fd = new FormData();
            fd.append('operator_id', String(operatorId || ''));
            const res = await fetch(rootUrl + '/admin/api/module1/generate_declared_fleet.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data || !data.ok) {
              const code = (data && data.error) ? String(data.error) : 'generate_failed';
              if (code === 'no_linked_vehicles') throw new Error('No linked vehicles found for this operator.');
              throw new Error((data && data.message) ? String(data.message) : code);
            }

            const files = data.files || {};
            const pdfFile = files.pdf || '';
            const excelFile = files.excel || '';
            const token = data.token || '';
            const rows = Array.isArray(data.rows) ? data.rows : [];
            const count = rows.length;
            const previewRows = rows.slice(0, 25);
            const op = data.operator || {};
            const sys = data.system || {};
            const fa = data.franchise_application || {};
            const summary = data.summary || {};
            const breakdown = summary.breakdown || {};

            const pdfUrl = pdfFile ? (rootUrl + '/admin/uploads/' + encodeURIComponent(String(pdfFile))) : '';
            const excelUrl = excelFile ? (rootUrl + '/admin/uploads/' + encodeURIComponent(String(excelFile))) : '';

            const breakdownLines = Object.keys(breakdown).sort((a, b) => {
              const av = Number(breakdown[a] || 0);
              const bv = Number(breakdown[b] || 0);
              if (bv !== av) return bv - av;
              return String(a).localeCompare(String(b));
            }).map((k) => `<div class="text-xs font-semibold text-slate-700 dark:text-slate-200">- ${esc(k)}: ${esc(breakdown[k])}</div>`).join('');

            const previewHtml = `
              <div class="space-y-4">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                  <div class="text-sm font-black text-slate-900 dark:text-white">Declared Fleet Preview</div>
                  <div class="mt-1 text-xs font-semibold text-slate-600 dark:text-slate-300">Review the generated file first. Upload is only enabled after preview.</div>
                  <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                      <div class="font-black text-slate-700 dark:text-slate-200">${esc((sys.lgu_name || sys.name || '').toString()) || 'LGU PUV Management System'}</div>
                      <div class="mt-1 font-semibold text-slate-600 dark:text-slate-300">DECLARED FLEET REPORT</div>
                      <div class="mt-2 text-slate-600 dark:text-slate-300">
                        <div><span class="font-bold">Operator:</span> ${esc(operatorName || op.name || '')}</div>
                        <div><span class="font-bold">Operator Type:</span> ${esc(op.type || '')}</div>
                        <div><span class="font-bold">Operator ID:</span> ${esc(op.code || op.id || '')}</div>
                        ${fa.franchise_ref_number ? `<div><span class="font-bold">Franchise Application ID:</span> ${esc(fa.franchise_ref_number)}</div>` : ``}
                      </div>
                    </div>
                    <div class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                      <div class="font-black text-slate-700 dark:text-slate-200">Fleet Summary</div>
                      <div class="mt-1 text-xs font-semibold text-slate-600 dark:text-slate-300">Total Vehicles: ${esc(summary.total_vehicles || count)}</div>
                      <div class="mt-2 space-y-1">${breakdownLines || `<div class="text-xs font-semibold text-slate-600 dark:text-slate-300">No breakdown data.</div>`}</div>
                    </div>
                  </div>
                  <label class="mt-3 flex items-start gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
                    <input type="checkbox" class="mt-0.5 w-4 h-4" data-fleet-confirm="1">
                    <span>I reviewed the generated file and confirm it is correct.</span>
                  </label>
                  <div class="mt-3 flex flex-wrap gap-2">
                    ${pdfUrl ? `<a class="px-3 py-2 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="${esc(pdfUrl)}" target="_blank">Open PDF</a>` : ``}
                    ${excelUrl ? `<a class="px-3 py-2 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="${esc(excelUrl)}" target="_blank">Open Excel (CSV)</a>` : ``}
                    <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-blue-700 hover:bg-blue-800 text-white transition-colors" data-fleet-upload="pdf" data-fleet-token="${esc(token)}" disabled>Upload PDF to Documents</button>
                    <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white transition-colors" data-fleet-upload="excel" data-fleet-token="${esc(token)}" disabled>Upload Excel to Documents</button>
                    <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" data-fleet-back="1">Back</button>
                  </div>
                </div>

                <div class="text-xs font-bold text-slate-600 dark:text-slate-300">Previewing ${esc(previewRows.length)} of ${esc(count)} vehicles</div>
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                  <table class="min-w-full text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                      <tr class="text-left">
                        <th class="px-3 py-2 font-black">Plate</th>
                        <th class="px-3 py-2 font-black">Type</th>
                        <th class="px-3 py-2 font-black">Make</th>
                        <th class="px-3 py-2 font-black">Model</th>
                        <th class="px-3 py-2 font-black">Year</th>
                        <th class="px-3 py-2 font-black">Engine</th>
                        <th class="px-3 py-2 font-black">Chassis</th>
                        <th class="px-3 py-2 font-black">OR No</th>
                        <th class="px-3 py-2 font-black">CR No</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-900">
                      ${previewRows.map((r) => `
                        <tr>
                          <td class="px-3 py-2 font-bold">${esc(r.plate_number || '')}</td>
                          <td class="px-3 py-2">${esc(r.vehicle_type || '')}</td>
                          <td class="px-3 py-2">${esc(r.make || '')}</td>
                          <td class="px-3 py-2">${esc(r.model || '')}</td>
                          <td class="px-3 py-2">${esc(r.year_model || '')}</td>
                          <td class="px-3 py-2">${esc(r.engine_no || '')}</td>
                          <td class="px-3 py-2">${esc(r.chassis_no || '')}</td>
                          <td class="px-3 py-2">${esc(r.or_number || '')}</td>
                          <td class="px-3 py-2">${esc(r.cr_number || '')}</td>
                        </tr>
                      `).join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `;

            openModal(previewHtml, 'Declared Fleet • ' + operatorName);
            const confirmEl = body.querySelector('[data-fleet-confirm="1"]');
            const uploadBtns = Array.from(body.querySelectorAll('[data-fleet-upload]'));
            const setUploadEnabled = (enabled) => {
              uploadBtns.forEach((x) => {
                const fmt = x.getAttribute('data-fleet-upload') || '';
                if (fmt === 'pdf' && !pdfFile) { x.disabled = true; return; }
                if (fmt === 'excel' && !excelFile) { x.disabled = true; return; }
                x.disabled = !enabled;
              });
            };
            setUploadEnabled(false);
            if (confirmEl) confirmEl.addEventListener('change', () => setUploadEnabled(!!confirmEl.checked));
            body.querySelectorAll('[data-fleet-upload]').forEach((btnUp) => {
              btnUp.addEventListener('click', async () => {
                const fmt = btnUp.getAttribute('data-fleet-upload') || 'pdf';
                const tok = btnUp.getAttribute('data-fleet-token') || '';
                const origTxt = btnUp.textContent;
                btnUp.disabled = true;
                btnUp.textContent = 'Uploading...';
                try {
                  const fd2 = new FormData();
                  fd2.append('operator_id', String(operatorId || ''));
                  fd2.append('commit', '1');
                  fd2.append('token', String(tok || ''));
                  fd2.append('format', String(fmt || 'pdf'));
                  const res2 = await fetch(rootUrl + '/admin/api/module1/generate_declared_fleet.php', { method: 'POST', body: fd2 });
                  const data2 = await res2.json();
                  if (!data2 || !data2.ok) throw new Error((data2 && data2.error) ? data2.error : 'upload_failed');
                  showToast('Declared Fleet uploaded (For Review).');
                  const latest = await loadOperatorDocs(operatorId);
                  renderDocs(operatorId, operatorName, latest);
                  if (data2.file_path) {
                    window.open(rootUrl + '/admin/uploads/' + encodeURIComponent(String(data2.file_path)), '_blank');
                  }
                } catch (err2) {
                  showToast(err2.message || 'Failed', 'error');
                  btnUp.disabled = false;
                  btnUp.textContent = origTxt;
                }
              });
            });
            const backBtn = body.querySelector('[data-fleet-back="1"]');
            if (backBtn) {
              backBtn.addEventListener('click', async () => {
                try {
                  const latest = await loadOperatorDocs(operatorId);
                  renderDocs(operatorId, operatorName, latest);
                } catch (e) {
                  showToast('Failed to reload documents', 'error');
                }
              });
            }
          } catch (err) {
            showToast(err.message || 'Failed', 'error');
            b.disabled = false;
            b.textContent = orig;
          }
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
