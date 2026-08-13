<style>
.gl-wrap { /* max-width/padding entfernt — .main-content liefert die Gutter konsistent */ }

.gl-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.gl-tab {
    padding: 0.65rem 1.1rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-family: inherit;
    font-size: var(--d-fs-sm);
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.gl-tab.active { color: var(--thoxan-700); border-bottom-color: var(--thoxan-700); font-weight: 600; }
.gl-tab-count {
    font-size: var(--d-fs-xs);
    padding: 1px 7px;
    border-radius: 10px;
    background: #e5e7eb;
    color: #4b5563;
    font-weight: 600;
}
.gl-tab.active .gl-tab-count { background: #e6f0fa; color: var(--thoxan-900); }

.gl-list { display: flex; flex-direction: column; gap: 0.5rem; }

.gl-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.gl-row:hover { border-color: #cbd5e1; }
.gl-row.editing { border-color: var(--thoxan-700); box-shadow: 0 0 0 3px rgba(0, 76, 155,0.12); }
.gl-row[data-active="0"] .gl-row-content { opacity: 0.5; }

.gl-handle {
    color: #cbd5e1;
    cursor: grab;
    padding-top: 4px;
    user-select: none;
}
.gl-handle:active { cursor: grabbing; }

.gl-toggle {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}
.gl-toggle input { opacity: 0; width: 0; height: 0; }
.gl-toggle-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: #cbd5e1;
    border-radius: 20px;
    transition: 0.15s;
}
.gl-toggle-slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background: #fff;
    border-radius: 50%;
    transition: 0.15s;
}
.gl-toggle input:checked + .gl-toggle-slider { background: #10b981; }
.gl-toggle input:checked + .gl-toggle-slider:before { transform: translateX(16px); }

.gl-row-content { flex: 1; min-width: 0; cursor: pointer; }
.gl-row-title { font-weight: 600; font-size: var(--d-fs-sm); color: #1e293b; margin-bottom: 2px; }
.gl-row-text { font-size: var(--d-fs-sm); color: #64748b; line-height: 1.45; white-space: pre-wrap; word-break: break-word; }

.gl-row-actions {
    display: flex;
    gap: 0.2rem;
    opacity: 0;
    transition: opacity 0.15s;
    flex-shrink: 0;
}
.gl-row:hover .gl-row-actions { opacity: 1; }
.gl-action-btn {
    background: none;
    border: 1px solid transparent;
    border-radius: 6px;
    padding: 4px 6px;
    cursor: pointer;
    color: #94a3b8;
    transition: all 0.1s;
}
.gl-action-btn:hover { background: #f1f5f9; color: #1e293b; border-color: #e2e8f0; }
.gl-action-btn.danger:hover { background: var(--rose-50); color: var(--rose-600); border-color: #fecaca; }
.gl-action-btn .material-symbols-rounded { font-size: 16px; display: block; }

.gl-edit-form { width: 100%; }
.gl-edit-form input,
.gl-edit-form textarea,
.gl-edit-form select {
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: var(--d-fs-sm);
    box-sizing: border-box;
    font-family: inherit;
    margin-bottom: 0.5rem;
}
.gl-edit-form textarea { min-height: 80px; resize: vertical; }
.gl-edit-actions { display: flex; gap: 0.4rem; justify-content: flex-end; }

.gl-empty {
    text-align: center;
    padding: 3rem 1.5rem;
    color: #94a3b8;
    background: #fff;
    border: 1px dashed #e2e8f0;
    border-radius: 10px;
}
.gl-empty .material-symbols-rounded { font-size: 40px; opacity: 0.4; margin-bottom: 0.5rem; }

.gl-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(0, 76, 155,0.3);
    border-top-color: var(--thoxan-700);
    border-radius: 50%;
    animation: gl-spin 1s linear infinite;
    vertical-align: middle;
    margin-right: 6px;
}
@keyframes gl-spin { to { transform: rotate(360deg); } }

/* Drawer für Neu-Anlage */
.gl-drawer-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 200;
    justify-content: center;
    align-items: center;
}
.gl-drawer-overlay.active { display: flex; }
.gl-drawer {
    background: #fff;
    border-radius: 14px;
    width: 92%;
    max-width: 560px;
    max-height: 85vh;
    overflow-y: auto;
    padding: 1.75rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.gl-drawer h2 { margin: 0 0 1.25rem; font-size: var(--d-fs-lg); }
.gl-drawer-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; }

.gl-info-banner {
    background: linear-gradient(135deg, #e6f0fa, #f1f7fc);
    border: 1px solid #b3d0ec;
    border-radius: 10px;
    padding: 0.85rem 1rem;
    color: var(--thoxan-900);
    font-size: var(--d-fs-sm);
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
</style>

<div class="gl-wrap">
    <div class="thx-page-header">
        <div>
            <h1 class="thx-page-title">Guidelines</h1>
            <div class="thx-page-subtitle">Kundenübergreifende Verhaltensvorgaben für die KI. Wirken deterministisch im Chat, in erzeugten Texten und im KI Kompass &mdash; je nach Kategorie.</div>
        </div>
        <div class="thx-page-actions">
            <button class="thx-btn thx-btn-primary" onclick="glOpenCreate()">
                <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">add</span>
                Neue Guideline
            </button>
        </div>
    </div>

    <div class="gl-info-banner">
        <span class="material-symbols-rounded" style="font-size:18px;">info</span>
        <span>
            <strong>Tool-Kommunikation</strong> regelt, wie die KI mit dir spricht.
            <strong>Content &amp; Output</strong> wirkt nur in erzeugten Texten / Artefakten.
            <strong>Sprache &amp; Format</strong> gilt überall.
        </span>
    </div>

    <div class="gl-tabs" id="gl-tabs"></div>
    <div class="gl-list" id="gl-list">
        <div class="gl-empty"><span class="gl-spinner"></span>Lade Guidelines…</div>
    </div>
</div>

<!-- Create-Drawer -->
<div class="gl-drawer-overlay" id="gl-drawer">
    <div class="gl-drawer">
        <h2>Neue Guideline</h2>
        <div style="margin-bottom:0.75rem;">
            <label style="font-size: var(--d-fs-sm);color:#64748b;font-weight:500;display:block;margin-bottom:0.3rem;">Kategorie</label>
            <select id="gl-create-category" style="width:100%;padding:0.55rem 0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-size: var(--d-fs-sm);font-family:inherit;">
                <option value="tool_communication">Tool-Kommunikation — wie redet die KI mit dir</option>
                <option value="content_output">Content &amp; Output — Stil erzeugter Texte</option>
                <option value="internal">Sprache &amp; Format — gilt überall</option>
            </select>
        </div>
        <div style="margin-bottom:0.75rem;">
            <label style="font-size: var(--d-fs-sm);color:#64748b;font-weight:500;display:block;margin-bottom:0.3rem;">Titel</label>
            <input type="text" id="gl-create-title" placeholder="z.B. „Immer dutzen"" style="width:100%;padding:0.55rem 0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-size: var(--d-fs-sm);font-family:inherit;">
        </div>
        <div style="margin-bottom:0.75rem;">
            <label style="font-size: var(--d-fs-sm);color:#64748b;font-weight:500;display:block;margin-bottom:0.3rem;">Anweisung an die KI</label>
            <textarea id="gl-create-content" rows="5" placeholder="Klare Anweisung, was die KI tun oder lassen soll. Wird 1:1 in den System-Prompt übernommen." style="width:100%;padding:0.55rem 0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-size: var(--d-fs-sm);font-family:inherit;resize:vertical;"></textarea>
        </div>
        <div class="gl-drawer-actions">
            <button class="thx-btn thx-btn-secondary" onclick="glCloseCreate()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" id="gl-create-btn" onclick="glCreate()">Anlegen</button>
        </div>
    </div>
</div>

<script>
function waitForApp(fn) {
    if (window.App && typeof window.App.get === 'function') { fn(); return; }
    const i = setInterval(() => {
        if (window.App && typeof window.App.get === 'function') { clearInterval(i); fn(); }
    }, 50);
}

let glData = { items: [], categories: {}, counts: {} };
let glActiveTab = 'tool_communication';

async function glLoad() {
    const r = await App.get('/guidelines');
    if (!r.success) {
        document.getElementById('gl-list').innerHTML = '<div class="gl-empty" style="color:var(--rose-600);">Fehler: ' + esc(r.message || '') + '</div>';
        return;
    }
    glData = r.data || { items: [], categories: {}, counts: {} };
    glRenderTabs();
    glRenderList();
}

function glRenderTabs() {
    const el = document.getElementById('gl-tabs');
    const labels = glData.categories || {};
    const counts = glData.counts || {};
    el.innerHTML = Object.entries(labels).map(([key, label]) => `
        <button class="gl-tab ${glActiveTab === key ? 'active' : ''}" onclick="glSwitchTab('${key}')">
            ${esc(label)}
            <span class="gl-tab-count">${counts[key] || 0}</span>
        </button>
    `).join('');
}

function glSwitchTab(key) {
    glActiveTab = key;
    glRenderTabs();
    glRenderList();
}

function glRenderList() {
    const list = document.getElementById('gl-list');
    const items = (glData.items || []).filter(g => g.category === glActiveTab);

    if (items.length === 0) {
        list.innerHTML = `
            <div class="gl-empty">
                <span class="material-symbols-rounded">tips_and_updates</span>
                <p style="margin:0 0 0.75rem;">Noch keine Guidelines in dieser Kategorie.</p>
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="glOpenCreate('${glActiveTab}')">+ Erste anlegen</button>
            </div>`;
        return;
    }

    list.innerHTML = items.map(g => `
        <div class="gl-row" data-id="${g.id}" data-active="${g.is_active}">
            <span class="gl-handle" title="Ziehen zum Sortieren">⋮⋮</span>
            <label class="gl-toggle" title="Aktivieren / Deaktivieren">
                <input type="checkbox" ${g.is_active ? 'checked' : ''} onchange="glToggle(${g.id}, this.checked, this)">
                <span class="gl-toggle-slider"></span>
            </label>
            <div class="gl-row-content" onclick="glStartEdit(${g.id})">
                <div class="gl-row-title">${esc(g.title)}</div>
                <div class="gl-row-text">${esc(g.content)}</div>
            </div>
            <div class="gl-row-actions">
                <button class="gl-action-btn" onclick="glStartEdit(${g.id})" title="Bearbeiten">
                    <span class="material-symbols-rounded">edit</span>
                </button>
                <button class="gl-action-btn danger" onclick="glDelete(${g.id})" title="Löschen">
                    <span class="material-symbols-rounded">delete</span>
                </button>
            </div>
        </div>
    `).join('');

    glEnableDragDrop();
}

window.glStartEdit = function(id) {
    const row = document.querySelector('.gl-row[data-id="' + id + '"]');
    if (!row || row.classList.contains('editing')) return;
    const g = glData.items.find(x => x.id == id);
    if (!g) return;

    const cats = Object.entries(glData.categories || {}).map(
        ([k, v]) => `<option value="${k}" ${k === g.category ? 'selected' : ''}>${esc(v)}</option>`
    ).join('');

    row.classList.add('editing');
    row.innerHTML = `
        <div class="gl-edit-form">
            <select id="gl-edit-cat-${id}">${cats}</select>
            <input type="text" id="gl-edit-title-${id}" value="${esc(g.title).replace(/"/g, '&quot;')}">
            <textarea id="gl-edit-content-${id}" rows="4">${esc(g.content)}</textarea>
            <div class="gl-edit-actions">
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="glCancelEdit()">Abbrechen</button>
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="glSaveEdit(${id})">Speichern</button>
            </div>
        </div>
    `;
};

window.glCancelEdit = function() { glRenderList(); };

window.glSaveEdit = async function(id) {
    const data = {
        category: document.getElementById('gl-edit-cat-' + id).value,
        title: document.getElementById('gl-edit-title-' + id).value.trim(),
        content: document.getElementById('gl-edit-content-' + id).value.trim(),
    };
    if (!data.title || !data.content) {
        App.showNotification('Titel und Inhalt erforderlich', 'error');
        return;
    }
    const r = await App.put('/guidelines/' + id, data);
    if (!r.success) {
        App.showNotification(r.message || 'Fehler', 'error');
        return;
    }
    App.showNotification('Gespeichert', 'success');
    await glLoad();
};

window.glToggle = async function(id, active, input) {
    try {
        await App.request('PATCH', '/guidelines/' + id + '/toggle');
        const row = input.closest('.gl-row');
        if (row) row.dataset.active = active ? '1' : '0';
        // counts updaten ohne reload
        const item = glData.items.find(x => x.id == id);
        if (item) item.is_active = active ? 1 : 0;
    } catch (e) {
        input.checked = !active;
        App.showNotification(e.message || 'Fehler', 'error');
    }
};

window.glDelete = async function(id) {
    if (!confirm('Guideline wirklich löschen?')) return;
    try {
        await App.delete('/guidelines/' + id);
        App.showNotification('Gelöscht', 'success');
        await glLoad();
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
};

window.glOpenCreate = function(presetCategory = null) {
    if (presetCategory) document.getElementById('gl-create-category').value = presetCategory;
    else document.getElementById('gl-create-category').value = glActiveTab;
    document.getElementById('gl-create-title').value = '';
    document.getElementById('gl-create-content').value = '';
    document.getElementById('gl-drawer').classList.add('active');
    setTimeout(() => document.getElementById('gl-create-title').focus(), 80);
};

window.glCloseCreate = function() {
    document.getElementById('gl-drawer').classList.remove('active');
};

window.glCreate = async function() {
    const btn = document.getElementById('gl-create-btn');
    btn.disabled = true;
    const data = {
        category: document.getElementById('gl-create-category').value,
        title: document.getElementById('gl-create-title').value.trim(),
        content: document.getElementById('gl-create-content').value.trim(),
    };
    if (!data.title || !data.content) {
        App.showNotification('Titel und Inhalt erforderlich', 'error');
        btn.disabled = false;
        return;
    }
    try {
        const r = await App.post('/guidelines', data);
        if (!r.success) throw new Error(r.message || 'Fehler');
        App.showNotification('Angelegt', 'success');
        glActiveTab = data.category;
        glCloseCreate();
        await glLoad();
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
    btn.disabled = false;
};

// Drag & Drop für Sortierung innerhalb einer Kategorie
let glDragSrc = null;
function glEnableDragDrop() {
    document.querySelectorAll('.gl-row').forEach(row => {
        const handle = row.querySelector('.gl-handle');
        if (!handle) return;
        row.draggable = true;
        row.addEventListener('dragstart', e => {
            glDragSrc = row;
            row.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        });
        row.addEventListener('dragend', () => {
            row.style.opacity = '';
        });
        row.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
        row.addEventListener('drop', async e => {
            e.preventDefault();
            if (!glDragSrc || glDragSrc === row) return;
            const list = document.getElementById('gl-list');
            const rect = row.getBoundingClientRect();
            const before = e.clientY < rect.top + rect.height / 2;
            list.insertBefore(glDragSrc, before ? row : row.nextSibling);

            // Persistieren
            const ids = Array.from(list.querySelectorAll('.gl-row')).map(r => parseInt(r.dataset.id));
            try {
                await App.post('/guidelines/reorder', { ids });
            } catch (err) {
                App.showNotification('Reihenfolge konnte nicht gespeichert werden', 'error');
                glLoad();
            }
            glDragSrc = null;
        });
    });
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

document.getElementById('gl-drawer').addEventListener('click', e => {
    if (e.target.id === 'gl-drawer') glCloseCreate();
});

waitForApp(() => glLoad());
</script>
