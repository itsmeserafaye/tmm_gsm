<?php require_once __DIR__ . '/../../includes/db.php'; $db = db(); ?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Violation Logging & Ticket Processing</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">On-site and automated violation recording, ticket generation, evidence attachment, and initial case creation.</p>
  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>
  <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-green-500 shadow-sm mb-6">
    <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="ticket" class="w-5 h-5 text-green-500"></i> Create Ticket</h2>
    <form id="ticketForm" class="grid grid-cols-1 md:grid-cols-3 gap-4" enctype="multipart/form-data">
      <?php $vt = $db->query("SELECT violation_code, description FROM violation_types ORDER BY violation_code"); ?>
      <select name="violation_code" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all" required>
        <option value="">Violation code</option>
        <?php while($v = $vt->fetch_assoc()): ?>
          <option value="<?php echo htmlspecialchars($v['violation_code']); ?>"><?php echo htmlspecialchars($v['violation_code'] . ' — ' . $v['description']); ?></option>
        <?php endwhile; ?>
      </select>
      <input name="vehicle_plate" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 uppercase focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all" placeholder="Vehicle plate" required>
      <input name="driver_name" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all" placeholder="Driver/Operator name">
      <input name="location" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all" placeholder="Location">
      <?php $off = $db->query("SELECT officer_id, name, badge_no FROM officers WHERE active_status=1 ORDER BY name"); ?>
      <select name="officer_id" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all" required>
        <option value="">Issued by (Officer)</option>
        <?php while($o = $off->fetch_assoc()): ?>
          <option value="<?php echo (int)$o['officer_id']; ?>"><?php echo htmlspecialchars($o['name'] . ' — ' . $o['badge_no']); ?></option>
        <?php endwhile; ?>
      </select>
      <input name="date_issued" type="datetime-local" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all">
      <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div><label class="block text-sm mb-1">Photo</label><input name="evidence" type="file" class="w-full text-sm border border-dashed border-slate-300 dark:border-slate-700 rounded p-2 bg-slate-50 dark:bg-slate-800"></div>
        <div><label class="block text-sm mb-1">Video</label><input disabled type="file" class="w-full text-sm opacity-50 border border-dashed border-slate-300 dark:border-slate-700 rounded p-2"></div>
        <div><label class="block text-sm mb-1">Notes</label><input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all" placeholder="Additional info"></div>
      </div>
      <button id="btnCreateTicket" type="submit" class="md:col-span-3 flex items-center justify-center gap-2 px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white font-medium rounded-lg w-full transition-colors shadow-sm shadow-green-500/30">Generate Ticket</button>
    </form>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Ticket #</th>
          <th class="py-2 px-3">Violation</th>
          <th class="py-2 px-3">Plate</th>
          <th class="py-2 px-3">Issued By</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
        <?php
          $res = $db->query("SELECT ticket_number, violation_code, vehicle_plate, issued_by, issued_by_badge, status FROM tickets ORDER BY date_issued DESC LIMIT 20");
          if ($res && $res->num_rows > 0):
            while ($r = $res->fetch_assoc()):
        ?>
        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
          <td class="py-2 px-3"><?php echo htmlspecialchars($r['ticket_number']); ?></td>
          <td class="py-2 px-3"><?php echo htmlspecialchars($r['violation_code']); ?></td>
          <td class="py-2 px-3"><?php echo htmlspecialchars($r['vehicle_plate']); ?></td>
          <td class="py-2 px-3"><?php echo htmlspecialchars($r['issued_by']); ?><?php echo !empty($r['issued_by_badge'])?' — '.htmlspecialchars($r['issued_by_badge']):''; ?></td>
          <td class="py-2 px-3">
            <?php $sc='bg-amber-100 text-amber-700 ring-1 ring-amber-600/20'; if($r['status']==='Validated') $sc='bg-blue-100 text-blue-700 ring-1 ring-blue-600/20'; if($r['status']==='Settled') $sc='bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/20'; if($r['status']==='Escalated') $sc='bg-red-100 text-red-700 ring-1 ring-red-600/20'; ?>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?php echo $sc; ?>"><?php echo htmlspecialchars($r['status']); ?></span>
          </td>
          <td class="py-2 px-3 space-x-2"><a class="px-2 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/30 dark:text-blue-400 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors" href="?page=module3/submodule2&ticket=<?php echo urlencode($r['ticket_number']); ?>">Open</a></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="6" class="py-4 text-center text-slate-500">No tickets yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
(function(){
  function showToast(msg, type='success'){
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const colors = type === 'success' ? 'bg-green-500' : (type === 'error' ? 'bg-red-500' : 'bg-blue-500');
    toast.className = colors + " text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px] z-50";
    toast.innerHTML = '<span class="font-medium text-sm">'+msg+'</span>';
    container.appendChild(toast);
    requestAnimationFrame(()=>toast.classList.remove('translate-y-10','opacity-0'));
    setTimeout(()=>toast.remove(),3000);
  }
  const form = document.getElementById('ticketForm');
  form?.addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = document.getElementById('btnCreateTicket');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Processing...';
    try {
      const fd = new FormData(this);
      const res = await fetch('/tmm/admin/api/tickets/create.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        const ev = fd.get('evidence');
        if (ev && typeof ev === 'object' && ev.size > 0) {
          const evfd = new FormData();
          evfd.append('ticket_number', data.ticket_number);
          evfd.append('evidence', ev);
          await fetch('/tmm/admin/api/tickets/evidence_upload.php', { method: 'POST', body: evfd });
        }
        showToast('Ticket '+data.ticket_number+' created');
        setTimeout(()=>window.location.reload(), 800);
      } else {
        showToast(data.error || 'Failed to create ticket', 'error');
      }
    } catch (err) {
      showToast('Network error', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });
})();
</script>
