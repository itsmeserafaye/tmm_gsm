<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.endorse','module2.approve','module2.history']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$prefillApp = (int)($_GET['application_id'] ?? 0);
$activeTab = (string)($_GET['tab'] ?? '');
if ($activeTab === '') $activeTab = 'review';
$q = trim((string)($_GET['q'] ?? ''));

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
$sqlEnd = "SELECT fa.application_id,
                  fa.franchise_ref_number,
                  fa.status AS app_status,
                  fa.endorsed_at,
                  fa.vehicle_type,
                  fa.vehicle_count,
                  COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
                  COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code,
                  r.origin,
                  r.destination,
                  $endorsementStatusExpr AS endorsement_status,
                  $endorsementConditionsExpr AS conditions
           FROM franchise_applications fa
           LEFT JOIN operators o ON o.id=fa.operator_id
           LEFT JOIN routes r ON r.id=fa.route_id
           LEFT JOIN endorsement_records er ON er.application_id=fa.application_id
           WHERE fa.status IN ('LGU-Endorsed','Endorsed','Rejected')
             AND COALESCE(NULLIF(fa.vehicle_type,''),'')<>'Tricycle'";
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $sqlEnd .= " AND (fa.franchise_ref_number LIKE '%$qv%' OR COALESCE(NULLIF(o.name,''), o.full_name) LIKE '%$qv%' OR COALESCE(NULLIF(r.route_code,''), r.route_id) LIKE '%$qv%' OR COALESCE(r.origin,'') LIKE '%$qv%' OR COALESCE(r.destination,'') LIKE '%$qv%')";
}
$sqlEnd .= " ORDER BY COALESCE(fa.endorsed_at, fa.submitted_at) DESC LIMIT 300";
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
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">PUV Local Endorsement / Permit</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">For non‑tricycle PUVs (jeepney, UV, bus): LGU reviews LTFRB franchise proof, LTO OR/CR, insurance, and vehicle details, then records local endorsement / permit details. This is not local franchise issuance.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module2/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="folder-open" class="w-4 h-4"></i>
        Applications
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
    <div class="border-b border-slate-200 dark:border-slate-700 px-6 pt-4">
      <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-800 p-1 text-xs font-bold">
        <button type="button" data-tab="review" class="px-3 py-1.5 rounded-lg <?php echo $activeTab === 'review' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-300'; ?>">To Review</button>
        <button type="button" data-tab="history" class="px-3 py-1.5 rounded-lg <?php echo $activeTab === 'history' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-300'; ?>">Endorsed / History</button>
      </div>
    </div>
    <div class="p-6 space-y-6" id="tab-review" <?php echo $activeTab === 'history' ? 'style="display:none"' : ''; ?>>
      <form id="formLoad" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end" novalidate>
        <div class="flex-1 relative">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">PUV Endorsement / Permit Applications</label>
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
              <div class="mt-3">
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Local Endorsement Status</div>
                <div id="lptrpStatusView" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
              </div>
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
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Local Endorsement Approval</div>
              <form id="formEndorse" class="space-y-4 mt-4" novalidate>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Approved Units</label>
                  <input id="approvedUnits" name="approved_units" type="number" min="1" max="500" step="1" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <div id="approvedUnitsHint" class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400"></div>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Approved Route</label>
                  <select id="approvedRouteSelect" name="approved_route_id" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                    <option value="">Use requested route</option>
                  </select>
                  <div id="approvedRouteHint" class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400"></div>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Notes</label>
                  <textarea name="notes" rows="4" maxlength="500" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Documents complete; units and route verified."></textarea>
                </div>
                <div class="flex items-center gap-2">
                  <button type="button" id="btnEndorseApprove" class="flex-1 px-4 py-2.5 rounded-md bg-emerald-700 hover:bg-emerald-800 text-white font-semibold">Approve</button>
                  <button type="button" id="btnEndorseReject" class="flex-1 px-4 py-2.5 rounded-md bg-rose-600 hover:bg-rose-700 text-white font-semibold">Reject</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div id="emptyState" class="text-sm text-slate-500 dark:text-slate-400 italic">Load an application to proceed.</div>
    </div>
    <div class="p-6 space-y-4" id="tab-history" <?php echo $activeTab === 'review' ? 'style="display:none"' : ''; ?>>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <div class="text-sm font-black text-slate-900 dark:text-white">Endorsed Applications</div>
          <div class="text-xs text-slate-500 dark:text-slate-400">LGU-Endorsed / Rejected records for non-tricycle PUVs with endorsement status.</div>
        </div>
        <?php if (has_permission('reports.export')): ?>
          <?php
            $qs = http_build_query(['q' => $q]);
            tmm_render_export_toolbar([[
              'href' => $rootUrl . '/admin/api/module2/print_endorsements.php?' . $qs,
              'label' => 'Print',
              'icon' => 'printer',
              'attrs' => [
                'data-print-url' => $rootUrl . '/admin/api/module2/print_endorsements.php?' . $qs,
                'data-report-name' => 'Endorsed Applications Report'
              ]
            ]], ['mb' => 'mb-0']);
          ?>
        <?php endif; ?>
      </div>
      <form method="GET" class="flex flex-col sm:flex-row sm:items-center gap-2 w-full">
        <input type="hidden" name="page" value="module2/submodule3">
        <input type="hidden" name="tab" value="history">
        <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full sm:w-72 px-3 py-2 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Search ref/operator/route…">
        <button class="w-full sm:w-auto px-4 py-2 rounded-md bg-slate-900 dark:bg-slate-700 text-white text-sm font-semibold">Apply</button>
        <a href="?<?php echo http_build_query(['page'=>'module2/submodule3','tab'=>'history']); ?>" class="w-full sm:w-auto px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold text-center">Reset</a>
      </form>
      <div class="overflow-x-auto lg:overflow-x-visible">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
            <tr class="text-left text-slate-500 dark:text-slate-400">
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Application</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Operator</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Vehicle Type</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Route</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Units</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Endorsement Status</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden lg:table-cell">Endorsed At</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
            <?php if ($endorsedRows): ?>
              <?php foreach ($endorsedRows as $row): ?>
                <?php
                  $appId = (int)($row['application_id'] ?? 0);
                  $ref = (string)($row['franchise_ref_number'] ?? '');
                  $op = (string)($row['operator_name'] ?? '');
                  $vt = (string)($row['vehicle_type'] ?? '');
                  $units = (int)($row['vehicle_count'] ?? 0);
                  $rc = (string)($row['route_code'] ?? '');
                  $ro = (string)($row['origin'] ?? '');
                  $rd = (string)($row['destination'] ?? '');
                  $appSt = (string)($row['app_status'] ?? '');
                  $es = trim((string)($row['endorsement_status'] ?? ''));
                  if ($es === '') $es = ($appSt === 'Rejected') ? 'Rejected' : 'Endorsed (Complete)';
                  $badge = match($es) {
                    'Rejected' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                    'Endorsed (Conditional)' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                    'Endorsed (Complete)' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                    default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                  };
                  $dt = (string)($row['endorsed_at'] ?? '');
                  $routeLabel = $rc;
                  if ($ro !== '' || $rd !== '') $routeLabel .= ' • ' . trim($ro . ' → ' . $rd);
                ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                  <td class="py-3 px-4">
                    <div class="font-black text-slate-900 dark:text-white">APP-<?php echo $appId; ?></div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars($ref); ?></div>
                  </td>
                  <td class="py-3 px-4 text-slate-700 dark:text-slate-200 font-semibold"><?php echo htmlspecialchars($op); ?></td>
                  <td class="py-3 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-medium"><?php echo htmlspecialchars($vt); ?></td>
                  <td class="py-3 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-medium"><?php echo htmlspecialchars($routeLabel); ?></td>
                  <td class="py-3 px-4 hidden sm:table-cell font-black text-slate-700 dark:text-slate-200"><?php echo $units; ?></td>
                  <td class="py-3 px-4">
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($es); ?></span>
                  </td>
                  <td class="py-3 px-4 hidden lg:table-cell text-xs text-slate-500 dark:text-slate-400 font-medium"><?php echo htmlspecialchars($dt !== '' ? date('M d, Y', strtotime($dt)) : '-'); ?></td>
                  <td class="py-3 px-4 text-right whitespace-nowrap">
                    <a href="?<?php echo http_build_query(['page'=>'module2/submodule3','application_id'=>$appId,'tab'=>'review']); ?>" class="inline-flex items-center justify-center p-1.5 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="Open">
                      <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8" class="py-10 text-center text-slate-500 font-medium italic">No endorsed applications yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
</div>

<div id="modalFinalizeApproval" class="fixed inset-0 z-[200] hidden">
  <div id="modalFinalizeApprovalBackdrop" class="absolute inset-0 bg-slate-900/50 opacity-0 transition-opacity"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div id="modalFinalizeApprovalPanel" class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl transform scale-95 opacity-0 transition-all">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div class="font-black text-slate-900 dark:text-white">Finalize Approval</div>
        <button type="button" id="modalFinalizeApprovalClose" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div class="p-6 space-y-5 max-h-[80vh] overflow-y-auto">
        <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 p-4">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Submitted (Reference)</div>
          <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
            <div>
              <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Requested Units</div>
              <div id="finalRefUnits" class="mt-1 font-black">-</div>
            </div>
            <div>
              <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Requested Route</div>
              <div id="finalRefRoute" class="mt-1 font-black">-</div>
            </div>
          </div>
        </div>

        <form id="formFinalizeApproval" class="space-y-4" novalidate>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Approved Units</label>
              <input id="finalApprovedUnits" name="approved_vehicle_count" type="number" min="1" max="500" step="1" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Assigned Routes</label>
              <div id="finalRoutesBox" class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 max-h-56 overflow-y-auto text-sm font-semibold"></div>
              <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Select the final assigned routes for this authority.</div>
            </div>
          </div>
          <div class="flex items-center justify-end gap-2 pt-2">
            <button type="button" id="btnFinalizeCancel" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">Cancel</button>
            <button id="btnFinalizeConfirm" class="px-4 py-2.5 rounded-md bg-emerald-700 hover:bg-emerald-800 text-white font-semibold">Confirm & Save</button>
          </div>
        </form>
      </div>
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
    const btnEndorseApprove = document.getElementById('btnEndorseApprove');
    const btnEndorseReject = document.getElementById('btnEndorseReject');
    const approvedUnitsInput = document.getElementById('approvedUnits');
    const approvedUnitsHint = document.getElementById('approvedUnitsHint');
    const approvedRouteSelect = document.getElementById('approvedRouteSelect');
    const approvedRouteHint = document.getElementById('approvedRouteHint');
    const tabButtons = document.querySelectorAll('[data-tab]');

    let currentAppId = 0;
    let currentStatus = '';
    let currentLptrpStatus = '';
    let pickTimer = null;
    let lastPickQuery = '';
    let lastPickItems = [];
    let routesCache = null;

    function normalizeVehicleCategory(v) {
      const s = (v || '').toString().trim();
      if (!s) return '';
      if (['Tricycle','Jeepney','UV','Bus'].includes(s)) return s;
      const l = s.toLowerCase();
      if (l.includes('tricycle') || l.includes('e-trike') || l.includes('pedicab')) return 'Tricycle';
      if (l.includes('jeepney')) return 'Jeepney';
      if (l.includes('bus') || l.includes('mini-bus')) return 'Bus';
      if (l.includes('uv') || l.includes('van') || l.includes('shuttle')) return 'UV';
      return '';
    }

    async function loadRoutes() {
      if (routesCache) return routesCache;
      const res = await fetch(rootUrl + '/admin/api/module2/routes_list.php');
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) throw new Error('routes_load_failed');
      routesCache = Array.isArray(data.data) ? data.data : [];
      return routesCache;
    }

    function populateApprovedRouteSelect(vehicleType, currentRouteDbId, requestedRouteLabel) {
      if (!approvedRouteSelect) return;
      approvedRouteSelect.innerHTML = '';
      const optDefault = document.createElement('option');
      optDefault.value = '';
      optDefault.textContent = 'Use requested route';
      approvedRouteSelect.appendChild(optDefault);
      const rows = Array.isArray(routesCache) ? routesCache : [];
      const cat = normalizeVehicleCategory(vehicleType);
      const options = rows.filter((r) => {
        if (!r) return false;
        const kind = String(r.kind || 'route');
        if (kind !== 'route') return false;
        const rv = String(r.vehicle_type || '');
        if (!cat) return true;
        return normalizeVehicleCategory(rv) === cat;
      });
      const seen = new Set();
      options.forEach((r) => {
        const id = Number(r.route_db_id || r.id || 0);
        if (!id || seen.has(id)) return;
        seen.add(id);
        const code = String(r.route_code || r.route_id || '');
        const name = String(r.route_name || '');
        const od = [String(r.origin || ''), String(r.destination || '')].filter(Boolean).join(' → ');
        const label = [code, name].filter(Boolean).join(' • ') + (od ? ' • ' + od : '');
        const opt = document.createElement('option');
        opt.value = String(id);
        opt.textContent = label;
        approvedRouteSelect.appendChild(opt);
      });
      if (currentRouteDbId) {
        approvedRouteSelect.value = String(currentRouteDbId);
      } else {
        approvedRouteSelect.value = '';
      }
      if (approvedRouteHint) {
        approvedRouteHint.textContent = requestedRouteLabel ? ('Requested: ' + requestedRouteLabel) : '';
      }
    }

    function escapeHtml(s) {
      return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

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
      if (sectionEndorse) {
        if (['Submitted','Pending Review','Returned for Correction'].includes(currentStatus)) {
          sectionEndorse.classList.remove('hidden');
        } else {
          sectionEndorse.classList.add('hidden');
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
      const url = rootUrl + '/admin/api/module2/list_applications.php?limit=200'
        + '&q=' + encodeURIComponent(q || '')
        + '&vehicle_type=' + encodeURIComponent('PUV')
        + '&submitted_channel=' + encodeURIComponent('PUV_LOCAL_ENDORSEMENT');
      try {
        const res = await fetch(url);
        const ct = (res.headers.get('content-type') || '').toLowerCase();
        let data = null;
        if (ct.includes('application/json')) {
          data = await res.json().catch(() => null);
        } else {
          return [];
        }
        if (!data || !data.ok) return [];
        const rows = Array.isArray(data.data) ? data.data : [];
        const allowedStatus = new Set(['Submitted','Pending Review','Returned for Correction']);
        return rows.filter((row) => {
          const st = (row && row.status) ? String(row.status) : '';
          return allowedStatus.has(st);
        });
      } catch (_) {
        return [];
      }
    }

    async function refreshAppDropdown(q) {
      const qq = (q || '').toString().trim();
      lastPickQuery = qq;
      if (appDropdownList) {
        appDropdownList.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500 italic">Loading…</div>';
      }
      try {
        const items = await fetchPendingLtfrbApps(qq);
        lastPickItems = Array.isArray(items) ? items : [];
        renderDropdownItems(lastPickItems);
      } catch (err) {
        lastPickItems = [];
        if (appDropdownList) {
          appDropdownList.innerHTML = '<div class="px-4 py-3 text-sm text-rose-600">Failed to load applications.</div>';
        }
      }
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

    async function render(a) {
      currentAppId = Number(a.application_id || 0);
      currentStatus = (a.status || '').toString().trim();
      currentLptrpStatus = (a.lptrp_status || '').toString().trim();
      if (appIdInput) appIdInput.value = String(currentAppId);
      setDropdownLabel(optionLabel(a));
      document.getElementById('appTitle').textContent = 'APP-' + currentAppId;
      document.getElementById('appSub').textContent = (a.franchise_ref_number || '').toString();
      document.getElementById('opName').textContent = (a.operator_name || '').toString();
      document.getElementById('opMeta').textContent = 'Type: ' + (a.operator_type || '-') + ' • Status: ' + (a.operator_status || '-');
      const routeLabel = (a.route_code || '-') + ((a.origin || a.destination) ? (' • ' + (a.origin || '') + ' → ' + (a.destination || '')) : '');
      document.getElementById('routeLabel').textContent = routeLabel;
      document.getElementById('routeMeta').textContent = 'Route status: ' + (a.route_status || '-');
      const requestedUnits = Number(a.vehicle_count || 0) || 1;
      document.getElementById('vehCount').textContent = String(requestedUnits);
      document.getElementById('appStatus').textContent = currentStatus || '-';
      const lpv = document.getElementById('lptrpStatusView');
      if (lpv) lpv.textContent = currentLptrpStatus || '-';
      if (approvedUnitsInput) approvedUnitsInput.value = String(requestedUnits);
      if (approvedUnitsHint) approvedUnitsHint.textContent = 'Requested: ' + String(requestedUnits) + ' unit' + (requestedUnits === 1 ? '' : 's');
      window.__currentRouteId = Number(a.route_id || 0) || 0;
      try {
        await loadRoutes();
        populateApprovedRouteSelect(String(a.vehicle_type || ''), window.__currentRouteId, routeLabel);
      } catch (_) {
        if (approvedRouteHint && routeLabel) approvedRouteHint.textContent = 'Requested: ' + routeLabel;
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

    const btnLptrpSave = document.getElementById('btnLptrpSave');
    if (btnLptrpSave) {
      btnLptrpSave.addEventListener('click', async () => {
        const sel = document.getElementById('lptrpStatusSelect');
        const val = sel ? (sel.value || '') : '';
        if (!currentAppId || !val) { showToast('Pick an application and status.', 'error'); return; }
        btnLptrpSave.disabled = true;
        btnLptrpSave.textContent = 'Saving...';
        try {
          const fd = new FormData();
          fd.append('application_id', String(currentAppId));
          fd.append('lptrp_status', val);
          const res = await fetch(rootUrl + '/admin/api/module2/update_lptrp_status.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'update_failed');
          currentLptrpStatus = val;
          const lpv2 = document.getElementById('lptrpStatusView');
          if (lpv2) lpv2.textContent = currentLptrpStatus || '-';
          showToast('Local endorsement status updated.');
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        }
        btnLptrpSave.disabled = false;
        btnLptrpSave.textContent = 'Save';
      });
    }

    if (tabButtons && tabButtons.length) {
      tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          const tab = btn.getAttribute('data-tab');
          if (!tab) return;
          const params = new URLSearchParams(window.location.search || '');
          params.set('page', 'module2/submodule3');
          params.set('tab', tab);
          if (currentAppId) params.set('application_id', String(currentAppId));
          window.location.search = params.toString();
        });
      });
    }

    function submitEndorse(decision) {
      if (!formEndorse || !currentAppId) return;
      const isApprove = decision === 'Approved';
      const btn = isApprove ? btnEndorseApprove : btnEndorseReject;
      if (!btn) return;
      btn.disabled = true;
      const orig = btn.textContent;
      btn.textContent = isApprove ? 'Approving...' : 'Rejecting...';
      (async () => {
        try {
          const fd = new FormData(formEndorse);
          fd.append('application_id', String(currentAppId));
          fd.append('endorsement_status', decision);
          const res = await fetch(rootUrl + '/admin/api/module2/endorse_app.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'endorse_failed');
          if (isApprove) {
            const fd2 = new FormData();
            fd2.append('application_id', String(currentAppId));
            fd2.append('lptrp_status', 'Approved');
            if (approvedUnitsInput) {
              const au = Number(approvedUnitsInput.value || 0);
              if (au > 0) fd2.append('approved_units', String(au));
            }
            if (approvedRouteSelect) {
              const rid = Number(approvedRouteSelect.value || 0);
              if (rid > 0) fd2.append('approved_route_id', String(rid));
            }
            await fetch(rootUrl + '/admin/api/module2/update_lptrp_status.php', { method: 'POST', body: fd2 });
          } else {
            const fd2 = new FormData();
            fd2.append('application_id', String(currentAppId));
            fd2.append('lptrp_status', 'Rejected');
            await fetch(rootUrl + '/admin/api/module2/update_lptrp_status.php', { method: 'POST', body: fd2 });
          }
          showToast(isApprove ? 'Endorsement approved.' : 'Endorsement rejected.');
          const params = new URLSearchParams(window.location.search || '');
          params.set('page', 'module2/submodule3');
          params.set('tab', 'history');
          params.set('highlight_application_id', String(currentAppId));
          window.location.href = '?' + params.toString();
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        } finally {
          btn.disabled = false;
          btn.textContent = orig;
        }
      })();
    }

    if (btnEndorseApprove) {
      btnEndorseApprove.addEventListener('click', () => {
        const au = Number(approvedUnitsInput ? approvedUnitsInput.value : 0) || 0;
        if (au <= 0) { showToast('Set approved units before approving.', 'error'); return; }
        submitEndorse('Approved');
      });
    }
    if (btnEndorseReject) {
      btnEndorseReject.addEventListener('click', () => submitEndorse('Rejected'));
    }

    if (<?php echo json_encode($prefillApp > 0); ?>) {
      formLoad.dispatchEvent(new Event('submit'));
    }
  })();
</script>
