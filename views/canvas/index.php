<?php
$db = \Core\Database::getInstance();
$userId = \Core\Auth::id();
$customerId = \Core\Auth::customerId();
$isAdmin = \Core\Auth::isAdmin();
$customers = $isAdmin ? $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name") : [];
?>

<style>
.cv-container { /* max-width/padding entfernt — .main-content liefert die Gutter konsistent */ }
.cv-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.25rem;
}
.cv-card {
    background: var(--color-bg-primary, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: box-shadow 0.2s, border-color 0.2s;
}
.cv-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-color: var(--color-primary, #004c9b);
}
.cv-card-title {
    font-size: var(--d-fs-lg);
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.cv-card-desc {
    font-size: var(--d-fs-sm);
    color: var(--color-text-secondary, #64748b);
    margin-bottom: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.cv-card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: var(--d-fs-sm);
    color: var(--color-text-tertiary, #94a3b8);
}
.cv-card-badges {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.cv-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-size: var(--d-fs-xs);
    font-weight: 500;
}
.cv-badge-active { background: #ecfdf5; color: #059669; }
.cv-badge-completed { background: #eff6ff; color: #2563eb; }
.cv-badge-archived { background: #f1f5f9; color: #64748b; }
.cv-progress-mini {
    width: 60px;
    height: 6px;
    background: var(--color-bg-tertiary, #f1f5f9);
    border-radius: 3px;
    overflow: hidden;
}
.cv-progress-mini-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s;
}
.cv-card-count {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.cv-card-count .material-symbols-rounded { font-size: 14px; }
.cv-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--color-text-secondary, #64748b);
}
.cv-empty .material-symbols-rounded {
    font-size: 48px;
    margin-bottom: 1rem;
    opacity: 0.4;
}
.cv-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 1px solid var(--color-border, #e2e8f0);
    margin-bottom: 1.5rem;
}
.cv-tab {
    padding: 0.6rem 1rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    font-size: var(--d-fs-sm);
    color: var(--color-text-secondary, #64748b);
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.cv-tab.active {
    color: var(--color-primary, #004c9b);
    border-bottom-color: var(--color-primary, #004c9b);
    font-weight: 600;
}
.cv-tab-count {
    font-size: var(--d-fs-xs);
    padding: 1px 7px;
    border-radius: 10px;
    background: #e5e7eb;
    color: #4b5563;
    font-weight: 600;
}
.cv-tab.active .cv-tab-count {
    background: #e6f0fa;
    color: #003a78;
}

.cv-card {
    position: relative;
}
.cv-card-menu-btn {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: none;
    border: 1px solid transparent;
    border-radius: 6px;
    padding: 4px 6px;
    cursor: pointer;
    color: var(--color-text-tertiary, #94a3b8);
    opacity: 0;
    transition: opacity 0.15s, background 0.15s;
    z-index: 2;
}
.cv-card:hover .cv-card-menu-btn { opacity: 1; }
.cv-card-menu-btn:hover {
    background: #f3f4f6;
    border-color: var(--color-border, #e2e8f0);
    color: var(--color-text-primary, #1e293b);
}
.cv-card-menu-btn .material-symbols-rounded { font-size: 18px; display: block; }

.cv-menu-popup {
    position: fixed;
    background: white;
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 8px;
    padding: 0.3rem 0;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    z-index: 1000;
    min-width: 180px;
    display: none;
}
.cv-menu-popup.active { display: block; }
.cv-menu-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.85rem;
    cursor: pointer;
    font-size: var(--d-fs-sm);
    color: var(--color-text-primary, #1e293b);
    transition: background 0.1s;
    background: none;
    border: none;
    width: 100%;
    text-align: left;
    font-family: inherit;
}
.cv-menu-item:hover { background: #f8fafc; }
.cv-menu-item.danger { color: #dc2626; }
.cv-menu-item.danger:hover { background: #fef2f2; }
.cv-menu-item .material-symbols-rounded { font-size: 16px; }
.cv-menu-divider {
    height: 1px;
    background: var(--color-border, #e2e8f0);
    margin: 0.25rem 0;
}
/* Modal */
.cv-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
.cv-modal-overlay.active { display: flex; }
.cv-modal {
    background: var(--color-bg-primary, #fff);
    border-radius: 12px;
    padding: 2rem;
    width: 90%;
    max-width: 480px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.cv-modal h2 {
    margin: 0 0 1.5rem;
    font-size: var(--d-fs-lg);
}
.cv-modal .form-group {
    margin-bottom: 1rem;
}
.cv-modal label {
    display: block;
    font-size: var(--d-fs-sm);
    font-weight: 500;
    margin-bottom: 0.375rem;
}
.cv-modal input, .cv-modal textarea, .cv-modal select {
    width: 100%;
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 8px;
    font-size: var(--d-fs-sm);
    background: var(--color-bg-primary, #fff);
    color: var(--color-text-primary, #1e293b);
    box-sizing: border-box;
}
.cv-modal textarea { min-height: 80px; resize: vertical; }
.cv-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.5rem;
}
</style>

<div class="cv-container">
    <div class="thx-page-header">
        <div>
            <h1 class="thx-page-title">KI Kompass</h1>
            <div class="thx-page-subtitle">Canvas-Briefings fuer KI-Sparring</div>
        </div>
        <div class="thx-page-actions">
            <button class="thx-btn thx-btn-primary" onclick="openCreateModal()">
                <span class="material-symbols-rounded" style="font-size:18px;">add</span>
                Neues Canvas
            </button>
        </div>
    </div>

    <div class="thx-tabs">
        <button class="thx-tab is-active" data-tab="active" onclick="switchTab('active')">
            <span class="material-symbols-rounded" style="font-size:18px;">view_kanban</span>
            Aktiv
            <span class="thx-chip" id="count-active">0</span>
        </button>
        <button class="thx-tab" data-tab="archived" onclick="switchTab('archived')">
            <span class="material-symbols-rounded" style="font-size:18px;">archive</span>
            Archiviert
            <span class="thx-chip" id="count-archived">0</span>
        </button>
    </div>

    <div class="cv-grid" id="canvas-list">
        <div class="cv-empty" id="canvas-empty" style="display:none;">
            <span class="material-symbols-rounded">view_kanban</span>
            <p id="cv-empty-text">Noch kein Projekt vorhanden.<br>Erstelle dein erstes KI-Kompass-Projekt.</p>
        </div>
    </div>
</div>

<!-- Card-Menue Popup -->
<div class="thx-contextmenu" id="cv-menu-popup" style="display:none;"></div>

<!-- Create Modal -->
<div class="thx-modal-backdrop" id="create-modal" style="display:none;">
    <div class="thx-modal" style="width:520px;max-width:92vw;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Neues Canvas erstellen</h3>
            <button class="thx-modal-close" onclick="closeCreateModal()">&times;</button>
        </div>
        <div class="thx-modal-body">
            <div class="thx-form-field">
                <label>Titel *</label>
                <input class="thx-input" type="text" id="create-title" placeholder="z.B. KI-Chatbot fuer Kundenservice" autofocus>
            </div>
            <div class="thx-form-field">
                <label>Beschreibung</label>
                <textarea class="thx-textarea" id="create-description" placeholder="Worum geht es in diesem Projekt?"></textarea>
            </div>
            <?php if ($isAdmin && !empty($customers)): ?>
            <div class="thx-form-field">
                <label>Kunde *</label>
                <select class="thx-select" id="create-customer">
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="thx-modal-footer">
            <button class="thx-btn thx-btn-secondary" onclick="closeCreateModal()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="createCanvas()" id="create-btn">Erstellen</button>
        </div>
    </div>
</div>

<script>
(function() {
    const csrfToken = '<?= \Core\Session::getCsrfToken() ?>';
    const customerId = <?= $customerId ?: 'null' ?>;

    async function apiFetch(url, options = {}) {
        const headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
        const resp = await fetch('/api/v1' + url, { ...options, headers });
        return resp.json();
    }

    let allItems = [];
    let activeTab = 'active';

    async function loadCanvases() {
        const result = await apiFetch('/canvas/projects?include_archived=1');
        allItems = result.data?.items || [];
        renderList();
    }

    function renderList() {
        const list = document.getElementById('canvas-list');
        const empty = document.getElementById('canvas-empty');
        const emptyText = document.getElementById('cv-empty-text');

        // Zaehler
        const activeCount = allItems.filter(p => p.status !== 'archived').length;
        const archivedCount = allItems.filter(p => p.status === 'archived').length;
        document.getElementById('count-active').textContent = activeCount;
        document.getElementById('count-archived').textContent = archivedCount;

        // Filtern
        const items = activeTab === 'archived'
            ? allItems.filter(p => p.status === 'archived')
            : allItems.filter(p => p.status !== 'archived');

        list.querySelectorAll('.cv-card').forEach(el => el.remove());

        if (items.length === 0) {
            empty.style.display = 'block';
            emptyText.innerHTML = activeTab === 'archived'
                ? 'Keine archivierten Projekte.'
                : 'Noch kein Projekt vorhanden.<br>Erstelle dein erstes KI-Kompass-Projekt.';
            list.appendChild(empty);
            return;
        }

        empty.style.display = 'none';
        items.forEach(p => {
            const readiness = parseInt(p.briefing_readiness) || 0;
            let progressColor = '#ef4444';
            if (readiness >= 80) progressColor = '#22c55e';
            else if (readiness >= 40) progressColor = '#f59e0b';

            const statusClass = 'cv-badge-' + p.status;
            const statusLabel = { active: 'Aktiv', completed: 'Abgeschlossen', archived: 'Archiviert' }[p.status] || p.status;
            const date = new Date(p.updated_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });

            const card = document.createElement('div');
            card.className = 'thx-card';
            card.style.cursor = 'pointer';
            card.style.position = 'relative';
            card.onclick = (e) => {
                if (e.target.closest('.cv-card-menu-btn')) return;
                window.location.href = '/canvas/' + p.id;
            };
            card.innerHTML = `
                <button class="thx-icon-btn cv-card-menu-btn" onclick="openMenu(event, ${p.id}, '${p.status}')" title="Aktionen">
                    <span class="material-symbols-rounded">more_vert</span>
                </button>
                <h3 class="thx-card-title">${escHtml(p.title)}</h3>
                <p class="thx-card-sub" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:var(--space-3);">${escHtml(p.description || 'Keine Beschreibung')}</p>
                <div class="cv-card-meta" style="display:flex;justify-content:space-between;align-items:center;font-size:var(--d-fs-sm);color:var(--slate-500);">
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span class="thx-chip ${statusClass}">${statusLabel}</span>
                        <div class="cv-progress-mini" title="${readiness}% Briefing-Reife">
                            <div class="cv-progress-mini-fill" style="width:${readiness}%;background:${progressColor}"></div>
                        </div>
                        <span style="font-size:var(--d-fs-xs);">${readiness}%</span>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span style="display:inline-flex;align-items:center;gap:2px;"><span class="material-symbols-rounded" style="font-size:14px;">style</span>${p.card_count || 0}</span>
                        <span>${date}</span>
                    </div>
                </div>
            `;
            list.appendChild(card);
        });
    }

    window.switchTab = function(tab) {
        activeTab = tab;
        document.querySelectorAll('.thx-tab').forEach(t => t.classList.toggle('is-active', t.dataset.tab === tab));
        renderList();
    };

    window.openMenu = function(e, projectId, status) {
        e.stopPropagation();
        const popup = document.getElementById('cv-menu-popup');
        const rect = e.currentTarget.getBoundingClientRect();

        const isArchived = status === 'archived';
        popup.innerHTML = isArchived
            ? `
                <button class="thx-contextmenu-item" onclick="unarchiveCanvas(${projectId})">
                    <span class="material-symbols-rounded">unarchive</span> Wiederherstellen
                </button>
                <div class="thx-contextmenu-divider"></div>
                <button class="thx-contextmenu-item is-danger" onclick="deleteCanvas(${projectId})">
                    <span class="material-symbols-rounded">delete</span> Endgueltig loeschen
                </button>
              `
            : `
                <button class="thx-contextmenu-item" onclick="window.location.href='/canvas/${projectId}'">
                    <span class="material-symbols-rounded">edit</span> Oeffnen
                </button>
                <button class="thx-contextmenu-item" onclick="archiveCanvas(${projectId})">
                    <span class="material-symbols-rounded">archive</span> Archivieren
                </button>
                <div class="thx-contextmenu-divider"></div>
                <button class="thx-contextmenu-item is-danger" onclick="deleteCanvas(${projectId})">
                    <span class="material-symbols-rounded">delete</span> Loeschen
                </button>
              `;

        popup.style.left = Math.min(rect.right - 180, window.innerWidth - 200) + 'px';
        popup.style.top = (rect.bottom + 4) + 'px';
        popup.style.display = 'block';

        setTimeout(() => {
            document.addEventListener('click', closeMenuOnce, { once: true });
        }, 0);
    };

    function closeMenuOnce() {
        document.getElementById('cv-menu-popup').style.display = 'none';
    }

    window.archiveCanvas = async function(id) {
        closeMenuOnce();
        const r = await apiFetch('/canvas/projects/' + id, {
            method: 'PUT',
            body: JSON.stringify({ action: 'archive' })
        });
        if (r.success) loadCanvases();
        else alert(r.message || 'Fehler');
    };

    window.unarchiveCanvas = async function(id) {
        closeMenuOnce();
        const r = await apiFetch('/canvas/projects/' + id, {
            method: 'PUT',
            body: JSON.stringify({ action: 'unarchive' })
        });
        if (r.success) loadCanvases();
        else alert(r.message || 'Fehler');
    };

    window.deleteCanvas = async function(id) {
        closeMenuOnce();
        if (!confirm('Canvas endgueltig loeschen? Alle Karten, Chat-Verlauf und Versionen werden entfernt.')) return;
        const r = await apiFetch('/canvas/projects/' + id, { method: 'DELETE' });
        if (r.success) loadCanvases();
        else alert(r.message || 'Fehler');
    };

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    window.openCreateModal = function() {
        document.getElementById('create-modal').classList.add('active');
        document.getElementById('create-title').focus();
    };
    window.closeCreateModal = function() {
        document.getElementById('create-modal').classList.remove('active');
    };

    window.createCanvas = async function() {
        const title = document.getElementById('create-title').value.trim();
        if (!title) { document.getElementById('create-title').focus(); return; }

        const custSelect = document.getElementById('create-customer');
        const cId = custSelect ? parseInt(custSelect.value) : customerId;

        const btn = document.getElementById('create-btn');
        btn.disabled = true;
        btn.textContent = 'Erstelle...';

        const result = await apiFetch('/canvas/projects', {
            method: 'POST',
            body: JSON.stringify({
                title,
                description: document.getElementById('create-description').value.trim(),
                customer_id: cId
            })
        });

        if (result.success && result.data?.id) {
            window.location.href = '/canvas/' + result.data.id;
        } else {
            alert(result.message || 'Fehler beim Erstellen');
            btn.disabled = false;
            btn.textContent = 'Erstellen';
        }
    };

    // Click outside modal to close
    document.getElementById('create-modal').addEventListener('click', function(e) {
        if (e.target === this) closeCreateModal();
    });

    // Enter key in title
    document.getElementById('create-title').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); createCanvas(); }
    });

    loadCanvases();
})();
</script>
