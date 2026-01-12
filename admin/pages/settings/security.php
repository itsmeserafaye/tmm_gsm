<?php
$scanner = getenv('TMM_AV_SCANNER') ?: '';
$scannerEnabled = $scanner !== '';
?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 rounded-lg">
  <h1 class="text-2xl font-bold mb-4 text-slate-900 dark:text-white">Security Settings</h1>
  <p class="text-sm text-slate-600 dark:text-slate-300 mb-6">Security and privacy settings for the LGU transport management system.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="border rounded-lg border-slate-200 dark:border-slate-700 p-4 bg-slate-50/60 dark:bg-slate-800/60">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">File Upload Virus Scanning</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Checks uploaded documents and evidence files using the configured scanner.</p>
        </div>
        <?php if ($scannerEnabled): ?>
          <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
            Enabled
          </span>
        <?php else: ?>
          <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
            Disabled
          </span>
        <?php endif; ?>
      </div>

      <dl class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
        <div class="flex items-start justify-between gap-3">
          <dt class="font-medium text-slate-700 dark:text-slate-200">Scanner command</dt>
          <dd class="text-right max-w-xs">
            <?php if ($scannerEnabled): ?>
              <span class="inline-block px-2 py-1 rounded bg-slate-100 dark:bg-slate-900/60 text-[11px] font-mono break-all">
                <?php echo htmlspecialchars($scanner); ?>
              </span>
            <?php else: ?>
              <span class="text-slate-400">Not configured (uses default behavior)</span>
            <?php endif; ?>
          </dd>
        </div>
        <div class="flex items-start justify-between gap-3">
          <dt class="font-medium text-slate-700 dark:text-slate-200">Environment variable</dt>
          <dd class="text-right max-w-xs">
            <span class="inline-block px-2 py-1 rounded bg-slate-100 dark:bg-slate-900/60 text-[11px] font-mono">
              TMM_AV_SCANNER
            </span>
          </dd>
        </div>
      </dl>

      <p class="mt-4 text-[11px] text-slate-500 dark:text-slate-400">
        To enable scanning, configure the TMM_AV_SCANNER environment variable on the server
        to point to a command-line antivirus scanner that returns exit code 0 for clean files.
      </p>
    </div>
  </div>
</div>
