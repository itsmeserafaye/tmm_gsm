<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!has_permission('settings.manage')) {
    echo '<div class="mx-auto max-w-3xl px-4 py-10">';
    echo '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-700">';
    echo '<div class="text-lg font-black">Access Denied</div>';
    echo '<div class="mt-1 text-sm font-bold">You do not have permission to manage security settings.</div>';
    echo '</div>';
    echo '</div>';
    return;
}
$db = db();

$settings = [];
$res = $db->query("SELECT setting_key, setting_value FROM app_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

function get_setting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}
?>

<div class="mx-auto max-w-5xl px-4 py-8 space-y-8">
    <div>
        <h1 class="text-3xl font-black text-slate-800 dark:text-white flex items-center gap-3">
            <div class="p-3 bg-rose-500/10 rounded-2xl">
                <i data-lucide="shield-check" class="w-8 h-8 text-rose-500"></i>
            </div>
            Security Settings
        </h1>
        <p class="mt-2 text-slate-500 dark:text-slate-400 font-medium ml-14">Configure authentication policies, session limits, and access controls.</p>
    </div>

    <form id="security-settings-form" class="space-y-8">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-3">
                <div class="p-2 bg-rose-100 dark:bg-rose-900/30 rounded-xl">
                    <i data-lucide="lock" class="w-5 h-5 text-rose-600 dark:text-rose-400"></i>
                </div>
                <div>
                    <h2 class="text-lg font-black text-slate-800 dark:text-white">Authentication Policy</h2>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Password & Access Rules</p>
                </div>
            </div>
            
            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Minimum Password Length</label>
                        <input type="number" name="password_min_length" min="6" max="32" value="<?php echo htmlspecialchars(get_setting('password_min_length', '8')); ?>" 
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-rose-500 transition-all">
                        <p class="mt-2 text-xs text-slate-400 font-medium">Recommended: 12 characters or more.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Max Login Attempts</label>
                        <input type="number" name="max_login_attempts" min="3" max="10" value="<?php echo htmlspecialchars(get_setting('max_login_attempts', '5')); ?>" 
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-rose-500 transition-all">
                        <p class="mt-2 text-xs text-slate-400 font-medium">Account lockout threshold.</p>
                    </div>
                </div>

                <div class="md:col-span-2 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block text-sm font-bold text-slate-800 dark:text-white">Require Two-Factor Authentication (MFA)</label>
                            <p class="text-xs text-slate-500 mt-1">Force all administrators to use MFA.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="require_mfa" value="0">
                            <input type="checkbox" name="require_mfa" value="1" class="sr-only peer" <?php echo get_setting('require_mfa') === '1' ? 'checked' : ''; ?>>
                            <div class="w-14 h-7 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-rose-300 dark:peer-focus:ring-rose-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-gray-600 peer-checked:bg-rose-600"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-3">
                <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-xl">
                    <i data-lucide="clock" class="w-5 h-5 text-amber-600 dark:text-amber-400"></i>
                </div>
                <div>
                    <h2 class="text-lg font-black text-slate-800 dark:text-white">Session Management</h2>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Timeouts & Cookies</p>
                </div>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Session Timeout (Minutes)</label>
                        <input type="number" name="session_timeout" min="5" max="1440" value="<?php echo htmlspecialchars(get_setting('session_timeout', '30')); ?>" 
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-rose-500 transition-all">
                        <p class="mt-2 text-xs text-slate-400 font-medium">Auto-logout after inactivity.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-4 z-50">
            <div class="bg-slate-900/90 dark:bg-slate-800/90 backdrop-blur-md text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center justify-between border border-white/10">
                <div class="flex items-center gap-3">
                    <i data-lucide="shield-alert" class="w-5 h-5 text-rose-400"></i>
                    <span class="text-sm font-medium">Strict security rules applied instantly.</span>
                </div>
                <button type="submit" id="btn-save-security" class="bg-rose-500 hover:bg-rose-400 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg shadow-rose-500/30 transition-all flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Save Policies
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if(window.lucide) window.lucide.createIcons();

    const form = document.getElementById('security-settings-form');
    const btn = document.getElementById('btn-save-security');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const originalBtnContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Applying...';
        if(window.lucide) window.lucide.createIcons();

        try {
            const formData = new FormData(form);
            const res = await fetch((window.TMM_ROOT_URL || '') + '/admin/api/settings/update.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.ok) {
                btn.classList.remove('bg-rose-500', 'hover:bg-rose-400');
                btn.classList.add('bg-emerald-500', 'hover:bg-emerald-400');
                btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Policies Updated!';
                if(window.lucide) window.lucide.createIcons();
                
                setTimeout(() => {
                    btn.classList.remove('bg-emerald-500', 'hover:bg-emerald-400');
                    btn.classList.add('bg-rose-500', 'hover:bg-rose-400');
                    btn.innerHTML = originalBtnContent;
                    btn.disabled = false;
                    if(window.lucide) window.lucide.createIcons();
                }, 2000);
            } else {
                throw new Error(data.error || 'Failed to save');
            }
        } catch (err) {
            console.error(err);
            btn.classList.remove('bg-rose-500', 'hover:bg-rose-400');
            btn.classList.add('bg-amber-500', 'hover:bg-amber-400');
            btn.innerHTML = '<i data-lucide="alert-triangle" class="w-4 h-4"></i> Error';
            if(window.lucide) window.lucide.createIcons();
            
            setTimeout(() => {
                btn.classList.remove('bg-amber-500', 'hover:bg-amber-400');
                btn.classList.add('bg-rose-500', 'hover:bg-rose-400');
                btn.innerHTML = originalBtnContent;
                btn.disabled = false;
                if(window.lucide) window.lucide.createIcons();
            }, 3000);
        }
    });
});
</script>

