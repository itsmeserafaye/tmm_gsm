<?php
if (!function_exists('tmm_render_export_toolbar')) {
  function tmm_render_export_toolbar(array $items, array $options = []): void
  {
    $label = isset($options['label']) ? (string)$options['label'] : 'Export';
    $mbClass = isset($options['mb']) ? (string)$options['mb'] : 'mb-4';
    $wrapperClass = 'tmm-export-toolbar ' . $mbClass;
    $barClass = 'rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/25 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3';
    $labelClass = 'text-xs font-black uppercase tracking-widest text-slate-400 dark:text-slate-500';
    $groupClass = 'inline-flex overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-900/30 shadow-sm';
    $buttonBaseClass = 'inline-flex items-center gap-2 px-4 py-2 text-xs font-black text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800 transition-colors';
    $separatorClass = 'border-l border-slate-200 dark:border-slate-700';
    echo '<div class="' . htmlspecialchars($wrapperClass, ENT_QUOTES) . '">';
    echo '<div class="' . htmlspecialchars($barClass, ENT_QUOTES) . '">';
    echo '<div class="' . htmlspecialchars($labelClass, ENT_QUOTES) . '">' . htmlspecialchars($label, ENT_QUOTES) . '</div>';
    echo '<div class="' . htmlspecialchars($groupClass, ENT_QUOTES) . '">';
    $idx = 0;
    foreach ($items as $item) {
      if (!is_array($item)) continue;
      $tag = isset($item['tag']) ? strtolower((string)$item['tag']) : 'a';
      $href = isset($item['href']) ? (string)$item['href'] : '#';
      $text = isset($item['label']) ? (string)$item['label'] : '';
      $icon = isset($item['icon']) ? (string)$item['icon'] : '';
      $target = isset($item['target']) ? (string)$item['target'] : '';
      $attrs = isset($item['attrs']) && is_array($item['attrs']) ? $item['attrs'] : [];
      $classes = $buttonBaseClass . ($idx > 0 ? (' ' . $separatorClass) : '');
      $attrs['class'] = $classes;
      if ($tag === 'a') {
        $attrs['href'] = $href;
        if ($target !== '') $attrs['target'] = $target;
        if (($attrs['target'] ?? '') === '_blank') $attrs['rel'] = 'noopener';
      } else {
        if (!isset($attrs['type'])) $attrs['type'] = 'button';
      }
      $attrStr = '';
      foreach ($attrs as $k => $v) {
        $k = (string)$k;
        if ($k === '') continue;
        $attrStr .= ' ' . htmlspecialchars($k, ENT_QUOTES) . '="' . htmlspecialchars((string)$v, ENT_QUOTES) . '"';
      }
      echo '<' . htmlspecialchars($tag, ENT_QUOTES) . $attrStr . '>';
      if ($icon !== '') {
        echo '<i data-lucide="' . htmlspecialchars($icon, ENT_QUOTES) . '" class="w-4 h-4"></i>';
      }
      echo htmlspecialchars($text, ENT_QUOTES);
      echo '</' . htmlspecialchars($tag, ENT_QUOTES) . '>';
      $idx++;
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
  }
}
