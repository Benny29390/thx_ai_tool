<?php
/**
 * /rules — Zentrale Regel-Verwaltung (Master-Detail-Layout)
 *
 * Sidebar links: Scope-Wechsler (GLOBAL + alle Kunden).
 * Detail rechts:
 *   - Scope GLOBAL : globale Regeln (customer_id IS NULL)
 *   - Scope Kunde X: kundenspezifische Regeln (oben) + globale Regeln (unten,
 *     mit Toggle "fuer diesen Kunden aktiv" — Override-Mechanik via customer_rules).
 *
 * Modals fuer Anlegen/Bearbeiten + Kategorien-Verwaltung sind ausgelagerte
 * Partials, die auch im Steckbrief eingebunden werden.
 */

// Aktive Kundenliste (vom Route-Handler bereitgestellt: $rules, $types, $categories, $customers)
$customers = $customers ?? [];
// scope kommt als GET-Param: ?scope=global oder ?scope=<customer_id>
$scopeParam = isset($_GET['scope']) ? trim((string)$_GET['scope']) : 'global';
$activeScope = ($scopeParam === 'global') ? 'global' : (int)$scopeParam;
?>

<?php include __DIR__ . '/../admin/_customer_master_styles.php'; ?>

<style>
/* === Rules-Detail: kompaktes Tabellen-Layout, skaliert bis ~500 Regeln === */
.rl-detail { display: flex; flex-direction: column; gap: 12px; min-width: 0; overflow-y: auto; padding-bottom: 12px; }
.rl-card {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 12px;
    /* Wichtig: KEIN overflow:hidden — schneidet sonst Kacheln im Grid ab.
       Der border-radius greift trotzdem, weil die inneren Bloecke keine
       eigene Hintergrundfarbe ueber den Rand hinaus haben. */
    flex-shrink: 0;
}
.rl-card > .rl-head { border-radius: 12px 12px 0 0; }
.rl-head { padding: 12px 16px 10px; border-bottom: 1px solid var(--slate-100); }
.rl-head-title { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.rl-head-title h2 { margin: 0; font-size: var(--d-fs-lg); flex: 1; min-width: 0; }
.rl-head-subtitle { font-size: var(--d-fs-xs); color: var(--slate-500); }

/* Toolbar: Suche + Pill-Filter + Sortierung */
.rl-toolbar { padding: 8px 16px; background: #fafbfc; display: flex; flex-direction: column; gap: 6px; border-radius: 0 0 12px 12px; }
.rl-toolbar-row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.rl-search {
    flex: 1; min-width: 200px; padding: 6px 10px; border: 1px solid var(--slate-200);
    border-radius: 6px; font-size: var(--d-fs-sm); background: #fff;
}
.rl-search:focus { outline: none; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px var(--thoxan-100); }
.rl-pill {
    padding: 3px 9px; border: 1px solid var(--slate-200); background: #fff;
    border-radius: 12px; font-size: var(--d-fs-xs); color: var(--slate-700);
    cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
    transition: all 0.12s;
}
.rl-pill:hover { border-color: var(--slate-300); }
.rl-pill.is-active { background: var(--thoxan-700); color: #fff; border-color: var(--thoxan-700); }
.rl-pill[data-empty="1"] { opacity: 0.4; }
.rl-pill[data-empty="1"]:hover { opacity: 0.7; }
.rl-pill-count { font-size: 10px; opacity: 0.75; font-family: ui-monospace, monospace; }
.rl-pill-label { color: var(--slate-500); font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; margin-right: 4px; padding-left: 2px; }
.rl-sort {
    padding: 5px 10px; border: 1px solid var(--slate-200); border-radius: 6px;
    font-size: var(--d-fs-xs); background: #fff; color: var(--slate-700); cursor: pointer;
}

/* Kategorie-Sektion mit kollabierbarem Header */
.rl-cat {
    border-top: 1px solid var(--slate-100);
}
.rl-cat:first-of-type { border-top: 0; }
.rl-cat.is-empty .rl-cat-head { opacity: 0.55; }
.rl-cat.is-empty .rl-cat-head:hover { opacity: 0.85; }
.rl-cat-head {
    display: flex; align-items: center; gap: 8px; padding: 8px 16px;
    background: #fafbfc; cursor: pointer; user-select: none;
    border-left: 3px solid var(--cat-color, #9ca3af);
    font-size: var(--d-fs-sm); color: var(--slate-700);
}
.rl-cat-head:hover { background: var(--slate-50); }
.rl-cat-chev { font-size: 18px; color: var(--slate-500); transition: transform 0.15s; }
.rl-cat.is-collapsed .rl-cat-chev { transform: rotate(-90deg); }
.rl-cat-name { font-weight: 600; }
.rl-cat-count { margin-left: auto; color: var(--slate-500); font-family: ui-monospace, monospace; font-size: var(--d-fs-xs); }
.rl-cat-body { display: block; }
.rl-cat.is-collapsed .rl-cat-body { display: none; }

/* Tabelle: kompakte Zeilen */
/* Kachel-Grid statt langer Tabelle: 4 nebeneinander, responsive umsortierend */
.rl-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 10px; padding: 14px 16px;
    border-radius: 0 0 12px 12px;
}
.rl-tile {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 10px;
    padding: 10px 12px; display: flex; flex-direction: column; gap: 6px;
    transition: border-color 0.12s, box-shadow 0.12s;
    min-height: 110px;
}
.rl-tile:hover { border-color: var(--slate-300); box-shadow: 0 2px 8px rgba(15,23,42,0.05); }
.rl-tile.is-disabled .rl-tile-name { text-decoration: line-through; color: var(--slate-400); }
.rl-tile.is-disabled { opacity: 0.6; }
/* "Andere globale Regeln" — Card + Tiles gedaempft */
.rl-card-muted { background: #fafbfc; border-style: dashed; }
.rl-card-muted .rl-head { border-bottom-color: var(--slate-200); }
.rl-tile.is-other { background: #fafbfc; opacity: 0.78; }
.rl-tile.is-other .rl-tile-name { color: var(--slate-500); }
.rl-tile-addscope {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 9px; cursor: pointer;
    font-size: 10px; background: #eff6ff; color: #2563eb; border: 1px dashed #93c5fd;
}
.rl-tile-addscope:hover { background: #dbeafe; border-style: solid; }
.rl-tile-addscope .material-symbols-rounded { font-size: 12px; }
.rl-tile-head { display: flex; align-items: flex-start; gap: 8px; }
.rl-tile-name {
    flex: 1; font-weight: 600; color: var(--slate-800); font-size: var(--d-fs-sm);
    line-height: 1.3; cursor: pointer;
    display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}
.rl-tile-edit { background: transparent; border: none; cursor: pointer; padding: 2px; color: var(--slate-400); border-radius: 4px; flex-shrink: 0; }
.rl-tile-edit:hover { color: var(--thoxan-700); background: var(--slate-100); }
.rl-tile-edit .material-symbols-rounded { font-size: 16px; }
.rl-tile-meta { display: flex; gap: 4px; flex-wrap: wrap; }
.rl-tile-content {
    font-size: var(--d-fs-xs); color: var(--slate-500); line-height: 1.45;
    display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3; -webkit-box-orient: vertical;
    overflow: hidden; cursor: pointer; flex: 1; min-height: 0;
}
.rl-badge {
    font-size: 10px; padding: 2px 7px; border-radius: 9px;
    background: var(--slate-100); color: var(--slate-600); white-space: nowrap;
}
.rl-badge-inactive { background: #fee2e2; color: var(--rose-700); }
.rl-actions { display: flex; gap: 1px; }
.rl-actions button { background: transparent; border: none; cursor: pointer; padding: 4px; color: var(--slate-400); border-radius: 4px; }
.rl-actions button:hover { color: var(--thoxan-700); background: var(--slate-100); }
.rl-actions .material-symbols-rounded { font-size: 16px; }

/* Toggle-Switch */
.rl-toggle { position: relative; display: inline-block; width: 28px; height: 16px; flex-shrink: 0; cursor: pointer; }
.rl-toggle input { opacity: 0; width: 0; height: 0; }
.rl-toggle-slider { position: absolute; inset: 0; background: var(--slate-300); border-radius: 16px; transition: 0.18s; }
.rl-toggle-slider:before { content: ''; position: absolute; height: 12px; width: 12px; left: 2px; bottom: 2px; background: white; border-radius: 50%; transition: 0.18s; box-shadow: 0 1px 2px rgba(0,0,0,0.15); }
.rl-toggle input:checked + .rl-toggle-slider { background: #10b981; }
.rl-toggle input:checked + .rl-toggle-slider:before { transform: translateX(12px); }

.rl-empty { padding: 24px; text-align: center; color: var(--slate-400); font-size: var(--d-fs-sm); }

/* Sidebar-spezifisch */
.cm-customer.cm-customer-global .cm-customer-abbr { background: #2563eb; color:#fff; }
.cm-customer.cm-customer-global { font-weight: 600; }
.cm-sb-divider { font-size: 10px; color: var(--slate-400); text-transform: uppercase; letter-spacing: 0.05em; padding: 8px 8px 4px; margin-top: 4px; border-top: 1px solid var(--slate-100); }
</style>

<div class="thx-page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="thx-page-title">Regeln</h1>
        <div class="thx-page-subtitle">KI-Schreibregeln — global oder pro Kunde. Toggle deaktiviert eine Regel nur im gewählten Scope.</div>
    </div>
    <div class="thx-page-actions">
        <button class="thx-btn thx-btn-secondary" onclick="ptOpen()" title="Projekt-Arten anlegen/bearbeiten — synchron zu Kunden">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">workspaces</span>
            Projekt-Arten
        </button>
        <button class="thx-btn thx-btn-primary" id="rl-new-rule" onclick="rlNewRule()">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">add</span>
            Neue Regel
        </button>
    </div>
</div>

<div class="cm-page">
    <!-- Sidebar 1:1 wie /admin/customers -->
    <aside class="cm-sidebar" id="cm-sidebar">
        <div class="cm-sb-collapsed">
            <button class="cm-sb-toggle" onclick="cmToggleSidebar()" title="Aufklappen">
                <span class="material-symbols-rounded">menu</span>
            </button>
            <a href="/rules" class="cm-sb-collapsed-home" title="Globale Regeln">
                <span class="material-symbols-rounded">rule</span>
            </a>
        </div>

        <div class="cm-sb-head">
            <div class="cm-sb-title">Regeln</div>
            <button class="cm-sb-toggle" onclick="cmToggleSidebar()" title="Einklappen">
                <span class="material-symbols-rounded">chevron_left</span>
            </button>
        </div>

        <div class="cm-sb-search">
            <div class="cm-search-wrap">
                <span class="material-symbols-rounded cm-search-icon">search</span>
                <input type="search" class="cm-search-input" id="cm-search"
                       placeholder="Kunde suchen…"
                       oninput="rlFilterSidebar(this.value)">
            </div>
        </div>

        <div class="cm-sb-quickactions">
            <a href="/rules?scope=global" class="cm-quick<?= $activeScope === 'global' ? ' is-active' : '' ?>" title="Globale Regeln — gelten fuer alle Kunden">
                <span class="material-symbols-rounded">public</span>
                <span>Globale Regeln</span>
                <span style="margin-left:auto;font-size:11px;color:var(--slate-500);font-family:ui-monospace,monospace;" id="rl-global-count">…</span>
            </a>
        </div>

        <?php
        // Filter-Pillen (Status / Art) — gleiche Optik wie /admin/customers
        $_allTags = [];
        foreach ($customers as $c) {
            foreach (($c['tags_array'] ?? []) as $t) { if ($t) $_allTags[$t] = ($_allTags[$t] ?? 0) + 1; }
        }
        ksort($_allTags);
        ?>
        <div class="cm-sb-filters">
            <div class="cm-filter-row">
                <span class="cm-filter-label">Status</span>
                <button class="cm-filter-pill is-active" data-filter="status" data-value="" onclick="cmTogglePill(this)">Alle</button>
                <button class="cm-filter-pill" data-filter="status" data-value="active" onclick="cmTogglePill(this)">
                    <span class="cm-dot cm-dot-active"></span>Aktiv
                </button>
                <button class="cm-filter-pill" data-filter="status" data-value="inactive" onclick="cmTogglePill(this)">
                    <span class="cm-dot cm-dot-inactive"></span>Inaktiv
                </button>
            </div>
            <?php if (!empty($_allTags)): ?>
            <div class="cm-filter-row">
                <span class="cm-filter-label">Art</span>
                <?php foreach ($_allTags as $tag => $count): ?>
                    <button class="cm-filter-pill" data-filter="tag" data-value="<?= htmlspecialchars($tag) ?>" onclick="cmTogglePill(this)">
                        <?= htmlspecialchars($tag) ?><span class="cm-pill-count"><?= $count ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="cm-sb-list" id="cm-customer-list">
            <?php foreach ($customers as $cust):
                $cid = (int) $cust['id'];
                $abbr = $cust['abbreviation'] ?: mb_substr($cust['name'], 0, 3);
                $isActive = $activeScope === $cid;
                $tags = $cust['tags_array'] ?? [];
                $tagsHaystack = mb_strtolower(implode(' ', $tags));
                $statusDot = $cust['is_active'] ? 'active' : 'inactive';
                $search = mb_strtolower($cust['name'] . ' ' . ($cust['abbreviation'] ?? '') . ' ' . $tagsHaystack);
            ?>
            <a class="cm-customer<?= $isActive ? ' is-active' : '' ?>"
               href="/rules?scope=<?= $cid ?>"
               data-scope="<?= $cid ?>"
               data-status="<?= $statusDot ?>"
               data-tags="<?= htmlspecialchars($tagsHaystack) ?>"
               data-search="<?= htmlspecialchars($search) ?>">
                <span class="cm-customer-abbr"><?= htmlspecialchars(mb_substr($abbr, 0, 3)) ?></span>
                <span class="cm-customer-name"><?= htmlspecialchars($cust['name']) ?></span>
                <span style="font-size:11px;color:var(--slate-500);font-family:ui-monospace,monospace;" data-customer-count="<?= $cid ?>">…</span>
            </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- Detail-Pane -->
    <main class="rl-detail" id="rl-detail">
        <div style="padding:30px;text-align:center;color:var(--slate-400);">
            <span class="material-symbols-rounded" style="font-size:32px;">hourglass_top</span>
            <div style="margin-top:8px;">Lädt…</div>
        </div>
    </main>
</div>

<!-- Shared Modals -->
<?php include __DIR__ . '/_rule_modal.php'; ?>
<?php include __DIR__ . '/_project_types_modal.php'; ?>

<script>
(function () {
    const initialScope = <?= json_encode($activeScope) ?>;
    // Alle aktiven Kategorien aus dem Backend (auch leere) — fuer Filter + Kategorie-Render
    const allCategories = <?= json_encode(array_map(fn($c) => [
        'id' => (int) $c['id'],
        'name' => $c['name'],
        'color' => $c['color'] ?? '#9ca3af',
        'icon' => $c['icon'] ?? 'rule',
        'sort_order' => (int) ($c['sort_order'] ?? 999),
    ], $categories ?? []), JSON_UNESCAPED_UNICODE) ?>;
    // Projekt-Arten — wird lazy geladen
    let allProjectTypes = [];
    async function rlLoadProjectTypes() {
        if (allProjectTypes.length) return;
        try {
            const r = await App.get('/admin/project-types');
            if (r.success) allProjectTypes = r.data?.project_types || [];
        } catch (_) {}
    }
    let currentScope = initialScope; // 'global' | <int>
    let state = null;
    let filterType = 'all';
    let filterStatus = 'all';
    let filterCategory = 'all'; // (legacy, wird im UI nicht mehr genutzt)
    let filterScope = 'all';    // 'all' | int (project_type_id) | 'universal' (kein Geltungsbereich)
    let filterEnforcement = 'all'; // 'all' | 'strict' | 'soft'
    let filterApplies = 'all';     // 'all' | 'content' | 'tool' | 'both'
    let filterSearch = '';
    let viewMode = (() => { try { return localStorage.getItem('thx_rules_view') || 'list'; } catch (_) { return 'list'; } })();
    function setViewMode(v) {
        viewMode = v;
        try { localStorage.setItem('thx_rules_view', v); } catch (_) {}
        renderDetail();
    }
    window.rlSetView = setViewMode;
    let filterSort = (() => { try { return localStorage.getItem('thx_rules_sort') || 'name_asc'; } catch (_) { return 'name_asc'; } })();

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

    async function loadAndRender() {
        const detail = document.getElementById('rl-detail');
        detail.innerHTML = '<div style="padding:30px;text-align:center;color:var(--slate-400);"><span class="material-symbols-rounded" style="font-size:32px;">hourglass_top</span><div style="margin-top:8px;">Lädt…</div></div>';
        await rlLoadProjectTypes();
        try {
            if (currentScope === 'global') {
                const r = await App.get('/rules?active=all&scope=global');
                if (!r.success) throw new Error(r.message || 'Fehler');
                state = { mode: 'global', rules: r.data?.rules || [] };
            } else {
                const r = await App.get('/admin/customers/' + currentScope + '/rules');
                if (!r.success) throw new Error(r.message || 'Fehler');
                state = {
                    mode: 'customer',
                    customerId: currentScope,
                    // Backend liefert 'specific' als flaches Array — fallback auf grouped-flatMap fuer alte API
                    specific: (r.data?.specific && Array.isArray(r.data.specific))
                        ? r.data.specific
                        : (r.data?.grouped || []).flatMap(g => g.rules || []),
                    globals: r.data?.globals || [],
                    globalsApplicable: r.data?.globals_applicable || [],
                    globalsOther: r.data?.globals_other || [],
                    customerPtIds: (r.data?.customer_project_type_ids || []).map(Number),
                    customerProjectTypes: r.data?.customer_project_types || [],
                    customerName: r.data?.customer_name || ''
                };
                // Falls API noch keine globals liefert: nachladen
                if (!state.globals.length) {
                    const g = await App.get('/rules?active=all&scope=global');
                    if (g.success) state.globals = g.data?.rules || [];
                    // Disabled-Flag mit Customer-Override mergen
                    const disabled = r.data?.disabled_ids || [];
                    state.globals.forEach(gr => { gr.disabled = disabled.includes(parseInt(gr.id, 10)); });
                }
                // Falls Backend kein Split mitliefert (Cache/alte API): hier aufteilen
                if (!state.globalsApplicable.length && !state.globalsOther.length) {
                    const cptIds = state.customerPtIds || [];
                    state.globalsApplicable = [];
                    state.globalsOther = [];
                    (state.globals || []).forEach(g => {
                        const pt = (g.project_type_ids || []).map(Number);
                        const isUni = pt.length === 0;
                        const overlap = pt.some(id => cptIds.includes(id));
                        if (isUni || overlap) state.globalsApplicable.push(g);
                        else state.globalsOther.push(g);
                    });
                }
            }
            renderDetail();
            updateSidebarCounts();
        } catch (e) {
            detail.innerHTML = '<div style="padding:30px;text-align:center;color:var(--rose-600);">' + esc(e.message || 'Fehler') + '</div>';
        }
    }

    function renderDetail() {
        const detail = document.getElementById('rl-detail');
        if (state.mode === 'global') {
            detail.innerHTML = renderGlobalView();
        } else {
            detail.innerHTML = renderCustomerView();
        }
        attachListeners();
    }

    function applySort(rules) {
        const arr = rules.slice();
        const collator = new Intl.Collator('de', { sensitivity: 'base' });
        switch (filterSort) {
            case 'name_desc':     arr.sort((a, b) => collator.compare(b.name || '', a.name || '')); break;
            case 'priority_desc': arr.sort((a, b) => (b.priority || 0) - (a.priority || 0) || collator.compare(a.name || '', b.name || '')); break;
            case 'created_desc':  arr.sort((a, b) => (b.id || 0) - (a.id || 0)); break;
            case 'created_asc':   arr.sort((a, b) => (a.id || 0) - (b.id || 0)); break;
            case 'type':          arr.sort((a, b) => (a.rule_type || '').localeCompare(b.rule_type || '') || collator.compare(a.name || '', b.name || '')); break;
            default:              arr.sort((a, b) => collator.compare(a.name || '', b.name || ''));
        }
        return arr;
    }

    function applyClientFilter(rules, opts = {}) {
        const q = (filterSearch || '').toLowerCase().trim();
        const ignoreScope = !!opts.ignoreScope;
        const filtered = (rules || []).filter(r => {
            if (filterType !== 'all' && r.rule_type !== filterType) return false;
            // "Aktiv" = greift hier wirklich. "Inaktiv" = greift hier nicht (entweder global is_active=0
            // oder per Customer-Override deaktiviert). Aus User-Sicht ist beides "Regel greift nicht".
            const effActive = r.is_active && !r.disabled;
            if (filterStatus === 'active' && !effActive) return false;
            if (filterStatus === 'inactive' && effActive) return false;
            if (filterStatus === 'manual' && !r.force_enabled) return false;
            if (filterEnforcement !== 'all') {
                const eff = r.enforcement || 'strict';
                if (eff !== filterEnforcement) return false;
            }
            if (filterApplies !== 'all') {
                const ap = r.applies_to || 'both';
                // 'content'/'tool' matchen exklusiv, aber 'both'-Regeln greifen IN BEIDEN Filtern mit
                if (filterApplies === 'content' && ap !== 'content' && ap !== 'both') return false;
                if (filterApplies === 'tool' && ap !== 'tool' && ap !== 'both') return false;
                if (filterApplies === 'both' && ap !== 'both') return false;
            }
            // Bereich-Filter: ist eine Projekt-Art gewaehlt, treffen Regeln mit
            // GENAU dieser Art ODER ohne Geltungsbereich-Einschraenkung (universell).
            if (!ignoreScope && filterScope !== 'all') {
                const ptIds = r.project_type_ids || [];
                const isUniversal = ptIds.length === 0;
                if (!isUniversal && !ptIds.includes(parseInt(filterScope, 10))) return false;
            }
            if (q) {
                const hay = (r.name + ' ' + (r.rule_content || '') + ' ' + (r.description || '')).toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });
        return applySort(filtered);
    }

    function groupByCategory(rules) {
        const groups = {};
        rules.forEach(r => {
            const k = r.category_id || 0;
            if (!groups[k]) groups[k] = {
                id: k || null,
                name: r.category_name || 'Ohne Kategorie',
                color: r.category_color || '#9ca3af',
                icon: r.category_icon || 'help',
                rules: [],
            };
            groups[k].rules.push(r);
        });
        return Object.values(groups).sort((a, b) => (a.id || 999) - (b.id || 999));
    }

    // Toolbar: Suche + Status/Typ/Kategorie-Pillen + Sortierung. Counts pro aktueller Liste.
    function renderToolbar(allRules) {
        const typeCounts = {};
        const typeLabels = {};
        const catCounts = {};
        let activeRules = 0, inactiveRules = 0, manualRules = 0;
        let strictRules = 0, softRules = 0;
        let contentRules = 0, toolRules = 0, bothRules = 0;
        let noCatCount = 0;
        (allRules || []).forEach(r => {
            const t = r.rule_type || 'unknown';
            typeCounts[t] = (typeCounts[t] || 0) + 1;
            if (r.type_name) typeLabels[t] = r.type_name;
            else typeLabels[t] = t.charAt(0).toUpperCase() + t.slice(1);
            const effActive = r.is_active && !r.disabled;
            if (effActive) activeRules++; else inactiveRules++;
            if (r.force_enabled) manualRules++;
            if ((r.enforcement || 'strict') === 'soft') softRules++; else strictRules++;
            const ap = r.applies_to || 'both';
            if (ap === 'content') contentRules++;
            else if (ap === 'tool') toolRules++;
            else bothRules++;
            const cid = parseInt(r.category_id, 10) || 0;
            if (cid > 0) catCounts[cid] = (catCounts[cid] || 0) + 1;
            else noCatCount++;
        });
        const total = (allRules || []).length;

        // Alle definierten Typen (System-Whitelist) — auch wenn 0 Treffer in der aktuellen Liste
        const allTypes = [
            { slug: 'style', label: 'Schreibstil' }, { slug: 'format', label: 'Format' },
            { slug: 'content', label: 'Inhalt' }, { slug: 'tone', label: 'Tonalität' },
            { slug: 'link', label: 'Link' }, { slug: 'seo', label: 'SEO' }, { slug: 'language', label: 'Sprache' },
        ];

        const statusPill = (val, label, count, color) => `
            <button class="rl-pill ${filterStatus === val ? 'is-active' : ''}" data-pill-status="${val}" type="button" ${count === 0 && val !== 'all' ? 'data-empty="1"' : ''}>
                ${color ? `<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${color};"></span>` : ''}
                ${label}<span class="rl-pill-count">${count}</span>
            </button>`;

        const typePillsHtml = allTypes.map(t => {
            const cnt = typeCounts[t.slug] || 0;
            return `<button class="rl-pill ${filterType === t.slug ? 'is-active' : ''}" data-pill-type="${esc(t.slug)}" type="button" ${cnt === 0 ? 'data-empty="1"' : ''}>${esc(t.label)}<span class="rl-pill-count">${cnt}</span></button>`;
        }).join('');

        // Bereich-Pillen: zeigen die Anzahl der Regeln, die im jeweiligen Bereich greifen
        // — exklusiv-zugewiesen PLUS universelle (die ueberall greifen).
        const scopeCounts = {};
        let universalCount = 0;
        (allRules || []).forEach(r => {
            const ids = r.project_type_ids || [];
            if (!ids.length) {
                universalCount++; // taucht in JEDER Projekt-Art-Pille auf
            } else {
                ids.forEach(id => { scopeCounts[id] = (scopeCounts[id] || 0) + 1; });
            }
        });
        // Im Customer-Scope nur die Projekt-Arten des Kunden anzeigen — andere sind unzutreffend
        // und wuerden den User in die Irre fuehren (z.B. "Kunde"-Filter fuer ein Eigenprojekt).
        const inCustomerScope = state && state.mode === 'customer';
        const customerPtIds = (state && state.customerPtIds) || [];
        const scopePts = inCustomerScope
            ? (allProjectTypes || []).filter(pt => customerPtIds.includes(pt.id))
            : (allProjectTypes || []);
        const scopePillsHtml = scopePts.map(pt => {
            const cnt = (scopeCounts[pt.id] || 0) + universalCount; // universelle zählen mit
            const active = String(filterScope) === String(pt.id);
            return `<button class="rl-pill ${active ? 'is-active' : ''}" data-pill-scope="${pt.id}" type="button" ${cnt === 0 ? 'data-empty="1"' : ''}>
                <span style="display:inline-block;width:7px;height:7px;border-radius:2px;background:${esc(pt.color)};"></span>
                ${esc(pt.name)}<span class="rl-pill-count">${cnt}</span>
            </button>`;
        }).join('');

        return `
            <div class="rl-toolbar">
                <div class="rl-toolbar-row">
                    <input type="search" class="rl-search" id="rl-filter-search" placeholder="Suche in Name, Inhalt, Beschreibung…" value="${esc(filterSearch)}">
                    <select class="rl-sort" id="rl-filter-sort" title="Sortierung">
                        <option value="name_asc" ${filterSort==='name_asc'?'selected':''}>↑ Name</option>
                        <option value="name_desc" ${filterSort==='name_desc'?'selected':''}>↓ Name</option>
                        <option value="priority_desc" ${filterSort==='priority_desc'?'selected':''}>Priorität</option>
                        <option value="created_desc" ${filterSort==='created_desc'?'selected':''}>Neueste zuerst</option>
                        <option value="created_asc" ${filterSort==='created_asc'?'selected':''}>Älteste zuerst</option>
                        <option value="type" ${filterSort==='type'?'selected':''}>Typ</option>
                    </select>
                </div>
                <div class="rl-toolbar-row">
                    <span class="rl-pill-label">Status</span>
                    ${statusPill('all', 'Alle', total)}
                    ${statusPill('active', 'Aktiv', activeRules, '#10b981')}
                    ${statusPill('inactive', 'Inaktiv', inactiveRules, '#ef4444')}
                    ${inCustomerScope ? statusPill('manual', 'Manuell', manualRules, '#f59e0b') : ''}
                </div>
                <div class="rl-toolbar-row">
                    <span class="rl-pill-label" title="Typ = was die Regel funktional macht (vom System vorgegeben)">Typ</span>
                    <button class="rl-pill ${filterType === 'all' ? 'is-active' : ''}" data-pill-type="all" type="button">Alle<span class="rl-pill-count">${total}</span></button>
                    ${typePillsHtml}
                </div>
                <div class="rl-toolbar-row">
                    <span class="rl-pill-label" title="Welche Regeln gelten fuer welche Projekt-Art? Universelle Regeln (kein Bereich gesetzt) erscheinen in jedem Filter mit.">Bereich</span>
                    <button class="rl-pill ${filterScope === 'all' ? 'is-active' : ''}" data-pill-scope="all" type="button">Alle<span class="rl-pill-count">${total}</span></button>
                    ${scopePillsHtml}
                </div>
                <div class="rl-toolbar-row">
                    <span class="rl-pill-label" title="Strikt = MUSS eingehalten werden. Empfehlung = SOLL eingehalten werden, bei Konflikt darf abgewichen werden.">Einhaltung</span>
                    <button class="rl-pill ${filterEnforcement === 'all' ? 'is-active' : ''}" data-pill-enf="all" type="button">Alle<span class="rl-pill-count">${total}</span></button>
                    <button class="rl-pill ${filterEnforcement === 'strict' ? 'is-active' : ''}" data-pill-enf="strict" type="button" ${strictRules === 0 ? 'data-empty="1"' : ''}>
                        <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#b91c1c;"></span>
                        Strikt<span class="rl-pill-count">${strictRules}</span>
                    </button>
                    <button class="rl-pill ${filterEnforcement === 'soft' ? 'is-active' : ''}" data-pill-enf="soft" type="button" ${softRules === 0 ? 'data-empty="1"' : ''}>
                        <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#94a3b8;"></span>
                        Empfehlung<span class="rl-pill-count">${softRules}</span>
                    </button>
                </div>
                <div class="rl-toolbar-row">
                    <span class="rl-pill-label" title="Wo gilt die Regel? Content = nur erzeugte Inhalte, Tool = nur Dialog mit Dir, Beides = ueberall. 'Beides'-Regeln zaehlen in Content- und Tool-Filter mit.">Wirkung</span>
                    <button class="rl-pill ${filterApplies === 'all' ? 'is-active' : ''}" data-pill-applies="all" type="button">Alle<span class="rl-pill-count">${total}</span></button>
                    <button class="rl-pill ${filterApplies === 'content' ? 'is-active' : ''}" data-pill-applies="content" type="button" ${(contentRules + bothRules) === 0 ? 'data-empty="1"' : ''}>
                        <span style="display:inline-block;width:7px;height:7px;border-radius:2px;background:#15803d;"></span>
                        Content<span class="rl-pill-count">${contentRules + bothRules}</span>
                    </button>
                    <button class="rl-pill ${filterApplies === 'tool' ? 'is-active' : ''}" data-pill-applies="tool" type="button" ${(toolRules + bothRules) === 0 ? 'data-empty="1"' : ''}>
                        <span style="display:inline-block;width:7px;height:7px;border-radius:2px;background:#6d28d9;"></span>
                        Tool<span class="rl-pill-count">${toolRules + bothRules}</span>
                    </button>
                    <button class="rl-pill ${filterApplies === 'both' ? 'is-active' : ''}" data-pill-applies="both" type="button" ${bothRules === 0 ? 'data-empty="1"' : ''}>
                        Beides<span class="rl-pill-count">${bothRules}</span>
                    </button>
                </div>
            </div>
        `;
    }

    // Kategorie-Collapse-State (in localStorage)
    function catCollapsed(catId) {
        try { return (localStorage.getItem('thx_rules_collapsed') || '').split(',').includes(String(catId || 0)); } catch (_) { return false; }
    }
    window.rlToggleCat = function(catId) {
        try {
            let cur = (localStorage.getItem('thx_rules_collapsed') || '').split(',').filter(Boolean);
            const key = String(catId || 0);
            if (cur.includes(key)) cur = cur.filter(x => x !== key);
            else cur.push(key);
            localStorage.setItem('thx_rules_collapsed', cur.join(','));
        } catch (_) {}
        const el = document.querySelector(`.rl-cat[data-cat-id="${catId || 0}"]`);
        if (el) el.classList.toggle('is-collapsed');
    };

    // Kachel-Markup pro Regel
    function ruleTileHtml(r, opts = {}) {
        const inactive = !r.is_active;
        const disabled = !!r.disabled;
        const showOverrideToggle = !!opts.showOverrideToggle;
        const otherScope = !!opts.otherScope;
        const customerPtIds = opts.customerPtIds || [];
        const cls = (otherScope ? 'is-other' : '') + ' ' + ((inactive || disabled) ? 'is-disabled' : '');
        let toggle;
        if (otherScope) {
            // Toggle = positiver Override: an = customer_rules.is_active=1; aus = Default (Regel greift nicht)
            toggle = `<label class="rl-toggle" title="Regel fuer diesen Kunden trotzdem aktivieren (statt eine Dublette anzulegen)">
                <input type="checkbox" data-rule-id="${r.id}" data-force-toggle>
                <span class="rl-toggle-slider"></span>
            </label>`;
        } else if (showOverrideToggle) {
            const isForceEnabled = !!r.force_enabled;
            const title = disabled
                ? 'Aus fuer diesen Kunden'
                : (isForceEnabled
                    ? 'Manuell aktiviert (Projekt-Art passt nicht) — Klick: wieder ausblenden'
                    : 'Aktiv — klick: fuer diesen Kunden deaktivieren');
            toggle = `<label class="rl-toggle" title="${title}">
                <input type="checkbox" data-rule-id="${r.id}" data-override-toggle ${disabled ? '' : 'checked'} ${isForceEnabled ? 'data-force-on="1"' : ''}>
                <span class="rl-toggle-slider"></span>
            </label>`;
        } else {
            toggle = `<label class="rl-toggle" title="${inactive ? 'Inaktiv' : 'Aktiv'}">
                <input type="checkbox" data-rule-id="${r.id}" data-active-toggle ${inactive ? '' : 'checked'}>
                <span class="rl-toggle-slider"></span>
            </label>`;
        }
        const content = (r.rule_content || '').trim().replace(/\s+/g, ' ');
        const pts = r.project_types || [];
        let scopeBadges;
        if (otherScope) {
            // Hervorhebung: welche Projekt-Art FEHLT beim Kunden
            scopeBadges = pts.map(p => {
                const customerHas = customerPtIds.includes(p.id);
                if (customerHas) {
                    return `<span class="rl-badge" style="background:${esc(p.color)}22;color:${esc(p.color)};" title="${esc(p.name)}">${esc(p.name)}</span>`;
                }
                return `<button type="button" class="rl-tile-addscope" data-add-scope-pt="${p.id}" data-add-scope-name="${esc(p.name)}" title="Bereich hinzufuegen — Regel greift dann auch fuer diesen Kunden"><span class="material-symbols-rounded">add</span>${esc(p.name)}</button>`;
            }).join('');
        } else {
            scopeBadges = pts.length === 0
                ? '<span class="rl-badge" style="background:#eff6ff;color:#2563eb;" title="Gilt fuer alle Kunden">⤢ alle</span>'
                : pts.slice(0, 3).map(p => `<span class="rl-badge" style="background:${esc(p.color)}22;color:${esc(p.color)};" title="${esc(p.name)}">${esc(p.name)}</span>`).join('')
                  + (pts.length > 3 ? `<span class="rl-badge" title="${pts.slice(3).map(p => p.name).join(', ')}">+${pts.length - 3}</span>` : '');
        }
        const forceBadge = (showOverrideToggle && r.force_enabled)
            ? '<span class="rl-badge" style="background:#fef3c7;color:#92400e;" title="Manuell aktiviert — Projekt-Art des Kunden passt nicht zur Regel">manuell</span>'
            : '';
        const enforcement = r.enforcement || 'strict';
        const enforcementBadge = enforcement === 'soft'
            ? '<span class="rl-badge" style="background:#f1f5f9;color:#64748b;border:1px dashed #cbd5e1;" title="Empfehlung — bei Konflikt mit Klarheit/Nutzen darf abgewichen werden">Empf.</span>'
            : '<span class="rl-badge" style="background:#fee2e2;color:#b91c1c;" title="Strikt — muss eingehalten werden">Strikt</span>';
        const appliesTo = r.applies_to || 'both';
        const appliesBadge = appliesTo === 'tool'
            ? '<span class="rl-badge" style="background:#ede9fe;color:#6d28d9;" title="Gilt nur im Tool-Dialog mit Dir, nicht fuer erzeugte Inhalte">Tool</span>'
            : (appliesTo === 'content'
                ? '<span class="rl-badge" style="background:#dcfce7;color:#15803d;" title="Gilt nur fuer erzeugte Inhalte (Artikel, Texte, etc.)">Content</span>'
                : ''); // both: kein eigener Badge — ist der Default
        return `
            <div class="rl-tile ${cls.trim()}" data-rule-id="${r.id}">
                <div class="rl-tile-head">
                    ${toggle}
                    <div class="rl-tile-name" onclick="rmOpen(${r.id})">${esc(r.name)}</div>
                    <button class="rl-tile-edit" onclick="rmOpen(${r.id})" title="Bearbeiten">
                        <span class="material-symbols-rounded">edit</span>
                    </button>
                </div>
                <div class="rl-tile-meta">
                    ${enforcementBadge}
                    ${appliesBadge}
                    ${r.type_name ? `<span class="rl-badge" style="color:${esc(r.type_color || '#64748b')};">${esc(r.type_name)}</span>` : ''}
                    ${scopeBadges}
                    ${forceBadge}
                    ${inactive ? '<span class="rl-badge rl-badge-inactive">aus</span>' : ''}
                </div>
                ${content ? `<div class="rl-tile-content" onclick="rmOpen(${r.id})">${esc(content)}</div>` : '<div class="rl-tile-content" style="font-style:italic;color:var(--slate-400);">(kein Inhalt)</div>'}
            </div>
        `;
    }

    function ruleGridHtml(rules, opts = {}) {
        if (!rules.length) return '';
        return `<div class="rl-grid">${rules.map(r => ruleTileHtml(r, opts)).join('')}</div>`;
    }

    function emptyHint(text, sub = '') {
        return `<div class="rl-empty">${text}${sub ? '<br><small style="color:var(--slate-400);">' + sub + '</small>' : ''}</div>`;
    }

    function renderGlobalView() {
        const allRules = state.rules || [];
        const filtered = applyClientFilter(allRules);
        let body;
        if (viewMode === 'matrix') {
            body = renderMatrixView(filtered);
        } else if (!filtered.length) {
            body = allRules.length === 0
                ? emptyHint('Noch keine globalen Regeln.', '<a href="javascript:rlNewRule()" style="color:var(--thoxan-700);">+ Erste Regel anlegen</a>')
                : emptyHint('Keine Treffer im aktuellen Filter (' + allRules.length + ' Regeln insgesamt).');
        } else {
            body = ruleGridHtml(filtered);
        }
        return `
            <div class="rl-card">
                ${renderToolbarBar(allRules, 'Globale Regeln', 'Gelten by default fuer alle Kunden')}
                ${renderToolbar(allRules)}
            </div>
            <div class="rl-card">
                <div class="rl-head">
                    <div class="rl-head-title">
                        <h2><span style="color:#2563eb;">⬤</span> Globale Regeln</h2>
                        <span class="rl-head-subtitle">${filtered.length} von ${allRules.length} Treffer</span>
                    </div>
                </div>
                ${body}
            </div>
        `;
    }

    function renderCustomerView() {
        const specAll = state.specific || [];
        const spec = applyClientFilter(specAll);
        const gApplAll = state.globalsApplicable || [];
        const gAppl = applyClientFilter(gApplAll);
        const gOtherAll = state.globalsOther || [];
        const gOther = applyClientFilter(gOtherAll);
        const inactiveCount = specAll.filter(r => !r.is_active).length;
        const disabledCount = gApplAll.filter(g => g.disabled).length;
        // Toolbar zaehlt ueber alles, was MOEGLICHERWEISE relevant ist
        // (specific + applicable; other bewusst raus, damit Counts der Realitaet entsprechen)
        const allRulesForToolbar = [...specAll, ...gApplAll];

        // Kundens Projekt-Arten als Liste fuer den Header
        const cptList = (state.customerProjectTypes || [])
            .map(pt => `<span class="rl-badge" style="background:${esc(pt.color)}22;color:${esc(pt.color)};">${esc(pt.name)}</span>`)
            .join('') || '<span class="rl-badge" style="background:#f1f5f9;color:#64748b;">keine Projekt-Art gesetzt</span>';

        let block2;
        if (viewMode === 'matrix') {
            block2 = renderMatrixView(spec);
        } else if (!spec.length) {
            block2 = specAll.length > 0
                ? emptyHint('Keine Treffer im aktuellen Filter (' + specAll.length + ' Regel' + (specAll.length === 1 ? '' : 'n') + ' insgesamt).')
                : emptyHint('Noch keine kundenspezifische Regel.', 'Beispiel: „Verwende Sie-Anrede" — gilt nur fuer ' + esc(state.customerName || 'diesen Kunden') + '.');
        } else {
            block2 = ruleGridHtml(spec);
        }
        const block3 = viewMode === 'matrix'
            ? renderMatrixView(gAppl)
            : (!gAppl.length
                ? emptyHint('Keine Treffer.', gApplAll.length ? '(' + gApplAll.length + ' insgesamt — Filter anpassen)' : 'Es greifen keine globalen Regeln fuer diesen Kunden.')
                : ruleGridHtml(gAppl, { showOverrideToggle: true }));

        // Block 4: andere globale Regeln (greift nicht beim Kunden, weil dessen Projekt-Arten nicht passen)
        // Immer ausgeklappt — User kann pro Regel einzeln "trotzdem aktivieren" (verhindert Dubletten).
        // Bereich-Filter wirkt hier bewusst NICHT — der User soll alle anderen Regeln sehen koennen.
        let block4Html = '';
        if (gOtherAll.length) {
            // Block 4 ignoriert filterScope (Bereich), aber respektiert Status/Typ/Suche
            const gOtherBlockFiltered = applyClientFilter(gOtherAll, { ignoreScope: true });
            const block4Body = viewMode === 'matrix'
                ? renderMatrixView(gOtherBlockFiltered)
                : (!gOtherBlockFiltered.length ? emptyHint('Keine Treffer im Filter.') : ruleGridHtml(gOtherBlockFiltered, { otherScope: true, customerPtIds: state.customerPtIds }));
            const missingPts = new Set();
            gOtherAll.forEach(g => (g.project_type_ids || []).forEach(id => { if (!state.customerPtIds.includes(parseInt(id, 10))) missingPts.add(parseInt(id, 10)); }));
            const missingNames = Array.from(missingPts).map(id => {
                const pt = (allProjectTypes || []).find(p => p.id === id);
                return pt ? pt.name : null;
            }).filter(Boolean);

            block4Html = `
                <div class="rl-card rl-card-muted">
                    <div class="rl-head">
                        <div class="rl-head-title">
                            <h2 style="opacity:0.7;"><span style="color:#94a3b8;">⬤</span> Andere globale Regeln</h2>
                            <span class="rl-head-subtitle">${gOtherBlockFiltered.length} von ${gOtherAll.length} · greifen nicht fuer ${esc(state.customerName || 'diesen Kunden')}</span>
                        </div>
                        <div class="rl-head-subtitle">
                            ${missingNames.length ? 'Sind anderen Projekt-Arten zugeordnet (<strong>' + missingNames.map(esc).join('</strong>, <strong>') + '</strong>). Per Toggle lassen sich einzelne Regeln trotzdem fuer diesen Kunden aktivieren — vermeidet Dubletten.' : 'Diese globalen Regeln greifen aktuell nicht.'}
                        </div>
                    </div>
                    ${block4Body}
                </div>
            `;
        }

        return `
            <div class="rl-card">
                ${renderToolbarBar(allRulesForToolbar, esc(state.customerName || 'Kunde'), 'Kundenspezifische Regeln + globale Regeln, die fuer diesen Kunden greifen. Bereich(e): ' + cptList)}
                ${renderToolbar(allRulesForToolbar)}
            </div>

            <div class="rl-card">
                <div class="rl-head">
                    <div class="rl-head-title">
                        <h2><span style="color:#8b5cf6;">⬤</span> Kundenspezifische Regeln</h2>
                        <span class="rl-head-subtitle">${specAll.length} angelegt${inactiveCount ? ' · ' + inactiveCount + ' inaktiv' : ''}</span>
                    </div>
                    <div class="rl-head-subtitle">Gelten nur fuer ${esc(state.customerName || 'diesen Kunden')} — zusaetzlich zu den globalen</div>
                </div>
                ${block2}
            </div>

            <div class="rl-card">
                <div class="rl-head">
                    <div class="rl-head-title">
                        <h2><span style="color:#2563eb;">⬤</span> Globale Regeln</h2>
                        <span class="rl-head-subtitle">${gAppl.length} von ${gApplAll.length}${disabledCount ? ' · ' + disabledCount + ' fuer diesen Kunden deaktiviert' : ''}</span>
                    </div>
                    <div class="rl-head-subtitle">Greifen fuer ${esc(state.customerName || 'diesen Kunden')} — Toggle aus = nur hier deaktivieren</div>
                </div>
                ${block3}
            </div>

            ${block4Html}
        `;
    }


    // Page-Header der Toolbar-Card (Titel + View-Toggle rechts)
    function renderToolbarBar(allRules, title, subtitle) {
        return `
            <div class="rl-head">
                <div class="rl-head-title">
                    <h2>${title}</h2>
                    <span class="rl-head-subtitle">${(allRules || []).length} Regeln</span>
                    ${renderViewToggle()}
                </div>
                ${subtitle ? `<div class="rl-head-subtitle">${subtitle}</div>` : ''}
            </div>
        `;
    }

    function renderViewToggle() {
        return `
            <div style="margin-left:auto;display:inline-flex;gap:0;border:1px solid var(--slate-200);border-radius:6px;overflow:hidden;">
                <button onclick="rlSetView('list')" type="button" title="Kachel-Ansicht"
                        style="padding:5px 9px;border:none;cursor:pointer;background:${viewMode==='list'?'var(--thoxan-700)':'#fff'};color:${viewMode==='list'?'#fff':'var(--slate-600)'};font-size:var(--d-fs-xs);display:inline-flex;align-items:center;gap:4px;">
                    <span class="material-symbols-rounded" style="font-size:14px;">grid_view</span>Kacheln
                </button>
                <button onclick="rlSetView('matrix')" type="button" title="Matrix-Ansicht"
                        style="padding:5px 9px;border:none;cursor:pointer;background:${viewMode==='matrix'?'var(--thoxan-700)':'#fff'};color:${viewMode==='matrix'?'#fff':'var(--slate-600)'};font-size:var(--d-fs-xs);display:inline-flex;align-items:center;gap:4px;border-left:1px solid var(--slate-200);">
                    <span class="material-symbols-rounded" style="font-size:14px;">grid_on</span>Matrix
                </button>
            </div>
        `;
    }

    // Matrix-View: unveraendert (Zeilen = Regeln, Spalten = Projekt-Arten + Universell).
    function renderMatrixView(rules) {
        const pts = allProjectTypes || [];
        if (!rules.length) return '<div class="rl-empty">Keine Regeln im aktuellen Filter.</div>';
        const headerCols = `
            <th style="text-align:center;padding:6px;min-width:80px;font-size:10px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;" title="Gilt fuer alle (kein Bereich eingeschraenkt)">Universell</th>
            ${pts.map(pt => `
                <th style="text-align:center;padding:6px;min-width:80px;font-size:10px;color:${esc(pt.color)};text-transform:uppercase;letter-spacing:0.04em;" title="${esc(pt.name)}">
                    ${esc(pt.name)}
                </th>
            `).join('')}
        `;
        const rows = rules.map(r => {
            const ptIds = new Set((r.project_type_ids || []).map(Number));
            const isUniversal = ptIds.size === 0;
            const cell = (id, active) => `
                <td style="text-align:center;padding:5px;">
                    <input type="checkbox" data-matrix-rule="${r.id}" data-matrix-pt="${id || 'universal'}"
                           ${active ? 'checked' : ''}
                           style="cursor:pointer;width:16px;height:16px;">
                </td>`;
            return `
                <tr style="border-bottom:1px solid var(--slate-100);${!r.is_active ? 'opacity:0.45;' : ''}">
                    <td style="padding:7px 12px;position:sticky;left:0;background:#fff;border-right:1px solid var(--slate-100);min-width:200px;max-width:280px;">
                        <div style="font-weight:500;color:var(--slate-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;" onclick="rmOpen(${r.id})">${esc(r.name)}</div>
                        ${r.type_name ? `<span class="rl-badge" style="color:${esc(r.type_color || '#64748b')};font-size:10px;">${esc(r.type_name)}</span>` : ''}
                    </td>
                    ${cell(null, isUniversal)}
                    ${pts.map(pt => cell(pt.id, ptIds.has(pt.id))).join('')}
                </tr>
            `;
        }).join('');
        return `
            <div style="overflow-x:auto;max-height:calc(100vh - 350px);">
                <table style="width:100%;border-collapse:collapse;font-size:var(--d-fs-sm);">
                    <thead style="position:sticky;top:0;background:#fafbfc;z-index:1;border-bottom:1px solid var(--slate-200);">
                        <tr>
                            <th style="text-align:left;padding:8px 12px;position:sticky;left:0;background:#fafbfc;border-right:1px solid var(--slate-100);font-size:10px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;">Regel</th>
                            ${headerCols}
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    function attachListeners() {
        // Suche
        const s = document.getElementById('rl-filter-search');
        if (s) s.addEventListener('input', () => { filterSearch = s.value; renderDetail(); });
        // Sortierung
        const so = document.getElementById('rl-filter-sort');
        if (so) so.addEventListener('change', () => {
            filterSort = so.value;
            try { localStorage.setItem('thx_rules_sort', filterSort); } catch (_) {}
            renderDetail();
        });
        // Pillen — Status
        document.querySelectorAll('[data-pill-status]').forEach(p => {
            p.addEventListener('click', () => { filterStatus = p.dataset.pillStatus; renderDetail(); });
        });
        // Pillen — Typ
        document.querySelectorAll('[data-pill-type]').forEach(p => {
            p.addEventListener('click', () => { filterType = p.dataset.pillType; renderDetail(); });
        });
        // Pillen — Geltungsbereich
        document.querySelectorAll('[data-pill-scope]').forEach(p => {
            p.addEventListener('click', () => { filterScope = p.dataset.pillScope; renderDetail(); });
        });
        // Pillen — Einhaltung (strict/soft)
        document.querySelectorAll('[data-pill-enf]').forEach(p => {
            p.addEventListener('click', () => { filterEnforcement = p.dataset.pillEnf; renderDetail(); });
        });
        // Pillen — Wirkungsbereich (content/tool/both)
        document.querySelectorAll('[data-pill-applies]').forEach(p => {
            p.addEventListener('click', () => { filterApplies = p.dataset.pillApplies; renderDetail(); });
        });
        // Matrix-Zellen: Klick toggelt project_type_ids
        document.querySelectorAll('input[data-matrix-rule]').forEach(cb => {
            cb.addEventListener('change', async () => {
                const ruleId = parseInt(cb.dataset.matrixRule, 10);
                const ptKey = cb.dataset.matrixPt; // 'universal' | int
                const checked = cb.checked;
                // Lokales state-Update
                const all = state.mode === 'global' ? (state.rules || []) : [...(state.specific||[]), ...(state.globals||[])];
                const r = all.find(x => parseInt(x.id, 10) === ruleId);
                if (!r) return;
                let newIds = [...(r.project_type_ids || [])];
                if (ptKey === 'universal') {
                    // Checkbox "Universell" exklusiv: an → alle anderen aus; aus → keine Aenderung an spezifischen
                    if (checked) newIds = [];
                } else {
                    const id = parseInt(ptKey, 10);
                    if (checked) { if (!newIds.includes(id)) newIds.push(id); }
                    else newIds = newIds.filter(x => x !== id);
                }
                try {
                    const resp = await App.post('/admin/rules/' + ruleId + '/scope', { project_type_ids: newIds });
                    if (!resp.success) throw new Error(resp.message || 'Fehler');
                    r.project_type_ids = newIds;
                    // project_types-Array aus allProjectTypes synthetisieren
                    r.project_types = newIds.map(id => allProjectTypes.find(p => p.id === id)).filter(Boolean);
                    renderDetail();
                } catch (e) {
                    cb.checked = !checked;
                    App.showNotification('Speichern fehlgeschlagen: ' + (e.message || ''), 'error');
                }
            });
        });

        // Active-Toggle (rule.is_active umschalten)
        document.querySelectorAll('input[data-active-toggle]').forEach(cb => {
            cb.addEventListener('change', async () => {
                const id = parseInt(cb.dataset.ruleId, 10);
                const active = cb.checked;
                try {
                    const r = await App.put('/rules?id=' + id, { is_active: active });
                    if (!r.success) throw new Error(r.message);
                    // Lokal aktualisieren
                    const updateRule = (arr) => (arr || []).forEach(rr => { if (parseInt(rr.id, 10) === id) rr.is_active = active; });
                    if (state.mode === 'global') updateRule(state.rules);
                    else { updateRule(state.specific); updateRule(state.globals); }
                    renderDetail();
                } catch (e) {
                    cb.checked = !active;
                    App.showNotification('Toggle fehlgeschlagen: ' + (e.message || ''), 'error');
                }
            });
        });

        // "Bereich hinzufuegen" — fuegt Projekt-Art beim Kunden hinzu, sodass die Regel greift
        document.querySelectorAll('[data-add-scope-pt]').forEach(btn => {
            btn.addEventListener('click', async (ev) => {
                ev.stopPropagation();
                const ptId = parseInt(btn.dataset.addScopePt, 10);
                const ptName = btn.dataset.addScopeName || '';
                if (!confirm('Soll "' + (state.customerName || 'dieser Kunde') + '" zur Projekt-Art "' + ptName + '" hinzugefuegt werden?\n\nDadurch greifen kuenftig alle globalen Regeln dieser Art auch fuer den Kunden.')) return;
                try {
                    const r = await App.post('/admin/customers/' + state.customerId + '/project-types', { project_type_id: ptId });
                    if (!r.success) throw new Error(r.message || 'Fehler');
                    App.showNotification('Projekt-Art "' + ptName + '" hinzugefuegt — Regeln werden neu geladen.', 'success');
                    loadAndRender();
                } catch (e) {
                    App.showNotification('Fehler: ' + (e.message || ''), 'error');
                }
            });
        });

        // Override-Toggle in Block 3 (greifende globale Regeln) — Toggle aus = customer-spezifisch deaktivieren.
        // Spezialfall: war die Regel force_enabled (manuell aktiviert obwohl Scope nicht passt),
        // dann bedeutet "aus" nicht "Disabled-Override anlegen", sondern den Force-Override loeschen
        // (Regel wandert dann in Block 4 zurueck).
        document.querySelectorAll('input[data-override-toggle]').forEach(cb => {
            cb.addEventListener('change', async () => {
                const id = parseInt(cb.dataset.ruleId, 10);
                const wasForceOn = cb.dataset.forceOn === '1';
                const isOn = cb.checked;
                try {
                    if (wasForceOn && !isOn) {
                        // Force-Override aufheben — Regel wandert nach Block 4
                        const r = await App.post('/admin/customers/' + state.customerId + '/rules', { rule_id: id, enabled: false });
                        if (!r.success) throw new Error(r.message);
                    } else {
                        const disabled = !isOn;
                        const r = await App.post('/admin/customers/' + state.customerId + '/rules', { rule_id: id, disabled });
                        if (!r.success) throw new Error(r.message);
                    }
                    loadAndRender();
                } catch (e) {
                    cb.checked = !isOn;
                    App.showNotification('Override fehlgeschlagen: ' + (e.message || ''), 'error');
                }
            });
        });

        // Force-Toggle in Block 4 (andere globale Regeln) — Toggle an = customer-spezifisch aktivieren.
        // Damit greift die Regel fuer diesen Kunden, ohne dass eine Dublette als kundenspez. Regel angelegt wird.
        document.querySelectorAll('input[data-force-toggle]').forEach(cb => {
            cb.addEventListener('change', async () => {
                const id = parseInt(cb.dataset.ruleId, 10);
                const enabled = cb.checked;
                try {
                    const r = await App.post('/admin/customers/' + state.customerId + '/rules', { rule_id: id, enabled });
                    if (!r.success) throw new Error(r.message);
                    loadAndRender();
                } catch (e) {
                    cb.checked = !enabled;
                    App.showNotification('Aktivierung fehlgeschlagen: ' + (e.message || ''), 'error');
                }
            });
        });
    }

    async function updateSidebarCounts() {
        // Global-Count
        try {
            const r = await App.get('/rules?scope=global&active=all');
            if (r.success) {
                const cnt = (r.data?.rules || []).length;
                const el = document.getElementById('rl-global-count');
                if (el) el.textContent = cnt;
            }
        } catch (e) {}
        // Kunden-Counts: lazy in batches (asynchron, nicht blockierend)
        // Wir holen die Counts in einem Sammelschritt
        try {
            const r = await App.get('/rules?scope=all&active=all');
            if (r.success) {
                const counts = {};
                (r.data?.rules || []).forEach(rr => {
                    if (rr.customer_id) counts[rr.customer_id] = (counts[rr.customer_id] || 0) + 1;
                });
                document.querySelectorAll('[data-customer-count]').forEach(el => {
                    const cid = parseInt(el.dataset.customerCount, 10);
                    const c = counts[cid] || 0;
                    el.textContent = c > 0 ? '+' + c : '0';
                    el.closest('.rl-item')?.classList.toggle('has-specific', c > 0);
                });
            }
        } catch (e) {}
    }

    // Sidebar-Filter (Kunde suchen)
    window.rlFilterSidebar = function (q) {
        q = (q || '').toLowerCase().trim();
        document.querySelectorAll('.rl-item[data-scope]').forEach(el => {
            if (el.dataset.scope === 'global') { el.style.display = ''; return; }
            const s = el.dataset.search || '';
            el.style.display = (!q || s.includes(q)) ? '' : 'none';
        });
    };

    // Neue Regel im aktuellen Scope
    window.rlNewRule = function () {
        rmOpen(0, currentScope === 'global' ? null : currentScope);
    };

    // Nach Save/Delete im Modal: Detail neu laden
    document.addEventListener('rm:saved', () => loadAndRender());

    // Init — warten bis App-Object verfuegbar ist (app.js laedt nach Inline-Scripts)
    function waitForApp(fn) {
        if (window.App && typeof window.App.get === 'function') { fn(); return; }
        const i = setInterval(() => {
            if (window.App && typeof window.App.get === 'function') { clearInterval(i); fn(); }
        }, 50);
    }
    waitForApp(loadAndRender);
})();
</script>
