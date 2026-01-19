<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module5.parking_fees');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$prefillTerminalId = (int)($_GET['terminal_id'] ?? 0);

$terminals = [];
$resT = $db->query("SELECT id, name FROM terminals WHERE type <> 'Parking' ORDER BY name ASC LIMIT 500");
if ($resT) while ($r = $resT->fetch_assoc()) $terminals[] = $r;
if ($prefillTerminalId <= 0 && $terminals) $prefillTerminalId = (int)($terminals[0]['id'] ?? 0);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-4xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Payment</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Input plate, select a slot, generate fee, and record OR.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module5/submodule3" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="layout-grid" class="w-4 h-4"></i>
        Parking Slots
      </a>
      <a href="?page=module5/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="home" class="w-4 h-4"></i>
        Terminal List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-5">
      <form id="formPay" class="space-y-5" novalidate>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Terminal</label>
            <select id="terminalSelect" name="terminal_id" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              <option value="">Select terminal</option>
              <?php foreach ($terminals as $t): ?>
                <option value="<?php echo (int)$t['id']; ?>" <?php echo ((int)$t['id'] === $prefillTerminalId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$t['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Slot</label>
            <select id="slotSelect" name="slot_id" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              <option value="">Select slot</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Plate No</label>
            <input id="plateInput" name="plate_no" required minlength="5" maxlength="12" pattern="^[A-Za-z0-9\\-\\s]{5,12}$" autocapitalize="characters" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="e.g., ABC-1234">
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Amount</label>
            <input id="amountInput" name="amount" type="number" min="0.01" step="0.01" value="20.00" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 50.00">
          </div>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR No</label>
          <input id="orInput" name="or_no" required minlength="3" maxlength="40" pattern="^[A-Za-z0-9\\-\\/]{3,40}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="e.g., OR-2026-000123">
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="button" id="btnGenerate" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold">Generate Fee</button>
          <button id="btnPay" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const terminalSelect = document.getElementById('terminalSelect');
    const slotSelect = document.getElementById('slotSelect');
    const btnPay = document.getElementById('btnPay');
    const btnGenerate = document.getElementById('btnGenerate');
    const amountInput = document.getElementById('amountInput');
    const form = document.getElementById('formPay');
    const plateInput = document.getElementById('plateInput');
    const orInput = document.getElementById('orInput');

    function normalizePlate(value) {
      const v = (value || '').toString().toUpperCase().replace(/\s+/g, '');
      if (v.includes('-')) return v;
      if (v.length >= 6) return v.slice(0, 3) + '-' + v.slice(3);
      return v;
    }
    if (plateInput) {
      plateInput.addEventListener('input', () => { plateInput.value = normalizePlate(plateInput.value); });
      plateInput.addEventListener('blur', () => { plateInput.value = normalizePlate(plateInput.value); });
    }
    if (orInput) {
      orInput.addEventListener('input', () => { orInput.value = (orInput.value || '').toString().toUpperCase().replace(/\s+/g, ''); });
    }

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

    async function loadSlots(terminalId) {
      slotSelect.innerHTML = '<option value=\"\">Loading...</option>';
      const res = await fetch(rootUrl + '/admin/api/module5/slots_list.php?terminal_id=' + encodeURIComponent(String(terminalId)));
      const data = await res.json();
      if (!data || !data.ok) throw new Error('load_failed');
      const slots = (data.data || []).filter(s => (s.status || '') === 'Free');
      if (!slots.length) {
        slotSelect.innerHTML = '<option value=\"\">No free slots</option>';
        return;
      }
      slotSelect.innerHTML = '<option value=\"\">Select slot</option>' + slots.map(s => `<option value=\"${s.slot_id}\">${s.slot_no}</option>`).join('');
    }

    if (terminalSelect) {
      terminalSelect.addEventListener('change', () => {
        const id = terminalSelect.value;
        if (!id) {
          slotSelect.innerHTML = '<option value=\"\">Select slot</option>';
          return;
        }
        loadSlots(id).catch(() => {
          slotSelect.innerHTML = '<option value=\"\">Failed to load</option>';
        });
      });
    }

    if (terminalSelect && terminalSelect.value) {
      loadSlots(terminalSelect.value).catch(() => {
        slotSelect.innerHTML = '<option value=\"\">Failed to load</option>';
      });
    }

    if (btnGenerate && amountInput) {
      btnGenerate.addEventListener('click', () => {
        if (!amountInput.value || Number(amountInput.value) <= 0) amountInput.value = '20.00';
        showToast('Fee generated.');
      });
    }

    if (form && btnPay) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        btnPay.disabled = true;
        btnPay.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/parking_payment_record.php', { method: 'POST', body: new FormData(form) });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'save_failed');
          showToast('Payment saved.');
          setTimeout(() => { window.location.href = '?page=module5/submodule1'; }, 700);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          btnPay.disabled = false;
          btnPay.textContent = 'Save Payment';
        }
      });
    }
  })();
</script>
