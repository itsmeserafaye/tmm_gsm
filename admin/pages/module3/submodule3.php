<?php require_once __DIR__ . '/../../includes/db.php'; $db = db(); ?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Analytics, Reporting & Integration</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Dashboards and reports with synchronization hooks for MMDA STS, Parking & Terminal, and Inspection modules.</p>
  <?php
    $period = strtolower(trim($_GET['period'] ?? ''));
    $status = trim($_GET['status'] ?? '');
    $q = trim($_GET['q'] ?? '');
    $officer_id = isset($_GET['officer_id']) ? (int)$_GET['officer_id'] : 0;
    $sql = "SELECT ticket_number, violation_code, vehicle_plate, status, fine_amount, date_issued FROM tickets";
    $conds = [];
    if ($status !== '' && in_array($status, ['Pending','Validated','Settled','Escalated'])) { $conds[] = "status='".$db->real_escape_string($status)."'"; }
    if ($period === '30d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
    if ($period === '90d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
    if ($period === 'year') { $conds[] = "YEAR(date_issued) = YEAR(NOW())"; }
    if ($q !== '') { $qv = $db->real_escape_string($q); $conds[] = "(vehicle_plate LIKE '%$qv%' OR ticket_number LIKE '%$qv%')"; }
    if ($officer_id > 0) { $conds[] = "officer_id=".$officer_id; }
    if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
    $sql .= " ORDER BY date_issued DESC LIMIT 100";
    $items = $db->query($sql);
    $officers = $db->query("SELECT officer_id, name, badge_no FROM officers WHERE active_status=1 ORDER BY name");
    $officer_stats = null; $officer_breakdown = null;
    if ($officer_id > 0) {
      $officer_stats = [
        'issued' => (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE officer_id=".$officer_id)->fetch_assoc()['c'] ?? 0),
        'settled' => (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE officer_id=".$officer_id." AND status='Settled'")->fetch_assoc()['c'] ?? 0),
        'validated' => (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE officer_id=".$officer_id." AND status='Validated'")->fetch_assoc()['c'] ?? 0),
        'last_date' => ($db->query("SELECT MAX(date_issued) AS d FROM tickets WHERE officer_id=".$officer_id)->fetch_assoc()['d'] ?? null)
      ];
      $officer_breakdown = $db->query("SELECT violation_code, COUNT(*) AS cnt FROM tickets WHERE officer_id=".$officer_id." GROUP BY violation_code ORDER BY cnt DESC LIMIT 5");
    }
  ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-indigo-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="sliders-horizontal" class="w-5 h-5 text-indigo-500"></i> Reporting Filters</h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3" method="GET">
        <select name="period" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
          <option value="">Period</option><option value="30d" <?php echo $period==='30d'?'selected':''; ?>>30d</option><option value="90d" <?php echo $period==='90d'?'selected':''; ?>>90d</option><option value="year" <?php echo $period==='year'?'selected':''; ?>>Year</option>
        </select>
        <select name="status" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
          <option value="">Status</option><option <?php echo $status==='Pending'?'selected':''; ?>>Pending</option><option <?php echo $status==='Validated'?'selected':''; ?>>Validated</option><option <?php echo $status==='Settled'?'selected':''; ?>>Settled</option><option <?php echo $status==='Escalated'?'selected':''; ?>>Escalated</option>
        </select>
        <select name="officer_id" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
          <option value="">Officer</option>
          <?php if ($officers && $officers->num_rows > 0): while($o = $officers->fetch_assoc()): ?>
            <option value="<?php echo (int)$o['officer_id']; ?>" <?php echo $officer_id===(int)$o['officer_id']?'selected':''; ?>><?php echo htmlspecialchars($o['name'].' — '.$o['badge_no']); ?></option>
          <?php endwhile; endif; ?>
        </select>
        <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all" placeholder="Plate or Ticket #">
        <button type="submit" class="md:col-span-3 flex items-center justify-center gap-2 px-6 py-2.5 bg-indigo-500 hover:bg-indigo-600 text-white font-medium rounded-lg transition-colors">Generate</button>
      </form>
    </div>
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-teal-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="link" class="w-5 h-5 text-teal-500"></i> Integration Actions</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <button class="px-4 py-2 border rounded hover:bg-teal-50 dark:hover:bg-teal-900/20 transition-colors">Sync to STS</button>
        <a class="px-6 py-2.5 bg-teal-500 hover:bg-teal-600 text-white font-medium rounded-lg text-center transition-colors" target="_blank" href="/tmm/admin/api/tickets/export_csv.php?period=<?php echo urlencode($period); ?>&status=<?php echo urlencode($status); ?>&officer_id=<?php echo (int)$officer_id; ?>&q=<?php echo urlencode($q); ?>">Export CSV</a>
        <a class="px-6 py-2.5 bg-teal-500 hover:bg-teal-600 text-white font-medium rounded-lg text-center transition-colors" target="_blank" href="/tmm/admin/api/tickets/export_pdf.php?period=<?php echo urlencode($period); ?>&status=<?php echo urlencode($status); ?>&officer_id=<?php echo (int)$officer_id; ?>&q=<?php echo urlencode($q); ?>">Export PDF</a>
        <button class="px-4 py-2 border rounded hover:bg-teal-50 dark:hover:bg-teal-900/20 transition-colors">Notify Inspection</button>
        <button class="px-4 py-2 border rounded hover:bg-teal-50 dark:hover:bg-teal-900/20 transition-colors">Notify Parking</button>
      </div>
    </div>
  </div>
  <?php if ($officer_id > 0 && $officer_stats): ?>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
    <div class="p-3 border rounded ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900">
      <div class="text-sm text-slate-500">Issued</div>
      <div class="text-2xl font-bold"><?php echo (int)$officer_stats['issued']; ?></div>
    </div>
    <div class="p-3 border rounded ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900">
      <div class="text-sm text-slate-500">Settled</div>
      <div class="text-2xl font-bold"><?php echo (int)$officer_stats['settled']; ?></div>
    </div>
    <div class="p-3 border rounded ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900">
      <div class="text-sm text-slate-500">Settlement Rate</div>
      <div class="text-2xl font-bold"><?php echo ($officer_stats['issued']>0)? round(($officer_stats['settled']/$officer_stats['issued'])*100) : 0; ?>%</div>
    </div>
  </div>
  <div class="overflow-x-auto mt-4">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Violation</th>
          <th class="py-2 px-3">Count</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <?php if ($officer_breakdown && $officer_breakdown->num_rows > 0): while($b = $officer_breakdown->fetch_assoc()): ?>
        <tr>
          <td class="py-2 px-3"><?php echo htmlspecialchars($b['violation_code']); ?></td>
          <td class="py-2 px-3"><?php echo (int)$b['cnt']; ?></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="2" class="py-4 text-center text-slate-500">No data.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <div class="overflow-x-auto mt-6">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Ticket #</th>
          <th class="py-2 px-3">Violation</th>
          <th class="py-2 px-3">Plate</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3">Fine</th>
          <th class="py-2 px-3">Issued</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
        <?php if ($items && $items->num_rows > 0): while($r = $items->fetch_assoc()): ?>
        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
          <td class="py-2 px-3"><?php echo htmlspecialchars($r['ticket_number']); ?></td>
          <td class="py-2 px-3"><?php echo htmlspecialchars($r['violation_code']); ?></td>
          <td class="py-2 px-3"><?php echo htmlspecialchars($r['vehicle_plate']); ?></td>
          <td class="py-2 px-3">
            <?php $sc='bg-amber-100 text-amber-700 ring-1 ring-amber-600/20'; if($r['status']==='Validated') $sc='bg-blue-100 text-blue-700 ring-1 ring-blue-600/20'; if($r['status']==='Settled') $sc='bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/20'; if($r['status']==='Escalated') $sc='bg-red-100 text-red-700 ring-1 ring-red-600/20'; ?>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?php echo $sc; ?>"><?php echo htmlspecialchars($r['status']); ?></span>
          </td>
          <td class="py-2 px-3">₱<?php echo number_format((float)$r['fine_amount'],2); ?></td>
          <td class="py-2 px-3"><?php echo date('Y-m-d', strtotime($r['date_issued'])); ?></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="6" class="py-4 text-center text-slate-500">No records.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
