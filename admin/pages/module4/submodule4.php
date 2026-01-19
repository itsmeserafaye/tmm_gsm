<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module4.inspect','module4.certify']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$scheduleId = (int)($_GET['schedule_id'] ?? 0);

$schedules = [];
$resS = $db->query("SELECT schedule_id, plate_number, status, schedule_date, scheduled_at FROM inspection_schedules WHERE status IN ('Scheduled','Rescheduled') ORDER BY COALESCE(schedule_date, scheduled_at) ASC LIMIT 500");
if ($resS) while ($r = $resS->fetch_assoc()) $schedules[] = $r;

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-5xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Conduct Inspection</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Fill the checklist, submit pass/fail result, and upload inspection photos.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module4/submodule3" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="calendar-plus" class="w-4 h-4"></i>
        Schedule Inspection
      </a>
      <a href="?page=module4/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="list" class="w-4 h-4"></i>
        Vehicle Registration List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-6">
      <form id="formConduct" class="space-y-6" enctype="multipart/form-data" novalidate>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Schedule</label>
            <select name="schedule_id" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              <option value="">Select schedule</option>
              <?php foreach ($schedules as $s): ?>
                <?php
                  $sid = (int)($s['schedule_id'] ?? 0);
                  $dt = (string)($s['schedule_date'] ?? $s['scheduled_at'] ?? '');
                  $label = 'SCH-' . $sid . ' • ' . (string)($s['plate_number'] ?? '');
                  if ($dt !== '') $label .= ' • ' . date('M d, Y H:i', strtotime($dt));
                ?>
                <option value="<?php echo $sid; ?>" <?php echo ($scheduleId > 0 && $scheduleId === $sid) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Overall Result</label>
            <select name="overall_status" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              <option value="Passed">Passed</option>
              <option value="Failed">Failed</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <?php
            $items = [
              'LIGHTS' => 'Lights & Horn',
              'BRAKES' => 'Brakes',
              'EMISSION' => 'Emission & Smoke Test',
              'TIRES' => 'Tires & Wipers',
              'INTERIOR' => 'Interior Safety',
              'DOCS' => 'Documents & Plate',
            ];
          ?>
          <?php foreach ($items as $code => $label): ?>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
              <div class="text-sm font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($label); ?></div>
              <div class="mt-2">
                <select name="items[<?php echo htmlspecialchars($code); ?>]" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="Pass">Pass</option>
                  <option value="Fail">Fail</option>
                  <option value="NA">N/A</option>
                </select>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Remarks (optional)</label>
          <textarea name="remarks" rows="3" maxlength="300" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Failed brakes; repair required before retest."></textarea>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Upload Photos (JPG/PNG)</label>
          <input name="photos[]" type="file" multiple accept=".jpg,.jpeg,.png,image/*" class="w-full text-sm">
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Photos are uploaded after the checklist is saved.</div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button id="btnSubmit" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Submit Result</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const form = document.getElementById('formConduct');
    const btn = document.getElementById('btnSubmit');

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

    if (form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        btn.disabled = true;
        btn.textContent = 'Submitting...';
        try {
          const fd = new FormData(form);
          const scheduleId = (fd.get('schedule_id') || '').toString();
          const overall = (fd.get('overall_status') || '').toString();
          const anyFail = Array.from(form.querySelectorAll('select[name^="items["]')).some((s) => (s.value || '') === 'Fail');
          const allPassOrNA = Array.from(form.querySelectorAll('select[name^="items["]')).every((s) => (s.value || '') !== 'Fail');
          if (overall === 'Passed' && anyFail) { showToast('Overall result is Passed but one or more checklist items are Fail.', 'error'); btn.disabled = false; btn.textContent = 'Submit Result'; return; }
          if (overall === 'Failed' && allPassOrNA) { showToast('Overall result is Failed but checklist items have no Fail.', 'error'); btn.disabled = false; btn.textContent = 'Submit Result'; return; }

          const res = await fetch(rootUrl + '/admin/api/module4/submit_checklist.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'submit_failed');

          const photos = form.querySelector('input[type="file"][name="photos[]"]');
          if (photos && photos.files && photos.files.length) {
            const up = new FormData();
            up.append('schedule_id', scheduleId);
            Array.from(photos.files).forEach((f) => up.append('photos[]', f));
            const res2 = await fetch(rootUrl + '/admin/api/module4/upload_inspection_photos.php', { method: 'POST', body: up });
            const data2 = await res2.json();
            if (!data2 || !data2.ok) throw new Error((data2 && data2.error) ? data2.error : 'upload_failed');
          }

          showToast('Inspection result saved.');
          setTimeout(() => { window.location.href = '?page=module4/submodule1'; }, 700);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          btn.disabled = false;
          btn.textContent = 'Submit Result';
        }
      });
    }
  })();
</script>
