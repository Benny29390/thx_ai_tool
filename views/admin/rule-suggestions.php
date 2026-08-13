<div class="page-header">
    <h1>Regelvorschläge</h1>
    <p class="subtitle">KI-generierte Vorschläge basierend auf Benutzer-Feedback</p>
</div>

<?php if (empty($suggestions)): ?>
    <div class="card text-center" style="padding: var(--spacing-2xl);">
        <div class="empty-icon"><span class="material-symbols-rounded" style="font-size: 48px;">lightbulb</span></div>
        <h3>Keine offenen Vorschläge</h3>
        <p class="text-muted">
            Neue Vorschläge werden automatisch generiert, wenn genügend negatives Feedback vorliegt.
        </p>
    </div>
<?php else: ?>
    <div class="suggestions-list">
        <?php foreach ($suggestions as $suggestion): ?>
            <div class="suggestion-card card" data-id="<?= $suggestion['id'] ?>">
                <div class="suggestion-header">
                    <span class="rule-type type-<?= $suggestion['rule_type'] ?>">
                        <?= ucfirst($suggestion['rule_type']) ?>
                    </span>
                    <span class="confidence">
                        Konfidenz: <?= round($suggestion['confidence_score'] * 100) ?>%
                    </span>
                </div>

                <div class="suggestion-content">
                    <p class="suggested-rule"><?= htmlspecialchars($suggestion['suggested_rule']) ?></p>
                    <p class="suggestion-meta text-muted">
                        Kunde: <?= htmlspecialchars($suggestion['customer_name']) ?> |
                        Erstellt: <?= date('d.m.Y H:i', strtotime($suggestion['created_at'])) ?>
                    </p>
                </div>

                <div class="suggestion-actions">
                    <button class="btn btn-success" onclick="approveSuggestion(<?= $suggestion['id'] ?>)">
                        Freigeben
                    </button>
                    <button class="btn btn-secondary" onclick="editSuggestion(<?= $suggestion['id'] ?>)">
                        Anpassen
                    </button>
                    <button class="btn btn-danger btn-icon" onclick="rejectSuggestion(<?= $suggestion['id'] ?>)" title="Ablehnen">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal: Anpassen -->
<div class="modal" id="edit-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Regel anpassen</h2>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="edit-form">
            <input type="hidden" id="edit-id">

            <div class="form-group">
                <label for="edit-type">Typ</label>
                <select id="edit-type">
                    <option value="style">Stil</option>
                    <option value="tone">Tonalität</option>
                    <option value="format">Format</option>
                    <option value="content">Inhalt</option>
                    <option value="link">Links</option>
                </select>
            </div>

            <div class="form-group">
                <label for="edit-content">Regel</label>
                <textarea id="edit-content" rows="4"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Abbrechen</button>
                <button type="button" class="btn btn-success" onclick="submitEdit()">Freigeben</button>
            </div>
        </form>
    </div>
</div>

<style>
.empty-icon {
    font-size: var(--d-fs-2xl);
    margin-bottom: var(--spacing-md);
}
.suggestions-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
}
.suggestion-card {
    padding: var(--spacing-lg);
}
.suggestion-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
}
.confidence {
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
}
.suggested-rule {
    font-size: var(--d-fs-lg);
    margin: 0 0 var(--spacing-sm) 0;
}
.suggestion-meta {
    font-size: var(--d-fs-sm);
    margin: 0;
}
.suggestion-actions {
    display: flex;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--color-gray-200);
}
.btn-success {
    background: var(--color-success);
    color: white;
}
.btn-success:hover {
    opacity: 0.9;
}
</style>

<script>
async function approveSuggestion(id) {
    if (!confirm('Regel freigeben?')) return;

    try {
        await App.put('/suggestions/' + id, { action: 'approve' });
        document.querySelector(`.suggestion-card[data-id="${id}"]`).remove();
        App.showNotification('Regel freigegeben', 'success');

        // Prüfen ob Liste leer
        if (document.querySelectorAll('.suggestion-card').length === 0) {
            location.reload();
        }
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

async function rejectSuggestion(id) {
    if (!confirm('Vorschlag ablehnen?')) return;

    try {
        await App.put('/suggestions/' + id, { action: 'reject' });
        document.querySelector(`.suggestion-card[data-id="${id}"]`).remove();
        App.showNotification('Vorschlag abgelehnt', 'success');

        if (document.querySelectorAll('.suggestion-card').length === 0) {
            location.reload();
        }
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

function editSuggestion(id) {
    const card = document.querySelector(`.suggestion-card[data-id="${id}"]`);
    const type = card.querySelector('.rule-type').textContent.trim().toLowerCase();
    const content = card.querySelector('.suggested-rule').textContent;

    document.getElementById('edit-id').value = id;
    document.getElementById('edit-type').value = type;
    document.getElementById('edit-content').value = content;

    App.openModal('edit-modal');
}

async function submitEdit() {
    const id = document.getElementById('edit-id').value;
    const type = document.getElementById('edit-type').value;
    const content = document.getElementById('edit-content').value;

    try {
        await App.put('/suggestions/' + id, {
            action: 'approve',
            rule_type: type,
            rule_content: content
        });
        App.closeModal('edit-modal');
        document.querySelector(`.suggestion-card[data-id="${id}"]`).remove();
        App.showNotification('Regel freigegeben', 'success');

        if (document.querySelectorAll('.suggestion-card').length === 0) {
            location.reload();
        }
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}
</script>
