<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Terminal Management</h1>
        <button onclick="openModal('modalAddTerminal')" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Add Terminal
        </button>
    </div>

    <!-- Terminals Grid -->
    <div id="terminalsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Cards injected via JS -->
    </div>
</div>

<!-- Terminal Details Modal -->
<div id="modalTerminal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex justify-between items-start">
            <div>
                <h2 id="termName" class="text-2xl font-bold text-slate-900 dark:text-white">Terminal Name</h2>
                <p id="termLoc" class="text-slate-500 dark:text-slate-400 text-sm flex items-center gap-1 mt-1">
                    <i data-lucide="map-pin" class="w-4 h-4"></i> <span id="termCity">City</span>
                </p>
            </div>
            <button onclick="closeModal('modalTerminal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto">
            <!-- Tabs -->
            <div class="flex border-b border-slate-200 dark:border-slate-700 mb-6">
                <button class="px-4 py-2 border-b-2 border-blue-600 text-blue-600 font-medium">Designated Areas & Routes</button>
                <button class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200">Operators & Drivers</button>
            </div>

            <!-- Areas Section -->
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-lg">Queuing Lines / Areas</h3>
                    <button onclick="openAddArea()" class="text-sm px-3 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-md">
                        + Add Area
                    </button>
                </div>
                
                <div class="grid gap-4" id="areasList">
                    <!-- Area Items -->
                </div>
            </div>

            <!-- Operators Section (Hidden initially, simplified for this view) -->
            <div class="mt-8">
                <h3 class="font-semibold text-lg mb-4">Assigned Operators</h3>
                <div class="space-y-2" id="operatorsList">
                    <!-- Operator Items -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Area Modal -->
<div id="modalAddArea" class="fixed inset-0 z-[60] hidden bg-black/20 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4">Add Designated Area</h3>
        <form id="formAddArea">
            <input type="hidden" name="terminal_id" id="inputTermId">
            <div class="space-y-3">
                <input name="area_name" placeholder="Area Name (e.g. Line 1)" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600" required>
                <input name="route_name" placeholder="Route Name (e.g. Downtown)" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600">
                <input name="fare_range" placeholder="Fare Range (e.g. 15-20 PHP)" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600">
                <div class="grid grid-cols-2 gap-3">
                    <input name="max_slots" type="number" placeholder="Max Slots" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600">
                    <input name="puv_type" placeholder="PUV Type" class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:border-slate-600">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalAddArea').classList.add('hidden')" class="px-4 py-2 text-slate-600">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentTerminalId = null;

function loadTerminals() {
    fetch('api/module5/get_terminals_full.php')
        .then(r => r.json())
        .then(data => {
            const grid = document.getElementById('terminalsGrid');
            grid.innerHTML = data.map(t => `
                <div onclick="showTerminal(${t.id})" class="group cursor-pointer bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 hover:shadow-lg hover:border-blue-500 transition-all duration-200">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-blue-600 dark:text-blue-400 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <i data-lucide="bus" class="w-6 h-6"></i>
                        </div>
                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 rounded-full">${t.status}</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">${t.name}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">${t.location || 'No location'}</p>
                    
                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <div>
                            <div class="text-2xl font-bold text-slate-900 dark:text-white">${t.area_count}</div>
                            <div class="text-xs text-slate-500">Routes/Areas</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-slate-900 dark:text-white">${t.operator_count}</div>
                            <div class="text-xs text-slate-500">Operators</div>
                        </div>
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        });
}

function showTerminal(id) {
    currentTerminalId = id;
    fetch(`api/module5/get_terminal_details.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            const t = data.terminal;
            document.getElementById('termName').textContent = t.name;
            document.getElementById('termCity').textContent = (t.location || '') + ', ' + (t.city || '');
            
            // Areas
            const areasHtml = data.areas.map(a => `
                <div class="p-4 border rounded-lg dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white">${a.area_name}</h4>
                            <p class="text-sm text-slate-500">${a.route_name || 'No specific route'}</p>
                        </div>
                        <span class="px-2 py-1 text-xs font-mono bg-slate-200 dark:bg-slate-700 rounded">${a.puv_type}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        <div><span class="text-slate-500 block text-xs">Fare</span> ${a.fare_range || '-'}</div>
                        <div><span class="text-slate-500 block text-xs">Slots</span> ${a.current_usage}/${a.max_slots}</div>
                        <div><span class="text-slate-500 block text-xs">Status</span> <span class="text-green-600">Active</span></div>
                    </div>
                </div>
            `).join('');
            document.getElementById('areasList').innerHTML = areasHtml || '<p class="text-slate-500 italic">No designated areas found.</p>';

            // Operators
            const opsHtml = data.operators.map(o => `
                <div class="p-3 border rounded dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <div class="flex justify-between items-center cursor-pointer" onclick="this.nextElementSibling.classList.toggle('hidden')">
                        <div class="font-medium">${o.operator_name} <span class="text-xs text-slate-500 ml-2">(${o.driver_count} drivers)</span></div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                    </div>
                    <div class="hidden mt-2 pl-4 text-sm text-slate-600 border-l-2 border-slate-200">
                        ${o.drivers.map(d => `<div class="py-1">${d.name} <span class="text-xs text-slate-400">Lic: ${d.license_no}</span></div>`).join('')}
                        ${o.drivers.length === 0 ? '<div class="italic text-slate-400">No drivers enrolled</div>' : ''}
                    </div>
                </div>
            `).join('');
            document.getElementById('operatorsList').innerHTML = opsHtml || '<p class="text-slate-500 italic">No operators assigned.</p>';

            openModal('modalTerminal');
            lucide.createIcons();
        });
}

function openAddArea() {
    document.getElementById('inputTermId').value = currentTerminalId;
    document.getElementById('modalAddArea').classList.remove('hidden');
}

document.getElementById('formAddArea').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('api/module5/save_terminal_area.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('modalAddArea').classList.add('hidden');
                this.reset();
                showTerminal(currentTerminalId); // Refresh details
            } else {
                alert(res.message);
            }
        });
});

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

loadTerminals();
</script>