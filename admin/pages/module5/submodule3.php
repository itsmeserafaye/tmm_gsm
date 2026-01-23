<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module5.manage_terminal');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$terminalId = (int)($_GET['terminal_id'] ?? 0);
$terminals = [];
$resT = $db->query("SELECT id, name FROM terminals WHERE type <> 'Parking' ORDER BY name ASC LIMIT 500");
if ($resT) while ($r = $resT->fetch_assoc()) $terminals[] = $r;

if ($terminalId <= 0 && $terminals) $terminalId = (int)($terminals[0]['id'] ?? 0);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-5xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Parking Slot Management</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">View slots and toggle status.</p>
    </div>
    <div class="flex items-center gap-3">
      <?php if (has_permission('reports.export')): ?>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module5/export_slots.php?<?php echo http_build_query(['terminal_id'=>$terminalId,'format'=>'csv']); ?>"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="download" class="w-4 h-4"></i>
          Export CSV
        </a>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module5/export_slots.php?<?php echo http_build_query(['terminal_id'=>$terminalId,'format'=>'excel']); ?>"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
          Export Excel
        </a>
      <?php endif; ?>
      <a href="?page=module5/submodule4" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="credit-card" class="w-4 h-4"></i>
        Payment
      </a>
      <a href="?page=module5/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Terminal List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-4">
      <form class="flex flex-col sm:flex-row gap-3 items-end" method="GET">
        <input type="hidden" name="page" value="module5/submodule3">
        <div class="flex-1">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Terminal</label>
          <select name="terminal_id" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <?php foreach ($terminals as $t): ?>
              <option value="<?php echo (int)$t['id']; ?>" <?php echo (int)$t['id'] === $terminalId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$t['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold">Load</button>
      </form>

      <form id="formAddSlot" class="flex flex-col sm:flex-row gap-3 items-end" novalidate>
        <input type="hidden" name="terminal_id" value="<?php echo (int)$terminalId; ?>">
        <div class="flex-1">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Slot No</label>
          <input name="slot_no" required minlength="2" maxlength="10" pattern="^[A-Za-z0-9\\-]{2,10}$" autocapitalize="characters" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="e.g., A-01">
        </div>
        <button id="btnAdd" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Add Slot</button>
      </form>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Slot</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Status</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Action</th>
          </tr>
        </thead>
        <tbody id="slotsBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <tr><td colspan="3" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const terminalId = <?php echo (int)$terminalId; ?>;

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

    async function loadSlots() {
      const body = document.getElementById('slotsBody');
      const res = await fetch(rootUrl + '/admin/api/module5/slots_list.php?terminal_id=' + encodeURIComponent(String(terminalId)));
      const data = await res.json();
      if (!data || !data.ok) throw new Error('load_failed');
      const rows = (data.data || []);
      if (!rows.length) {
        body.innerHTML = '<tr><td colspan="3" class="py-10 text-center text-slate-500 font-medium italic">No slots yet.</td></tr>';
        return;
      }
      body.innerHTML = rows.map(r => {
        const badge = r.status === 'Occupied' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700';
        return `
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
            <td class="py-4 px-6 font-black text-slate-900 dark:text-white">${(r.slot_no || '')}</td>
            <td class="py-4 px-4"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ${badge}">${r.status}</span></td>
            <td class="py-4 px-4 text-right">
              <button data-slot="${r.slot_id}" class="btnToggle px-3 py-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20">Toggle</button>
            </td>
          </tr>
        `;
      }).join('');

      Array.from(document.querySelectorAll('.btnToggle')).forEach(btn => {
        btn.addEventListener('click', async () => {
          const slotId = btn.getAttribute('data-slot');
          const fd = new FormData();
          fd.append('slot_id', slotId);
          try {
            const res2 = await fetch(rootUrl + '/admin/api/module5/slot_toggle.php', { method: 'POST', body: fd });
            const d2 = await res2.json();
            if (!d2 || !d2.ok) throw new Error((d2 && d2.error) ? d2.error : 'toggle_failed');
            showToast('Updated.');
            await loadSlots();
          } catch (e) {
            showToast(e.message || 'Failed', 'error');
          }
        });
      });
    }

    const formAdd = document.getElementById('formAddSlot');
    const btnAdd = document.getElementById('btnAdd');
    if (formAdd && btnAdd) {
      const slotInput = formAdd.querySelector('input[name="slot_no"]');
      const normalizeSlot = (value) => {
        let v = (value || '').toString().toUpperCase().trim().replace(/\s+/g, '');
        const m1 = v.match(/^([A-Z])\-?(\d{1,2})$/);
        if (m1) return m1[1] + '-' + String(m1[2]).padStart(2, '0');
        const m2 = v.match(/^([A-Z]{1,3})(\d{1,3})$/);
        if (m2 && m2[2].length <= 2) return m2[1] + '-' + String(m2[2]).padStart(2, '0');
        return v;
      };
      if (slotInput) {
        slotInput.addEventListener('input', () => { slotInput.value = normalizeSlot(slotInput.value); });
        slotInput.addEventListener('blur', () => { slotInput.value = normalizeSlot(slotInput.value); });
      }
      formAdd.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!formAdd.checkValidity()) { formAdd.reportValidity(); return; }
        btnAdd.disabled = true;
        btnAdd.textContent = 'Adding...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/slot_create.php', { method: 'POST', body: new FormData(formAdd) });
          const d = await res.json();
          if (!d || !d.ok) throw new Error((d && d.error) ? d.error : 'add_failed');
          showToast('Slot added.');
          formAdd.reset();
          await loadSlots();
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        } finally {
          btnAdd.disabled = false;
          btnAdd.textContent = 'Add Slot';
        }
      });
    }

    loadSlots().catch(() => {
      const body = document.getElementById('slotsBody');
      if (body) body.innerHTML = '<tr><td colspan="3" class="py-10 text-center text-rose-600 font-semibold">Failed to load slots.</td></tr>';
    });
  })();
</script>
