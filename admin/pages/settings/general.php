<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!has_permission('settings.manage')) {
    echo '<div class="mx-auto max-w-3xl px-4 py-10">';
    echo '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-700">';
    echo '<div class="text-lg font-black">Access Denied</div>';
    echo '<div class="mt-1 text-sm font-bold">You do not have permission to manage settings.</div>';
    echo '</div>';
    echo '</div>';
    return;
}
$db = db();

// Fetch all settings
$settings = [];
$res = $db->query("SELECT setting_key, setting_value FROM app_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Helper to get setting with default
function get_setting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}
?>

<div class="mx-auto max-w-5xl px-4 py-8 space-y-8">
    <!-- Header -->
    <div>
        <h1 class="text-3xl font-black text-slate-800 dark:text-white flex items-center gap-3">
            <div class="p-3 bg-indigo-500/10 rounded-2xl">
                <i data-lucide="settings" class="w-8 h-8 text-indigo-500"></i>
            </div>
            General Settings
        </h1>
        <p class="mt-2 text-slate-500 dark:text-slate-400 font-medium ml-14">Manage system identity, external integrations, and core configurations.</p>
    </div>

    <form id="general-settings-form" class="space-y-8">
        <!-- System Identity -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-xl">
                    <i data-lucide="monitor" class="w-5 h-5 text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                    <h2 class="text-lg font-black text-slate-800 dark:text-white">System Identity</h2>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Basic Information</p>
                </div>
            </div>
            
            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">System Name</label>
                        <input type="text" name="system_name" value="<?php echo htmlspecialchars(get_setting('system_name', 'TMM System')); ?>" 
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all placeholder:font-medium">
                        <p class="mt-2 text-xs text-slate-400 font-medium">Displayed in browser titles and emails.</p>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Support Email</label>
                        <input type="email" name="system_email" value="<?php echo htmlspecialchars(get_setting('system_email', 'admin@tmm.gov.ph')); ?>" 
                            pattern="^(?!.*\.\.)[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$"
                            placeholder="admin@tmm.gov.ph"
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all placeholder:font-medium">
                        <p class="mt-2 text-xs text-slate-400 font-medium">Used for system notifications.</p>
                    </div>
                </div>

                <div class="md:col-span-2 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block text-sm font-bold text-slate-800 dark:text-white">Maintenance Mode</label>
                            <p class="text-xs text-slate-500 mt-1">Prevent users from accessing the system during updates.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="maintenance_mode" value="0">
                            <input type="checkbox" name="maintenance_mode" value="1" class="sr-only peer" <?php echo get_setting('maintenance_mode') === '1' ? 'checked' : ''; ?>>
                            <div class="w-14 h-7 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- External Integrations -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-3">
                <div class="p-2 bg-sky-100 dark:bg-sky-900/30 rounded-xl">
                    <i data-lucide="cloud-sun" class="w-5 h-5 text-sky-600 dark:text-sky-400"></i>
                </div>
                <div>
                    <h2 class="text-lg font-black text-slate-800 dark:text-white">External Data</h2>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Weather & Events API</p>
                </div>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Weather -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-4 h-4 text-slate-400"></i> Location Coordinates
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Latitude</label>
                            <input type="text" name="weather_lat" value="<?php echo htmlspecialchars(get_setting('weather_lat')); ?>" 
                                class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Longitude</label>
                            <input type="text" name="weather_lon" value="<?php echo htmlspecialchars(get_setting('weather_lon')); ?>" 
                                class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Location Label</label>
                        <input type="text" name="weather_label" value="<?php echo htmlspecialchars(get_setting('weather_label')); ?>" 
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all">
                    </div>
                </div>

                <!-- Events -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i data-lucide="calendar" class="w-4 h-4 text-slate-400"></i> Event Localization
                    </h3>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Country Code (ISO)</label>
                        <input type="text" name="events_country" value="<?php echo htmlspecialchars(get_setting('events_country')); ?>" 
                            class="block w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Target City</label>
                        <input type="text" name="events_city" value="<?php echo htmlspecialchars(get_setting('events_city')); ?>" 
                            class="block w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-3">
                <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl">
                    <i data-lucide="shield-check" class="w-5 h-5 text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <div>
                    <h2 class="text-lg font-black text-slate-800 dark:text-white">reCAPTCHA</h2>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Anti-bot protection</p>
                </div>
            </div>
            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Site Key</label>
                    <input type="text" name="recaptcha_site_key" value="<?php echo htmlspecialchars(get_setting('recaptcha_site_key')); ?>"
                        class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 transition-all"
                        placeholder="Site key (public)">
                    <p class="mt-2 text-xs text-slate-400 font-medium">Leave blank to disable reCAPTCHA in the UI and API enforcement.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Secret Key</label>
                    <input type="password" name="recaptcha_secret_key" value=""
                        autocomplete="new-password"
                        class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 transition-all"
                        placeholder="Secret key (server-only)">
                    <p class="mt-2 text-xs text-slate-400 font-medium">For security, this is never shown. Leave blank to keep the current secret.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-3">
                    <div class="p-2 bg-violet-100 dark:bg-violet-900/30 rounded-xl">
                        <i data-lucide="sliders" class="w-5 h-5 text-violet-600 dark:text-violet-400"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-slate-800 dark:text-white">AI Forecast Tuning</h2>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Weather • Events • Traffic impact weights</p>
                    </div>
                </div>

                <div class="p-8 grid grid-cols-1 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Weather Weight</label>
                        <input type="number" step="0.01" min="-0.50" max="0.50" name="ai_weather_weight" value="<?php echo htmlspecialchars(get_setting('ai_weather_weight', '0.12')); ?>"
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-violet-500 transition-all">
                        <p class="mt-2 text-xs text-slate-400 font-medium">Positive increases demand during bad weather; negative decreases it.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Events Weight</label>
                        <input type="number" step="0.01" min="-0.50" max="0.50" name="ai_event_weight" value="<?php echo htmlspecialchars(get_setting('ai_event_weight', '0.10')); ?>"
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-violet-500 transition-all">
                        <p class="mt-2 text-xs text-slate-400 font-medium">Applies to holidays and RSS events within the forecast window.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Traffic Weight</label>
                        <input type="number" step="0.01" min="0.00" max="2.00" name="ai_traffic_weight" value="<?php echo htmlspecialchars(get_setting('ai_traffic_weight', '1.00')); ?>"
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-violet-500 transition-all">
                        <p class="mt-2 text-xs text-slate-400 font-medium">Scales traffic congestion impact from TomTom (0 disables).</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl">
                            <i data-lucide="database" class="w-5 h-5 text-emerald-600 dark:text-emerald-400"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-black text-slate-800 dark:text-white">Data Inputs</h2>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Record demand observations</p>
                        </div>
                    </div>
                    <a href="?page=module5/submodule3"
                        class="p-2 rounded-md bg-slate-50 dark:bg-slate-700 text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-all"
                        title="Go to Parking Data">
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                    </a>
                </div>

                <div class="p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Area Type</label>
                            <select id="demand-area-type"
                                class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 transition-all">
                                <option value="terminal">Terminal</option>
                                <option value="route">Route</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Location</label>
                            <select id="demand-area-ref"
                                class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 transition-all"></select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Hour</label>
                            <input id="demand-observed-at" type="datetime-local"
                                class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Count</label>
                            <input id="demand-count" type="number" min="0" placeholder="0"
                                class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 transition-all">
                        </div>
                    </div>

                    <button id="demand-log-save" type="button"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-6 rounded-md shadow-sm transition-all flex items-center justify-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Save Observation
                    </button>
                    <div id="demand-log-result" class="text-center text-xs font-bold min-h-[1.5em]"></div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="sticky bottom-4 z-50">
            <div class="bg-slate-900/90 dark:bg-slate-800/90 backdrop-blur-md text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center justify-between border border-white/10">
                <div class="flex items-center gap-3">
                    <i data-lucide="info" class="w-5 h-5 text-indigo-400"></i>
                    <span class="text-sm font-medium">Changes take effect immediately.</span>
                </div>
                <button type="submit" id="btn-save-general" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-md shadow-sm transition-all flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if(window.lucide) window.lucide.createIcons();

    const form = document.getElementById('general-settings-form');
    const btn = document.getElementById('btn-save-general');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // UI Feedback
        const originalBtnContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...';
        if(window.lucide) window.lucide.createIcons();

        try {
            const formData = new FormData(form);
            const res = await fetch((window.TMM_ROOT_URL || '') + '/admin/api/settings/update.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.ok) {
                // Success Toast/State
                btn.classList.remove('bg-indigo-500', 'hover:bg-indigo-400');
                btn.classList.add('bg-emerald-500', 'hover:bg-emerald-400');
                btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Saved!';
                if(window.lucide) window.lucide.createIcons();
                
                setTimeout(() => {
                    btn.classList.remove('bg-emerald-500', 'hover:bg-emerald-400');
                    btn.classList.add('bg-indigo-500', 'hover:bg-indigo-400');
                    btn.innerHTML = originalBtnContent;
                    btn.disabled = false;
                    if(window.lucide) window.lucide.createIcons();
                }, 2000);
            } else {
                throw new Error(data.error || 'Failed to save');
            }
        } catch (err) {
            console.error(err);
            btn.classList.remove('bg-indigo-500', 'hover:bg-indigo-400');
            btn.classList.add('bg-rose-500', 'hover:bg-rose-400');
            btn.innerHTML = '<i data-lucide="alert-triangle" class="w-4 h-4"></i> Error';
            if(window.lucide) window.lucide.createIcons();
            
            setTimeout(() => {
                btn.classList.remove('bg-rose-500', 'hover:bg-rose-400');
                btn.classList.add('bg-indigo-500', 'hover:bg-indigo-400');
                btn.innerHTML = originalBtnContent;
                btn.disabled = false;
                if(window.lucide) window.lucide.createIcons();
            }, 3000);
        }
    });

    const demandType = document.getElementById('demand-area-type');
    const demandRef = document.getElementById('demand-area-ref');
    const demandAt = document.getElementById('demand-observed-at');
    const demandCount = document.getElementById('demand-count');
    const demandSave = document.getElementById('demand-log-save');
    const demandResult = document.getElementById('demand-log-result');

    let demandAreas = { terminal: [], route: [] };

    const setDemandResult = (text, kind) => {
        if (!demandResult) return;
        demandResult.textContent = String(text || '');
        if (kind === 'ok') demandResult.className = 'text-center text-xs font-bold min-h-[1.5em] text-emerald-600';
        else if (kind === 'warn') demandResult.className = 'text-center text-xs font-bold min-h-[1.5em] text-amber-600';
        else if (kind === 'err') demandResult.className = 'text-center text-xs font-bold min-h-[1.5em] text-rose-600';
        else demandResult.className = 'text-center text-xs font-bold min-h-[1.5em] text-slate-500';
    };

    const populateDemandAreas = (type) => {
        if (!demandRef) return;
        const list = (demandAreas && demandAreas[type]) ? demandAreas[type] : [];
        demandRef.innerHTML = '';
        list.forEach((a) => {
            if (!a) return;
            const opt = document.createElement('option');
            opt.value = String(a.ref || '');
            opt.textContent = String(a.label || a.name || a.ref || '');
            demandRef.appendChild(opt);
        });
    };

    const initDemandDateTime = () => {
        if (!demandAt) return;
        const d = new Date();
        d.setMinutes(0, 0, 0);
        const pad = (n) => String(n).padStart(2, '0');
        demandAt.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':00';
    };

    const loadDemandAreaLists = async () => {
        if (!demandRef) return;
        setDemandResult('Loading locations...', 'info');
        try {
            const res = await fetch((window.TMM_ROOT_URL || '') + '/admin/api/analytics/demand_forecast.php?area_type=terminal&hours=24&include_traffic=0', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (!data || !data.ok || !data.area_lists) {
                setDemandResult('Failed to load locations.', 'err');
                return;
            }
            demandAreas = data.area_lists;
            populateDemandAreas(demandType ? demandType.value : 'terminal');
            setDemandResult('', 'info');
        } catch (e) {
            setDemandResult('Failed to load locations.', 'err');
        }
    };

    if (demandType) {
        demandType.addEventListener('change', () => {
            populateDemandAreas(demandType.value);
        });
    }

    if (demandSave) {
        demandSave.addEventListener('click', async () => {
            const type = demandType ? String(demandType.value || 'terminal') : 'terminal';
            const ref = demandRef ? String(demandRef.value || '') : '';
            const at = demandAt ? String(demandAt.value || '') : '';
            const count = demandCount ? String(demandCount.value || '') : '';
            if (!ref) { setDemandResult('Select a location.', 'warn'); return; }
            if (!at) { setDemandResult('Select an hour.', 'warn'); return; }
            if (count.trim() === '') { setDemandResult('Enter a count.', 'warn'); return; }

            demandSave.disabled = true;
            setDemandResult('Saving...', 'info');
            try {
                const fd = new FormData();
                fd.append('area_type', type);
                fd.append('area_ref', ref);
                fd.append('observed_at', at);
                fd.append('demand_count', count);
                const res = await fetch((window.TMM_ROOT_URL || '') + '/admin/api/analytics/demand_observation_upsert.php', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: fd
                });
                const data = await res.json();
                if (data && data.ok) {
                    setDemandResult('OBSERVATION SAVED!', 'ok');
                    setTimeout(() => setDemandResult('', 'info'), 2500);
                } else {
                    setDemandResult((data && (data.error || data.message)) ? (data.error || data.message) : 'FAILED TO SAVE', 'err');
                }
            } catch (e) {
                setDemandResult('NETWORK ERROR', 'err');
            } finally {
                demandSave.disabled = false;
            }
        });
    }

    initDemandDateTime();
    loadDemandAreaLists();
});
</script>
