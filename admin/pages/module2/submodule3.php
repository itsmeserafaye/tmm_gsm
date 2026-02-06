<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.endorse','module2.approve','module2.history']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$prefillApp = (int)($_GET['application_id'] ?? 0);

$erHasStatus = false;
$erHasConditions = false;
$colEr = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='endorsement_records' AND COLUMN_NAME IN ('endorsement_status','conditions')");
if ($colEr) {
  while ($c = $colEr->fetch_assoc()) {
    $cn = (string)($c['COLUMN_NAME'] ?? '');
    if ($cn === 'endorsement_status') $erHasStatus = true;
    if ($cn === 'conditions') $erHasConditions = true;
  }
}
$endorsementStatusExpr = $erHasStatus ? "er.endorsement_status" : "NULL";
$endorsementConditionsExpr = $erHasConditions ? "er.conditions" : "NULL";

$endorsedRows = [];
$sqlEnd = "SELECT fa.application_id, fa.franchise_ref_number, fa.status AS app_status, fa.endorsed_at,
                  COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
                  r.route_id AS route_code, r.origin, r.destination,
                  $endorsementStatusExpr AS endorsement_status,
                  $endorsementConditionsExpr AS conditions
           FROM franchise_applications fa
           LEFT JOIN operators o ON o.id=fa.operator_id
           LEFT JOIN routes r ON r.id=fa.route_id
           LEFT JOIN endorsement_records er ON er.application_id=fa.application_id
           WHERE fa.status IN ('LGU-Endorsed','Endorsed','Rejected')
           ORDER BY COALESCE(fa.endorsed_at, fa.submitted_at) DESC
           LIMIT 300";
$resEnd = $db->query($sqlEnd);
if ($resEnd) while ($r = $resEnd->fetch_assoc()) $endorsedRows[] = $r;

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-5xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Endorsement & LTFRB Approval</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Endorse Submitted applications and encode LTFRB issuance (PA / CPC). Validity countdown starts at LTFRB issue date.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module2/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="folder-open" class="w-4 h-4"></i>
        Applications
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-6">
      <form id="formLoad" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end" novalidate>
        <div class="flex-1 relative">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Submitted Applications</label>
          <button type="button" id="appDropdownBtn"
            class="w-full flex items-center justify-between gap-3 px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-200">
            <span id="appDropdownBtnText" class="truncate">Select application</span>
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
          </button>
          <div id="appDropdownPanel"
            class="hidden absolute left-0 right-0 mt-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl z-[120] overflow-hidden">
            <div class="p-3 border-b border-slate-200 dark:border-slate-700">
              <input id="appDropdownSearch" type="text" autocomplete="off"
                class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold"
                placeholder="Search application ref / operator / route…">
            </div>
            <div id="appDropdownList" class="max-h-72 overflow-auto"></div>
          </div>
          <input id="appIdInput" type="hidden" value="<?php echo (int)$prefillApp; ?>">
        </div>
        <button id="btnLoad" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-semibold">Load</button>
      </form>

      <div id="appDetails" class="hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="lg:col-span-2 space-y-4">
            <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Application</div>
              <div id="appTitle" class="mt-2 text-lg font-black text-slate-900 dark:text-white">-</div>
              <div id="appSub" class="mt-1 text-sm text-slate-600 dark:text-slate-300">-</div>
            </div>
            <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Operator</div>
                  <div id="opName" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
                  <div id="opMeta" class="mt-1 text-xs text-slate-500 dark:text-slate-400">-</div>
                </div>
                <div>
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Route</div>
                  <div id="routeLabel" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
                  <div id="routeMeta" class="mt-1 text-xs text-slate-500 dark:text-slate-400">-</div>
                </div>
                <div>
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Vehicle Count</div>
                  <div id="vehCount" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
                </div>
                <div>
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Status</div>
                  <div id="appStatus" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
                </div>
              </div>
            </div>
          </div>
          <div class="space-y-4">
            <div id="sectionEndorse" class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Endorse</div>
              <form id="formEndorse" class="space-y-4 mt-4" novalidate>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Endorsement Status</label>
                  <select name="endorsement_status" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                    <?php foreach (['Endorsed (Complete)','Endorsed (Conditional)','Rejected'] as $s): ?>
                      <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Conditions (optional)</label>
                  <textarea name="conditions" rows="3" maxlength="500" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Subject to passing vehicle inspection&#10;Subject to submission of OR/CR and insurance&#10;Subject to LTFRB CPC / PA issuance"></textarea>
                </div>
                <textarea name="notes" rows="4" maxlength="500" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Verified documents; ready for approval."></textarea>
                <button id="btnEndorse" class="w-full px-4 py-2.5 rounded-md bg-violet-700 hover:bg-violet-800 text-white font-semibold">Save Endorsement</button>
              </form>
            </div>
            <div id="sectionApprove" class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hidden">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">LTFRB Issuance Entry (PA / CPC)</div>
              <form id="formApprove" class="space-y-4 mt-4" novalidate>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Authority Type</label>
                  <select name="authority_type" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                    <?php foreach (['PA','CPC'] as $t): ?>
                      <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">LTFRB Reference No</label>
                  <input name="ltfrb_ref_no" required maxlength="40" pattern="^[0-9][0-9\\-\\/]{2,39}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 2026-0001">
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Decision Order No</label>
                  <input name="decision_order_no" required maxlength="40" pattern="^[0-9]{3,40}$" inputmode="numeric" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 1002003">
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Issue Date</label>
                  <input name="issue_date" type="date" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Validity starts here (LTFRB issuance date).</div>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Expiry Date</label>
                  <input name="expiry_date" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Auto-computed for PA (1 year). Required for CPC.</div>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Remarks</label>
                  <input name="remarks" maxlength="200" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Valid until expiry date">
                </div>
                <button id="btnApprove" class="w-full px-4 py-2.5 rounded-md bg-emerald-700 hover:bg-emerald-800 text-white font-semibold">Save Approval</button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div id="emptyState" class="text-sm text-slate-500 dark:text-slate-400 italic">Load an application to proceed.</div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700">
      <div class="text-sm font-black text-slate-900 dark:text-white">Endorsed Applications</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">LGU-Endorsed / Rejected records with endorsement status and conditions.</div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Application</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Operator</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Route</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Endorsement Status</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden lg:table-cell">Conditions</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Endorsed At</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php if ($endorsedRows): ?>
            <?php foreach ($endorsedRows as $row): ?>
              <?php
                $appId = (int)($row['application_id'] ?? 0);
                $ref = (string)($row['franchise_ref_number'] ?? '');
                $op = (string)($row['operator_name'] ?? '');
                $rc = (string)($row['route_code'] ?? '');
                $ro = (string)($row['origin'] ?? '');
                $rd = (string)($row['destination'] ?? '');
                $appSt = (string)($row['app_status'] ?? '');
                $es = trim((string)($row['endorsement_status'] ?? ''));
                if ($es === '') $es = ($appSt === 'Rejected') ? 'Rejected' : 'Endorsed (Complete)';
                $cond = trim((string)($row['conditions'] ?? ''));
                $badge = match($es) {
                  'Rejected' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  'Endorsed (Conditional)' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                  'Endorsed (Complete)' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                };
                $dt = (string)($row['endorsed_at'] ?? '');
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-6">
                  <div class="font-black text-slate-900 dark:text-white">APP-<?php echo $appId; ?></div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars($ref); ?></div>
                </td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold"><?php echo htmlspecialchars($op); ?></td>
                <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-medium"><?php echo htmlspecialchars($rc . ($ro !== '' || $rd !== '' ? (' • ' . trim($ro . ' → ' . $rd)) : '')); ?></td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($es); ?></span>
                </td>
                <td class="py-4 px-4 hidden lg:table-cell text-xs text-slate-600 dark:text-slate-300 font-semibold whitespace-pre-wrap"><?php echo htmlspecialchars($cond !== '' ? $cond : '-'); ?></td>
                <td class="py-4 px-4 hidden md:table-cell text-xs text-slate-500 dark:text-slate-400 font-medium"><?php echo htmlspecialchars($dt !== '' ? date('M d, Y', strtotime($dt)) : '-'); ?></td>
                <td class="py-4 px-4 text-right whitespace-nowrap">
                  <a href="?<?php echo http_build_query(['page'=>'module2/submodule3','application_id'=>$appId]); ?>" class="inline-flex items-center justify-center p-1.5 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="Open">
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">No endorsed applications yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const appDropdownBtn = document.getElementById('appDropdownBtn');
    const appDropdownBtnText = document.getElementById('appDropdownBtnText');
    const appDropdownPanel = document.getElementById('appDropdownPanel');
    const appDropdownSearch = document.getElementById('appDropdownSearch');
    const appDropdownList = document.getElementById('appDropdownList');
    const appIdInput = document.getElementById('appIdInput');
    const formLoad = document.getElementById('formLoad');
    const btnLoad = document.getElementById('btnLoad');
    const appDetails = document.getElementById('appDetails');
    const emptyState = document.getElementById('emptyState');
    const formEndorse = document.getElementById('formEndorse');
    const formApprove = document.getElementById('formApprove');
    const btnEndorse = document.getElementById('btnEndorse');
    const btnApprove = document.getElementById('btnApprove');

    let currentAppId = 0;
    let currentStatus = '';
    let pickTimer = null;
    let lastPickQuery = '';
    let lastPickItems = [];

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

    function setEnabled() {
      const sectionEndorse = document.getElementById('sectionEndorse');
      const sectionApprove = document.getElementById('sectionApprove');
      
      // Endorse Section: Visible only if status is Submitted
      if (sectionEndorse) {
        if (currentStatus === 'Submitted') {
          sectionEndorse.classList.remove('hidden');
          btnEndorse.disabled = false;
        } else {
          sectionEndorse.classList.add('hidden');
          btnEndorse.disabled = true;
        }
      }

      // Approve Section: Visible only if endorsed or already issued (for corrections)
      if (sectionApprove) {
        if (['Endorsed', 'LGU-Endorsed', 'Approved', 'LTFRB-Approved', 'PA Issued', 'CPC Issued'].includes(currentStatus)) {
          sectionApprove.classList.remove('hidden');
          btnApprove.disabled = false;
        } else {
          sectionApprove.classList.add('hidden');
          btnApprove.disabled = true;
        }
      }
    }

    async function loadApp(appId) {
      const res = await fetch(rootUrl + '/admin/api/module2/get_application.php?application_id=' + encodeURIComponent(appId));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return data.data;
    }

    function optionLabel(row) {
      const id = Number(row.application_id || 0);
      const ref = (row.franchise_ref_number || '').toString();
      const op = (row.operator_name || '').toString();
      const route = (row.route_code || row.route_id || '').toString();
      return `APP-${id} • ${ref || 'No Ref'} • ${route || 'No Route'} • ${op || 'Unknown Operator'}`;
    }

    async function fetchPendingLtfrbApps(q) {
      const url = rootUrl + '/admin/api/module2/list_applications.php?status=' + encodeURIComponent('Submitted') + '&limit=200&q=' + encodeURIComponent(q || '');
      const res = await fetch(url);
      const data = await res.json();
      if (!data || !data.ok) return [];
      return Array.isArray(data.data) ? data.data : [];
    }

    async function refreshAppDropdown(q) {
      const qq = (q || '').toString().trim();
      lastPickQuery = qq;
      if (appDropdownList) {
        appDropdownList.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500 italic">Loading…</div>';
      }
      const items = await fetchPendingLtfrbApps(qq);
      lastPickItems = Array.isArray(items) ? items : [];
      renderDropdownItems(lastPickItems);
    }

    function setDropdownLabel(text) {
      if (!appDropdownBtnText) return;
      const t = (text || '').toString().trim();
      appDropdownBtnText.textContent = t !== '' ? t : 'Select application';
    }

    function isDropdownOpen() {
      return appDropdownPanel && !appDropdownPanel.classList.contains('hidden');
    }

    function openDropdown() {
      if (!appDropdownPanel) return;
      appDropdownPanel.classList.remove('hidden');
      if (appDropdownSearch) {
        appDropdownSearch.focus();
        appDropdownSearch.select();
      }
      if (!lastPickItems.length) refreshAppDropdown(lastPickQuery);
    }

    function closeDropdown() {
      if (!appDropdownPanel) return;
      appDropdownPanel.classList.add('hidden');
    }

    function pickApplication(row) {
      const id = Number(row && row.application_id ? row.application_id : 0);
      if (!id) return;
      if (appIdInput) appIdInput.value = String(id);
      setDropdownLabel(optionLabel(row));
      closeDropdown();
    }

    function renderDropdownItems(items) {
      if (!appDropdownList) return;
      const currentId = Number((appIdInput && appIdInput.value) ? appIdInput.value : 0);
      if (!Array.isArray(items) || !items.length) {
        appDropdownList.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500 italic">No matches.</div>';
        return;
      }
      appDropdownList.innerHTML = '';
      items.forEach((row) => {
        const id = Number(row.application_id || 0);
        if (!id) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'w-full text-left px-4 py-3 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800/60 border-b border-slate-100 dark:border-slate-800 ' + (id === currentId ? 'bg-blue-50/60 dark:bg-blue-900/10' : '');
        btn.textContent = optionLabel(row);
        btn.addEventListener('click', () => pickApplication(row));
        appDropdownList.appendChild(btn);
      });
      const hasSelected = currentId > 0 && items.some((r) => Number(r.application_id || 0) === currentId);
      if (!hasSelected && currentId > 0) {
        const hint = document.createElement('div');
        hint.className = 'px-4 py-3 text-xs text-slate-500 dark:text-slate-400 italic';
        hint.textContent = 'Selected application is not in current results. Search to find it.';
        appDropdownList.appendChild(hint);
      }
    }

    if (appDropdownBtn) {
      appDropdownBtn.addEventListener('click', () => {
        if (isDropdownOpen()) closeDropdown(); else openDropdown();
      });
    }
    if (appDropdownSearch) {
      appDropdownSearch.addEventListener('input', () => {
        if (pickTimer) clearTimeout(pickTimer);
        pickTimer = setTimeout(() => refreshAppDropdown(appDropdownSearch.value || ''), 180);
      });
      appDropdownSearch.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { e.preventDefault(); closeDropdown(); }
      });
    }
    document.addEventListener('click', (e) => {
      if (!isDropdownOpen()) return;
      const t = e.target;
      if (!t) return;
      if (appDropdownPanel && appDropdownPanel.contains(t)) return;
      if (appDropdownBtn && appDropdownBtn.contains(t)) return;
      closeDropdown();
    });

    function render(a) {
      currentAppId = Number(a.application_id || 0);
      currentStatus = (a.status || '').toString().trim();
      if (appIdInput) appIdInput.value = String(currentAppId);
      setDropdownLabel(optionLabel(a));
      document.getElementById('appTitle').textContent = 'APP-' + currentAppId;
      document.getElementById('appSub').textContent = (a.franchise_ref_number || '').toString();
      document.getElementById('opName').textContent = (a.operator_name || '').toString();
      document.getElementById('opMeta').textContent = 'Type: ' + (a.operator_type || '-') + ' • Status: ' + (a.operator_status || '-');
      const routeLabel = (a.route_code || '-') + ((a.origin || a.destination) ? (' • ' + (a.origin || '') + ' → ' + (a.destination || '')) : '');
      document.getElementById('routeLabel').textContent = routeLabel;
      document.getElementById('routeMeta').textContent = 'Route status: ' + (a.route_status || '-');
      document.getElementById('vehCount').textContent = String(Number(a.vehicle_count || 0));
      document.getElementById('appStatus').textContent = currentStatus || '-';
      if (formApprove) {
        const typeEl = formApprove.querySelector('select[name="authority_type"]');
        const ltfrbEl = formApprove.querySelector('input[name="ltfrb_ref_no"]');
        const doEl = formApprove.querySelector('input[name="decision_order_no"]');
        const issueEl = formApprove.querySelector('input[name="issue_date"]');
        const expEl = formApprove.querySelector('input[name="expiry_date"]');
        if (typeEl && a.authority_type) typeEl.value = String(a.authority_type);
        if (ltfrbEl && a.ltfrb_ref_no) ltfrbEl.value = String(a.ltfrb_ref_no);
        if (doEl && a.decision_order_no) doEl.value = String(a.decision_order_no);
        if (issueEl && a.issue_date) issueEl.value = String(a.issue_date);
        if (expEl && a.franchise_expiry_date) expEl.value = String(a.franchise_expiry_date);
        if (typeEl) typeEl.dispatchEvent(new Event('change'));
        if (issueEl) issueEl.dispatchEvent(new Event('change'));
      }
      appDetails.classList.remove('hidden');
      emptyState.classList.add('hidden');
      setEnabled();
    }

    if (formLoad) {
      formLoad.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = Number((appIdInput && appIdInput.value) ? appIdInput.value : 0);
        if (!id) { showToast('Enter a valid application ID.', 'error'); return; }
        btnLoad.disabled = true;
        btnLoad.textContent = 'Loading...';
        try {
          const a = await loadApp(id);
          render(a);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        } finally {
          btnLoad.disabled = false;
          btnLoad.textContent = 'Load';
        }
      });
    }

    if (formEndorse) {
      formEndorse.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentAppId) return;
        if (currentStatus !== 'Submitted') { showToast('Only Submitted applications can be endorsed.', 'error'); return; }
        btnEndorse.disabled = true;
        btnEndorse.textContent = 'Saving...';
        try {
          const fd = new FormData(formEndorse);
          fd.append('application_id', String(currentAppId));
          const res = await fetch(rootUrl + '/admin/api/module2/endorse_app.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'endorse_failed');
          showToast('Endorsement saved.');
          const a = await loadApp(currentAppId);
          render(a);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        } finally {
          btnEndorse.textContent = 'Save Endorsement';
          setEnabled();
        }
      });
    }

    if (formApprove) {
      const ltfrbEl = formApprove.querySelector('input[name="ltfrb_ref_no"]');
      const doEl = formApprove.querySelector('input[name="decision_order_no"]');
      const typeEl = formApprove.querySelector('select[name="authority_type"]');
      const issueEl = formApprove.querySelector('input[name="issue_date"]');
      const expEl = formApprove.querySelector('input[name="expiry_date"]');
      if (ltfrbEl) {
        ltfrbEl.addEventListener('input', () => {
          ltfrbEl.value = (ltfrbEl.value || '').toString().replace(/[^0-9\/-]+/g, '').slice(0, 40);
        });
      }
      if (doEl) {
        doEl.addEventListener('input', () => {
          doEl.value = (doEl.value || '').toString().replace(/\D+/g, '').slice(0, 40);
        });
      }
      function addDays(iso, days) {
        if (!iso) return '';
        const d = new Date(iso + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + days);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
      }
      function recomputeExpiry() {
        if (!typeEl || !issueEl || !expEl) return;
        const t = (typeEl.value || '').toUpperCase();
        if (t === 'PA') {
          const issue = (issueEl.value || '').trim();
          if (issue) {
            const d = new Date(issue + 'T00:00:00');
            if (!isNaN(d.getTime())) {
              const plusOne = new Date(d);
              plusOne.setFullYear(plusOne.getFullYear() + 1);
              plusOne.setDate(plusOne.getDate() - 1);
              const yyyy = plusOne.getFullYear();
              const mm = String(plusOne.getMonth() + 1).padStart(2, '0');
              const dd = String(plusOne.getDate()).padStart(2, '0');
              expEl.value = `${yyyy}-${mm}-${dd}`;
            } else {
              expEl.value = '';
            }
          } else {
            expEl.value = '';
          }
          expEl.required = false;
          expEl.readOnly = true;
          expEl.classList.add('opacity-75');
        } else {
          expEl.readOnly = false;
          expEl.classList.remove('opacity-75');
          expEl.required = true;
        }
      }
      if (typeEl) typeEl.addEventListener('change', recomputeExpiry);
      if (issueEl) issueEl.addEventListener('change', recomputeExpiry);
      recomputeExpiry();
      formApprove.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentAppId) return;
        if (!(['Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued'].includes(currentStatus))) { showToast('Endorse the application first.', 'error'); return; }
        if (!formApprove.checkValidity()) { formApprove.reportValidity(); return; }
        btnApprove.disabled = true;
        btnApprove.textContent = 'Saving...';
        try {
          const fd = new FormData(formApprove);
          fd.append('application_id', String(currentAppId));
          const res = await fetch(rootUrl + '/admin/api/module2/approve_application.php', { method: 'POST', body: fd });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) {
            const code = (data && data.error) ? String(data.error) : 'approve_failed';
            const msg = (function(){
              if (code === 'orcr_required_for_approval') {
                const need = Number(data.need || 0) || 0;
                const have = Number(data.have || 0) || 0;
                const plates = Array.isArray(data.missing_plates) ? data.missing_plates.filter(Boolean) : [];
                const plateText = plates.length ? (' Missing OR/CR (not verified): ' + plates.slice(0, 8).join(', ') + (plates.length > 8 ? '…' : '')) : '';
                return `Approval needs ${need} verified OR/CR. Found ${have}.${plateText}`;
              }
              if (code === 'vehicles_not_ready') {
                const need = Number(data.need || 0) || 0;
                const have = Number(data.have || 0) || 0;
                const missIns = Array.isArray(data.missing_inspection) ? data.missing_inspection.filter(Boolean) : [];
                const missDocs = Array.isArray(data.missing_docs) ? data.missing_docs.filter(Boolean) : [];
                const parts = [];
                if (missIns.length) parts.push('Missing inspection: ' + missIns.slice(0, 6).join(', ') + (missIns.length > 6 ? '…' : ''));
                if (missDocs.length) parts.push('Missing OR/CR/insurance: ' + missDocs.slice(0, 6).join(', ') + (missDocs.length > 6 ? '…' : ''));
                return `Approval needs ${need} vehicles ready (linked, inspected, registered, insured). Found ${have}.` + (parts.length ? (' ' + parts.join(' • ')) : '');
              }
              if (code === 'no_linked_vehicles') return 'Operator has no linked vehicles. Link vehicles first.';
              if (code === 'duplicate_ltfrb_ref_no') return 'LTFRB Ref No already exists.';
              if (code === 'invalid_status') return 'Application status is not eligible for approval.';
              if (code === 'invalid_ltfrb_ref_no') return 'Invalid LTFRB Ref No format.';
              if (code === 'invalid_decision_order_no') return 'Decision Order No must be numeric.';
              if (code === 'invalid_authority_type') return 'Authority Type must be PA or CPC.';
              if (code === 'invalid_issue_date') return 'Invalid issue date.';
              if (code === 'invalid_expiry_date') return 'Invalid expiry date.';
              return (data && data.message) ? String(data.message) : code;
            })();
            showToast(msg, 'error');
            return;
          }
          showToast('Application approved.');
          const a = await loadApp(currentAppId);
          render(a);
        } catch (err) {
          showToast('Failed to save approval.', 'error');
        } finally {
          btnApprove.textContent = 'Save Approval';
          setEnabled();
        }
      });
    }

    if (<?php echo json_encode($prefillApp > 0); ?>) {
      formLoad.dispatchEvent(new Event('submit'));
    }
  })();
</script>
