<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$statTotal = (int)($db->query("SELECT COUNT(*) AS c FROM operators")->fetch_assoc()['c'] ?? 0);
$statActive = (int)($db->query("SELECT COUNT(*) AS c FROM operators WHERE workflow_status='Active'")->fetch_assoc()['c'] ?? 0);
$statIncomplete = (int)($db->query("SELECT COUNT(*) AS c FROM operators WHERE workflow_status IN ('Incomplete','Pending Validation')")->fetch_assoc()['c'] ?? 0);
$statDraft = (int)($db->query("SELECT COUNT(*) AS c FROM operators WHERE workflow_status='Draft'")->fetch_assoc()['c'] ?? 0);
$statInactive = (int)($db->query("SELECT COUNT(*) AS c FROM operators WHERE workflow_status='Inactive'")->fetch_assoc()['c'] ?? 0);

$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['operator_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$highlightId = (int)($_GET['highlight_operator_id'] ?? 0);

$sql = "SELECT id, operator_type, COALESCE(NULLIF(registered_name,''), NULLIF(name,''), full_name) AS display_name, address, contact_no, email, workflow_status, created_at,
               COALESCE(portal_user_id, 0) AS portal_user_id, COALESCE(submitted_by_name,'') AS submitted_by_name, submitted_at
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
  if ($status === 'Incomplete') {
    $conds[] = "workflow_status IN ('Incomplete','Pending Validation')";
  } elseif ($status === 'Returned') {
    $conds[] = "workflow_status IN ('Returned','Rejected')";
  } else {
    $conds[] = "workflow_status=?";
    $params[] = $status;
    $types .= 's';
  }
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
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
      <a href="?page=puv-database/vehicle-encoding" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="car" class="w-4 h-4"></i>
        Vehicles
      </a>
      <?php if (has_any_permission(['module1.link_vehicle','module1.write'])): ?>
        <a href="?page=puv-database/link-vehicle-to-operator" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="link-2" class="w-4 h-4"></i>
          Link Vehicle
        </a>
      <?php endif; ?>
      <div class="w-full flex items-start gap-2 rounded-md bg-slate-50 dark:bg-slate-800/50 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
        <i data-lucide="info" class="w-4 h-4"></i>
        Operator records are submitted via Operator Portal. Use Assisted Encoding for walk-ins.
      </div>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($statTotal); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active</div>
      <div class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?php echo number_format($statActive); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Incomplete</div>
      <div class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400"><?php echo number_format($statIncomplete); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Draft</div>
      <div class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400"><?php echo number_format($statDraft); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Inactive</div>
      <div class="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400"><?php echo number_format($statInactive); ?></div>
    </div>
  </div>

  <div class="flex justify-end">
    <?php if (has_permission('module1.write')): ?>
      <button id="btnOpenAddOperator" type="button" class="inline-flex items-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="user-plus" class="w-4 h-4"></i>
        Assisted Encoding (Walk-in)
      </button>
    <?php endif; ?>
  </div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
      <div>
        <div class="text-lg font-bold text-slate-900 dark:text-white">Operator Record Submissions</div>
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-1">Submitted by operators via Operator Portal. Approve or reject to finalize the official operator record.</div>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
        <select id="subStatus" class="w-full sm:w-auto rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm font-semibold">
          <option value="Submitted">Submitted</option>
          <option value="Approved">Approved</option>
          <option value="Rejected">Rejected</option>
        </select>
        <input id="subQ" class="w-full sm:w-56 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm font-semibold" placeholder="Search name/email…">
        <button id="btnReloadSubs" class="w-full sm:w-auto rounded-md bg-slate-900 hover:bg-black text-white px-4 py-2 text-sm font-semibold">Reload</button>
      </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-900/40 text-slate-600 dark:text-slate-300">
          <tr>
            <th class="px-4 py-3 text-left font-black">Submitted</th>
            <th class="px-4 py-3 text-left font-black">Operator</th>
            <th class="px-4 py-3 text-left font-black">Type</th>
            <th class="px-4 py-3 text-left font-black">Contact</th>
            <th class="px-4 py-3 text-left font-black">Status</th>
            <th class="px-4 py-3 text-left font-black">Action</th>
          </tr>
        </thead>
        <tbody id="submissionsTbody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800"></tbody>
      </table>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <?php if (has_permission('reports.export')): ?>
      <?php tmm_render_export_toolbar([
        [
          'href' => $rootUrl . '/admin/api/module1/export_operators_csv.php?' . http_build_query(['q' => $q, 'operator_type' => $type, 'status' => $status]),
          'label' => 'CSV',
          'icon' => 'download'
        ],
        [
          'href' => $rootUrl . '/admin/api/module1/export_operators_csv.php?' . http_build_query(['q' => $q, 'operator_type' => $type, 'status' => $status, 'format' => 'excel']),
          'label' => 'Excel',
          'icon' => 'file-spreadsheet'
        ]
      ]); ?>
    <?php endif; ?>
    <form class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" method="GET">
      <input type="hidden" name="page" value="puv-database/operator-encoding">
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
        <div class="relative w-full sm:w-56">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Draft','Incomplete','Returned','Active','Inactive'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
        <button class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="filter" class="w-4 h-4"></i>
          Apply
        </button>
        <a href="?page=puv-database/operator-encoding" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
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
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Source</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Encoded By</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Encoded At</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Contact</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Status</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
              <?php
                $rid = (int)($row['id'] ?? 0);
                $isHighlight = $highlightId > 0 && $highlightId === $rid;
                $stRaw = (string)($row['workflow_status'] ?? '');
                $st = $stRaw === 'Rejected' ? 'Returned' : $stRaw;
                $badge = match($st) {
                  'Active' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  'Pending Validation' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                  'Incomplete' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400',
                  'Draft' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400',
                  'Returned' => 'bg-orange-100 text-orange-700 ring-orange-600/20 dark:bg-orange-900/30 dark:text-orange-400 dark:ring-orange-500/20',
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
              <?php
                $portalUserId = (int)($row['portal_user_id'] ?? 0);
                $submittedBy = trim((string)($row['submitted_by_name'] ?? ''));
                $submittedAt = trim((string)($row['submitted_at'] ?? ''));
                $sourceLabel = $portalUserId > 0 ? 'Operator Portal' : ($submittedBy !== '' ? 'Walk-in' : 'Unknown');
                $whereLabel = $portalUserId > 0 ? 'Operator Portal' : ($submittedBy !== '' ? 'Admin Dashboard' : '-');
                $whenLabel = $submittedAt !== '' ? $submittedAt : (string)($row['created_at'] ?? '');
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group <?php echo $isHighlight ? 'bg-emerald-50/70 dark:bg-emerald-900/15 ring-1 ring-inset ring-emerald-200/70 dark:ring-emerald-900/30' : ''; ?>" <?php echo $isHighlight ? 'id="op-row-highlight"' : ''; ?>>
                <td class="py-4 px-6">
                  <div class="font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($row['display_name'] ?? '')); ?></div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ID: <?php echo (int)$rid; ?></div>
                </td>
                <td class="py-4 px-4">
                  <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset <?php echo $sourceLabel === 'Operator Portal' ? 'bg-indigo-100 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-900/30 dark:text-indigo-400 dark:ring-indigo-500/20' : ($sourceLabel === 'Walk-in' ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20' : 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'); ?>">
                    <?php echo htmlspecialchars($sourceLabel); ?>
                  </span>
                  <div class="mt-1 text-xs text-slate-500 dark:text-slate-400 font-semibold">Type: <?php echo htmlspecialchars((string)($row['operator_type'] ?? '')); ?></div>
                </td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">
                  <?php echo htmlspecialchars($submittedBy !== '' ? $submittedBy : '-'); ?>
                </td>
                <td class="py-4 px-4">
                  <div class="text-sm font-bold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($whereLabel); ?></div>
                  <div class="mt-1 text-xs text-slate-500 dark:text-slate-400 font-semibold"><?php echo htmlspecialchars($whenLabel !== '' ? $whenLabel : '-'); ?></div>
                </td>
                <td class="py-4 px-4 text-slate-600 dark:text-slate-300 font-medium hidden md:table-cell">
                  <?php echo htmlspecialchars($displayContact); ?>
                </td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
                </td>
                <td class="py-4 px-4 text-right">
                  <div class="flex items-center justify-end gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                    <button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" data-op-view="1" data-operator-id="<?php echo (int)$rid; ?>" data-operator-name="<?php echo htmlspecialchars((string)($row['display_name'] ?? ''), ENT_QUOTES); ?>" title="View Operator">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <button type="button"
                      class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all"
                      data-op-edit="1"
                      data-operator-id="<?php echo (int)$rid; ?>"
                      data-operator-name="<?php echo htmlspecialchars((string)($row['display_name'] ?? ''), ENT_QUOTES); ?>"
                      data-operator-type="<?php echo htmlspecialchars((string)($row['operator_type'] ?? 'Individual'), ENT_QUOTES); ?>"
                      data-operator-address="<?php echo htmlspecialchars((string)($row['address'] ?? ''), ENT_QUOTES); ?>"
                      data-operator-contact="<?php echo htmlspecialchars((string)($row['contact_no'] ?? ''), ENT_QUOTES); ?>"
                      data-operator-email="<?php echo htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES); ?>"
                      title="Edit Operator">
                      <i data-lucide="pencil" class="w-4 h-4"></i>
                    </button>
                    <?php if (has_permission('module1.write')): ?>
                      <a href="?page=module1/submodule3&review_operator_id=<?php echo (int)$rid; ?>" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all inline-flex items-center justify-center" title="Validate Documents">
                        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                      </a>
                    <?php endif; ?>
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
      <div id="modalOpBody" class="p-6 max-h-[80vh] overflow-y-auto"></div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const canWrite = <?php echo json_encode(has_any_permission(['module1.write','module1.vehicles.write'])); ?>;
    const canVerify = <?php echo json_encode(has_permission('module1.write')); ?>;

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
    function escAttr(v) {
      return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/\"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    }

    const subStatus = document.getElementById('subStatus');
    const subQ = document.getElementById('subQ');
    const btnReloadSubs = document.getElementById('btnReloadSubs');
    const submissionsTbody = document.getElementById('submissionsTbody');

    async function loadOperatorSubmissions() {
      if (!submissionsTbody) return;
      submissionsTbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">Loading…</td></tr>`;
      try {
        const qs = new URLSearchParams();
        qs.set('status', (subStatus && subStatus.value) ? subStatus.value : 'Submitted');
        if (subQ && subQ.value.trim() !== '') qs.set('q', subQ.value.trim());
        const res = await fetch(rootUrl + '/admin/api/module1/operator_submissions_list.php?' + qs.toString());
        const data = await res.json();
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
        const rows = Array.isArray(data.data) ? data.data : [];
        if (!rows.length) {
          submissionsTbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">No submissions found.</td></tr>`;
          return;
        }
        submissionsTbody.innerHTML = rows.map((r) => {
          const sid = r.submission_id;
          const who = (r.submitted_by_name || '').toString();
          const email = (r.email || '').toString();
          const opName = (r.registered_name || r.name || '').toString();
          const type = (r.operator_type || '').toString();
          const contact = ((r.contact_no || '') + (email ? (' • ' + email) : '')).trim();
          const st = (r.status || '').toString();
          const submittedAt = (r.submitted_at || '').toString();
          const actionHtml = st === 'Submitted'
            ? `<div class="flex gap-2">
                 <button data-sub-approve="${escAttr(sid)}" class="px-3 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Approve</button>
                 <button data-sub-reject="${escAttr(sid)}" class="px-3 py-2 rounded-md bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">Reject</button>
               </div>`
            : `<div class="text-xs font-semibold text-slate-500 dark:text-slate-400">${escAttr(r.approved_by_name || '')} ${escAttr(r.approved_at || '')}</div>`;
          return `<tr>
            <td class="px-4 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${escAttr(submittedAt)}</td>
            <td class="px-4 py-3">
              <div class="font-bold text-slate-900 dark:text-white">${escAttr(opName || who || 'Operator')}</div>
              <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Submitted by ${escAttr(who || 'Operator')}</div>
            </td>
            <td class="px-4 py-3 text-xs font-semibold">${escAttr(type)}</td>
            <td class="px-4 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${escAttr(contact)}</td>
            <td class="px-4 py-3 text-xs font-black">${escAttr(st)}</td>
            <td class="px-4 py-3">${actionHtml}</td>
          </tr>`;
        }).join('');

        submissionsTbody.querySelectorAll('[data-sub-approve]').forEach((b) => {
          b.addEventListener('click', () => reviewOperatorSubmission(b.getAttribute('data-sub-approve'), 'approve'));
        });
        submissionsTbody.querySelectorAll('[data-sub-reject]').forEach((b) => {
          b.addEventListener('click', () => reviewOperatorSubmission(b.getAttribute('data-sub-reject'), 'reject'));
        });
      } catch (e) {
        submissionsTbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-rose-600 font-semibold">${escAttr(e.message || 'Failed')}</td></tr>`;
      }
    }

    async function reviewOperatorSubmission(submissionId, decision) {
      if (!submissionId) return;
      const remarks = prompt((decision === 'approve' ? 'Approval remarks (optional):' : 'Rejection reason:') , '');
      if (decision === 'reject' && (!remarks || !remarks.trim())) { showToast('Rejection reason is required.', 'error'); return; }
      const fd = new FormData();
      fd.append('submission_id', String(submissionId));
      fd.append('decision', String(decision));
      fd.append('remarks', String(remarks || ''));
      try {
        const res = await fetch(rootUrl + '/admin/api/module1/operator_submissions_review.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'review_failed');
        showToast(decision === 'approve' ? 'Approved.' : 'Rejected.');
        loadOperatorSubmissions();
      } catch (e) {
        showToast(e.message || 'Failed', 'error');
      }
    }

    if (btnReloadSubs) btnReloadSubs.addEventListener('click', loadOperatorSubmissions);
    if (subStatus) subStatus.addEventListener('change', loadOperatorSubmissions);
    if (subQ) subQ.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); loadOperatorSubmissions(); } });
    loadOperatorSubmissions();

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
            <input type="hidden" name="assisted" value="1">
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
                <input name="contact_no" type="tel" inputmode="numeric" minlength="7" maxlength="20" pattern="^[0-9]{7,20}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 09171234567">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Email</label>
                <input name="email" type="email" maxlength="120" pattern="^(?!.*\\.\\.)[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[A-Za-z]{2,}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., juan.delacruz@email.com">
              </div>
            </div>

            <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 p-4">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Note</div>
              <div class="text-sm text-slate-700 dark:text-slate-200 mt-1">Assisted encoding is for walk-ins without device access. This creates a Draft operator record; upload/validate documents in the Documents screen.</div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
              <button type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold" data-op-cancel="1">Cancel</button>
              <button id="btnSaveOperator" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
            </div>
          </form>
        `, 'Assisted Operator Encoding (Walk-in)');

        const cancel = body.querySelector('[data-op-cancel="1"]');
        if (cancel) cancel.addEventListener('click', closeModal);

        const form = document.getElementById('formAddOperator');
        const btn = document.getElementById('btnSaveOperator');
        if (!form || !btn) return;
        const contactInput = form.querySelector('input[name="contact_no"]');
        const digitsOnly = (v) => (v || '').toString().replace(/\D+/g, '');
        if (contactInput) {
          contactInput.addEventListener('input', () => { contactInput.value = digitsOnly(contactInput.value).slice(0, 20); });
          contactInput.addEventListener('blur', () => { contactInput.value = digitsOnly(contactInput.value).slice(0, 20); });
        }

        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          if (!form.checkValidity()) { form.reportValidity(); return; }

          const orig = btn.textContent;
          btn.disabled = true;
          btn.textContent = 'Saving...';

          try {
            const fd = new FormData(form);
            const saveFd = new FormData();
            ['operator_type','name','address','contact_no','email'].forEach((k) => saveFd.append(k, fd.get(k) || ''));

            const res = await fetch(rootUrl + '/admin/api/module1/save_operator.php', { method: 'POST', body: saveFd });
            const data = await res.json();
            if (!data || !data.ok || !data.operator_id) throw new Error((data && data.error) ? data.error : 'save_failed');

            const operatorId = Number(data.operator_id);
            showToast('Operator saved as Draft.');
            const params = new URLSearchParams(window.location.search || '');
            params.set('page', 'puv-database/operator-encoding');
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

    document.querySelectorAll('[data-op-edit="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-operator-id');
        const name = btn.getAttribute('data-operator-name') || '';
        const type = btn.getAttribute('data-operator-type') || 'Individual';
        const address = btn.getAttribute('data-operator-address') || '';
        const contact = btn.getAttribute('data-operator-contact') || '';
        const email = btn.getAttribute('data-operator-email') || '';
        

        openModal(`
          <form id="formEditOperator" class="space-y-5" novalidate>
            <input type="hidden" name="operator_id" value="${String(id || '')}">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Operator Type</label>
                <select name="operator_type" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option ${type === 'Individual' ? 'selected' : ''}>Individual</option>
                  <option ${type === 'Cooperative' ? 'selected' : ''}>Cooperative</option>
                  <option ${type === 'Corporation' ? 'selected' : ''}>Corporation</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Name</label>
                <input name="name" required minlength="3" maxlength="120" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${escAttr(name)}">
              </div>
            </div>

            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Address</label>
              <input name="address" maxlength="180" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${escAttr(address)}">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contact No</label>
                <input name="contact_no" type="tel" inputmode="numeric" minlength="7" maxlength="20" pattern="^[0-9]{7,20}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 09171234567" value="${escAttr(contact)}">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Email</label>
                <input name="email" type="email" maxlength="120" pattern="^(?!.*\\.\\.)[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[A-Za-z]{2,}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., juan.delacruz@email.com" value="${escAttr(email)}">
              </div>
            </div>

            <div class="flex items-end">
              <div class="text-xs text-slate-500 dark:text-slate-400">Edits update the encoded operator details. Verification is handled via document validation.</div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
              <button type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold" data-op-cancel="1">Cancel</button>
              <button id="btnUpdateOperator" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Update</button>
            </div>
          </form>
        `, 'Edit Operator • ' + (name || ''));

        const cancel = body.querySelector('[data-op-cancel="1"]');
        if (cancel) cancel.addEventListener('click', closeModal);

        const form = document.getElementById('formEditOperator');
        const btnSubmit = document.getElementById('btnUpdateOperator');
        if (!form || !btnSubmit) return;
        const contactInput = form.querySelector('input[name="contact_no"]');
        const digitsOnly = (v) => (v || '').toString().replace(/\D+/g, '');
        if (contactInput) {
          contactInput.addEventListener('input', () => { contactInput.value = digitsOnly(contactInput.value).slice(0, 20); });
          contactInput.addEventListener('blur', () => { contactInput.value = digitsOnly(contactInput.value).slice(0, 20); });
        }

        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          if (!form.checkValidity()) { form.reportValidity(); return; }
          const orig = btnSubmit.textContent;
          btnSubmit.disabled = true;
          btnSubmit.textContent = 'Updating...';
          try {
            const fd = new FormData(form);
            const res = await fetch(rootUrl + '/admin/api/module1/update_operator.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'update_failed');
            showToast('Operator updated successfully.');
            const params = new URLSearchParams(window.location.search || '');
            params.set('page', 'puv-database/operator-encoding');
            params.set('highlight_operator_id', String(id || ''));
            window.location.search = params.toString();
          } catch (err) {
            showToast(err.message || 'Failed', 'error');
            btnSubmit.disabled = false;
            btnSubmit.textContent = orig;
          }
        });
      });
    });

    const highlight = document.getElementById('op-row-highlight');
    if (highlight) {
      setTimeout(() => { highlight.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 300);
    }
  })();
</script>
