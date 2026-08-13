<?php
/** Customer Portal — Dashboard. Tabs + Kacheln gespiegelt aus dem Steckbrief. Kartendarstellung nutzt die echten Steckbrief-View-Klassen (sb-*). */
$h = fn($s) => htmlspecialchars((string)$s);
$abbr = trim($header['abbreviation'] ?? '');
if ($abbr === '') $abbr = mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/u', '', $header['name'] ?? '?'), 0, 3));
$cid = (int) ($customerId ?? 0);
$fileQ = !empty($isPreview) ? ('&customer=' . $cid) : '';
$fmtSize = function (int $b): string { if ($b >= 1048576) return round($b / 1048576, 1) . ' MB'; if ($b >= 1024) return round($b / 1024) . ' KB'; return $b . ' B'; };

$typeMeta = [
    'richtext'  => ['edit_note', 'var(--thoxan-500)'], 'kpi' => ['monitoring', '#0891b2'], 'brand' => ['palette', '#14b8a6'],
    'contacts'  => ['groups', '#0891b2'], 'links' => ['link', '#6366f1'], 'accounts' => ['key', 'var(--amber-600)'],
    'documents' => ['folder', 'var(--amber-500)'], 'images' => ['image', '#ec4899'],
];
// Icons/Akzente fuer System-Kacheln (analog Steckbrief sysIconMap)
$sysMeta = [
    'profile' => ['badge', 'var(--thoxan-700)'], 'markenprofil' => ['palette', '#ec4899'], 'regeln' => ['rule', '#8b5cf6'],
    'asana' => ['task_alt', '#f97316'], 'knowledge' => ['library_books', '#10b981'], 'website' => ['language', '#0891b2'],
    'site_monitor' => ['monitor_heart', '#6366f1'],
];

// ── Karten-Renderer: identisch zur Steckbrief-Anzeige (View-Markup, read-only) ──
$previewQ = !empty($isPreview) ? ('?customer=' . $cid) : '';
$cardBody = function (array $card) use ($h, $fileQ, $fmtSize, $previewQ): string {
    $b = $card['body']; $type = $card['type'];
    if ($type === '_system') {
        $kind = $b['kind'] ?? 'system';
        if ($kind === 'rules') {
            $o = '';
            foreach ($b['items'] as $it) {
                $o .= '<div class="sb-sys-block">';
                if ($it['title'] !== '') $o .= '<div class="sb-sys-block-title">' . $h($it['title']) . '</div>';
                if ($it['text'] !== '') $o .= '<div class="sb-rt-view">' . nl2br($h($it['text'])) . '</div>';
                $o .= '</div>';
            }
            return $o;
        }
        if ($kind === 'knowledge') {
            $iconMap = ['upload' => 'description', 'url' => 'link', 'text' => 'edit_note', 'chat' => 'chat', 'asana' => 'task_alt'];
            $o = '<p class="pf-sub" style="margin:0 0 8px;">' . (int)$b['count'] . ' Einträge</p><div class="cs-doc-list">';
            foreach ($b['items'] as $i => $it) {
                $hidden = $i >= 5 ? ' style="display:none;"' : '';
                $o .= '<div class="cs-doc-row pf-kbrow"' . $hidden . '>'
                    . '<span class="material-symbols-rounded cs-doc-icon">' . ($iconMap[$it['source_type']] ?? 'article') . '</span>'
                    . '<div class="cs-doc-title"><strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $h($it['title']) . '</strong>'
                    . ($it['description'] !== '' ? '<small style="color:#64748b;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $h(mb_substr($it['description'], 0, 100)) . '</small>' : '') . '</div>'
                    . ($it['category'] !== '' ? '<span class="cs-doc-type">' . $h($it['category']) . '</span>' : '')
                    . '<span class="cs-doc-type cs-doc-type-' . $h($it['source_type']) . '">' . $h($it['source_type']) . '</span>'
                    . '<span style="font-size:var(--d-fs-xs);color:#94a3b8;white-space:nowrap;">' . $h($it['date']) . '</span></div>';
            }
            $o .= '</div>';
            $tot = count($b['items']);
            if ($tot > 5) $o .= '<button class="cs-kb-toggle" onclick="pfKbToggle(this)" data-total="' . $tot . '"><span class="material-symbols-rounded">expand_more</span> Alle ' . $tot . ' Einträge zeigen (' . ($tot - 5) . ' weitere)</button>';
            return $o;
        }
        if ($kind === 'tasks') {
            $o = '<div class="sb-task-list">';
            foreach ($b['items'] as $it) {
                $o .= '<div class="pf-ms ' . (!empty($it['done']) ? 'done' : 'open') . '"><span class="material-symbols-rounded">' . (!empty($it['done']) ? 'check_circle' : 'radio_button_unchecked') . '</span><span>' . $h($it['name']) . '</span></div>';
            }
            return $o . '</div>';
        }
        if ($kind === 'monitor') {
            // Echtes Steckbrief-Widget client-seitig rendern (geteiltes Render-Modul)
            $bid = 'pf-sm-' . (int)$card['id'];
            return '<div id="' . $bid . '"><p class="pf-empty">Lädt Monitoring…</p></div>'
                . '<script>(function(){function go(){pfRenderMonitor(' . json_encode($bid) . ',' . json_encode($previewQ) . ');}'
                . 'if(window.pfRenderMonitor){go();}else{var s=document.createElement("script");s.src="/assets/js/portal/sm-widget.js";s.onload=go;document.body.appendChild(s);}})();</script>';
        }
        // info-rows (profile/website/markenprofil/site_monitor)
        $o = '<div class="sb-sys-rows">';
        foreach (($b['rows'] ?? []) as $rw) {
            $val = !empty($rw['url'])
                ? '<a class="sb-link-v-link" href="' . $h(preg_match('#^https?://#i', $rw['url']) ? $rw['url'] : 'https://' . $rw['url']) . '" target="_blank" rel="noopener">' . $h($rw['value']) . '</a>'
                : ($h($rw['value']));
            if (!empty($rw['color'])) $val = '<span style="display:inline-flex;align-items:center;gap:8px;"><span class="sb-color-swatch" style="width:18px;height:18px;border-radius:5px;background:' . $h($rw['color']) . ';"></span>' . $val . '</span>';
            $o .= '<div class="sb-sys-row"><div class="sb-sys-label">' . $h($rw['label']) . '</div><div class="sb-sys-val">' . $val . '</div></div>';
        }
        $o .= '</div>';
        if (!empty($b['note'])) $o .= '<div class="sb-rt-view" style="margin-top:8px;">' . nl2br($h($b['note'])) . '</div>';
        return $o;
    }
    if ($type === 'richtext') return '<div class="sb-rt-view">' . $b['html'] . '</div>';

    if ($type === 'kpi') {
        $o = '<div class="sb-kpi-list">';
        foreach ($b['items'] as $it) {
            $o .= '<div class="sb-kpi-row"><div class="sb-kpi-cell">'
                . ($it['value'] !== '' ? '<span class="sb-kpi-value">' . $h($it['value']) . '</span>' : '')
                . ($it['label'] !== '' ? '<span class="sb-kpi-label">' . $h($it['label']) . '</span>' : '')
                . '</div><div class="sb-kpi-cell">'
                . (!empty($it['target']) ? '<span class="sb-kpi-target">' . $h($it['target']) . '</span>' : '')
                . (!empty($it['period']) ? '<span class="sb-kpi-period">' . $h($it['period']) . '</span>' : '')
                . '</div></div>';
        }
        return $o . '</div>';
    }

    if ($type === 'brand') {
        $o = '';
        if (!empty($b['colors'])) {
            $o .= '<div class="sb-brand-section"><h4>Farben</h4>';
            foreach ($b['colors'] as $c) $o .= '<div class="sb-brand-row sb-brand-row-view"><span class="sb-color-swatch" style="background:' . $h($c['value']) . ';"></span><span class="sb-brand-hex">' . $h($c['value']) . '</span><span class="sb-brand-name">' . $h($c['name']) . '</span></div>';
            $o .= '</div>';
        }
        if (!empty($b['fonts'])) {
            $o .= '<div class="sb-brand-section"><h4>Schriftarten</h4>';
            foreach ($b['fonts'] as $f) $o .= '<div class="sb-brand-row sb-brand-row-view" style="grid-template-columns:1fr;"><span class="sb-brand-name" style="font-family:' . $h($f['name']) . ';">' . $h($f['name']) . '</span></div>';
            $o .= '</div>';
        }
        return $o;
    }

    if ($type === 'contacts') {
        $o = '<div class="sb-contacts">';
        foreach ($b['groups'] as $g) {
            $o .= '<div class="sb-contact-group">';
            if (!empty($g['title'])) $o .= '<div class="sb-contact-group-title-v">' . $h($g['title']) . '</div>';
            $o .= '<div class="sb-contact-people">';
            foreach ($g['people'] as $p) {
                $ini = $p['initials'] ?: mb_strtoupper(mb_substr($p['name'], 0, 2));
                $o .= '<div class="sb-contact-person sb-contact-person-view"><div class="sb-contact-avatar">' . $h($ini) . '</div><div class="sb-contact-fields">'
                    . ($p['role'] !== '' ? '<div class="sb-c-role">' . $h($p['role']) . '</div>' : '')
                    . '<div class="sb-c-name">' . $h($p['name']) . '</div>'
                    . ($p['email'] !== '' ? '<a class="sb-c-link" href="mailto:' . $h($p['email']) . '">' . $h($p['email']) . '</a>' : '')
                    . ($p['phone'] !== '' ? '<div class="sb-c-link">' . $h($p['phone']) . '</div>' : '')
                    . '</div></div>';
            }
            $o .= '</div></div>';
        }
        return $o . '</div>';
    }

    if ($type === 'links') {
        $o = '<div class="sb-links-list">';
        foreach ($b['items'] as $it) {
            $url = $it['url']; $href = ($url !== '' ? (preg_match('#^https?://#i', $url) ? $url : 'https://' . $url) : '');
            $txt = preg_replace('#^https?://#i', '', $url);
            $o .= '<div class="sb-link-row"><div class="sb-link-view">'
                . ($it['title'] !== '' ? '<div class="sb-link-v-title">' . $h($it['title']) . '</div>' : '')
                . ($href !== '' ? '<a class="sb-link-v-link" href="' . $h($href) . '" target="_blank" rel="noopener" title="' . $h($url) . '">' . $h($txt) . '</a>' : '')
                . '</div></div>';
        }
        return $o . '</div>';
    }

    if ($type === 'accounts') {
        $o = '<div class="sb-accounts-list">';
        foreach ($b['items'] as $it) {
            $url = $it['url']; $href = ($url !== '' ? (preg_match('#^https?://#i', $url) ? $url : 'https://' . $url) : '');
            $txt = preg_replace('#^https?://#i', '', $url);
            $o .= '<div class="sb-account-row"><div class="sb-account-view">'
                . ($it['label'] !== '' ? '<div class="sb-account-v-label">' . $h($it['label']) . '</div>' : '');
            if ($it['account_id'] !== '') $o .= '<div class="sb-account-v-id"><span class="sb-account-v-idval">' . $h($it['account_id']) . '</span><button type="button" class="sb-account-copy" onclick="pfCopy(this,\'' . $h(addslashes($it['account_id'])) . '\')" title="ID kopieren"><span class="material-symbols-rounded" style="font-size:15px;">content_copy</span></button></div>';
            if ($href !== '') $o .= '<a class="sb-account-v-link" href="' . $h($href) . '" target="_blank" rel="noopener" title="' . $h($url) . '">' . $h($txt) . '</a>';
            $o .= '</div></div>';
        }
        return $o . '</div>';
    }

    if ($type === 'documents') {
        if (empty($b['files']) && $b['note'] === '') return '<p class="pf-empty">Keine Dateien.</p>';
        $o = $b['note'] !== '' ? '<p class="pf-sub" style="margin:0 0 6px;">' . $h($b['note']) . '</p>' : '';
        $o .= '<div class="sb-file-list">';
        foreach (($b['files'] ?? []) as $f) {
            $o .= '<div class="sb-file-item"><span class="material-symbols-rounded">description</span><div class="sb-file-name"><a href="/api/v1/portal/card-file?id=' . (int)$f['id'] . $fileQ . '" target="_blank" rel="noopener" title="' . $h($f['name']) . '">' . $h($f['name']) . '</a></div><span class="sb-file-size">' . $fmtSize((int)$f['size']) . '</span></div>';
        }
        return $o . '</div>';
    }

    if ($type === 'images') {
        if (empty($b['files'])) return '<p class="pf-empty">Keine Bilder.</p>';
        $o = ($b['note'] ?? '') !== '' ? '<p class="pf-sub" style="margin:0 0 6px;">' . $h($b['note']) . '</p>' : '';
        $o .= '<div class="sb-image-grid">';
        foreach ($b['files'] as $f) {
            $src = '/api/v1/portal/card-file?id=' . (int)$f['id'] . $fileQ;
            $o .= '<div class="sb-image-tile"><a href="' . $src . '" target="_blank" rel="noopener"><img src="' . $src . '" alt="' . $h($f['name']) . '" loading="lazy"></a></div>';
        }
        return $o . '</div>';
    }
    return '';
};
$tile = function (string $icon, string $accent, string $title, string $bodyHtml) use ($h): string {
    return '<section class="sb-card" style="border-left-color:' . $accent . ';"><div class="sb-card-head"><span class="sb-card-icon"><span class="material-symbols-rounded">' . $icon . '</span></span><span class="sb-card-title">' . $h($title) . '</span></div><div class="sb-card-body">' . $bodyHtml . '</div></section>';
};

// ── Tabs ─────────────────────────────────────────────────────────────────────
$hasStatus = in_array('projektstatus', $modules, true) && !empty($projektstatus);
$hasMs     = in_array('meilensteine', $modules, true) && !empty($meilensteine);
$hasPp     = !empty($ppWidget); // Projektplanner-Dashboard (exakt die Steckbrief-Komponente)
$tabs = [];
foreach ($tabOrder as $t) {
    if (!empty($cardsByTab[$t]) || ($t === 'uebersicht' && ($hasStatus || $hasMs || $hasPp))) $tabs[$t] = $tabLabels[$t] ?? ucfirst($t);
}
$tabTiles = [];
foreach ($tabs as $t => $lbl) {
    $list = [];
    if ($t === 'uebersicht' && $hasPp) $list[] = $ppWidget; // 1:1 dieselbe Kachel wie im Steckbrief
    if ($t === 'uebersicht' && $hasStatus) {
        $bd = '<div style="margin-bottom:10px;"><span class="thx-chip is-active">' . $h($projektstatus['status']) . '</span></div><div style="font-weight:600;color:var(--slate-900);">' . $h($projektstatus['title']) . '</div>';
        if (!empty($projektstatus['from']) || !empty($projektstatus['to'])) $bd .= '<div class="pf-sub" style="margin-top:6px;">Zeitraum: ' . $h($projektstatus['from']) . (!empty($projektstatus['to']) ? ' – ' . $h($projektstatus['to']) : '') . '</div>';
        $list[] = $tile('flag', 'var(--emerald-600)', 'Projektstatus', $bd);
    }
    if ($t === 'uebersicht' && $hasMs) {
        $bd = '';
        foreach ($meilensteine as $m) $bd .= '<div class="pf-ms ' . (!empty($m['erledigt']) ? 'done' : 'open') . '"><span class="material-symbols-rounded">' . (!empty($m['erledigt']) ? 'check_circle' : 'radio_button_unchecked') . '</span><span>' . $h($m['titel']) . '</span>' . (!empty($m['datum']) ? '<span class="d">' . $h($m['datum']) . '</span>' : '') . '</div>';
        $list[] = $tile('timeline', 'var(--amber-500)', 'Meilensteine', $bd);
    }
    foreach (($cardsByTab[$t] ?? []) as $card) {
        if ($card['type'] === '_system') { [$ic, $acc] = $sysMeta[$card['system_key']] ?? ['widgets', 'var(--slate-400)']; }
        else { [$ic, $acc] = $typeMeta[$card['type']] ?? ['widgets', 'var(--slate-300)']; }
        $list[] = $tile($ic, $acc, $card['title'] ?: $lbl, $cardBody($card));
    }
    $tabTiles[$t] = $list;
}
$firstTab = array_key_first($tabs);
?>
<?php if ($hasPp) { require_once SERVICES_PATH . '/PpWidgetRenderer.php'; echo \Services\PpWidgetRenderer::css(); } ?>
<style>
    .pf-badge { display:inline-flex; align-items:center; justify-content:center; min-width:72px; height:72px; padding:0 14px; border-radius:18px; background:linear-gradient(135deg,var(--thoxan-700),#1976d2); color:#fff; font-size:var(--d-fs-xl); font-weight:800; letter-spacing:1px; flex-shrink:0; box-shadow:0 6px 18px rgba(0,76,155,0.18); text-transform:uppercase; }
    .cs-tabs { display:flex; gap:4px; flex-wrap:wrap; margin:1rem 0 1.25rem; border-bottom:1px solid var(--slate-200); }
    .cs-tab { display:inline-flex; align-items:center; gap:6px; padding:9px 14px 11px; background:transparent; border:0; border-bottom:2px solid transparent; cursor:pointer; font-size:var(--d-fs-sm); font-weight:600; color:var(--slate-500); font-family:inherit; }
    .cs-tab:hover { color:var(--slate-800); } .cs-tab.is-active { color:var(--thoxan-700); border-bottom-color:var(--thoxan-600); }
    .cs-tab .material-symbols-rounded { font-size:18px; transform:translateY(-1px); }
    .cs-tab-count { display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px; padding:0 7px; background:var(--slate-300); color:var(--slate-800); border-radius:999px; font-size:11px; font-weight:700; line-height:1; margin-left:6px; }
    .cs-tab.is-active .cs-tab-count { background:var(--thoxan-700); color:#fff; }
    .cs-kanban { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; align-items:start; }
    .cs-kanban .cs-col { min-width:0; display:flex; flex-direction:column; gap:12px; }
    @media (max-width:1100px){ .cs-kanban { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:760px){ .cs-kanban { grid-template-columns:1fr; } }
    .sb-card { background:#fff; border:1px solid var(--slate-200); border-left:3px solid var(--slate-300); border-radius:12px; overflow:hidden; display:flex; flex-direction:column; }
    .sb-card-head { display:flex; align-items:center; gap:.5rem; padding:.85rem 1rem; border-bottom:1px solid var(--slate-200); background:var(--slate-50); }
    .sb-card-icon { width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:var(--slate-100); color:var(--slate-600); flex-shrink:0; }
    .sb-card-icon .material-symbols-rounded { font-size:16px; color:var(--slate-600); }
    .sb-card-title { flex:1; min-width:0; font-size:var(--d-fs-base); font-weight:600; color:#1e293b; padding:4px 6px; }
    .sb-card-body { padding:.9rem 1rem 1rem; }
    /* Projektstatus/Meilensteine-Helfer */
    .pf-sub { color:var(--slate-500); font-size:var(--d-fs-xs); }
    .pf-ms { display:flex; align-items:flex-start; gap:10px; padding:7px 0; font-size:var(--d-fs-sm); }
    .pf-ms .material-symbols-rounded { font-size:18px; flex:0 0 auto; margin-top:1px; }
    .pf-ms.done .material-symbols-rounded { color:var(--emerald-600); } .pf-ms.open .material-symbols-rounded { color:var(--slate-300); }
    .pf-ms .d { margin-left:auto; color:var(--slate-400); font-size:var(--d-fs-xs); white-space:nowrap; padding-left:8px; }
    .pf-empty { color:var(--slate-400); font-size:var(--d-fs-sm); }

    /* ===== Karten-Anzeige: 1:1 aus dem Steckbrief (read-only) ===== */
    .sb-sys-rows { display:flex; flex-direction:column; gap:2px; }
    .sb-sys-row { padding:7px 0; border-bottom:1px solid #f1f5f9; } .sb-sys-row:last-child { border-bottom:none; }
    .sb-sys-label { font-size:var(--d-fs-xs); font-weight:600; text-transform:uppercase; letter-spacing:.4px; color:#94a3b8; margin-bottom:2px; }
    .sb-sys-val { font-size:var(--d-fs-sm); color:#1e293b; } .sb-sys-val a { color:var(--thoxan-700); text-decoration:none; } .sb-sys-val a:hover { text-decoration:underline; }
    .sb-sys-block { padding:8px 0; border-bottom:1px solid #f1f5f9; } .sb-sys-block:last-child { border-bottom:none; }
    .sb-sys-block-title { font-weight:600; color:#0f172a; font-size:var(--d-fs-sm); margin-bottom:3px; }
    .sb-task-list { display:flex; flex-direction:column; }
    /* Wissen-Liste (cs-doc-* aus dem Steckbrief) */
    .cs-doc-row { display:flex; gap:.6rem; padding:.5rem .6rem; border-bottom:1px solid #f1f5f9; align-items:center; }
    .cs-doc-row:last-of-type { border-bottom:none; }
    .cs-doc-icon { color:var(--thoxan-700); font-size:18px; }
    .cs-doc-title { flex:1; min-width:0; font-size:var(--d-fs-sm); }
    .cs-doc-type { font-size:var(--d-fs-xs); padding:1px 6px; border-radius:4px; background:#e6f0fa; color:var(--thoxan-900); white-space:nowrap; }
    .cs-doc-type-asana { background:#fff7ed; color:#c2410c; }
    .cs-kb-toggle { display:flex; align-items:center; justify-content:center; gap:4px; width:100%; margin-top:8px; padding:7px; background:none; border:1px dashed var(--slate-300); border-radius:8px; color:var(--slate-600); cursor:pointer; font-size:var(--d-fs-sm); font-family:inherit; }
    .cs-kb-toggle:hover { border-color:var(--thoxan-700); color:var(--thoxan-700); }
    .cs-kb-toggle .material-symbols-rounded { font-size:16px; }
    .sb-stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
    .sb-stat { background:var(--slate-50); border:1px solid var(--slate-200); border-radius:10px; padding:10px 12px; text-align:center; }
    .sb-stat-l { font-size:var(--d-fs-xs); color:var(--slate-400); text-transform:uppercase; letter-spacing:.3px; margin-bottom:4px; }
    .sb-stat-v { font-size:var(--d-fs-lg); font-weight:700; color:var(--slate-900); }
    .sb-rt-view { font-size:var(--d-fs-sm); line-height:1.6; color:#1e293b; }
    .sb-rt-view h1 { font-size:var(--d-fs-lg); margin:.5em 0 .3em; } .sb-rt-view h2 { font-size:var(--d-fs-base); margin:.5em 0 .3em; } .sb-rt-view h3 { font-size:var(--d-fs-sm); margin:.4em 0 .3em; }
    .sb-rt-view ul, .sb-rt-view ol { padding-left:1.5em; margin:.3em 0; } .sb-rt-view a { color:var(--thoxan-700); text-decoration:underline; }
    .sb-kpi-list { display:flex; flex-direction:column; gap:6px; }
    .sb-kpi-row { display:grid; grid-template-columns:1fr 1fr; gap:8px; align-items:start; padding:8px; background:linear-gradient(135deg,#f8fafc,#fff); border:1px solid #e2e8f0; border-radius:10px; }
    .sb-kpi-cell { display:flex; flex-direction:column; gap:2px; min-width:0; }
    .sb-kpi-row span { padding:5px 8px; display:block; }
    .sb-kpi-value { font-weight:700; color:var(--thoxan-800); font-size:var(--d-fs-base); }
    .sb-kpi-label { color:#64748b; font-size:var(--d-fs-xs); text-transform:uppercase; letter-spacing:.4px; }
    .sb-kpi-target, .sb-kpi-period { color:#1e293b; font-size:var(--d-fs-sm); }
    .sb-brand-section { margin-bottom:.8rem; } .sb-brand-section:last-child { margin-bottom:0; }
    .sb-brand-section h4 { margin:0 0 .3rem; font-size:var(--d-fs-sm); text-transform:uppercase; letter-spacing:.5px; color:#64748b; font-weight:600; }
    .sb-brand-row-view { display:grid; grid-template-columns:32px 1fr 1fr; gap:6px; align-items:center; padding:4px 0; }
    .sb-color-swatch { width:32px; height:32px; border-radius:8px; border:2px solid #fff; box-shadow:0 0 0 1px #e2e8f0, inset 0 0 0 1px rgba(0,0,0,.04); }
    .sb-brand-hex { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:var(--d-fs-sm); color:#334155; } .sb-brand-name { font-size:var(--d-fs-sm); color:#1e293b; }
    .sb-contacts { display:flex; flex-direction:column; gap:8px; }
    .sb-contact-group { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:8px 10px; }
    .sb-contact-group-title-v { font-size:var(--d-fs-sm); font-weight:700; color:#1e293b; text-transform:uppercase; letter-spacing:.4px; padding:4px 6px; margin-bottom:6px; }
    .sb-contact-people { display:flex; flex-direction:column; gap:6px; }
    .sb-contact-person-view { display:grid; grid-template-columns:36px 1fr; gap:8px; align-items:start; background:#fff; border:1px solid #f1f5f9; border-radius:8px; padding:6px; }
    .sb-contact-avatar { width:36px; height:36px; border-radius:9px; background:linear-gradient(135deg,#0891b2,#22d3ee); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:var(--d-fs-sm); letter-spacing:.5px; }
    .sb-contact-fields { display:flex; flex-direction:column; gap:2px; min-width:0; padding-top:2px; }
    .sb-c-role { color:#64748b; font-size:var(--d-fs-xs); text-transform:uppercase; letter-spacing:.3px; }
    .sb-c-name { font-weight:600; color:#1e293b; font-size:var(--d-fs-sm); }
    .sb-c-link { color:var(--thoxan-700); font-size:var(--d-fs-xs); text-decoration:none; } .sb-c-link:hover { text-decoration:underline; }
    .sb-links-list, .sb-accounts-list { display:flex; flex-direction:column; }
    .sb-link-row, .sb-account-row { padding:7px 0; border-bottom:1px solid #f1f5f9; } .sb-link-row:last-child, .sb-account-row:last-child { border-bottom:none; }
    .sb-link-view, .sb-account-view { display:flex; flex-direction:column; gap:2px; }
    .sb-link-v-title, .sb-account-v-label { font-weight:600; font-size:var(--d-fs-sm); color:#0f172a; }
    .sb-link-v-link, .sb-account-v-link { display:block; max-width:100%; color:var(--thoxan-700); text-decoration:none; font-size:var(--d-fs-sm); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .sb-link-v-link:hover, .sb-account-v-link:hover { text-decoration:underline; }
    .sb-account-v-id { display:flex; align-items:center; gap:6px; }
    .sb-account-v-idval { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; letter-spacing:.02em; font-size:var(--d-fs-sm); color:#334155; }
    .sb-account-copy { flex:0 0 auto; color:#94a3b8; background:none; border:none; cursor:pointer; padding:2px; border-radius:5px; display:flex; }
    .sb-account-copy:hover { background:rgba(0,76,155,.08); color:var(--thoxan-700); }
    .sb-file-list { display:flex; flex-direction:column; gap:4px; }
    .sb-file-item { display:flex; gap:8px; align-items:center; padding:7px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:var(--d-fs-sm); }
    .sb-file-item .material-symbols-rounded { color:var(--thoxan-700); font-size:18px; }
    .sb-file-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; } .sb-file-name a { color:inherit; text-decoration:none; } .sb-file-name a:hover { color:var(--thoxan-700); }
    .sb-file-size { font-size:var(--d-fs-xs); color:#94a3b8; white-space:nowrap; }
    .sb-image-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:6px; }
    .sb-image-tile { position:relative; aspect-ratio:1/1; border-radius:8px; overflow:hidden; background:#f1f5f9; border:1px solid #e2e8f0; }
    .sb-image-tile img { width:100%; height:100%; object-fit:cover; display:block; }
    .pf-preview { display:flex; align-items:center; gap:10px; flex-wrap:wrap; background:var(--amber-50); color:var(--amber-800); border:1px solid var(--amber-200); border-radius:10px; font-size:var(--d-fs-xs); font-weight:600; padding:8px 12px; margin-bottom:var(--space-4); }
    .pf-preview .material-symbols-rounded { font-size:16px; }
</style>

<?php if (!empty($isPreview)): ?>
    <div class="pf-preview"><span class="material-symbols-rounded">visibility</span> Vorschau-Modus (Team) — so sieht der Kunde seinen Bereich.
        <a href="/admin/customers/<?= $cid ?>/steckbrief" target="_blank" rel="noopener" class="thx-btn thx-btn-secondary thx-btn-small" style="margin-left:auto;"><span class="material-symbols-rounded">grid_view</span> Kacheln im Steckbrief schalten</a>
    </div>
<?php endif; ?>

<div class="thx-page-header" style="align-items:center;">
    <div style="display:flex;align-items:center;gap:1.25rem;min-width:0;">
        <div class="pf-badge"><?= $h($abbr) ?></div>
        <div style="min-width:0;">
            <h1 class="thx-page-title" style="margin:0;"><?= $h($header['name']) ?></h1>
            <div class="thx-page-subtitle">Der aktuelle Stand unserer Zusammenarbeit – auf einen Blick.</div>
        </div>
    </div>
</div>

<?php if (empty($tabs)): ?>
    <div class="sb-card"><div class="sb-card-body"><p class="pf-empty">Für Ihren Bereich ist aktuell noch nichts freigeschaltet. Ihr Thoxan-Team richtet die Ansicht gerade ein.</p></div></div>
<?php else: ?>
<div class="cs-tabs">
    <?php foreach ($tabs as $t => $lbl): $n = count($tabTiles[$t]); ?>
        <button class="cs-tab <?= $t === $firstTab ? 'is-active' : '' ?>" data-ptab="<?= $h($t) ?>" onclick="portalTab('<?= $h($t) ?>')"><?= $h($lbl) ?><?php if ($n > 1): ?><span class="cs-tab-count"><?= $n ?></span><?php endif; ?></button>
    <?php endforeach; ?>
</div>
<?php foreach ($tabs as $t => $lbl): $cols = [[], [], []]; foreach ($tabTiles[$t] as $i => $tileHtml) { $cols[$i % 3][] = $tileHtml; } ?>
    <div class="cs-tab-panel cs-kanban" data-ppanel="<?= $h($t) ?>" style="<?= $t === $firstTab ? '' : 'display:none;' ?>">
        <?php foreach ([0, 1, 2] as $ci): ?><div class="cs-col"><?= implode('', $cols[$ci]) ?></div><?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<script>
function portalTab(t) {
    document.querySelectorAll('.cs-tab').forEach(b => b.classList.toggle('is-active', b.dataset.ptab === t));
    document.querySelectorAll('.cs-tab-panel').forEach(p => p.style.display = p.dataset.ppanel === t ? '' : 'none');
}
function pfCopy(btn, val) { try { navigator.clipboard.writeText(val); App.showNotification('Kopiert', 'success', 1200); } catch (e) {} }
function pfKbToggle(btn) {
    const rows = btn.parentElement.querySelectorAll('.pf-kbrow');
    const expand = btn.dataset.open !== '1';
    rows.forEach((r, i) => { if (i >= 5) r.style.display = expand ? '' : 'none'; });
    btn.dataset.open = expand ? '1' : '0';
    const t = btn.dataset.total;
    btn.innerHTML = expand ? '<span class="material-symbols-rounded">expand_less</span> Weniger zeigen' : '<span class="material-symbols-rounded">expand_more</span> Alle ' + t + ' Einträge zeigen (' + (t - 5) + ' weitere)';
}
</script>
