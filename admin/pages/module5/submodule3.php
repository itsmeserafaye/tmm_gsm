<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Create Fee Charge
        if ($_POST['action'] === 'create_charge') {
            $parking_area_id = !empty($_POST['parking_area_id']) ? (int)$_POST['parking_area_id'] : "NULL";
            $amount = (float)$_POST['amount'];
            $charge_type = $db->real_escape_string($_POST['charge_type']);
            $vehicle_plate = $db->real_escape_string($_POST['vehicle_plate']);
            
            $sql = "INSERT INTO parking_transactions (parking_area_id, amount, transaction_type, vehicle_plate, status) 
                    VALUES ($parking_area_id, $amount, '$charge_type', '$vehicle_plate', 'Paid')";
            
            if($db->query($sql)) {
                echo "<script>window.location.href = window.location.href;</script>";
            } else {
                echo "<script>alert('Error: " . $db->error . "');</script>";
            }
        }

        // Create Violation Ticket
        if ($_POST['action'] === 'create_ticket') {
            $parking_area_id = !empty($_POST['parking_area_id']) ? (int)$_POST['parking_area_id'] : "NULL";
            $vehicle_plate = $db->real_escape_string($_POST['vehicle_plate']);
            $violation_type = $db->real_escape_string($_POST['violation_type']);
            $penalty_amount = (float)$_POST['penalty_amount'];
            
            $sql = "INSERT INTO parking_violations (parking_area_id, vehicle_plate, violation_type, penalty_amount, status) 
                    VALUES ($parking_area_id, '$vehicle_plate', '$violation_type', $penalty_amount, 'Unpaid')";
            
            if($db->query($sql)) {
                echo "<script>window.location.href = window.location.href;</script>";
            } else {
                echo "<script>alert('Error: " . $db->error . "');</script>";
            }
        }
    }
}

// Fetch Data
$parking_areas = [];
$res = $db->query("SELECT * FROM parking_areas ORDER BY name");
while($row = $res->fetch_assoc()) $parking_areas[] = $row;

// Analytics
$total_fees = 0;
$res = $db->query("SELECT SUM(amount) as total FROM parking_transactions");
if($row = $res->fetch_assoc()) $total_fees = $row['total'] ?? 0;

$incidents_count = 0;
$res = $db->query("SELECT COUNT(*) as count FROM parking_violations");
if($row = $res->fetch_assoc()) $incidents_count = $row['count'] ?? 0;

$active_parking_areas = count($parking_areas);
?>

<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Parking Fees, Enforcement & Analytics</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Manage fees, issue tickets, and view analytics for Parking Areas.</p>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
      <div class="text-sm text-slate-500">Total Fees Collected</div>
      <div class="text-3xl font-bold text-green-600">₱<?php echo number_format($total_fees, 2); ?></div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
      <div class="text-sm text-slate-500">Total Incidents</div>
      <div class="text-3xl font-bold text-red-600"><?php echo $incidents_count; ?></div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
      <div class="text-sm text-slate-500">Active Parking Areas</div>
      <div class="text-3xl font-bold text-blue-600"><?php echo $active_parking_areas; ?></div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Fee Charges Form -->
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2">
        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Fee Charges & Payments
      </h2>
      <form method="POST" class="grid grid-cols-1 gap-4">
        <input type="hidden" name="action" value="create_charge">
        
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Parking Area</label>
            <select name="parking_area_id" required class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">-- Select Parking Area --</option>
                <?php foreach($parking_areas as $pa): ?>
                    <option value="<?php echo $pa['id']; ?>"><?php echo htmlspecialchars($pa['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Vehicle Plate</label>
                <input type="text" name="vehicle_plate" placeholder="ABC-1234" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Amount (₱)</label>
                <input type="number" step="0.01" name="amount" required placeholder="0.00" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Charge Type</label>
            <select name="charge_type" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="Usage Fee">Usage Fee (Hourly/Daily)</option>
                <option value="Permit Fee">Permit Fee</option>
                <option value="Stall Rent">Stall Rent</option>
            </select>
        </div>

        <button type="submit" class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded transition">Record Payment</button>
      </form>
    </div>

    <!-- Enforcement Form -->
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        Enforcement & Violations
      </h2>
      <form method="POST" class="grid grid-cols-1 gap-4">
        <input type="hidden" name="action" value="create_ticket">

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Location (Parking Area)</label>
            <select name="parking_area_id" required class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                <option value="">-- Select Location --</option>
                <?php foreach($parking_areas as $pa): ?>
                    <option value="<?php echo $pa['id']; ?>"><?php echo htmlspecialchars($pa['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Vehicle Plate</label>
                <input type="text" name="vehicle_plate" required placeholder="ABC-1234" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-red-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Penalty (₱)</label>
                <input type="number" step="0.01" name="penalty_amount" required placeholder="0.00" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-red-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Violation Type</label>
            <select name="violation_type" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                <option value="Illegal Parking">Illegal Parking</option>
                <option value="Overstaying">Overstaying</option>
                <option value="Obstruction">Obstruction</option>
                <option value="No Permit">No Permit</option>
            </select>
        </div>

        <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded transition">Issue Ticket</button>
      </form>
    </div>
  </div>

  <div class="mt-6 p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Recent Activity</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Recent Transactions -->
          <div>
              <h3 class="text-sm font-medium text-slate-500 mb-2">Latest Payments</h3>
              <div class="bg-slate-50 dark:bg-slate-800 rounded border dark:border-slate-700 max-h-60 overflow-y-auto">
                  <table class="w-full text-sm text-left">
                      <thead class="text-xs text-slate-500 uppercase bg-slate-100 dark:bg-slate-700 sticky top-0">
                          <tr>
                              <th class="px-3 py-2">Plate</th>
                              <th class="px-3 py-2">Area</th>
                              <th class="px-3 py-2 text-right">Amount</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php 
                          $sql = "SELECT t.*, p.name as area_name FROM parking_transactions t LEFT JOIN parking_areas p ON t.parking_area_id = p.id ORDER BY t.created_at DESC LIMIT 5";
                          $res = $db->query($sql);
                          if($res->num_rows > 0):
                              while($row = $res->fetch_assoc()):
                          ?>
                          <tr class="border-b dark:border-slate-700">
                              <td class="px-3 py-2 font-medium"><?php echo htmlspecialchars($row['vehicle_plate'] ?? '-'); ?></td>
                              <td class="px-3 py-2 text-slate-500"><?php echo htmlspecialchars($row['area_name'] ?? 'Unknown'); ?></td>
                              <td class="px-3 py-2 text-right text-green-600 font-bold">₱<?php echo number_format($row['amount'], 2); ?></td>
                          </tr>
                          <?php endwhile; else: ?>
                          <tr><td colspan="3" class="px-3 py-2 text-center text-slate-500">No recent payments</td></tr>
                          <?php endif; ?>
                      </tbody>
                  </table>
              </div>
          </div>

          <!-- Recent Violations -->
          <div>
              <h3 class="text-sm font-medium text-slate-500 mb-2">Latest Violations</h3>
              <div class="bg-slate-50 dark:bg-slate-800 rounded border dark:border-slate-700 max-h-60 overflow-y-auto">
                  <table class="w-full text-sm text-left">
                      <thead class="text-xs text-slate-500 uppercase bg-slate-100 dark:bg-slate-700 sticky top-0">
                          <tr>
                              <th class="px-3 py-2">Plate</th>
                              <th class="px-3 py-2">Violation</th>
                              <th class="px-3 py-2 text-right">Penalty</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php 
                          $sql = "SELECT v.*, p.name as area_name FROM parking_violations v LEFT JOIN parking_areas p ON v.parking_area_id = p.id ORDER BY v.created_at DESC LIMIT 5";
                          $res = $db->query($sql);
                          if($res->num_rows > 0):
                              while($row = $res->fetch_assoc()):
                          ?>
                          <tr class="border-b dark:border-slate-700">
                              <td class="px-3 py-2 font-medium"><?php echo htmlspecialchars($row['vehicle_plate']); ?></td>
                              <td class="px-3 py-2 text-red-500"><?php echo htmlspecialchars($row['violation_type']); ?></td>
                              <td class="px-3 py-2 text-right font-bold">₱<?php echo number_format($row['penalty_amount'], 2); ?></td>
                          </tr>
                          <?php endwhile; else: ?>
                          <tr><td colspan="3" class="px-3 py-2 text-center text-slate-500">No recent violations</td></tr>
                          <?php endif; ?>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
  </div>
</div>
