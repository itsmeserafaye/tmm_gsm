<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Parking Area Management</h1>
            <p class="text-sm text-slate-500">City-wide parking facilities and status</p>
        </div>
        <button onclick="openModal('modalAddParking')" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Parking Area
        </button>
    </div>

    <!-- Parking Grid -->
    <div id="parkingGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Cards injected via JS -->
    </div>
</div>

<!-- Add Parking Modal -->
<div id="modalAddParking" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">Add Parking Area</h3>
        <form id="formAddParking">
            <div class="space-y-3">
                <input name="name" placeholder="Parking Name" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600" required>
                <input name="city" value="Caloocan City" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600">
                <input name="location" placeholder="Location / Address" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600">
                
                <select name="type" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600" onchange="toggleTerminalSelect(this.value)">
                    <option value="Standalone">Standalone / Non-Terminal</option>
                    <option value="Terminal Parking">Terminal Parking</option>
                </select>
                
                <div id="divTermSelect" class="hidden">
                    <select name="terminal_id" id="selTerminals" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600">
                        <option value="">Select Associated Terminal</option>
                    </select>
                </div>

                <input name="total_slots" type="number" placeholder="Total Slots" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600">
                <input name="allowed_puv_types" placeholder="Allowed Vehicle Types" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600">
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeModal('modalAddParking')" class="px-4 py-2 text-slate-600">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Parking Detail Modal -->
<div id="modalParkingDetail" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-lg p-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h2 id="pkName" class="text-2xl font-bold">Parking Name</h2>
                <span id="pkType" class="text-xs px-2 py-1 rounded bg-slate-100 dark:bg-slate-700">Type</span>
            </div>
            <button onclick="closeModal('modalParkingDetail')" class="text-slate-400"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        
        <div class="space-y-4">
            <div class="p-4 bg-slate-50 dark:bg-slate-700/50 rounded-lg grid grid-cols-2 gap-4">
                <div>
                    <span class="text-xs text-slate-500 uppercase font-bold">Location</span>
                    <div id="pkLoc" class="font-medium">...</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500 uppercase font-bold">City</span>
                    <div id="pkCity" class="font-medium">...</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500 uppercase font-bold">Associated Terminal</span>
                    <div id="pkTerm" class="font-medium text-blue-600">None</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500 uppercase font-bold">Status</span>
                    <div id="pkStatus" class="font-medium text-green-600">Available</div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-2">Capacity & Usage</h3>
                <div class="w-full bg-slate-200 rounded-full h-4 dark:bg-slate-700">
                    <div class="bg-blue-600 h-4 rounded-full" style="width: 45%"></div>
                </div>
                <div class="flex justify-between text-sm mt-1 text-slate-500">
                    <span>Used: 45 (Simulated)</span>
                    <span id="pkSlots">Total: 100</span>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-2">Allowed Vehicles</h3>
                <div id="pkVehicles" class="flex flex-wrap gap-2">
                    <!-- Badges -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadParking() {
    fetch('api/module5/get_parking_areas.php')
        .then(r => r.json())
        .then(data => {
            const grid = document.getElementById('parkingGrid');
            grid.innerHTML = data.map(p => `
                <div onclick='showParking(${JSON.stringify(p)})' class="cursor-pointer bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 hover:shadow-lg hover:border-blue-500 transition-all duration-200">
                    <div class="flex justify-between items-start mb-3">
                        <div class="p-2 rounded bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">
                            <i data-lucide="parking-circle" class="w-6 h-6"></i>
                        </div>
                        <span class="text-xs font-mono text-slate-400">${p.type === 'Terminal Parking' ? 'TERMINAL' : 'STANDALONE'}</span>
                    </div>
                    <h3 class="font-bold text-lg mb-1 truncate">${p.name}</h3>
                    <p class="text-sm text-slate-500 mb-4 truncate">${p.location}</p>
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <i data-lucide="car" class="w-4 h-4 text-slate-400"></i>
                        <span>${p.total_slots} Slots</span>
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        });
}

function showParking(p) {
    document.getElementById('pkName').textContent = p.name;
    document.getElementById('pkType').textContent = p.type;
    document.getElementById('pkLoc').textContent = p.location;
    document.getElementById('pkCity').textContent = p.city;
    document.getElementById('pkTerm').textContent = p.terminal_name || 'None';
    document.getElementById('pkStatus').textContent = p.status;
    document.getElementById('pkSlots').textContent = 'Total: ' + p.total_slots;
    
    const vehicles = p.allowed_puv_types ? p.allowed_puv_types.split(',') : [];
    document.getElementById('pkVehicles').innerHTML = vehicles.map(v => 
        `<span class="px-2 py-1 bg-slate-100 dark:bg-slate-700 rounded text-xs">${v.trim()}</span>`
    ).join('');

    openModal('modalParkingDetail');
}

function toggleTerminalSelect(type) {
    const div = document.getElementById('divTermSelect');
    if (type === 'Terminal Parking') {
        div.classList.remove('hidden');
        // Load terminals if empty
        if (document.getElementById('selTerminals').options.length <= 1) {
            fetch('api/module5/get_terminals_full.php')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('selTerminals').innerHTML = '<option value="">Select Terminal</option>' + 
                        data.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
                });
        }
    } else {
        div.classList.add('hidden');
    }
}

document.getElementById('formAddParking').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('api/module5/save_parking_area.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeModal('modalAddParking');
                this.reset();
                loadParking();
            } else {
                alert(res.message);
            }
        });
});

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

loadParking();
</script>