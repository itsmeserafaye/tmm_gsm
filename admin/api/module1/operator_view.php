<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
require_login();
require_any_permission(['module1.read', 'module1.view', 'module1.write', 'module1.vehicles.write']);

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

$stmt = $db->prepare("SELECT id, COALESCE(NULLIF(name,''), NULLIF(full_name,'')) AS display_name, operator_type, address, contact_no, email, status, created_at FROM operators WHERE id=? LIMIT 1");
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

$st = (string) ($op['status'] ?? '');
$badge = match ($st) {
    'Approved' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
    'Pending' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
    'Inactive' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
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

$docs = [];
$stmtD = $db->prepare("SELECT doc_id, doc_type, file_path, uploaded_at FROM operator_documents WHERE operator_id=? ORDER BY uploaded_at DESC");
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

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contact</div>
            <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($displayContact); ?></div>
        </div>
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Created</div>
            <div class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars((string) ($op['created_at'] ?? '')); ?></div>
        </div>
    </div>

    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
        <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Address</div>
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars((string) ($op['address'] ?? '')); ?></div>
    </div>

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
                        ?>
                        <a href="<?php echo htmlspecialchars($href, ENT_QUOTES); ?>" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-all">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500">
                                    <i data-lucide="file" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-black text-slate-800 dark:text-white"><?php echo htmlspecialchars((string) ($d['doc_type'] ?? '')); ?></div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($dt); ?></div>
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
