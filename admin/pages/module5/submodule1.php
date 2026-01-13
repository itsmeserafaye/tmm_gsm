<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100">
    <?php
    require_once __DIR__ . '/../../includes/db.php';
    $db = db();

    function m5_column_exists($db, $table, $column) {
        $t = $db->real_escape_string($table);
        $c = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $res && $res->num_rows > 0;
    }

    $terminalHasStatus = m5_column_exists($db, 'terminals', 'status');
    $areaHasSlotCapacity = m5_column_exists($db, 'terminal_areas', 'slot_capacity');
    $areaCapacityCol = $areaHasSlotCapacity ? 'slot_capacity' : 'max_slots';

    // --- Handle Actions ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            // ... (Keep existing PHP logic for Create/Update/Delete) ...
            if ($_POST['action'] === 'create_terminal') {
                $name = $db->real_escape_string($_POST['name']);
                $city = $db->real_escape_string($_POST['city']);
                $address = $db->real_escape_string($_POST['address']);
                $capacity = (int)$_POST['capacity'];
                
                $cols = "name, city, address, capacity, type";
                $vals = "'$name', '$city', '$address', $capacity, 'Terminal'";
                if ($terminalHasStatus) {
                    $cols .= ", status";
                    $vals .= ", 'Active'";
                }
                $sql = "INSERT INTO terminals ($cols) VALUES ($vals)";
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
                $route_pick = trim((string)($_POST['route_name'] ?? ''));
                if ($route_pick === '__custom__') {
                    $route_pick = trim((string)($_POST['route_name_custom'] ?? ''));
                }
                $route_name = $db->real_escape_string($route_pick);
                $fare_range = $db->real_escape_string($_POST['fare_range']);
                $max_slots = (int)$_POST['max_slots'];
                $puv_type = $db->real_escape_string($_POST['puv_type']);

                if ($fare_range === '' || $max_slots <= 0) {
                    $stmtR = $db->prepare("SELECT fare, max_vehicle_limit FROM routes WHERE route_id=?");
                    if ($stmtR) {
                        $stmtR->bind_param('s', $route_pick);
                        $stmtR->execute();
                        $r = $stmtR->get_result()->fetch_assoc() ?: null;
                        if ($r) {
                            if ($fare_range === '' && isset($r['fare']) && (float)$r['fare'] > 0) {
                                $fare_range = $db->real_escape_string('₱' . number_format((float)$r['fare'], 2));
                            }
                            if ($max_slots <= 0) {
                                $limit = (int)($r['max_vehicle_limit'] ?? 0);
                                $max_slots = $limit > 0 ? min($limit, 25) : 10;
                            }
                        }
                    }
                }

                $sql = "INSERT INTO terminal_areas (terminal_id, area_name, route_name, fare_range, $areaCapacityCol, puv_type) 
                        VALUES ($terminal_id, '$area_name', '$route_name', '$fare_range', $max_slots, '$puv_type')";
                if($db->query($sql)) {
                    echo "<script>window.location.href = window.location.href;</script>";
                }
            }
            if ($_POST['action'] === 'update_terminal_area') {
                $id = (int)$_POST['id'];
                $area_name = $db->real_escape_string($_POST['area_name']);
                $route_pick = trim((string)($_POST['route_name'] ?? ''));
                if ($route_pick === '__custom__') {
                    $route_pick = trim((string)($_POST['route_name_custom'] ?? ''));
                }
                $route_name = $db->real_escape_string($route_pick);
                $fare_range = $db->real_escape_string($_POST['fare_range']);
                $max_slots = (int)$_POST['max_slots'];
                $puv_type = $db->real_escape_string($_POST['puv_type']);

                if ($fare_range === '' || $max_slots <= 0) {
                    $stmtR = $db->prepare("SELECT fare, max_vehicle_limit FROM routes WHERE route_id=?");
                    if ($stmtR) {
                        $stmtR->bind_param('s', $route_pick);
                        $stmtR->execute();
                        $r = $stmtR->get_result()->fetch_assoc() ?: null;
                        if ($r) {
                            if ($fare_range === '' && isset($r['fare']) && (float)$r['fare'] > 0) {
                                $fare_range = $db->real_escape_string('₱' . number_format((float)$r['fare'], 2));
                            }
                            if ($max_slots <= 0) {
                                $limit = (int)($r['max_vehicle_limit'] ?? 0);
                                $max_slots = $limit > 0 ? min($limit, 25) : 10;
                            }
                        }
                    }
                }

                $sql = "UPDATE terminal_areas SET area_name='$area_name', route_name='$route_name', fare_range='$fare_range', $areaCapacityCol=$max_slots, puv_type='$puv_type' WHERE id=$id";
                if($db->query($sql)) {
                    echo "<script>window.location.href = window.location.href;</script>";
                }
            }
            if ($_POST['action'] === 'delete_terminal_area') {
                $id = (int)$_POST['id'];
                $db->query("DELETE FROM terminal_areas WHERE id = $id");
                echo "<script>window.location.href = window.location.href;</script>";
            }
        }
    }

    // Fetch Data
    $routes = [];
    $routes_res = $db->query("SELECT route_id, route_name, origin, destination, fare, max_vehicle_limit, status FROM routes ORDER BY route_name ASC");
    if ($routes_res) {
        while ($row = $routes_res->fetch_assoc()) $routes[] = $row;
    }

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
        while($row = $terminals_res->fetch_assoc()) $terminals[] = $row;
    }

    // Fetch Areas
    $details_sql = "
        SELECT 
            t.id as terminal_id,
            ta.id as area_id,
            ta.area_name,
            ta.route_name,
            ta.fare_range,
            ta.$areaCapacityCol as max_slots,
            ta.puv_type,
            r.route_name as linked_route_name,
            r.origin as route_origin,
            r.destination as route_destination,
            r.fare as linked_fare,
            r.max_vehicle_limit as route_limit
        FROM terminals t
        JOIN terminal_areas ta ON t.id = ta.terminal_id
        LEFT JOIN routes r ON r.route_id = ta.route_name
    ";
    $details_res = $db->query($details_sql);
    $areas = [];
    if ($details_res) {
        while($row = $details_res->fetch_assoc()) $areas[$row['terminal_id']][] = $row;
    }

    // Fetch Operators
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

    $assignmentsByTerminalRoute = [];
    $resAsn = $db->query("
        SELECT 
            ta.terminal_name,
            ta.route_id,
            ta.plate_number,
            ta.status,
            ta.assigned_at,
            v.operator_name,
            v.vehicle_type,
            v.coop_name,
            o.id AS fallback_operator_id
        FROM terminal_assignments ta
        LEFT JOIN vehicles v ON v.plate_number = ta.plate_number
        LEFT JOIN operators o ON o.full_name = v.operator_name AND (o.coop_name = v.coop_name OR v.coop_name IS NULL OR v.coop_name = '')
        ORDER BY ta.assigned_at DESC
    ");
    if ($resAsn) {
        while ($r = $resAsn->fetch_assoc()) {
            $tname = (string)($r['terminal_name'] ?? '');
            $rid = (string)($r['route_id'] ?? '');
            if ($tname === '' || $rid === '') continue;
            $assignmentsByTerminalRoute[$tname][$rid][] = $r;
        }
    }

    $statsTerminals = is_array($terminals) ? count($terminals) : 0;
    $statsAreas = 0;
    if (is_array($areas)) {
        foreach ($areas as $list) if (is_array($list)) $statsAreas += count($list);
    }
    $statsAssignedOperators = 0;
    $resOp = $db->query("SELECT COUNT(DISTINCT operator_id) AS c FROM terminal_area_operators");
    if ($resOp && ($r = $resOp->fetch_assoc())) $statsAssignedOperators = (int)($r['c'] ?? 0);
    ?>

    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-8 border-b border-slate-200 dark:border-slate-700 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Terminal Management</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage public transport terminals, bay assignments, route links, and operator dispatching.</p>
        </div>
        <div class="flex gap-2">
            <button onclick="openCreateModal()" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 hover:bg-blue-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all">
                <i data-lucide="plus" class="w-4 h-4"></i>
                New Terminal
            </button>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-8">
        <!-- Stat Card 1 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-emerald-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Terminals</div>
                <i data-lucide="map-pin" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo (int)$statsTerminals; ?></div>
        </div>

        <!-- Stat Card 2 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-blue-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Bays & Areas</div>
                <i data-lucide="grid" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo (int)$statsAreas; ?></div>
        </div>

        <!-- Stat Card 3 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-purple-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Operators Assigned</div>
                <i data-lucide="users" class="w-4 h-4 text-purple-600 dark:text-purple-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo (int)$statsAssignedOperators; ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white dark:bg-slate-800 p-4 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="relative max-w-md w-full">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <i data-lucide="search" class="h-4 w-4 text-slate-400"></i>
            </div>
            <input id="terminalSearch" class="block w-full rounded-md border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 py-2 pl-10 pr-4 text-slate-900 dark:text-white placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="Search terminals, cities, or areas...">
        </div>
    </div>

    <!-- Terminals Grid -->
    <?php if (empty($terminals)): ?>
        <div class="p-12 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-center">
            <div class="mx-auto h-12 w-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                <i data-lucide="map-off" class="w-6 h-6 text-slate-400"></i>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">No terminals found</h3>
            <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Get started by adding your first transport terminal to manage routes and assignments.</p>
            <button onclick="openCreateModal()" class="mt-6 inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition-all">
                Add Terminal
            </button>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach($terminals as $term): ?>
                <div class="terminal-card group relative p-6 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all" 
                     data-name="<?php echo htmlspecialchars((string)$term['name'], ENT_QUOTES); ?>" 
                     data-city="<?php echo htmlspecialchars((string)($term['city'] ?? ''), ENT_QUOTES); ?>">
                    
                    <div class="flex items-start justify-between mb-4">
                        <div class="h-10 w-10 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                            <i data-lucide="bus" class="w-5 h-5"></i>
                        </div>
                        <div class="flex gap-1">
                            <button onclick="openEditModal(<?php echo $term['id']; ?>)" class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-blue-600 transition-all">
                                <i data-lucide="edit-2" class="w-4 h-4"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Delete this terminal?');" class="inline">
                                <input type="hidden" name="action" value="delete_terminal">
                                <input type="hidden" name="id" value="<?php echo $term['id']; ?>">
                                <button type="submit" class="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-red-600 transition-all">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1"><?php echo htmlspecialchars($term['name']); ?></h3>
                    <div class="flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 mb-4">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                        <?php echo htmlspecialchars($term['city'] ?? 'Unknown City'); ?>
                    </div>

                    <div class="grid grid-cols-2 gap-4 py-4 border-t border-slate-100 dark:border-slate-700">
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Capacity</div>
                            <div class="text-base font-bold text-slate-700 dark:text-slate-200"><?php echo number_format($term['capacity']); ?></div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Bays/Routes</div>
                            <div class="text-base font-bold text-slate-700 dark:text-slate-200"><?php echo number_format($term['area_count']); ?></div>
                        </div>
                    </div>

                    <button onclick="openTerminalModal(<?php echo $term['id']; ?>)" class="w-full mt-2 inline-flex items-center justify-center gap-2 rounded-md bg-slate-50 dark:bg-slate-700/50 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-900/20 dark:hover:text-blue-400 transition-all border border-slate-200 dark:border-slate-600">
                        <span>Manage Bays & Dispatch</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modals -->
    <!-- Create Terminal Modal -->
    <div id="createModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
                    <div class="bg-slate-50 dark:bg-slate-800 px-6 py-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">New Terminal</h3>
                        <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-500 transition-all"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <input type="hidden" name="action" value="create_terminal">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Terminal Name</label>
                            <input name="name" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="e.g. Central Terminal">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">City / Municipality</label>
                            <input name="city" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="e.g. Caloocan City">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Address</label>
                            <textarea name="address" rows="2" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Max Capacity</label>
                            <input type="number" name="capacity" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="500">
                        </div>
                        <div class="pt-4 flex justify-end gap-3">
                            <button type="button" onclick="closeCreateModal()" class="px-4 py-2 rounded-md text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm transition-all">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-all">Create Terminal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Terminal Modal -->
    <div id="editModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
                    <div class="bg-slate-50 dark:bg-slate-800 px-6 py-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Edit Terminal</h3>
                        <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-500 transition-all"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <input type="hidden" name="action" value="update_terminal">
                        <input type="hidden" name="id" id="edit_id">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Terminal Name</label>
                            <input name="name" id="edit_name" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">City</label>
                            <input name="city" id="edit_city" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Address</label>
                            <textarea name="address" id="edit_address" rows="2" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Capacity</label>
                            <input type="number" name="capacity" id="edit_capacity" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div class="pt-4 flex justify-end gap-3">
                            <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-md text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm transition-all">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-all">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal (Manage Bays) -->
    <div id="terminalModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:w-full sm:max-w-5xl border border-slate-200 dark:border-slate-700 flex flex-col max-h-[90vh]">
                    <!-- Header -->
                    <div class="bg-slate-50 dark:bg-slate-800 px-6 py-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
                        <div>
                            <h2 id="modalTitle" class="text-lg font-bold text-slate-900 dark:text-white">Terminal Details</h2>
                            <p id="modalSubtitle" class="text-xs text-slate-500 mt-0.5">View routes and assignments</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button onclick="openAddAreaModal()" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500 transition-all shadow-sm">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Bay/Route
                            </button>
                            <button onclick="closeTerminalModal()" class="text-slate-400 hover:text-slate-500 transition-all">
                                <i data-lucide="x" class="w-6 h-6"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Content -->
                    <div id="modalContent" class="p-6 overflow-y-auto bg-slate-50/50 dark:bg-slate-900"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Area Modal -->
    <div id="addAreaModal" class="fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
                    <div class="bg-slate-50 dark:bg-slate-800 px-6 py-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Add Bay / Route</h3>
                        <button onclick="closeAddAreaModal()" class="text-slate-400 hover:text-slate-500 transition-all"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <input type="hidden" name="action" value="create_terminal_area">
                        <input type="hidden" name="terminal_id" id="area_terminal_id">
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Bay Name</label>
                            <input name="area_name" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="e.g. Bay 1 - Cubao">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Route</label>
                            <select name="route_name" id="add_route_select" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">Select Route...</option>
                                <?php foreach ($routes as $r): $rid = (string)($r['route_id'] ?? ''); ?>
                                    <option value="<?php echo htmlspecialchars($rid); ?>"><?php echo htmlspecialchars(($r['route_name'] ?? $rid) . ' (' . $rid . ')'); ?></option>
                                <?php endforeach; ?>
                                <option value="__custom__">Custom / Other</option>
                            </select>
                            <input type="text" name="route_name_custom" id="add_route_custom" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm hidden" placeholder="Enter custom route label">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fare Range</label>
                                <input name="fare_range" id="add_fare_range" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Max Slots</label>
                                <input type="number" name="max_slots" id="add_max_slots" value="10" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">PUV Type</label>
                            <select name="puv_type" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="Jeepney">Jeepney</option>
                                <option value="Bus">Bus</option>
                                <option value="Van">Van</option>
                                <option value="Tricycle">Tricycle</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="pt-4 flex justify-end gap-3">
                            <button type="button" onclick="closeAddAreaModal()" class="px-4 py-2 rounded-md text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm transition-all">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-all">Add Bay</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Area Modal -->
    <div id="editAreaModal" class="fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
                    <div class="bg-slate-50 dark:bg-slate-800 px-6 py-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Edit Bay / Route</h3>
                        <button onclick="closeEditAreaModal()" class="text-slate-400 hover:text-slate-500 transition-all"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <input type="hidden" name="action" value="update_terminal_area">
                        <input type="hidden" name="id" id="edit_area_id">
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Bay Name</label>
                            <input name="area_name" id="edit_area_name" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Route</label>
                            <select name="route_name" id="edit_route_select" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">Select Route...</option>
                                <?php foreach ($routes as $r): $rid = (string)($r['route_id'] ?? ''); ?>
                                    <option value="<?php echo htmlspecialchars($rid); ?>"><?php echo htmlspecialchars(($r['route_name'] ?? $rid) . ' (' . $rid . ')'); ?></option>
                                <?php endforeach; ?>
                                <option value="__custom__">Custom / Other</option>
                            </select>
                            <input type="text" name="route_name_custom" id="edit_route_custom" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm hidden">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fare Range</label>
                                <input name="fare_range" id="edit_fare_range" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Max Slots</label>
                                <input type="number" name="max_slots" id="edit_max_slots" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">PUV Type</label>
                            <select name="puv_type" id="edit_puv_type" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="Jeepney">Jeepney</option>
                                <option value="Bus">Bus</option>
                                <option value="Van">Van</option>
                                <option value="Tricycle">Tricycle</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="pt-4 flex justify-end gap-3">
                            <button type="button" onclick="closeEditAreaModal()" class="px-4 py-2 rounded-md text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm transition-all">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-all">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Data
const terminalData = <?php echo json_encode($terminals); ?>;
const areaData = <?php echo json_encode($areas); ?>;
const operatorData = <?php echo json_encode($operators); ?>;
const routeData = <?php echo json_encode($routes); ?>;
const assignmentsData = <?php echo json_encode($assignmentsByTerminalRoute); ?>;
const routeMap = {};
(routeData || []).forEach(r => { if(r.route_id) routeMap[r.route_id] = r; });

// Route Controls Update Helper
function updateRouteControls(sel, cust, hint, fare, slots) {
    if(!sel) return;
    const v = sel.value;
    const isCustom = v === '__custom__';
    if(cust) cust.classList.toggle('hidden', !isCustom);
    if(isCustom) return;
    
    const r = routeMap[v];
    if(r) {
        if(fare && !fare.value && r.fare > 0) fare.value = '₱' + Number(r.fare).toFixed(2);
        if(slots && (!slots.value || slots.value <= 0) && r.max_vehicle_limit > 0) slots.value = Math.min(r.max_vehicle_limit, 25);
    }
}

// Modal Toggle Helpers
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

// Global functions for buttons
window.openCreateModal = () => toggleModal('createModal', true);
window.closeCreateModal = () => toggleModal('createModal', false);
window.openEditModal = (id) => {
    const t = terminalData.find(x => x.id == id);
    if(!t) return;
    document.getElementById('edit_id').value = t.id;
    document.getElementById('edit_name').value = t.name;
    document.getElementById('edit_city').value = t.city;
    document.getElementById('edit_address').value = t.address;
    document.getElementById('edit_capacity').value = t.capacity;
    toggleModal('editModal', true);
};
window.closeEditModal = () => toggleModal('editModal', false);
window.openAddAreaModal = () => toggleModal('addAreaModal', true);
window.closeAddAreaModal = () => toggleModal('addAreaModal', false);
window.closeTerminalModal = () => toggleModal('terminalModal', false);

window.openEditAreaModal = (id) => {
    let area = null;
    for(let tid in areaData) {
        const found = areaData[tid].find(a => a.area_id == id);
        if(found) { area = found; break; }
    }
    if(!area) return;
    
    document.getElementById('edit_area_id').value = area.area_id;
    document.getElementById('edit_area_name').value = area.area_name;
    document.getElementById('edit_fare_range').value = area.fare_range;
    document.getElementById('edit_max_slots').value = area.max_slots;
    document.getElementById('edit_puv_type').value = area.puv_type;
    
    const sel = document.getElementById('edit_route_select');
    if(sel) {
        if(routeMap[area.route_name]) sel.value = area.route_name;
        else sel.value = '__custom__';
        
        const cust = document.getElementById('edit_route_custom');
        if(cust) {
            cust.classList.toggle('hidden', sel.value !== '__custom__');
            if(sel.value === '__custom__') cust.value = area.route_name;
        }
    }
    toggleModal('editAreaModal', true);
};
window.closeEditAreaModal = () => toggleModal('editAreaModal', false);

window.openTerminalModal = (id) => {
    const term = terminalData.find(t => t.id == id);
    if(!term) return;
    document.getElementById('area_terminal_id').value = id;
    document.getElementById('modalTitle').textContent = term.name;
    document.getElementById('modalSubtitle').textContent = (term.city || '') + ' • ' + (term.address || '');
    
    const areas = areaData[id] || [];
    const container = document.getElementById('modalContent');
    
    if(areas.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 mb-4">
                    <i data-lucide="layers" class="w-6 h-6"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">No bays configured</h3>
                <p class="text-xs text-slate-500 mt-1">Add a bay or route to start assigning operators.</p>
            </div>
        `;
    } else {
        container.innerHTML = `<div class="grid grid-cols-1 gap-4">` + areas.map(area => {
            const ops = operatorData[area.area_id] ? Object.values(operatorData[area.area_id]) : [];
            const rMeta = routeMap[area.route_name];
            const routeDisplay = rMeta ? (area.route_name + ' • ' + rMeta.route_name) : (area.route_name || 'Unlinked');
            const termName = term.name;
            const asns = (assignmentsData[termName] && assignmentsData[termName][area.route_name]) || [];
            const authCount = asns.filter(x => x.status === 'Authorized').length;
            
            return `
            <div class="group relative overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
                <div class="absolute top-0 left-0 w-1 h-full bg-blue-600"></div>
                <div class="p-5 pl-6">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                ${area.area_name}
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase tracking-wider">${area.puv_type}</span>
                            </h4>
                            <div class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1.5">
                                <i data-lucide="map" class="w-3.5 h-3.5"></i> ${routeDisplay}
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button onclick="openEditAreaModal(${area.area_id})" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-blue-600 transition-all"><i data-lucide="settings-2" class="w-4 h-4"></i></button>
                            <form method="POST" onsubmit="return confirm('Delete this area?');" class="inline">
                                <input type="hidden" name="action" value="delete_terminal_area">
                                <input type="hidden" name="id" value="${area.area_id}">
                                <button class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-red-600 transition-all"><i data-lucide="trash" class="w-4 h-4"></i></button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-3 rounded-md bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-700">
                            <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Assigned Vehicles</div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-lg font-bold text-slate-900 dark:text-white">${authCount}</span>
                                <span class="text-xs font-medium text-slate-500">/ ${area.max_slots} slots</span>
                            </div>
                            <div class="h-1.5 w-full bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full" style="width: ${Math.min((authCount/area.max_slots)*100, 100)}%"></div>
                            </div>
                            <a href="?page=module1/submodule3&route_id=${encodeURIComponent(area.route_name)}" class="mt-2 block text-center text-xs font-semibold text-blue-600 hover:text-blue-500 hover:underline">Manage Assignments</a>
                        </div>
                        
                        <div class="p-3 rounded-md bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-700">
                            <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Operators</div>
                            <div class="flex flex-wrap gap-1.5">
                                ${ops.length > 0 ? ops.map(o => `
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-[10px] font-bold text-slate-700 dark:text-slate-300">
                                        <i data-lucide="user" class="w-3 h-3 text-slate-400"></i> ${o.name}
                                    </span>
                                `).join('') : '<span class="text-xs italic text-slate-400">No operators linked</span>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('') + `</div>`;
    }
    toggleModal('terminalModal', true);
};

// Listeners
document.addEventListener('DOMContentLoaded', () => {
    if(window.lucide) window.lucide.createIcons();
    
    const addSel = document.getElementById('add_route_select');
    if(addSel) addSel.addEventListener('change', () => updateRouteControls(addSel, document.getElementById('add_route_custom'), null, document.getElementById('add_fare_range'), document.getElementById('add_max_slots')));
    
    const search = document.getElementById('terminalSearch');
    if(search) {
        search.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase();
            document.querySelectorAll('.terminal-card').forEach(el => {
                const txt = (el.dataset.name + ' ' + el.dataset.city).toLowerCase();
                el.classList.toggle('hidden', !txt.includes(q));
            });
        });
    }
});
</script>
