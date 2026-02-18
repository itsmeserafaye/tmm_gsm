<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module5.manage_terminal', 'module5.parking_fees']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$canManage = has_permission('module5.manage_terminal');

$qFilter = trim((string)($_GET['q'] ?? ''));

$statParkingAreas = (int)($db->query("SELECT COUNT(*) AS c FROM terminals WHERE type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingSlotsFree = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Free' AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingSlotsOccupied = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Occupied' AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingPaymentsToday = (int)($db->query("SELECT COUNT(*) AS c FROM parking_payments pp JOIN parking_slots ps ON ps.slot_id=pp.slot_id JOIN terminals t ON t.id=ps.terminal_id WHERE DATE(pp.paid_at)=CURDATE() AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);

$parkingRows = [];
// Discover possible owner/operator columns
$termCols = [];
$colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminals'");
if ($colRes) while ($c = $colRes->fetch_assoc()) $termCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
$ownerCol = isset($termCols['owner_name']) ? 'owner_name' : (isset($termCols['owner']) ? 'owner' : (isset($termCols['owned_by']) ? 'owned_by' : ''));
$operatorCol = isset($termCols['operator_name']) ? 'operator_name' : (isset($termCols['operator']) ? 'operator' : (isset($termCols['managed_by']) ? 'managed_by' : ''));
// Discover permit columns
$permCols = [];
$permColRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits'");
if ($permColRes) while ($c = $permColRes->fetch_assoc()) $permCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
$permStatusCol = isset($permCols['status']) ? 'status' : '';
$permTypeCol = isset($permCols['doc_type']) ? 'doc_type' : (isset($permCols['document_type']) ? 'document_type' : (isset($permCols['type']) ? 'type' : ''));
$permIssueCol = isset($permCols['issue_date']) ? 'issue_date' : (isset($permCols['issued_at']) ? 'issued_at' : (isset($permCols['start_date']) ? 'start_date' : ''));
$permExpiryCol = isset($permCols['expiry_date']) ? 'expiry_date' : (isset($permCols['expires_at']) ? 'expires_at' : (isset($permCols['valid_until']) ? 'valid_until' : ''));
$permCreatedCol = isset($permCols['created_at']) ? 'created_at' : '';
$orderParts = [];
if ($permExpiryCol !== '') $orderParts[] = "p.$permExpiryCol";
if ($permIssueCol !== '') $orderParts[] = "p.$permIssueCol";
if ($permCreatedCol !== '') $orderParts[] = "p.$permCreatedCol";
$permOrderExpr = $orderParts ? ('COALESCE(' . implode(',', $orderParts) . ')') : '1';
$ownerExpr = $ownerCol !== '' ? "t.$ownerCol" : "NULL";
$operatorExpr = $operatorCol !== '' ? "t.$operatorCol" : "NULL";
$permTypeExpr = $permTypeCol !== '' ? "(SELECT p.$permTypeCol FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1)" : "NULL";
$permStatusExpr = $permStatusCol !== '' ? "(SELECT p.$permStatusCol FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1)" : "NULL";
$permIssueExpr = $permIssueCol !== '' ? "(SELECT p.$permIssueCol FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1)" : "NULL";
$permExpiryExpr = $permExpiryCol !== '' ? "(SELECT p.$permExpiryCol FROM terminal_permits p WHERE p.terminal_id=t.id ORDER BY $permOrderExpr DESC LIMIT 1)" : "NULL";
$where = "t.type='Parking'";
$params = [];
$types = '';
if ($qFilter !== '') {
  $where .= " AND (t.name LIKE ? OR COALESCE(t.location,'') LIKE ? OR COALESCE(t.address,'') LIKE ?)";
  $types .= 'sss';
  $like = '%' . $qFilter . '%';
  $params = [$like, $like, $like];
}
$sql = "SELECT t.id, t.name, t.location, t.address, t.capacity,
               $ownerExpr AS owner_name,
               $operatorExpr AS operator_name,
               $permTypeExpr AS permit_type,
               $permStatusExpr AS permit_status,
               $permIssueExpr AS permit_issue_date,
               $permExpiryExpr AS permit_expiry_date
        FROM terminals t
        WHERE $where
        ORDER BY t.name ASC LIMIT 500";
$resP = null;
if ($types !== '') {
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resP = $stmt->get_result();
  }
} else {
  $resP = $db->query($sql);
}
if ($resP) while ($r = $resP->fetch_assoc()) $parkingRows[] = $r;

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Parking</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage parking areas, slots, and payments.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=parking/slots-payments" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="layout-grid" class="w-4 h-4"></i>
        Slots & Payments
      </a>
      <a href="?page=module5/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Terminals
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Parking Areas</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingAreas; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Free Slots</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingSlotsFree; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Occupied Slots</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingSlotsOccupied; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Payments Today</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingPaymentsToday; ?></div>
    </div>
  </div>

  <?php if ($canManage): ?>
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
        <div class="flex items-center gap-3">
          <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
            <i data-lucide="plus" class="w-5 h-5"></i>
          </div>
          <h2 class="text-base font-bold text-slate-900 dark:text-white">Create Parking Area</h2>
        </div>
      </div>
      <div class="p-6">
        <form id="formParking" class="grid grid-cols-1 md:grid-cols-12 gap-4" novalidate>
          <input type="hidden" name="type" value="Parking">
          <div class="md:col-span-3">
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Name</label>
            <input name="name" required minlength="3" maxlength="80" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., MCU Parking">
          </div>
          <div class="md:col-span-5">
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Location</label>
            <input name="location" required maxlength="120" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Caloocan City">
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Address</label>
            <input name="address" maxlength="180" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., EDSA, Monumento">
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Capacity</label>
            <input name="capacity" type="number" min="0" max="5000" step="1" value="0" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 120">
          </div>
          <div class="md:col-span-12">
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">MOA / Legal Permit (PDF/JPG/PNG)</label>
            <input name="permit_file" type="file" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
            <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Required on create to ensure legal compliance.</div>
          </div>
          <div class="md:col-span-12 flex items-center justify-end gap-2">
            <button id="btnSaveParking" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
      <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
        <input type="hidden" name="page" value="parking/list">
        <div class="md:col-span-8">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Search</label>
          <div class="relative">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <input name="q" value="<?php echo htmlspecialchars($qFilter); ?>" class="w-full pl-9 pr-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Parking name / location / address">
          </div>
        </div>
        <div class="md:col-span-4 flex items-center gap-2">
          <button class="flex-1 px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold transition-colors shadow-sm">Apply</button>
          <a href="?page=parking/list" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-colors hover:bg-slate-50 dark:hover:bg-slate-700" title="Reset">
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
          </a>
          <?php if (has_permission('reports.export')): ?>
            <?php
              $qs = http_build_query(['type' => 'Parking', 'q' => $qFilter]);
              $printUrl = $rootUrl . '/admin/api/module5/print_terminals.php?' . $qs;
            ?>
            <a href="<?php echo htmlspecialchars($printUrl); ?>" target="_blank" rel="noopener" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-colors hover:bg-slate-50 dark:hover:bg-slate-700" title="Print Report" data-print-url="<?php echo htmlspecialchars($printUrl); ?>" data-report-name="Parking List Report">
              <i data-lucide="printer" class="w-4 h-4"></i>
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Name</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Location</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Capacity</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800" id="parkingBody">
          <?php if ($parkingRows): ?>
            <?php foreach ($parkingRows as $t): ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-6 font-black text-slate-900 dark:text-white">
                  <?php echo htmlspecialchars((string)($t['name'] ?? '')); ?>
                  <?php
                    $hasPermitLine = trim((string)($t['permit_type'] ?? '')) !== '' || trim((string)($t['permit_status'] ?? '')) !== '' || trim((string)($t['permit_issue_date'] ?? '')) !== '' || trim((string)($t['permit_expiry_date'] ?? '')) !== '';
                  ?>
                  <span class="ml-2 inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-black <?php echo $hasPermitLine ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/20 dark:text-rose-300'; ?>">
                    <?php echo $hasPermitLine ? 'Permit on file' : 'No permit'; ?>
                  </span>
                  <?php if (trim((string)($t['owner_name'] ?? '')) !== '' || trim((string)($t['operator_name'] ?? '')) !== '' || $hasPermitLine): ?>
                  <div class="mt-1 space-y-0.5">
                    <?php if (trim((string)($t['owner_name'] ?? '')) !== ''): ?>
                      <div class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Owner: <span class="font-bold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars((string)$t['owner_name']); ?></span></div>
                    <?php endif; ?>
                    <?php if (trim((string)($t['operator_name'] ?? '')) !== ''): ?>
                      <div class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Operator: <span class="font-bold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars((string)$t['operator_name']); ?></span></div>
                    <?php endif; ?>
                    <?php if (trim((string)($t['permit_type'] ?? '')) !== '' || trim((string)($t['permit_status'] ?? '')) !== ''): ?>
                      <div class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Permit: <span class="font-bold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars(trim(((string)($t['permit_type'] ?? '')) . (trim((string)($t['permit_status'] ?? '')) !== '' ? (' • ' . (string)$t['permit_status']) : ''))); ?></span></div>
                    <?php endif; ?>
                    <?php if (trim((string)($t['permit_issue_date'] ?? '')) !== '' || trim((string)($t['permit_expiry_date'] ?? '')) !== ''): ?>
                      <div class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Validity: <span class="font-bold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars(trim(((string)($t['permit_issue_date'] ?? '')) . ((string)($t['permit_issue_date'] ?? '') !== '' || (string)($t['permit_expiry_date'] ?? '') !== '' ? ' → ' : '') . ((string)($t['permit_expiry_date'] ?? '')))); ?></span></div>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars((string)($t['location'] ?? ($t['address'] ?? ''))); ?></td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold"><?php echo (int)($t['capacity'] ?? 0); ?></td>
                <td class="py-4 px-4 text-right">
                  <a title="Slots" aria-label="Slots" href="?page=parking/slots-payments&<?php echo http_build_query(['terminal_id'=>(int)($t['id'] ?? 0),'tab'=>'slots']); ?>" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors mr-2">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    <span class="sr-only">Slots</span>
                  </a>
                  <a title="Payments" aria-label="Payments" href="?page=parking/slots-payments&<?php echo http_build_query(['terminal_id'=>(int)($t['id'] ?? 0),'tab'=>'payments']); ?>" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                    <span class="sr-only">Payments</span>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4" class="py-12 text-center text-slate-500 font-medium italic">No parking areas yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function () {
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

    // const search = document.getElementById('parkingSearchTerm');
    // const body = document.getElementById('parkingBody');
    // if (search && body) {
    //   search.addEventListener('input', () => {
    //     const q = (search.value || '').toString().trim().toLowerCase();
    //     Array.from(body.querySelectorAll('tr')).forEach((tr) => {
    //       const txt = (tr.textContent || '').toLowerCase();
    //       tr.style.display = (!q || txt.includes(q)) ? '' : 'none';
    //     });
    //   });
    // }

    const form = document.getElementById('formParking');
    const btn = document.getElementById('btnSaveParking');
    if (canManage && form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/save_terminal.php', { method: 'POST', body: new FormData(form) });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && data.message) ? data.message : 'save_failed');
          showToast('Parking saved.');
          setTimeout(() => { window.location.reload(); }, 250);
        } catch (err) {
          showToast((err && err.message) ? err.message : 'Failed', 'error');
          btn.disabled = false;
          btn.textContent = original;
        }
      });
    }

    if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
  })();
</script>

