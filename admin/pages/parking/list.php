<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module5.manage_terminal', 'module5.parking_fees']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

// Auto-fix missing tables
$db->query("CREATE TABLE IF NOT EXISTS terminals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    city VARCHAR(100),
    address TEXT,
    type VARCHAR(50) DEFAULT 'Terminal',
    capacity INT DEFAULT 0,
    category VARCHAR(100),
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$db->query("CREATE TABLE IF NOT EXISTS `facility_owners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT 'Person',
  `contact_info` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$db->query("CREATE TABLE IF NOT EXISTS `facility_agreements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `terminal_id` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `agreement_type` varchar(50) DEFAULT 'MOA',
  `reference_no` varchar(100) DEFAULT NULL,
  `rent_amount` decimal(12,2) DEFAULT '0.00',
  `rent_frequency` varchar(50) DEFAULT 'Monthly',
  `status` varchar(50) DEFAULT 'Active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `terms_summary` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `terminal_id` (`terminal_id`),
  KEY `owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$db->query("CREATE TABLE IF NOT EXISTS `facility_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `terminal_id` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `agreement_id` int(11) DEFAULT NULL,
  `doc_type` varchar(50) DEFAULT 'Document',
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `terminal_id` (`terminal_id`),
  KEY `agreement_id` (`agreement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");


$canManage = has_permission('module5.manage_terminal');

$qFilter = trim((string)($_GET['q'] ?? ''));
$ownerFilter = trim((string)($_GET['owner'] ?? ''));
$locationFilter = trim((string)($_GET['location'] ?? ''));
$permitFilter = trim((string)($_GET['permit'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$capacityFilter = trim((string)($_GET['capacity'] ?? ''));

$statParkingAreas = (int)($db->query("SELECT COUNT(*) AS c FROM terminals WHERE type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingSlotsFree = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Free' AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingSlotsOccupied = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Occupied' AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingPaymentsToday = (int)($db->query("SELECT COUNT(*) AS c FROM parking_payments pp JOIN parking_slots ps ON ps.slot_id=pp.slot_id JOIN terminals t ON t.id=ps.terminal_id WHERE DATE(pp.paid_at)=CURDATE() AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);

$owners = [];
$locations = [];
$statuses = [];
$capacities = [];

$ownerNameExpr = "NULL";
$faTidCol = '';
$faExists = (bool)($db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_agreements' LIMIT 1")?->fetch_row());
$foExists = (bool)($db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_owners' LIMIT 1")?->fetch_row());
if ($faExists && $foExists) {
  $faCols = [];
  $resCols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_agreements'");
  if ($resCols) while ($c = $resCols->fetch_assoc()) $faCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
  $tidCol = isset($faCols['terminal_id']) ? 'terminal_id' : (isset($faCols['facility_id']) ? 'facility_id' : '');
  $faTidCol = $tidCol;
  $statusCol = isset($faCols['status']) ? 'status' : '';
  $createdCol = isset($faCols['created_at']) ? 'created_at' : '';
  if ($tidCol !== '') {
    $order = $statusCol !== '' ? "FIELD(fa.$statusCol, 'Active', 'Expiring Soon', 'Expired', 'Terminated'), " : '';
    $order .= $createdCol !== '' ? "fa.$createdCol DESC" : "fa.id DESC";
    $ownerNameExpr = "(SELECT fo.name FROM facility_agreements fa JOIN facility_owners fo ON fa.owner_id = fo.id WHERE fa.$tidCol = terminals.id ORDER BY $order LIMIT 1)";
  }
}
if ($ownerNameExpr !== 'NULL') $ownerNameExpr = str_replace('t.id', 'terminals.id', $ownerNameExpr);

$resLoc = $db->query("SELECT DISTINCT TRIM(COALESCE(location,'')) AS location FROM terminals WHERE type='Parking' AND COALESCE(location,'')<>'' ORDER BY location ASC LIMIT 500");
if ($resLoc) while ($r = $resLoc->fetch_assoc()) { $l = trim((string)($r['location'] ?? '')); if ($l !== '') $locations[] = $l; }
$resCap = $db->query("SELECT DISTINCT COALESCE(capacity,0) AS capacity FROM terminals WHERE type='Parking' ORDER BY COALESCE(capacity,0) ASC LIMIT 500");
if ($resCap) while ($r = $resCap->fetch_assoc()) { $capacities[] = (int)($r['capacity'] ?? 0); }
$capacities = array_values(array_unique($capacities));
$resStats = $db->query("SELECT DISTINCT COALESCE(NULLIF(TRIM(status),''),'Active') AS status FROM terminals WHERE type='Parking' ORDER BY status ASC LIMIT 200");
if ($resStats) {
  $set = ['Active' => true, 'Inactive' => true, 'Archived' => true];
  while ($r = $resStats->fetch_assoc()) { $s = trim((string)($r['status'] ?? '')); if ($s !== '') $set[$s] = true; }
  $statuses = array_keys($set);
  sort($statuses, SORT_NATURAL | SORT_FLAG_CASE);
}
if ($foExists) {
  $resOwners = $db->query("SELECT DISTINCT TRIM(COALESCE(name,'')) AS name FROM facility_owners WHERE COALESCE(name,'')<>'' ORDER BY name ASC LIMIT 500");
  if ($resOwners) while ($r = $resOwners->fetch_assoc()) { $n = trim((string)($r['name'] ?? '')); if ($n !== '') $owners[] = $n; }
}

$permitAnyExpr = '';
try {
  $parts = [];
  $chkDocs = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_documents' LIMIT 1");
  if ($chkDocs && $chkDocs->fetch_row()) {
    $cols = [];
    $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_documents'");
    if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
    $dTidCol = isset($cols['terminal_id']) ? 'terminal_id' : (isset($cols['facility_id']) ? 'facility_id' : '');
    $dTypeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['type']) ? 'type' : (isset($cols['document_type']) ? 'document_type' : ''));
    if ($dTidCol !== '' && $dTypeCol !== '') $parts[] = "EXISTS (SELECT 1 FROM facility_documents d WHERE d.$dTidCol=terminals.id AND LOWER(COALESCE(d.$dTypeCol,'')) LIKE '%permit%')";
  }
  $chkPerm = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits' LIMIT 1");
  if ($chkPerm && $chkPerm->fetch_row()) {
    $cols = [];
    $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits'");
    if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
    $pTidCol = isset($cols['terminal_id']) ? 'terminal_id' : (isset($cols['facility_id']) ? 'facility_id' : '');
    if ($pTidCol !== '') $parts[] = "EXISTS (SELECT 1 FROM terminal_permits p2 WHERE p2.$pTidCol=terminals.id)";
  }
  if ($parts) $permitAnyExpr = '(' . implode(' OR ', $parts) . ')';
} catch (Throwable $e) {}

$parkingRows = [];
$where = "type='Parking'";
$params = [];
$types = '';
if ($qFilter !== '') {
  $where .= " AND (name LIKE ? OR COALESCE(location,'') LIKE ? OR COALESCE(address,'') LIKE ?)";
  $like = '%' . $qFilter . '%';
  $types .= 'sss';
  $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($ownerFilter !== '' && $faExists && $foExists && $faTidCol !== '') {
  $where .= " AND EXISTS (SELECT 1 FROM facility_agreements fa JOIN facility_owners fo ON fa.owner_id=fo.id WHERE fa.$faTidCol=terminals.id AND fo.name=?)";
  $types .= 's';
  $params[] = $ownerFilter;
}
if ($locationFilter !== '') { $where .= " AND COALESCE(location,'') = ?"; $types .= 's'; $params[] = $locationFilter; }
if ($statusFilter !== '') {
  if (strcasecmp($statusFilter, 'Active') === 0) $where .= " AND COALESCE(NULLIF(TRIM(status),''),'Active')='Active'";
  else { $where .= " AND COALESCE(NULLIF(TRIM(status),''),'Active')=?"; $types .= 's'; $params[] = $statusFilter; }
}
if ($capacityFilter !== '') { $where .= " AND COALESCE(capacity,0)=?"; $types .= 'i'; $params[] = (int)$capacityFilter; }
if ($permitFilter !== '' && $permitAnyExpr !== '') {
  $pv = strtolower($permitFilter);
  if ($pv === 'yes' || $pv === 'permitted' || $pv === 'permit') $where .= " AND $permitAnyExpr";
  if ($pv === 'no' || $pv === 'not_permitted' || $pv === 'n/a') $where .= " AND NOT $permitAnyExpr";
}

$sql = "SELECT id, name, location, address, capacity, $ownerNameExpr as owner_name FROM terminals WHERE $where ORDER BY name ASC LIMIT 500";
$resP = null;
if ($types !== '') {
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resP = $stmt->get_result();
  } else {
    $resP = false;
  }
} else {
  $resP = $db->query($sql);
}
if ($resP) while ($r = $resP->fetch_assoc()) $parkingRows[] = $r;

$permCountByTerminal = [];
try {
  $idSet = [];
  foreach ($parkingRows as $r) {
    $tid = (int)($r['id'] ?? 0);
    if ($tid > 0) $idSet[$tid] = true;
  }

  if ($idSet) {
    $chkDocs = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_documents' LIMIT 1");
    if ($chkDocs && $chkDocs->fetch_row()) {
      $cols = [];
      $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_documents'");
      if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
      $tidCol = isset($cols['terminal_id']) ? 'terminal_id' : (isset($cols['facility_id']) ? 'facility_id' : '');
      $typeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['type']) ? 'type' : (isset($cols['document_type']) ? 'document_type' : ''));
      if ($tidCol !== '' && $typeCol !== '') {
        $resPerm = $db->query("SELECT $tidCol AS tid, COUNT(*) AS c FROM facility_documents WHERE LOWER(COALESCE($typeCol,'')) LIKE '%permit%' GROUP BY $tidCol");
        if ($resPerm) {
          while ($row = $resPerm->fetch_assoc()) {
            $tid = (int)($row['tid'] ?? 0);
            if ($tid > 0 && isset($idSet[$tid])) $permCountByTerminal[$tid] = ($permCountByTerminal[$tid] ?? 0) + (int)($row['c'] ?? 0);
          }
        }
      }
    }

    $chkPerm = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits' LIMIT 1");
    if ($chkPerm && $chkPerm->fetch_row()) {
      $cols = [];
      $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits'");
      if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
      $tidCol = isset($cols['terminal_id']) ? 'terminal_id' : (isset($cols['facility_id']) ? 'facility_id' : '');
      if ($tidCol !== '') {
        $resPerm = $db->query("SELECT $tidCol AS tid, COUNT(*) AS c FROM terminal_permits GROUP BY $tidCol");
        if ($resPerm) {
          while ($row = $resPerm->fetch_assoc()) {
            $tid = (int)($row['tid'] ?? 0);
            if ($tid > 0 && isset($idSet[$tid])) $permCountByTerminal[$tid] = ($permCountByTerminal[$tid] ?? 0) + (int)($row['c'] ?? 0);
          }
        }
      }
    }
  }
} catch (Throwable $e) {}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Parking</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage parking areas, slots, and payments.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=parking/slots-payments" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="layout-grid" class="w-4 h-4"></i>
        Slots & Payments
      </a>
      <a href="?page=module5/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Terminals
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Parking Areas</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingAreas; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Free Slots</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingSlotsFree; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Occupied Slots</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingSlotsOccupied; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Payments Today</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statParkingPaymentsToday; ?></div>
    </div>
  </div>

  <?php if ($canManage): ?>
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div id="btnToggleCreateParking" class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
        <div class="flex items-center gap-3">
          <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
            <i data-lucide="plus" class="w-5 h-5"></i>
          </div>
          <h2 class="text-base font-bold text-slate-900 dark:text-white flex-1">Create Parking Area</h2>
          <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400"></i>
        </div>
      </div>
      <div id="createParkingPanel" class="p-6 hidden">
        <form id="formParking" class="grid grid-cols-1 md:grid-cols-12 gap-4" novalidate enctype="multipart/form-data">
          <input type="hidden" name="type" value="Parking">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="agreement_id" value="">

          <!-- Tabs -->
          <div class="md:col-span-12 border-b border-slate-200 dark:border-slate-700 mb-2">
            <nav class="-mb-px flex gap-6" aria-label="Tabs">
              <button type="button" class="tab-btn border-b-2 border-blue-600 py-2 px-1 text-sm font-bold text-blue-600 dark:text-blue-400" data-target="tab-p-general">General</button>
              <button type="button" class="tab-btn border-b-2 border-transparent py-2 px-1 text-sm font-bold text-slate-500 dark:text-slate-400 hover:border-slate-300 hover:text-slate-700 dark:hover:text-slate-200" data-target="tab-p-owner">Owner & Agreement</button>
              <button type="button" class="tab-btn border-b-2 border-transparent py-2 px-1 text-sm font-bold text-slate-500 dark:text-slate-400 hover:border-slate-300 hover:text-slate-700 dark:hover:text-slate-200" data-target="tab-p-docs">Documents</button>
            </nav>
          </div>

          <!-- Tab: General -->
          <div id="tab-p-general" class="tab-pane md:col-span-12 grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-3">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Name *</label>
              <input name="name" required minlength="3" maxlength="80" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., MCU Parking">
            </div>
            <div class="md:col-span-5">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Location *</label>
              <input name="location" required maxlength="120" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Caloocan City">
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Address</label>
              <input name="address" maxlength="180" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., EDSA, Monumento">
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Capacity</label>
              <input name="capacity" type="number" min="0" max="5000" step="1" value="0" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 120">
            </div>
          </div>

          <!-- Tab: Owner & Agreement -->
          <div id="tab-p-owner" class="tab-pane hidden md:col-span-12 grid grid-cols-1 md:grid-cols-12 gap-4">
             <div class="md:col-span-12 border-b border-slate-100 dark:border-slate-800 pb-2 mb-2">
               <h3 class="text-sm font-black text-slate-800 dark:text-slate-200">Owner Information</h3>
             </div>
             <div class="md:col-span-6">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Owner Name *</label>
               <input name="owner_name" maxlength="255" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Person / Company / Coop Name">
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Type</label>
               <select name="owner_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                 <option value="Person">Person</option>
                 <option value="Cooperative">Cooperative</option>
                 <option value="Company">Company</option>
                 <option value="Government">Government</option>
                 <option value="Other" selected>Other</option>
               </select>
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contact Info</label>
               <input name="owner_contact" maxlength="255" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Phone / Email">
             </div>

             <div class="md:col-span-12 border-b border-slate-100 dark:border-slate-800 pb-2 mb-2 mt-2">
               <h3 class="text-sm font-black text-slate-800 dark:text-slate-200">Agreement / Contract</h3>
             </div>
             <div class="md:col-span-4">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Agreement Type</label>
               <select name="agreement_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                 <option value="MOA">MOA</option>
                 <option value="Lease Contract">Lease Contract</option>
                 <option value="Rental Agreement">Rental Agreement</option>
                 <option value="Other">Other</option>
               </select>
             </div>
             <div class="md:col-span-4">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Reference No.</label>
               <input name="agreement_reference_no" maxlength="100" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Optional">
             </div>
             <div class="md:col-span-4">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
               <select name="agreement_status" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                 <option value="Active">Active</option>
                 <option value="Expiring Soon">Expiring Soon</option>
                 <option value="Expired">Expired</option>
                 <option value="Terminated">Terminated</option>
               </select>
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Start Date</label>
               <input name="start_date" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">End Date</label>
               <input name="end_date" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Rent Amount</label>
               <input name="rent_amount" type="number" step="0.01" min="0" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="0.00">
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Frequency</label>
               <select name="rent_frequency" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                 <option value="Monthly">Monthly</option>
                 <option value="Weekly">Weekly</option>
                 <option value="Annual">Annual</option>
                 <option value="One-time">One-time</option>
               </select>
             </div>
             <div class="md:col-span-12">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Terms Summary</label>
               <textarea name="terms_summary" rows="2" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Short notes about the agreement..."></textarea>
             </div>
          </div>

          <!-- Tab: Documents -->
          <div id="tab-p-docs" class="tab-pane hidden md:col-span-12 space-y-4">
             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
               <div>
                 <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">MOA File (PDF/Image)</label>
                 <input name="moa_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
               </div>
               <div>
                 <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contract File</label>
                 <input name="contract_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
               </div>
               <div>
                 <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Permit / Clearance</label>
                 <input name="permit_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
               </div>
               <div>
                 <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Other Attachments (Multiple)</label>
                 <input name="other_attachments[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
               </div>
             </div>
             <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md text-xs text-blue-800 dark:text-blue-300">
               <strong>Note:</strong> Uploading new files will add to the existing documents list.
             </div>
          </div>

          <div class="md:col-span-12 flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-700">
            <button id="btnSaveParking" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
      <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
        <input type="hidden" name="page" value="parking/list">
        <div class="md:col-span-6">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Search</label>
          <div class="relative">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <input name="q" value="<?php echo htmlspecialchars($qFilter); ?>" class="w-full pl-9 pr-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Parking name / location / address">
          </div>
        </div>
        <div class="md:col-span-3">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Owner</label>
          <select name="owner" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" <?php echo ($faExists && $foExists && $faTidCol !== '') ? '' : 'disabled'; ?>>
            <option value="" <?php echo $ownerFilter === '' ? 'selected' : ''; ?>>All Owners</option>
            <?php foreach ($owners as $o): ?>
              <option value="<?php echo htmlspecialchars($o); ?>" <?php echo $ownerFilter === $o ? 'selected' : ''; ?>><?php echo htmlspecialchars($o); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-3">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Location</label>
          <select name="location" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="" <?php echo $locationFilter === '' ? 'selected' : ''; ?>>All Locations</option>
            <?php foreach ($locations as $l): ?>
              <option value="<?php echo htmlspecialchars($l); ?>" <?php echo $locationFilter === $l ? 'selected' : ''; ?>><?php echo htmlspecialchars($l); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Permitted</label>
          <select name="permit" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="" <?php echo $permitFilter === '' ? 'selected' : ''; ?>>All</option>
            <option value="yes" <?php echo strtolower($permitFilter) === 'yes' ? 'selected' : ''; ?>>Permitted</option>
            <option value="no" <?php echo strtolower($permitFilter) === 'no' ? 'selected' : ''; ?>>No Permit</option>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
          <select name="status" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All Status</option>
            <?php foreach ($statuses as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Capacity</label>
          <select name="capacity" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="" <?php echo $capacityFilter === '' ? 'selected' : ''; ?>>All Capacities</option>
            <?php foreach ($capacities as $cap): ?>
              <option value="<?php echo (int)$cap; ?>" <?php echo ((string)$capacityFilter !== '' && (int)$capacityFilter === (int)$cap) ? 'selected' : ''; ?>><?php echo (int)$cap; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-4 flex items-center gap-2">
          <button class="flex-1 px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold transition-colors shadow-sm">Apply</button>
          <a href="?page=parking/list" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-colors hover:bg-slate-50 dark:hover:bg-slate-700" title="Reset">
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
          </a>
          <?php if (has_permission('reports.export')): ?>
            <?php
              $qs = http_build_query([
                'type' => 'Parking',
                'q' => $qFilter,
                'owner' => $ownerFilter,
                'location' => $locationFilter,
                'permit' => $permitFilter,
                'status' => $statusFilter,
                'capacity' => $capacityFilter,
              ]);
              $csvUrl = $rootUrl . '/admin/api/module5/export_terminals_csv.php?' . $qs;
              $excelUrl = $rootUrl . '/admin/api/module5/export_terminals_csv.php?' . $qs . '&format=excel';
              $printUrl = $rootUrl . '/admin/api/module5/print_terminals.php?' . $qs;
            ?>
            <a href="<?php echo htmlspecialchars($csvUrl); ?>" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-colors hover:bg-slate-50 dark:hover:bg-slate-700" title="Export CSV">
              <i data-lucide="download" class="w-4 h-4"></i>
            </a>
            <a href="<?php echo htmlspecialchars($excelUrl); ?>" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-colors hover:bg-slate-50 dark:hover:bg-slate-700" title="Export Excel">
              <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
            </a>
            <a href="<?php echo htmlspecialchars($printUrl); ?>" target="_blank" rel="noopener" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-colors hover:bg-slate-50 dark:hover:bg-slate-700" title="Print Report" data-print-url="<?php echo htmlspecialchars($printUrl); ?>" data-report-name="Parking List Report" onclick="return window.tmmPrintLink && window.tmmPrintLink(this);">
              <i data-lucide="printer" class="w-4 h-4"></i>
            </a>
          <?php elseif (has_permission('module5.manage_terminal')): ?>
            <?php
              $qs = http_build_query([
                'type' => 'Parking',
                'q' => $qFilter,
                'owner' => $ownerFilter,
                'location' => $locationFilter,
                'permit' => $permitFilter,
                'status' => $statusFilter,
                'capacity' => $capacityFilter,
              ]);
              $printUrl = $rootUrl . '/admin/api/module5/print_terminals.php?' . $qs;
            ?>
            <a href="<?php echo htmlspecialchars($printUrl); ?>" target="_blank" rel="noopener" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-colors hover:bg-slate-50 dark:hover:bg-slate-700" title="Print Report" data-print-url="<?php echo htmlspecialchars($printUrl); ?>" data-report-name="Parking List Report" onclick="return window.tmmPrintLink && window.tmmPrintLink(this);">
              <i data-lucide="printer" class="w-4 h-4"></i>
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Name</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Owner</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Location</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Capacity</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800" id="parkingBody">
          <?php if ($parkingRows): ?>
            <?php foreach ($parkingRows as $t): ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-6 font-black text-slate-900 dark:text-white">
                  <?php echo htmlspecialchars((string)($t['name'] ?? '')); ?>
                  <?php
                    $tidBadge = (int)($t['id'] ?? 0);
                    $pc = (int)($permCountByTerminal[$tidBadge] ?? 0);
                    $hasPermit = $pc > 0;
                  ?>
                  <span class="ml-2 inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-black <?php echo $hasPermit ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/20 dark:text-rose-300'; ?>">
                    <?php echo $hasPermit ? 'Permit on file' : 'No permit'; ?>
                  </span>
                </td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">
                  <?php $owner = trim((string)($t['owner_name'] ?? '')); ?>
                  <?php if ($owner): ?>
                    <div class="flex items-center gap-2">
                      <span><?php echo htmlspecialchars($owner); ?></span>
                      <button type="button" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors" data-terminal-info="<?php echo (int)($t['id'] ?? 0); ?>" title="View Details">
                        <i data-lucide="info" class="w-4 h-4"></i>
                      </button>
                      <?php if ($canManage): ?>
                        <button type="button" class="text-slate-600 hover:text-blue-700 dark:text-slate-300 dark:hover:text-blue-300 transition-colors" data-terminal-agreement="<?php echo (int)($t['id'] ?? 0); ?>" title="Edit Details">
                          <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <span class="text-slate-400 italic text-xs">Unspecified</span>
                    <?php if ($canManage): ?>
                      <button type="button" class="ml-1 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors" data-terminal-agreement="<?php echo (int)($t['id'] ?? 0); ?>" title="Add Details">
                        <i data-lucide="plus-circle" class="w-4 h-4 inline"></i>
                      </button>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars((string)($t['location'] ?? ($t['address'] ?? ''))); ?></td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold"><?php echo (int)($t['capacity'] ?? 0); ?></td>
                <td class="py-4 px-4 text-right">
                  <?php if ($canManage): ?>
                    <button type="button" title="Edit" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors mr-2" data-parking-edit="<?php echo (int)($t['id'] ?? 0); ?>">
                      <i data-lucide="pencil" class="w-4 h-4"></i>
                      <span class="sr-only">Edit</span>
                    </button>
                  <?php endif; ?>
                  <a title="Slots" aria-label="Slots" href="?page=parking/slots-payments&<?php echo http_build_query(['terminal_id'=>(int)($t['id'] ?? 0),'tab'=>'slots']); ?>" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors mr-2">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    <span class="sr-only">Slots</span>
                  </a>
                  <a title="Payments" aria-label="Payments" href="?page=parking/slots-payments&<?php echo http_build_query(['terminal_id'=>(int)($t['id'] ?? 0),'tab'=>'payments']); ?>" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                    <span class="sr-only">Payments</span>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" class="py-12 text-center text-slate-500 font-medium italic">No parking areas yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="terminalInfoModal" class="fixed inset-0 z-[200] hidden">
  <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-2xl rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
      <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <div class="text-sm font-black text-slate-900 dark:text-white">Parking Details</div>
        <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div id="terminalInfoContent" class="p-6 overflow-y-auto space-y-6">
        <div class="text-center text-slate-500 italic py-10">Loading...</div>
      </div>
    </div>
  </div>
</div>

<?php if ($canManage): ?>
<div id="parkingAgreementModal" class="fixed inset-0 z-[210] hidden">
  <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-4xl rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
      <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
        <div>
          <div class="text-sm font-black text-slate-900 dark:text-white">Parking Agreement</div>
          <div id="parkingAgreementSub" class="text-xs text-slate-500 dark:text-slate-400 font-semibold"></div>
        </div>
        <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div class="p-6 overflow-y-auto">
        <form id="formParkingAgreement" class="grid grid-cols-1 md:grid-cols-12 gap-4" novalidate enctype="multipart/form-data">
          <input type="hidden" name="terminal_id" value="">
          <input type="hidden" name="agreement_id" value="">
          <div class="md:col-span-6">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Owner Information</div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
              <div class="md:col-span-12">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Owner Name *</label>
                <input name="owner_name" required maxlength="255" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Type</label>
                <select name="owner_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="Person">Person</option>
                  <option value="Cooperative">Cooperative</option>
                  <option value="Company">Company</option>
                  <option value="Government">Government</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contact</label>
                <input name="owner_contact" maxlength="255" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
            </div>
          </div>
          <div class="md:col-span-6">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Agreement</div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Type</label>
                <select name="agreement_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="MOA">MOA</option>
                  <option value="Lease Contract">Lease Contract</option>
                  <option value="Rental Agreement">Rental Agreement</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Ref No.</label>
                <input name="agreement_reference_no" maxlength="100" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div class="md:col-span-4">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Rent Amount</label>
                <input name="rent_amount" type="number" step="0.01" min="0" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div class="md:col-span-4">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Frequency</label>
                <select name="rent_frequency" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="Monthly">Monthly</option>
                  <option value="Weekly">Weekly</option>
                  <option value="Annual">Annual</option>
                  <option value="One-time">One-time</option>
                </select>
              </div>
              <div class="md:col-span-4">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
                <select name="agreement_status" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="Active">Active</option>
                  <option value="Expiring Soon">Expiring Soon</option>
                  <option value="Expired">Expired</option>
                  <option value="Terminated">Terminated</option>
                </select>
              </div>
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Start Date *</label>
                <input name="start_date" type="date" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">End Date *</label>
                <input name="end_date" type="date" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
            </div>
          </div>
          <div class="md:col-span-12">
            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Terms Summary</label>
            <textarea name="terms_summary" rows="3" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold"></textarea>
          </div>
          <div class="md:col-span-12 grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-4">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">MOA</label>
              <input name="moa_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-xs file:font-black file:text-white hover:file:bg-blue-800">
            </div>
            <div class="md:col-span-4">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contract</label>
              <input name="contract_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-xs file:font-black file:text-white hover:file:bg-blue-800">
            </div>
            <div class="md:col-span-4">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Permit</label>
              <input name="permit_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-xs file:font-black file:text-white hover:file:bg-blue-800">
            </div>
            <div class="md:col-span-12">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Other Attachments</label>
              <input name="other_attachments[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-xs file:font-black file:text-white hover:file:bg-blue-800">
            </div>
          </div>
          <div class="md:col-span-12 flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-700">
            <button type="button" data-modal-cancel class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">Cancel</button>
            <button id="btnSaveParkingAgreement" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save Details</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
  (function () {
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const canManage = <?php echo json_encode($canManage); ?>;

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

    // const search = document.getElementById('parkingSearchTerm');
    // const body = document.getElementById('parkingBody');
    // if (search && body) {
    //   search.addEventListener('input', () => {
    //     const q = (search.value || '').toString().trim().toLowerCase();
    //     Array.from(body.querySelectorAll('tr')).forEach((tr) => {
    //       const txt = (tr.textContent || '').toLowerCase();
    //       tr.style.display = (!q || txt.includes(q)) ? '' : 'none';
    //     });
    //   });
    // }

    const form = document.getElementById('formParking');
    const btn = document.getElementById('btnSaveParking');
    if (canManage && form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/save_terminal.php', { method: 'POST', body: new FormData(form) });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && data.message) ? data.message : 'save_failed');
          showToast('Parking saved.');
          setTimeout(() => { window.location.reload(); }, 250);
        } catch (err) {
          showToast((err && err.message) ? err.message : 'Failed', 'error');
          btn.disabled = false;
          btn.textContent = original;
        }
      });
    }

    // --- Enhanced Logic ---
    // Toggle Create Parking
    const btnToggle = document.getElementById('btnToggleCreateParking');
    const panelCreate = document.getElementById('createParkingPanel');
    if (btnToggle && panelCreate) {
      btnToggle.addEventListener('click', () => {
        panelCreate.classList.toggle('hidden');
        // Rotate chevron if possible
        const icon = btnToggle.querySelector('.lucide-chevron-down') || btnToggle.querySelector('[data-lucide="chevron-down"]');
        if (icon) {
            const isHidden = panelCreate.classList.contains('hidden');
            icon.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(180deg)';
            icon.style.transition = 'transform 0.2s';
        }
      });
    }

    if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.getAttribute('data-target');
        const nav = btn.parentElement;
        nav.querySelectorAll('.tab-btn').forEach(b => {
           if (b === btn) {
             b.classList.remove('border-transparent', 'text-slate-500', 'hover:border-slate-300', 'hover:text-slate-700', 'dark:text-slate-400', 'dark:hover:text-slate-200');
             b.classList.add('border-blue-600', 'text-blue-600', 'dark:text-blue-400');
           } else {
             b.classList.add('border-transparent', 'text-slate-500', 'hover:border-slate-300', 'hover:text-slate-700', 'dark:text-slate-400', 'dark:hover:text-slate-200');
             b.classList.remove('border-blue-600', 'text-blue-600', 'dark:text-blue-400');
           }
        });
        const container = nav.closest('form');
        if (container) {
          container.querySelectorAll('.tab-pane').forEach(p => {
            p.classList.toggle('hidden', p.id !== target);
          });
        }
      });
    });

    // Info Modal
    const infoModal = document.getElementById('terminalInfoModal');
    const infoContent = document.getElementById('terminalInfoContent');
    function closeInfoModal() { if (infoModal) infoModal.classList.add('hidden'); }
    if (infoModal) {
       infoModal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeInfoModal));
       const bd = infoModal.querySelector('[data-modal-backdrop]');
       if (bd) bd.addEventListener('click', closeInfoModal);
    }
    
    async function showTerminalInfo(id) {
       if (!infoModal || !infoContent) return;
       infoModal.classList.remove('hidden');
       infoContent.innerHTML = '<div class="text-center text-slate-500 italic py-10">Loading...</div>';
       try {
         const res = await fetch(rootUrl + '/admin/api/module5/get_terminal_details.php?id=' + id);
         const data = await res.json();
         if (!data || !data.success) throw new Error(data.message || 'Error');
         
         const t = data.terminal || {};
         const a = data.agreement || {};
         const d = data.documents || [];
         const owner = a.owner_name || 'Unspecified';
         const contact = a.owner_contact || '-';
         
         const docsHtml = d.length ? d.map(doc => `
           <div class="flex items-center justify-between p-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
             <div class="flex items-center gap-3">
               <div class="p-2 rounded bg-white dark:bg-slate-700 text-blue-600"><i data-lucide="file-text" class="w-4 h-4"></i></div>
               <div><div class="font-bold text-slate-900 dark:text-white text-sm">${doc.doc_type || 'Document'}</div><div class="text-xs text-slate-500">${doc.uploaded_at || ''}</div></div>
             </div>
             <a href="${rootUrl}/uploads/${doc.file_path}" target="_blank" class="text-xs font-bold text-blue-600 hover:underline">Download</a>
           </div>
         `).join('') : '<div class="text-slate-500 italic text-sm">No documents attached.</div>';

         infoContent.innerHTML = `
           <div class="space-y-6">
             <div class="grid grid-cols-2 gap-4">
               <div><div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Name</div><div class="font-black text-slate-900 dark:text-white text-lg">${t.name}</div></div>
               <div><div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Location</div><div class="font-semibold text-slate-700 dark:text-slate-200">${t.location || '-'}</div></div>
             </div>
             <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
               <h4 class="font-black text-slate-900 dark:text-white mb-3 flex items-center gap-2"><i data-lucide="user" class="w-4 h-4"></i> Owner Information</h4>
               <div class="grid grid-cols-2 gap-4">
                 <div><div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Owner Name</div><div class="font-bold text-slate-900 dark:text-white">${owner}</div></div>
                 <div><div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Contact</div><div class="font-semibold text-slate-700 dark:text-slate-200">${contact}</div></div>
               </div>
             </div>
             <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
               <h4 class="font-black text-slate-900 dark:text-white mb-3 flex items-center gap-2"><i data-lucide="file-check" class="w-4 h-4"></i> Agreement Details</h4>
               ${a.id ? `
                 <div class="grid grid-cols-2 gap-4 text-sm">
                   <div><span class="text-slate-500">Type:</span> <span class="font-bold dark:text-white">${a.agreement_type}</span></div>
                   <div><span class="text-slate-500">Status:</span> <span class="font-bold ${a.status==='Active'?'text-emerald-600':'text-rose-600'}">${a.status}</span></div>
                   <div><span class="text-slate-500">Rent:</span> <span class="font-bold dark:text-white">${parseFloat(a.rent_amount||0).toFixed(2)} / ${a.rent_frequency}</span></div>
                   <div><span class="text-slate-500">Ref No:</span> <span class="font-bold dark:text-white">${a.reference_no||'-'}</span></div>
                   <div><span class="text-slate-500">Start:</span> <span class="font-bold dark:text-white">${a.start_date||'-'}</span></div>
                   <div><span class="text-slate-500">End:</span> <span class="font-bold dark:text-white">${a.end_date||'-'}</span></div>
                   <div class="col-span-2"><span class="text-slate-500">Duration:</span> <span class="font-bold dark:text-white">${a.duration_computed||'-'}</span></div>
                   <div class="col-span-2 bg-slate-50 dark:bg-slate-800 p-3 rounded text-slate-600 dark:text-slate-300 italic text-xs border border-slate-200 dark:border-slate-700">${a.terms_summary||'No terms summary.'}</div>
                 </div>
               ` : '<div class="text-slate-500 italic">No active agreement found.</div>'}
             </div>
             <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
               <h4 class="font-black text-slate-900 dark:text-white mb-3 flex items-center gap-2"><i data-lucide="folder" class="w-4 h-4"></i> Documents</h4>
               <div class="space-y-2">${docsHtml}</div>
             </div>
           </div>
         `;
         if (window.lucide) window.lucide.createIcons();
       } catch (e) {
         infoContent.innerHTML = '<div class="text-center text-rose-500 font-bold py-10">Failed to load details.</div>';
       }
    }
    document.addEventListener('click', (e) => {
       const btn = e.target.closest('[data-terminal-info]');
       if (btn) showTerminalInfo(btn.getAttribute('data-terminal-info'));
    });

    const agreeModal = document.getElementById('parkingAgreementModal');
    const agreeSub = document.getElementById('parkingAgreementSub');
    const agreeForm = document.getElementById('formParkingAgreement');
    const agreeSaveBtn = document.getElementById('btnSaveParkingAgreement');
    function closeAgreeModal() { if (agreeModal) agreeModal.classList.add('hidden'); }
    function openAgreeModal() { if (agreeModal) agreeModal.classList.remove('hidden'); if (window.lucide) window.lucide.createIcons(); }
    if (agreeModal) {
      const closeBtn = agreeModal.querySelector('[data-modal-close]');
      const cancelBtn = agreeModal.querySelector('[data-modal-cancel]');
      const bd = agreeModal.querySelector('[data-modal-backdrop]');
      if (closeBtn) closeBtn.addEventListener('click', closeAgreeModal);
      if (cancelBtn) cancelBtn.addEventListener('click', closeAgreeModal);
      if (bd) bd.addEventListener('click', closeAgreeModal);
    }

    function setAgreeValue(name, value) {
      if (!agreeForm) return;
      const el = agreeForm.querySelector(`[name="${name}"]`);
      if (!el) return;
      el.value = (value === null || value === undefined) ? '' : String(value);
    }

    async function loadAgreementIntoModal(terminalId) {
      if (!agreeForm) return;
      setAgreeValue('terminal_id', terminalId);
      setAgreeValue('agreement_id', '');
      setAgreeValue('owner_name', '');
      setAgreeValue('owner_type', 'Other');
      setAgreeValue('owner_contact', '');
      setAgreeValue('agreement_type', 'MOA');
      setAgreeValue('agreement_reference_no', '');
      setAgreeValue('rent_amount', '');
      setAgreeValue('rent_frequency', 'Monthly');
      setAgreeValue('agreement_status', 'Active');
      setAgreeValue('start_date', '');
      setAgreeValue('end_date', '');
      setAgreeValue('terms_summary', '');
      if (agreeSub) agreeSub.textContent = 'Parking ID: ' + String(terminalId);
      try {
        const res = await fetch(rootUrl + '/admin/api/module5/get_terminal_details.php?id=' + encodeURIComponent(String(terminalId)));
        const data = await res.json();
        if (data && data.success) {
          const t = data.terminal || {};
          const a = data.agreement || {};
          if (agreeSub) agreeSub.textContent = (t.name ? String(t.name) : 'Parking') + ' • Agreement';
          if (a && a.id) {
            setAgreeValue('agreement_id', a.id);
            setAgreeValue('agreement_type', a.agreement_type);
            setAgreeValue('agreement_reference_no', a.reference_no);
            setAgreeValue('rent_amount', a.rent_amount);
            setAgreeValue('rent_frequency', a.rent_frequency);
            setAgreeValue('agreement_status', a.status);
            setAgreeValue('start_date', a.start_date);
            setAgreeValue('end_date', a.end_date);
            setAgreeValue('terms_summary', a.terms_summary);
          }
          setAgreeValue('owner_name', a.owner_name);
          setAgreeValue('owner_type', a.owner_type || 'Other');
          setAgreeValue('owner_contact', a.owner_contact);
        }
      } catch (_) {}
    }

    if (canManage) {
      document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-terminal-agreement]');
        if (!btn) return;
        const id = Number(btn.getAttribute('data-terminal-agreement') || 0);
        if (!id) return;
        await loadAgreementIntoModal(id);
        openAgreeModal();
      });
    }

    if (agreeForm && agreeSaveBtn) {
      agreeForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!agreeForm.checkValidity()) { agreeForm.reportValidity(); return; }
        agreeSaveBtn.disabled = true;
        const prev = agreeSaveBtn.textContent;
        agreeSaveBtn.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/save_terminal_agreement.php', { method: 'POST', body: new FormData(agreeForm) });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && data.message) ? data.message : 'save_failed');
          showToast('Details saved.');
          closeAgreeModal();
          setTimeout(() => { window.location.reload(); }, 350);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          agreeSaveBtn.disabled = false;
          agreeSaveBtn.textContent = prev;
        }
      });
    }

    // Edit Logic
    document.addEventListener('click', async (e) => {
       const btn = e.target.closest('[data-parking-edit]');
       if (!btn) return;
       if (!canManage) { showToast('You do not have permission to edit parking areas.', 'error'); return; }
       const id = btn.getAttribute('data-parking-edit');
       if (!id) return;
       
       if (panelCreate && panelCreate.classList.contains('hidden')) {
          if (btnToggle) btnToggle.click();
          else panelCreate.classList.remove('hidden');
       }

       if (form) {
          form.scrollIntoView({ behavior: 'smooth' });
          form.reset();
          const firstTab = document.querySelector('.tab-btn[data-target="tab-p-general"]');
          if (firstTab) firstTab.click();
          
          const set = (n, v) => {
             const el = form.querySelector(`[name="${n}"]`);
             if (el) el.value = (v===null||v===undefined)?'':v;
          };
          
          const saveBtn = document.getElementById('btnSaveParking');
          const originalText = saveBtn ? saveBtn.textContent : 'Save';
          if (saveBtn) saveBtn.textContent = 'Loading...';
          
          try {
             const res = await fetch(rootUrl + '/admin/api/module5/get_terminal_details.php?id=' + id);
             const data = await res.json();
             if (saveBtn) saveBtn.textContent = 'Update';
             
             if (data && data.success) {
                const t = data.terminal || {};
                const a = data.agreement || {};
                
                set('id', t.id);
                set('name', t.name);
                set('location', t.location);
                set('address', t.address);
                set('capacity', t.capacity);
                
                set('owner_name', a.owner_name);
                set('owner_type', a.owner_type || 'Other');
                set('owner_contact', a.owner_contact);
                
                if (a.id) {
                   set('agreement_id', a.id);
                   set('agreement_type', a.agreement_type);
                   set('agreement_reference_no', a.reference_no);
                   set('agreement_status', a.status);
                   set('start_date', a.start_date);
                   set('end_date', a.end_date);
                   set('rent_amount', a.rent_amount);
                   set('rent_frequency', a.rent_frequency);
                   set('terms_summary', a.terms_summary);
                }
                
                const title = document.querySelector('#btnToggleCreateParking h2');
                if (title) title.textContent = 'Edit Parking Area';
             } else {
                throw new Error((data && data.message) ? data.message : 'Failed to load parking details.');
             }
          } catch (err) {
             showToast((err && err.message) ? err.message : 'Failed to load details.', 'error');
             if (saveBtn) saveBtn.textContent = originalText;
          }
       } else {
          showToast('Edit form is not available on this page.', 'error');
       }
    });
  })();
</script>

