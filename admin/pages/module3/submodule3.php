<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  
  <!-- Header -->
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Analytics & Reporting</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Comprehensive view of traffic violations, STS data, and officer performance.</p>
    </div>
    <div class="flex items-center gap-2">
       <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-full border border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
          </span>
          STS-Compatible
      </div>
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
    $sql = "SELECT t.ticket_number, t.violation_code, t.sts_violation_code, t.is_sts_violation, t.sts_ticket_no, t.demerit_points, t.vehicle_plate, t.status, t.fine_amount, t.date_issued, t.issued_by, t.issued_by_badge, o.name AS officer_name, o.badge_no AS officer_badge FROM tickets t LEFT JOIN officers o ON t.officer_id = o.officer_id";
    $conds = [];
    
    if ($status !== '' && in_array($status, ['Pending','Validated','Settled','Escalated'])) { 
        $conds[] = "t.status='".$db->real_escape_string($status)."'"; 
    }
    if ($period === '30d') { $conds[] = "t.date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
    if ($period === '90d') { $conds[] = "t.date_issued >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
    if ($period === 'year') { $conds[] = "YEAR(t.date_issued) = YEAR(NOW())"; }
    
    if ($q !== '') { 
        $qv = $db->real_escape_string($q); 
        $conds[] = "(t.vehicle_plate LIKE '%$qv%' OR t.ticket_number LIKE '%$qv%' OR t.sts_ticket_no LIKE '%$qv%' OR t.sts_violation_code LIKE '%$qv%' OR t.violation_code LIKE '%$qv%')"; 
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
      $officer_breakdown = $db->query("SELECT COALESCE(NULLIF(sts_violation_code, ''), violation_code) AS violation_code, COUNT(*) AS cnt FROM tickets WHERE officer_id=".$officer_id." GROUP BY COALESCE(NULLIF(sts_violation_code, ''), violation_code) ORDER BY cnt DESC LIMIT 5");
    }
  ?>

  <!-- Main Content Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    
    <!-- Left Column: Filters & Actions -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Filter Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <h2 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-sm">
                    <i data-lucide="filter" class="w-4 h-4"></i> Filters
                </h2>
            </div>
            <div class="p-4">
                <form method="GET" class="space-y-4">
                    <input type="hidden" name="page" value="module3/submodule3">
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Search</label>
                        <div class="relative">
                            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                            <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all text-sm font-medium" placeholder="Ticket, Plate...">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Period</label>
                        <select name="period" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all text-sm font-medium" onchange="this.form.submit()">
                            <option value="">All Time</option>
                            <option value="30d" <?php echo $period==='30d'?'selected':''; ?>>Last 30 Days</option>
                            <option value="90d" <?php echo $period==='90d'?'selected':''; ?>>Last 90 Days</option>
                            <option value="year" <?php echo $period==='year'?'selected':''; ?>>This Year</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Status</label>
                        <select name="status" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all text-sm font-medium" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option <?php echo $status==='Pending'?'selected':''; ?>>Pending</option>
                            <option <?php echo $status==='Validated'?'selected':''; ?>>Validated</option>
                            <option <?php echo $status==='Settled'?'selected':''; ?>>Settled</option>
                            <option <?php echo $status==='Escalated'?'selected':''; ?>>Escalated</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Officer</label>
                        <select name="officer_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all text-sm font-medium" onchange="this.form.submit()">
                            <option value="0">All Officers</option>
                            <?php if($officers) { while($o = $officers->fetch_assoc()): ?>
                                <option value="<?php echo $o['officer_id']; ?>" <?php echo $officer_id == $o['officer_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($o['name']); ?>
                                </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Export Actions -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <h2 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-sm">
                    <i data-lucide="download" class="w-4 h-4"></i> Export Data
                </h2>
            </div>
            <div class="p-4 grid grid-cols-2 gap-3">
                 <a href="/tmm/admin/api/tickets/export_csv.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:border-emerald-500/50 transition-all group">
                     <i data-lucide="file-spreadsheet" class="w-6 h-6 text-emerald-600 mb-2 group-hover:scale-110 transition-transform"></i>
                     <span class="text-xs font-bold text-slate-600 dark:text-slate-300">CSV Report</span>
                 </a>
                 <a href="/tmm/admin/api/tickets/export_pdf.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:border-rose-500/50 transition-all group">
                     <i data-lucide="file-text" class="w-6 h-6 text-rose-600 mb-2 group-hover:scale-110 transition-transform"></i>
                     <span class="text-xs font-bold text-slate-600 dark:text-slate-300">PDF Summary</span>
                 </a>
            </div>
        </div>
        
    </div>

    <!-- Right Column: Data & Stats -->
    <div class="lg:col-span-3 space-y-6">
        
        <!-- Officer Stats Cards (Conditional) -->
        <?php if ($officer_stats): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="text-3xl font-bold text-slate-800 dark:text-white mb-1"><?php echo $officer_stats['issued']; ?></div>
                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider">Tickets Issued</div>
            </div>
            <div class="p-5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="text-3xl font-bold text-emerald-600 mb-1"><?php echo $officer_stats['settled']; ?></div>
                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider">Settled</div>
            </div>
            <div class="p-5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="text-3xl font-bold text-blue-600 mb-1"><?php echo $officer_stats['validated']; ?></div>
                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider">Validated</div>
            </div>
            <div class="p-5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="text-3xl font-bold text-slate-800 dark:text-white mb-1">
                    <?php echo ($officer_stats['issued']>0)? round(($officer_stats['settled']/$officer_stats['issued'])*100) : 0; ?>%
                </div>
                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider">Success Rate</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Data Table -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
                 <div>
                    <h2 class="font-bold text-slate-900 dark:text-white text-lg">Ticket Records</h2>
                    <p class="text-sm text-slate-500">Showing latest violations based on current filters</p>
                 </div>
                 <?php if ($items && $items->num_rows > 0): ?>
                 <div class="text-xs font-medium text-slate-400">
                    <?php echo $items->num_rows; ?> records found
                 </div>
                 <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 font-medium border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-4 px-6">Ticket Details</th>
                            <th class="py-4 px-6">Violation Info</th>
                            <th class="py-4 px-6">Status</th>
                            <th class="py-4 px-6 text-right">Fine</th>
                            <th class="py-4 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
                         <?php if ($items && $items->num_rows > 0): ?>
                            <?php while($r = $items->fetch_assoc()): ?>
                                <?php
                                    $status = $r['status'] ?? 'Pending';
                                    $statusClass = 'bg-slate-100 text-slate-600 border-slate-200';
                                    if ($status === 'Validated') $statusClass = 'bg-blue-50 text-blue-700 border-blue-100';
                                    elseif ($status === 'Settled') $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                    elseif ($status === 'Escalated') $statusClass = 'bg-rose-50 text-rose-700 border-rose-100';
                                    elseif ($status === 'Pending') $statusClass = 'bg-amber-50 text-amber-700 border-amber-100';
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group">
                                    <td class="py-4 px-6">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($r['ticket_number']); ?></span>
                                            <span class="text-xs text-slate-500 font-mono mt-0.5"><?php echo date('M d, Y H:i', strtotime($r['date_issued'])); ?></span>
                                            <div class="mt-1 flex items-center gap-1.5">
                                                 <i data-lucide="user" class="w-3 h-3 text-slate-400"></i>
                                                 <span class="text-xs text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($r['officer_name'] ?: ($r['issued_by'] ?: 'Unknown')); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-xs font-bold bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600">
                                                    <?php echo htmlspecialchars($r['vehicle_plate']); ?>
                                                </span>
                                                <?php if (!empty($r['is_sts_violation'])): ?>
                                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 border border-blue-200" title="STS Aligned">STS</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-sm text-slate-700 dark:text-slate-300 line-clamp-1" title="<?php echo htmlspecialchars($r['violation_code']); ?>">
                                                <?php echo htmlspecialchars($r['violation_code']); ?>
                                            </span>
                                            <?php if(!empty($r['sts_ticket_no'])): ?>
                                                <span class="text-xs text-slate-400">Ref: <?php echo htmlspecialchars($r['sts_ticket_no']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-right font-mono font-semibold text-slate-900 dark:text-white">
                                        ₱<?php echo number_format($r['fine_amount'], 2); ?>
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <button onclick="viewEvidence('<?php echo htmlspecialchars($r['ticket_number']); ?>')" class="text-slate-400 hover:text-blue-600 transition-colors p-2 hover:bg-blue-50 rounded-lg">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                         <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <i data-lucide="inbox" class="w-10 h-10 stroke-1 opacity-50"></i>
                                        <p>No records found matching your filters.</p>
                                    </div>
                                </td>
                            </tr>
                         <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
              if(d.success) {
                sub.textContent = 'Evidence files loaded.';
                if(d.files && d.files.length > 0) {
                    let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
                    d.files.forEach(f => {
                        const isImg = f.match(/\.(jpg|jpeg|png|gif|webp)$/i);
                        if(isImg) {
                            html += `<div class="rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700">
                                <img src="${f}" class="w-full h-auto object-cover" alt="Evidence">
                                <div class="p-2 text-xs text-slate-500 bg-white dark:bg-slate-800 break-all">${f.split('/').pop()}</div>
                            </div>`;
                        } else {
                            html += `<div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-slate-800 flex items-center gap-3">
                                <i data-lucide="file" class="w-8 h-8 text-slate-400"></i>
                                <div class="overflow-hidden">
                                    <div class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">${f.split('/').pop()}</div>
                                    <a href="${f}" target="_blank" class="text-xs text-blue-600 hover:underline">Download/View</a>
                                </div>
                            </div>`;
                        }
                    });
                    html += '</div>';
                    body.innerHTML = html;
                } else {
                    body.innerHTML = '<div class="text-center py-8 text-slate-400">No evidence files attached to this ticket.</div>';
                }
              } else {
                sub.textContent = 'Error loading evidence.';
                body.innerHTML = `<div class="text-center py-8 text-rose-500">${d.error || 'Failed to load evidence.'}</div>`;
              }
              if(window.lucide) window.lucide.createIcons();
          })
          .catch(e => {
             sub.textContent = 'Error';
             body.innerHTML = '<div class="text-center py-8 text-rose-500">Network error occurred.</div>';
          });
    };
  })();
  </script>
</div>
