<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['SuperAdmin']);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 py-8 space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white flex items-center gap-3">
                <div class="p-3 bg-teal-500/10 rounded-2xl">
                    <i data-lucide="users" class="w-8 h-8 text-teal-500"></i>
                </div>
                Commuter Accounts
            </h1>
            <p class="mt-2 text-slate-500 dark:text-slate-400 font-medium ml-14">Manage public commuter accounts (status, reset password, delete).</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="loadCommuterAccounts(true)" class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center gap-2">
                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                Refresh
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 flex flex-col sm:flex-row gap-4">
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-3 w-5 h-5 text-slate-400"></i>
            <input type="text" id="com-search" placeholder="Search email, name, mobile..." class="block w-full pl-10 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 text-sm font-medium focus:ring-2 focus:ring-teal-500">
        </div>
        <select id="com-status-filter" class="rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 pl-4 pr-10 text-sm font-medium focus:ring-2 focus:ring-teal-500">
            <option value="">All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Locked">Locked</option>
        </select>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase tracking-wider text-slate-500 font-bold">
                        <th class="px-6 py-4">Account</th>
                        <th class="px-6 py-4">Contact Info</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Created</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="com-table-body" class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                            <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>
                            Loading commuter accounts...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="com-pwd-modal" class="fixed inset-0 z-[110] hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeComPwdModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
                <div class="bg-slate-50/50 dark:bg-slate-800/50 px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-lg font-black text-slate-800 dark:text-white">Temporary Password</h3>
                    <button type="button" onclick="closeComPwdModal()" class="text-slate-400 hover:text-slate-500 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="px-6 py-6 space-y-4">
                    <div class="text-sm font-semibold text-slate-600 dark:text-slate-300">Copy and send this to the commuter. It will be shown only once.</div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4">
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Password</div>
                        <div class="mt-2 flex items-center gap-2">
                            <input id="com-temp-password" readonly class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 py-2.5 px-4 text-sm font-black tracking-wider">
                            <button type="button" id="com-copy-btn" onclick="copyComTempPassword()" class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-4 rounded-xl shadow-sm transition-all flex items-center gap-2">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                                Copy
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeComPwdModal()" class="bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const rootUrl = <?php echo json_encode($rootUrl, JSON_UNESCAPED_SLASHES); ?>;
        const apiUrl = rootUrl + '/admin/api/users/commuters.php';
        const tableBody = document.getElementById('com-table-body');
        const searchEl = document.getElementById('com-search');
        const statusEl = document.getElementById('com-status-filter');
        const pwdModal = document.getElementById('com-pwd-modal');
        const pwdInput = document.getElementById('com-temp-password');

        let debounceId = null;

        function esc(s) {
            const div = document.createElement('div');
            div.textContent = String(s || '');
            return div.innerHTML;
        }

        function badge(status) {
            const st = String(status || '');
            if (st === 'Active') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
            if (st === 'Locked') return 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300';
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        }

        function renderLoading() {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                        <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>
                        Loading commuter accounts...
                    </td>
                </tr>
            `;
            try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
        }

        function renderEmpty() {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                        No commuter accounts found.
                    </td>
                </tr>
            `;
        }

        async function loadCommuterAccounts(refresh = false) {
            if (refresh) renderLoading();
            
            const q = searchEl.value;
            const status = statusEl.value;
            const url = new URL(apiUrl, window.location.href);
            url.searchParams.append('q', q);
            url.searchParams.append('status', status);
            url.searchParams.append('t', Date.now());

            try {
                const res = await fetch(url);
                const data = await res.json();
                if (data.ok && Array.isArray(data.users)) {
                    if (data.users.length === 0) {
                        renderEmpty();
                    } else {
                        tableBody.innerHTML = data.users.map(u => `
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-full bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center text-teal-700 dark:text-teal-300 font-bold">
                                            ${esc(u.name.charAt(0))}
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white">${esc(u.name)}</div>
                                            <div class="text-xs text-slate-500">${esc(u.email)}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-slate-700 dark:text-slate-300">${esc(u.mobile)}</div>
                                    <div class="text-xs text-slate-500">${esc(u.barangay)}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${badge(u.status)}">
                                        ${esc(u.status)}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-slate-500">${u.created_at ? new Date(u.created_at).toLocaleDateString() : '-'}</div>
                                    <div class="text-xs text-slate-400">${u.last_login_at ? 'Last login: ' + new Date(u.last_login_at).toLocaleDateString() : 'Never logged in'}</div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        ${u.status === 'Active' ? `
                                            <button onclick="setStatus(${u.id}, 'Inactive')" class="p-2 hover:bg-amber-100 text-amber-600 rounded-lg transition-colors" title="Deactivate">
                                                <i data-lucide="ban" class="w-4 h-4"></i>
                                            </button>
                                        ` : `
                                            <button onclick="setStatus(${u.id}, 'Active')" class="p-2 hover:bg-emerald-100 text-emerald-600 rounded-lg transition-colors" title="Activate">
                                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                            </button>
                                        `}
                                        <button onclick="resetPassword(${u.id})" class="p-2 hover:bg-indigo-100 text-indigo-600 rounded-lg transition-colors" title="Reset Password">
                                            <i data-lucide="key" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="deleteAccount(${u.id})" class="p-2 hover:bg-rose-100 text-rose-600 rounded-lg transition-colors" title="Delete Account">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('');
                        try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
                    }
                } else {
                    renderEmpty();
                }
            } catch (e) {
                console.error(e);
                tableBody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-rose-500 font-bold">Failed to load data.</td></tr>`;
            }
        }

        window.loadCommuterAccounts = loadCommuterAccounts;

        window.setStatus = async (id, status) => {
            if (!confirm(`Set status to ${status}?`)) return;
            try {
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'set_status', user_id: id, status })
                });
                const data = await res.json();
                if (data.ok) {
                    loadCommuterAccounts();
                } else {
                    alert('Error: ' + (data.error || 'Failed'));
                }
            } catch (e) {
                alert('Connection error');
            }
        };

        window.resetPassword = async (id) => {
            if (!confirm('Generate a temporary password for this user?')) return;
            try {
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset_password', user_id: id })
                });
                const data = await res.json();
                if (data.ok) {
                    pwdInput.value = data.temporary_password;
                    pwdModal.classList.remove('hidden');
                } else {
                    alert('Error: ' + (data.error || 'Failed'));
                }
            } catch (e) {
                alert('Connection error');
            }
        };

        window.deleteAccount = async (id) => {
            if (!confirm('Are you sure you want to DELETE this account? This cannot be undone.')) return;
            try {
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', user_id: id })
                });
                const data = await res.json();
                if (data.ok) {
                    loadCommuterAccounts();
                } else {
                    alert('Error: ' + (data.error || 'Failed'));
                }
            } catch (e) {
                alert('Connection error');
            }
        };

        window.closeComPwdModal = () => {
            pwdModal.classList.add('hidden');
            pwdInput.value = '';
        };

        window.copyComTempPassword = () => {
            pwdInput.select();
            document.execCommand('copy');
            const btn = document.getElementById('com-copy-btn');
            const orig = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Copied';
            setTimeout(() => btn.innerHTML = orig, 2000);
            try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
        };

        searchEl.addEventListener('input', () => {
            clearTimeout(debounceId);
            debounceId = setTimeout(() => loadCommuterAccounts(), 300);
        });

        statusEl.addEventListener('change', () => loadCommuterAccounts());

        // Init
        loadCommuterAccounts(true);
    })();
</script>
