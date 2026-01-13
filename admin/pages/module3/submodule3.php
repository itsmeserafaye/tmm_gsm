<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Analytics, Reporting & Integration</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Comprehensive violation reports, officer performance metrics, and external system hooks.</p>
    </div>
  </div>

  <!-- Toast Container -->
  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <?php
    require_once __DIR__ . '/../../includes/db.php';
    $db = db();

    // Filters
    $period = strtolower(trim($_GET['period'] ?? ''));
    $status = trim($_GET['status'] ?? '');
    $q = trim($_GET['q'] ?? '');
    $officer_id = isset($_GET['officer_id']) ? (int)$_GET['officer_id'] : 0;

    // Build Query
    $sql = "SELECT t.ticket_number, t.violation_code, t.vehicle_plate, t.status, t.fine_amount, t.date_issued, t.issued_by, t.issued_by_badge, o.name AS officer_name, o.badge_no AS officer_badge FROM tickets t LEFT JOIN officers o ON t.officer_id = o.officer_id";
    $conds = [];
    
    if ($status !== '' && in_array($status, ['Pending','Validated','Settled','Escalated'])) { 
        $conds[] = "t.status='".$db->real_escape_string($status)."'"; 
    }
    if ($period === '30d') { $conds[] = "t.date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
    if ($period === '90d') { $conds[] = "t.date_issued >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
    if ($period === 'year') { $conds[] = "YEAR(t.date_issued) = YEAR(NOW())"; }
    
    if ($q !== '') { 
        $qv = $db->real_escape_string($q); 
        $conds[] = "(t.vehicle_plate LIKE '%$qv%' OR t.ticket_number LIKE '%$qv%')"; 
    }
    if ($officer_id > 0) { 
        $conds[] = "t.officer_id=".$officer_id; 
    }
    
    if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
    $sql .= " ORDER BY t.date_issued DESC LIMIT 100";
    
    $items = $db->query($sql);

    // Officers List for Filter
    $officers = $db->query("SELECT officer_id, name, badge_no FROM officers WHERE active_status=1 AND name <> '' AND badge_no <> '' ORDER BY name");
    
    // Officer Stats (if selected)
    $officer_stats = null; 
    $officer_breakdown = null;
    if ($officer_id > 0) {
      $officer_stats = [
        'issued' => (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE officer_id=".$officer_id)->fetch_assoc()['c'] ?? 0),
        'settled' => (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE officer_id=".$officer_id." AND status='Settled'")->fetch_assoc()['c'] ?? 0),
        'validated' => (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE officer_id=".$officer_id." AND status='Validated'")->fetch_assoc()['c'] ?? 0),
      ];
      $officer_breakdown = $db->query("SELECT violation_code, COUNT(*) AS cnt FROM tickets WHERE officer_id=".$officer_id." GROUP BY violation_code ORDER BY cnt DESC LIMIT 5");
    }

    // Notifications
    $notif_rows = $db->query("SELECT tn.*, o.name AS officer_name, o.badge_no FROM ticket_notifications tn LEFT JOIN officers o ON tn.filter_officer_id = o.officer_id ORDER BY tn.created_at DESC LIMIT 10");
  ?>

  <!-- Filter & Actions Card -->
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="sliders-horizontal" class="w-5 h-5"></i>
        </div>
        <h2 class="text-base font-bold text-slate-900 dark:text-white">Report Configuration</h2>
      </div>
      <div class="flex gap-2">
         <a href="/tmm/admin/api/tickets/export_csv.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="px-4 py-2 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors text-sm font-semibold flex items-center gap-2">
           <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> CSV
         </a>
         <a href="/tmm/admin/api/tickets/export_pdf.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="px-4 py-2 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors text-sm font-semibold flex items-center gap-2">
           <i data-lucide="file-text" class="w-4 h-4"></i> PDF
         </a>
      </div>
    </div>
    
    <div class="p-6">
      <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <input type="hidden" name="page" value="module3/submodule3">
        
        <div class="relative md:col-span-1">
          <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-9 pr-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="Search ticket, plate...">
        </div>

        <div class="md:col-span-1">
          <select name="period" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white" onchange="this.form.submit()">
            <option value="">Any Period</option>
            <option value="30d" <?php echo $period==='30d'?'selected':''; ?>>Last 30 Days</option>
            <option value="90d" <?php echo $period==='90d'?'selected':''; ?>>Last 90 Days</option>
            <option value="year" <?php echo $period==='year'?'selected':''; ?>>This Year</option>
          </select>
        </div>

        <div class="md:col-span-1">
          <select name="status" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white" onchange="this.form.submit()">
            <option value="">Any Status</option>
            <option <?php echo $status==='Pending'?'selected':''; ?>>Pending</option>
            <option <?php echo $status==='Validated'?'selected':''; ?>>Validated</option>
            <option <?php echo $status==='Settled'?'selected':''; ?>>Settled</option>
            <option <?php echo $status==='Escalated'?'selected':''; ?>>Escalated</option>
          </select>
        </div>

        <div class="md:col-span-1">
           <select name="officer_id" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white" onchange="this.form.submit()">
            <option value="0">All Officers</option>
            <?php if($officers) while($o = $officers->fetch_assoc()): ?>
              <option value="<?php echo $o['officer_id']; ?>" <?php echo $officer_id == $o['officer_id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($o['name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
      </form>

      <!-- Officer Stats Section -->
      <?php if ($officer_stats): ?>
        <div class="mt-6 pt-6 border-t border-slate-100">
          <h3 class="text-xs font-semibold text-slate-400 uppercase mb-4">Officer Performance Metrics</h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
              <div class="text-2xl font-bold text-slate-800"><?php echo $officer_stats['issued']; ?></div>
              <div class="text-[10px] text-slate-500 uppercase tracking-wide">Tickets Issued</div>
            </div>
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-100">
              <div class="text-2xl font-bold text-emerald-600"><?php echo $officer_stats['settled']; ?></div>
              <div class="text-[10px] text-emerald-500 uppercase tracking-wide">Settled Tickets</div>
            </div>
            <div class="p-4 rounded-xl bg-blue-50 border border-blue-100">
              <div class="text-2xl font-bold text-blue-600"><?php echo $officer_stats['validated']; ?></div>
              <div class="text-[10px] text-blue-500 uppercase tracking-wide">Validated Tickets</div>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
              <div class="text-2xl font-bold text-slate-800">
                <?php echo ($officer_stats['issued']>0)? round(($officer_stats['settled']/$officer_stats['issued'])*100) : 0; ?>%
              </div>
              <div class="text-[10px] text-slate-500 uppercase tracking-wide">Settlement Rate</div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Results Table -->
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm text-left">
        <thead class="bg-slate-50 dark:bg-slate-700/30 text-slate-500 dark:text-slate-200 font-medium border-b border-slate-200 dark:border-slate-700">
          <tr>
            <th class="py-3 px-6">Ticket #</th>
            <th class="py-3 px-4">Violation</th>
            <th class="py-3 px-4">Plate</th>
            <th class="py-3 px-4">Officer</th>
            <th class="py-3 px-4">Status</th>
            <th class="py-3 px-4">Fine</th>
            <th class="py-3 px-4">Date</th>
            <th class="py-3 px-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php if ($items && $items->num_rows > 0): ?>
            <?php while($r = $items->fetch_assoc()): ?>
              <?php
                 $status = $r['status'] ?? 'Pending';
                 $badgeClass = 'bg-slate-100 text-slate-600 border border-slate-200';
                 if ($status === 'Validated') $badgeClass = 'bg-blue-50 text-blue-700 border border-blue-100';
                 elseif ($status === 'Settled') $badgeClass = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                 elseif ($status === 'Escalated') $badgeClass = 'bg-rose-50 text-rose-700 border border-rose-100';
                 elseif ($status === 'Pending') $badgeClass = 'bg-amber-50 text-amber-700 border border-amber-100';
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="py-3 px-6 font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($r['ticket_number']); ?></td>
                <td class="py-3 px-4 text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($r['violation_code']); ?></td>
                <td class="py-3 px-4 font-mono text-xs text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($r['vehicle_plate']); ?></td>
                <td class="py-3 px-4 text-slate-500 dark:text-slate-400 text-xs"><?php echo htmlspecialchars($r['officer_name'] ?: ($r['issued_by'] ?: '-')); ?></td>
                <td class="py-3 px-4">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide <?php echo $badgeClass; ?>">
                    <?php echo htmlspecialchars($status); ?>
                  </span>
                </td>
                <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">₱<?php echo number_format($r['fine_amount'], 2); ?></td>
                <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400"><?php echo date('M d, Y', strtotime($r['date_issued'])); ?></td>
                <td class="py-3 px-4 text-right">
                  <button onclick="viewEvidence('<?php echo htmlspecialchars($r['ticket_number']); ?>')" class="text-blue-700 hover:text-blue-800 font-semibold text-xs hover:underline">
                    Evidence
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="py-8 text-center text-slate-400">No records found matching your filters.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Integration/Notification Triggers -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
    <h3 class="font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2 text-sm">
      <i data-lucide="bell-ring" class="w-4 h-4 text-slate-500 dark:text-slate-300"></i> System Notifications
    </h3>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Trigger manual sync/notifications to external modules.</p>
    <div class="flex flex-col sm:flex-row gap-3">
      <button onclick="notifyTarget('inspection')" class="px-4 py-2 rounded-md border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 text-sm font-semibold text-slate-700 dark:text-slate-200 transition-colors">
        Notify Inspection
      </button>
      <button onclick="notifyTarget('parking')" class="px-4 py-2 rounded-md border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 text-sm font-semibold text-slate-700 dark:text-slate-200 transition-colors">
        Notify Parking
      </button>
    </div>
    <div id="integration-result" class="mt-3 text-xs text-slate-500 dark:text-slate-400 h-4"></div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
    <h3 class="font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2 text-sm">
      <i data-lucide="history" class="w-4 h-4 text-slate-400"></i> Notification Log
    </h3>
    <div class="space-y-2 max-h-[100px] overflow-y-auto">
      <?php if ($notif_rows && $notif_rows->num_rows > 0): ?>
        <?php while($n = $notif_rows->fetch_assoc()): ?>
          <div class="flex items-center justify-between text-xs p-2 rounded-md bg-slate-50 dark:bg-slate-700/30">
            <span class="text-slate-700 dark:text-slate-200 font-semibold"><?php echo ucfirst($n['target_module']); ?> Sync</span>
            <span class="text-slate-400"><?php echo date('M d H:i', strtotime($n['created_at'])); ?></span>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="text-xs text-slate-400 italic">No recent logs.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Evidence Modal -->
<div id="evidenceModal" class="fixed inset-0 z-[60] hidden">
  <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0" id="evidenceModalBackdrop"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-3xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl transform scale-95 opacity-0 transition-all duration-300 flex flex-col max-h-[90vh] border border-slate-200 dark:border-slate-700" id="evidenceModalContent">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div>
           <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="evModalTitle">Evidence</h3>
           <p class="text-xs text-slate-500 dark:text-slate-400" id="evModalSubtitle">Loading...</p>
        </div>
        <button onclick="closeEvidenceModal()" class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-300 transition-colors">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div id="evidenceModalBody" class="p-6 overflow-y-auto bg-slate-50 dark:bg-slate-900/30 min-h-[200px]">
        <!-- Content injected via JS -->
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  if (window.lucide) window.lucide.createIcons();

  window.notifyTarget = function(target) {
    const resEl = document.getElementById('integration-result');
    resEl.textContent = 'Sending notification...';
    resEl.className = 'mt-3 text-xs text-slate-500 h-4';
    
    // Get current filters
    const params = new URLSearchParams(window.location.search);
    const fd = new FormData();
    fd.append('action', 'notify_' + target);
    fd.append('period', params.get('period') || '');
    fd.append('status', params.get('status') || '');
    fd.append('officer_id', params.get('officer_id') || '');
    fd.append('q', params.get('q') || '');

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if(d.ok) {
            resEl.textContent = `Success: ${d.count} tickets synced to ${target}.`;
            resEl.className = 'mt-3 text-xs text-emerald-600 font-medium h-4';
            setTimeout(() => window.location.reload(), 2000);
        } else {
            resEl.textContent = d.error || 'Sync failed.';
            resEl.className = 'mt-3 text-xs text-rose-600 font-medium h-4';
        }
    })
    .catch(() => {
        resEl.textContent = 'Network error.';
        resEl.className = 'mt-3 text-xs text-rose-600 font-medium h-4';
    });
  };

  // Evidence Modal Logic
  const modal = document.getElementById('evidenceModal');
  const backdrop = document.getElementById('evidenceModalBackdrop');
  const content = document.getElementById('evidenceModalContent');
  const body = document.getElementById('evidenceModalBody');
  const title = document.getElementById('evModalTitle');
  const sub = document.getElementById('evModalSubtitle');

  window.closeEvidenceModal = function() {
    backdrop.classList.add('opacity-0');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        body.innerHTML = '';
    }, 300);
  };

  window.viewEvidence = function(ticket) {
    title.textContent = `Evidence for ${ticket}`;
    sub.textContent = 'Fetching files...';
    body.innerHTML = '<div class="flex justify-center py-8"><i data-lucide="loader-2" class="w-8 h-8 animate-spin text-slate-300"></i></div>';
    if(window.lucide) window.lucide.createIcons();
    
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        content.classList.remove('scale-95', 'opacity-0');
    });

    fetch('api/tickets/get_evidence.php?ticket=' + encodeURIComponent(ticket))
        .then(r => r.json())
        .then(d => {
            if(d.ok && d.evidence && d.evidence.length > 0) {
                sub.textContent = `${d.evidence.length} file(s) found`;
                body.innerHTML = '';
                const grid = document.createElement('div');
                grid.className = 'grid grid-cols-1 sm:grid-cols-2 gap-4';
                
                d.evidence.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm';
                    
                    let media = '';
                    if(item.file_type === 'image' || item.file_type === 'photo') {
                        media = `<img src="${item.url}" class="w-full h-48 object-cover hover:scale-105 transition-transform duration-500">`;
                    } else if(item.file_type === 'video') {
                        media = `<video src="${item.url}" controls class="w-full h-48 bg-black"></video>`;
                    } else {
                        media = `<div class="h-48 flex items-center justify-center bg-slate-50 text-slate-400"><i data-lucide="file" class="w-12 h-12"></i></div>`;
                    }

                    card.innerHTML = `
                        <div class="overflow-hidden bg-slate-100 relative group">
                            ${media}
                            <a href="${item.url}" target="_blank" class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity">
                                <span class="px-4 py-2 bg-white rounded-lg text-xs font-bold text-slate-800 shadow-lg">Open Original</span>
                            </a>
                        </div>
                        <div class="p-3 border-t border-slate-100 flex justify-between items-center">
                            <span class="text-xs font-bold uppercase text-slate-500">${item.file_type}</span>
                            <span class="text-[10px] text-slate-400">${item.timestamp || ''}</span>
                        </div>
                    `;
                    grid.appendChild(card);
                });
                body.appendChild(grid);
            } else {
                sub.textContent = 'No evidence found';
                body.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                        <i data-lucide="image-off" class="w-12 h-12 mb-2 opacity-50"></i>
                        <p class="text-sm">No evidence files uploaded for this ticket.</p>
                    </div>
                `;
            }
            if(window.lucide) window.lucide.createIcons();
        })
        .catch(err => {
            sub.textContent = 'Error loading evidence';
            body.innerHTML = `<div class="p-4 text-center text-rose-500 text-sm">Failed to load evidence: ${err.message}</div>`;
        });
  };

})();
</script>
