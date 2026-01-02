<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

// --- Handle Actions (Create/Update/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Terminal Actions
        if ($_POST['action'] === 'create_terminal') {
            $name = $db->real_escape_string($_POST['name']);
            $city = $db->real_escape_string($_POST['city']);
            $address = $db->real_escape_string($_POST['address']);
            $capacity = (int)$_POST['capacity'];
            
            $sql = "INSERT INTO terminals (name, city, address, capacity, type) VALUES ('$name', '$city', '$address', $capacity, 'Terminal')";
            if($db->query($sql)) {
                echo "<script>window.location.href = window.location.href;</script>";
            }
        }
        if ($_POST['action'] === 'update_terminal') {
            $id = (int)$_POST['id'];
            $name = $db->real_escape_string($_POST['name']);
            $city = $db->real_escape_string($_POST['city']);
            $address = $db->real_escape_string($_POST['address']);
            $capacity = (int)$_POST['capacity'];
            
            $sql = "UPDATE terminals SET name='$name', city='$city', address='$address', capacity=$capacity WHERE id=$id";
            if($db->query($sql)) {
                echo "<script>window.location.href = window.location.href;</script>";
            }
        }
        if ($_POST['action'] === 'delete_terminal') {
            $id = (int)$_POST['id'];
            $db->query("DELETE FROM terminals WHERE id = $id");
            echo "<script>window.location.href = window.location.href;</script>";
        }

        // Terminal Area Actions
        if ($_POST['action'] === 'create_terminal_area') {
            $terminal_id = (int)$_POST['terminal_id'];
            $area_name = $db->real_escape_string($_POST['area_name']);
            $route_name = $db->real_escape_string($_POST['route_name']);
            $fare_range = $db->real_escape_string($_POST['fare_range']);
            $max_slots = (int)$_POST['max_slots'];
            $puv_type = $db->real_escape_string($_POST['puv_type']);

            $sql = "INSERT INTO terminal_areas (terminal_id, area_name, route_name, fare_range, slot_capacity, puv_type) 
                    VALUES ($terminal_id, '$area_name', '$route_name', '$fare_range', $max_slots, '$puv_type')";
            if($db->query($sql)) {
                echo "<script>window.location.href = window.location.href;</script>";
            }
        }
        if ($_POST['action'] === 'update_terminal_area') {
            $id = (int)$_POST['id'];
            $area_name = $db->real_escape_string($_POST['area_name']);
            $route_name = $db->real_escape_string($_POST['route_name']);
            $fare_range = $db->real_escape_string($_POST['fare_range']);
            $max_slots = (int)$_POST['max_slots'];
            $puv_type = $db->real_escape_string($_POST['puv_type']);

            $sql = "UPDATE terminal_areas SET area_name='$area_name', route_name='$route_name', fare_range='$fare_range', slot_capacity=$max_slots, puv_type='$puv_type' WHERE id=$id";
            if($db->query($sql)) {
                echo "<script>window.location.href = window.location.href;</script>";
            }
        }
        if ($_POST['action'] === 'delete_terminal_area') {
            $id = (int)$_POST['id'];
            $db->query("DELETE FROM terminal_areas WHERE id = $id");
            echo "<script>window.location.href = window.location.href;</script>";
        }

        // Operator & Driver Actions
        if ($_POST['action'] === 'assign_operator') {
            $area_id = (int)$_POST['area_id'];
            $is_new = (isset($_POST['is_new_operator']) && $_POST['is_new_operator'] == '1');
            
            if ($is_new) {
                $name = $db->real_escape_string($_POST['new_op_name']);
                $coop = $db->real_escape_string($_POST['new_op_coop']);
                $db->query("INSERT INTO operators (full_name, coop_name) VALUES ('$name', '$coop')");
                $operator_id = $db->insert_id;
            } else {
                $operator_id = (int)$_POST['operator_id'];
            }

            // Link to area
            $db->query("INSERT INTO terminal_area_operators (area_id, operator_id) VALUES ($area_id, $operator_id)");
            echo "<script>window.location.href = window.location.href;</script>";
        }

        if ($_POST['action'] === 'create_driver') {
            $operator_id = (int)$_POST['operator_id'];
            $name = $db->real_escape_string($_POST['driver_name']);
            $license = $db->real_escape_string($_POST['license_no']);
            $contact = $db->real_escape_string($_POST['contact_no']);
            
            $db->query("INSERT INTO drivers (operator_id, driver_name, license_no, contact_no) VALUES ($operator_id, '$name', '$license', '$contact')");
            echo "<script>window.location.href = window.location.href;</script>";
        }
    }
}

// 1. Fetch Terminals
$terminals_sql = "
    SELECT t.*, COUNT(ta.id) as area_count 
    FROM terminals t 
    LEFT JOIN terminal_areas ta ON t.id = ta.terminal_id 
    WHERE t.type != 'Parking'
    GROUP BY t.id
    ORDER BY t.id DESC
";
$terminals_res = $db->query($terminals_sql);
$terminals = [];
if ($terminals_res) {
    while($row = $terminals_res->fetch_assoc()) {
        $terminals[] = $row;
    }
}

// 2. Fetch Areas
$details_sql = "
    SELECT 
        t.id as terminal_id,
        ta.id as area_id,
        ta.area_name,
        ta.route_name,
        ta.fare_range,
        ta.slot_capacity as max_slots,
        ta.puv_type
    FROM terminals t
    JOIN terminal_areas ta ON t.id = ta.terminal_id
";
$details_res = $db->query($details_sql);
$areas = [];
if ($details_res) {
    while($row = $details_res->fetch_assoc()) {
        $areas[$row['terminal_id']][] = $row;
    }
}

// 3. Fetch Operators & Drivers
$ops_sql = "
    SELECT 
        ta.id as area_id,
        o.id as operator_id,
        o.full_name as operator_name,
        o.coop_name as association_name,
        d.driver_name
    FROM terminal_areas ta
    JOIN terminal_area_operators tao ON ta.id = tao.area_id
    JOIN operators o ON tao.operator_id = o.id
    LEFT JOIN drivers d ON o.id = d.operator_id
";
$ops_res = $db->query($ops_sql);
$operators = [];
if ($ops_res) {
    while($row = $ops_res->fetch_assoc()) {
        // Group drivers by operator within area
        $key = $row['area_id'] . '_' . $row['operator_name'];
        if (!isset($operators[$row['area_id']][$row['operator_name']])) {
            $operators[$row['area_id']][$row['operator_name']] = [
                'id' => $row['operator_id'],
                'name' => $row['operator_name'],
                'association' => $row['association_name'],
                'drivers' => []
            ];
        }
        if ($row['driver_name']) {
            $operators[$row['area_id']][$row['operator_name']]['drivers'][] = $row['driver_name'];
        }
    }
}
?>

<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg min-h-screen">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">Terminal Management</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Manage terminals, routes, and operator assignments.</p>
            <!-- Create Terminal Modal -->
    <div id="createModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-50 transition-opacity opacity-0">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="createModalPanel">
            <form method="POST">
                <input type="hidden" name="action" value="create_terminal">
                <div class="p-6 border-b dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Add New Terminal</h2>
                    <button type="button" onclick="closeCreateModal()" class="text-slate-400 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Terminal Name</label>
                        <input type="text" name="name" required placeholder="e.g. Central Terminal" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">City</label>
                        <input type="text" name="city" required placeholder="e.g. Caloocan City" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Address</label>
                        <textarea name="address" rows="2" placeholder="Full address" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Total Capacity</label>
                        <input type="number" name="capacity" placeholder="e.g. 500" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t dark:border-slate-700 text-right space-x-2">
                    <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded hover:bg-slate-300 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Create Terminal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Area Modal -->
    <div id="editAreaModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-[60] transition-opacity opacity-0">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="editAreaModalPanel">
            <form method="POST">
                <input type="hidden" name="action" value="update_terminal_area">
                <input type="hidden" name="id" id="edit_area_id">
                <div class="p-6 border-b dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Edit Area/Route</h2>
                    <button type="button" onclick="closeEditAreaModal()" class="text-slate-400 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Area/Line Name</label>
                        <input type="text" name="area_name" id="edit_area_name" required class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Route Name</label>
                        <input type="text" name="route_name" id="edit_route_name" required class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fare Range</label>
                            <input type="text" name="fare_range" id="edit_fare_range" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Max Slots</label>
                            <input type="number" name="max_slots" id="edit_max_slots" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">PUV Type</label>
                        <select name="puv_type" id="edit_puv_type" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="Tricycle">Tricycle</option>
                            <option value="Jeepney">Jeepney</option>
                            <option value="Bus">Bus</option>
                            <option value="Van">Van</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t dark:border-slate-700 text-right space-x-2">
                    <button type="button" onclick="closeEditAreaModal()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded hover:bg-slate-300 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Operator Modal -->
    <div id="assignOperatorModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-[70] transition-opacity opacity-0">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="assignOperatorModalPanel">
            <form method="POST">
                <input type="hidden" name="action" value="assign_operator">
                <input type="hidden" name="area_id" id="assign_area_id">
                <div class="p-6 border-b dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Assign Operator</h2>
                    <button type="button" onclick="closeAssignOperatorModal()" class="text-slate-400 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <!-- Tab Switcher -->
                    <div class="flex border-b dark:border-slate-700 mb-4">
                        <button type="button" onclick="switchOpTab('existing')" id="tab_existing" class="px-4 py-2 text-blue-600 border-b-2 border-blue-600 font-medium">Select Existing</button>
                        <button type="button" onclick="switchOpTab('new')" id="tab_new" class="px-4 py-2 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Create New</button>
                    </div>

                    <!-- Existing Operator -->
                    <div id="op_existing_content">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Select Operator</label>
                        <select name="operator_id" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php
                            $all_ops = $db->query("SELECT * FROM operators ORDER BY full_name ASC");
                            if($all_ops){
                                while($op = $all_ops->fetch_assoc()){
                                    echo "<option value='{$op['id']}'>{$op['full_name']} ({$op['coop_name']})</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <!-- New Operator -->
                    <div id="op_new_content" class="hidden space-y-4">
                        <input type="hidden" name="is_new_operator" id="is_new_operator" value="0">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Full Name</label>
                            <input type="text" name="new_op_name" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Coop/Association</label>
                            <input type="text" name="new_op_coop" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                
                <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t dark:border-slate-700 text-right space-x-2">
                    <button type="button" onclick="closeAssignOperatorModal()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded hover:bg-slate-300 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Assign Operator</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Driver Modal -->
    <div id="addDriverModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-[80] transition-opacity opacity-0">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="addDriverModalPanel">
            <form method="POST">
                <input type="hidden" name="action" value="create_driver">
                <input type="hidden" name="operator_id" id="driver_operator_id">
                <div class="p-6 border-b dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Add Driver</h2>
                    <button type="button" onclick="closeAddDriverModal()" class="text-slate-400 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Driver Name</label>
                        <input type="text" name="driver_name" required class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">License No.</label>
                        <input type="text" name="license_no" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Contact No.</label>
                        <input type="text" name="contact_no" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t dark:border-slate-700 text-right space-x-2">
                    <button type="button" onclick="closeAddDriverModal()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded hover:bg-slate-300 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Add Driver</button>
                </div>
            </form>
        </div>
    </div>
</div>
        <button onclick="openCreateModal()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Terminal
        </button>
    </div>

    <!-- Terminal Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($terminals as $term): ?>
            <div class="group relative p-6 border rounded-lg hover:shadow-xl transition-all duration-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:-translate-y-1">
                <!-- Delete Button -->
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this terminal?');" class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition z-10">
                    <input type="hidden" name="action" value="delete_terminal">
                    <input type="hidden" name="id" value="<?php echo $term['id']; ?>">
                    <button type="submit" class="p-2 text-slate-400 hover:text-red-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </form>

                <div onclick="openTerminalModal(<?php echo $term['id']; ?>)" class="cursor-pointer">
                    <div class="mb-4">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-600 mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($term['name']); ?></h3>
                        <p class="text-sm text-slate-500"><?php echo htmlspecialchars($term['city'] ?? 'Unknown City'); ?></p>
                    </div>
                    <div class="flex items-center justify-between pt-4 border-t dark:border-slate-700">
                        <div class="text-center">
                            <span class="block text-2xl font-bold text-slate-700 dark:text-slate-200"><?php echo $term['area_count']; ?></span>
                            <span class="text-xs text-slate-500 uppercase tracking-wide">Routes/Areas</span>
                        </div>
                        <div class="text-center">
                            <span class="block text-2xl font-bold text-slate-700 dark:text-slate-200"><?php echo $term['capacity']; ?></span>
                            <span class="text-xs text-slate-500 uppercase tracking-wide">Capacity</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Detail Modal -->
    <div id="terminalModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-50 transition-opacity opacity-0">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-5xl max-h-[90vh] overflow-hidden transform scale-95 transition-transform" id="modalPanel">
            <!-- Header -->
            <div class="p-6 border-b dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800">
                <div>
                    <h2 id="modalTitle" class="text-2xl font-bold text-slate-800 dark:text-slate-100">Terminal Details</h2>
                    <p id="modalSubtitle" class="text-sm text-slate-500">View routes, operators, and status</p>
                </div>
                <button onclick="closeTerminalModal()" class="text-slate-400 hover:text-red-500 transition">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <!-- Scrollable Content -->
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-100px)] space-y-8" id="modalContent">
                <!-- Injected via JS -->
            </div>
        </div>
    </div>

    <!-- Edit Terminal Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-50 transition-opacity opacity-0">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="editModalPanel">
            <form method="POST">
                <input type="hidden" name="action" value="update_terminal">
                <input type="hidden" name="id" id="edit_id">
                <div class="p-6 border-b dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Edit Terminal</h2>
                    <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Terminal Name</label>
                        <input type="text" name="name" id="edit_name" required class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">City</label>
                        <input type="text" name="city" id="edit_city" required class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Address</label>
                        <textarea name="address" id="edit_address" rows="2" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Capacity</label>
                        <input type="number" name="capacity" id="edit_capacity" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t dark:border-slate-700 text-right space-x-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded hover:bg-slate-300 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Area Modal -->
    <div id="addAreaModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-[60] transition-opacity opacity-0">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="addAreaModalPanel">
            <form method="POST">
                <input type="hidden" name="action" value="create_terminal_area">
                <input type="hidden" name="terminal_id" id="area_terminal_id">
                <div class="p-6 border-b dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Add New Area/Route</h2>
                    <button type="button" onclick="closeAddAreaModal()" class="text-slate-400 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Area/Line Name</label>
                        <input type="text" name="area_name" required placeholder="e.g. Line 1, North Wing" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Route Name</label>
                        <input type="text" name="route_name" required placeholder="e.g. Downtown Route" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fare Range</label>
                            <input type="text" name="fare_range" placeholder="e.g. 15-25 PHP" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Max Slots</label>
                            <input type="number" name="max_slots" value="10" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">PUV Type</label>
                        <select name="puv_type" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="Tricycle">Tricycle</option>
                            <option value="Jeepney" selected>Jeepney</option>
                            <option value="Bus">Bus</option>
                            <option value="Van">Van</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t dark:border-slate-700 text-right space-x-2">
                    <button type="button" onclick="closeAddAreaModal()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded hover:bg-slate-300 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Add Area</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const terminalData = <?php echo json_encode($terminals); ?>;
const areaData = <?php echo json_encode($areas); ?>;
const operatorData = <?php echo json_encode($operators); ?>;

let currentTerminalId = null;

// Detail Modal Functions
function openTerminalModal(id) {
    const term = terminalData.find(t => t.id == id);
    if(!term) return;
    
    currentTerminalId = id;
    document.getElementById('area_terminal_id').value = id;

    document.getElementById('modalTitle').innerText = term.name;
    document.getElementById('modalSubtitle').innerText = `${term.city || ''} • ${term.address || 'No address'}`;
    
    const areas = areaData[id] || [];
    let html = '';
    
    if (areas.length === 0) {
        html = `
            <div class="text-center py-12 text-slate-500 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-dashed dark:border-slate-700">
                <svg class="w-12 h-12 mx-auto mb-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0121 18.382V7.618a1 1 0 01-.553-.894L15 7m0 13V7m0 0L9 4"></path></svg>
                <p>No designated areas or routes found for this terminal.</p>
                <button onclick="openAddAreaModal()" class="mt-4 text-blue-600 hover:underline">Add one now</button>
            </div>
        `;
    } else {
        html += '<div class="grid grid-cols-1 gap-6">';
        
        areas.forEach(area => {
            const ops = operatorData[area.area_id] ? Object.values(operatorData[area.area_id]) : [];
            
            html += `
                <div class="border dark:border-slate-700 rounded-lg overflow-hidden relative group-area">
                    <div class="bg-slate-50 dark:bg-slate-800 p-4 border-b dark:border-slate-700 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">${area.area_name}</h3>
                            <div class="flex items-center space-x-2 text-sm text-slate-500 mt-1">
                                <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 text-xs font-semibold">${area.puv_type}</span>
                                <span>•</span>
                                <span>${area.route_name || 'No Route Name'}</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Fare: ${area.fare_range || 'N/A'}</div>
                                <div class="text-xs text-slate-500">Max Slots: ${area.max_slots}</div>
                            </div>
                            
                            <!-- Edit Area Button -->
                            <button onclick="openEditAreaModal(${area.area_id})" class="text-slate-400 hover:text-blue-500 p-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>

                            <!-- Delete Area Button -->
                            <form method="POST" onsubmit="return confirm('Delete this area and all its associations?');">
                                <input type="hidden" name="action" value="delete_terminal_area">
                                <input type="hidden" name="id" value="${area.area_id}">
                                <button type="submit" class="text-slate-400 hover:text-red-500 p-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-white dark:bg-slate-900">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-xs font-semibold uppercase text-slate-500 tracking-wider">Assigned Operators & Drivers</h4>
                            <button onclick="openAssignOperatorModal(${area.area_id})" class="text-xs text-blue-600 hover:underline">+ Assign Operator</button>
                        </div>
                        
                        ${ops.length > 0 ? `
                            <div class="space-y-3">
                                ${ops.map(op => `
                                    <div class="flex items-start p-3 rounded bg-slate-50 dark:bg-slate-800/50">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between mr-2">
                                                <div class="font-medium text-slate-800 dark:text-slate-200">${op.name}</div>
                                                <button onclick="openAddDriverModal(${op.id})" class="text-xs text-blue-600 hover:underline" title="Add Driver">+ Driver</button>
                                            </div>
                                            <div class="text-xs text-slate-500">${op.association || 'Independent'}</div>
                                        </div>
                                        <div class="flex-1 border-l dark:border-slate-700 pl-3">
                                            <div class="text-xs text-slate-400 mb-1">Drivers</div>
                                            <div class="flex flex-wrap gap-1">
                                                ${op.drivers.length > 0 
                                                    ? op.drivers.map(d => `<span class="inline-block px-2 py-1 bg-white dark:bg-slate-700 border dark:border-slate-600 rounded text-xs">${d}</span>`).join('')
                                                    : '<span class="text-xs italic text-slate-400">No drivers listed</span>'
                                                }
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : `
                            <p class="text-sm text-slate-400 italic">No operators assigned to this area.</p>
                        `}
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }

    document.getElementById('modalContent').innerHTML = html;
    
    const modal = document.getElementById('terminalModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('modalPanel').classList.remove('scale-95');
        document.getElementById('modalPanel').classList.add('scale-100');
    }, 10);
}

function closeTerminalModal() {
    const modal = document.getElementById('terminalModal');
    modal.classList.add('opacity-0');
    document.getElementById('modalPanel').classList.remove('scale-100');
    document.getElementById('modalPanel').classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        currentTerminalId = null;
    }, 300);
}

// Edit Modal Functions
function openEditModal(id) {
    const term = terminalData.find(t => t.id == id);
    if(!term) return;

    document.getElementById('edit_id').value = term.id;
    document.getElementById('edit_name').value = term.name;
    document.getElementById('edit_city').value = term.city;
    document.getElementById('edit_address').value = term.address;
    document.getElementById('edit_capacity').value = term.capacity;

    const modal = document.getElementById('editModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('editModalPanel').classList.remove('scale-95');
        document.getElementById('editModalPanel').classList.add('scale-100');
    }, 10);
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    modal.classList.add('opacity-0');
    document.getElementById('editModalPanel').classList.remove('scale-100');
    document.getElementById('editModalPanel').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Add Area Modal Functions
function openAddAreaModal() {
    const modal = document.getElementById('addAreaModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('addAreaModalPanel').classList.remove('scale-95');
        document.getElementById('addAreaModalPanel').classList.add('scale-100');
    }, 10);
}

function closeAddAreaModal() {
    const modal = document.getElementById('addAreaModal');
    modal.classList.add('opacity-0');
    document.getElementById('addAreaModalPanel').classList.remove('scale-100');
    document.getElementById('addAreaModalPanel').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Create Modal Functions
function openCreateModal() {
    const modal = document.getElementById('createModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('createModalPanel').classList.remove('scale-95');
        document.getElementById('createModalPanel').classList.add('scale-100');
    }, 10);
}

function closeCreateModal() {
    const modal = document.getElementById('createModal');
    modal.classList.add('opacity-0');
    document.getElementById('createModalPanel').classList.remove('scale-100');
    document.getElementById('createModalPanel').classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Edit Area Modal Functions
function openEditAreaModal(id) {
    // Find the area data
    let area = null;
    for (let tId in areaData) {
        const found = areaData[tId].find(a => a.area_id == id);
        if (found) {
            area = found;
            break;
        }
    }
    if(!area) return;

    document.getElementById('edit_area_id').value = area.area_id;
    document.getElementById('edit_area_name').value = area.area_name;
    document.getElementById('edit_route_name').value = area.route_name;
    document.getElementById('edit_fare_range').value = area.fare_range;
    document.getElementById('edit_max_slots').value = area.max_slots;
    document.getElementById('edit_puv_type').value = area.puv_type;

    const modal = document.getElementById('editAreaModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('editAreaModalPanel').classList.remove('scale-95');
        document.getElementById('editAreaModalPanel').classList.add('scale-100');
    }, 10);
}

function closeEditAreaModal() {
    const modal = document.getElementById('editAreaModal');
    modal.classList.add('opacity-0');
    document.getElementById('editAreaModalPanel').classList.remove('scale-100');
    document.getElementById('editAreaModalPanel').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Assign Operator Modal Functions
function openAssignOperatorModal(areaId) {
    document.getElementById('assign_area_id').value = areaId;
    
    const modal = document.getElementById('assignOperatorModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('assignOperatorModalPanel').classList.remove('scale-95');
        document.getElementById('assignOperatorModalPanel').classList.add('scale-100');
    }, 10);
}

function closeAssignOperatorModal() {
    const modal = document.getElementById('assignOperatorModal');
    modal.classList.add('opacity-0');
    document.getElementById('assignOperatorModalPanel').classList.remove('scale-100');
    document.getElementById('assignOperatorModalPanel').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function switchOpTab(tab) {
    const tabExisting = document.getElementById('tab_existing');
    const tabNew = document.getElementById('tab_new');
    const contentExisting = document.getElementById('op_existing_content');
    const contentNew = document.getElementById('op_new_content');
    const isNewInput = document.getElementById('is_new_operator');

    if (tab === 'existing') {
        tabExisting.classList.add('text-blue-600', 'border-blue-600');
        tabExisting.classList.remove('text-slate-500');
        tabNew.classList.remove('text-blue-600', 'border-blue-600');
        tabNew.classList.add('text-slate-500');
        
        contentExisting.classList.remove('hidden');
        contentNew.classList.add('hidden');
        isNewInput.value = '0';
    } else {
        tabNew.classList.add('text-blue-600', 'border-blue-600');
        tabNew.classList.remove('text-slate-500');
        tabExisting.classList.remove('text-blue-600', 'border-blue-600');
        tabExisting.classList.add('text-slate-500');
        
        contentNew.classList.remove('hidden');
        contentExisting.classList.add('hidden');
        isNewInput.value = '1';
    }
}

// Add Driver Modal Functions
function openAddDriverModal(operatorId) {
    document.getElementById('driver_operator_id').value = operatorId;
    
    const modal = document.getElementById('addDriverModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('addDriverModalPanel').classList.remove('scale-95');
        document.getElementById('addDriverModalPanel').classList.add('scale-100');
    }, 10);
}

function closeAddDriverModal() {
    const modal = document.getElementById('addDriverModal');
    modal.classList.add('opacity-0');
    document.getElementById('addDriverModalPanel').classList.remove('scale-100');
    document.getElementById('addDriverModalPanel').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
</script>
