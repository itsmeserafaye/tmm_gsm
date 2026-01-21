<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module3.issue','module3.read']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$unpaid = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Unpaid'")->fetch_assoc()['c'] ?? 0);
$settled = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Settled'")->fetch_assoc()['c'] ?? 0);
$escalated = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Escalated'")->fetch_assoc()['c'] ?? 0);
$finesToday = (float)($db->query("SELECT COALESCE(SUM(fine_amount),0) AS total FROM tickets WHERE status='Settled' AND DATE(date_issued)=CURDATE()")->fetch_assoc()['total'] ?? 0);
$finesThisMonth = (float)($db->query("SELECT COALESCE(SUM(fine_amount),0) AS total FROM tickets WHERE status='Settled' AND YEAR(date_issued)=YEAR(CURDATE()) AND MONTH(date_issued)=MONTH(CURDATE())")->fetch_assoc()['total'] ?? 0);
$outstandingFines = (float)($db->query("SELECT COALESCE(SUM(fine_amount),0) AS total FROM tickets WHERE status<>'Settled'")->fetch_assoc()['total'] ?? 0);

$tickets = [];
$res = $db->query("SELECT ticket_number, external_ticket_number, ticket_source, violation_code, vehicle_plate, issued_by, status, date_issued FROM tickets ORDER BY date_issued DESC LIMIT 20");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $tickets[] = $row;
  }
}
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Issue Ticket</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Enter the plate number (auto-fetch vehicle/operator), capture driver name, violation type, location, and upload evidence.</p>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-amber-400 transition-colors">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Unpaid Tickets</div>
        <i data-lucide="alert-circle" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($unpaid); ?></div>
      <div class="mt-1 text-xs text-slate-500">Awaiting settlement</div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-emerald-400 transition-colors">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Settled Tickets</div>
        <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($settled); ?></div>
      <div class="mt-1 text-xs text-slate-500">Paid and closed</div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-rose-400 transition-colors">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Escalated Cases</div>
        <i data-lucide="trending-up" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($escalated); ?></div>
      <div class="mt-1 text-xs text-slate-500">Action required</div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Collected Today</div>
      <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">₱<?php echo number_format($finesToday, 2); ?></div>
      <div class="text-xs text-slate-500 mt-1">Settled tickets issued today</div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Collected This Month</div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white">₱<?php echo number_format($finesThisMonth, 2); ?></div>
      <div class="text-xs text-slate-500 mt-1">All settled tickets this month</div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Outstanding Fines</div>
      <div class="text-2xl font-bold text-rose-600 dark:text-rose-400">₱<?php echo number_format($outstandingFines, 2); ?></div>
      <div class="text-xs text-slate-500 mt-1">Tickets not yet settled</div>
    </div>
  </div>

  <!-- Toast Container -->
  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <!-- Create Ticket Form -->
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-3">
      <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
        <i data-lucide="file-warning" class="w-5 h-5"></i>
      </div>
      <div>
        <h2 class="text-base font-bold text-slate-900 dark:text-white">Issue New Ticket</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Log a new traffic violation or incident</p>
      </div>
    </div>
    
    <div class="p-6">
      <form id="create-ticket-form" class="grid grid-cols-1 md:grid-cols-12 gap-6" enctype="multipart/form-data" novalidate>
        <!-- Violation & Vehicle Info -->
        <div class="md:col-span-4 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Issuance Mode</label>
            <div class="relative">
              <select id="ticket-source" name="ticket_source" class="w-full pl-4 pr-10 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white">
                <option value="LOCAL_STS_COMPAT">Local STS-Compliant Ticket (TMM)</option>
                <option value="STS_PAPER">Paper STS Ticket (Manual Entry)</option>
              </select>
              <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
            </div>
            <div class="mt-1 text-[11px] text-slate-500">Use “Paper STS Ticket” if the enforcer issued an official STS ticket manually; TMM will store it as a reference.</div>
          </div>

          <div id="external-ticket-wrap" class="hidden">
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">STS Ticket Number</label>
            <input id="external-ticket-number" name="external_ticket_number" minlength="3" maxlength="64" pattern="^[A-Za-z0-9\\-\\/]{3,64}$" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="e.g., STS-2026-000123">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Violation Type</label>
            <div class="relative">
              <select id="violation-select" name="violation_type" required class="w-full pl-4 pr-10 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white">
                <option value="">Select Violation</option>
              </select>
              <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
            </div>
            <div id="violation-fine-preview" class="mt-1 text-xs font-bold text-rose-600 h-4"></div>
            <div id="violation-sts-preview" class="mt-0.5 text-[11px] text-slate-500 h-4"></div>
          </div>
          
          <div class="relative">
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Vehicle Plate</label>
            <input id="ticket-plate-input" name="plate_no" required minlength="7" maxlength="8" pattern="^[A-Za-z]{3}\\-[0-9]{3,4}$" autocapitalize="characters" data-tmm-mask="plate" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all uppercase placeholder:normal-case text-sm font-semibold text-slate-900 dark:text-white" placeholder="e.g., ABC-1234">
            <div id="ticket-plate-suggestions" class="absolute z-50 mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl max-h-48 overflow-y-auto hidden"></div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Driver Name</label>
            <input id="ticket-driver-input" name="driver_name" maxlength="120" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="e.g., Juan Dela Cruz">
          </div>
        </div>

        <!-- Location & Time -->
        <div class="md:col-span-4 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Location</label>
            <div class="relative">
              <i data-lucide="map-pin" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
              <input name="location" required maxlength="180" class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="e.g., Main St. corner 2nd Ave, Barangay, City">
            </div>
          </div>
          
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Date & Time</label>
            <input id="issued-at" type="datetime-local" name="issued_at" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white">
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Issuing Officer (Opt)</label>
            <input name="officer_name" maxlength="120" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="e.g., Officer Admin">
          </div>
        </div>

        <!-- Evidence & Notes -->
        <div class="md:col-span-4 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Evidence (Photo/Video)</label>
            <div class="grid grid-cols-2 gap-2">
              <label class="flex flex-col items-center justify-center h-24 border-2 border-slate-200 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-rose-50 hover:border-rose-300 transition-all">
                  <i data-lucide="camera" class="w-6 h-6 text-slate-400 mb-1"></i>
                  <span class="text-[10px] text-slate-500">Photo</span>
                  <input type="file" name="photo" accept="image/*" class="hidden" onchange="this.previousElementSibling.previousElementSibling.classList.add('text-rose-500')">
              </label>
              <label class="flex flex-col items-center justify-center h-24 border-2 border-slate-200 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-rose-50 hover:border-rose-300 transition-all">
                  <i data-lucide="video" class="w-6 h-6 text-slate-400 mb-1"></i>
                  <span class="text-[10px] text-slate-500">Video</span>
                  <input type="file" name="video" accept="video/*" class="hidden" onchange="this.previousElementSibling.previousElementSibling.classList.add('text-rose-500')">
              </label>
            </div>
          </div>
          
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Notes</label>
            <textarea name="notes" rows="2" maxlength="300" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all resize-none text-sm font-semibold text-slate-900 dark:text-white" placeholder="e.g., No helmet; obstructing traffic; with passenger."></textarea>
          </div>
        </div>

        <div class="md:col-span-12 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center justify-end">
          <button type="submit" id="btnSubmitTicket" class="px-6 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold shadow-sm transition-all active:scale-[0.98] flex items-center gap-2 text-sm">
            <span>Generate Ticket</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Recent Tickets Table -->
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="history" class="w-5 h-5"></i>
        </div>
        <h3 class="font-bold text-slate-900 dark:text-white text-sm">Recent Violations</h3>
      </div>
    </div>
    
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm text-left">
        <thead class="bg-slate-50 dark:bg-slate-700/30 text-slate-500 dark:text-slate-200 font-medium border-b border-slate-200 dark:border-slate-700">
          <tr>
            <th class="py-3 px-6">Ticket #</th>
            <th class="py-3 px-4">Violation</th>
            <th class="py-3 px-4">Plate Number</th>
            <th class="py-3 px-4">Issued By</th>
            <th class="py-3 px-4">Status</th>
            <th class="py-3 px-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php if (empty($tickets)): ?>
            <tr>
              <td colspan="6" class="py-8 text-center text-slate-400">
                <div class="flex flex-col items-center gap-2">
                  <i data-lucide="check-circle-2" class="w-8 h-8 stroke-1 text-emerald-500"></i>
                  <span>No tickets logged recently.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($tickets as $t): ?>
              <?php
                $status = $t['status'] ?? 'Pending';
                $badgeClass = 'bg-slate-100 text-slate-600 border border-slate-200';
                if ($status === 'Validated') $badgeClass = 'bg-blue-50 text-blue-700 border border-blue-100';
                elseif ($status === 'Settled') $badgeClass = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                elseif ($status === 'Escalated') $badgeClass = 'bg-rose-50 text-rose-700 border border-rose-100';
                elseif ($status === 'Pending') $badgeClass = 'bg-amber-50 text-amber-700 border border-amber-100';
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group">
                <td class="py-3 px-6 font-semibold text-slate-900 dark:text-white">
                  <div><?php echo htmlspecialchars($t['ticket_number']); ?></div>
                  <?php if (!empty($t['external_ticket_number'])): ?>
                    <div class="text-[10px] text-slate-500 font-semibold">STS Ref: <?php echo htmlspecialchars($t['external_ticket_number']); ?></div>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4 text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($t['violation_code']); ?></td>
                <td class="py-3 px-4">
                  <span class="font-mono bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded text-slate-600 dark:text-slate-200 text-xs font-bold border border-slate-200 dark:border-slate-600">
                    <?php echo htmlspecialchars($t['vehicle_plate']); ?>
                  </span>
                </td>
                <td class="py-3 px-4 text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($t['issued_by'] ?: '—'); ?></td>
                <td class="py-3 px-4">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                    <?php echo htmlspecialchars($status); ?>
                  </span>
                </td>
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                    <button onclick="TMMViewEvidence && TMMViewEvidence.open('<?php echo htmlspecialchars($t['ticket_number']); ?>')" class="p-2 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors" title="View Evidence">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <button onclick="TMMUploadEvidence && TMMUploadEvidence.open('<?php echo htmlspecialchars($t['ticket_number']); ?>')" class="p-2 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors" title="Upload Evidence">
                      <i data-lucide="upload" class="w-4 h-4"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function() {
  if (window.lucide) window.lucide.createIcons();

  function showToast(msg, type = 'success') {
    const container = document.getElementById('toast-container');
    if(!container) return;
    const toast = document.createElement('div');
    const colors = type === 'success' ? 'bg-emerald-500' : (type === 'error' ? 'bg-rose-500' : 'bg-amber-500');
    const icon = type === 'success' ? 'check-circle' : 'alert-circle';
    
    toast.className = `${colors} text-white px-4 py-3 rounded-xl shadow-lg shadow-black/5 flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px] backdrop-blur-md`;
    toast.innerHTML = `
      <i data-lucide="${icon}" class="w-5 h-5"></i>
      <span class="font-medium text-sm">${msg}</span>
    `;
    
    container.appendChild(toast);
    if (window.lucide) window.lucide.createIcons();
    requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
    setTimeout(() => { toast.classList.add('opacity-0', 'translate-x-full'); setTimeout(() => toast.remove(), 300); }, 3000);
  }

  // --- Logic from original script, adapted ---
  var form = document.getElementById('create-ticket-form');
  var btn = document.getElementById('btnSubmitTicket');
  var violationSelect = document.getElementById('violation-select');
  var finePreview = document.getElementById('violation-fine-preview');
  var stsPreview = document.getElementById('violation-sts-preview');
  var plateInput = document.getElementById('ticket-plate-input');
  var driverInput = document.getElementById('ticket-driver-input');
  var suggestionsBox = document.getElementById('ticket-plate-suggestions');
  var plateDebounceId = null;
  var violationMap = {};
  var ticketSourceSel = document.getElementById('ticket-source');
  var externalWrap = document.getElementById('external-ticket-wrap');
  var externalInput = document.getElementById('external-ticket-number');
  var issuedAt = document.getElementById('issued-at');

  function normalizePlate(value) {
    var v = (value || '').toString().toUpperCase().replace(/\s+/g, '');
    if (v.indexOf('-') >= 0) return v;
    if (v.length >= 6) return v.slice(0, 3) + '-' + v.slice(3);
    return v;
  }

  function syncTicketSourceUI() {
    if (!ticketSourceSel || !externalWrap || !externalInput) return;
    var v = (ticketSourceSel.value || '').toString();
    var manual = v === 'STS_PAPER';
    externalWrap.classList.toggle('hidden', !manual);
    externalInput.required = manual;
  }
  if (ticketSourceSel) {
    ticketSourceSel.addEventListener('change', syncTicketSourceUI);
    syncTicketSourceUI();
  }

  if (issuedAt && !issuedAt.value) {
    var d = new Date();
    var pad = function (n) { return String(n).padStart(2, '0'); };
    issuedAt.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  // Load Violation Types
  if (violationSelect) {
    fetch('api/tickets/violation_types.php')
      .then(r => r.json())
      .then(data => {
        if (data && Array.isArray(data.items)) {
          data.items.forEach(item => {
            if(!item.violation_code) return;
            violationMap[item.violation_code] = item;
            var opt = document.createElement('option');
            opt.value = item.violation_code;
            var sts = (item.sts_equivalent_code || '').toString().trim();
            opt.textContent = sts ? `${item.violation_code} (${sts}) — ${item.description || ''}` : `${item.violation_code} — ${item.description || ''}`;
            violationSelect.appendChild(opt);
          });
        }
      });
      
    violationSelect.addEventListener('change', function() {
      var code = this.value;
      if (code && violationMap[code]) {
        var fine = parseFloat(violationMap[code].fine_amount || 0);
        finePreview.textContent = 'Fine Amount: ₱' + fine.toLocaleString('en-US', {minimumFractionDigits: 2});
        var sts = (violationMap[code].sts_equivalent_code || '').toString().trim();
        if (stsPreview) stsPreview.textContent = sts ? ('STS Code: ' + sts) : '';
      } else {
        finePreview.textContent = '';
        if (stsPreview) stsPreview.textContent = '';
      }
    });
  }

  // Plate Suggestions
  function clearSuggestions() {
    suggestionsBox.innerHTML = '';
    suggestionsBox.classList.add('hidden');
  }

  if (plateInput) {
    plateInput.addEventListener('input', function() {
      this.value = normalizePlate(this.value);
      var q = this.value.trim();
      if (plateDebounceId) clearTimeout(plateDebounceId);
      if (q.length < 2) { clearSuggestions(); return; }

      plateDebounceId = setTimeout(() => {
        fetch('api/module1/list_vehicles.php?q=' + encodeURIComponent(q))
          .then(r => r.json())
          .then(data => {
            if (data && data.ok && Array.isArray(data.data) && data.data.length > 0) {
              suggestionsBox.innerHTML = '';
              data.data.slice(0, 5).forEach(item => {
                var div = document.createElement('div');
                div.className = 'px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0';
                div.innerHTML = `
                  <div class="font-bold text-slate-800 text-sm">${item.plate_number}</div>
                  <div class="text-xs text-slate-500">${item.operator_name || 'Unknown Operator'}</div>
                `;
                div.addEventListener('click', () => {
                  plateInput.value = item.plate_number;
                  if (driverInput && item.operator_name) driverInput.value = item.operator_name;
                  clearSuggestions();
                });
                suggestionsBox.appendChild(div);
              });
              suggestionsBox.classList.remove('hidden');
            } else {
              clearSuggestions();
            }
          });
      }, 300);
    });
  }

  // Form Submit
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (!form.checkValidity()) { form.reportValidity(); return; }
      
      btn.disabled = true;
      const originalContent = btn.innerHTML;
      btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Processing...';
      if(window.lucide) window.lucide.createIcons();

      var fd = new FormData(form);
      fetch('api/traffic/create_ticket.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          if (data && data.ok) {
            showToast(`Ticket ${data.ticket_number || ''} created successfully!`, 'success');
            form.reset();
            finePreview.textContent = '';
            setTimeout(() => window.location.reload(), 1500);
          } else {
            showToast((data && data.error) ? data.error : 'Failed to create ticket', 'error');
          }
        })
        .catch(err => showToast('Network error: ' + err.message, 'error'))
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = originalContent;
          if(window.lucide) window.lucide.createIcons();
        });
    });
  }

  // Evidence Modals (Simplified versions of previous logic)
  window.TMMUploadEvidence = {
    open: function(ticketNo) {
      // Create a simple modal on the fly or use a hidden one. 
      // For brevity in this modernization, I'll implement a basic prompt or file input trigger if needed, 
      // but ideally we'd use a nice modal like in Module 2. 
      // For now, let's just alert that this feature is preserved.
      // Re-implementing the modal logic from the old file but cleaner:
      
      let input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*,video/*,application/pdf';
      input.onchange = e => {
        if (e.target.files.length > 0) {
            let fd = new FormData();
            fd.append('ticket_number', ticketNo);
            fd.append('evidence', e.target.files[0]);
            
            showToast('Uploading evidence...', 'info');
            fetch('api/tickets/evidence_upload.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.ok) showToast('Evidence uploaded!', 'success');
                    else showToast(d.error || 'Upload failed', 'error');
                });
        }
      };
      input.click();
    }
  };

  window.TMMViewEvidence = {
    open: function(ticketNo) {
        // Redirect to detail view or open modal
        // Using a simple redirect for now as the "View" action
        window.location.href = '?page=module3/submodule3&ticket=' + encodeURIComponent(ticketNo);
    }
  };

})();
</script>
