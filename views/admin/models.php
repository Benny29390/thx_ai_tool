<div class="page-header">
    <h1>KI-Modelle</h1>
    <button class="btn btn-primary" onclick="openModelModal()">
        <span class="btn-icon">+</span> Neues Modell
    </button>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th>Name</th>
                    <th>Model ID</th>
                    <th>Provider</th>
                    <th>Kosten (Input/Output)</th>
                    <th>Chat</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="models-tbody">
                <?php if (empty($models)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">Keine Modelle vorhanden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($models as $model): ?>
                        <tr data-id="<?= $model['id'] ?>">
                            <td class="drag-handle" title="Ziehen zum Sortieren">&#9776;</td>
                            <td>
                                <strong><?= htmlspecialchars($model['display_name']) ?></strong>
                                <?php if ($model['is_default']): ?>
                                    <span class="badge badge-info">Standard</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($model['model_id']) ?></code></td>
                            <td>
                                <span class="provider-badge provider-<?= $model['provider'] ?>">
                                    <?= match($model['provider']) { 'openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'google' => 'Google', 'local' => 'Lokal', default => $model['provider'] } ?>
                                </span>
                            </td>
                            <td class="text-muted">
                                $<?= number_format($model['cost_input'], 4) ?> / $<?= number_format($model['cost_output'], 4) ?>
                            </td>
                            <td>
                                <?php if (!empty($model['chat_enabled'])): ?>
                                    <span class="badge badge-success">Chat</span>
                                <?php else: ?>
                                    <span class="badge" style="background:var(--color-gray-100);color:var(--color-gray-400)">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($model['is_active']): ?>
                                    <span class="badge badge-success">Aktiv</span>
                                <?php else: ?>
                                    <span class="badge badge-error">Inaktiv</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-small btn-secondary"
                                        onclick="editModel(<?= $model['id'] ?>)">
                                    Bearbeiten
                                </button>
                                <button class="btn btn-small btn-danger btn-icon"
                                        onclick="deleteModel(<?= $model['id'] ?>)" title="Löschen">
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

<p class="info-text">
    <strong>Hinweis:</strong> Die Kosten sind pro 1000 Tokens angegeben. Nur aktive Modelle werden in der Auswahl angezeigt.
</p>

<!-- Modal: Modell bearbeiten -->
<div class="modal" id="model-modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 id="model-modal-title">Neues Modell</h2>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="model-form" data-ajax data-endpoint="/admin/models" data-method="POST"
              data-on-success="modelSaved">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" id="model-id" name="id" value="">

            <div class="form-row">
                <div class="form-group">
                    <label for="model-display-name">Anzeigename *</label>
                    <input type="text" id="model-display-name" name="display_name" required
                           placeholder="z.B. GPT-4 Turbo">
                </div>
                <div class="form-group">
                    <label for="model-model-id">Model ID *</label>
                    <input type="text" id="model-model-id" name="model_id" required
                           placeholder="z.B. gpt-4-turbo">
                    <small>Exakte API-Bezeichnung</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="model-provider">Provider *</label>
                    <select id="model-provider" name="provider" required>
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Anthropic</option>
                        <option value="google">Google</option>
                        <option value="local">Lokal (eigener Server)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="model-max-tokens">Max Tokens</label>
                    <input type="number" id="model-max-tokens" name="max_tokens"
                           value="4096" min="1" max="1000000">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="model-cost-input">Kosten Input (pro 1K Tokens) *</label>
                    <div class="input-with-prefix">
                        <span class="input-prefix">$</span>
                        <input type="number" id="model-cost-input" name="cost_input" required
                               step="0.000001" min="0" placeholder="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label for="model-cost-output">Kosten Output (pro 1K Tokens) *</label>
                    <div class="input-with-prefix">
                        <span class="input-prefix">$</span>
                        <input type="number" id="model-cost-output" name="cost_output" required
                               step="0.000001" min="0" placeholder="0.03">
                    </div>
                </div>
            </div>

            <div class="form-row" id="model-status-row" style="display: none;">
                <div class="form-group">
                    <label for="model-status">Status</label>
                    <select id="model-status" name="is_active">
                        <option value="1">Aktiv</option>
                        <option value="0">Inaktiv</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="model-default">Standard-Modell</label>
                    <select id="model-default" name="is_default">
                        <option value="0">Nein</option>
                        <option value="1">Ja</option>
                    </select>
                </div>
            </div>

            <div class="form-row" id="model-chat-row" style="display: none;">
                <div class="form-group">
                    <label for="model-chat-enabled">Im Chat verfuegbar</label>
                    <select id="model-chat-enabled" name="chat_enabled">
                        <option value="0">Nein</option>
                        <option value="1">Ja</option>
                    </select>
                    <small>Modell in der Chat-Funktion anbieten</small>
                </div>
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
    color: #1e40af;
}
.provider-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: var(--d-fs-xs);
    font-weight: 500;
}
.provider-openai {
    background: #10a37f20;
    color: #10a37f;
}
.provider-anthropic {
    background: #d97b0820;
    color: #d97b08;
}
.provider-google {
    background: #4285f420;
    color: #4285f4;
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
.input-with-prefix {
    display: flex;
    align-items: center;
}
.input-prefix {
    background: var(--color-gray-100);
    border: 1px solid var(--color-gray-300);
    border-right: none;
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    padding: var(--spacing-sm) var(--spacing-md);
    color: var(--color-gray-500);
}
.input-with-prefix input {
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    flex: 1;
}
.info-text {
    margin-top: var(--spacing-lg);
    padding: var(--spacing-md);
    background: var(--color-gray-50);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-sm);
    color: var(--color-gray-600);
}
</style>

<script>
const modelsData = <?= json_encode($models) ?>;

function modelSaved() {
    App.closeModal('model-modal');
    location.reload();
}

async function editModel(id) {
    const model = modelsData.find(m => m.id == id);
    if (!model) {
        App.showNotification('Modell nicht gefunden', 'error');
        return;
    }

    document.getElementById('model-modal-title').textContent = 'Modell bearbeiten';
    document.getElementById('model-id').value = model.id;
    document.getElementById('model-display-name').value = model.display_name;
    document.getElementById('model-model-id').value = model.model_id;
    document.getElementById('model-provider').value = model.provider;
    document.getElementById('model-max-tokens').value = model.max_tokens || 4096;
    document.getElementById('model-cost-input').value = model.cost_input;
    document.getElementById('model-cost-output').value = model.cost_output;
    document.getElementById('model-status').value = model.is_active ? '1' : '0';
    document.getElementById('model-default').value = model.is_default ? '1' : '0';
    document.getElementById('model-chat-enabled').value = model.chat_enabled ? '1' : '0';
    document.getElementById('model-status-row').style.display = 'grid';
    document.getElementById('model-chat-row').style.display = 'grid';

    const form = document.getElementById('model-form');
    form.setAttribute('data-endpoint', '/admin/models/' + id);
    form.setAttribute('data-method', 'PUT');

    App.openModal('model-modal');
}

function openModelModal() {
    document.getElementById('model-modal-title').textContent = 'Neues Modell';
    document.getElementById('model-id').value = '';
    document.getElementById('model-display-name').value = '';
    document.getElementById('model-model-id').value = '';
    document.getElementById('model-provider').value = 'openai';
    document.getElementById('model-max-tokens').value = '4096';
    document.getElementById('model-cost-input').value = '';
    document.getElementById('model-cost-output').value = '';
    document.getElementById('model-status-row').style.display = 'none';
    document.getElementById('model-chat-row').style.display = 'none';

    const form = document.getElementById('model-form');
    form.setAttribute('data-endpoint', '/admin/models');
    form.setAttribute('data-method', 'POST');

    App.openModal('model-modal');
}

async function deleteModel(id) {
    if (!confirm('Modell wirklich löschen?')) return;

    try {
        await App.delete('/admin/models/' + id);
        document.querySelector(`tr[data-id="${id}"]`).remove();
        App.showNotification('Modell gelöscht', 'success');
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

// Drag & Drop Sortierung
const tbody = document.getElementById('models-tbody');
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
        await App.post('/admin/models/sort', { order: order });
    } catch (error) {
        console.error('Sort error:', error);
    }
}

// Drag-Attribute setzen
document.querySelectorAll('#models-tbody tr[data-id]').forEach(row => {
    row.draggable = true;
});
</script>
