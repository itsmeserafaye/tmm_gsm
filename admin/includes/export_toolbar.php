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
      if (isset($item['print_url']) && !isset($item['attrs']['data-print-url'])) {
        if (!isset($item['attrs'])) $item['attrs'] = [];
        $item['attrs']['data-print-url'] = (string)$item['print_url'];
      }
      $attrs = isset($item['attrs']) && is_array($item['attrs']) ? $item['attrs'] : [];
      $classes = $buttonBaseClass . ($idx > 0 ? (' ' . $separatorClass) : '');
      $attrs['class'] = $classes;
      if ($tag === 'a') {
        // If this is a print link, keep href as '#' to avoid navigation
        if (isset($attrs['data-print-url'])) $href = '#';
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
    // Bind print handlers once globally
    echo '<script>(function(){try{if(window.__tmmPrintBound)return;window.__tmmPrintBound=1;function qClosest(n,sel){try{return n.closest? n.closest(sel):null;}catch(e){for(var x=n;x&&x.nodeType===1;x=x.parentElement){if(x.matches && x.matches(sel))return x;}return null;}}document.addEventListener(\"click\",function(e){var t=e.target;if(!t)return;var el=qClosest(t,\"[data-print-url]\"); if(!el)return; e.preventDefault(); var url=el.getAttribute(\"data-print-url\"); if(!url)return; var iframe=document.getElementById(\"__tmmPrintFrame\"); if(!iframe){iframe=document.createElement(\"iframe\"); iframe.style.position=\"fixed\"; iframe.style.right=\"0\"; iframe.style.bottom=\"0\"; iframe.style.width=\"0\"; iframe.style.height=\"0\"; iframe.style.border=\"0\"; iframe.style.visibility=\"hidden\"; iframe.setAttribute(\"aria-hidden\",\"true\"); iframe.id=\"__tmmPrintFrame\"; document.body.appendChild(iframe);} var loaded=false; var failTimer=setTimeout(function(){ if(loaded) return; try{var w=window.open(url,\"tmm_print\",\"noopener,noreferrer,width=900,height=700\"); if(w){ var intv=setInterval(function(){ try{ if(w.closed){clearInterval(intv);} }catch(e){} },500);} }catch(e){ window.open(url,\"_blank\"); } }, 1200); iframe.onload=function(){ loaded=true; clearTimeout(failTimer); try{var w=iframe.contentWindow; if(!w) return; var cleanup=function(){ setTimeout(function(){ try{iframe.src=\"about:blank\";}catch(e){} },300); }; if(\"onafterprint\" in w){ w.addEventListener(\"afterprint\", cleanup); } if(w.matchMedia){ var mql=w.matchMedia(\"print\"); if(mql){ if(mql.addEventListener) mql.addEventListener(\"change\", function(ev){ if(!ev.matches) cleanup();}); else if(mql.addListener) mql.addListener(function(m){ if(!m.matches) cleanup();}); } } setTimeout(function(){ try{w.focus(); w.print();}catch(e){} }, 150); }catch(e){} }; try{iframe.src=url;}catch(e){ window.open(url,\"_blank\"); } });}catch(e){}})();</script>';
  }
}
