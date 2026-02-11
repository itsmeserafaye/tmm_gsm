<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module4.inspect','module4.certify']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$scheduleId = (int)($_GET['schedule_id'] ?? 0);

$schedules = [];
$resS = $db->query("SELECT schedule_id, plate_number, status, schedule_date, scheduled_at FROM inspection_schedules WHERE status IN ('Scheduled','Rescheduled','Completed') ORDER BY COALESCE(schedule_date, scheduled_at) DESC LIMIT 500");
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
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">LGU operational safety checklist for monitoring and local enforcement support. This does not replace LTO registration, CMVI/PMVIC testing, or LTFRB franchise evaluation.</p>
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
            $catalog = [
              'Roadworthiness (Visual Check)' => [
                'RW_LIGHTS' => 'Head/Tail Lights, Signals, Brake Lights',
                'RW_HORN' => 'Horn / Audible Warning',
                'RW_BRAKES' => 'Brakes (Pedal Feel, Response, Leaks)',
                'RW_STEER' => 'Steering (Play, Alignment Feel)',
                'RW_SUSP' => 'Suspension (Noise, Stability)',
                'RW_TIRES' => 'Tires (Tread, Condition, Pressure)',
                'RW_WIPERS' => 'Wipers / Windshield Condition',
                'RW_MIRRORS' => 'Mirrors (Left/Right/Rear)',
                'RW_LEAKS' => 'Fluid Leaks (Oil/Coolant/Fuel)',
              ],
              'Passenger Safety' => [
                'PS_SEATS' => 'Seats Secure / No Sharp Edges',
                'PS_HANDHOLD' => 'Handholds / Grab Bars (if applicable)',
                'PS_DOORS' => 'Doors & Locks Working',
                'PS_WINDOWS' => 'Windows / Ventilation OK',
                'PS_SEATBELT' => 'Seatbelts Present (if applicable)',
              ],
              'Safety Equipment (LGU Check)' => [
                'SE_EXT' => 'Fire Extinguisher Present & Serviceable',
                'SE_EWD' => 'Early Warning Device / Warning Triangles',
                'SE_FIRSTAID' => 'First Aid Kit Present',
                'SE_REFLECT' => 'Reflectors / Visibility Markings',
              ],
              'Operational Compliance (LGU)' => [
                'LGU_SIGN' => 'Route/Line Signage Displayed',
                'LGU_BODYNO' => 'Body Number / Unit Markings Visible',
                'LGU_CAP' => 'Capacity Label / No Excess Seats',
                'LGU_CLEAN' => 'Cleanliness / Passenger Area Condition',
              ],
              'Document Presentation (For Verification Only)' => [
                'DOC_CR' => 'CR/OR-CR Presented (Ownership Proof)',
                'DOC_OR' => 'OR Presented (Registration Payment Proof)',
                'DOC_CMVI' => 'CMVI/PMVIC Certificate Presented (Roadworthiness Test)',
                'DOC_CTPL' => 'CTPL Insurance Presented (Valid Coverage)',
              ],
            ];

            $legacy = [];
            $resItems = $db->query("SELECT item_code, item_label
                                    FROM inspection_checklist_items
                                    WHERE COALESCE(item_code,'')<>'' AND COALESCE(item_label,'')<>''
                                    GROUP BY item_code, item_label
                                    ORDER BY item_label ASC
                                    LIMIT 200");
            if ($resItems) {
              while ($r = $resItems->fetch_assoc()) {
                $code = strtoupper(trim((string)($r['item_code'] ?? '')));
                $label = trim((string)($r['item_label'] ?? ''));
                if ($code !== '' && $label !== '') $legacy[$code] = $label;
              }
            }

            $flat = [];
            foreach ($catalog as $cat => $items) {
              foreach ($items as $code => $label) {
                $flat[$code] = $label;
              }
            }
            $legacyExtra = [];
            foreach ($legacy as $code => $label) {
              if (!isset($flat[$code])) $legacyExtra[$code] = $label;
            }
            if ($legacyExtra) {
              $catalog['Other / Legacy Items'] = $legacyExtra;
            }

            $existing = [];
            if ($scheduleId > 0) {
              $stmtLast = $db->prepare("SELECT result_id FROM inspection_results WHERE schedule_id=? ORDER BY submitted_at DESC, result_id DESC LIMIT 1");
              if ($stmtLast) {
                $stmtLast->bind_param('i', $scheduleId);
                $stmtLast->execute();
                $last = $stmtLast->get_result()->fetch_assoc();
                $stmtLast->close();
                $rid = (int)($last['result_id'] ?? 0);
                if ($rid > 0) {
                  $stmtIt = $db->prepare("SELECT item_code, status FROM inspection_checklist_items WHERE result_id=?");
                  if ($stmtIt) {
                    $stmtIt->bind_param('i', $rid);
                    $stmtIt->execute();
                    $resIt = $stmtIt->get_result();
                    while ($resIt && ($row = $resIt->fetch_assoc())) {
                      $c = strtoupper(trim((string)($row['item_code'] ?? '')));
                      $s = strtoupper(trim((string)($row['status'] ?? '')));
                      if ($c !== '' && $s !== '') $existing[$c] = $s;
                    }
                    $stmtIt->close();
                  }
                }
              }
            }
          ?>
          <?php foreach ($catalog as $catLabel => $items): ?>
            <div class="md:col-span-2">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2"><?php echo htmlspecialchars($catLabel); ?></div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach ($items as $code => $label): ?>
                  <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
                    <div class="text-sm font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($label); ?></div>
                    <div class="mt-2">
                      <input type="hidden" name="labels[<?php echo htmlspecialchars($code); ?>]" value="<?php echo htmlspecialchars($label); ?>">
                      <select name="items[<?php echo htmlspecialchars($code); ?>]" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                        <?php $sel = $existing[$code] ?? 'NA'; ?>
                        <option value="Pass" <?php echo $sel === 'PASS' ? 'selected' : ''; ?>>Pass</option>
                        <option value="Fail" <?php echo $sel === 'FAIL' ? 'selected' : ''; ?>>Fail</option>
                        <option value="NA" <?php echo $sel === 'NA' ? 'selected' : ''; ?>>N/A</option>
                      </select>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Remarks (optional)</label>
          <textarea name="remarks" rows="3" maxlength="255" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Failed brakes; repair required before retest."></textarea>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Upload Photos (JPG/PNG)</label>
          <input name="photos[]" type="file" multiple accept=".jpg,.jpeg,.png,image/*" class="w-full text-sm">
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Photos are uploaded after the checklist is saved.</div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 flex-wrap">
          <button type="button" id="btnViewReport" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold">View Checklist + Result</button>
          <button type="button" id="btnDownloadReport" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold">Download Checklist + Result (PDF)</button>
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
    const btnViewReport = document.getElementById('btnViewReport');
    const btnDownloadReport = document.getElementById('btnDownloadReport');
    const scheduleSelect = form ? form.querySelector('select[name="schedule_id"]') : null;

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

    function getScheduleId() {
      if (!scheduleSelect) return '';
      return (scheduleSelect.value || '').toString().trim();
    }

    function syncReportButtons() {
      const sid = getScheduleId();
      const has = !!sid;
      if (btnViewReport) btnViewReport.disabled = !has;
      if (btnDownloadReport) btnDownloadReport.disabled = !has;
    }

    if (scheduleSelect) {
      scheduleSelect.addEventListener('change', syncReportButtons);
      syncReportButtons();
    }
    if (btnViewReport) {
      btnViewReport.addEventListener('click', () => {
        const sid = getScheduleId();
        if (!sid) { showToast('Select a schedule first.', 'error'); return; }
        window.open(rootUrl + '/admin/api/module4/inspection_report.php?format=html&schedule_id=' + encodeURIComponent(sid), '_blank', 'noopener');
      });
    }
    if (btnDownloadReport) {
      btnDownloadReport.addEventListener('click', () => {
        const sid = getScheduleId();
        if (!sid) { showToast('Select a schedule first.', 'error'); return; }
        window.open(rootUrl + '/admin/api/module4/inspection_report.php?format=pdf&schedule_id=' + encodeURIComponent(sid), '_blank', 'noopener');
      });
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
          const required = new Set([
            'RW_LIGHTS',
            'RW_HORN',
            'RW_BRAKES',
            'RW_STEER',
            'RW_TIRES',
            'RW_WIPERS',
            'RW_MIRRORS',
            'RW_LEAKS',
            'PS_DOORS',
            'SE_EXT',
            'SE_EWD',
            'DOC_CR',
            'DOC_OR',
            'DOC_CMVI',
            'DOC_CTPL'
          ]);
          const requiredMissing = [];
          const requiredNotPass = [];
          Array.from(form.querySelectorAll('select[name^="items["]')).forEach((s) => {
            const name = String(s.getAttribute('name') || '');
            const m = name.match(/^items\[(.+)\]$/);
            if (!m) return;
            const code = String(m[1] || '');
            if (!required.has(code)) return;
            const v = String(s.value || '');
            if (v === 'NA' || v === '') requiredMissing.push(code);
            else if (v !== 'Pass') requiredNotPass.push(code);
          });
          const anyFail = Array.from(form.querySelectorAll('select[name^="items["]')).some((s) => (s.value || '') === 'Fail');
          const allPassOrNA = Array.from(form.querySelectorAll('select[name^="items["]')).every((s) => (s.value || '') !== 'Fail');
          if (requiredMissing.length) {
            showToast('Required checklist items cannot be N/A. Please complete: ' + requiredMissing.join(', '), 'error');
            btn.disabled = false; btn.textContent = 'Submit Result';
            return;
          }
          if (overall === 'Passed' && requiredNotPass.length) {
            showToast('Overall result is Passed but required items are not Pass: ' + requiredNotPass.join(', '), 'error');
            btn.disabled = false; btn.textContent = 'Submit Result';
            return;
          }
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
