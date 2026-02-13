<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.view','module1.vehicles.write']);
$db = db();
$plate = trim($_GET['plate'] ?? '');
$canEdit = has_permission('module1.vehicles.write');
$v = null;
if ($plate !== '') {
  $stmt = $db->prepare("SELECT v.id AS vehicle_id, v.plate_number, v.vehicle_type, v.operator_id,
                               COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '') AS operator_display,
                               v.engine_no, v.chassis_no, v.make, v.model, v.year_model, v.fuel_type,
                               v.or_number, v.cr_number, v.cr_issue_date, v.registered_owner, v.color,
                               v.submitted_by_portal_user_id, v.submitted_by_name, v.submitted_at,
                               v.status, v.created_at
                        FROM vehicles v
                        LEFT JOIN operators o ON o.id=v.operator_id
                        WHERE v.plate_number=?");
  $stmt->bind_param('s', $plate);
  $stmt->execute();
  $v = $stmt->get_result()->fetch_assoc();
}
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>
<div class="p-1 space-y-6">
  <?php if (!$v): ?>
    <div class="flex flex-col items-center justify-center p-12 text-center rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-slate-700">
      <div class="p-4 rounded-full bg-slate-100 dark:bg-slate-800 mb-4">
        <i data-lucide="search-x" class="w-8 h-8 text-slate-400"></i>
      </div>
      <h3 class="text-lg font-bold text-slate-900 dark:text-white">Vehicle Not Found</h3>
      <p class="text-slate-500 dark:text-slate-400 max-w-xs mt-2">The requested vehicle record could not be found in the registry.</p>
    </div>
  <?php else: ?>
    <!-- Header with Status -->
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
      <div class="flex items-start gap-5">
        <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 ring-1 ring-blue-100 dark:ring-blue-800/30">
          <i data-lucide="bus" class="w-8 h-8"></i>
        </div>
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight"><?php echo htmlspecialchars($v['plate_number']); ?></h2>
            <?php
              $statusClass = match($v['status']) {
                'Active' => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
                'Linked' => 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20',
                'Unlinked' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
                'Blocked' => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
                'Inactive' => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
                default => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/20'
              };
            ?>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold border <?php echo $statusClass; ?>">
              <?php echo htmlspecialchars($v['status']); ?>
            </span>
          </div>
          <?php $vs = (string)($v['status'] ?? ''); ?>
          <?php if ($vs === 'Blocked'): ?>
            <div class="mt-2 inline-flex items-center gap-2 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 px-3 py-1.5 text-xs font-bold">
              <i data-lucide="octagon-alert" class="w-4 h-4"></i>
              Operation blocked (OR expired)
            </div>
          <?php elseif ($vs === 'Inactive'): ?>
            <div class="mt-2 inline-flex items-center gap-2 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 px-3 py-1.5 text-xs font-bold">
              <i data-lucide="triangle-alert" class="w-4 h-4"></i>
              Inactive (missing OR)
            </div>
          <?php endif; ?>
          <div class="text-base font-medium text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
            <i data-lucide="user" class="w-4 h-4"></i>
            <?php echo htmlspecialchars($v['operator_display'] !== '' ? $v['operator_display'] : 'Unlinked'); ?>
          </div>
          <div class="text-xs text-slate-400 mt-2 flex items-center gap-2">
            <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
            <?php
              $encodedAt = (string)($v['submitted_at'] ?? '');
              if ($encodedAt === '') $encodedAt = (string)($v['created_at'] ?? '');
              $encodedBy = trim((string)($v['submitted_by_name'] ?? ''));
              $via = ((int)($v['submitted_by_portal_user_id'] ?? 0) > 0) ? 'Operator Portal' : ($encodedBy !== '' ? 'Admin Dashboard (Vehicle Records)' : 'Unknown');
            ?>
            Encoded on <?php echo $encodedAt !== '' ? date('F d, Y h:i A', strtotime($encodedAt)) : '-'; ?> • <?php echo htmlspecialchars($via); ?>
          </div>
          <div class="text-xs text-slate-400 mt-1 flex items-center gap-2">
            <i data-lucide="badge-check" class="w-3.5 h-3.5"></i>
            Encoded by <?php echo htmlspecialchars($encodedBy !== '' ? $encodedBy : ($via === 'Operator Portal' ? 'Portal User' : 'Unknown')); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="space-y-6">
      <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
          <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="info" class="w-4 h-4 text-blue-500"></i> Vehicle Information
          </h3>
        </div>
        <div class="p-6">
          <?php if (!$canEdit): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Vehicle Type</span>
                <div class="font-bold text-slate-900 dark:text-white text-lg"><?php echo htmlspecialchars($v['vehicle_type']); ?></div>
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Vehicle ID</span>
                <div class="font-bold text-slate-900 dark:text-white text-lg"><?php echo (int)($v['vehicle_id'] ?? 0); ?></div>
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Engine No</span>
                <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['engine_no'] ?? '-'); ?></div>
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Chassis No</span>
                <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['chassis_no'] ?? '-'); ?></div>
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Make / Model</span>
                <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(trim((string)($v['make'] ?? '') . ' ' . (string)($v['model'] ?? '')) ?: '-'); ?></div>
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Year / Fuel</span>
                <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(trim((string)($v['year_model'] ?? '') . ' ' . (string)($v['fuel_type'] ?? '')) ?: '-'); ?></div>
              </div>
            </div>
          <?php else: ?>
            <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-2 pointer-events-none"></div>
            <form id="vehInlineEditForm" class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6" novalidate>
              <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars((string)($v['plate_number'] ?? ''), ENT_QUOTES); ?>">
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Vehicle Type</span>
                <input name="vehicle_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold" value="<?php echo htmlspecialchars((string)($v['vehicle_type'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Vehicle ID</span>
                <input class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold" value="<?php echo (int)($v['vehicle_id'] ?? 0); ?>" disabled>
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Engine No</span>
                <input name="engine_no" minlength="5" maxlength="20" pattern="^[A-Z0-9-]{5,20}$" autocapitalize="characters" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold uppercase" value="<?php echo htmlspecialchars((string)($v['engine_no'] ?? ''), ENT_QUOTES); ?>" placeholder="e.g., 1NZFE-12345">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Chassis No</span>
                <input name="chassis_no" minlength="17" maxlength="17" pattern="^[A-HJ-NPR-Z0-9]{17}$" autocapitalize="characters" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold uppercase" value="<?php echo htmlspecialchars((string)($v['chassis_no'] ?? ''), ENT_QUOTES); ?>" placeholder="e.g., NCP12345678901234">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Make</span>
                <input name="make" maxlength="40" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold" value="<?php echo htmlspecialchars((string)($v['make'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Model</span>
                <input name="model" maxlength="40" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold" value="<?php echo htmlspecialchars((string)($v['model'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Year Model</span>
                <input name="year_model" maxlength="10" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold" value="<?php echo htmlspecialchars((string)($v['year_model'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Fuel Type</span>
                <input name="fuel_type" maxlength="20" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold" value="<?php echo htmlspecialchars((string)($v['fuel_type'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">OR Number</span>
                <input name="or_number" inputmode="numeric" minlength="6" maxlength="12" pattern="^[0-9]{6,12}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold" value="<?php echo htmlspecialchars((string)($v['or_number'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">CR Number</span>
                <input name="cr_number" minlength="6" maxlength="20" pattern="^[A-Z0-9-]{6,20}$" autocapitalize="characters" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold uppercase" value="<?php echo htmlspecialchars((string)($v['cr_number'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">CR Issue Date</span>
                <input name="cr_issue_date" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold" value="<?php echo htmlspecialchars((string)($v['cr_issue_date'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Registered Owner</span>
                <input name="registered_owner" maxlength="120" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold" value="<?php echo htmlspecialchars((string)($v['registered_owner'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Color</span>
                <input name="color" maxlength="64" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold" value="<?php echo htmlspecialchars((string)($v['color'] ?? ''), ENT_QUOTES); ?>">
              </div>
              <div class="space-y-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Save</span>
                <button id="vehInlineSaveBtn" class="w-full px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save Changes</button>
              </div>
            </form>
            <script>
              (function () {
                const rootUrl = <?php echo json_encode($rootUrl); ?>;
                const form = document.getElementById('vehInlineEditForm');
                const btn = document.getElementById('vehInlineSaveBtn');
                const toastContainer = document.getElementById('toast-container');
                function toast(msg, type) {
                  if (!toastContainer) return;
                  const t = (type || 'success').toString();
                  const color = t === 'error' ? 'bg-rose-600' : 'bg-emerald-600';
                  const el = document.createElement('div');
                  el.className = `pointer-events-auto px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold ${color}`;
                  el.textContent = msg;
                  toastContainer.appendChild(el);
                  setTimeout(() => { el.classList.add('opacity-0'); el.style.transition = 'opacity 250ms'; }, 2600);
                  setTimeout(() => { el.remove(); }, 3000);
                }
                if (!form || !btn) return;
                form.addEventListener('submit', async (e) => {
                  e.preventDefault();
                  if (!form.checkValidity()) { form.reportValidity(); return; }
                  btn.disabled = true;
                  const old = btn.textContent;
                  btn.textContent = 'Saving...';
                  try {
                    const res = await fetch(rootUrl + '/admin/api/module1/update_vehicle.php', { method: 'POST', body: new FormData(form) });
                    const data = await res.json().catch(() => null);
                    if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'save_failed');
                    toast('Saved.');
                  } catch (err) {
                    toast(err.message || 'Failed', 'error');
                  } finally {
                    btn.disabled = false;
                    btn.textContent = old;
                  }
                });
                form.querySelectorAll('input[autocapitalize="characters"]').forEach((el) => {
                  el.addEventListener('input', () => { el.value = (el.value || '').toString().toUpperCase().replace(/\s+/g, ''); });
                });
              })();
            </script>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
