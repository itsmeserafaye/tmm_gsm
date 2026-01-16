<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100">
    <?php
    require_once __DIR__ . '/../../includes/db.php';
    $db = db();

    // --- Handle Actions ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create_parking_area') {
                $name = $db->real_escape_string($_POST['name']);
                $city = $db->real_escape_string($_POST['city']);
                $location = $db->real_escape_string($_POST['location']);
                $type = $db->real_escape_string($_POST['type']);
                $terminal_id = !empty($_POST['terminal_id']) ? (int)$_POST['terminal_id'] : "NULL";
                $total_slots = (int)$_POST['total_slots'];
                $allowed_types = $db->real_escape_string(implode(',', $_POST['allowed_types'] ?? []));
                $status = $db->real_escape_string($_POST['status']);

                $sql = "INSERT INTO parking_areas (name, city, location, type, terminal_id, total_slots, allowed_puv_types, status) 
                        VALUES ('$name', '$city', '$location', '$type', $terminal_id, $total_slots, '$allowed_types', '$status')";
                
                if($db->query($sql)) {
                    echo "<script>window.location.href = window.location.href;</script>";
                }
            }
            
            if ($_POST['action'] === 'update_parking_area') {
                $id = (int)$_POST['id'];
                $name = $db->real_escape_string($_POST['name']);
                $city = $db->real_escape_string($_POST['city']);
                $location = $db->real_escape_string($_POST['location']);
                $type = $db->real_escape_string($_POST['type']);
                $terminal_id = !empty($_POST['terminal_id']) ? (int)$_POST['terminal_id'] : "NULL";
                $total_slots = (int)$_POST['total_slots'];
                $allowed_types = $db->real_escape_string(implode(',', $_POST['allowed_types'] ?? []));
                $status = $db->real_escape_string($_POST['status']);

                $sql = "UPDATE parking_areas SET 
                        name='$name', city='$city', location='$location', type='$type', 
                        terminal_id=$terminal_id, total_slots=$total_slots, allowed_puv_types='$allowed_types', status='$status' 
                        WHERE id=$id";
                
                if($db->query($sql)) {
                    echo "<script>window.location.href = window.location.href;</script>";
                }
            }

            if ($_POST['action'] === 'delete_parking_area') {
                $id = (int)$_POST['id'];
                $db->query("DELETE FROM parking_areas WHERE id=$id");
                echo "<script>window.location.href = window.location.href;</script>";
            }
        }
    }

    // Fetch Terminals for Dropdown
    $terminals_res = $db->query("SELECT id, name, city FROM terminals ORDER BY name");
    $terminals_list = [];
    if($terminals_res) {
        while($row = $terminals_res->fetch_assoc()) $terminals_list[] = $row;
    }

    // Fetch Parking Areas
    $sql = "
        SELECT 
            pa.*,
            t.name as associated_terminal_name
        FROM parking_areas pa
        LEFT JOIN terminals t ON pa.terminal_id = t.id
        ORDER BY pa.city, pa.name
    ";
    $res = $db->query($sql);
    $parking_areas = [];
    if($res) {
        while($row = $res->fetch_assoc()) $parking_areas[] = $row;
    }

    // Stats
    $totalAreas = count($parking_areas);
    $totalSlots = array_sum(array_column($parking_areas, 'total_slots'));
    $activeAreas = count(array_filter($parking_areas, fn($a) => $a['status'] === 'Available'));

    // Group by City
    $grouped_areas = [];
    foreach ($parking_areas as $area) {
        $grouped_areas[$area['city']][] = $area;
    }
    ?>

    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-8 border-b border-slate-200 dark:border-slate-700 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Parking Area Management</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Monitor city-wide parking availability, designated zones, and capacity status.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openCreateModal()" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 hover:bg-blue-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Add Parking Area
            </button>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-8">
        <!-- Stat Card 1 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-emerald-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Areas</div>
                <i data-lucide="map" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($totalAreas); ?></div>
        </div>

        <!-- Stat Card 2 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-blue-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Capacity</div>
                <i data-lucide="layout-grid" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($totalSlots); ?> <span class="text-sm font-medium text-slate-400">slots</span></div>
        </div>

        <!-- Stat Card 3 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-purple-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Available Now</div>
                <i data-lucide="check-circle" class="w-4 h-4 text-purple-600 dark:text-purple-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($activeAreas); ?> <span class="text-sm font-medium text-slate-400">areas</span></div>
        </div>
    </div>

    <!-- Grouped Areas -->
    <?php if (empty($grouped_areas)): ?>
        <div class="p-12 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-center">
            <div class="mx-auto h-12 w-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                <i data-lucide="parking-square-off" class="w-6 h-6 text-slate-400"></i>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">No parking areas found</h3>
            <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Add designated parking zones to start monitoring capacity.</p>
        </div>
    <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($grouped_areas as $city => $areas): ?>
                <div class="relative">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="h-6 w-1 rounded-full bg-blue-600"></div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($city); ?></h2>
                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-500"><?php echo count($areas); ?> Areas</span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($areas as $area): ?>
                            <?php 
                            $statusColor = match($area['status']) {
                                'Available' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/20 dark:text-emerald-400',
                                'Full' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-900/20 dark:text-red-400',
                                'Maintenance' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/20 dark:text-amber-400',
                                default => 'bg-slate-50 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                            };
                            $types = explode(',', $area['allowed_puv_types'] ?? '');
                            ?>
                            <div class="group relative p-6 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
                                <div class="flex justify-between items-start mb-3">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset <?php echo $statusColor; ?>">
                                        <?php echo $area['status']; ?>
                                    </span>
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button onclick="openEditModal(<?php echo $area['id']; ?>)" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-blue-600 transition-all">
                                            <i data-lucide="edit-2" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="deleteParkingArea(<?php echo $area['id']; ?>)" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-red-600 transition-all">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>

                                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1"><?php echo htmlspecialchars($area['name']); ?></h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mb-4">
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                                    <?php echo htmlspecialchars($area['location'] ?? $area['city']); ?>
                                </p>

                                <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-700">
                                    <div>
                                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Capacity</div>
                                        <div class="text-lg font-bold text-slate-900 dark:text-white"><?php echo $area['total_slots']; ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Allowed</div>
                                        <div class="flex -space-x-1 justify-end">
                                            <?php foreach(array_slice($types, 0, 3) as $type): $t = trim($type); ?>
                                                <div class="h-6 w-6 rounded-full bg-slate-100 dark:bg-slate-700 border border-white dark:border-slate-800 flex items-center justify-center text-[10px] font-bold text-slate-600 dark:text-slate-300" title="<?php echo $t; ?>">
                                                    <?php echo strtoupper(substr($t, 0, 1)); ?>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if(count($types) > 3): ?>
                                                <div class="h-6 w-6 rounded-full bg-slate-50 dark:bg-slate-800 border border-white dark:border-slate-800 flex items-center justify-center text-[10px] font-bold text-slate-400">
                                                    +<?php echo count($types) - 3; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Form Modal (Create/Edit) -->
    <div id="formModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
                    <div class="bg-slate-50 dark:bg-slate-800 px-6 py-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white" id="formTitle">Parking Area</h3>
                        <button onclick="closeFormModal()" class="text-slate-400 hover:text-slate-500 transition-all"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <form method="POST" id="parkingForm" class="p-6 space-y-4">
                        <input type="hidden" name="action" id="formAction">
                        <input type="hidden" name="id" id="formId">
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Parking Name</label>
                            <input name="name" id="p_name" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">City</label>
                                <input name="city" id="p_city" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Type</label>
                                <select name="type" id="p_type" onchange="toggleTerminalSelect()" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    <option value="Terminal Parking">Terminal Parking</option>
                                    <option value="Non-Terminal">Non-Terminal / Standalone</option>
                                </select>
                            </div>
                        </div>

                        <div id="terminalSelectGroup">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Associated Terminal</label>
                            <select name="terminal_id" id="p_terminal" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">-- Select Terminal --</option>
                                <?php foreach($terminals_list as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name'] . ' (' . $t['city'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Location / Address</label>
                            <textarea name="location" id="p_location" rows="2" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Total Slots</label>
                                <input type="number" name="total_slots" id="p_slots" required class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 px-3 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Current Status</label>
                                <select name="status" id="p_status" class="block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    <option value="Available">Available</option>
                                    <option value="Full">Full</option>
                                    <option value="Restricted">Restricted</option>
                                    <option value="Maintenance">Under Maintenance</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Allowed PUV Types</label>
                            <div class="grid grid-cols-2 gap-3">
                                <?php $ptypes = ['Tricycle', 'Jeepney', 'Bus', 'Van', 'Private']; ?>
                                <?php foreach($ptypes as $pt): ?>
                                    <label class="flex items-center gap-2 p-2 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-slate-100 transition-all">
                                        <input type="checkbox" name="allowed_types[]" value="<?php echo $pt; ?>" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-slate-700 dark:text-slate-300"><?php echo $pt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-end gap-3">
                            <button type="button" onclick="closeFormModal()" class="px-4 py-2 rounded-md text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm transition-all">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-all">Save Area</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const parkingData = <?php echo json_encode($parking_areas); ?>;

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

function toggleTerminalSelect() {
    const type = document.getElementById('p_type').value;
    const group = document.getElementById('terminalSelectGroup');
    if (type === 'Terminal Parking') {
        group.classList.remove('hidden');
    } else {
        group.classList.add('hidden');
    }
}

window.openCreateModal = () => {
    document.getElementById('formTitle').innerText = 'Add Parking Area';
    document.getElementById('formAction').value = 'create_parking_area';
    document.getElementById('formId').value = '';
    document.getElementById('parkingForm').reset();
    toggleTerminalSelect();
    toggleModal('formModal', true);
};

window.openEditModal = (id) => {
    const area = parkingData.find(a => a.id == id);
    if(!area) return;

    document.getElementById('formTitle').innerText = 'Edit Parking Area';
    document.getElementById('formAction').value = 'update_parking_area';
    document.getElementById('formId').value = area.id;

    document.getElementById('p_name').value = area.name;
    document.getElementById('p_city').value = area.city;
    document.getElementById('p_location').value = area.location;
    document.getElementById('p_type').value = area.type;
    document.getElementById('p_slots').value = area.total_slots;
    document.getElementById('p_terminal').value = area.terminal_id || '';
    document.getElementById('p_status').value = area.status;

    const allowed = (area.allowed_puv_types || '').split(',').map(s => s.trim());
    document.querySelectorAll('input[name="allowed_types[]"]').forEach(cb => {
        cb.checked = allowed.includes(cb.value);
    });

    toggleTerminalSelect();
    toggleModal('formModal', true);
};

window.closeFormModal = () => toggleModal('formModal', false);

window.deleteParkingArea = (id) => {
    if(confirm('Are you sure you want to delete this parking area? This cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete_parking_area"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if(window.lucide) window.lucide.createIcons();
});
</script>
