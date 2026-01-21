<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['operator_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$highlightId = (int)($_GET['highlight_operator_id'] ?? 0);

$operators = [];
$operatorIds = [];

$sql = "SELECT
  o.id,
  o.operator_type,
  COALESCE(NULLIF(o.registered_name,''), NULLIF(o.name,''), o.full_name) AS display_name,
  o.verification_status,
  o.created_at,
  COUNT(v.id) AS vehicle_count
FROM operators o
LEFT JOIN vehicles v ON v.operator_id=o.id
WHERE 1=1";

$params = [];
$types = '';
if ($q !== '') {
  $sql .= " AND (o.registered_name LIKE ? OR o.name LIKE ? OR o.full_name LIKE ? OR v.plate_number LIKE ?)";
  $like = "%$q%";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'ssss';
}
if ($type !== '' && $type !== 'Type') {
  $sql .= " AND o.operator_type=?";
  $params[] = $type;
  $types .= 's';
}
if ($status !== '' && $status !== 'Status') {
  $sql .= " AND o.verification_status=?";
  $params[] = $status;
  $types .= 's';
}
$sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT 300";

if ($params) {
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resO = $stmt->get_result();
  } else {
    $resO = null;
  }
} else {
  $resO = $db->query($sql);
}

if ($resO) {
  while ($r = $resO->fetch_assoc()) {
    $id = (int)($r['id'] ?? 0);
    $nm = trim((string)($r['display_name'] ?? ''));
    if ($id <= 0 || $nm === '') continue;
    $operators[] = [
      'operator_id' => $id,
      'display_name' => $nm,
      'operator_type' => (string)($r['operator_type'] ?? ''),
      'verification_status' => (string)($r['verification_status'] ?? 'Draft'),
      'vehicle_count' => (int)($r['vehicle_count'] ?? 0),
    ];
    $operatorIds[] = $id;
  }
}
if (isset($stmt) && $stmt) $stmt->close();

$vehiclesByOperator = [];
if ($operatorIds) {
  $placeholders = implode(',', array_fill(0, count($operatorIds), '?'));
  $typesIn = str_repeat('i', count($operatorIds));
  $sqlV = "SELECT id, operator_id, UPPER(plate_number) AS plate_number, vehicle_type, status, created_at
           FROM vehicles
           WHERE operator_id IN ($placeholders)
           ORDER BY created_at DESC";
  $stmtV = $db->prepare($sqlV);
  if ($stmtV) {
    $stmtV->bind_param($typesIn, ...$operatorIds);
    $stmtV->execute();
    $resV = $stmtV->get_result();
    while ($row = $resV->fetch_assoc()) {
      $opId = (int)($row['operator_id'] ?? 0);
      if ($opId <= 0) continue;
      if (!isset($vehiclesByOperator[$opId])) $vehiclesByOperator[$opId] = [];
      $vehiclesByOperator[$opId][] = [
        'vehicle_id' => (int)($row['id'] ?? 0),
        'plate_number' => (string)($row['plate_number'] ?? ''),
        'vehicle_type' => (string)($row['vehicle_type'] ?? ''),
        'status' => (string)($row['status'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
      ];
    }
    $stmtV->close();
  }
}

$allPlates = [];
$resP = $db->query("SELECT UPPER(plate_number) AS plate_number, COALESCE(NULLIF(status,''),'') AS status
                    FROM vehicles
                    WHERE COALESCE(plate_number,'')<>'' AND (operator_id IS NULL OR operator_id=0)
                    ORDER BY created_at DESC LIMIT 1000");
if ($resP) {
  while ($r = $resP->fetch_assoc()) {
    $p = strtoupper(trim((string)($r['plate_number'] ?? '')));
    if ($p === '') continue;
    $allPlates[] = ['plate_number' => $p, 'status' => (string)($r['status'] ?? '')];
  }
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
$canLink = has_any_permission(['module1.link_vehicle','module1.write']);
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Vehicle–Operator Linking</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">View operators and their linked vehicles, then link additional vehicles as needed.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module1/submodule2" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="bus" class="w-4 h-4"></i>
        Vehicle Encoding
      </a>
      <a href="?page=module1/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="users" class="w-4 h-4"></i>
        Operator Encoding
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <form class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" method="GET">
      <input type="hidden" name="page" value="module1/submodule4">
      <div class="flex-1 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1 sm:max-w-sm group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all placeholder:text-slate-400" placeholder="Search operator or plate...">
        </div>
        <div class="relative w-full sm:w-52">
          <select name="operator_type" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Types</option>
            <?php foreach (['Individual','Cooperative','Corporation'] as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $type === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
        <div class="relative w-full sm:w-44">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Draft','Verified','Inactive'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="filter" class="w-4 h-4"></i>
          Apply
        </button>
        <a href="?page=module1/submodule4" class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          Reset
        </a>
      </div>
    </form>
    <datalist id="vehiclePlateList">
      <?php foreach ($allPlates as $v): ?>
        <option value="<?php echo htmlspecialchars($v['plate_number'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($v['plate_number'] . ($v['status'] !== '' ? (' • ' . $v['status']) : '')); ?></option>
      <?php endforeach; ?>
    </datalist>
  </div>

  <div class="space-y-4">
    <?php if (!$operators): ?>
      <div class="bg-white dark:bg-slate-800 p-10 rounded-lg border border-slate-200 dark:border-slate-700 text-center text-sm text-slate-500 dark:text-slate-400 italic">No operators found.</div>
    <?php endif; ?>
    <?php foreach ($operators as $o): ?>
      <?php
        $opId = (int)$o['operator_id'];
        $isHighlight = $highlightId > 0 && $highlightId === $opId;
        $st = (string)($o['verification_status'] ?? 'Draft');
        $badge = match($st) {
          'Verified' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
          'Draft' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
          'Inactive' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
          default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
        };
        $veh = $vehiclesByOperator[$opId] ?? [];
      ?>
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden <?php echo $isHighlight ? 'ring-1 ring-inset ring-emerald-200/70 dark:ring-emerald-900/30' : ''; ?>" <?php echo $isHighlight ? 'id="op-row-highlight"' : ''; ?>>
        <div class="p-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <div class="text-lg font-black text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars((string)$o['display_name']); ?></div>
              <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
              <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-700/50 px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-300 ring-1 ring-inset ring-slate-500/10"><?php echo htmlspecialchars((string)$o['operator_type']); ?></span>
              <span class="inline-flex items-center rounded-lg bg-white dark:bg-slate-900 px-2.5 py-1 text-xs font-bold text-slate-500 dark:text-slate-400 ring-1 ring-inset ring-slate-200 dark:ring-slate-700">ID: <?php echo (int)$opId; ?></span>
            </div>
            <div class="mt-2 text-sm text-slate-600 dark:text-slate-300 font-semibold"><?php echo (int)($o['vehicle_count'] ?? 0); ?> vehicle(s) linked</div>
            <div class="mt-3 flex flex-wrap gap-2">
              <?php if ($veh): ?>
                <?php foreach (array_slice($veh, 0, 10) as $v): ?>
                  <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200">
                    <i data-lucide="car" class="w-3.5 h-3.5 text-slate-400"></i>
                    <?php echo htmlspecialchars((string)$v['plate_number']); ?>
                  </span>
                <?php endforeach; ?>
                <?php if (count($veh) > 10): ?>
                  <span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700/40 text-xs font-bold text-slate-600 dark:text-slate-300">+<?php echo (int)(count($veh) - 10); ?> more</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-xs text-slate-500 dark:text-slate-400 italic">No vehicles linked yet.</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="shrink-0 flex items-center gap-2">
            <button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900 dark:bg-slate-700 text-white text-xs font-bold hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors" data-toggle-vehicles="1" data-operator-id="<?php echo (int)$opId; ?>">
              <i data-lucide="chevron-down" class="w-4 h-4"></i>
              Details
            </button>
          </div>
        </div>
        <div class="hidden border-t border-slate-200 dark:border-slate-700 p-5 bg-slate-50/40 dark:bg-slate-900/20" data-vehicles-panel="<?php echo (int)$opId; ?>">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-3">Linked Vehicles</div>
              <?php if (!$veh): ?>
                <div class="text-sm text-slate-500 dark:text-slate-400 italic">No vehicles linked.</div>
              <?php else: ?>
                <div class="space-y-2 max-h-[45vh] overflow-y-auto pr-1">
                  <?php foreach ($veh as $v): ?>
                    <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                      <div class="min-w-0">
                        <div class="text-sm font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)$v['plate_number']); ?></div>
                        <div class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars(trim((string)$v['vehicle_type'] . ($v['status'] ? (' • ' . $v['status']) : ''))); ?></div>
                      </div>
                      <a href="?page=module1/submodule2&highlight_plate=<?php echo urlencode((string)$v['plate_number']); ?>" class="shrink-0 px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Open</a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-3">Link Another Vehicle</div>
              <?php if (!$canLink): ?>
                <div class="text-sm text-slate-500 dark:text-slate-400 italic">You don't have permission to link vehicles.</div>
              <?php else: ?>
                <form class="space-y-4" data-link-form="1" data-operator-id="<?php echo (int)$opId; ?>" novalidate>
                  <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle (Plate)</label>
                    <input name="plate_number" list="vehiclePlateList" required minlength="7" maxlength="8" pattern="^[A-Za-z]{3}\\-[0-9]{3,4}$" autocapitalize="characters" data-tmm-mask="plate" data-tmm-uppercase="1" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="e.g., ABC-1234">
                  </div>
                  <div class="flex items-center justify-end gap-2">
                    <button type="submit" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold" data-link-btn="1">Link Vehicle</button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const normalizePlate = (value) => {
      const v = (value || '').toString().toUpperCase().replace(/\\s+/g, '').replace(/[^A-Z0-9-]/g, '').replace(/-+/g, '-');
      const letters = v.replace(/[^A-Z]/g, '').slice(0, 3);
      const digits = v.replace(/[^0-9]/g, '').slice(0, 4);
      if (letters.length < 3) return letters + digits;
      return letters + '-' + digits;
    };

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

    document.querySelectorAll('input[name="plate_number"]').forEach((el) => {
      el.addEventListener('input', () => { el.value = normalizePlate(el.value); });
      el.addEventListener('blur', () => { el.value = normalizePlate(el.value); });
    });

    document.querySelectorAll('[data-toggle-vehicles="1"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-operator-id');
        const panel = document.querySelector('[data-vehicles-panel="' + id + '"]');
        if (!panel) return;
        panel.classList.toggle('hidden');
        const icon = btn.querySelector('i[data-lucide="chevron-down"]');
        if (icon) icon.classList.toggle('rotate-180');
      });
    });

    document.querySelectorAll('form[data-link-form="1"]').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const operatorId = form.getAttribute('data-operator-id');
        const btn = form.querySelector('[data-link-btn="1"]');
        const fd = new FormData(form);
        const plate = (fd.get('plate_number') || '').toString().trim().toUpperCase();
        if (!plate) { showToast('Select a vehicle plate.', 'error'); return; }
        if (!operatorId) { showToast('Missing operator.', 'error'); return; }

        const orig = btn ? btn.textContent : '';
        if (btn) { btn.disabled = true; btn.textContent = 'Linking...'; }
        try {
          const post = new FormData();
          post.append('plate_number', plate);
          post.append('operator_id', String(operatorId));
          const res = await fetch(rootUrl + '/admin/api/module1/link_vehicle_operator.php', { method: 'POST', body: post });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'link_failed');
          showToast('Vehicle linked successfully.');
          const params = new URLSearchParams(window.location.search || '');
          params.set('page', 'module1/submodule4');
          params.set('highlight_operator_id', String(operatorId));
          window.location.search = params.toString();
        } catch (err) {
          const raw = (err && err.message) ? String(err.message) : '';
          const msg = raw === 'already_linked'
            ? 'This plate is already linked to another operator.'
            : (raw || 'Failed to link');
          showToast(msg, 'error');
          if (btn) { btn.disabled = false; btn.textContent = orig || 'Link Vehicle'; }
        }
      });
    });
  })();
</script>
