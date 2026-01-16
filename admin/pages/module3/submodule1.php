<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Traffic Violation Monitoring (STS-Compliant)</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">STS-aligned local ticketing workflow for violation recording, ticket generation, and evidence management (not the official STS platform).</p>
    </div>
    <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-full border border-slate-200 dark:border-slate-700">
        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1.5"></span>
        Local Compliance Module
    </div>
  </div>

  <!-- Toast Container -->
  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <?php
    require_once __DIR__ . '/../../includes/db.php';
    $db = db();
    
    $tickets = [];
    $res = $db->query("SELECT ticket_number, violation_code, sts_violation_code, is_sts_violation, vehicle_plate, issued_by, status, date_issued FROM tickets ORDER BY date_issued DESC LIMIT 20");
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $tickets[] = $row;
      }
    }
  ?>

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
      <form id="create-ticket-form" class="grid grid-cols-1 md:grid-cols-12 gap-6" enctype="multipart/form-data">
        <!-- Ticket Type Selection (STS Integration) -->
        <div class="md:col-span-12">
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-3">Ticket Type & Context</label>
            <div class="flex items-center gap-4">
                <label class="relative flex items-center gap-3 p-3 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer bg-white dark:bg-slate-900/50 hover:border-blue-500 transition-all group">
                    <input type="radio" name="ticket_type" value="local" checked class="peer sr-only" onchange="toggleSTSFields(false)">
                    <span class="w-5 h-5 rounded-full border border-slate-300 dark:border-slate-600 peer-checked:border-blue-500 peer-checked:bg-blue-500 flex items-center justify-center">
                        <span class="w-2 h-2 rounded-full bg-white opacity-0 peer-checked:opacity-100 transition-opacity"></span>
                    </span>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-blue-600">LGU Ordinance</span>
                        <span class="text-xs text-slate-500">Local citation (City Traffic Code)</span>
                    </div>
                </label>
                <label class="relative flex items-center gap-3 p-3 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer bg-white dark:bg-slate-900/50 hover:border-blue-500 transition-all group">
                    <input type="radio" name="ticket_type" value="sts" class="peer sr-only" onchange="toggleSTSFields(true)">
                    <span class="w-5 h-5 rounded-full border border-slate-300 dark:border-slate-600 peer-checked:border-blue-500 peer-checked:bg-blue-500 flex items-center justify-center">
                        <span class="w-2 h-2 rounded-full bg-white opacity-0 peer-checked:opacity-100 transition-opacity"></span>
                    </span>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-blue-600">STS-Aligned Ticket (Local Mirror)</span>
                        <span class="text-xs text-slate-500">Uses the configured STS codes and fines</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- STS Specific Fields (Hidden by default) -->
        <div id="sts-fields" class="md:col-span-12 grid grid-cols-1 md:grid-cols-2 gap-6 p-4 rounded-lg bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800 hidden">
             <div class="md:col-span-2 flex items-center gap-2 mb-2 text-blue-700 dark:text-blue-400">
                <i data-lucide="info" class="w-4 h-4"></i>
                <span class="text-xs font-bold uppercase">STS Reference (Local Record Only)</span>
             </div>
             <div>
                <label class="block text-xs font-semibold text-blue-700 dark:text-blue-400 uppercase mb-1.5">STS Ticket Number</label>
                <input name="sts_ticket_no" id="sts_ticket_no" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-blue-200 dark:border-blue-800 rounded-md focus:ring-1 focus:ring-blue-500 outline-none transition-all font-mono text-sm" placeholder="e.g. MMDA-2026-8888">
             </div>
             <div>
                <label class="block text-xs font-semibold text-blue-700 dark:text-blue-400 uppercase mb-1.5">Demerit Points</label>
                <input type="number" name="demerit_points" id="demerit_points" min="0" max="20" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-blue-200 dark:border-blue-800 rounded-md focus:ring-1 focus:ring-blue-500 outline-none transition-all text-sm" placeholder="0">
             </div>
        </div>

        <!-- Violation & Vehicle Info -->
        <div class="md:col-span-4 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Violation Code</label>
            <div class="relative">
              <select id="violation-select" name="violation_code" required class="w-full pl-4 pr-10 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white">
                <option value="">Select Violation</option>
              </select>
              <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
            </div>
            <div id="violation-fine-preview" class="mt-1 text-xs font-bold text-rose-600 h-4"></div>
          </div>
          
          <div class="relative">
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Vehicle Plate</label>
            <input id="ticket-plate-input" name="plate_number" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all uppercase placeholder:normal-case text-sm font-semibold text-slate-900 dark:text-white" placeholder="e.g. ABC-1234">
            <div id="ticket-plate-suggestions" class="absolute z-50 mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl max-h-48 overflow-y-auto hidden"></div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Driver / Operator</label>
            <input id="ticket-driver-input" name="driver_name" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="Driver Name">
          </div>
        </div>

        <!-- Location & Time -->
        <div class="md:col-span-4 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Location</label>
            <div class="relative">
              <i data-lucide="map-pin" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
              <input name="location" class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="Street / Area">
            </div>
          </div>
          
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Date & Time</label>
            <input type="datetime-local" name="issued_at" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white">
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Issuing Officer (Opt)</label>
            <input name="officer_name" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="Officer Name">
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
            <textarea name="notes" rows="2" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all resize-none text-sm font-semibold text-slate-900 dark:text-white" placeholder="Additional details..."></textarea>
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
                <td class="py-3 px-6 font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($t['ticket_number']); ?></td>
                <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                  <?php if (!empty($t['is_sts_violation']) && !empty($t['sts_violation_code'])): ?>
                    <span class="font-mono font-bold"><?php echo htmlspecialchars($t['sts_violation_code']); ?></span>
                    <span class="ml-2 text-[10px] font-bold px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-100">STS-Aligned</span>
                    <span class="ml-2 text-xs text-slate-400">(<?php echo htmlspecialchars($t['violation_code']); ?>)</span>
                  <?php else: ?>
                    <?php echo htmlspecialchars($t['violation_code']); ?>
                  <?php endif; ?>
                </td>
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
  var plateInput = document.getElementById('ticket-plate-input');
  var driverInput = document.getElementById('ticket-driver-input');
  var suggestionsBox = document.getElementById('ticket-plate-suggestions');
  var plateDebounceId = null;
  var violationMap = {};

  // Load Violation Types
  if (violationSelect) {
    fetch('api/tickets/violation_types.php')
      .then(r => r.json())
      .then(data => {
        if (data && Array.isArray(data.items)) {
          data.items.forEach(item => {
            if(!item.violation_code) return;
            var selectCode = item.sts_equivalent_code ? item.sts_equivalent_code : item.violation_code;
            violationMap[selectCode] = item;
            var opt = document.createElement('option');
            opt.value = selectCode;
            var suffix = (item.sts_equivalent_code && item.violation_code && item.violation_code !== item.sts_equivalent_code) ? ` (${item.violation_code})` : '';
            opt.textContent = `${selectCode}${suffix} — ${item.description || ''}`;
            violationSelect.appendChild(opt);
          });
        }
      });
      
    violationSelect.addEventListener('change', function() {
      var code = this.value;
      if (code && violationMap[code]) {
        var fine = parseFloat(violationMap[code].fine_amount || 0);
        finePreview.textContent = 'Fine Amount: ₱' + fine.toLocaleString('en-US', {minimumFractionDigits: 2});
      } else {
        finePreview.textContent = '';
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

  // Toggle STS Fields
  window.toggleSTSFields = function(isSTS) {
      const container = document.getElementById('sts-fields');
      const stsInput = document.getElementById('sts_ticket_no');
      
      if(isSTS) {
          container.classList.remove('hidden');
          container.classList.add('grid');
          stsInput.setAttribute('required', 'required');
      } else {
          container.classList.add('hidden');
          container.classList.remove('grid');
          stsInput.removeAttribute('required');
          stsInput.value = ''; // Clear value when switching back
      }
  };

})();
</script>
