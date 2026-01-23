<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['dashboard.view','module1.read','module2.read','module3.read','module4.read','module5.read']);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-4xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="border-b border-slate-200 dark:border-slate-700 pb-6">
    <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">TAM Survey</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Technology Acceptance Model (Perceived Usefulness + Perceived Ease of Use)</p>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
    <form id="tamForm" class="space-y-6" novalidate>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Respondent Type</label>
          <select name="respondent_type" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="" disabled selected>Select</option>
            <option>Admin</option>
            <option>Officer</option>
            <option>Operator</option>
            <option>Commuter</option>
            <option>Staff</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Module Used</label>
          <select name="module_used" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="">General</option>
            <option value="Module 1">PUV Database</option>
            <option value="Module 2">Franchise Management</option>
            <option value="Module 3">Ticketing & Treasury</option>
            <option value="Module 4">Registration & Inspection</option>
            <option value="Module 5">Terminal & Parking</option>
            <option value="Citizen">Citizen Portals</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Role</label>
          <input name="respondent_role" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="<?php echo htmlspecialchars((string)($_SESSION['role'] ?? '')); ?>">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 space-y-4">
          <div class="font-black text-slate-900 dark:text-white">Perceived Usefulness (PU)</div>
          <?php
            $pu = [
              'pu_1' => 'Using the system improves my performance.',
              'pu_2' => 'Using the system increases my productivity.',
              'pu_3' => 'Using the system enhances my effectiveness.',
              'pu_4' => 'Overall, the system is useful for my tasks.',
            ];
            foreach ($pu as $k => $q):
          ?>
            <div class="space-y-2">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($q); ?></div>
              <div class="flex gap-3">
                <?php for ($i=1;$i<=5;$i++): ?>
                  <label class="flex items-center gap-2 text-sm font-bold text-slate-600 dark:text-slate-300">
                    <input type="radio" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo $i; ?>" required>
                    <?php echo $i; ?>
                  </label>
                <?php endfor; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 space-y-4">
          <div class="font-black text-slate-900 dark:text-white">Perceived Ease of Use (PEOU)</div>
          <?php
            $peou = [
              'peou_1' => 'Learning to use the system is easy for me.',
              'peou_2' => 'It is easy for me to become skillful at using the system.',
              'peou_3' => 'I find the system easy to use.',
              'peou_4' => 'The system is clear and understandable.',
            ];
            foreach ($peou as $k => $q):
          ?>
            <div class="space-y-2">
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($q); ?></div>
              <div class="flex gap-3">
                <?php for ($i=1;$i<=5;$i++): ?>
                  <label class="flex items-center gap-2 text-sm font-bold text-slate-600 dark:text-slate-300">
                    <input type="radio" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo $i; ?>" required>
                    <?php echo $i; ?>
                  </label>
                <?php endfor; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Comments (optional)</label>
        <textarea name="comments" rows="3" maxlength="2000" class="w-full px-4 py-3 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="What should be improved?"></textarea>
      </div>

      <div class="flex items-center justify-end gap-2">
        <a href="?page=research/tam_results" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">View Results</a>
        <button id="btnTamSubmit" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Submit</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
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
    const form = document.getElementById('tamForm');
    const btn = document.getElementById('btnTamSubmit');
    if (!form || !btn) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!form.checkValidity()) { form.reportValidity(); return; }
      btn.disabled = true;
      btn.textContent = 'Submitting...';
      try {
        const fd = new FormData(form);
        const res = await fetch(rootUrl + '/admin/api/research/tam_submit.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'submit_failed');
        showToast('Submitted.');
        form.reset();
      } catch (e2) {
        showToast('Failed', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Submit';
      }
    });
  })();
</script>

