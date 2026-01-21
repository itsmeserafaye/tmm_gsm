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
  o.verification_status,
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
  $sql .= " AND o.verification_status=?";
  $params[] = $status;
  $types .= 's';
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
  if ($operatorType === 'Cooperative') return ['CDA','Others'];
  if ($operatorType === 'Corporation') return ['SEC','Others'];
  return ['GovID'];
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
        <div class="relative w-full sm:w-44">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Draft','Verified','Inactive'] as $s): ?>
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
                $st = (string)($row['verification_status'] ?? 'Draft');
                $badge = match($st) {
                  'Verified' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  'Draft' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
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
                  <button type="button"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold transition-colors"
                    data-op-review="1"
                    data-operator-id="<?php echo (int)$rid; ?>"
                    data-operator-name="<?php echo htmlspecialchars((string)($row['display_name'] ?? ''), ENT_QUOTES); ?>">
                    <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                    Review
                  </button>
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
      return Array.isArray(data.data) ? data.data : [];
    }

    function renderDocs(operatorId, operatorName, rows) {
      const listHtml = rows.length ? `
        <div class="space-y-3">
          ${rows.map((d) => {
            const href = rootUrl + '/admin/uploads/' + encodeURIComponent(d.file_path || '');
            const dt = d.uploaded_at ? new Date(d.uploaded_at) : null;
            const date = dt && !isNaN(dt.getTime()) ? dt.toLocaleString() : '';
            const verified = (Number(d.is_verified || 0) === 1);
            const badge = verified
              ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
              : 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20';
            return `
              <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-3 min-w-0">
                  <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500">
                    <i data-lucide="file" class="w-4 h-4"></i>
                  </div>
                  <div class="min-w-0">
                    <div class="text-sm font-black text-slate-800 dark:text-white">${String(d.doc_type || '')}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 truncate">${date}</div>
                  </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ${badge}">${verified ? 'Verified' : 'Pending'}</span>
                  <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" data-doc-verify="1" data-doc-id="${String(d.doc_id || '')}" data-verify="${verified ? '0' : '1'}">${verified ? 'Unverify' : 'Verify'}</button>
                  <a href="${href}" target="_blank" class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-blue-600 transition-colors" title="Open">
                    <i data-lucide="external-link" class="w-4 h-4"></i>
                  </a>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      ` : `<div class="text-sm text-slate-500 dark:text-slate-400 italic">No documents uploaded.</div>`;

      const uploadHtml = `
        <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-700">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Upload Missing</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">GovID, CDA, SEC, or Others (PDF/JPG/PNG).</div>
          <form id="formUploadOperatorDocs" class="space-y-4 mt-4" novalidate>
            <input type="hidden" name="operator_id" value="${String(operatorId || '')}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">GovID</label>
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
            <div class="flex items-center justify-end gap-2">
              <button id="btnUploadOperatorDocs" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Upload</button>
            </div>
          </form>
        </div>
      `;

      body.innerHTML = `<div>${listHtml}${uploadHtml}</div>`;
      if (window.lucide) window.lucide.createIcons();

      body.querySelectorAll('[data-doc-verify="1"]').forEach((b) => {
        b.addEventListener('click', async () => {
          const docId = b.getAttribute('data-doc-id');
          const verify = b.getAttribute('data-verify') === '1' ? 1 : 0;
          try {
            const fd = new FormData();
            fd.append('doc_id', String(docId || ''));
            fd.append('is_verified', String(verify));
            const res = await fetch(rootUrl + '/admin/api/module1/verify_operator_document.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'verify_failed');
            showToast(verify ? 'Document verified.' : 'Document marked pending.');
            const latest = await loadOperatorDocs(operatorId);
            renderDocs(operatorId, operatorName, latest);
          } catch (err) {
            showToast(err.message || 'Failed', 'error');
          }
        });
      });

      const form = document.getElementById('formUploadOperatorDocs');
      const btn = document.getElementById('btnUploadOperatorDocs');
      if (form && btn) {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const fd = new FormData(form);
          const hasDocs = (fd.get('id_doc') && fd.get('id_doc').name) || (fd.get('cda_doc') && fd.get('cda_doc').name) || (fd.get('sec_doc') && fd.get('sec_doc').name) || (fd.get('others_doc') && fd.get('others_doc').name);
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
          const rows = await loadOperatorDocs(id);
          renderDocs(id, name, rows);
        } catch (err) {
          body.innerHTML = `<div class="text-sm text-rose-600">${(err && err.message) ? err.message : 'Failed to load documents'}</div>`;
        }
      });
    });
  })();
</script>
