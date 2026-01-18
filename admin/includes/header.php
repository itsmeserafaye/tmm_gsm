<?php
$crumbText = implode(' > ', $breadcrumb ?? ['Dashboard']);
?>
<div class="bg-white/80 backdrop-blur-xl border-b border-slate-200 px-6 py-4 dark:bg-slate-800 dark:border-slate-700/50">
  <div class="flex items-center justify-between">
    <div class="flex items-center space-x-4">
      <button class="p-2 rounded-lg text-slate-500 hover:bg-slate-200 transition-colors duration-200" onclick="toggleSidebar()">
        <i data-lucide="menu" class="w-6 h-6"></i>
      </button>
      <div>
        <div class="hidden md:flex items-center space-x-1">
          <h1 class="text-md font-bold dark:text-white">TRANSPORT & MOBILITY MANAGEMENT</h1>
        </div>
        <div>
          <span class="text-xs text-slate-500 dark:text-slate-400 font-bold"><?php echo htmlspecialchars($crumbText); ?></span>
        </div>
      </div>
    </div>
    <div class="flex items-center space-x-1">
      <button class="relative rounded-xl p-2 text-slate-600 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors cursor-pointer">
        <i data-lucide="bell" class="w-6 h-6"></i>
        <span class="absolute top-0 w-4 h-4 text-white text-xs bg-red-600 rounded-full flex items-center justify-center">1</span>
      </button>
      <button class="ml-2 rounded-xl p-2 text-slate-600 hover:bg-slate-200 dark:text-yellow-400 dark:hover:bg-slate-700 transition-colors cursor-pointer" onclick="toggleTheme()" aria-label="Toggle dark mode">
        <span id="themeState" class="sr-only">light</span>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="5" stroke="currentColor" stroke-width="2" fill="none"/><path stroke="currentColor" stroke-width="2" d="M12 1v2m0 18v2m11-11h-2M3 12H1m16.95 7.07l-1.41-1.41M6.46 6.46L5.05 5.05m13.9 0l-1.41 1.41M6.46 17.54l-1.41 1.41"/></svg>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke="currentColor" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3a7 7 0 109.79 9.79z"/></svg>
      </button>
    </div>
  </div>
</div>
