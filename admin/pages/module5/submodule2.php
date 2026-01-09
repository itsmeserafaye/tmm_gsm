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
    while($row = $terminals_res->fetch_assoc()) {
        $terminals_list[] = $row;
    }
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
    while($row = $res->fetch_assoc()) {
        $parking_areas[] = $row;
    }
}

// Group by City for better organization (Optional but nice)
$grouped_areas = [];
foreach ($parking_areas as $area) {
    $grouped_areas[$area['city']][] = $area;
}
?>

<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg min-h-screen">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">Parking Area Management</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">City-wide parking areas and status monitoring.</p>
        </div>
        <button onclick="openCreateModal()" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Parking Area
        </button>
    </div>

    <?php if (empty($grouped_areas)): ?>
        <div class="text-center py-12 text-slate-500">
            <p>No parking areas found.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped_areas as $city => $areas): ?>
            <h2 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-4 mt-6 border-b dark:border-slate-700 pb-2 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <?php echo htmlspecialchars($city); ?>
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($areas as $area): ?>
                    <div onclick="openParkingModal(<?php echo $area['id']; ?>)" class="cursor-pointer group relative p-6 border rounded-lg hover:shadow-xl transition-all duration-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:-translate-y-1">
                        <!-- Status Badge -->
                        <div class="absolute top-4 right-4">
                            <?php 
                            $statusColor = 'bg-gray-100 text-gray-800';
                            if ($area['status'] === 'Available') $statusColor = 'bg-green-100 text-green-800';
                            elseif ($area['status'] === 'Full') $statusColor = 'bg-red-100 text-red-800';
                            elseif ($area['status'] === 'Maintenance') $statusColor = 'bg-yellow-100 text-yellow-800';
                            ?>
                            <span class="px-2 py-1 text-xs rounded font-semibold <?php echo $statusColor; ?>">
                                <?php echo $area['status']; ?>
                            </span>
                        </div>

                        <div class="mb-4 pr-12">
                            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 truncate"><?php echo htmlspecialchars($area['name']); ?></h3>
                            <p class="text-sm text-slate-500 truncate"><?php echo htmlspecialchars($area['type']); ?></p>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t dark:border-slate-700">
                            <div>
                                <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Total Slots</div>
                                <div class="text-2xl font-bold text-slate-700 dark:text-slate-200"><?php echo $area['total_slots']; ?></div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Allowed Types</div>
                                <div class="flex -space-x-1 justify-end">
                                    <!-- Simple visual representation based on text -->
                                    <?php 
                                    $types = explode(',', $area['allowed_puv_types'] ?? '');
                                    foreach(array_slice($types, 0, 3) as $type): 
                                    ?>
                                        <span class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-600 border-2 border-white dark:border-slate-800 flex items-center justify-center text-[10px] font-bold text-slate-600 dark:text-slate-300" title="<?php echo trim($type); ?>">
                                            <?php echo strtoupper(substr(trim($type), 0, 1)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if(count($types) > 3): ?>
                                        <span class="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-700 border-2 border-white dark:border-slate-800 flex items-center justify-center text-[8px] text-slate-500">+</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Modal -->
    <div id="parkingModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-50 transition-opacity opacity-0">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="parkingModalPanel">
            <div class="p-6 border-b dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800">
                <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Parking Details</h2>
                <button onclick="closeParkingModal()" class="text-slate-400 hover:text-red-500 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-6 space-y-4" id="parkingModalContent">
                <!-- Content injected via JS -->
            </div>
            
            <div id="parkingModalFooter" class="p-4 bg-slate-50 dark:bg-slate-800 border-t dark:border-slate-700 text-right">
                <button onclick="closeParkingModal()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded hover:bg-slate-300 transition">Close</button>
            </div>
        </div>
    </div>

    <!-- Form Modal -->
    <div id="formModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-[60] transition-opacity opacity-0">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-11/12 max-w-lg overflow-hidden transform scale-95 transition-transform" id="formModalPanel">
            <form method="POST" id="parkingForm">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="formId">
                
                <div class="p-6 border-b dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100" id="formTitle">Add Parking Area</h2>
                    <button type="button" onclick="closeFormModal()" class="text-slate-400 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Parking Name</label>
                        <input type="text" name="name" id="p_name" required class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">City</label>
                            <input type="text" name="city" id="p_city" required class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Type</label>
                            <select name="type" id="p_type" onchange="toggleTerminalSelect()" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Terminal Parking">Terminal Parking</option>
                                <option value="Non-Terminal">Non-Terminal / Standalone</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="terminalSelectGroup">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Associated Terminal</label>
                        <select name="terminal_id" id="p_terminal" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Select Terminal --</option>
                            <?php foreach($terminals_list as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name'] . ' (' . $t['city'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Location / Address</label>
                        <textarea name="location" id="p_location" rows="2" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Total Slots</label>
                            <input type="number" name="total_slots" id="p_slots" required class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Current Status</label>
                            <select name="status" id="p_status" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Available">Available</option>
                                <option value="Full">Full</option>
                                <option value="Restricted">Restricted</option>
                                <option value="Maintenance">Under Maintenance</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Allowed PUV Types</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="allowed_types[]" value="Tricycle" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-slate-600 dark:text-slate-400">Tricycle</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="allowed_types[]" value="Jeepney" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-slate-600 dark:text-slate-400">Jeepney</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="allowed_types[]" value="Bus" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-slate-600 dark:text-slate-400">Bus</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="allowed_types[]" value="Van" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-slate-600 dark:text-slate-400">Van</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="allowed_types[]" value="Private" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-slate-600 dark:text-slate-400">Private Vehicle</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="p-4 bg-slate-50 dark:bg-slate-800 border-t dark:border-slate-700 text-right space-x-2">
                    <button type="button" onclick="closeFormModal()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded hover:bg-slate-300 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Save Parking Area</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const parkingData = <?php echo json_encode($parking_areas); ?>;

function toggleTerminalSelect() {
    const type = document.getElementById('p_type').value;
    const group = document.getElementById('terminalSelectGroup');
    if (type === 'Terminal Parking') {
        group.classList.remove('hidden');
    } else {
        group.classList.add('hidden');
    }
}

// Form Modal Functions
function openCreateModal() {
    document.getElementById('formTitle').innerText = 'Add Parking Area';
    document.getElementById('formAction').value = 'create_parking_area';
    document.getElementById('formId').value = '';
    document.getElementById('parkingForm').reset();
    toggleTerminalSelect(); // Reset visibility

    showFormModal();
}

function openEditModal(id) {
    const area = parkingData.find(a => a.id == id);
    if(!area) return;

    document.getElementById('formTitle').innerText = 'Edit Parking Area';
    document.getElementById('formAction').value = 'update_parking_area';
    document.getElementById('formId').value = area.id;

    // Populate fields
    document.getElementById('p_name').value = area.name;
    document.getElementById('p_city').value = area.city;
    document.getElementById('p_location').value = area.location;
    document.getElementById('p_type').value = area.type;
    document.getElementById('p_slots').value = area.total_slots;
    document.getElementById('p_terminal').value = area.terminal_id || '';
    document.getElementById('p_status').value = area.status;

    // Checkboxes
    const allowed = (area.allowed_puv_types || '').split(',').map(s => s.trim());
    document.querySelectorAll('input[name="allowed_types[]"]').forEach(cb => {
        cb.checked = allowed.includes(cb.value);
    });

    toggleTerminalSelect();
    showFormModal();
}

function showFormModal() {
    const modal = document.getElementById('formModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('formModalPanel').classList.remove('scale-95');
        document.getElementById('formModalPanel').classList.add('scale-100');
    }, 10);
}

function closeFormModal() {
    const modal = document.getElementById('formModal');
    modal.classList.add('opacity-0');
    document.getElementById('formModalPanel').classList.remove('scale-100');
    document.getElementById('formModalPanel').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function openParkingModal(id) {
    const area = parkingData.find(a => a.id == id);
    if(!area) return;

    let statusClass = 'text-gray-600';
    if(area.status === 'Available') statusClass = 'text-green-600';
    else if(area.status === 'Full') statusClass = 'text-red-600';
    else if(area.status === 'Maintenance') statusClass = 'text-yellow-600';

    const html = `
        <div class="mb-4">
            <h3 class="text-2xl font-bold text-slate-800 dark:text-slate-100 mb-1">${area.name}</h3>
            <div class="flex items-center text-sm text-slate-500">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                ${area.location || 'No location specified'}, ${area.city}
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded border dark:border-slate-700">
                <div class="text-xs text-slate-500 uppercase">Type</div>
                <div class="font-semibold text-slate-700 dark:text-slate-200">${area.type}</div>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded border dark:border-slate-700">
                <div class="text-xs text-slate-500 uppercase">Status</div>
                <div class="font-bold ${statusClass}">${area.status}</div>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded border dark:border-slate-700">
                <div class="text-xs text-slate-500 uppercase">Total Slots</div>
                <div class="font-semibold text-slate-700 dark:text-slate-200">${area.total_slots}</div>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded border dark:border-slate-700">
                <div class="text-xs text-slate-500 uppercase">Allowed PUVs</div>
                <div class="font-semibold text-slate-700 dark:text-slate-200">${area.allowed_puv_types || 'Any'}</div>
            </div>
        </div>

        ${area.associated_terminal_name ? `
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-lg">
                <div class="text-xs text-blue-500 uppercase mb-1">Associated Terminal</div>
                <div class="font-semibold text-blue-800 dark:text-blue-200 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    ${area.associated_terminal_name}
                </div>
            </div>
        ` : ''}
    `;

    document.getElementById('parkingModalContent').innerHTML = html;
    
    // Inject Footer Actions
    const footerHtml = `
        <div class="flex justify-between items-center w-full">
            <button onclick="deleteParkingArea(${area.id})" class="text-red-500 hover:text-red-700 text-sm font-medium px-3 py-2 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                Delete Area
            </button>
            <div class="space-x-2">
                <button onclick="closeParkingModal()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded hover:bg-slate-300 transition">Close</button>
                <button onclick="openEditParkingModal(${area.id})" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Edit Details</button>
            </div>
        </div>
    `;
    document.getElementById('parkingModalFooter').innerHTML = footerHtml;

    const modal = document.getElementById('parkingModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('parkingModalPanel').classList.remove('scale-95');
        document.getElementById('parkingModalPanel').classList.add('scale-100');
    }, 10);
}

function closeParkingModal() {
    const modal = document.getElementById('parkingModal');
    modal.classList.add('opacity-0');
    document.getElementById('parkingModalPanel').classList.remove('scale-100');
    document.getElementById('parkingModalPanel').classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function openEditParkingModal(id) {
    closeParkingModal();
    openEditModal(id);
}

function deleteParkingArea(id) {
    if(confirm('Are you sure you want to delete this parking area? This cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete_parking_area"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
