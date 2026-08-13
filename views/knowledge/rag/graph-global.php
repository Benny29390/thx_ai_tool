<?php
$isAdmin = \Core\Auth::isAdmin();
?>

<style>
.kgg-container { display: flex; flex-direction: column; height: calc(100vh - 60px); overflow: hidden; }
.kgg-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--color-border,#e2e8f0);
    gap: 1rem; flex-shrink: 0; flex-wrap: wrap;
}
.kgg-header-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.kgg-header-left a { color: var(--color-text-secondary,#64748b); text-decoration: none; display: flex; }
.kgg-header h1 { margin: 0; font-size: var(--d-fs-lg); font-weight: 600; white-space: nowrap; }

.kgg-controls { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.kgg-controls select, .kgg-controls input {
    padding: 0.35rem 0.6rem; border: 1px solid var(--color-border,#e2e8f0);
    border-radius: 6px; font-size: var(--d-fs-sm); background: var(--color-bg-primary,#fff);
}
.kgg-stats { display: flex; gap: 0.5rem; font-size: var(--d-fs-xs); color: var(--color-text-secondary,#64748b); }
.kgg-stats strong { color: var(--color-text-primary,#1e293b); font-weight: 600; }

.kgg-body { flex: 1; position: relative; overflow: hidden; display: flex; }
#kgg-graph { flex: 1; min-height: 500px; background: var(--color-bg-primary,#fff); }

.kgg-side { width: 320px; border-left: 1px solid var(--color-border,#e2e8f0); padding: 1rem; overflow-y: auto; background: var(--color-bg-secondary,#f8fafc); display: none; }
.kgg-side.open { display: block; }
.kgg-side h3 { margin: 0 0 0.5rem; font-size: var(--d-fs-sm); }
.kgg-side-sub { color: var(--color-text-secondary,#64748b); font-size: var(--d-fs-xs); margin-bottom: 0.75rem; }
.kgg-side-docs { display: flex; flex-direction: column; gap: 0.5rem; }
.kgg-doc-item {
    padding: 0.5rem 0.625rem; background: var(--color-bg-primary,#fff);
    border: 1px solid var(--color-border,#e2e8f0); border-radius: 6px;
    font-size: var(--d-fs-sm); cursor: pointer; transition: border-color 0.15s;
}
.kgg-doc-item:hover { border-color: var(--color-primary,var(--thoxan-700)); }
.kgg-doc-title { font-weight: 500; }
.kgg-doc-meta { font-size: var(--d-fs-xs); color: var(--color-text-tertiary,#94a3b8); margin-top: 0.2rem; }

.kgg-legend { display: flex; gap: 0.4rem; font-size: var(--d-fs-xs); flex-wrap: wrap; }
.kgg-legend-item { display: flex; align-items: center; gap: 0.25rem; padding: 0.15rem 0.4rem; border-radius: 4px; background: var(--color-bg-secondary,#f8fafc); }
.kgg-legend-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

.kgg-loading { display: flex; align-items: center; justify-content: center; flex: 1; color: var(--color-text-secondary,#64748b); font-size: var(--d-fs-sm); }
.kgg-spinner {
    display: inline-block; width: 16px; height: 16px; border: 2px solid #e2e8f0;
    border-top-color: var(--thoxan-700); border-radius: 50%; animation: kgg-spin 0.8s linear infinite;
    margin-right: 8px; vertical-align: middle;
}
@keyframes kgg-spin { to { transform: rotate(360deg); } }
</style>

<div class="kgg-container">
    <div class="thx-page-header kgg-header">
        <div class="kgg-header-left" style="display:flex; align-items:center; gap:0.75rem;">
            <a href="/wissen" style="color:var(--color-text-secondary,#64748b); text-decoration:none; display:flex;"><span class="material-symbols-rounded">arrow_back</span></a>
            <div>
                <h1 class="thx-page-title">Wissen Graph</h1>
                <div class="thx-page-subtitle">Gesamt-Wissensgraph aller Dokumente</div>
                <div class="kgg-stats" id="kgg-stats" style="margin-top:0.25rem;">
                    <span><strong id="stat-entities">–</strong> Entities</span>
                    <span>·</span>
                    <span><strong id="stat-relations">–</strong> Relations</span>
                    <span>·</span>
                    <span><strong id="stat-documents">–</strong> Dokumente</span>
                </div>
            </div>
        </div>
        <div class="thx-page-actions kgg-controls">
            <?php if ($isAdmin): ?>
            <select id="filter-customer" onchange="loadGraph()">
                <option value="">Alle Kunden</option>
                <option value="null">Ohne Kunde</option>
                <?php
                $db = \Core\Database::getInstance();
                // Non-Admin: nur effektive Kundenliste (direkt + ueber Rolle)
                if (\Core\Auth::isAdmin()) {
                    $customers = $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
                } else {
                    $customers = \Core\Auth::customers();
                }
                foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select id="filter-type" onchange="loadGraph()">
                <option value="">Alle Typen</option>
                <option value="PER">Person</option>
                <option value="ORG">Organisation</option>
                <option value="LOC">Ort</option>
                <option value="PRODUCT">Produkt</option>
                <option value="CONCEPT">Konzept</option>
                <option value="EVENT">Event</option>
                <option value="MISC">Sonstiges</option>
            </select>
            <select id="filter-limit" onchange="loadGraph()">
                <option value="50">Top 50</option>
                <option value="100">Top 100</option>
                <option value="200" selected>Top 200</option>
                <option value="500">Top 500</option>
            </select>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="kggFit()" title="An Bildschirm anpassen">
                <span class="material-symbols-rounded" style="font-size:16px;">fit_screen</span>
            </button>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="kggReset()" title="Auswahl aufheben">
                <span class="material-symbols-rounded" style="font-size:16px;">restart_alt</span>
            </button>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="loadGraph()" title="Layout neu berechnen">
                <span class="material-symbols-rounded" style="font-size:16px;">refresh</span>
            </button>
            <div class="kgg-legend">
                <div class="kgg-legend-item"><span class="kgg-legend-dot" style="background:#ef4444"></span>PER</div>
                <div class="kgg-legend-item"><span class="kgg-legend-dot" style="background:#3b82f6"></span>ORG</div>
                <div class="kgg-legend-item"><span class="kgg-legend-dot" style="background:#10b981"></span>LOC</div>
                <div class="kgg-legend-item"><span class="kgg-legend-dot" style="background:#f59e0b"></span>PRODUCT</div>
                <div class="kgg-legend-item"><span class="kgg-legend-dot" style="background:#004c9b"></span>CONCEPT</div>
                <div class="kgg-legend-item"><span class="kgg-legend-dot" style="background:#ec4899"></span>EVENT</div>
                <div class="kgg-legend-item"><span class="kgg-legend-dot" style="background:#94a3b8"></span>MISC</div>
            </div>
        </div>
    </div>
    <div class="kgg-body">
        <div id="kgg-graph">
            <div class="kgg-loading"><span class="kgg-spinner"></span>Lade Graph...</div>
        </div>
        <div class="kgg-side" id="kgg-side">
            <h3 id="kgg-side-title">Details</h3>
            <div class="kgg-side-sub" id="kgg-side-sub"></div>
            <div class="kgg-side-docs" id="kgg-side-docs"></div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/cytoscape@3.30.2/dist/cytoscape.min.js"></script>
<script src="https://unpkg.com/layout-base@2.0.1/layout-base.js"></script>
<script src="https://unpkg.com/cose-base@2.2.0/cose-base.js"></script>
<script src="https://unpkg.com/cytoscape-fcose@2.2.0/cytoscape-fcose.js"></script>
<script>
(function() {
    const csrfToken = '<?= \Core\Session::getCsrfToken() ?>';

    const typeColors = {
        PER: '#ef4444', ORG: '#3b82f6', LOC: '#10b981',
        PRODUCT: '#f59e0b', CONCEPT: '#004c9b', EVENT: '#ec4899', MISC: '#94a3b8'
    };

    let cy = null;
    let currentData = null;

    window.loadGraph = async function() {
        const container = document.getElementById('kgg-graph');
        container.innerHTML = '<div class="kgg-loading"><span class="kgg-spinner"></span>Lade Graph...</div>';
        document.getElementById('kgg-side').classList.remove('open');

        const params = new URLSearchParams();
        const cust = document.getElementById('filter-customer')?.value;
        if (cust) params.set('customer_id', cust);
        const type = document.getElementById('filter-type').value;
        if (type) params.set('type', type);
        const limit = document.getElementById('filter-limit').value;
        if (limit) params.set('limit', limit);

        try {
            const resp = await fetch('/api/v1/knowledge/graph-global?' + params.toString(), {
                headers: { 'X-CSRF-Token': csrfToken }
            });
            const r = await resp.json();
            if (!r.success) {
                container.innerHTML = '<div class="kgg-loading">Fehler: ' + (r.message || '') + '</div>';
                return;
            }

            currentData = r.data;
            // Lade-Anzeige: Layout-Phase
            container.innerHTML = '<div class="kgg-loading"><span class="kgg-spinner"></span>Layout wird berechnet (' + (r.data.entities?.length||0) + ' Knoten, ' + (r.data.relations?.length||0) + ' Kanten)...</div>';
            // Dem Browser einen Tick Zeit geben, das Loading-DOM zu malen
            await new Promise(rq => requestAnimationFrame(() => requestAnimationFrame(rq)));
            renderGraph(r.data);

            document.getElementById('stat-entities').textContent = r.data.stats.entity_count || 0;
            const relStats = r.data.stats.relations_raw && r.data.stats.relations_raw > r.data.stats.relation_count
                ? r.data.stats.relation_count + ' (aus ' + r.data.stats.relations_raw.toLocaleString('de-DE') + ')'
                : r.data.stats.relation_count;
            document.getElementById('stat-relations').textContent = relStats;
            document.getElementById('stat-documents').textContent = r.data.stats.document_count || 0;
        } catch (e) {
            container.innerHTML = '<div class="kgg-loading">Fehler: ' + e.message + '</div>';
        }
    };

    function renderGraph(data) {
        const container = document.getElementById('kgg-graph');
        container.innerHTML = '';

        if (!data.entities || data.entities.length === 0) {
            container.innerHTML = '<div class="kgg-loading">Keine Entities gefunden. Filter anpassen oder Wissen anlegen.</div>';
            return;
        }

        // Knotengrößen: log-skaliert (1..N mentions → 18..56 px)
        const maxMentions = Math.max(1, ...data.entities.map(e => e.mention_count || 1));
        const nodeSize = m => {
            const ratio = Math.log(1 + (m || 1)) / Math.log(1 + maxMentions);
            return Math.round(18 + ratio * 38);
        };

        const elements = [
            ...data.entities.map(e => ({
                data: {
                    id: 'n' + e.id,
                    rawId: e.id,
                    label: e.name,
                    type: e.type,
                    color: typeColors[e.type] || '#94a3b8',
                    size: nodeSize(e.mention_count || 1),
                    tip: e.type + ' · ' + (e.mention_count || 1) + '× · ' + (e.doc_count || 0) + ' Dok.',
                },
            })),
            ...(data.relations || []).map(r => {
                // weight ist jetzt aggregiert (Summe) — normieren auf 0..1 fuer Stroke-Width
                const w = Math.min(1, Math.log(1 + (r.weight || 0.5)) / Math.log(1 + 20));
                return {
                    data: {
                        id: 'e' + r.id,
                        source: 'n' + r.from_entity_id,
                        target: 'n' + r.to_entity_id,
                        label: r.type,
                        weight: 0.5 + w * 2.5,
                        tip: r.type + (r.source_count > 1 ? ' (' + r.source_count + ' Belege)' : '') + (r.document_title ? ' · ' + r.document_title : ''),
                    },
                };
            }),
        ];

        cy = cytoscape({
            container,
            elements,
            wheelSensitivity: 0.25,
            style: [
                {
                    selector: 'node',
                    style: {
                        'background-color': 'data(color)',
                        'label': 'data(label)',
                        'color': '#1e293b',
                        'font-size': 11,
                        'font-family': 'inherit',
                        'text-valign': 'bottom',
                        'text-margin-y': 6,
                        'text-outline-color': '#fff',
                        'text-outline-width': 3,
                        'width': 'data(size)',
                        'height': 'data(size)',
                        'border-width': 0,
                        'transition-property': 'background-color, border-width, border-color',
                        'transition-duration': '0.15s',
                    },
                },
                {
                    selector: 'node:hover',
                    style: {
                        'border-width': 3,
                        'border-color': '#fff',
                        'overlay-color': 'data(color)',
                        'overlay-opacity': 0.18,
                        'overlay-padding': 6,
                    },
                },
                {
                    selector: 'node.selected',
                    style: { 'border-width': 4, 'border-color': '#1e293b' },
                },
                {
                    selector: 'node.faded',
                    style: { 'opacity': 0.18 },
                },
                {
                    selector: 'edge',
                    style: {
                        'curve-style': 'bezier',
                        'target-arrow-shape': 'triangle',
                        'target-arrow-color': '#cbd5e1',
                        'line-color': '#e2e8f0',
                        'width': 'data(weight)',
                        'arrow-scale': 0.85,
                        'opacity': 0.7,
                    },
                },
                {
                    selector: 'edge.highlight',
                    style: {
                        'line-color': '#004c9b',
                        'target-arrow-color': '#004c9b',
                        'opacity': 1,
                        'z-index': 99,
                    },
                },
                {
                    selector: 'edge.faded',
                    style: { 'opacity': 0.07 },
                },
            ],
            // Performance: animate erst beim Final-Step (kein Frame-by-Frame),
            // 'default' Quality reicht bei dieser Edgedichte und ist ~3x schneller als 'proof'.
            layout: {
                name: 'fcose',
                quality: elements.length > 800 ? 'draft' : 'default',
                animate: 'end',
                animationDuration: 500,
                animationEasing: 'ease-out',
                randomize: true,
                fit: true,
                padding: 50,
                nodeRepulsion: 7000,
                idealEdgeLength: 120,
                edgeElasticity: 0.4,
                gravity: 0.25,
                nestingFactor: 0.1,
                numIter: elements.length > 800 ? 800 : 1500,
                tile: true,
                packComponents: true,
                uniformNodeDimensions: true,
                samplingType: true,
                sampleSize: 30,
            },
        });

        // Tooltip (vanilla, kein zusaetzliches Lib)
        const tip = document.createElement('div');
        tip.style.cssText = 'position:fixed;pointer-events:none;background:#0f172a;color:#fff;font-size:11px;padding:5px 8px;border-radius:5px;opacity:0;transition:opacity 0.12s;z-index:9999;white-space:nowrap;max-width:300px;';
        document.body.appendChild(tip);
        cy.on('mouseover', 'node, edge', (evt) => {
            tip.textContent = evt.target.data('tip') || '';
            tip.style.opacity = '0.95';
            const e = evt.originalEvent;
            tip.style.left = (e.clientX + 14) + 'px';
            tip.style.top = (e.clientY + 14) + 'px';
        });
        cy.on('mousemove', (evt) => {
            if (tip.style.opacity !== '0') {
                const e = evt.originalEvent;
                tip.style.left = (e.clientX + 14) + 'px';
                tip.style.top = (e.clientY + 14) + 'px';
            }
        });
        cy.on('mouseout', 'node, edge', () => { tip.style.opacity = '0'; });

        // Klick: Details + Nachbarschaft hervorheben
        cy.on('tap', 'node', (evt) => {
            const node = evt.target;
            cy.elements().removeClass('selected highlight faded');
            const neighborhood = node.closedNeighborhood();
            cy.elements().not(neighborhood).addClass('faded');
            neighborhood.edges().addClass('highlight');
            node.addClass('selected');
            showEntityDetails(node.data('rawId'));
        });
        cy.on('tap', (evt) => {
            if (evt.target === cy) {
                cy.elements().removeClass('selected highlight faded');
                document.getElementById('kgg-side').classList.remove('open');
            }
        });
    }

    function showEntityDetails(entityId) {
        const entity = currentData.entities.find(e => e.id == entityId);
        if (!entity) return;

        const side = document.getElementById('kgg-side');
        const title = document.getElementById('kgg-side-title');
        const sub = document.getElementById('kgg-side-sub');
        const docsEl = document.getElementById('kgg-side-docs');

        title.textContent = entity.name;
        sub.innerHTML = `<span class="kgg-legend-item"><span class="kgg-legend-dot" style="background:${typeColors[entity.type]||'#94a3b8'}"></span>${entity.type}</span> · ${entity.mention_count||1}x erwähnt · ${entity.doc_count||0} Dokument(e)`;

        const relatedDocs = (currentData.documents || []).filter(d => d.entity_ids.includes(parseInt(entityId)));
        if (relatedDocs.length === 0) {
            docsEl.innerHTML = '<p style="color:var(--color-text-tertiary,#94a3b8);font-size: var(--d-fs-sm);">Keine Dokumente gefunden.</p>';
        } else {
            docsEl.innerHTML = relatedDocs.map(d => `
                <div class="kgg-doc-item" onclick="window.open('/wissen/${d.id}', '_blank')">
                    <div class="kgg-doc-title">${esc(d.title)}</div>
                    <div class="kgg-doc-meta">${d.category || ''}${d.category && d.source_type ? ' · ' : ''}${d.source_type || ''}</div>
                </div>
            `).join('');
        }

        side.classList.add('open');
    }

    function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    // Toolbar: Fit + Reset
    window.kggFit = function() { if (cy) cy.fit(undefined, 50); };
    window.kggReset = function() { if (cy) { cy.elements().removeClass('selected highlight faded'); cy.fit(undefined, 50); document.getElementById('kgg-side').classList.remove('open'); } };

    loadGraph();
})();
</script>
