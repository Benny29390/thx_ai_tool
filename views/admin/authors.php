<div class="page-header">
    <h1>Autorenprofile</h1>
    <div style="display: flex; gap: 8px;">
        <button class="btn btn-secondary" onclick="openAuthorWizard()">
            <span class="material-symbols-rounded" style="font-size: 18px; vertical-align: middle; margin-right: 4px;">auto_awesome</span>
            KI-Wizard
        </button>
        <button class="btn btn-primary" onclick="openAuthorModal()">
            <span class="btn-icon">+</span> Neuer Autor
        </button>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Kunde</th>
                    <th>Schreibstil</th>
                    <th>Expertise</th>
                    <th>Tonalität</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($authors)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Keine Autorenprofile vorhanden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($authors as $author): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($author['name']) ?></strong></td>
                            <td>
                                <?php if (!empty($author['customer_name'])): ?>
                                    <?= htmlspecialchars($author['customer_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Global</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars(mb_substr($author['writing_style'] ?? '-', 0, 50)) ?><?= mb_strlen($author['writing_style'] ?? '') > 50 ? '...' : '' ?></td>
                            <td class="text-muted"><?= htmlspecialchars(mb_substr($author['expertise'] ?? '-', 0, 50)) ?><?= mb_strlen($author['expertise'] ?? '') > 50 ? '...' : '' ?></td>
                            <td>
                                <?php if (!empty($author['tone'])): ?>
                                    <span class="badge badge-primary"><?= htmlspecialchars($author['tone']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($author['is_active']): ?>
                                    <span class="badge badge-success">Aktiv</span>
                                <?php else: ?>
                                    <span class="badge badge-error">Inaktiv</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon"
                                            onclick="editAuthor(<?= $author['id'] ?>)"
                                            title="Bearbeiten">
                                        <span class="material-symbols-rounded">edit</span>
                                    </button>
                                    <button class="btn-icon btn-danger"
                                            onclick="deleteAuthor(<?= $author['id'] ?>, '<?= htmlspecialchars(addslashes($author['name'])) ?>')"
                                            title="Löschen">
                                        <span class="material-symbols-rounded">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Autor bearbeiten -->
<div class="modal" id="author-modal">
    <div class="modal-content" style="max-width: 640px;">
        <div class="modal-header">
            <h2 id="author-modal-title">Neuer Autor</h2>
            <button class="modal-close" onclick="closeAuthorModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px;">
            <input type="hidden" id="author-id" value="">

            <div class="form-group">
                <label for="author-name">Name *</label>
                <input type="text" id="author-name" required placeholder="z.B. Dr. Maria Schmidt">
            </div>

            <div class="form-group">
                <label for="author-customer">Kunde</label>
                <select id="author-customer">
                    <option value="">Global (alle Kunden)</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="author-writing-style">Schreibstil</label>
                <textarea id="author-writing-style" rows="3" placeholder="z.B. Sachlich, faktenbasiert, mit persönlicher Note"></textarea>
            </div>

            <div class="form-group">
                <label for="author-expertise">Expertise / Fachgebiete</label>
                <textarea id="author-expertise" rows="2" placeholder="z.B. SEO, Content Marketing, Technologie"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="author-tone">Tonalität</label>
                    <select id="author-tone">
                        <option value="">-- Auswählen --</option>
                        <option value="formal">Formal</option>
                        <option value="informal">Informal</option>
                        <option value="professional">Professional</option>
                        <option value="friendly">Freundlich</option>
                        <option value="technical">Technisch</option>
                        <option value="casual">Locker</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="author-perspective">Perspektive</label>
                    <select id="author-perspective">
                        <option value="">-- Auswählen --</option>
                        <option value="Ich-Form">Ich-Form</option>
                        <option value="Wir-Form">Wir-Form</option>
                        <option value="Neutral">Neutral / Man-Form</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="author-example-text">Beispieltext</label>
                <textarea id="author-example-text" rows="4" placeholder="Referenztext für Stilerkennung (optional)"></textarea>
            </div>

            <div class="form-group">
                <label for="author-notes">Zusätzliche Hinweise</label>
                <textarea id="author-notes" rows="2" placeholder="Besondere Anweisungen für die KI"></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-item">
                    <input type="checkbox" id="author-active" checked>
                    Aktiv
                </label>
            </div>

            <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 16px;">
                <button type="button" class="btn btn-secondary" onclick="closeAuthorModal()">Abbrechen</button>
                <button type="button" class="btn btn-primary" onclick="saveAuthor()" id="author-save-btn">Speichern</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Autor-Wizard (KI-Profil-Erstellung) -->
<div class="modal" id="author-wizard-modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2>
                <span class="material-symbols-rounded" style="font-size: 22px; vertical-align: middle; margin-right: 6px;">auto_awesome</span>
                KI-Autoren-Wizard
            </h2>
            <button class="modal-close" onclick="closeWizard()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <!-- Wizard Steps Header -->
            <div class="wizard-steps">
                <div class="wizard-step active" data-step="1">
                    <span class="step-number">1</span>
                    <span class="step-label">Quellen</span>
                </div>
                <div class="wizard-step" data-step="2">
                    <span class="step-number">2</span>
                    <span class="step-label">Analyse</span>
                </div>
                <div class="wizard-step" data-step="3">
                    <span class="step-number">3</span>
                    <span class="step-label">Profil</span>
                </div>
            </div>

            <!-- Step 1: Quellen -->
            <div class="wizard-panel" id="wizard-step-1" style="padding: 24px;">
                <div class="form-group">
                    <label>Dokumente hochladen</label>
                    <div class="wizard-dropzone" id="wizard-dropzone">
                        <span class="material-symbols-rounded" style="font-size: 36px; color: var(--text-muted);">upload_file</span>
                        <p style="margin: 8px 0 4px;">Dateien hierher ziehen oder klicken</p>
                        <small class="text-muted">PDF, DOCX, TXT (max. 10 MB pro Datei)</small>
                        <input type="file" id="wizard-file-input" multiple accept=".pdf,.docx,.txt" style="display: none;">
                    </div>
                    <div id="wizard-file-list" class="wizard-file-list"></div>
                </div>

                <div class="form-group">
                    <label>URLs eingeben</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="url" id="wizard-url-input" placeholder="https://example.com/blog-post" style="flex: 1;">
                        <button class="btn btn-secondary" onclick="addWizardUrl()" title="URL hinzufügen">
                            <span class="material-symbols-rounded" style="font-size: 18px;">add</span>
                        </button>
                    </div>
                    <div id="wizard-url-list" class="wizard-url-list"></div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="wizard-author-name">Autorenname (optional)</label>
                        <input type="text" id="wizard-author-name" placeholder="z.B. Dr. Maria Schmidt">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="wizard-customer">Kunde (optional)</label>
                        <select id="wizard-customer">
                            <option value="">Global (alle Kunden)</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="wizard-model">KI-Modell</label>
                    <select id="wizard-model">
                        <?php foreach ($models as $m): ?>
                            <option value="<?= htmlspecialchars($m['model_id']) ?>"<?= $m['model_id'] === 'gpt-5.2' ? ' selected' : '' ?>>
                                <?= htmlspecialchars($m['display_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 16px;">
                    <button type="button" class="btn btn-secondary" onclick="closeWizard()">Abbrechen</button>
                    <button type="button" class="btn btn-primary" onclick="runWizardAnalysis()" id="wizard-analyze-btn">
                        <span class="material-symbols-rounded" style="font-size: 18px; vertical-align: middle; margin-right: 4px;">psychology</span>
                        Analysieren
                    </button>
                </div>
            </div>

            <!-- Step 2: Analyse -->
            <div class="wizard-panel" id="wizard-step-2" style="padding: 48px 24px; display: none; text-align: center;">
                <div class="wizard-loader">
                    <div class="wizard-spinner"></div>
                    <p id="wizard-status-text" style="margin-top: 20px; font-size: var(--d-fs-base); color: var(--text-primary);">Texte werden extrahiert...</p>
                    <p class="text-muted" style="margin-top: 8px; font-size: var(--d-fs-sm);">Dies kann einige Sekunden dauern</p>
                </div>
                <div id="wizard-error" style="display: none; margin-top: 24px;">
                    <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 16px; color: #991b1b;">
                        <span class="material-symbols-rounded" style="font-size: 20px; vertical-align: middle; margin-right: 6px;">error</span>
                        <span id="wizard-error-text"></span>
                    </div>
                    <button class="btn btn-secondary" onclick="goToWizardStep(1)" style="margin-top: 16px;">Zurück</button>
                </div>
            </div>

            <!-- Step 3: Profil prüfen -->
            <div class="wizard-panel" id="wizard-step-3" style="padding: 24px; display: none;">
                <div style="background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: var(--d-fs-sm); color: #166534;">
                    <span class="material-symbols-rounded" style="font-size: 18px; vertical-align: middle; margin-right: 6px;">check_circle</span>
                    Profil erfolgreich generiert. Passe die Felder bei Bedarf an.
                </div>

                <div class="form-group">
                    <label for="wiz-name">Name *</label>
                    <input type="text" id="wiz-name" required placeholder="z.B. Dr. Maria Schmidt">
                </div>

                <div class="form-group">
                    <label for="wiz-customer">Kunde</label>
                    <select id="wiz-customer">
                        <option value="">Global (alle Kunden)</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="wiz-writing-style">Schreibstil</label>
                    <textarea id="wiz-writing-style" rows="3" placeholder="z.B. Sachlich, faktenbasiert, mit persönlicher Note"></textarea>
                </div>

                <div class="form-group">
                    <label for="wiz-expertise">Expertise / Fachgebiete</label>
                    <textarea id="wiz-expertise" rows="2" placeholder="z.B. SEO, Content Marketing, Technologie"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="wiz-tone">Tonalität</label>
                        <select id="wiz-tone">
                            <option value="">-- Auswählen --</option>
                            <option value="formal">Formal</option>
                            <option value="informal">Informal</option>
                            <option value="professional">Professional</option>
                            <option value="friendly">Freundlich</option>
                            <option value="technical">Technisch</option>
                            <option value="casual">Locker</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="wiz-perspective">Perspektive</label>
                        <select id="wiz-perspective">
                            <option value="">-- Auswählen --</option>
                            <option value="Ich-Form">Ich-Form</option>
                            <option value="Wir-Form">Wir-Form</option>
                            <option value="Neutral">Neutral / Man-Form</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="wiz-example-text">Beispieltext</label>
                    <textarea id="wiz-example-text" rows="4" placeholder="Referenztext für Stilerkennung"></textarea>
                </div>

                <div class="form-group">
                    <label for="wiz-notes">Zusätzliche Hinweise</label>
                    <textarea id="wiz-notes" rows="2" placeholder="Besondere Anweisungen für die KI"></textarea>
                </div>

                <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 16px;">
                    <button type="button" class="btn btn-secondary" onclick="goToWizardStep(1)">Zurück zu Quellen</button>
                    <button type="button" class="btn btn-primary" onclick="saveWizardAuthor()" id="wizard-save-btn">
                        <span class="material-symbols-rounded" style="font-size: 18px; vertical-align: middle; margin-right: 4px;">save</span>
                        Speichern
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openAuthorModal(data = null) {
    const modal = document.getElementById('author-modal');
    const title = document.getElementById('author-modal-title');

    if (data) {
        title.textContent = 'Autor bearbeiten';
        document.getElementById('author-id').value = data.id;
        document.getElementById('author-name').value = data.name || '';
        document.getElementById('author-customer').value = data.customer_id || '';
        document.getElementById('author-writing-style').value = data.writing_style || '';
        document.getElementById('author-expertise').value = data.expertise || '';
        document.getElementById('author-tone').value = data.tone || '';
        document.getElementById('author-perspective').value = data.perspective || '';
        document.getElementById('author-example-text').value = data.example_text || '';
        document.getElementById('author-notes').value = data.notes || '';
        document.getElementById('author-active').checked = parseInt(data.is_active) === 1;
    } else {
        title.textContent = 'Neuer Autor';
        document.getElementById('author-id').value = '';
        document.getElementById('author-name').value = '';
        document.getElementById('author-customer').value = '';
        document.getElementById('author-writing-style').value = '';
        document.getElementById('author-expertise').value = '';
        document.getElementById('author-tone').value = '';
        document.getElementById('author-perspective').value = '';
        document.getElementById('author-example-text').value = '';
        document.getElementById('author-notes').value = '';
        document.getElementById('author-active').checked = true;
    }

    modal.classList.add('open');
}

function closeAuthorModal() {
    document.getElementById('author-modal').classList.remove('open');
}

async function editAuthor(id) {
    try {
        const response = await App.get('/admin/authors/' + id);
        openAuthorModal(response.data);
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

async function saveAuthor() {
    const id = document.getElementById('author-id').value;
    const name = document.getElementById('author-name').value.trim();

    if (!name) {
        App.showNotification('Name ist erforderlich', 'error');
        return;
    }

    const data = {
        name: name,
        customer_id: document.getElementById('author-customer').value || null,
        writing_style: document.getElementById('author-writing-style').value.trim(),
        expertise: document.getElementById('author-expertise').value.trim(),
        tone: document.getElementById('author-tone').value,
        perspective: document.getElementById('author-perspective').value,
        example_text: document.getElementById('author-example-text').value.trim(),
        notes: document.getElementById('author-notes').value.trim(),
        is_active: document.getElementById('author-active').checked ? 1 : 0
    };

    const btn = document.getElementById('author-save-btn');
    btn.disabled = true;

    try {
        if (id) {
            await App.put('/admin/authors/' + id, data);
            App.showNotification('Autorenprofil aktualisiert', 'success');
        } else {
            await App.post('/admin/authors', data);
            App.showNotification('Autorenprofil erstellt', 'success');
        }
        closeAuthorModal();
        location.reload();
    } catch (error) {
        App.showNotification(error.message, 'error');
    } finally {
        btn.disabled = false;
    }
}

async function deleteAuthor(id, name) {
    if (!confirm('Autorenprofil "' + name + '" wirklich löschen?')) return;

    try {
        await App.delete('/admin/authors/' + id);
        App.showNotification('Autorenprofil gelöscht', 'success');
        location.reload();
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

// Close modal on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAuthorModal();
        closeWizard();
    }
});

// Close modal on backdrop click
document.getElementById('author-modal').addEventListener('click', function(e) {
    if (e.target === this) closeAuthorModal();
});

// ===== Wizard Logic =====

let wizardFiles = [];
let wizardUrls = [];

function openAuthorWizard() {
    wizardFiles = [];
    wizardUrls = [];
    document.getElementById('wizard-file-list').innerHTML = '';
    document.getElementById('wizard-url-list').innerHTML = '';
    document.getElementById('wizard-url-input').value = '';
    document.getElementById('wizard-author-name').value = '';
    document.getElementById('wizard-customer').value = '';
    document.getElementById('wizard-file-input').value = '';
    goToWizardStep(1);
    document.getElementById('author-wizard-modal').classList.add('open');
}

function closeWizard() {
    document.getElementById('author-wizard-modal').classList.remove('open');
}

document.getElementById('author-wizard-modal').addEventListener('click', function(e) {
    if (e.target === this) closeWizard();
});

function goToWizardStep(step) {
    // Update step indicators
    document.querySelectorAll('.wizard-step').forEach(el => {
        const s = parseInt(el.dataset.step);
        el.classList.remove('active', 'completed');
        if (s === step) el.classList.add('active');
        else if (s < step) el.classList.add('completed');
    });

    // Show/hide panels
    document.querySelectorAll('.wizard-panel').forEach(el => el.style.display = 'none');
    document.getElementById('wizard-step-' + step).style.display = '';
}

// Drag & Drop
const dropzone = document.getElementById('wizard-dropzone');
const fileInput = document.getElementById('wizard-file-input');

dropzone.addEventListener('click', () => fileInput.click());

dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
});

dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('dragover');
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    handleWizardFiles(e.dataTransfer.files);
});

fileInput.addEventListener('change', () => {
    handleWizardFiles(fileInput.files);
    fileInput.value = '';
});

function handleWizardFiles(files) {
    const allowed = ['.pdf', '.docx', '.txt'];
    for (const file of files) {
        const ext = '.' + file.name.split('.').pop().toLowerCase();
        if (!allowed.includes(ext)) {
            App.showNotification(`${file.name}: Nicht unterstütztes Format`, 'error');
            continue;
        }
        if (file.size > 10 * 1024 * 1024) {
            App.showNotification(`${file.name}: Zu groß (max. 10 MB)`, 'error');
            continue;
        }
        // Duplikate vermeiden
        if (wizardFiles.some(f => f.name === file.name && f.size === file.size)) continue;
        wizardFiles.push(file);
    }
    renderWizardFiles();
}

function renderWizardFiles() {
    const list = document.getElementById('wizard-file-list');
    list.innerHTML = wizardFiles.map((f, i) => {
        const icon = f.name.endsWith('.pdf') ? 'picture_as_pdf' : f.name.endsWith('.docx') ? 'description' : 'text_snippet';
        const size = f.size < 1024 ? f.size + ' B' : f.size < 1048576 ? Math.round(f.size / 1024) + ' KB' : (f.size / 1048576).toFixed(1) + ' MB';
        return `<div class="wizard-file-item">
            <span class="material-symbols-rounded" style="font-size: 18px; color: var(--text-muted);">${icon}</span>
            <span style="flex: 1;">${f.name}</span>
            <span class="text-muted" style="font-size: var(--d-fs-sm);">${size}</span>
            <button class="btn-icon" onclick="removeWizardFile(${i})" title="Entfernen">
                <span class="material-symbols-rounded" style="font-size: 16px;">close</span>
            </button>
        </div>`;
    }).join('');
}

function removeWizardFile(index) {
    wizardFiles.splice(index, 1);
    renderWizardFiles();
}

function addWizardUrl() {
    const input = document.getElementById('wizard-url-input');
    const url = input.value.trim();
    if (!url) return;

    try {
        new URL(url);
    } catch {
        App.showNotification('Bitte eine gültige URL eingeben', 'error');
        return;
    }

    if (wizardUrls.includes(url)) {
        App.showNotification('URL bereits hinzugefügt', 'error');
        return;
    }

    wizardUrls.push(url);
    input.value = '';
    renderWizardUrls();
}

// Allow Enter to add URL
document.getElementById('wizard-url-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addWizardUrl();
    }
});

function renderWizardUrls() {
    const list = document.getElementById('wizard-url-list');
    list.innerHTML = wizardUrls.map((url, i) => {
        const display = url.length > 55 ? url.substring(0, 55) + '...' : url;
        return `<div class="wizard-file-item">
            <span class="material-symbols-rounded" style="font-size: 18px; color: var(--text-muted);">link</span>
            <span style="flex: 1; word-break: break-all;" title="${url}">${display}</span>
            <button class="btn-icon" onclick="removeWizardUrl(${i})" title="Entfernen">
                <span class="material-symbols-rounded" style="font-size: 16px;">close</span>
            </button>
        </div>`;
    }).join('');
}

function removeWizardUrl(index) {
    wizardUrls.splice(index, 1);
    renderWizardUrls();
}

function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            // Result ist data:...;base64,XXXX — wir senden alles, Backend strippt prefix
            resolve(reader.result);
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

async function runWizardAnalysis() {
    if (wizardFiles.length === 0 && wizardUrls.length === 0) {
        App.showNotification('Bitte mindestens ein Dokument oder eine URL hinzufügen', 'error');
        return;
    }

    // Switch to step 2
    goToWizardStep(2);
    document.getElementById('wizard-error').style.display = 'none';
    document.querySelector('.wizard-loader').style.display = '';
    document.getElementById('wizard-status-text').textContent = 'Texte werden extrahiert...';

    try {
        // Convert files to base64
        const statusEl = document.getElementById('wizard-status-text');
        const documents = [];
        for (let i = 0; i < wizardFiles.length; i++) {
            statusEl.textContent = `Datei ${i + 1}/${wizardFiles.length} wird gelesen...`;
            const data = await fileToBase64(wizardFiles[i]);
            documents.push({
                filename: wizardFiles[i].name,
                data: data
            });
        }

        statusEl.textContent = 'KI analysiert Schreibstil...';

        const response = await App.post('/admin/author-wizard', {
            documents: documents,
            urls: wizardUrls,
            author_name: document.getElementById('wizard-author-name').value.trim(),
            model: document.getElementById('wizard-model').value
        });

        const profile = response.data;

        statusEl.textContent = 'Profil wird erstellt...';

        // Fill step 3 form
        document.getElementById('wiz-name').value = profile.name || document.getElementById('wizard-author-name').value || '';
        document.getElementById('wiz-customer').value = document.getElementById('wizard-customer').value || '';
        document.getElementById('wiz-writing-style').value = profile.writing_style || '';
        document.getElementById('wiz-expertise').value = profile.expertise || '';
        document.getElementById('wiz-tone').value = profile.tone || '';
        document.getElementById('wiz-perspective').value = profile.perspective || '';
        document.getElementById('wiz-example-text').value = profile.example_text || '';
        document.getElementById('wiz-notes').value = profile.notes || '';

        // Go to step 3
        setTimeout(() => goToWizardStep(3), 400);

    } catch (error) {
        document.querySelector('.wizard-loader').style.display = 'none';
        document.getElementById('wizard-error').style.display = '';
        document.getElementById('wizard-error-text').textContent = error.message || 'Ein Fehler ist aufgetreten';
    }
}

async function saveWizardAuthor() {
    const name = document.getElementById('wiz-name').value.trim();
    if (!name) {
        App.showNotification('Name ist erforderlich', 'error');
        return;
    }

    const data = {
        name: name,
        customer_id: document.getElementById('wiz-customer').value || null,
        writing_style: document.getElementById('wiz-writing-style').value.trim(),
        expertise: document.getElementById('wiz-expertise').value.trim(),
        tone: document.getElementById('wiz-tone').value,
        perspective: document.getElementById('wiz-perspective').value,
        example_text: document.getElementById('wiz-example-text').value.trim(),
        notes: document.getElementById('wiz-notes').value.trim(),
        is_active: 1
    };

    const btn = document.getElementById('wizard-save-btn');
    btn.disabled = true;

    try {
        await App.post('/admin/authors', data);
        App.showNotification('Autorenprofil erstellt', 'success');
        closeWizard();
        location.reload();
    } catch (error) {
        App.showNotification(error.message, 'error');
    } finally {
        btn.disabled = false;
    }
}
</script>

<style>
.form-row {
    display: flex;
    gap: 16px;
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
.badge-primary {
    background: rgba(0, 0, 0, 0.08);
    color: #1a1a1a;
    font-weight: 600;
}

/* Wizard Styles */
.wizard-steps {
    display: flex;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
    padding: 0;
}
.wizard-step {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 16px 12px;
    font-size: var(--d-fs-sm);
    color: var(--text-muted, #6b7280);
    position: relative;
    transition: color 0.2s;
}
.wizard-step.active {
    color: var(--text-primary, #111);
    font-weight: 600;
}
.wizard-step.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--primary-color, #2563eb);
}
.wizard-step.completed {
    color: #166534;
}
.step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-size: var(--d-fs-xs);
    font-weight: 600;
    background: var(--bg-secondary, #f3f4f6);
    color: var(--text-muted, #6b7280);
    flex-shrink: 0;
}
.wizard-step.active .step-number {
    background: var(--primary-color, #2563eb);
    color: #fff;
}
.wizard-step.completed .step-number {
    background: #166534;
    color: #fff;
}
.step-label {
    display: inline;
}

.wizard-dropzone {
    border: 2px dashed var(--border-color, #d1d5db);
    border-radius: 8px;
    padding: 32px 16px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
}
.wizard-dropzone:hover,
.wizard-dropzone.dragover {
    border-color: var(--primary-color, #2563eb);
    background: rgba(37, 99, 235, 0.04);
}

.wizard-file-list,
.wizard-url-list {
    margin-top: 8px;
}
.wizard-file-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    background: var(--bg-secondary, #f9fafb);
    border-radius: 6px;
    margin-top: 4px;
    font-size: var(--d-fs-sm);
}

.wizard-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--border-color, #e5e7eb);
    border-top-color: var(--primary-color, #2563eb);
    border-radius: 50%;
    animation: wizard-spin 0.8s linear infinite;
    margin: 0 auto;
}
@keyframes wizard-spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 600px) {
    .step-label {
        display: none;
    }
    .wizard-step {
        padding: 12px 8px;
    }
}
</style>
