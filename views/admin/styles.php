<div class="page-header">
    <h1>Stile</h1>
    <button class="btn btn-primary" onclick="openStyleModal()">
        <span class="btn-icon">+</span> Neuer Stil
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
                    <th>Beschreibung</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="styles-tbody">
                <?php if (empty($styles)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">Keine Stile vorhanden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($styles as $style): ?>
                        <tr data-id="<?= $style['id'] ?>">
                            <td class="drag-handle" title="Ziehen zum Sortieren">&#9776;</td>
                            <td><strong><?= htmlspecialchars($style['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($style['slug']) ?></code></td>
                            <td class="text-muted"><?= htmlspecialchars($style['description'] ?? '') ?></td>
                            <td>
                                <?php if ($style['is_active']): ?>
                                    <span class="badge badge-success">Aktiv</span>
                                <?php else: ?>
                                    <span class="badge badge-error">Inaktiv</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-small btn-secondary"
                                        onclick="editStyle(<?= $style['id'] ?>)">
                                    Bearbeiten
                                </button>
                                <button class="btn btn-small btn-danger btn-icon"
                                        onclick="deleteStyle(<?= $style['id'] ?>)" title="Löschen">
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

<!-- Modal: Stil bearbeiten -->
<div class="modal" id="style-modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 id="style-modal-title">Neuer Stil</h2>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="style-form" data-ajax data-endpoint="/admin/styles" data-method="POST"
              data-on-success="styleSaved">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" id="style-id" name="id" value="">

            <div class="form-group">
                <label for="style-name">Name *</label>
                <input type="text" id="style-name" name="name" required
                       placeholder="z.B. Professionell">
            </div>

            <div class="form-group">
                <label for="style-slug">Slug *</label>
                <input type="text" id="style-slug" name="slug" required
                       pattern="[a-z0-9-]+" title="Nur Kleinbuchstaben, Zahlen und Bindestriche"
                       placeholder="z.B. professionell">
                <small>Wird intern verwendet</small>
            </div>

            <div class="form-group">
                <label for="style-description">Beschreibung</label>
                <input type="text" id="style-description" name="description"
                       placeholder="Kurze Beschreibung des Stils">
            </div>

            <div class="form-group">
                <label for="style-prompt">Stil-Anweisung *</label>
                <textarea id="style-prompt" name="prompt_addition" required rows="5"
                          placeholder="Anweisungen für die KI, wie sie in diesem Stil schreiben soll..."></textarea>
                <small>Diese Anweisung wird dem KI-Prompt hinzugefügt</small>
            </div>

            <div class="form-group" id="style-status-group" style="display: none;">
                <label for="style-status">Status</label>
                <select id="style-status" name="is_active">
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
textarea {
    width: 100%;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    padding: var(--spacing-sm);
    font-family: inherit;
    font-size: var(--d-fs-sm);
    resize: vertical;
}
textarea:focus {
    outline: none;
    border-color: var(--color-black);
}
</style>

<script>
function styleSaved() {
    App.closeModal('style-modal');
    location.reload();
}

async function editStyle(id) {
    try {
        const response = await App.get('/admin/styles/' + id);
        const style = response.data;

        document.getElementById('style-modal-title').textContent = 'Stil bearbeiten';
        document.getElementById('style-id').value = style.id;
        document.getElementById('style-name').value = style.name;
        document.getElementById('style-slug').value = style.slug;
        document.getElementById('style-description').value = style.description || '';
        document.getElementById('style-prompt').value = style.prompt_addition;
        document.getElementById('style-status').value = style.is_active ? '1' : '0';
        document.getElementById('style-status-group').style.display = 'block';

        const form = document.getElementById('style-form');
        form.setAttribute('data-endpoint', '/admin/styles/' + id);
        form.setAttribute('data-method', 'PUT');

        App.openModal('style-modal');
    } catch (error) {
        App.showNotification('Fehler beim Laden: ' + error.message, 'error');
    }
}

function openStyleModal() {
    document.getElementById('style-modal-title').textContent = 'Neuer Stil';
    document.getElementById('style-id').value = '';
    document.getElementById('style-name').value = '';
    document.getElementById('style-slug').value = '';
    document.getElementById('style-description').value = '';
    document.getElementById('style-prompt').value = '';
    document.getElementById('style-status-group').style.display = 'none';

    const form = document.getElementById('style-form');
    form.setAttribute('data-endpoint', '/admin/styles');
    form.setAttribute('data-method', 'POST');

    App.openModal('style-modal');
}

async function deleteStyle(id) {
    if (!confirm('Stil wirklich löschen?')) return;

    try {
        await App.delete('/admin/styles/' + id);
        document.querySelector(`tr[data-id="${id}"]`).remove();
        App.showNotification('Stil gelöscht', 'success');
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

// Slug aus Name generieren
document.getElementById('style-name')?.addEventListener('input', function() {
    const slugField = document.getElementById('style-slug');
    if (!slugField.value || slugField.dataset.autoGenerated === 'true') {
        const slug = this.value.toLowerCase()
            .replace(/[äöüß]/g, m => ({ä:'ae',ö:'oe',ü:'ue',ß:'ss'}[m]))
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
        slugField.value = slug;
        slugField.dataset.autoGenerated = 'true';
    }
});

document.getElementById('style-slug')?.addEventListener('input', function() {
    this.dataset.autoGenerated = 'false';
});

// Drag & Drop Sortierung
const tbody = document.getElementById('styles-tbody');
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
        await App.post('/admin/styles/sort', { order: order });
    } catch (error) {
        console.error('Sort error:', error);
    }
}

// Drag-Attribute setzen
document.querySelectorAll('#styles-tbody tr[data-id]').forEach(row => {
    row.draggable = true;
});
</script>
