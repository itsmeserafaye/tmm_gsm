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

                <div class="md:col-span-2 -mt-2">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-4">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Password Complexity</div>
                            <div class="mt-1 text-xs text-slate-400 font-medium">Applies to commuter and operator registrations.</div>
                        </div>
                        <label class="flex items-center justify-between gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Uppercase (A-Z)</span>
                            <span class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="password_require_upper" value="0">
                                <input type="checkbox" name="password_require_upper" value="1" class="sr-only peer" <?php echo get_setting('password_require_upper', '1') === '1' ? 'checked' : ''; ?>>
                                <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-rose-300 dark:peer-focus:ring-rose-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[3px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-600"></div>
                            </span>
                        </label>
                        <label class="flex items-center justify-between gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Lowercase (a-z)</span>
                            <span class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="password_require_lower" value="0">
                                <input type="checkbox" name="password_require_lower" value="1" class="sr-only peer" <?php echo get_setting('password_require_lower', '1') === '1' ? 'checked' : ''; ?>>
                                <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-rose-300 dark:peer-focus:ring-rose-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[3px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-600"></div>
                            </span>
                        </label>
                        <label class="flex items-center justify-between gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Number (0-9)</span>
                            <span class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="password_require_number" value="0">
                                <input type="checkbox" name="password_require_number" value="1" class="sr-only peer" <?php echo get_setting('password_require_number', '1') === '1' ? 'checked' : ''; ?>>
                                <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-rose-300 dark:peer-focus:ring-rose-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[3px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-600"></div>
                            </span>
                        </label>
                        <label class="flex items-center justify-between gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Symbol (!@#$)</span>
                            <span class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="password_require_symbol" value="0">
                                <input type="checkbox" name="password_require_symbol" value="1" class="sr-only peer" <?php echo get_setting('password_require_symbol', '1') === '1' ? 'checked' : ''; ?>>
                                <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-rose-300 dark:peer-focus:ring-rose-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[3px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-600"></div>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Lockout Duration (Minutes)</label>
                        <input type="number" name="lockout_minutes" min="1" max="240" value="<?php echo htmlspecialchars(get_setting('lockout_minutes', '15')); ?>" 
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-rose-500 transition-all">
                        <p class="mt-2 text-xs text-slate-400 font-medium">How long an account stays locked after repeated failures.</p>
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

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">MFA Trusted Device (Days)</label>
                        <input type="number" name="mfa_trust_days" min="0" max="30" value="<?php echo htmlspecialchars(get_setting('mfa_trust_days', '10')); ?>" 
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-rose-500 transition-all">
                        <p class="mt-2 text-xs text-slate-400 font-medium">0 means OTP required every login (even trusted devices).</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">OTP Expiry (Seconds)</label>
                        <input type="number" name="otp_ttl_seconds" min="60" max="900" value="<?php echo htmlspecialchars(get_setting('otp_ttl_seconds', '120')); ?>" 
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-rose-500 transition-all">
                        <p class="mt-2 text-xs text-slate-400 font-medium">Shorter expiry improves security, but may reduce deliverability.</p>
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
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Warning Countdown (Seconds)</label>
                        <input type="number" name="session_warning_seconds" min="10" max="120" value="<?php echo htmlspecialchars(get_setting('session_warning_seconds', '30')); ?>" 
                            class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-rose-500 transition-all">
                        <p class="mt-2 text-xs text-slate-400 font-medium">Show a countdown toast before logout.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-3">
                <div class="p-2 bg-sky-100 dark:bg-sky-900/30 rounded-xl">
                    <i data-lucide="mail" class="w-5 h-5 text-sky-600 dark:text-sky-400"></i>
                </div>
                <div>
                    <h2 class="text-lg font-black text-slate-800 dark:text-white">Email Delivery (OTP/MFA)</h2>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">SMTP Configuration</p>
                </div>
            </div>
            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">SMTP Host</label>
                    <input type="text" name="smtp_host" value="<?php echo htmlspecialchars(get_setting('smtp_host', '')); ?>" placeholder="smtp.gmail.com"
                        class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-sky-500 transition-all">
                    <p class="mt-2 text-xs text-slate-400 font-medium">If empty, server will use PHP mail() or .env SMTP config.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">SMTP Port</label>
                    <input type="number" name="smtp_port" min="1" max="65535" value="<?php echo htmlspecialchars(get_setting('smtp_port', '587')); ?>"
                        class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-sky-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">SMTP User</label>
                    <input type="text" name="smtp_user" value="<?php echo htmlspecialchars(get_setting('smtp_user', '')); ?>" placeholder="no-reply@example.com"
                        class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-sky-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">SMTP Password</label>
                    <input type="password" name="smtp_pass" value="" placeholder="Leave blank to keep current"
                        class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-sky-500 transition-all">
                    <p class="mt-2 text-xs text-slate-400 font-medium">For security, current password is never shown.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">SMTP Security</label>
                    <select name="smtp_secure" class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-sky-500 transition-all">
                        <?php $sec = strtolower((string)get_setting('smtp_secure', 'tls')); ?>
                        <option value="tls" <?php echo $sec === 'tls' ? 'selected' : ''; ?>>TLS (STARTTLS)</option>
                        <option value="ssl" <?php echo $sec === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    </select>
                </div>
                <div class="flex items-end justify-end gap-3">
                    <button type="button" id="btnTestEmail" class="px-4 py-2.5 rounded-md bg-sky-700 hover:bg-sky-800 text-white font-semibold">Send Test Email</button>
                </div>
                <div class="md:col-span-2">
                    <div id="emailTestMsg" class="text-xs font-bold text-slate-500 dark:text-slate-400 min-h-[1.5em]"></div>
                </div>
            </div>
        </div>

        <div class="tmm-sticky-actionbar sticky bottom-4 z-20">
            <div class="bg-slate-900/90 dark:bg-slate-800/90 backdrop-blur-md text-white px-6 py-4 rounded-2xl shadow-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border border-white/10">
                <div class="flex items-center gap-3">
                    <i data-lucide="shield-alert" class="w-5 h-5 text-rose-400"></i>
                    <span class="text-sm font-medium">Strict security rules applied instantly.</span>
                </div>
                <button type="submit" id="btn-save-security" class="w-full sm:w-auto bg-rose-500 hover:bg-rose-400 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg shadow-rose-500/30 transition-all flex items-center justify-center gap-2">
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
    const btnTestEmail = document.getElementById('btnTestEmail');
    const emailTestMsg = document.getElementById('emailTestMsg');

    function setEmailTestMsg(msg, ok) {
        if (!emailTestMsg) return;
        emailTestMsg.textContent = msg || '';
        emailTestMsg.className = 'text-xs font-bold min-h-[1.5em] ' + (ok ? 'text-emerald-600' : 'text-rose-600');
    }

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

    if (btnTestEmail) {
        btnTestEmail.addEventListener('click', async () => {
            btnTestEmail.disabled = true;
            btnTestEmail.textContent = 'Sending...';
            setEmailTestMsg('Sending test email...', true);
            try {
                const res = await fetch((window.TMM_ROOT_URL || '') + '/admin/api/settings/test_email.php', { method: 'POST' });
                const data = await res.json();
                if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'failed');
                setEmailTestMsg('Test email sent. Check your inbox/spam.', true);
            } catch (err) {
                setEmailTestMsg((err && err.message) ? String(err.message) : 'Failed to send test email.', false);
            } finally {
                btnTestEmail.disabled = false;
                btnTestEmail.textContent = 'Send Test Email';
            }
        });
    }
});
</script>
