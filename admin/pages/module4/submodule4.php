<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module4.inspect','module4.certify']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$scheduleId = (int)($_GET['schedule_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$resultFilter = trim((string)($_GET['result'] ?? ''));

$schedules = [];
$resS = $db->query("SELECT schedule_id, plate_number, status, schedule_date, scheduled_at
                    FROM inspection_schedules
                    WHERE status IN ('Scheduled','Rescheduled','Pending Verification','Pending Assignment','Overdue','Overdue / No-Show','Completed')
                    ORDER BY COALESCE(schedule_date, scheduled_at) DESC
                    LIMIT 500");
if ($resS) while ($r = $resS->fetch_assoc()) $schedules[] = $r;

$conductedRows = [];
$sqlC = "SELECT i.inspection_id, i.schedule_id, i.vehicle_id, i.result, i.remarks, i.inspected_at,
                s.plate_number, s.status AS schedule_status, s.location, COALESCE(s.schedule_date, s.scheduled_at) AS sched_dt
         FROM inspections i
         JOIN inspection_schedules s ON s.schedule_id=i.schedule_id
         WHERE 1=1";
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $sqlC .= " AND (s.plate_number LIKE '%$qv%' OR s.location LIKE '%$qv%')";
}
if ($resultFilter !== '' && in_array($resultFilter, ['Passed','Failed'], true)) {
  $rv = $db->real_escape_string($resultFilter);
  $sqlC .= " AND i.result='$rv'";
}
$sqlC .= " ORDER BY i.inspected_at DESC, i.inspection_id DESC LIMIT 500";
$resC = $db->query($sqlC);
if ($resC) while ($r = $resC->fetch_assoc()) $conductedRows[] = $r;

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
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full md:w-auto">
      <button type="button" id="btnOpenConduct" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="check-square" class="w-4 h-4"></i>
        Conduct Checklist
      </button>
      <a href="?page=module4/submodule3" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="calendar-plus" class="w-4 h-4"></i>
        Schedule Inspection
      </a>
      <a href="?page=module4/submodule1" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="list" class="w-4 h-4"></i>
        Vehicle Registration List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[500] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-sm font-black text-slate-900 dark:text-white">Conducted Inspections</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1">Inspection outcomes recorded from the checklist.</div>
        </div>
        <form method="GET" class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
          <input type="hidden" name="page" value="module4/submodule4">
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full sm:w-56 px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="Search plate/location...">
          <select name="result" class="w-full sm:w-auto px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="" <?php echo $resultFilter === '' ? 'selected' : ''; ?>>All Results</option>
            <option value="Passed" <?php echo $resultFilter === 'Passed' ? 'selected' : ''; ?>>Passed</option>
            <option value="Failed" <?php echo $resultFilter === 'Failed' ? 'selected' : ''; ?>>Failed</option>
          </select>
          <a href="?page=module4/submodule4" class="w-full sm:w-auto px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-sm text-center">Reset</a>
        </form>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
            <tr class="text-left text-slate-500 dark:text-slate-400">
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Inspection</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Plate</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Scheduled</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Location</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Result</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Inspected At</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php if (!empty($conductedRows)): ?>
              <?php foreach ($conductedRows as $r): ?>
                <?php
                  $sid = (int)($r['schedule_id'] ?? 0);
                  $pid = (string)($r['plate_number'] ?? '');
                  $dt = (string)($r['sched_dt'] ?? '');
                  $loc = (string)($r['location'] ?? '');
                  $res = (string)($r['result'] ?? '');
                  $insAt = (string)($r['inspected_at'] ?? '');
                  $badge = $res === 'Passed'
                    ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
                    : 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20';
                ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                  <td class="py-3 px-4 font-black text-slate-900 dark:text-white">SCH-<?php echo $sid; ?></td>
                  <td class="py-3 px-4 font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($pid); ?></td>
                  <td class="py-3 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars($dt !== '' ? date('M d, Y H:i', strtotime($dt)) : '-'); ?></td>
                  <td class="py-3 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars($loc !== '' ? $loc : '-'); ?></td>
                  <td class="py-3 px-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($res !== '' ? $res : '-'); ?></span>
                  </td>
                  <td class="py-3 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars($insAt !== '' ? date('M d, Y H:i', strtotime($insAt)) : '-'); ?></td>
                  <td class="py-3 px-4 text-right">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                      <a href="?<?php echo http_build_query(['page' => 'module4/submodule4', 'schedule_id' => $sid]); ?>" class="px-3 py-2 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold text-xs">New Result</a>
                      <a href="<?php echo htmlspecialchars($rootUrl . '/admin/api/module4/inspection_report.php?format=html&schedule_id=' . $sid, ENT_QUOTES); ?>" target="_blank" rel="noopener" class="px-3 py-2 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold text-xs">View</a>
                      <a href="<?php echo htmlspecialchars($rootUrl . '/admin/api/module4/inspection_report.php?format=pdf&schedule_id=' . $sid, ENT_QUOTES); ?>" target="_blank" rel="noopener" class="px-3 py-2 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold text-xs">PDF</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">No inspections recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="modalConduct" class="fixed inset-0 z-[220] hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="w-full max-w-5xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden ring-1 ring-slate-900/5">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
        <div class="text-lg font-black text-slate-900 dark:text-white">Conduct Inspection Checklist</div>
        <button type="button" id="btnCloseConduct" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-6 space-y-6 max-h-[80vh] overflow-y-auto">
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
                  $st = (string)($s['status'] ?? '');
                  if ($st !== '') $label .= ' • ' . $st;
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
              if (in_array($code, ['DOC_CR','DOC_OR','DOC_CMVI','DOC_CTPL'], true)) continue;
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

        <div class="mt-6">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Document Presentation (Upload)</div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
              <div class="text-sm font-black text-slate-900 dark:text-white">Certificate of Registration (CR)</div>
              <div id="docStatusCr" class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">Required</div>
              <div class="mt-2">
                <input name="doc_cr" type="file" accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" class="w-full text-sm">
              </div>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
              <div class="text-sm font-black text-slate-900 dark:text-white">Official Receipt (OR)</div>
              <div id="docStatusOr" class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">Required</div>
              <div class="mt-2">
                <input name="doc_or" type="file" accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" class="w-full text-sm">
              </div>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
              <div class="text-sm font-black text-slate-900 dark:text-white">CMVI / PMVIC Certificate</div>
              <div id="docStatusCmvi" class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">Required</div>
              <div class="mt-2">
                <input name="doc_cmvi" type="file" accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" class="w-full text-sm">
              </div>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
              <div class="text-sm font-black text-slate-900 dark:text-white">CTPL Insurance</div>
              <div id="docStatusCtpl" class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">Required</div>
              <div class="mt-2">
                <input name="doc_ctpl" type="file" accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" class="w-full text-sm">
              </div>
            </div>
          </div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-2">Documents are uploaded after the checklist is saved.</div>
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
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const form = document.getElementById('formConduct');
    const btn = document.getElementById('btnSubmit');
    const btnViewReport = document.getElementById('btnViewReport');
    const btnDownloadReport = document.getElementById('btnDownloadReport');
    const scheduleSelect = form ? form.querySelector('select[name="schedule_id"]') : null;
    const modal = document.getElementById('modalConduct');
    const btnOpen = document.getElementById('btnOpenConduct');
    const btnClose = document.getElementById('btnCloseConduct');
    const initialScheduleId = <?php echo json_encode($scheduleId); ?>;
    const docStatus = {
      cr: document.getElementById('docStatusCr'),
      or: document.getElementById('docStatusOr'),
      emission: document.getElementById('docStatusCmvi'),
      insurance: document.getElementById('docStatusCtpl'),
    };
    window.__tmm_docOnFile = { cr: false, or: false, emission: false, insurance: false };

    function openModal() {
      if (!modal) return;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      try { document.body.style.overflow = 'hidden'; } catch (e) { }
    }

    function closeModal() {
      if (!modal) return;
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      try { document.body.style.overflow = ''; } catch (e) { }
    }

    if (btnOpen) btnOpen.addEventListener('click', openModal);
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (modal) {
      modal.addEventListener('click', (e) => {
        if (e && e.target === modal) closeModal();
      });
      document.addEventListener('keydown', (e) => {
        if (e && e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
      });
    }
    if (initialScheduleId && Number(initialScheduleId) > 0) {
      setTimeout(openModal, 50);
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

    function setDocStatusText(key, onFile) {
      const el = docStatus[key];
      if (!el) return;
      if (onFile) {
        el.textContent = 'On file (vehicle records)';
        el.className = 'mt-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300';
      } else {
        el.textContent = 'Required';
        el.className = 'mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400';
      }
    }

    async function loadDocStatus() {
      const sid = getScheduleId();
      if (!sid) {
        window.__tmm_docOnFile = { cr: false, or: false, emission: false, insurance: false };
        ['cr','or','emission','insurance'].forEach((k) => setDocStatusText(k, false));
        return;
      }
      try {
        const res = await fetch(rootUrl + '/admin/api/module4/schedule_doc_status.php?schedule_id=' + encodeURIComponent(sid));
        const data = await res.json().catch(() => null);
        if (!data || !data.ok) throw new Error('load_failed');
        const onFile = data.on_file || {};
        window.__tmm_docOnFile = {
          cr: !!onFile.cr,
          or: !!onFile.or,
          emission: !!onFile.emission,
          insurance: !!onFile.insurance,
        };
        setDocStatusText('cr', window.__tmm_docOnFile.cr);
        setDocStatusText('or', window.__tmm_docOnFile.or);
        setDocStatusText('emission', window.__tmm_docOnFile.emission);
        setDocStatusText('insurance', window.__tmm_docOnFile.insurance);
      } catch (e) {
        window.__tmm_docOnFile = { cr: false, or: false, emission: false, insurance: false };
        ['cr','or','emission','insurance'].forEach((k) => setDocStatusText(k, false));
      }
    }

    if (scheduleSelect) {
      scheduleSelect.addEventListener('change', syncReportButtons);
      scheduleSelect.addEventListener('change', loadDocStatus);
      syncReportButtons();
      loadDocStatus();
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
            'PS_DOORS'
          ]);
          const requiredMissing = [];
          const requiredNotPass = [];
          const codeToLabel = {};
          Array.from(form.querySelectorAll('input[type="hidden"][name^="labels["]')).forEach((inp) => {
            const name = String(inp.getAttribute('name') || '');
            const m = name.match(/^labels\[(.+)\]$/);
            if (!m) return;
            const code = String(m[1] || '');
            const lbl = String(inp.value || '').trim();
            if (code && lbl) codeToLabel[code] = lbl;
          });
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
          const docOnFile = window.__tmm_docOnFile || {};
          const docMissing = [];
          const docMap = [
            ['doc_cr', 'cr'],
            ['doc_or', 'or'],
            ['doc_cmvi', 'emission'],
            ['doc_ctpl', 'insurance'],
          ];
          docMap.forEach(([field, key]) => {
            const inp = form.querySelector('input[name="' + field + '"]');
            const uploaded = !!(inp && inp.files && inp.files.length > 0);
            const onFile = !!docOnFile[key];
            if (!uploaded && !onFile) docMissing.push(field);
          });
          if (docMissing.length) {
            showToast('Upload the missing document(s) or use the already uploaded vehicle record documents.', 'error');
            btn.disabled = false; btn.textContent = 'Submit Result';
            return;
          }
          if (requiredMissing.length) {
            const names = requiredMissing.map((c) => codeToLabel[c] || c);
            showToast('Required items cannot be N/A. Please complete: ' + names.join(', '), 'error');
            btn.disabled = false; btn.textContent = 'Submit Result';
            return;
          }
          if (overall === 'Passed' && requiredNotPass.length) {
            const names = requiredNotPass.map((c) => codeToLabel[c] || c);
            showToast('Overall result is Passed but required items are not Pass: ' + names.join(', '), 'error');
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

          const docsUp = new FormData();
          docsUp.append('schedule_id', scheduleId);
          let anyDocs = false;
          ['doc_cr','doc_or','doc_cmvi','doc_ctpl'].forEach((n) => {
            const inp = form.querySelector('input[name="' + n + '"]');
            if (inp && inp.files && inp.files[0]) { docsUp.append(n, inp.files[0]); anyDocs = true; }
          });
          if (anyDocs) {
            const dRes = await fetch(rootUrl + '/admin/api/module4/upload_inspection_docs.php', { method: 'POST', body: docsUp });
            const dData = await dRes.json().catch(() => null);
            if (!dData || !dData.ok) throw new Error((dData && dData.error) ? dData.error : 'doc_upload_failed');
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
