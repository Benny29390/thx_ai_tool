<?php
$documentId = (int) ($documentId ?? 0);
?>

<style>
.kg-container { display: flex; flex-direction: column; height: calc(100vh - 60px); overflow: hidden; }
.kg-header { display:flex; align-items:center; justify-content:space-between; padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--color-border,#e2e8f0); gap: 1rem; flex-shrink: 0; }
.kg-header-left { display:flex; align-items:center; gap:0.75rem; min-width: 0; }
.kg-header-left a { color: var(--color-text-secondary,#64748b); text-decoration: none; display: flex; }
.kg-header h1 { margin:0; font-size: var(--d-fs-lg); font-weight:600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kg-legend { display:flex; gap:0.5rem; font-size: var(--d-fs-xs); }
.kg-legend-item { display:flex; align-items:center; gap:0.3rem; padding: 0.2rem 0.5rem; border-radius: 4px; }
.kg-legend-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
#kg-graph { flex: 1; min-height: 500px; background: var(--color-bg-primary,#fff); }
.kg-loading { display:flex; align-items:center; justify-content:center; flex:1; color: var(--color-text-secondary,#64748b); }
</style>

<div class="kg-container">
    <div class="thx-page-header kg-header">
        <div class="kg-header-left" style="display:flex; align-items:center; gap:0.75rem;">
            <a href="/wissen/<?= $documentId ?>" style="color:var(--color-text-secondary,#64748b); text-decoration:none; display:flex;"><span class="material-symbols-rounded">arrow_back</span></a>
            <div>
                <h1 class="thx-page-title" id="kg-title">Graph</h1>
                <div class="thx-page-subtitle">Entities und Relationen dieses Wissens-Eintrags</div>
            </div>
        </div>
        <div class="thx-page-actions kg-legend">
            <div class="kg-legend-item"><span class="kg-legend-dot" style="background:#ef4444"></span>PER</div>
            <div class="kg-legend-item"><span class="kg-legend-dot" style="background:#3b82f6"></span>ORG</div>
            <div class="kg-legend-item"><span class="kg-legend-dot" style="background:#10b981"></span>LOC</div>
            <div class="kg-legend-item"><span class="kg-legend-dot" style="background:#f59e0b"></span>PRODUCT</div>
            <div class="kg-legend-item"><span class="kg-legend-dot" style="background:#004c9b"></span>CONCEPT</div>
            <div class="kg-legend-item"><span class="kg-legend-dot" style="background:#ec4899"></span>EVENT</div>
            <div class="kg-legend-item"><span class="kg-legend-dot" style="background:#94a3b8"></span>MISC</div>
        </div>
    </div>
    <div id="kg-graph"><div class="kg-loading">Lade Graph...</div></div>
</div>

<script src="https://unpkg.com/cytoscape@3.30.2/dist/cytoscape.min.js"></script>
<script src="https://unpkg.com/layout-base@2.0.1/layout-base.js"></script>
<script src="https://unpkg.com/cose-base@2.2.0/cose-base.js"></script>
<script src="https://unpkg.com/cytoscape-fcose@2.2.0/cytoscape-fcose.js"></script>
<script>
(function() {
    const documentId = <?= $documentId ?>;
    const csrfToken = '<?= \Core\Session::getCsrfToken() ?>';

    const typeColors = {
        PER: '#ef4444', ORG: '#3b82f6', LOC: '#10b981',
        PRODUCT: '#f59e0b', CONCEPT: '#004c9b', EVENT: '#ec4899', MISC: '#94a3b8'
    };

    async function load() {
        const resp = await fetch('/api/v1/knowledge/documents/' + documentId + '/graph', {
            headers: { 'X-CSRF-Token': csrfToken }
        });
        const r = await resp.json();
        if (!r.success) {
            document.getElementById('kg-graph').innerHTML = '<div class="kg-loading">Fehler: ' + (r.message || '') + '</div>';
            return;
        }

        document.getElementById('kg-title').textContent = 'Graph: ' + r.data.document.title;

        const entities = r.data.entities || [];
        const relations = r.data.relations || [];

        if (entities.length === 0) {
            document.getElementById('kg-graph').innerHTML = '<div class="kg-loading">Keine Entities vorhanden.</div>';
            return;
        }

        const maxMentions = Math.max(1, ...entities.map(e => e.mention_count || 1));
        const nodeSize = m => Math.round(20 + (Math.log(1 + (m || 1)) / Math.log(1 + maxMentions)) * 40);

        const elements = [
            ...entities.map(e => ({
                data: {
                    id: 'n' + e.id,
                    label: e.name,
                    color: typeColors[e.type] || '#94a3b8',
                    size: nodeSize(e.mention_count || 1),
                    tip: e.type + ' — in ' + e.chunk_count + ' Chunks',
                },
            })),
            ...relations.map(r => ({
                data: {
                    id: 'e' + r.from_id + '_' + r.to_id + '_' + r.type,
                    source: 'n' + r.from_id,
                    target: 'n' + r.to_id,
                    label: r.type,
                    weight: Math.max(0.5, (r.weight || 0.5) * 2.5),
                    tip: r.type + ' (Gewicht: ' + (r.weight || 0).toFixed(2) + ')',
                },
            })),
        ];

        const container = document.getElementById('kg-graph');
        container.innerHTML = '';

        const cy = cytoscape({
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
                        'font-size': 12,
                        'font-family': 'inherit',
                        'text-valign': 'bottom',
                        'text-margin-y': 6,
                        'text-outline-color': '#fff',
                        'text-outline-width': 3,
                        'width': 'data(size)',
                        'height': 'data(size)',
                        'border-width': 0,
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
                    selector: 'edge',
                    style: {
                        'curve-style': 'bezier',
                        'target-arrow-shape': 'triangle',
                        'target-arrow-color': '#cbd5e1',
                        'line-color': '#e2e8f0',
                        'width': 'data(weight)',
                        'arrow-scale': 0.85,
                        'opacity': 0.7,
                        'label': 'data(label)',
                        'font-size': 9,
                        'color': '#64748b',
                        'text-rotation': 'autorotate',
                        'text-background-color': '#fff',
                        'text-background-opacity': 0.85,
                        'text-background-padding': 2,
                    },
                },
            ],
            layout: {
                name: 'fcose',
                quality: 'proof',
                animate: true,
                animationDuration: 600,
                animationEasing: 'ease-out',
                randomize: true,
                fit: true,
                padding: 60,
                nodeRepulsion: 6500,
                idealEdgeLength: 130,
                edgeElasticity: 0.45,
                gravity: 0.25,
                numIter: 2500,
            },
        });

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
    }

    load();
})();
</script>
