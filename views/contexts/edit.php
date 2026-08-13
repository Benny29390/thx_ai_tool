<div class="page-header">
    <h1><?= $context ? 'Kontext bearbeiten' : 'Neuer Kontext' ?></h1>
    <div class="page-actions">
        <a href="/contexts" class="btn btn-secondary">
            <span class="material-symbols-rounded">arrow_back</span> Zurück
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 24px;">
        <form id="context-form">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="ctx-name">Name *</label>
                    <input type="text" id="ctx-name" value="<?= htmlspecialchars($context['name'] ?? '') ?>" placeholder="z.B. Blog-Artikel Kunde XY" required>
                </div>

                <div class="form-group" style="flex: 1;">
                    <label for="ctx-customer">Kunde (optional)</label>
                    <select id="ctx-customer">
                        <option value="">-- Kein Kunde --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($context && $context['customer_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="ctx-description">Beschreibung</label>
                <textarea id="ctx-description" rows="2" placeholder="Wofür wird dieser Kontext verwendet?"><?= htmlspecialchars($context['description'] ?? '') ?></textarea>
            </div>

            <div class="nav-divider" style="margin: 24px 0;"></div>

            <h3 style="margin-bottom: 16px;">Kontext-Items</h3>
            <p style="color: var(--color-gray-500); font-size: var(--d-fs-sm); margin-bottom: 16px;">Füge Elemente hinzu, die als Kontext für die KI dienen.</p>

            <div id="items-container">
                <?php if ($context && !empty($context['items'])): ?>
                    <?php foreach ($context['items'] as $index => $item): ?>
                        <!-- Items werden per JS gerendert -->
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="context-add-buttons" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;">
                <button type="button" class="btn btn-primary btn-small" onclick="openBrowseModal()">
                    <span class="material-symbols-rounded" style="font-size: 16px;">search</span> Browse & Hinzufügen
                </button>
                <button type="button" class="btn btn-secondary btn-small" onclick="addItem('customer')">
                    <span class="material-symbols-rounded" style="font-size: 16px;">business</span> Kunde
                </button>
                <button type="button" class="btn btn-secondary btn-small" onclick="addItem('knowledge')">
                    <span class="material-symbols-rounded" style="font-size: 16px;">menu_book</span> Wissen
                </button>
                <button type="button" class="btn btn-secondary btn-small" onclick="addItem('url')">
                    <span class="material-symbols-rounded" style="font-size: 16px;">link</span> URL
                </button>
                <button type="button" class="btn btn-secondary btn-small" onclick="addItem('text')">
                    <span class="material-symbols-rounded" style="font-size: 16px;">notes</span> Freitext
                </button>
                <button type="button" class="btn btn-secondary btn-small" onclick="addItem('rule')">
                    <span class="material-symbols-rounded" style="font-size: 16px;">rule</span> Regel
                </button>
                <button type="button" class="btn btn-secondary btn-small" onclick="addItem('rule_category')">
                    <span class="material-symbols-rounded" style="font-size: 16px;">folder_special</span> Regel-Kategorie
                </button>
                <button type="button" class="btn btn-secondary btn-small" onclick="addItem('knowledge_category')">
                    <span class="material-symbols-rounded" style="font-size: 16px;">library_books</span> Wissenskategorie
                </button>
            </div>

            <div class="form-actions" style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="save-btn">
                    <?= $context ? 'Speichern' : 'Kontext erstellen' ?>
                </button>
                <a href="/contexts" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

<style>
.context-item {
    background: var(--color-gray-50);
    border: 1px solid var(--color-gray-200);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    position: relative;
}
.context-item-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}
.context-item-type {
    font-size: var(--d-fs-xs);
    font-weight: 600;
    text-transform: uppercase;
    color: var(--color-gray-500);
    display: flex;
    align-items: center;
    gap: 4px;
}
.context-item-remove {
    position: absolute;
    top: 12px;
    right: 12px;
    background: none;
    border: none;
    color: var(--color-gray-400);
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
}
.context-item-remove:hover {
    color: var(--color-error);
    background: #fef2f2;
}
.context-item .form-group {
    margin-bottom: 8px;
}
.context-item .form-group:last-child {
    margin-bottom: 0;
}
.context-item input,
.context-item select,
.context-item textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--color-gray-300);
    border-radius: 6px;
    font-size: var(--d-fs-sm);
}
.context-item label {
    font-size: var(--d-fs-xs);
    font-weight: 500;
    color: var(--color-gray-600);
    display: block;
    margin-bottom: 4px;
}
.url-fetch-btn {
    padding: 8px 12px;
    font-size: var(--d-fs-sm);
    margin-top: 4px;
}
</style>

<!-- Browse Modal -->
<div id="browse-modal" class="modal browse-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Kontext-Items hinzufügen</h2>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="browse-tabs">
            <button type="button" class="browse-tab active" data-tab="rules" onclick="switchBrowseTab('rules')">Regeln</button>
            <button type="button" class="browse-tab" data-tab="knowledge" onclick="switchBrowseTab('knowledge')">Wissen</button>
            <button type="button" class="browse-tab" data-tab="authors" onclick="switchBrowseTab('authors')">Autoren</button>
        </div>
        <div class="browse-search">
            <input type="text" id="browse-search-input" placeholder="Suchen..." oninput="filterBrowseItems()">
        </div>
        <div class="browse-content" id="browse-content">
            <p style="color: var(--color-gray-500); text-align: center; padding: 40px 0;">Lade Daten...</p>
        </div>
        <div class="browse-footer">
            <span class="browse-count" id="browse-count">0 ausgewählt</span>
            <button type="button" class="btn btn-primary btn-small" onclick="addBrowseSelection()">Hinzufügen</button>
        </div>
    </div>
</div>

<script>
const existingItems = <?= json_encode($context['items'] ?? []) ?>;
const availableCustomers = <?= json_encode($customers ?? []) ?>;
const availableKnowledge = <?= json_encode($knowledge ?? []) ?>;
const availableRules = <?= json_encode($rules ?? []) ?>;
const availableRuleCategories = <?= json_encode($ruleCategories ?? []) ?>;
const availableKnowledgeCategories = <?= json_encode($knowledgeCategories ?? []) ?>;
const contextId = <?= $context ? $context['id'] : 'null' ?>;
let itemCounter = 0;

// Items rendern
function renderItems() {
    const container = document.getElementById('items-container');
    container.innerHTML = '';
    existingItems.forEach((item, index) => {
        container.appendChild(createItemElement(item, index));
    });
}

function createItemElement(item, index) {
    const div = document.createElement('div');
    div.className = 'context-item';
    div.dataset.index = index;

    const typeLabels = {
        customer: { label: 'Kunde', icon: 'business' },
        knowledge: { label: 'Wissen', icon: 'menu_book' },
        url: { label: 'URL', icon: 'link' },
        pdf: { label: 'PDF', icon: 'picture_as_pdf' },
        text: { label: 'Freitext', icon: 'notes' },
        rule: { label: 'Regel', icon: 'rule' },
        rule_category: { label: 'Regel-Kategorie', icon: 'folder_special' },
        knowledge_category: { label: 'Wissenskategorie', icon: 'library_books' },
        author: { label: 'Autor', icon: 'person_edit' }
    };

    const typeInfo = typeLabels[item.item_type] || { label: item.item_type, icon: 'help' };

    let fieldsHtml = '';

    switch (item.item_type) {
        case 'customer':
            fieldsHtml = `
                <div class="form-group">
                    <label>Kunde auswählen</label>
                    <select data-field="reference_id">
                        <option value="">-- Kunde wählen --</option>
                        ${availableCustomers.map(c => `<option value="${c.id}" ${item.reference_id == c.id ? 'selected' : ''}>${App.escapeHtml(c.name)}</option>`).join('')}
                    </select>
                </div>`;
            break;

        case 'knowledge':
            fieldsHtml = `
                <div class="form-group">
                    <label>Wissenseintrag</label>
                    <select data-field="reference_id">
                        <option value="">-- Eintrag wählen --</option>
                        ${availableKnowledge.map(k => `<option value="${k.id}" ${item.reference_id == k.id ? 'selected' : ''}>${App.escapeHtml(k.title || k.base_name)}</option>`).join('')}
                    </select>
                </div>`;
            break;

        case 'url':
            fieldsHtml = `
                <div class="form-group">
                    <label>URL</label>
                    <input type="url" data-field="title" value="${App.escapeHtml(item.title || '')}" placeholder="https://...">
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-secondary btn-small url-fetch-btn" onclick="fetchUrl(${index})">
                        <span class="material-symbols-rounded" style="font-size: 14px;">download</span> Inhalt laden
                    </button>
                </div>
                <div class="form-group">
                    <label>Inhalt</label>
                    <textarea data-field="content" rows="3" placeholder="URL-Inhalt wird hier eingefügt...">${App.escapeHtml(item.content || '')}</textarea>
                </div>`;
            break;

        case 'text':
            fieldsHtml = `
                <div class="form-group">
                    <label>Titel</label>
                    <input type="text" data-field="title" value="${App.escapeHtml(item.title || '')}" placeholder="Titel (optional)">
                </div>
                <div class="form-group">
                    <label>Inhalt</label>
                    <textarea data-field="content" rows="4" placeholder="Freitext eingeben...">${App.escapeHtml(item.content || '')}</textarea>
                </div>`;
            break;

        case 'rule':
            fieldsHtml = `
                <div class="form-group">
                    <label>Regel auswählen</label>
                    <select data-field="reference_id">
                        <option value="">-- Regel wählen --</option>
                        ${availableRules.map(r => `<option value="${r.id}" ${item.reference_id == r.id ? 'selected' : ''}>${App.escapeHtml(r.name)}</option>`).join('')}
                    </select>
                </div>`;
            break;

        case 'rule_category':
            fieldsHtml = `
                <div class="form-group">
                    <label>Regel-Kategorie auswählen</label>
                    <select data-field="reference_id">
                        <option value="">-- Kategorie wählen --</option>
                        ${availableRuleCategories.map(rc => `<option value="${rc.id}" ${item.reference_id == rc.id ? 'selected' : ''}>${App.escapeHtml(rc.name)} (${rc.rules_count} Regeln)</option>`).join('')}
                    </select>
                </div>
                <p style="font-size: var(--d-fs-xs); color: var(--color-gray-500); margin-top: 4px;">Alle aktiven Regeln dieser Kategorie werden einbezogen.</p>`;
            break;

        case 'knowledge_category':
            fieldsHtml = `
                <div class="form-group">
                    <label>Wissenskategorie auswählen</label>
                    <select data-field="reference_id">
                        <option value="">-- Kategorie wählen --</option>
                        ${availableKnowledgeCategories.map(kc => `<option value="${kc.id}" ${item.reference_id == kc.id ? 'selected' : ''}>${App.escapeHtml(kc.name)} (${kc.entries_count} Einträge)</option>`).join('')}
                    </select>
                </div>
                <p style="font-size: var(--d-fs-xs); color: var(--color-gray-500); margin-top: 4px;">Alle Wissenseinträge dieser Kategorie werden einbezogen.</p>`;
            break;
    }

    div.innerHTML = `
        <div class="context-item-header">
            <span class="context-item-type">
                <span class="material-symbols-rounded" style="font-size: 16px;">${typeInfo.icon}</span>
                ${typeInfo.label}
            </span>
        </div>
        <button type="button" class="context-item-remove" onclick="removeItem(${index})" title="Entfernen">
            <span class="material-symbols-rounded" style="font-size: 18px;">close</span>
        </button>
        ${fieldsHtml}
    `;

    return div;
}

function addItem(type) {
    existingItems.push({
        item_type: type,
        reference_id: null,
        content: null,
        title: null,
        sort_order: existingItems.length
    });
    renderItems();
}

function removeItem(index) {
    existingItems.splice(index, 1);
    renderItems();
}

async function fetchUrl(index) {
    const container = document.querySelectorAll('.context-item')[index];
    const urlInput = container.querySelector('[data-field="title"]');
    const contentArea = container.querySelector('[data-field="content"]');
    const url = urlInput.value.trim();

    if (!url) {
        App.showNotification('Bitte URL eingeben', 'error');
        return;
    }

    try {
        const response = await App.post('/knowledge/fetch-url', { url });
        if (response.data) {
            contentArea.value = response.data.content || '';
            existingItems[index].content = response.data.content || '';
            existingItems[index].title = url;
            App.showNotification('URL-Inhalt geladen', 'success');
        }
    } catch (error) {
        App.showNotification('Fehler beim Laden: ' + error.message, 'error');
    }
}

function collectItems() {
    const items = [];
    document.querySelectorAll('.context-item').forEach((el, index) => {
        const item = { ...existingItems[index] };

        el.querySelectorAll('[data-field]').forEach(field => {
            const fieldName = field.dataset.field;
            item[fieldName] = field.value || null;
        });

        item.sort_order = index;
        items.push(item);
    });
    return items;
}

// Formular absenden
document.getElementById('context-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const name = document.getElementById('ctx-name').value.trim();
    if (!name) {
        App.showNotification('Name erforderlich', 'error');
        return;
    }

    const customerSelect = document.getElementById('ctx-customer');
    const customerId = customerSelect ? customerSelect.value : '';

    const data = {
        name: name,
        customer_id: customerId ? parseInt(customerId) : null,
        description: document.getElementById('ctx-description').value.trim() || null,
        items: collectItems()
    };

    const btn = document.getElementById('save-btn');
    btn.disabled = true;
    btn.textContent = 'Speichere...';

    try {
        if (contextId) {
            await App.put('/contexts/' + contextId, data);
            App.showNotification('Kontext aktualisiert', 'success');
        } else {
            const response = await App.post('/contexts', data);
            App.showNotification('Kontext erstellt', 'success');
            window.location.href = '/contexts';
        }
    } catch (error) {
        App.showNotification(error.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = contextId ? 'Speichern' : 'Kontext erstellen';
    }
});

// ========================================
// Browse Modal
// ========================================
let browseData = null;
let browseActiveTab = 'rules';
let browseSelected = { rules: new Set(), knowledge: new Set(), authors: new Set() };

async function openBrowseModal() {
    const modal = document.getElementById('browse-modal');
    modal.classList.add('open');
    browseSelected = { rules: new Set(), knowledge: new Set(), authors: new Set() };
    browseActiveTab = 'rules';
    document.getElementById('browse-search-input').value = '';

    // Aktiven Tab setzen
    document.querySelectorAll('.browse-tab').forEach(t => t.classList.remove('active'));
    document.querySelector('.browse-tab[data-tab="rules"]').classList.add('active');

    updateBrowseCount();

    // Daten laden
    const content = document.getElementById('browse-content');
    content.innerHTML = '<p style="color: var(--color-gray-500); text-align: center; padding: 40px 0;">Lade Daten...</p>';

    try {
        const customerId = document.getElementById('ctx-customer').value;
        const url = '/contexts/browse-items' + (customerId ? '?customer_id=' + customerId : '');
        const response = await App.get(url);
        browseData = response.data;
        renderBrowseContent();
    } catch (error) {
        content.innerHTML = '<p style="color: var(--color-error); text-align: center; padding: 40px 0;">Fehler beim Laden: ' + App.escapeHtml(error.message) + '</p>';
    }
}

function switchBrowseTab(tab) {
    browseActiveTab = tab;
    document.querySelectorAll('.browse-tab').forEach(t => t.classList.remove('active'));
    document.querySelector('.browse-tab[data-tab="' + tab + '"]').classList.add('active');
    renderBrowseContent();
}

function renderBrowseContent() {
    if (!browseData) return;

    const content = document.getElementById('browse-content');
    const searchTerm = document.getElementById('browse-search-input').value.toLowerCase().trim();

    if (browseActiveTab === 'rules') {
        content.innerHTML = renderBrowseRules(browseData.rules, searchTerm);
    } else if (browseActiveTab === 'knowledge') {
        content.innerHTML = renderBrowseKnowledge(browseData.knowledge, searchTerm);
    } else if (browseActiveTab === 'authors') {
        content.innerHTML = renderBrowseAuthors(browseData.authors || [], searchTerm);
    }
}

function renderBrowseRules(data, searchTerm) {
    let html = '';

    // Kategorisierte Regeln
    data.categories.forEach(cat => {
        const filteredRules = cat.rules.filter(r => !searchTerm || r.name.toLowerCase().includes(searchTerm));
        if (filteredRules.length === 0) return;

        const allSelected = filteredRules.every(r => browseSelected.rules.has(r.id));
        const someSelected = filteredRules.some(r => browseSelected.rules.has(r.id));

        html += '<div class="browse-category" data-category-id="' + cat.id + '">';
        html += '<div class="browse-category-header" onclick="toggleBrowseCategory(this)">';
        html += '<input type="checkbox" ' + (allSelected ? 'checked' : '') + (someSelected && !allSelected ? ' style="opacity:0.5"' : '') + ' onclick="toggleCategoryCheckbox(event, \'rules\', ' + JSON.stringify(filteredRules.map(r => r.id)) + ')">';
        html += '<span class="material-symbols-rounded" style="font-size: 18px;">folder</span>';
        html += '<span>' + App.escapeHtml(cat.name) + ' (' + filteredRules.length + ')</span>';
        html += '<span class="material-symbols-rounded" style="font-size: 18px; margin-left: auto;">expand_more</span>';
        html += '</div>';
        html += '<div class="browse-category-items">';

        filteredRules.forEach(rule => {
            const checked = browseSelected.rules.has(rule.id) ? ' checked' : '';
            html += '<div class="browse-item">';
            html += '<input type="checkbox" id="rule-' + rule.id + '"' + checked + ' onchange="toggleBrowseItem(\'rules\', ' + rule.id + ')">';
            html += '<label for="rule-' + rule.id + '">';
            html += '<span class="browse-item-name" onclick="toggleBrowsePreview(event, \'rule-preview-' + rule.id + '\')">' + App.escapeHtml(rule.name) + '</span>';
            html += '<div class="browse-item-preview" id="rule-preview-' + rule.id + '">' + App.escapeHtml(rule.rule_content) + '</div>';
            html += '</label>';
            html += '</div>';
        });

        html += '</div></div>';
    });

    // Unkategorisierte Regeln
    const uncatFiltered = data.uncategorized.filter(r => !searchTerm || r.name.toLowerCase().includes(searchTerm));
    if (uncatFiltered.length > 0) {
        const allSelected = uncatFiltered.every(r => browseSelected.rules.has(r.id));
        const someSelected = uncatFiltered.some(r => browseSelected.rules.has(r.id));

        html += '<div class="browse-category">';
        html += '<div class="browse-category-header" onclick="toggleBrowseCategory(this)">';
        html += '<input type="checkbox" ' + (allSelected ? 'checked' : '') + (someSelected && !allSelected ? ' style="opacity:0.5"' : '') + ' onclick="toggleCategoryCheckbox(event, \'rules\', ' + JSON.stringify(uncatFiltered.map(r => r.id)) + ')">';
        html += '<span class="material-symbols-rounded" style="font-size: 18px;">folder_open</span>';
        html += '<span>Ohne Kategorie (' + uncatFiltered.length + ')</span>';
        html += '<span class="material-symbols-rounded" style="font-size: 18px; margin-left: auto;">expand_more</span>';
        html += '</div>';
        html += '<div class="browse-category-items">';

        uncatFiltered.forEach(rule => {
            const checked = browseSelected.rules.has(rule.id) ? ' checked' : '';
            html += '<div class="browse-item">';
            html += '<input type="checkbox" id="rule-' + rule.id + '"' + checked + ' onchange="toggleBrowseItem(\'rules\', ' + rule.id + ')">';
            html += '<label for="rule-' + rule.id + '">';
            html += '<span class="browse-item-name" onclick="toggleBrowsePreview(event, \'rule-preview-' + rule.id + '\')">' + App.escapeHtml(rule.name) + '</span>';
            html += '<div class="browse-item-preview" id="rule-preview-' + rule.id + '">' + App.escapeHtml(rule.rule_content) + '</div>';
            html += '</label>';
            html += '</div>';
        });

        html += '</div></div>';
    }

    if (!html) {
        html = '<p style="color: var(--color-gray-500); text-align: center; padding: 40px 0;">Keine Regeln gefunden.</p>';
    }

    return html;
}

function renderBrowseKnowledge(data, searchTerm) {
    let html = '';

    // Kategorisierte Einträge
    data.categories.forEach(cat => {
        const filteredEntries = cat.entries.filter(e => !searchTerm || e.title.toLowerCase().includes(searchTerm));
        if (filteredEntries.length === 0) return;

        const allSelected = filteredEntries.every(e => browseSelected.knowledge.has(e.id));
        const someSelected = filteredEntries.some(e => browseSelected.knowledge.has(e.id));

        html += '<div class="browse-category" data-category-id="' + cat.id + '">';
        html += '<div class="browse-category-header" onclick="toggleBrowseCategory(this)">';
        html += '<input type="checkbox" ' + (allSelected ? 'checked' : '') + (someSelected && !allSelected ? ' style="opacity:0.5"' : '') + ' onclick="toggleCategoryCheckbox(event, \'knowledge\', ' + JSON.stringify(filteredEntries.map(e => e.id)) + ')">';
        html += '<span class="material-symbols-rounded" style="font-size: 18px;">menu_book</span>';
        html += '<span>' + App.escapeHtml(cat.name) + ' (' + filteredEntries.length + ')</span>';
        html += '<span class="material-symbols-rounded" style="font-size: 18px; margin-left: auto;">expand_more</span>';
        html += '</div>';
        html += '<div class="browse-category-items">';

        filteredEntries.forEach(entry => {
            const checked = browseSelected.knowledge.has(entry.id) ? ' checked' : '';
            html += '<div class="browse-item">';
            html += '<input type="checkbox" id="knowledge-' + entry.id + '"' + checked + ' onchange="toggleBrowseItem(\'knowledge\', ' + entry.id + ')">';
            html += '<label for="knowledge-' + entry.id + '">';
            html += '<span class="browse-item-name" onclick="toggleBrowsePreview(event, \'knowledge-preview-' + entry.id + '\')">' + App.escapeHtml(entry.title) + '</span>';
            html += '<div class="browse-item-preview" id="knowledge-preview-' + entry.id + '">' + App.escapeHtml(entry.content_preview) + '</div>';
            html += '</label>';
            html += '</div>';
        });

        html += '</div></div>';
    });

    // Unkategorisierte Einträge
    const uncatFiltered = data.uncategorized.filter(e => !searchTerm || e.title.toLowerCase().includes(searchTerm));
    if (uncatFiltered.length > 0) {
        const allSelected = uncatFiltered.every(e => browseSelected.knowledge.has(e.id));
        const someSelected = uncatFiltered.some(e => browseSelected.knowledge.has(e.id));

        html += '<div class="browse-category">';
        html += '<div class="browse-category-header" onclick="toggleBrowseCategory(this)">';
        html += '<input type="checkbox" ' + (allSelected ? 'checked' : '') + (someSelected && !allSelected ? ' style="opacity:0.5"' : '') + ' onclick="toggleCategoryCheckbox(event, \'knowledge\', ' + JSON.stringify(uncatFiltered.map(e => e.id)) + ')">';
        html += '<span class="material-symbols-rounded" style="font-size: 18px;">folder_open</span>';
        html += '<span>Ohne Kategorie (' + uncatFiltered.length + ')</span>';
        html += '<span class="material-symbols-rounded" style="font-size: 18px; margin-left: auto;">expand_more</span>';
        html += '</div>';
        html += '<div class="browse-category-items">';

        uncatFiltered.forEach(entry => {
            const checked = browseSelected.knowledge.has(entry.id) ? ' checked' : '';
            html += '<div class="browse-item">';
            html += '<input type="checkbox" id="knowledge-' + entry.id + '"' + checked + ' onchange="toggleBrowseItem(\'knowledge\', ' + entry.id + ')">';
            html += '<label for="knowledge-' + entry.id + '">';
            html += '<span class="browse-item-name" onclick="toggleBrowsePreview(event, \'knowledge-preview-' + entry.id + '\')">' + App.escapeHtml(entry.title) + '</span>';
            html += '<div class="browse-item-preview" id="knowledge-preview-' + entry.id + '">' + App.escapeHtml(entry.content_preview) + '</div>';
            html += '</label>';
            html += '</div>';
        });

        html += '</div></div>';
    }

    if (!html) {
        html = '<p style="color: var(--color-gray-500); text-align: center; padding: 40px 0;">Keine Wissenseinträge gefunden.</p>';
    }

    return html;
}

function renderBrowseAuthors(authors, searchTerm) {
    const filtered = authors.filter(a => !searchTerm || a.name.toLowerCase().includes(searchTerm) || (a.subtitle && a.subtitle.toLowerCase().includes(searchTerm)));
    if (filtered.length === 0) {
        return '<p style="color: var(--color-gray-500); text-align: center; padding: 40px 0;">Keine Autorenprofile gefunden.</p>';
    }

    let html = '';
    filtered.forEach(author => {
        const checked = browseSelected.authors.has(author.id) ? ' checked' : '';
        html += '<div class="browse-item">';
        html += '<input type="checkbox" id="author-' + author.id + '"' + checked + ' onchange="toggleBrowseItem(\'authors\', ' + author.id + ')">';
        html += '<label for="author-' + author.id + '">';
        html += '<span class="browse-item-name">' + App.escapeHtml(author.name) + '</span>';
        if (author.subtitle) {
            html += '<span style="display: block; font-size: var(--d-fs-xs); color: var(--color-gray-500);">' + App.escapeHtml(author.subtitle) + '</span>';
        }
        html += '</label>';
        html += '</div>';
    });

    return html;
}

function toggleBrowseCategory(header) {
    // Nicht toggling wenn auf Checkbox geklickt
    if (event.target.type === 'checkbox') return;
    const category = header.closest('.browse-category');
    category.classList.toggle('collapsed');
}

function toggleCategoryCheckbox(event, type, ids) {
    event.stopPropagation();
    const checked = event.target.checked;
    ids.forEach(id => {
        if (checked) {
            browseSelected[type].add(id);
        } else {
            browseSelected[type].delete(id);
        }
    });
    updateBrowseCount();
    renderBrowseContent();
}

function toggleBrowseItem(type, id) {
    if (browseSelected[type].has(id)) {
        browseSelected[type].delete(id);
    } else {
        browseSelected[type].add(id);
    }
    updateBrowseCount();
    // Kategorie-Checkbox-Status wird beim nächsten renderBrowseContent aktualisiert
}

function toggleBrowsePreview(event, previewId) {
    event.preventDefault();
    event.stopPropagation();
    const preview = document.getElementById(previewId);
    if (preview) {
        preview.classList.toggle('visible');
    }
}

function filterBrowseItems() {
    renderBrowseContent();
}

function updateBrowseCount() {
    const total = browseSelected.rules.size + browseSelected.knowledge.size + browseSelected.authors.size;
    document.getElementById('browse-count').textContent = total + ' ausgewählt';
}

function addBrowseSelection() {
    // Ausgewählte Regeln hinzufügen
    browseSelected.rules.forEach(ruleId => {
        // Prüfen ob bereits vorhanden
        const alreadyExists = existingItems.some(item => item.item_type === 'rule' && parseInt(item.reference_id) === ruleId);
        if (!alreadyExists) {
            existingItems.push({
                item_type: 'rule',
                reference_id: ruleId,
                content: null,
                title: null,
                sort_order: existingItems.length
            });
        }
    });

    // Ausgewählte Wissenseinträge hinzufügen
    browseSelected.knowledge.forEach(entryId => {
        const alreadyExists = existingItems.some(item => item.item_type === 'knowledge' && parseInt(item.reference_id) === entryId);
        if (!alreadyExists) {
            existingItems.push({
                item_type: 'knowledge',
                reference_id: entryId,
                content: null,
                title: null,
                sort_order: existingItems.length
            });
        }
    });

    // Ausgewählte Autoren hinzufügen
    browseSelected.authors.forEach(authorId => {
        const alreadyExists = existingItems.some(item => item.item_type === 'author' && parseInt(item.reference_id) === authorId);
        if (!alreadyExists) {
            existingItems.push({
                item_type: 'author',
                reference_id: authorId,
                content: null,
                title: null,
                sort_order: existingItems.length
            });
        }
    });

    const total = browseSelected.rules.size + browseSelected.knowledge.size + browseSelected.authors.size;

    // Modal schließen
    const modal = document.getElementById('browse-modal');
    modal.classList.remove('open');

    // Items neu rendern
    renderItems();

    if (total > 0) {
        App.showNotification(total + ' Item(s) hinzugefügt', 'success');
    }
}

// Initial rendern
renderItems();
</script>
