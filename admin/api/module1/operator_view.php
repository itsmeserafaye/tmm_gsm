<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
require_login();
require_any_permission(['module1.read', 'module1.view', 'module1.write', 'module1.vehicles.write']);
$canEdit = has_any_permission(['module1.write', 'module1.vehicles.write']);

$operatorId = isset($_GET['operator_id']) ? (int) $_GET['operator_id'] : 0;
header('Content-Type: text/html; charset=utf-8');

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) {
    $rootUrl = substr($scriptName, 0, $pos);
}
if ($rootUrl === '/') {
    $rootUrl = '';
}

if ($operatorId <= 0) {
    echo '<div class="text-sm text-slate-600">Missing operator_id.</div>';
    exit;
}

$stmt = $db->prepare("SELECT id, COALESCE(NULLIF(registered_name,''), NULLIF(name,''), NULLIF(full_name,'')) AS display_name, operator_type,
                             address, address_street, address_barangay, address_city, address_province, address_postal_code,
                             contact_no, email, verification_status, workflow_status, created_at,
                             COALESCE(portal_user_id,0) AS portal_user_id, COALESCE(submitted_by_name,'') AS submitted_by_name, submitted_at,
                             COALESCE(approved_by_name,'') AS approved_by_name, approved_at
                      FROM operators WHERE id=? LIMIT 1");
if (!$stmt) {
    echo '<div class="text-sm text-slate-600">Database error.</div>';
    exit;
}
$stmt->bind_param('i', $operatorId);
$stmt->execute();
$op = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$op) {
    echo '<div class="text-sm text-slate-600">Operator not found.</div>';
    exit;
}

$st = (string) ($op['workflow_status'] ?? ($op['verification_status'] ?? 'Draft'));
$badge = match ($st) {
    'Active' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
    'Incomplete' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400',
    'Pending Validation' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
    'Returned' => 'bg-orange-100 text-orange-700 ring-orange-600/20 dark:bg-orange-900/30 dark:text-orange-400 dark:ring-orange-500/20',
    'Rejected' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
    'Inactive' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
    'Draft' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400',
    default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
};

$contactLine = trim((string) ($op['contact_no'] ?? ''));
$emailLine = trim((string) ($op['email'] ?? ''));
$displayContact = $contactLine;
if ($displayContact !== '' && $emailLine !== '') {
    $displayContact .= ' / ';
}
$displayContact .= $emailLine;
$displayContact = trim($displayContact) !== '' ? $displayContact : '-';

$portalUserId = (int)($op['portal_user_id'] ?? 0);
$submittedBy = trim((string)($op['submitted_by_name'] ?? ''));
$submittedAt = trim((string)($op['submitted_at'] ?? ''));
$approvedBy = trim((string)($op['approved_by_name'] ?? ''));
$approvedAt = trim((string)($op['approved_at'] ?? ''));
$sourceLabel = $portalUserId > 0 ? 'Operator Portal' : ($submittedBy !== '' ? 'Walk-in' : 'Unknown');
$whereLabel = $portalUserId > 0 ? 'Operator Portal' : ($submittedBy !== '' ? 'Admin Dashboard' : '-');
$whenLabel = $submittedAt !== '' ? $submittedAt : (string)($op['created_at'] ?? '');

$docs = [];
$stmtD = $db->prepare("SELECT doc_id, doc_type, file_path, uploaded_at, doc_status, remarks FROM operator_documents WHERE operator_id=? ORDER BY uploaded_at DESC");
if ($stmtD) {
    $stmtD->bind_param('i', $operatorId);
    $stmtD->execute();
    $resD = $stmtD->get_result();
    while ($r = $resD->fetch_assoc()) {
        $docs[] = $r;
    }
    $stmtD->close();
}

$vehicles = [];
$stmtV = $db->prepare("SELECT plate_number, vehicle_type, status, created_at FROM vehicles WHERE operator_id=? ORDER BY created_at DESC LIMIT 10");
if ($stmtV) {
    $stmtV->bind_param('i', $operatorId);
    $stmtV->execute();
    $resV = $stmtV->get_result();
    while ($r = $resV->fetch_assoc()) {
        $vehicles[] = $r;
    }
    $stmtV->close();
}

?>
<div class="space-y-5">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-widest">Operator Profile</div>
            <div class="mt-1 text-2xl font-black text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars((string) ($op['display_name'] ?? '')); ?></div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
                <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-300 ring-1 ring-inset ring-slate-500/10"><?php echo htmlspecialchars((string) ($op['operator_type'] ?? 'Individual')); ?></span>
                <span class="inline-flex items-center rounded-lg bg-white dark:bg-slate-900 px-2.5 py-1 text-xs font-bold text-slate-500 dark:text-slate-400 ring-1 ring-inset ring-slate-200 dark:ring-slate-700">ID: <?php echo (int) $operatorId; ?></span>
            </div>
        </div>
        <div class="shrink-0 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 p-3">
            <i data-lucide="user" class="w-6 h-6 text-slate-500"></i>
        </div>
    </div>

    <details open class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
      <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between gap-3">
        <div class="text-sm font-black text-slate-900 dark:text-white">Contact & Address</div>
        <div id="opInlineMsg" class="text-[11px] font-bold text-slate-500 dark:text-slate-400 hidden"></div>
      </summary>
      <div id="opInlineEdit" class="p-4 pt-0 space-y-4" data-operator-id="<?php echo (int)$operatorId; ?>" data-root-url="<?php echo htmlspecialchars($rootUrl, ENT_QUOTES); ?>" data-can-edit="<?php echo $canEdit ? '1' : '0'; ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contact No</label>
            <input data-op-field="contact_no" data-initial="<?php echo htmlspecialchars((string)($op['contact_no'] ?? ''), ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string)($op['contact_no'] ?? ''), ENT_QUOTES); ?>" inputmode="numeric" maxlength="20" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-sm font-semibold" placeholder="e.g., 09171234567" <?php echo $canEdit ? '' : 'disabled'; ?>>
          </div>
          <div>
            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Email</label>
            <input data-op-field="email" data-initial="<?php echo htmlspecialchars((string)($op['email'] ?? ''), ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string)($op['email'] ?? ''), ENT_QUOTES); ?>" type="email" maxlength="120" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-sm font-semibold" placeholder="e.g., juan.delacruz@email.com" <?php echo $canEdit ? '' : 'disabled'; ?>>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="sm:col-span-2">
            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">House / Building / Street</label>
            <input data-op-field="address_street" data-initial="<?php echo htmlspecialchars((string)($op['address_street'] ?? ''), ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string)($op['address_street'] ?? ''), ENT_QUOTES); ?>" maxlength="160" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-sm font-semibold" placeholder="House / Building / Street" <?php echo $canEdit ? '' : 'disabled'; ?>>
          </div>
          <div>
            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Province</label>
            <input list="opProvList" id="opProv" data-op-field="address_province" data-initial="<?php echo htmlspecialchars((string)($op['address_province'] ?? ''), ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string)($op['address_province'] ?? ''), ENT_QUOTES); ?>" maxlength="120" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-sm font-semibold" placeholder="Select or type province" <?php echo $canEdit ? '' : 'disabled'; ?>>
            <datalist id="opProvList"></datalist>
          </div>
          <div>
            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">City / Municipality</label>
            <input list="opCityList" id="opCity" data-op-field="address_city" data-initial="<?php echo htmlspecialchars((string)($op['address_city'] ?? ''), ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string)($op['address_city'] ?? ''), ENT_QUOTES); ?>" maxlength="120" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-sm font-semibold" placeholder="Select or type city" <?php echo $canEdit ? '' : 'disabled'; ?>>
            <datalist id="opCityList"></datalist>
          </div>
          <div>
            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Barangay</label>
            <input list="opBrgyList" id="opBrgy" data-op-field="address_barangay" data-initial="<?php echo htmlspecialchars((string)($op['address_barangay'] ?? ''), ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string)($op['address_barangay'] ?? ''), ENT_QUOTES); ?>" maxlength="120" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-sm font-semibold" placeholder="Select or type barangay" <?php echo $canEdit ? '' : 'disabled'; ?>>
            <datalist id="opBrgyList"></datalist>
          </div>
          <div>
            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Postal Code</label>
            <input list="opPostalList" id="opPostal" data-op-field="address_postal_code" data-initial="<?php echo htmlspecialchars((string)($op['address_postal_code'] ?? ''), ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string)($op['address_postal_code'] ?? ''), ENT_QUOTES); ?>" maxlength="10" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-sm font-semibold" placeholder="Postal code" <?php echo $canEdit ? '' : 'disabled'; ?>>
            <datalist id="opPostalList"></datalist>
          </div>
        </div>

        <?php if (!$canEdit): ?>
          <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Read-only: you don’t have permission to edit this operator.</div>
        <?php endif; ?>
        <div class="flex items-center justify-end gap-2 pt-1">
          <button type="button" id="opInlineCancel" class="hidden px-4 py-2.5 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">Cancel</button>
          <button type="button" id="opInlineSave" class="hidden px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">Save Changes</button>
        </div>
      </div>
    </details>

    <details class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
      <summary class="cursor-pointer select-none px-4 py-3">
        <div class="text-sm font-black text-slate-900 dark:text-white">Record & Approval</div>
      </summary>
      <div class="p-4 pt-0 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Record Source</div>
              <div class="flex flex-wrap items-center gap-2">
                  <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset <?php echo $sourceLabel === 'Operator Portal' ? 'bg-indigo-100 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-900/30 dark:text-indigo-400 dark:ring-indigo-500/20' : ($sourceLabel === 'Walk-in' ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20' : 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'); ?>"><?php echo htmlspecialchars($sourceLabel); ?></span>
                  <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-300 ring-1 ring-inset ring-slate-500/10"><?php echo htmlspecialchars($whereLabel); ?></span>
              </div>
              <div class="mt-3 grid grid-cols-1 gap-2 text-sm">
                  <div class="flex items-center justify-between gap-3">
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Encoded By</div>
                      <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($submittedBy !== '' ? $submittedBy : '-'); ?></div>
                  </div>
                  <div class="flex items-center justify-between gap-3">
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Encoded Time</div>
                      <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($whenLabel !== '' ? $whenLabel : '-'); ?></div>
                  </div>
              </div>
          </div>
          <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Approval</div>
              <div class="grid grid-cols-1 gap-2 text-sm">
                  <div class="flex items-center justify-between gap-3">
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Approved By</div>
                      <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($approvedBy !== '' ? $approvedBy : '-'); ?></div>
                  </div>
                  <div class="flex items-center justify-between gap-3">
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Approved At</div>
                      <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($approvedAt !== '' ? $approvedAt : '-'); ?></div>
                  </div>
              </div>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Created</div>
              <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars((string) ($op['created_at'] ?? '')); ?></div>
          </div>
        </div>
      </div>
    </details>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Record Source</div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset <?php echo $sourceLabel === 'Operator Portal' ? 'bg-indigo-100 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-900/30 dark:text-indigo-400 dark:ring-indigo-500/20' : ($sourceLabel === 'Walk-in' ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20' : 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'); ?>"><?php echo htmlspecialchars($sourceLabel); ?></span>
                <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-300 ring-1 ring-inset ring-slate-500/10"><?php echo htmlspecialchars($whereLabel); ?></span>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-2 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Encoded By</div>
                    <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($submittedBy !== '' ? $submittedBy : '-'); ?></div>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Encoded Time</div>
                    <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($whenLabel !== '' ? $whenLabel : '-'); ?></div>
                </div>
            </div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Approval</div>
            <div class="grid grid-cols-1 gap-2 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Approved By</div>
                    <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($approvedBy !== '' ? $approvedBy : '-'); ?></div>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Approved At</div>
                    <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($approvedAt !== '' ? $approvedAt : '-'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <details open class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
      <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between gap-3">
        <div class="text-sm font-black text-slate-900 dark:text-white">Documents & Vehicles</div>
        <div class="text-xs font-bold text-slate-500"><?php echo (int) count($docs); ?> docs • <?php echo (int) count($vehicles); ?> vehicles</div>
      </summary>
      <div class="p-4 pt-0">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between mb-3">
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Documents</div>
                <span class="text-xs font-bold text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-lg"><?php echo (int) count($docs); ?></span>
            </div>
            <?php if (!$docs): ?>
                <div class="text-sm text-slate-500 dark:text-slate-400 italic">No documents uploaded.</div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($docs as $d): ?>
                        <?php
                        $href = $rootUrl . '/admin/uploads/' . rawurlencode((string) ($d['file_path'] ?? ''));
                        $dt = $d['uploaded_at'] ? date('M d, Y g:i A', strtotime((string) $d['uploaded_at'])) : '';
                        $dst = (string)($d['doc_status'] ?? '');
                        if ($dst === '') $dst = 'Pending';
                        $docBadge = $dst === 'Verified'
                          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                          : ($dst === 'Rejected'
                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400');
                        ?>
                        <a href="<?php echo htmlspecialchars($href, ENT_QUOTES); ?>" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-all">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500">
                                    <i data-lucide="file" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                      <div class="text-sm font-black text-slate-800 dark:text-white"><?php echo htmlspecialchars((string) ($d['doc_type'] ?? '')); ?></div>
                                      <span class="text-[10px] font-black px-2 py-0.5 rounded-full <?php echo $docBadge; ?>"><?php echo htmlspecialchars($dst); ?></span>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($dt); ?></div>
                                    <?php if ($dst === 'Rejected' && trim((string)($d['remarks'] ?? '')) !== ''): ?>
                                      <div class="text-xs font-semibold text-rose-600 mt-1">Remarks: <?php echo htmlspecialchars((string)($d['remarks'] ?? '')); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-slate-400"><i data-lucide="external-link" class="w-4 h-4"></i></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between mb-3">
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Linked Vehicles</div>
                <span class="text-xs font-bold text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-lg"><?php echo (int) count($vehicles); ?></span>
            </div>
            <?php if (!$vehicles): ?>
                <div class="text-sm text-slate-500 dark:text-slate-400 italic">No linked vehicles.</div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($vehicles as $v): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                            <div>
                                <div class="text-sm font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) ($v['plate_number'] ?? '')); ?></div>
                                <div class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars((string) ($v['vehicle_type'] ?? '')); ?></div>
                            </div>
                            <div class="text-xs font-bold text-slate-500"><?php echo htmlspecialchars((string) ($v['status'] ?? '')); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
      </div>
    </details>
</div>
<script>
  (function(){
    const wrap = document.getElementById('opInlineEdit');
    const msg = document.getElementById('opInlineMsg');
    const btnSave = document.getElementById('opInlineSave');
    const btnCancel = document.getElementById('opInlineCancel');
    if (!wrap) return;
    const canEdit = wrap.getAttribute('data-can-edit') === '1';
    if (!canEdit) return;

    const operatorId = wrap.getAttribute('data-operator-id') || '';
    const rootUrl = wrap.getAttribute('data-root-url') || '';
    const inputs = Array.from(wrap.querySelectorAll('[data-op-field]'));
    const digitsOnly = (v) => (v || '').toString().replace(/\D+/g, '');

    const showMsg = (text, kind) => {
      if (!msg) return;
      msg.textContent = text || '';
      msg.classList.remove('hidden');
      msg.className = 'text-[11px] font-black ' + (kind === 'error' ? 'text-rose-600 dark:text-rose-300' : (kind === 'success' ? 'text-emerald-600 dark:text-emerald-300' : 'text-slate-500 dark:text-slate-400'));
      if (text) setTimeout(() => { msg.classList.add('hidden'); }, 2500);
    };

    const isDirty = () => inputs.some((i) => (i.value || '') !== (i.getAttribute('data-initial') || ''));
    const refresh = () => {
      const dirty = isDirty();
      if (btnSave) btnSave.classList.toggle('hidden', !dirty);
      if (btnCancel) btnCancel.classList.toggle('hidden', !dirty);
    };
    const reset = () => {
      inputs.forEach((i) => { i.value = i.getAttribute('data-initial') || ''; });
      refresh();
    };

    const provInput = document.getElementById('opProv');
    const cityInput = document.getElementById('opCity');
    const brgyInput = document.getElementById('opBrgy');
    const postalInput = document.getElementById('opPostal');
    const provList = document.getElementById('opProvList');
    const cityList = document.getElementById('opCityList');
    const brgyList = document.getElementById('opBrgyList');
    const postalList = document.getElementById('opPostalList');

    const fillList = (dl, items) => {
      if (!dl) return;
      dl.innerHTML = (Array.isArray(items) ? items : []).map((x) => `<option value="${String(x || '').replace(/\"/g,'&quot;')}"></option>`).join('');
    };

    const loadOpts = async (mode, params) => {
      const qs = new URLSearchParams();
      qs.set('mode', mode);
      Object.keys(params || {}).forEach((k) => { if (params[k]) qs.set(k, params[k]); });
      const res = await fetch(rootUrl + '/admin/api/geo/address_options.php?' + qs.toString());
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) return [];
      return Array.isArray(data.data) ? data.data : [];
    };

    const initLocationLists = async () => {
      try {
        const provs = await loadOpts('provinces', {});
        fillList(provList, provs);
        const p = (provInput && provInput.value) ? provInput.value.trim() : '';
        if (p) {
          const cities = await loadOpts('cities', { province: p });
          fillList(cityList, cities);
        }
        const c = (cityInput && cityInput.value) ? cityInput.value.trim() : '';
        if (p && c) {
          const brgys = await loadOpts('barangays', { province: p, city: c });
          fillList(brgyList, brgys);
          const posts = await loadOpts('postals', { province: p, city: c });
          fillList(postalList, posts);
          if (postalInput && (!postalInput.value || postalInput.value.trim() === '') && posts.length === 1) {
            postalInput.value = posts[0];
            refresh();
          }
        }
      } catch (_) {}
    };

    const onProvinceChange = async () => {
      if (!provInput) return;
      const p = provInput.value.trim();
      try {
        const cities = p ? await loadOpts('cities', { province: p }) : [];
        fillList(cityList, cities);
        if (cityInput) cityInput.value = '';
        if (brgyInput) brgyInput.value = '';
        if (postalInput) postalInput.value = '';
        fillList(brgyList, []);
        fillList(postalList, []);
        refresh();
      } catch (_) {}
    };

    const onCityChange = async () => {
      const p = provInput ? provInput.value.trim() : '';
      const c = cityInput ? cityInput.value.trim() : '';
      try {
        const brgys = (p && c) ? await loadOpts('barangays', { province: p, city: c }) : [];
        fillList(brgyList, brgys);
        const posts = (p && c) ? await loadOpts('postals', { province: p, city: c }) : [];
        fillList(postalList, posts);
        if (brgyInput) brgyInput.value = '';
        if (postalInput) {
          if (posts.length === 1) postalInput.value = posts[0];
          else postalInput.value = '';
        }
        refresh();
      } catch (_) {}
    };

    inputs.forEach((inp) => {
      const field = inp.getAttribute('data-op-field') || '';
      if (field === 'contact_no') {
        inp.addEventListener('input', () => { inp.value = digitsOnly(inp.value).slice(0, 20); refresh(); });
        inp.addEventListener('blur', () => { inp.value = digitsOnly(inp.value).slice(0, 20); refresh(); });
      } else {
        inp.addEventListener('input', refresh);
      }
      inp.addEventListener('change', refresh);
    });

    if (btnCancel) btnCancel.addEventListener('click', reset);

    if (btnSave) {
      btnSave.addEventListener('click', async () => {
        if (!operatorId) return;
        btnSave.disabled = true;
        try {
          const fd = new FormData();
          fd.append('operator_id', operatorId);
          inputs.forEach((inp) => {
            const k = inp.getAttribute('data-op-field') || '';
            if (!k) return;
            fd.append(k, inp.value || '');
          });
          const res = await fetch(rootUrl + '/admin/api/module1/update_operator.php', { method: 'POST', body: fd });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'update_failed');
          inputs.forEach((inp) => { inp.setAttribute('data-initial', inp.value || ''); });
          refresh();
          showMsg('Saved.', 'success');
        } catch (e) {
          showMsg((e && e.message) ? e.message : 'Failed', 'error');
        } finally {
          btnSave.disabled = false;
        }
      });
    }

    if (provInput) {
      provInput.addEventListener('change', onProvinceChange);
      provInput.addEventListener('blur', onProvinceChange);
    }
    if (cityInput) {
      cityInput.addEventListener('change', onCityChange);
      cityInput.addEventListener('blur', onCityChange);
    }

    initLocationLists();
    refresh();
  })();
</script>
