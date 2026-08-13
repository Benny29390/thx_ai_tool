<div class="page-header">
    <h1>Regeltypen</h1>
    <button class="btn btn-primary" onclick="openTypeModal()">
        <span class="btn-icon">+</span> Neuer Typ
    </button>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Farbe</th>
                    <th>Icon</th>
                    <th>Regeln</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="types-tbody">
                <?php if (empty($types)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">Keine Regeltypen vorhanden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($types as $type): ?>
                        <tr data-id="<?= $type['id'] ?>">
                            <td class="drag-handle" title="Ziehen zum Sortieren">&#9776;</td>
                            <td>
                                <strong><?= htmlspecialchars($type['name']) ?></strong>
                                <?php if ($type['is_system']): ?>
                                    <span class="badge badge-info" style="font-size: var(--d-fs-xs);">System</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($type['slug']) ?></code></td>
                            <td>
                                <span class="color-preview" style="background: <?= htmlspecialchars($type['color']) ?>;"></span>
                                <?= htmlspecialchars($type['color']) ?>
                            </td>
                            <td>
                                <span class="material-symbols-rounded" style="color: <?= htmlspecialchars($type['color']) ?>;">
                                    <?= htmlspecialchars($type['icon']) ?>
                                </span>
                            </td>
                            <td><?= $type['rules_count'] ?? 0 ?></td>
                            <td>
                                <?php if ($type['is_active']): ?>
                                    <span class="badge badge-success">Aktiv</span>
                                <?php else: ?>
                                    <span class="badge badge-error">Inaktiv</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-small btn-secondary"
                                        onclick="editType(<?= $type['id'] ?>)">
                                    Bearbeiten
                                </button>
                                <?php if (!$type['is_system']): ?>
                                <button class="btn btn-small btn-danger btn-icon"
                                        onclick="deleteType(<?= $type['id'] ?>)" title="Löschen">
                                    <span class="material-symbols-rounded">delete</span>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Typ bearbeiten -->
<div class="modal" id="type-modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 id="type-modal-title">Neuer Regeltyp</h2>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="type-form" data-ajax data-endpoint="/admin/rule-types" data-method="POST"
              data-on-success="typeSaved">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" id="type-id" name="id" value="">

            <div class="form-group">
                <label for="type-name">Name *</label>
                <input type="text" id="type-name" name="name" required
                       placeholder="z.B. SEO-Regeln">
            </div>

            <div class="form-group">
                <label for="type-slug">Slug *</label>
                <input type="text" id="type-slug" name="slug" required
                       pattern="[a-z0-9-]+" title="Nur Kleinbuchstaben, Zahlen und Bindestriche"
                       placeholder="z.B. seo">
                <small>Wird intern verwendet</small>
            </div>

            <div class="form-group">
                <label for="type-description">Beschreibung</label>
                <input type="text" id="type-description" name="description"
                       placeholder="Kurze Beschreibung des Typs">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="type-color">Farbe</label>
                    <div class="color-input-wrapper">
                        <input type="color" id="type-color" name="color" value="#6b7280">
                        <input type="text" id="type-color-text" value="#6b7280"
                               pattern="^#[0-9a-fA-F]{6}$" placeholder="#6b7280">
                    </div>
                </div>
                <div class="form-group">
                    <label for="type-icon">Icon (Material Symbols)</label>
                    <div class="icon-input-wrapper">
                        <span class="material-symbols-rounded icon-preview" id="type-icon-preview">rule</span>
                        <input type="text" id="type-icon" name="icon" value="rule"
                               placeholder="rule">
                    </div>
                    <small><a href="https://fonts.google.com/icons" target="_blank">Icons durchsuchen</a></small>
                </div>
            </div>

            <div class="form-group" id="type-status-group" style="display: none;">
                <label for="type-status">Status</label>
                <select id="type-status" name="is_active">
                    <option value="1">Aktiv</option>
                    <option value="0">Inaktiv</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Abbrechen</button>
                <button type="submit" class="btn btn-primary">Speichern</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal.open {
    display: flex;
}
.modal-content {
    background: white;
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-large {
    max-width: 600px;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-lg);
    border-bottom: 1px solid var(--color-gray-200);
}
.modal-header h2 {
    margin: 0;
    font-size: var(--d-fs-xl);
}
.modal-close {
    background: none;
    border: none;
    font-size: var(--d-fs-2xl);
    cursor: pointer;
    color: var(--color-gray-500);
}
.modal-content form {
    padding: var(--spacing-lg);
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-md);
}
.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: var(--d-fs-xs);
    font-weight: 500;
}
.badge-success {
    background: rgba(34, 197, 94, 0.1);
    color: #166534;
}
.badge-error {
    background: rgba(239, 68, 68, 0.1);
    color: #991b1b;
}
.badge-info {
    background: rgba(59, 130, 246, 0.1);
    color: #1d4ed8;
}
.drag-handle {
    cursor: grab;
    color: var(--color-gray-400);
    font-size: var(--d-fs-xl);
    text-align: center;
}
.drag-handle:hover {
    color: var(--color-gray-600);
}
tr.dragging {
    opacity: 0.5;
    background: var(--color-gray-100);
}
.color-preview {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 3px;
    vertical-align: middle;
    margin-right: 4px;
    border: 1px solid rgba(0,0,0,0.1);
}
.color-input-wrapper {
    display: flex;
    gap: var(--spacing-sm);
    align-items: center;
}
.color-input-wrapper input[type="color"] {
    width: 50px;
    height: 38px;
    padding: 2px;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    cursor: pointer;
}
.color-input-wrapper input[type="text"] {
    flex: 1;
}
.icon-input-wrapper {
    display: flex;
    gap: var(--spacing-sm);
    align-items: center;
}
.icon-preview {
    font-size: 24px;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-gray-100);
    border-radius: var(--radius-sm);
}
.icon-input-wrapper input {
    flex: 1;
}
</style>

<script>
function typeSaved() {
    App.closeModal('type-modal');
    location.reload();
}

async function editType(id) {
    try {
        const response = await App.get('/admin/rule-types/' + id);
        const type = response.data;

        document.getElementById('type-modal-title').textContent = 'Regeltyp bearbeiten';
        document.getElementById('type-id').value = type.id;
        document.getElementById('type-name').value = type.name;
        document.getElementById('type-slug').value = type.slug;
        document.getElementById('type-description').value = type.description || '';
        document.getElementById('type-color').value = type.color || '#6b7280';
        document.getElementById('type-color-text').value = type.color || '#6b7280';
        document.getElementById('type-icon').value = type.icon || 'rule';
        document.getElementById('type-icon-preview').textContent = type.icon || 'rule';
        document.getElementById('type-status').value = type.is_active ? '1' : '0';
        document.getElementById('type-status-group').style.display = 'block';

        // Slug bei System-Typen nicht editierbar
        document.getElementById('type-slug').readOnly = type.is_system == 1;

        const form = document.getElementById('type-form');
        form.setAttribute('data-endpoint', '/admin/rule-types/' + id);
        form.setAttribute('data-method', 'PUT');

        App.openModal('type-modal');
    } catch (error) {
        App.showNotification('Fehler beim Laden: ' + error.message, 'error');
    }
}

function openTypeModal() {
    document.getElementById('type-modal-title').textContent = 'Neuer Regeltyp';
    document.getElementById('type-id').value = '';
    document.getElementById('type-name').value = '';
    document.getElementById('type-slug').value = '';
    document.getElementById('type-slug').readOnly = false;
    document.getElementById('type-description').value = '';
    document.getElementById('type-color').value = '#6b7280';
    document.getElementById('type-color-text').value = '#6b7280';
    document.getElementById('type-icon').value = 'rule';
    document.getElementById('type-icon-preview').textContent = 'rule';
    document.getElementById('type-status-group').style.display = 'none';

    const form = document.getElementById('type-form');
    form.setAttribute('data-endpoint', '/admin/rule-types');
    form.setAttribute('data-method', 'POST');

    App.openModal('type-modal');
}

async function deleteType(id) {
    if (!confirm('Regeltyp wirklich löschen?')) return;

    try {
        await App.delete('/admin/rule-types/' + id);
        document.querySelector(`tr[data-id="${id}"]`).remove();
        App.showNotification('Regeltyp gelöscht', 'success');
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

// Slug aus Name generieren
document.getElementById('type-name')?.addEventListener('input', function() {
    const slugField = document.getElementById('type-slug');
    if (!slugField.readOnly && (!slugField.value || slugField.dataset.autoGenerated === 'true')) {
        const slug = this.value.toLowerCase()
            .replace(/[äöüß]/g, m => ({ä:'ae',ö:'oe',ü:'ue',ß:'ss'}[m]))
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
        slugField.value = slug;
        slugField.dataset.autoGenerated = 'true';
    }
});

document.getElementById('type-slug')?.addEventListener('input', function() {
    this.dataset.autoGenerated = 'false';
});

// Farbe synchronisieren
document.getElementById('type-color')?.addEventListener('input', function() {
    document.getElementById('type-color-text').value = this.value;
});
document.getElementById('type-color-text')?.addEventListener('input', function() {
    if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
        document.getElementById('type-color').value = this.value;
    }
});

// Icon-Preview
document.getElementById('type-icon')?.addEventListener('input', function() {
    document.getElementById('type-icon-preview').textContent = this.value || 'rule';
});

// Drag & Drop Sortierung
const tbody = document.getElementById('types-tbody');
let draggedRow = null;

tbody?.addEventListener('dragstart', function(e) {
    if (e.target.tagName === 'TR') {
        draggedRow = e.target;
        e.target.classList.add('dragging');
    }
});

tbody?.addEventListener('dragend', function(e) {
    if (e.target.tagName === 'TR') {
        e.target.classList.remove('dragging');
        saveSortOrder();
    }
});

tbody?.addEventListener('dragover', function(e) {
    e.preventDefault();
    const afterElement = getDragAfterElement(tbody, e.clientY);
    if (draggedRow) {
        if (afterElement == null) {
            tbody.appendChild(draggedRow);
        } else {
            tbody.insertBefore(draggedRow, afterElement);
        }
    }
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('tr:not(.dragging)')];
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

async function saveSortOrder() {
    const rows = tbody.querySelectorAll('tr[data-id]');
    const order = Array.from(rows).map((row, index) => ({
        id: parseInt(row.dataset.id),
        sort_order: index + 1
    }));

    try {
        await App.post('/admin/rule-types/sort', { order: order });
    } catch (error) {
        console.error('Sort error:', error);
    }
}

// Drag-Attribute setzen
document.querySelectorAll('#types-tbody tr[data-id]').forEach(row => {
    row.draggable = true;
});
</script>
