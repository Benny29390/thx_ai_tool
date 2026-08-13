<div class="page-header">
    <h1>Regelkategorien</h1>
    <button class="btn btn-primary" onclick="openCategoryModal()">
        <span class="btn-icon">+</span> Neue Kategorie
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
            <tbody id="categories-tbody">
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">Keine Kategorien vorhanden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <tr data-id="<?= $category['id'] ?>">
                            <td class="drag-handle" title="Ziehen zum Sortieren">&#9776;</td>
                            <td><strong><?= htmlspecialchars($category['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($category['slug']) ?></code></td>
                            <td>
                                <span class="color-preview" style="background: <?= htmlspecialchars($category['color']) ?>;"></span>
                                <?= htmlspecialchars($category['color']) ?>
                            </td>
                            <td>
                                <span class="material-symbols-rounded" style="color: <?= htmlspecialchars($category['color']) ?>;">
                                    <?= htmlspecialchars($category['icon']) ?>
                                </span>
                            </td>
                            <td><?= $category['rules_count'] ?? 0 ?></td>
                            <td>
                                <?php if ($category['is_active']): ?>
                                    <span class="badge badge-success">Aktiv</span>
                                <?php else: ?>
                                    <span class="badge badge-error">Inaktiv</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-small btn-secondary"
                                        onclick="editCategory(<?= $category['id'] ?>)">
                                    Bearbeiten
                                </button>
                                <button class="btn btn-small btn-danger btn-icon"
                                        onclick="deleteCategory(<?= $category['id'] ?>)" title="Löschen">
                                    <span class="material-symbols-rounded">delete</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Kategorie bearbeiten -->
<div class="modal" id="category-modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 id="category-modal-title">Neue Kategorie</h2>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="category-form" data-ajax data-endpoint="/admin/rule-categories" data-method="POST"
              data-on-success="categorySaved">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" id="category-id" name="id" value="">

            <div class="form-group">
                <label for="category-name">Name *</label>
                <input type="text" id="category-name" name="name" required
                       placeholder="z.B. Marketing-Texte">
            </div>

            <div class="form-group">
                <label for="category-slug">Slug *</label>
                <input type="text" id="category-slug" name="slug" required
                       pattern="[a-z0-9-]+" title="Nur Kleinbuchstaben, Zahlen und Bindestriche"
                       placeholder="z.B. marketing">
                <small>Wird intern verwendet</small>
            </div>

            <div class="form-group">
                <label for="category-description">Beschreibung</label>
                <input type="text" id="category-description" name="description"
                       placeholder="Kurze Beschreibung der Kategorie">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category-color">Farbe</label>
                    <div class="color-input-wrapper">
                        <input type="color" id="category-color" name="color" value="#6b7280">
                        <input type="text" id="category-color-text" value="#6b7280"
                               pattern="^#[0-9a-fA-F]{6}$" placeholder="#6b7280">
                    </div>
                </div>
                <div class="form-group">
                    <label for="category-icon">Icon (Material Symbols)</label>
                    <div class="icon-input-wrapper">
                        <span class="material-symbols-rounded icon-preview" id="category-icon-preview">folder</span>
                        <input type="text" id="category-icon" name="icon" value="folder"
                               placeholder="folder">
                    </div>
                    <small><a href="https://fonts.google.com/icons" target="_blank">Icons durchsuchen</a></small>
                </div>
            </div>

            <div class="form-group" id="category-status-group" style="display: none;">
                <label for="category-status">Status</label>
                <select id="category-status" name="is_active">
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
function categorySaved() {
    App.closeModal('category-modal');
    location.reload();
}

async function editCategory(id) {
    try {
        const response = await App.get('/admin/rule-categories/' + id);
        const category = response.data;

        document.getElementById('category-modal-title').textContent = 'Kategorie bearbeiten';
        document.getElementById('category-id').value = category.id;
        document.getElementById('category-name').value = category.name;
        document.getElementById('category-slug').value = category.slug;
        document.getElementById('category-description').value = category.description || '';
        document.getElementById('category-color').value = category.color || '#6b7280';
        document.getElementById('category-color-text').value = category.color || '#6b7280';
        document.getElementById('category-icon').value = category.icon || 'folder';
        document.getElementById('category-icon-preview').textContent = category.icon || 'folder';
        document.getElementById('category-status').value = category.is_active ? '1' : '0';
        document.getElementById('category-status-group').style.display = 'block';

        const form = document.getElementById('category-form');
        form.setAttribute('data-endpoint', '/admin/rule-categories/' + id);
        form.setAttribute('data-method', 'PUT');

        App.openModal('category-modal');
    } catch (error) {
        App.showNotification('Fehler beim Laden: ' + error.message, 'error');
    }
}

function openCategoryModal() {
    document.getElementById('category-modal-title').textContent = 'Neue Kategorie';
    document.getElementById('category-id').value = '';
    document.getElementById('category-name').value = '';
    document.getElementById('category-slug').value = '';
    document.getElementById('category-description').value = '';
    document.getElementById('category-color').value = '#6b7280';
    document.getElementById('category-color-text').value = '#6b7280';
    document.getElementById('category-icon').value = 'folder';
    document.getElementById('category-icon-preview').textContent = 'folder';
    document.getElementById('category-status-group').style.display = 'none';

    const form = document.getElementById('category-form');
    form.setAttribute('data-endpoint', '/admin/rule-categories');
    form.setAttribute('data-method', 'POST');

    App.openModal('category-modal');
}

async function deleteCategory(id) {
    if (!confirm('Kategorie wirklich löschen? Zugehörige Regeln werden nicht gelöscht, nur die Kategoriezuweisung entfernt.')) return;

    try {
        await App.delete('/admin/rule-categories/' + id);
        document.querySelector(`tr[data-id="${id}"]`).remove();
        App.showNotification('Kategorie gelöscht', 'success');
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

// Slug aus Name generieren
document.getElementById('category-name')?.addEventListener('input', function() {
    const slugField = document.getElementById('category-slug');
    if (!slugField.value || slugField.dataset.autoGenerated === 'true') {
        const slug = this.value.toLowerCase()
            .replace(/[äöüß]/g, m => ({ä:'ae',ö:'oe',ü:'ue',ß:'ss'}[m]))
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
        slugField.value = slug;
        slugField.dataset.autoGenerated = 'true';
    }
});

document.getElementById('category-slug')?.addEventListener('input', function() {
    this.dataset.autoGenerated = 'false';
});

// Farbe synchronisieren
document.getElementById('category-color')?.addEventListener('input', function() {
    document.getElementById('category-color-text').value = this.value;
});
document.getElementById('category-color-text')?.addEventListener('input', function() {
    if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
        document.getElementById('category-color').value = this.value;
    }
});

// Icon-Preview
document.getElementById('category-icon')?.addEventListener('input', function() {
    document.getElementById('category-icon-preview').textContent = this.value || 'folder';
});

// Drag & Drop Sortierung
const tbody = document.getElementById('categories-tbody');
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
        await App.post('/admin/rule-categories/sort', { order: order });
    } catch (error) {
        console.error('Sort error:', error);
    }
}

// Drag-Attribute setzen
document.querySelectorAll('#categories-tbody tr[data-id]').forEach(row => {
    row.draggable = true;
});
</script>
