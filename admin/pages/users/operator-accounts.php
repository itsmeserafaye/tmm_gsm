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
                <div class="p-3 bg-violet-500/10 rounded-2xl">
                    <i data-lucide="id-card" class="w-8 h-8 text-violet-500"></i>
                </div>
                Operator Portal Accounts
            </h1>
            <p class="mt-2 text-slate-500 dark:text-slate-400 font-medium ml-14">Manage operator portal logins (status, reset password, delete).</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="syncOperatorPlates()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center gap-2">
                <i data-lucide="refresh-ccw" class="w-5 h-5"></i>
                Sync Plates
            </button>
            <button type="button" onclick="loadOperatorAccounts(true)" class="bg-violet-600 hover:bg-violet-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center gap-2">
                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                Refresh
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 flex flex-col sm:flex-row gap-4">
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-3 w-5 h-5 text-slate-400"></i>
            <input type="text" id="op-search" placeholder="Search email, name, plate..." class="block w-full pl-10 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 text-sm font-medium focus:ring-2 focus:ring-violet-500">
        </div>
        <select id="op-status-filter" class="rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 pl-4 pr-10 text-sm font-medium focus:ring-2 focus:ring-violet-500">
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
                        <th class="px-6 py-4">Plates</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Created</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="op-table-body" class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                            <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>
                            Loading operator accounts...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="op-pwd-modal" class="fixed inset-0 z-[110] hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeOpPwdModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
                <div class="bg-slate-50/50 dark:bg-slate-800/50 px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-lg font-black text-slate-800 dark:text-white">Temporary Password</h3>
                    <button type="button" onclick="closeOpPwdModal()" class="text-slate-400 hover:text-slate-500 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="px-6 py-6 space-y-4">
                    <div class="text-sm font-semibold text-slate-600 dark:text-slate-300">Copy and send this to the operator. It will be shown only once.</div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4">
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Password</div>
                        <div class="mt-2 flex items-center gap-2">
                            <input id="op-temp-password" readonly class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 py-2.5 px-4 text-sm font-black tracking-wider">
                            <button type="button" id="op-copy-btn" onclick="copyOpTempPassword()" class="bg-violet-600 hover:bg-violet-700 text-white font-bold py-2.5 px-4 rounded-xl shadow-sm transition-all flex items-center gap-2">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                                Copy
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeOpPwdModal()" class="bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all">
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
        const apiUrl = rootUrl + '/admin/api/users/operators.php';
        const tableBody = document.getElementById('op-table-body');
        const searchEl = document.getElementById('op-search');
        const statusEl = document.getElementById('op-status-filter');
        const pwdModal = document.getElementById('op-pwd-modal');
        const pwdInput = document.getElementById('op-temp-password');

        window.syncOperatorPlates = async function() {
            if (!confirm('This will sync all admin-linked vehicles to operator portal users based on matching names. Proceed?')) return;
            
            const btn = document.querySelector('button[onclick="syncOperatorPlates()"]');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Syncing...`;
            if (window.lucide) window.lucide.createIcons();

            try {
                const res = await fetch(rootUrl + '/admin/api/module1/sync_plates.php', {
                    headers: { 'Accept': 'application/json' }
                });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('JSON Parse Error:', text);
                    throw new Error('Invalid server response: ' + text.substring(0, 100));
                }
                
                if (data.ok) {
                    const stats = data.stats;
                    alert(`Sync Complete!\n\nProcessed: ${stats.processed}\nSynced: ${stats.synced}\nSkipped: ${stats.skipped}\nNot Found: ${stats.not_found}\nFailed: ${stats.failed}`);
                    loadOperatorAccounts(true);
                } else {
                    alert('Sync Failed: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error(e);
                alert('Sync Error: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (window.lucide) window.lucide.createIcons();
            }
        };

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
                        Loading operator accounts...
                    </td>
                </tr>
            `;
            try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
        }

        function renderEmpty() {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                        No operator accounts found.
                    </td>
                </tr>
            `;
        }

        function renderRows(users) {
            if (!users || !users.length) {
                renderEmpty();
                return;
            }
            tableBody.innerHTML = users.map(u => {
                const name = u.full_name ? u.full_name : '(no name)';
                const created = u.created_at ? u.created_at : '';
                const plates = u.plates ? u.plates : '';
                const st = u.status ? u.status : '';
                return `
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-black text-slate-800 dark:text-white">${esc(name)}</div>
                            <div class="text-sm text-slate-500 dark:text-slate-400 font-semibold mt-1">${esc(u.email)}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-slate-700 dark:text-slate-200">${esc(plates)}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-black ${badge(st)}">${esc(st)}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-slate-600 dark:text-slate-300">${esc(created)}</div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <select data-action="status" data-id="${u.id}" class="rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2 px-3 text-xs font-bold focus:ring-2 focus:ring-violet-500">
                                    <option value="Active" ${st === 'Active' ? 'selected' : ''}>Active</option>
                                    <option value="Inactive" ${st === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                    <option value="Locked" ${st === 'Locked' ? 'selected' : ''}>Locked</option>
                                </select>
                                <button type="button" data-action="reset" data-id="${u.id}" class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 text-slate-700 dark:text-slate-200 font-bold py-2 px-3 rounded-xl shadow-sm transition-all flex items-center gap-2 border border-slate-200 dark:border-slate-700">
                                    <i data-lucide="key" class="w-4 h-4"></i>
                                    Reset
                                </button>
                                <button type="button" data-action="delete" data-id="${u.id}" class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-2 px-3 rounded-xl shadow-sm transition-all flex items-center gap-2">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
            try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
        }

        async function apiPost(payload) {
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload || {})
            });
            const data = await res.json().catch(() => null);
            if (!data || !data.ok) {
                const msg = (data && (data.message || data.error)) ? String(data.message || data.error) : 'Request failed';
                throw new Error(msg);
            }
            return data;
        }

        async function loadOperatorAccounts(force) {
            if (force) renderLoading();
            const q = String(searchEl.value || '').trim();
            const st = String(statusEl.value || '').trim();
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (st) params.set('status', st);
            const res = await fetch(apiUrl + (params.toString() ? ('?' + params.toString()) : ''), { headers: { 'Accept': 'application/json' } });
            const data = await res.json().catch(() => null);
            if (!data || !data.ok) {
                tableBody.innerHTML = `
                    <tr><td colspan="5" class="px-6 py-12 text-center text-rose-500 font-semibold">Failed to load.</td></tr>
                `;
                return;
            }
            renderRows(data.users || []);
        }

        function scheduleReload() {
            if (debounceId) clearTimeout(debounceId);
            debounceId = setTimeout(() => loadOperatorAccounts(false), 250);
        }

        function openOpPwdModal(pwd) {
            if (!pwdModal || !pwdInput) return;
            pwdInput.value = String(pwd || '');
            pwdModal.classList.remove('hidden');
            try { document.body.classList.add('overflow-hidden'); } catch (e) { }
            try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
        }

        function closeOpPwdModal() {
            if (!pwdModal) return;
            pwdModal.classList.add('hidden');
            try { document.body.classList.remove('overflow-hidden'); } catch (e) { }
        }

        async function copyOpTempPassword() {
            const v = pwdInput ? String(pwdInput.value || '') : '';
            if (!v) return;
            try {
                await navigator.clipboard.writeText(v);
                const btn = document.getElementById('op-copy-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Copied';
                    setTimeout(() => { btn.disabled = false; btn.innerHTML = '<i data-lucide="copy" class="w-4 h-4"></i> Copy'; try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { } }, 900);
                }
            } catch (e) { }
        }

        tableBody.addEventListener('change', async (e) => {
            const el = e.target;
            if (!el || el.getAttribute('data-action') !== 'status') return;
            const id = parseInt(el.getAttribute('data-id') || '0', 10);
            const status = String(el.value || '');
            if (!id || !status) return;
            el.disabled = true;
            try {
                await apiPost({ action: 'set_status', user_id: id, status });
            } catch (err) {
                alert(err.message || 'Failed');
            } finally {
                el.disabled = false;
                await loadOperatorAccounts(false);
            }
        });

        tableBody.addEventListener('click', async (e) => {
            const btn = e.target && e.target.closest ? e.target.closest('button[data-action]') : null;
            if (!btn) return;
            const action = btn.getAttribute('data-action');
            const id = parseInt(btn.getAttribute('data-id') || '0', 10);
            if (!action || !id) return;
            btn.disabled = true;
            try {
                if (action === 'reset') {
                    const data = await apiPost({ action: 'reset_password', user_id: id });
                    openOpPwdModal(data.temporary_password || '');
                } else if (action === 'delete') {
                    const ok = confirm('Delete this operator portal account? This cannot be undone.');
                    if (!ok) return;
                    await apiPost({ action: 'delete', user_id: id });
                }
            } catch (err) {
                alert(err.message || 'Failed');
            } finally {
                btn.disabled = false;
                await loadOperatorAccounts(false);
            }
        });

        searchEl.addEventListener('input', scheduleReload);
        statusEl.addEventListener('change', () => loadOperatorAccounts(true));

        window.loadOperatorAccounts = loadOperatorAccounts;
        window.closeOpPwdModal = closeOpPwdModal;
        window.copyOpTempPassword = copyOpTempPassword;

        renderLoading();
        loadOperatorAccounts(true);
    })();
</script>
