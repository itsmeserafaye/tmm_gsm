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
        // Keep href as real URL for graceful fallback when JS fails
        $attrs['href'] = $href;
        if (isset($attrs['data-print-url']) && !isset($attrs['onclick'])) {
          $attrs['onclick'] = 'return window.tmmPrintLink && window.tmmPrintLink(this);';
        }
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
    echo '<script>(function(){try{if(window.__tmmPrintBound)return;window.__tmmPrintBound=1;';
    echo 'function tmmEnsureFrame(){var f=document.getElementById(\"__tmmPrintFrame\"); if(!f){f=document.createElement(\"iframe\"); f.style.position=\"fixed\"; f.style.right=\"0\"; f.style.bottom=\"0\"; f.style.width=\"0\"; f.style.height=\"0\"; f.style.border=\"0\"; f.style.visibility=\"hidden\"; f.setAttribute(\"aria-hidden\",\"true\"); f.id=\"__tmmPrintFrame\"; document.body.appendChild(f);} return f;}';
    echo 'window.tmmPrintLink=function(el){try{var url=(el && el.getAttribute)? el.getAttribute(\"data-print-url\"):\"\"; if(!url) return true; var iframe=tmmEnsureFrame(); var loaded=false; var fallback=setTimeout(function(){ if(loaded) return; try{var w=window.open(url,\"tmm_print\",\"noopener,noreferrer,width=900,height=700\"); if(w){ var h=function(){ try{w.close();}catch(e){} }; if(\"onafterprint\" in w){ w.addEventListener(\"afterprint\", h); } } }catch(e){ window.open(url,\"_blank\"); } },1200); iframe.onload=function(){ loaded=true; clearTimeout(fallback); try{var w=iframe.contentWindow; if(!w) return; var cleanup=function(){ setTimeout(function(){ try{iframe.src=\"about:blank\";}catch(e){} },300); }; if(\"onafterprint\" in w){ w.addEventListener(\"afterprint\", cleanup); } if(w.matchMedia){ var mql=w.matchMedia(\"print\"); if(mql){ if(mql.addEventListener) mql.addEventListener(\"change\", function(ev){ if(!ev.matches) cleanup();}); else if(mql.addListener) mql.addListener(function(m){ if(!m.matches) cleanup();}); } } setTimeout(function(){ try{w.focus(); w.print();}catch(e){} },150); }catch(e){} }; try{iframe.src=url;}catch(e){ window.open(url,\"_blank\"); } return false; }catch(e){ return true; }};';
    echo 'document.addEventListener(\"click\",function(e){var t=e.target; if(!t) return; var el=t.closest? t.closest(\"[data-print-url]\") : null; if(!el) return; if(window.tmmPrintLink && window.tmmPrintLink(el)===false){ e.preventDefault(); }});';
    echo '}catch(e){}})();</script>';
  }
}
