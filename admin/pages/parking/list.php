<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module5.manage_terminal', 'module5.parking_fees']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

// Auto-create terminal_contracts table if missing
$checkTable = $db->query("SHOW TABLES LIKE 'terminal_contracts'");
if ($checkTable && $checkTable->num_rows == 0) {
    $db->query("CREATE TABLE IF NOT EXISTS terminal_contracts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        terminal_id INT NOT NULL,
        owner_name VARCHAR(255) NOT NULL,
        contract_start DATE NOT NULL,
        contract_end DATE NOT NULL,
        monthly_rent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        contract_file VARCHAR(255) NULL,
        status ENUM('Active','Expired','Terminated') NOT NULL DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_terminal (terminal_id),
        KEY idx_status (status)
    )");
}

$canManage = has_permission('module5.manage_terminal');

$qFilter = trim((string)($_GET['q'] ?? ''));

$statParkingAreas = (int)($db->query("SELECT COUNT(*) AS c FROM terminals WHERE type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingSlotsFree = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Free' AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingSlotsOccupied = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Occupied' AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingPaymentsToday = (int)($db->query("SELECT COUNT(*) AS c FROM parking_payments pp JOIN parking_slots ps ON ps.slot_id=pp.slot_id JOIN terminals t ON t.id=ps.terminal_id WHERE DATE(pp.paid_at)=CURDATE() AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);

$parkingRows = [];
$sql = "SELECT t.id, t.name, t.location, t.address, t.capacity,
        (SELECT owner_name FROM terminal_contracts tc WHERE tc.terminal_id = t.id ORDER BY tc.id DESC LIMIT 1) AS owner_name
        FROM terminals t
        WHERE t.type='Parking' ";

if ($qFilter !== '') {
  $sql .= " AND (t.name LIKE ? OR COALESCE(t.location,'') LIKE ? OR COALESCE(t.address,'') LIKE ?) ";
}
$sql .= " ORDER BY t.name ASC LIMIT 500";

$stmt = $db->prepare($sql);
if ($qFilter !== '') {
    $like = '%' . $qFilter . '%';
    $stmt->bind_param('sss', $like, $like, $like);
}
$stmt->execute();
$resP = $stmt->get_result();
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
          <div class="md:col-span-12 flex items-center justify-end gap-2">
            <button id="btnSaveParking" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
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
              $qs = http_build_query([
                'type' => 'Parking',
                'q' => $qFilter,
                'owner' => $ownerFilter ?? '',
                'operator' => $operatorFilter ?? '',
                'permit_status' => $permitStatusFilter ?? '',
                'agreement_type' => $agreementTypeFilter ?? '',
                'valid_from' => $validFromFilter ?? '',
                'valid_to' => $validToFilter ?? '',
              ]);
              $printUrl = $rootUrl . '/admin/api/module5/print_terminals.php?' . $qs;
            ?>
            <a href="<?php echo htmlspecialchars($printUrl); ?>" target="_blank" rel="noopener" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-colors hover:bg-slate-50 dark:hover:bg-slate-700" title="Print Report" data-print-url="<?php echo htmlspecialchars($printUrl); ?>" data-report-name="Parking List Report">
              <i data-lucide="printer" class="w-4 h-4"></i>
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Name</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Location</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Owner</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Capacity</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800" id="parkingBody">
          <?php if ($parkingRows): ?>
            <?php foreach ($parkingRows as $t): ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-6 font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($t['name'] ?? '')); ?></td>
                <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars((string)($t['location'] ?? ($t['address'] ?? ''))); ?></td>
                <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold">
                  <?php if (!empty($t['owner_name'])): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-xs font-bold">
                      <?php echo htmlspecialchars($t['owner_name']); ?>
                    </span>
                  <?php else: ?>
                    <span class="text-slate-400 text-xs italic">--</span>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold"><?php echo (int)($t['capacity'] ?? 0); ?></td>
                <td class="py-4 px-4 text-right">
                  <button type="button" class="view-contract-details inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors mr-2" data-terminal-id="<?php echo $t['id']; ?>" title="Contract Details">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                  </button>
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
            <tr><td colspan="5" class="py-12 text-center text-slate-500 font-medium italic">No parking areas yet.</td></tr>
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

    window.showToast = function(message, type) {
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

<!-- Contract Details Modal -->
<div id="contractDetailsModal" class="fixed inset-0 z-[150] hidden">
  <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" id="contractModalBackdrop"></div>
  <div class="relative flex items-center justify-center min-h-screen p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <div>
          <h3 class="text-xl font-bold text-slate-900 dark:text-white" id="contractModalTitle">Parking Agreement</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1" id="contractModalSubtitle">Manage owner and contract details.</p>
        </div>
        <button type="button" id="contractModalClose" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 transition-colors">
          <i data-lucide="x" class="w-6 h-6"></i>
        </button>
      </div>
      
      <div class="flex-1 overflow-y-auto p-6" id="contractModalContent">
        <!-- Content injected by JS -->
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const rootUrl = <?php echo json_encode($rootUrl); ?>;
  const modal = document.getElementById('contractDetailsModal');
  const backdrop = document.getElementById('contractModalBackdrop');
  const closeBtn = document.getElementById('contractModalClose');
  const content = document.getElementById('contractModalContent');
  
  let currentTerminalId = 0;
  let currentContractData = null;

  function openModal() { modal.classList.remove('hidden'); }
  function closeModal() { modal.classList.add('hidden'); }
  
  if (backdrop) backdrop.addEventListener('click', closeModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  document.addEventListener('click', function(e) {
    if (e.target.closest('.view-contract-details')) {
      const btn = e.target.closest('.view-contract-details');
      const tid = btn.dataset.terminalId;
      if (tid) {
        currentTerminalId = tid;
        fetchContractDetails(tid);
      }
    }
  });

  async function fetchContractDetails(tid) {
    openModal();
    content.innerHTML = '<div class="flex items-center justify-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-700"></div></div>';
    
    try {
      const res = await fetch(rootUrl + `/admin/api/module5/get_contract.php?terminal_id=${tid}`);
      const json = await res.json();
      if (json.success) {
        currentContractData = json.data;
        renderViewMode();
      } else {
        content.innerHTML = `<div class="text-center py-8 text-red-500">Failed to load details: ${json.message}</div>`;
      }
    } catch (e) {
      console.error(e);
      content.innerHTML = '<div class="text-center py-8 text-red-500">Error loading details.</div>';
    }
  }

  function renderViewMode() {
    const d = currentContractData || {};
    const hasData = !!d.id;
    
    let html = `
      <div class="flex justify-end mb-4">
        <button type="button" id="btnEditContract" class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold transition-colors">
          <i data-lucide="pencil" class="w-4 h-4"></i>
          ${hasData ? 'Edit Details' : 'Add Details'}
        </button>
      </div>
    `;

    if (!hasData) {
      html += `
        <div class="text-center py-12 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-dashed border-slate-300 dark:border-slate-700">
          <p class="text-slate-500 dark:text-slate-400 font-medium">No agreement details found for this parking area.</p>
          <p class="text-sm text-slate-400 mt-1">Click "Add Details" to set up owner and contract information.</p>
        </div>
      `;
    } else {
      // Owner Info
      html += `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
          <div>
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-200 dark:border-slate-700 pb-2">Owner Information</h4>
            <div class="space-y-3">
              <div>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Name</span>
                <span class="text-base font-bold text-slate-900 dark:text-white">${d.owner_name || '-'}</span>
              </div>
              <div>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Type</span>
                <span class="text-sm text-slate-700 dark:text-slate-300">${d.owner_type || '-'}</span>
              </div>
              <div>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Contact</span>
                <span class="text-sm text-slate-700 dark:text-slate-300">${d.owner_contact || '-'}</span>
              </div>
            </div>
          </div>

          <div>
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-200 dark:border-slate-700 pb-2">Agreement Details</h4>
            <div class="space-y-3">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Type</span>
                  <span class="text-sm font-bold text-slate-900 dark:text-white">${d.agreement_type || '-'}</span>
                </div>
                <div>
                  <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Reference No.</span>
                  <span class="text-sm text-slate-700 dark:text-slate-300">${d.agreement_reference_no || '-'}</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Rent Amount</span>
                  <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">₱${Number(d.rent_amount || 0).toLocaleString()} <span class="text-xs font-normal text-slate-500">/ ${d.rent_frequency || 'Monthly'}</span></span>
                </div>
                <div>
                  <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Status</span>
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold ${d.status === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'}">${d.status || 'Active'}</span>
                </div>
              </div>
              <div>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Coverage</span>
                <div class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                  <span>${d.start_date || '?'}</span>
                  <i data-lucide="arrow-right" class="w-3 h-3 text-slate-400"></i>
                  <span>${d.end_date || '?'}</span>
                </div>
                <div class="text-xs text-slate-500 mt-1">Duration: ${d.duration_display || '-'}</div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
           <div>
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-200 dark:border-slate-700 pb-2">Permit & Legal</h4>
            <div class="space-y-3">
              <div>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Permit Type</span>
                <span class="text-sm text-slate-700 dark:text-slate-300">${d.permit_type || '-'}</span>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Permit Number</span>
                  <span class="text-sm text-slate-700 dark:text-slate-300">${d.permit_number || '-'}</span>
                </div>
                <div>
                  <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Valid Until</span>
                  <span class="text-sm text-slate-700 dark:text-slate-300">${d.permit_valid_until || '-'}</span>
                </div>
              </div>
            </div>
          </div>
          
          <div>
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-200 dark:border-slate-700 pb-2">Documents</h4>
            <div class="space-y-2">
              ${renderDocLink('MOA', d.moa_file_url)}
              ${renderDocLink('Contract', d.contract_file_url)}
              ${renderDocLink('Permit', d.permit_file_url)}
            </div>
             <div class="mt-4">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-2">Other Attachments</span>
                <div class="flex flex-wrap gap-2">
                   ${renderOtherDocs(d.other_attachments)}
                </div>
             </div>
          </div>
        </div>
        
        ${d.terms_summary ? `
        <div class="mb-8">
           <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 mb-2">Terms Summary</h4>
           <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-md text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap">${d.terms_summary}</div>
        </div>
        ` : ''}
      `;
    }

    content.innerHTML = html;
    if (window.lucide) window.lucide.createIcons();

    const editBtn = document.getElementById('btnEditContract');
    if (editBtn) editBtn.addEventListener('click', renderEditMode);
  }

  function renderDocLink(label, url) {
    if (!url) return `
      <div class="flex items-center justify-between p-2 rounded border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-sm font-medium text-slate-500">${label}</span>
        <span class="text-xs text-slate-400 italic">Not uploaded</span>
      </div>
    `;
    return `
      <a href="${rootUrl}/${url}" target="_blank" class="flex items-center justify-between p-2 rounded border border-blue-100 dark:border-blue-900 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors group">
        <span class="text-sm font-medium text-blue-700 dark:text-blue-300 flex items-center gap-2">
          <i data-lucide="file-text" class="w-4 h-4"></i> ${label}
        </span>
        <i data-lucide="external-link" class="w-4 h-4 text-blue-400 group-hover:text-blue-600"></i>
      </a>
    `;
  }

  function renderOtherDocs(json) {
    if (!json) return '<span class="text-xs text-slate-400 italic">None</span>';
    try {
      const docs = JSON.parse(json);
      if (!Array.isArray(docs) || docs.length === 0) return '<span class="text-xs text-slate-400 italic">None</span>';
      return docs.map((url, i) => `
        <a href="${rootUrl}/${url}" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600">
          <i data-lucide="paperclip" class="w-3 h-3"></i> File ${i+1}
        </a>
      `).join('');
    } catch (e) { return ''; }
  }

  function renderEditMode() {
    const d = currentContractData || {};
    
    content.innerHTML = `
      <form id="contractForm" class="space-y-6">
        <input type="hidden" name="terminal_id" value="${currentTerminalId}">
        <input type="hidden" name="contract_id" value="${d.id || ''}">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Owner Section -->
          <div class="space-y-4">
            <h4 class="font-bold text-slate-900 dark:text-white border-b pb-2">Owner Information</h4>
            <div>
              <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Owner Name <span class="text-red-500">*</span></label>
              <input type="text" name="owner_name" value="${d.owner_name || ''}" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700" required>
            </div>
            <div class="grid grid-cols-2 gap-4">
               <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Type</label>
                <select name="owner_type" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
                  ${['Person','Cooperative','Company','Government','Other'].map(o => `<option value="${o}" ${d.owner_type === o ? 'selected' : ''}>${o}</option>`).join('')}
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Contact</label>
                <input type="text" name="owner_contact" value="${d.owner_contact || ''}" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
              </div>
            </div>
          </div>

          <!-- Agreement Section -->
          <div class="space-y-4">
            <h4 class="font-bold text-slate-900 dark:text-white border-b pb-2">Agreement</h4>
             <div class="grid grid-cols-2 gap-4">
               <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Type</label>
                <select name="agreement_type" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
                  ${['MOA','Lease Contract','Rental Agreement','Other'].map(o => `<option value="${o}" ${d.agreement_type === o ? 'selected' : ''}>${o}</option>`).join('')}
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Ref No.</label>
                <input type="text" name="agreement_reference_no" value="${d.agreement_reference_no || ''}" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Rent Amount</label>
                <input type="number" step="0.01" name="rent_amount" value="${d.rent_amount || ''}" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
              </div>
              <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Frequency</label>
                <select name="rent_frequency" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
                   ${['Monthly','Weekly','Annual','One-time'].map(o => `<option value="${o}" ${d.rent_frequency === o ? 'selected' : ''}>${o}</option>`).join('')}
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
           <div class="space-y-4">
            <h4 class="font-bold text-slate-900 dark:text-white border-b pb-2">Coverage</h4>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Start Date</label>
                <input type="date" name="start_date" value="${d.start_date || ''}" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
              </div>
              <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">End Date</label>
                <input type="date" name="end_date" value="${d.end_date || ''}" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
              </div>
            </div>
             <div>
               <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Status</label>
               <select name="status" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
                  ${['Active','Expired','Expiring Soon'].map(o => `<option value="${o}" ${d.status === o ? 'selected' : ''}>${o}</option>`).join('')}
               </select>
             </div>
           </div>

           <div class="space-y-4">
            <h4 class="font-bold text-slate-900 dark:text-white border-b pb-2">Permit</h4>
            <div>
               <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Permit Type</label>
               <select name="permit_type" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
                  ${['Business Permit','Barangay Clearance','Terminal Permit','Other'].map(o => `<option value="${o}" ${d.permit_type === o ? 'selected' : ''}>${o}</option>`).join('')}
               </select>
             </div>
             <div class="grid grid-cols-2 gap-4">
               <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Permit No.</label>
                <input type="text" name="permit_number" value="${d.permit_number || ''}" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
               </div>
               <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Valid Until</label>
                <input type="date" name="permit_valid_until" value="${d.permit_valid_until || ''}" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">
               </div>
             </div>
           </div>
        </div>

        <div class="space-y-4">
          <h4 class="font-bold text-slate-900 dark:text-white border-b pb-2">Documents (Upload to replace)</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase text-slate-500 mb-1">MOA</label>
              <input type="file" name="moa_file" accept=".pdf,.jpg,.png" class="w-full text-sm">
            </div>
            <div>
              <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Contract</label>
              <input type="file" name="contract_file" accept=".pdf,.jpg,.png" class="w-full text-sm">
            </div>
            <div>
              <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Permit</label>
              <input type="file" name="permit_file" accept=".pdf,.jpg,.png" class="w-full text-sm">
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Other Attachments</label>
            <input type="file" name="other_attachments[]" multiple accept=".pdf,.jpg,.png" class="w-full text-sm">
          </div>
        </div>
        
        <div>
          <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Terms Summary</label>
          <textarea name="terms_summary" rows="3" class="w-full px-3 py-2 border rounded-md dark:bg-slate-800 dark:border-slate-700">${d.terms_summary || ''}</textarea>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t">
          <button type="button" id="btnCancelEdit" class="px-4 py-2 rounded-md border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
          <button type="submit" id="btnSaveContract" class="px-6 py-2 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-bold shadow-sm">Save Details</button>
        </div>
      </form>
    `;
    
    document.getElementById('btnCancelEdit').addEventListener('click', renderViewMode);
    document.getElementById('contractForm').addEventListener('submit', handleSave);
  }

  async function handleSave(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveContract');
    const form = e.target;
    
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    try {
      const fd = new FormData(form);
      const res = await fetch(rootUrl + '/admin/api/module5/save_contract.php', {
        method: 'POST',
        body: fd
      });
      const json = await res.json();
      
      if (json.success) {
        if (window.showToast) window.showToast('Contract saved successfully.');
        setTimeout(() => { window.location.reload(); }, 500);
      } else {
        const msg = json.message || 'Unknown error';
        if (window.showToast) window.showToast(msg, 'error');
        else alert(msg);
        btn.disabled = false;
        btn.textContent = 'Save Details';
      }
    } catch (err) {
      console.error(err);
      alert('Save failed.');
      btn.disabled = false;
      btn.textContent = 'Save Details';
    }
  }

})();
</script>
