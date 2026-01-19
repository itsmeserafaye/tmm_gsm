<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module5.view','parking.manage']);
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100">
    <?php
    require_once __DIR__ . '/../../includes/db.php';
    $db = db();

    // Handle Form Submissions (Fallback or standard POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!has_permission('parking.manage')) {
            http_response_code(403);
            echo 'forbidden';
            exit;
        }
        // Record Payment
        if (isset($_POST['action']) && $_POST['action'] === 'record_payment') {
            $plate = $db->real_escape_string(strtoupper($_POST['vehicle_plate']));
            $area_id = !empty($_POST['parking_area_id']) ? (int)$_POST['parking_area_id'] : "NULL";
            $amount = (float)$_POST['amount'];
            $duration = (int)$_POST['duration_hours'];
            $method = $db->real_escape_string($_POST['payment_method']);
            $ref = $db->real_escape_string($_POST['reference_no'] ?? '');
            
            // Insert
            $sql = "INSERT INTO parking_transactions (vehicle_plate, parking_area_id, amount, duration_hours, payment_method, reference_no, status, created_at) 
                    VALUES ('$plate', $area_id, $amount, $duration, '$method', '$ref', 'Paid', NOW())";
            if($db->query($sql)) {
                echo "<script>window.location.href = window.location.href;</script>";
            }
        }
        
        // Issue Violation
        if (isset($_POST['action']) && $_POST['action'] === 'issue_violation') {
            $plate = $db->real_escape_string(strtoupper($_POST['vehicle_plate']));
            $area_id = !empty($_POST['parking_area_id']) ? (int)$_POST['parking_area_id'] : "NULL";
            $type = $db->real_escape_string($_POST['violation_type']);
            $penalty = (float)$_POST['penalty_amount'];
            $notes = $db->real_escape_string($_POST['notes'] ?? '');
            $officer = $db->real_escape_string($_POST['officer_name'] ?? 'Admin');
            
            $sql = "INSERT INTO parking_violations (vehicle_plate, parking_area_id, violation_type, penalty_amount, notes, officer_name, status, created_at) 
                    VALUES ('$plate', $area_id, '$type', $penalty, '$notes', '$officer', 'Unpaid', NOW())";
            if($db->query($sql)) {
                echo "<script>window.location.href = window.location.href;</script>";
            }
        }
    }

    // Fetch Areas for Dropdowns
    $areas = [];
    $res = $db->query("SELECT id, name, city FROM parking_areas ORDER BY name");
    if($res){
        while($r = $res->fetch_assoc()) $areas[] = $r;
    }
    
    // Stats
    $totalCollected = 0;
    $resP = $db->query("SELECT SUM(amount) as s FROM parking_transactions WHERE DATE(created_at) = CURDATE()");
    if($resP && $r=$resP->fetch_assoc()) $totalCollected = (float)$r['s'];

    $activeViolations = 0;
    $resV = $db->query("SELECT COUNT(*) as c FROM parking_violations WHERE status = 'Unpaid'");
    if($resV && $r=$resV->fetch_assoc()) $activeViolations = (int)$r['c'];
    ?>

    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-8 border-b border-slate-200 dark:border-slate-700 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Parking Fees & Enforcement</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Process payments, issue violations, and track daily revenue.</p>
        </div>
        <div class="flex gap-2">
             <button onclick="openPaymentModal()" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all">
                <i data-lucide="banknote" class="w-4 h-4"></i>
                Record Payment
            </button>
            <button onclick="openViolationModal()" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 hover:bg-rose-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                Issue Violation
            </button>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-8">
        <!-- Stat Card 1 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-emerald-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Today's Revenue</div>
                <i data-lucide="receipt" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">₱<?php echo number_format($totalCollected, 2); ?></div>
        </div>

        <!-- Stat Card 2 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-rose-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Unpaid Violations</div>
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($activeViolations); ?></div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <!-- Tab Navigation -->
        <div class="flex border-b border-slate-200 dark:border-slate-700">
            <button onclick="switchTab('payments')" id="tab-payments" class="flex-1 py-3 text-sm font-bold text-blue-600 border-b-2 border-blue-600 bg-blue-50 dark:bg-blue-900/20 transition-all">
                Recent Payments
            </button>
            <button onclick="switchTab('violations')" id="tab-violations" class="flex-1 py-3 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-all">
                Violations Log
            </button>
        </div>

        <!-- Filters -->
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <i data-lucide="search" class="h-4 w-4 text-slate-400"></i>
                </div>
                <input type="text" id="filter-search" placeholder="Search plate number or area..." class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 py-2 pl-10 pr-4 text-slate-900 dark:text-white placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500 sm:text-sm shadow-sm">
            </div>
            <div class="w-full sm:w-40">
                <select id="filter-status" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 py-2 pl-3 pr-8 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 sm:text-sm shadow-sm">
                    <option value="">All Statuses</option>
                    <option value="Paid">Paid</option>
                    <option value="Pending Payment">Pending Payment</option>
                    <option value="Unpaid">Unpaid</option>
                </select>
            </div>
            <div class="w-full sm:w-40">
                <select id="filter-range" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 py-2 pl-3 pr-8 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 sm:text-sm shadow-sm">
                    <option value="today">Today Only</option>
                    <option value="7d">Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                    <option value="all">All Time</option>
                </select>
            </div>
        </div>

        <!-- Table Content -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 dark:bg-slate-800 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 tracking-wider">
                    <tr>
                        <th class="px-6 py-3 border-b border-slate-200 dark:border-slate-700">Plate Number</th>
                        <th class="px-6 py-3 border-b border-slate-200 dark:border-slate-700">Location</th>
                        <th class="px-6 py-3 border-b border-slate-200 dark:border-slate-700" id="col-amount">Amount</th>
                        <th class="px-6 py-3 border-b border-slate-200 dark:border-slate-700">Status</th>
                        <th class="px-6 py-3 border-b border-slate-200 dark:border-slate-700">Date/Time</th>
                        <th class="px-6 py-3 border-b border-slate-200 dark:border-slate-700">Actions</th>
                    </tr>
                </thead>
                <tbody id="table-body" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-700 dark:text-slate-300">
                    <!-- JS Populated -->
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                            <div class="flex flex-col items-center">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mb-2"></div>
                                <span>Loading data...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination / Footer -->
        <div class="p-3 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium text-slate-500 text-center">
            Showing recent 50 records
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div id="paymentModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 px-6 py-4 flex items-center justify-between border-b border-emerald-100 dark:border-emerald-800/50">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded bg-emerald-100 dark:bg-emerald-800 text-emerald-600 dark:text-emerald-300">
                                <i data-lucide="banknote" class="w-4 h-4"></i>
                            </div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Record Payment</h3>
                        </div>
                        <button onclick="closePaymentModal()" class="text-slate-400 hover:text-slate-500 transition-all"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <input type="hidden" name="action" value="record_payment">
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Vehicle Plate</label>
                            <input name="vehicle_plate" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white font-bold text-lg shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm uppercase placeholder:normal-case" placeholder="ABC-1234">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Parking Area</label>
                            <select name="parking_area_id" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                                <option value="">Select Area...</option>
                                <?php foreach($areas as $a): ?>
                                    <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Amount (₱)</label>
                                <input type="number" step="0.01" name="amount" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white font-bold shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Duration (Hrs)</label>
                                <input type="number" name="duration_hours" value="1" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white font-bold shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Payment Method</label>
                            <select name="payment_method" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                                <option value="Cash">Cash</option>
                                <option value="GCash">GCash</option>
                                <option value="Card">Card</option>
                            </select>
                        </div>

                        <label class="flex items-center gap-2 p-3 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer hover:bg-slate-100 transition-colors">
                            <input type="checkbox" id="pay-via-treasury" name="pay_via_treasury" value="1" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500 border-gray-300">
                            <span class="text-sm text-slate-600 font-medium">Pay via Treasury (Digital)</span>
                        </label>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Reference No. (Optional)</label>
                            <input name="reference_no" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm" placeholder="e.g. OR-12345">
                        </div>

                        <div class="pt-4 flex justify-end gap-3">
                            <button type="button" onclick="closePaymentModal()" class="px-4 py-2 rounded-md text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm transition-all">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition-all">Confirm Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Issue Violation Modal -->
    <div id="violationModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
                    <div class="bg-rose-50 dark:bg-rose-900/20 px-6 py-4 flex items-center justify-between border-b border-rose-100 dark:border-rose-800/50">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded bg-rose-100 dark:bg-rose-800 text-rose-600 dark:text-rose-300">
                                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                            </div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Issue Violation</h3>
                        </div>
                        <button onclick="closeViolationModal()" class="text-slate-400 hover:text-slate-500 transition-all"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <input type="hidden" name="action" value="issue_violation">
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Vehicle Plate</label>
                            <input name="vehicle_plate" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white font-bold text-lg shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm uppercase placeholder:normal-case" placeholder="ABC-1234">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Location / Area</label>
                            <select name="parking_area_id" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm">
                                <option value="">Select Area...</option>
                                <?php foreach($areas as $a): ?>
                                    <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Violation Type</label>
                                <select name="violation_type" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm">
                                    <option value="Illegal Parking">Illegal Parking</option>
                                    <option value="Overstaying">Overstaying</option>
                                    <option value="Obstruction">Obstruction</option>
                                    <option value="No Parking Zone">No Parking Zone</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Penalty (₱)</label>
                                <input type="number" step="0.01" name="penalty_amount" value="500" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white font-bold shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes / Remarks</label>
                            <textarea name="notes" rows="2" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"></textarea>
                        </div>

                        <div class="pt-4 flex justify-end gap-3">
                            <button type="button" onclick="closeViolationModal()" class="px-4 py-2 rounded-md text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm transition-all">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 shadow-sm transition-all">Issue Ticket</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentTab = 'payments';

// Modal Toggles
function toggleModal(id, show) {
    const el = document.getElementById(id);
    if(!el) return;
    if(show) {
        el.classList.remove('hidden');
        if(window.lucide) window.lucide.createIcons();
    } else {
        el.classList.add('hidden');
    }
}

window.openPaymentModal = () => toggleModal('paymentModal', true);
window.closePaymentModal = () => toggleModal('paymentModal', false);
window.openViolationModal = () => toggleModal('violationModal', true);
window.closeViolationModal = () => toggleModal('violationModal', false);

function showToast(msg, type = 'success') {
    const id = 'tmm-toast';
    let el = document.getElementById(id);
    if (!el) {
        el = document.createElement('div');
        el.id = id;
        el.className = 'fixed top-4 right-4 z-[100] max-w-sm space-y-2';
        document.body.appendChild(el);
    }
    const item = document.createElement('div');
    const bg = type === 'success' ? 'bg-emerald-600' : (type === 'error' ? 'bg-rose-600' : 'bg-amber-600');
    item.className = `${bg} text-white px-4 py-3 rounded-xl shadow-lg text-sm font-medium`;
    item.textContent = msg;
    el.appendChild(item);
    setTimeout(() => item.remove(), 3500);
}

// Tab Switching
window.switchTab = (tab) => {
    currentTab = tab;
    // Update Tab UI
    const payBtn = document.getElementById('tab-payments');
    const vioBtn = document.getElementById('tab-violations');
    
    if(tab === 'payments') {
        payBtn.className = "flex-1 py-3 text-sm font-bold text-blue-600 border-b-2 border-blue-600 bg-blue-50 dark:bg-blue-900/20 transition-all";
        vioBtn.className = "flex-1 py-3 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-all";
        document.getElementById('col-amount').innerText = 'Amount';
    } else {
        vioBtn.className = "flex-1 py-3 text-sm font-bold text-rose-600 border-b-2 border-rose-600 bg-rose-50 dark:bg-rose-900/20 transition-all";
        payBtn.className = "flex-1 py-3 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-all";
        document.getElementById('col-amount').innerText = 'Penalty';
    }
    
    fetchData();
};

// Data Fetching
const fetchData = async () => {
    const q = document.getElementById('filter-search').value;
    const status = document.getElementById('filter-status').value;
    const range = document.getElementById('filter-range').value;
    const tbody = document.getElementById('table-body');
    
    tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-slate-500"><div class="flex flex-col items-center"><div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mb-2"></div><span>Updating...</span></div></td></tr>`;
    
    try {
        const url = `${(window.TMM_ROOT_URL || '')}/admin/api/module5/parking_recent.php?kind=${currentTab}&q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}&range=${encodeURIComponent(range)}`;
        const res = await fetch(url);
        const data = await res.json();
        
        if(data.ok && data.rows) {
            if(data.rows.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-slate-500 italic">No records found.</td></tr>`;
                return;
            }
            
            tbody.innerHTML = data.rows.map(row => {
                let statusClass = '';
                if(row.status === 'Paid') statusClass = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
                else if(row.status === 'Pending Payment') statusClass = 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
                else if(row.status === 'Unpaid') statusClass = 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400';
                else statusClass = 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
                
                const amt = currentTab === 'payments' ? row.amount : row.penalty_amount;
                const typeLabel = currentTab === 'violations' ? `<div class="text-xs text-slate-400 mt-0.5">${row.violation_type}</div>` : '';
                const actions = currentTab === 'payments' && row.status !== 'Paid' && row.id
                    ? `<button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white" onclick="window.openTreasuryPay(${Number(row.id)})"><i data-lucide='banknote' class='w-3.5 h-3.5'></i>Pay</button>`
                    : `<span class="text-xs text-slate-400">—</span>`;
                
                return `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-900 dark:text-white uppercase">${row.vehicle_plate}</div>
                        ${typeLabel}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400"></i>
                            ${row.area_name || 'Unknown Area'}
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="font-bold text-slate-700 dark:text-slate-200">₱${Number(amt).toLocaleString('en-US', {minimumFractionDigits:2})}</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ring-black/5 dark:ring-white/10 ${statusClass}">
                            ${row.status}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-slate-500 dark:text-slate-400">
                        ${new Date(row.created_at).toLocaleDateString()} <span class="text-xs opacity-70">${new Date(row.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    </td>
                    <td class="px-6 py-4">${actions}</td>
                </tr>
                `;
            }).join('');
            
            if(window.lucide) window.lucide.createIcons();
        }
    } catch(e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 text-center text-red-500">Error loading data.</td></tr>`;
    }
};

// Listeners
document.getElementById('filter-search').addEventListener('input', _.debounce(fetchData, 500)); // Assuming lodash might be available, otherwise standard debounce
document.getElementById('filter-status').addEventListener('change', fetchData);
document.getElementById('filter-range').addEventListener('change', fetchData);

// Initial Load
document.addEventListener('DOMContentLoaded', () => {
    if(window.lucide) window.lucide.createIcons();
    fetchData();
});

window.openTreasuryPay = (txId) => {
    if (!txId) return;
    const url = `treasury/pay.php?kind=parking&transaction_id=${encodeURIComponent(txId)}`;
    window.open(url, '_blank', 'noopener');
};

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('paymentModal');
    const form = modal ? modal.querySelector('form') : null;
    if (!form) return;
    form.addEventListener('submit', async (e) => {
        const cb = document.getElementById('pay-via-treasury');
        if (!cb || !cb.checked) return;
        e.preventDefault();
        const fd = new FormData(form);
        try {
            const res = await fetch(`${(window.TMM_ROOT_URL || '')}/admin/api/module5/parking_create_pending.php`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data && data.ok && data.transaction_id) {
                toggleModal('paymentModal', false);
                showToast('Opening Treasury payment...', 'success');
                window.openTreasuryPay(data.transaction_id);
                setTimeout(() => fetchData(), 1200);
            } else {
                showToast((data && (data.error || data.message)) ? (data.error || data.message) : 'Unable to create transaction', 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Unable to create transaction', 'error');
        }
    });
});

// Simple debounce polyfill if lodash missing
if (typeof _ === 'undefined') {
    var _ = {
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    };
}
</script>
