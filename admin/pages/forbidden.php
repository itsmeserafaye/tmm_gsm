<?php
$msg = isset($tmmDeniedMessage) && is_string($tmmDeniedMessage) && $tmmDeniedMessage !== '' ? $tmmDeniedMessage : 'You do not have access to this page.';
echo '<div class="mx-auto max-w-3xl px-4 py-10">';
echo '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-700">';
echo '<div class="text-lg font-black">Access Denied</div>';
echo '<div class="mt-1 text-sm font-bold">' . htmlspecialchars($msg) . '</div>';
echo '</div>';
echo '</div>';

