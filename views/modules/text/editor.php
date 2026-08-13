<div class="editor-container">
    <!-- Header -->
    <div class="editor-header">
        <div class="editor-header-left">
            <a href="/projects" class="back-link">&larr; Projekte</a>
            <input type="text" id="project-title" class="title-input"
                   value="<?= htmlspecialchars($project['title'] ?? 'Neues Projekt') ?>"
                   placeholder="Projekttitel...">
            <span id="save-status" class="save-status"></span>
        </div>
        <div class="editor-header-right">
            <span id="word-count" class="word-count">0 Wörter</span>
            <button class="btn btn-secondary btn-small" onclick="openRevisionsModal()" title="Versionen">
                <span class="material-symbols-rounded">history</span>
                <span id="version-info">v<?= $project['current_version'] ?? 1 ?></span>
            </button>
            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle">Export</button>
                <div class="dropdown-menu">
                    <a href="#" onclick="exportAs('text')">Plaintext</a>
                    <a href="#" onclick="exportAs('html')">HTML</a>
                    <a href="#" onclick="exportAs('markdown')">Markdown</a>
                    <a href="#" onclick="exportAs('docx')">Word (.docx)</a>
                    <div class="dropdown-divider"></div>
                    <a href="#" onclick="openSaveToKnowledgeModal()">Zur Wissensdatenbank</a>
                </div>
            </div>
            <button class="btn btn-primary" onclick="saveProject()" id="save-btn">Speichern</button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="editor-main">
        <!-- Left Sidebar: Generator & Chat (breiter) -->
        <div class="editor-sidebar">
            <!-- Tab Navigation -->
            <div class="sidebar-tabs">
                <button class="sidebar-tab active" onclick="switchTab('generator')">Generator</button>
                <button class="sidebar-tab" onclick="switchTab('chat')">Chat</button>
            </div>

            <!-- Generator Tab -->
            <div class="tab-content active" id="tab-generator">
                <div class="generator-form">
                    <!-- Kunde -->
                    <?php if (!empty($customers)): ?>
                    <div class="form-group">
                        <label for="customer-select">Kunde *</label>
                        <select id="customer-select" onchange="onCustomerChange()">
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['id'] ?>" <?= ($project['customer_id'] ?? '') == $customer['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($customer['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Thema / Titel -->
                    <div class="form-group topic-group">
                        <div class="topic-header">
                            <label for="topic-input">Thema / Titel *</label>
                            <button type="button" class="btn-optimize" onclick="optimizeTopic()" id="optimize-btn" title="Thema mit KI optimieren">
                                <span class="material-symbols-rounded">auto_fix_high</span>
                                <span class="btn-text">Optimieren</span>
                            </button>
                        </div>
                        <textarea id="topic-input" class="topic-input" rows="8" oninput="triggerAutoSave()"
                                  placeholder="Thema eingeben oder Stichworte auflisten..."><?= htmlspecialchars($projectMetadata['topic'] ?? '') ?></textarea>
                    </div>

                    <!-- Dokument Upload -->
                    <div class="form-group">
                        <div class="file-dropzone" id="topic-dropzone">
                            <input type="file" id="topic-file-input" accept=".pdf,.docx,.txt" style="display:none" onchange="handleFileSelect(this)">
                            <span class="material-symbols-rounded">upload_file</span>
                            <span class="dropzone-text">Dokument laden (PDF, DOCX, TXT)</span>
                            <button type="button" class="btn-browse" onclick="document.getElementById('topic-file-input').click()">Durchsuchen</button>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="gen-sections">Abschnitte</label>
                            <input type="number" id="gen-sections" min="2" max="20"
                                   value="<?= (int)($projectMetadata['sections_count'] ?? 5) ?>"
                                   onchange="triggerAutoSave()">
                        </div>
                        <div class="form-group">
                            <label for="gen-words">Wörter insgesamt</label>
                            <select id="gen-words" onchange="triggerAutoSave()">
                                <option value="500" <?= ($project['target_word_count'] ?? 1000) == 500 ? 'selected' : '' ?>>~500</option>
                                <option value="1000" <?= ($project['target_word_count'] ?? 1000) == 1000 ? 'selected' : '' ?>>~1000</option>
                                <option value="1500" <?= ($project['target_word_count'] ?? 1000) == 1500 ? 'selected' : '' ?>>~1500</option>
                                <option value="2000" <?= ($project['target_word_count'] ?? 1000) == 2000 ? 'selected' : '' ?>>~2000</option>
                                <option value="3000" <?= ($project['target_word_count'] ?? 1000) == 3000 ? 'selected' : '' ?>>~3000</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="gen-style">Stil</label>
                            <select id="gen-style" onchange="triggerAutoSave()">
                                <?php if (!empty($styles)): ?>
                                    <?php foreach ($styles as $style): ?>
                                        <option value="<?= htmlspecialchars($style['slug']) ?>" <?= ($projectMetadata['style'] ?? '') === $style['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($style['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="informativ">Informativ</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gen-model">Modell</label>
                            <select id="gen-model" onchange="triggerAutoSave()">
                                <?php if (!empty($aiModels)): ?>
                                    <?php foreach ($aiModels as $model): ?>
                                        <option value="<?= htmlspecialchars($model['model_id']) ?>" <?= ($projectMetadata['model'] ?? '') === $model['model_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($model['display_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="gpt-4">GPT-4</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <button class="btn btn-primary btn-block btn-generate" onclick="generateArticle()" id="generate-btn">
                        <span class="material-symbols-rounded">auto_awesome</span>
                        Artikel generieren
                    </button>

                    <!-- Projekt-Status (eingeklappt) -->
                    <div class="sidebar-section collapsible collapsed" id="project-settings">
                        <div class="sidebar-section-header" onclick="toggleSidebarSection(this)">
                            <span>Status</span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        <div class="sidebar-section-content">
                            <div class="form-group">
                                <select id="project-status" onchange="triggerAutoSave()">
                                    <option value="draft" <?= ($project['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Entwurf</option>
                                    <option value="in_progress" <?= ($project['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Arbeit</option>
                                    <option value="review" <?= ($project['status'] ?? '') === 'review' ? 'selected' : '' ?>>Review</option>
                                    <option value="completed" <?= ($project['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Fertig</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Regeln (eingeklappt) -->
                    <div class="sidebar-section collapsible collapsed" id="rules-section">
                        <div class="sidebar-section-header" onclick="toggleSidebarSection(this)">
                            <span>Regeln</span>
                            <span class="badge" id="rules-count"><?= count($selectedRules ?? []) ?></span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        <div class="sidebar-section-content">
                            <div class="rules-list" id="rules-list">
                                <?php if (!empty($availableRules)): ?>
                                    <?php foreach ($availableRules as $rule): ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="rules[]" value="<?= $rule['id'] ?>"
                                                   <?= in_array($rule['id'], $selectedRules ?? []) ? 'checked' : '' ?>
                                                   onchange="updateRulesCount(); triggerAutoSave()">
                                            <span class="rule-name"><?= htmlspecialchars($rule['name']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-small">Keine Regeln verfügbar</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Wissensdatenbank (eingeklappt) -->
                    <div class="sidebar-section collapsible collapsed" id="knowledge-section">
                        <div class="sidebar-section-header" onclick="toggleSidebarSection(this)">
                            <span>Wissensdatenbank</span>
                            <span class="badge" id="knowledge-count"><?= count($selectedKnowledge ?? []) ?></span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        <div class="sidebar-section-content">
                            <div class="knowledge-list" id="knowledge-list">
                                <?php if (!empty($availableKnowledge)): ?>
                                    <?php foreach ($availableKnowledge as $entry): ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="knowledge[]" value="<?= $entry['id'] ?>"
                                                   <?= in_array($entry['id'], $selectedKnowledge ?? []) ? 'checked' : '' ?>
                                                   onchange="updateKnowledgeCount(); triggerAutoSave()">
                                            <span class="knowledge-title"><?= htmlspecialchars($entry['title'] ?: 'Ohne Titel') ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-small">Keine Einträge verfügbar</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat Tab -->
            <div class="tab-content" id="tab-chat">
                <div class="chat-container">
                    <div class="chat-header">
                        <h3>KI-Assistent</h3>
                        <select id="model-select" class="model-select">
                            <?php if (!empty($aiModels)): ?>
                                <?php foreach ($aiModels as $model): ?>
                                    <option value="<?= htmlspecialchars($model['model_id']) ?>">
                                        <?= htmlspecialchars($model['display_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="gpt-4">GPT-4</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="chat-messages" id="chat-messages">
                        <div class="chat-message assistant">
                            <p>Ich kann dir helfen, einzelne Abschnitte zu verbessern oder umzuschreiben.</p>
                        </div>
                    </div>

                    <div class="chat-input-container">
                        <textarea id="chat-input" class="chat-input"
                                  placeholder="Schreibe eine Nachricht..."
                                  rows="3"></textarea>
                        <button class="btn btn-primary" onclick="sendMessage()">Senden</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Editor Content (Hauptbereich) -->
        <div class="editor-content">
            <div class="sections-container" id="sections-container">
                <?php if (!empty($project['sections'])): ?>
                    <?php foreach ($project['sections'] as $index => $section): ?>
                        <div class="section" data-id="<?= $section['id'] ?>" data-order="<?= $section['section_order'] ?>" draggable="true">
                            <div class="section-header">
                                <div class="drag-handle" title="Ziehen zum Sortieren">
                                    <span class="material-symbols-rounded">drag_indicator</span>
                                </div>
                                <span class="section-number"><?= $index + 1 ?></span>
                                <select class="heading-level" onchange="updateHeading(this); triggerAutoSave();">
                                    <option value="h1" <?= $section['heading_level'] === 'h1' ? 'selected' : '' ?>>H1</option>
                                    <option value="h2" <?= $section['heading_level'] === 'h2' ? 'selected' : '' ?>>H2</option>
                                    <option value="h3" <?= $section['heading_level'] === 'h3' ? 'selected' : '' ?>>H3</option>
                                </select>
                                <input type="text" class="heading-input" value="<?= htmlspecialchars($section['heading_text'] ?? '') ?>"
                                       placeholder="Überschrift..." oninput="triggerAutoSave()">
                                <div class="section-actions">
                                    <button class="btn-icon" onclick="regenerateSection(this)" title="Neu generieren"><span class="material-symbols-rounded">refresh</span></button>
                                    <button class="btn-icon btn-danger" onclick="deleteSection(this)" title="Löschen"><span class="material-symbols-rounded">delete</span></button>
                                </div>
                            </div>
                            <textarea class="section-content" placeholder="Inhalt..." oninput="autoResize(this); triggerAutoSave();"><?= htmlspecialchars($section['content'] ?? '') ?></textarea>
                            <div class="section-footer">
                                <span class="section-words"><?= $section['word_count'] ?? 0 ?> Wörter</span>
                                <div class="feedback-buttons">
                                    <button class="feedback-btn negative" onclick="giveFeedback(this, 'negative')" title="Nicht gut - Regel vorschlagen"><span class="material-symbols-rounded">thumb_down</span></button>
                                    <button class="feedback-btn neutral" onclick="regenerateSection(this)" title="Neu generieren"><span class="material-symbols-rounded">refresh</span></button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Starte mit dem Artikel-Generator oder füge manuell Abschnitte hinzu.</p>
                    </div>
                <?php endif; ?>
            </div>

            <button class="add-section-btn" onclick="addSection()">
                <span>+</span> Abschnitt hinzufügen
            </button>
        </div>
    </div>
</div>

<input type="hidden" id="project-id" value="<?= $project['id'] ?? '' ?>">
<input type="hidden" id="csrf-token" value="<?= htmlspecialchars($csrfToken) ?>">
<input type="hidden" id="generation-status" value="<?= htmlspecialchars($project['generation_status'] ?? 'idle') ?>">
<input type="hidden" id="current-job-id" value="<?= htmlspecialchars($project['current_job_id'] ?? '') ?>">

<!-- Generation Progress Overlay -->
<div class="generation-overlay" id="generation-overlay">
    <div class="generation-modal">
        <div class="generation-header">
            <span class="material-symbols-rounded spinning">autorenew</span>
            <h3 id="generation-title">Artikel wird generiert...</h3>
        </div>
        <div class="generation-progress">
            <div class="progress-bar">
                <div class="progress-fill" id="generation-progress-fill"></div>
            </div>
            <div class="progress-text" id="generation-progress-text">Warte auf Verarbeitung...</div>
        </div>
        <div class="generation-info">
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value" id="generation-status-text">In Warteschlange</span>
            </div>
            <div class="info-row" id="queue-position-row">
                <span class="info-label">Position:</span>
                <span class="info-value" id="generation-queue-position">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">Versuch:</span>
                <span class="info-value" id="generation-attempts">1/3</span>
            </div>
        </div>
        <div class="generation-actions">
            <a href="/projects" class="btn btn-outline" id="back-to-projects-btn">
                Zurück zu Projekten
            </a>
            <button class="btn btn-secondary" onclick="cancelGeneration()" id="cancel-generation-btn">
                Abbrechen
            </button>
        </div>
        <div class="generation-hint">
            <small>Die Generierung läuft im Hintergrund weiter, auch wenn du die Seite verlässt.</small>
        </div>
    </div>
</div>

<!-- Modal: Revisionen -->
<div class="modal" id="revisions-modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2>Versionshistorie</h2>
            <button class="modal-close" onclick="closeRevisionsModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="revisions-container">
                <div class="revisions-list" id="revisions-list">
                    <p class="text-muted">Lade Versionen...</p>
                </div>
                <div class="revision-preview" id="revision-preview">
                    <p class="text-muted">Wähle eine Version zum Anzeigen</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Negatives Feedback -->
<div class="modal" id="feedback-modal">
    <div class="modal-content modal-md">
        <div class="modal-header">
            <h2>Was war nicht gut?</h2>
            <button class="modal-close" onclick="closeFeedbackModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="feedback-section-id">
            <input type="hidden" id="feedback-section-content">

            <div class="form-group">
                <label for="feedback-text">Beschreibe konkret, was dir nicht gefallen hat:</label>
                <textarea id="feedback-text" rows="4"
                          placeholder="z.B. 'Der Text klingt zu werblich' oder 'Es fehlen konkrete Beispiele'"></textarea>
                <small>Je genauer du beschreibst, desto besser kann die KI daraus lernen.</small>
            </div>

            <div class="feedback-examples">
                <span class="examples-label">Beispiele:</span>
                <button type="button" class="example-chip" onclick="setFeedbackExample('Der Text klingt zu werblich')">Zu werblich</button>
                <button type="button" class="example-chip" onclick="setFeedbackExample('Die Sätze sind zu lang')">Zu lange Sätze</button>
                <button type="button" class="example-chip" onclick="setFeedbackExample('Es fehlen Beispiele')">Fehlende Beispiele</button>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeFeedbackModal()">Abbrechen</button>
            <button class="btn btn-primary" onclick="analyzeFeedback()" id="analyze-feedback-btn">
                <span class="btn-text">Analysieren & Regel vorschlagen</span>
                <span class="btn-loading hidden">Analysiere...</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Regel-Vorschlag -->
<div class="modal" id="rule-suggestion-modal">
    <div class="modal-content modal-md">
        <div class="modal-header">
            <h2>Regel-Vorschlag</h2>
            <button class="modal-close" onclick="closeRuleSuggestionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="suggestion-feedback-id">
            <div class="suggestion-card">
                <div class="form-group">
                    <label>Regel-Name</label>
                    <input type="text" id="suggestion-name" class="suggestion-input">
                </div>
                <div class="form-group">
                    <label>Regel-Inhalt</label>
                    <textarea id="suggestion-content" rows="3" class="suggestion-input"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Typ</label>
                        <select id="suggestion-type" name="rule_type_id">
                            <?php if (!empty($ruleTypes)): ?>
                                <?php foreach ($ruleTypes as $type): ?>
                                <option value="<?= $type['id'] ?>" data-slug="<?= htmlspecialchars($type['slug']) ?>">
                                    <?= htmlspecialchars($type['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">Stil</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kategorie</label>
                        <select id="suggestion-category" name="category_id">
                            <option value="">-- Keine --</option>
                            <?php if (!empty($ruleCategories)): ?>
                                <?php foreach ($ruleCategories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="suggestion-explanation" id="suggestion-explanation"></div>
            </div>
            <div class="scope-selection">
                <label class="scope-label">Wo soll diese Regel gelten?</label>
                <div class="scope-options">
                    <label class="scope-option">
                        <input type="radio" name="rule-scope" value="customer" checked>
                        <span class="scope-box">
                            <span class="scope-title">Nur dieser Kunde</span>
                        </span>
                    </label>
                    <label class="scope-option">
                        <input type="radio" name="rule-scope" value="global">
                        <span class="scope-box">
                            <span class="scope-title">Alle Kunden</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeRuleSuggestionModal()">Verwerfen</button>
            <button class="btn btn-primary" onclick="applyRule()" id="apply-rule-btn">Regel übernehmen</button>
        </div>
    </div>
</div>

<!-- Modal: Zur Wissensdatenbank speichern -->
<div class="modal" id="save-knowledge-modal">
    <div class="modal-content modal-md">
        <div class="modal-header">
            <h2>Zur Wissensdatenbank hinzufügen</h2>
            <button class="modal-close" onclick="closeSaveKnowledgeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="kb-title">Titel *</label>
                <input type="text" id="kb-title" placeholder="z.B. Produktbeschreibung XY">
            </div>
            <?php if (!empty($customers)): ?>
            <div class="form-group">
                <label for="kb-customer">Kunde *</label>
                <select id="kb-customer">
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="kb-category">Kategorie *</label>
                <select id="kb-category">
                    <?php if (!empty($knowledgeCategories)): ?>
                        <?php foreach ($knowledgeCategories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="kb-auto-vectorize" checked>
                    <span>Automatisch vektorisieren</span>
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeSaveKnowledgeModal()">Abbrechen</button>
            <button class="btn btn-primary" onclick="saveToKnowledge()" id="save-knowledge-btn">Speichern</button>
        </div>
    </div>
</div>

<?php if (!empty($customerData)): ?>
<script>
const customerData = <?= json_encode($customerData) ?>;
</script>
<?php endif; ?>

<style>
.editor-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 32px);
    margin: calc(-1 * var(--spacing-xl));
    background: var(--color-gray-50);
}
.editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md) var(--spacing-lg);
    background: white;
    border-bottom: 1px solid var(--color-gray-200);
    gap: var(--spacing-md);
}
.editor-header-left, .editor-header-right {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}
.back-link {
    color: var(--color-gray-500);
    font-size: var(--d-fs-sm);
    white-space: nowrap;
}
.title-input {
    border: none;
    font-size: var(--d-fs-xl);
    font-weight: 600;
    padding: var(--spacing-sm);
    background: transparent;
    min-width: 250px;
    flex: 1;
}
.title-input:focus {
    outline: none;
    background: var(--color-gray-100);
    border-radius: var(--radius-sm);
}
.save-status {
    font-size: var(--d-fs-xs);
    color: var(--color-gray-500);
    white-space: nowrap;
}
.save-status.saving {
    color: var(--color-primary);
}
.save-status.saved {
    color: #22c55e;
}
.save-status.error {
    color: #ef4444;
}
.word-count {
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
    white-space: nowrap;
}

.editor-main {
    display: grid;
    grid-template-columns: 2fr 3fr;
    flex: 1;
    overflow: hidden;
}

/* Left Sidebar - Generator */
.editor-sidebar {
    border-right: 1px solid var(--color-gray-200);
    background: white;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Sidebar Sections (collapsed panels) */
.sidebar-section {
    border-top: 1px solid var(--color-gray-100);
}
.sidebar-section-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-sm) 0;
    cursor: pointer;
    font-size: var(--d-fs-sm);
    font-weight: 500;
    color: var(--color-gray-600);
}
.sidebar-section-header:hover {
    color: var(--color-gray-900);
}
.sidebar-section-header .badge {
    background: var(--color-black);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: var(--d-fs-xs);
}
.sidebar-section-header .toggle-icon {
    margin-left: auto;
    font-size: var(--d-fs-xs);
    transition: transform 0.2s;
}
.sidebar-section.collapsed .toggle-icon {
    transform: rotate(-90deg);
}
.sidebar-section.collapsed .sidebar-section-content {
    display: none;
}
.sidebar-section-content {
    padding-bottom: var(--spacing-sm);
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    padding: var(--spacing-xs) 0;
    font-size: var(--d-fs-xs);
    cursor: pointer;
}
.rules-list, .knowledge-list {
    max-height: 150px;
    overflow-y: auto;
}
.rule-name, .knowledge-title {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.badge-small {
    font-size: var(--d-fs-xs);
    padding: 1px 4px;
    border-radius: 3px;
    background: var(--color-gray-200);
    color: var(--color-gray-600);
}

/* Editor Content */
.editor-content {
    overflow-y: auto;
    padding: var(--spacing-lg);
}
.sections-container {
    max-width: 800px;
    margin: 0 auto;
}
.section {
    background: white;
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-md);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.section-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--color-gray-100);
}
.drag-handle {
    cursor: grab;
    color: var(--color-gray-400);
    display: flex;
    align-items: center;
    padding: 4px;
    margin: -4px;
    border-radius: var(--radius-sm);
    transition: all 0.15s ease;
}
.drag-handle:hover {
    color: var(--color-gray-600);
    background: var(--color-gray-100);
}
.drag-handle:active {
    cursor: grabbing;
}
.section-number {
    min-width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-gray-900);
    color: white;
    font-size: var(--d-fs-xs);
    font-weight: 600;
    border-radius: 50%;
}
.section.dragging {
    opacity: 0.5;
    background: var(--color-gray-100);
}
.section.drag-over {
    border-top: 3px solid var(--color-black);
    margin-top: -3px;
}
.heading-level {
    padding: 4px 8px;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-xs);
}
.heading-input {
    flex: 1;
    border: none;
    font-size: var(--d-fs-base);
    font-weight: 600;
    padding: var(--spacing-xs);
}
.heading-input:focus {
    outline: none;
    background: var(--color-gray-50);
}
.section-actions {
    display: flex;
    gap: var(--spacing-xs);
}
.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-gray-400);
    padding: 4px;
}
.btn-icon:hover {
    color: var(--color-gray-700);
}
.section-content {
    width: 100%;
    min-height: 80px;
    border: none;
    padding: var(--spacing-md);
    font-size: var(--d-fs-sm);
    line-height: 1.7;
    resize: none;
    overflow: hidden;
}
.section-content:focus {
    outline: none;
}
.section-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-sm) var(--spacing-md);
    border-top: 1px solid var(--color-gray-100);
    font-size: var(--d-fs-xs);
    color: var(--color-gray-500);
}
.feedback-buttons {
    display: flex;
    gap: var(--spacing-xs);
}
.feedback-btn {
    background: none;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    padding: 4px 8px;
    cursor: pointer;
}
.feedback-btn:hover {
    background: var(--color-gray-100);
}
.add-section-btn {
    width: 100%;
    max-width: 800px;
    margin: 0 auto;
    padding: var(--spacing-md);
    background: white;
    border: 2px dashed var(--color-gray-300);
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
}
.add-section-btn:hover {
    border-color: var(--color-gray-400);
    background: var(--color-gray-50);
}
.empty-state {
    text-align: center;
    padding: var(--spacing-2xl);
    color: var(--color-gray-500);
}

/* Sidebar Tabs */
.sidebar-tabs {
    display: flex;
    border-bottom: 1px solid var(--color-gray-200);
    flex-shrink: 0;
}
.sidebar-tab {
    flex: 1;
    padding: var(--spacing-sm) var(--spacing-md);
    background: none;
    border: none;
    font-size: var(--d-fs-sm);
    font-weight: 500;
    color: var(--color-gray-500);
    cursor: pointer;
    border-bottom: 2px solid transparent;
}
.sidebar-tab:hover {
    background: var(--color-gray-50);
}
.sidebar-tab.active {
    color: var(--color-black);
    border-bottom-color: var(--color-black);
}
.tab-content {
    display: none;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}
.tab-content.active {
    display: flex;
}
.generator-form {
    padding: var(--spacing-lg);
    overflow-y: auto;
    flex: 1;
}
.generator-form .form-group {
    margin-bottom: var(--spacing-md);
}
.generator-form label {
    display: block;
    font-size: var(--d-fs-sm);
    font-weight: 500;
    margin-bottom: var(--spacing-xs);
    color: var(--color-gray-700);
}

/* Topic Input */
.topic-group {
    margin-bottom: var(--spacing-sm) !important;
}
.topic-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-xs);
}
.topic-header label {
    margin-bottom: 0;
}
.btn-optimize {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: var(--color-gray-100);
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-xs);
    font-weight: 500;
    color: var(--color-gray-700);
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-optimize:hover {
    background: var(--color-gray-200);
    border-color: var(--color-gray-400);
}
.btn-optimize:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.btn-optimize .material-symbols-rounded {
    font-size: var(--d-fs-base);
}
.topic-input {
    width: 100%;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-md);
    padding: var(--spacing-md);
    font-size: var(--d-fs-sm);
    line-height: 1.5;
    resize: vertical;
    min-height: 160px;
}
.topic-input:focus {
    outline: none;
    border-color: var(--color-black);
}

/* File Dropzone - Slim */
.file-dropzone {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px dashed var(--color-gray-300);
    border-radius: var(--radius-sm);
    background: var(--color-gray-50);
    cursor: pointer;
    transition: all 0.2s ease;
}
.file-dropzone:hover {
    border-color: var(--color-gray-400);
    background: var(--color-gray-100);
}
.file-dropzone.drag-over {
    border-color: var(--color-primary);
    border-style: solid;
    background: rgba(59, 130, 246, 0.08);
}
.file-dropzone .material-symbols-rounded {
    font-size: var(--d-fs-xl);
    color: var(--color-gray-500);
}
.file-dropzone .dropzone-text {
    flex: 1;
    font-size: var(--d-fs-sm);
    color: var(--color-gray-600);
}
.file-dropzone .btn-browse {
    padding: 4px 10px;
    background: white;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-xs);
    color: var(--color-gray-700);
    cursor: pointer;
}
.file-dropzone .btn-browse:hover {
    background: var(--color-gray-100);
}

.form-row {
    display: flex;
    gap: var(--spacing-sm);
}
.form-row .form-group {
    flex: 1;
}
.generator-form select {
    width: 100%;
    padding: var(--spacing-sm);
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-sm);
}
.btn-block {
    width: 100%;
    padding: var(--spacing-md);
}
.btn-generate {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    font-size: var(--d-fs-sm);
    margin-top: var(--spacing-md);
}
.btn-generate .material-symbols-rounded {
    font-size: var(--d-fs-xl);
}

/* Chat */
.chat-container {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.chat-header {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--color-gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.chat-header h3 {
    margin: 0;
    font-size: var(--d-fs-sm);
}
.model-select {
    font-size: var(--d-fs-xs);
    padding: 4px 8px;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
}
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: var(--spacing-md);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}
.chat-message {
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--radius-md);
    max-width: 90%;
}
.chat-message.user {
    background: var(--color-black);
    color: white;
    align-self: flex-end;
}
.chat-message.assistant {
    background: var(--color-gray-100);
    align-self: flex-start;
}
.chat-message p {
    margin: 0;
    font-size: var(--d-fs-sm);
    line-height: 1.5;
}
.chat-input-container {
    padding: var(--spacing-md);
    border-top: 1px solid var(--color-gray-200);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}
.chat-input {
    resize: none;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    padding: var(--spacing-sm);
    font-size: var(--d-fs-sm);
}

/* Dropdown */
.dropdown {
    position: relative;
}
.dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    box-shadow: var(--glass-shadow);
    z-index: 100;
    min-width: 150px;
}
.dropdown:hover .dropdown-menu {
    display: block;
}
.dropdown-menu a {
    display: block;
    padding: var(--spacing-sm) var(--spacing-md);
    color: var(--color-gray-700);
    font-size: var(--d-fs-sm);
}
.dropdown-menu a:hover {
    background: var(--color-gray-100);
}
.dropdown-divider {
    height: 1px;
    background: var(--color-gray-200);
    margin: 4px 0;
}

/* Modals */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal.active {
    display: flex;
}
.modal-content {
    background: white;
    border-radius: var(--radius-lg);
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.modal-md {
    max-width: 500px;
}
.modal-lg {
    max-width: 900px;
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
    font-size: var(--d-fs-lg);
}
.modal-close {
    background: none;
    border: none;
    font-size: var(--d-fs-2xl);
    cursor: pointer;
    color: var(--color-gray-400);
}
.modal-body {
    padding: var(--spacing-lg);
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-sm);
    padding: var(--spacing-lg);
    border-top: 1px solid var(--color-gray-200);
}

/* Revisions */
.revisions-container {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: var(--spacing-lg);
    min-height: 400px;
}
.revisions-list {
    border-right: 1px solid var(--color-gray-200);
    padding-right: var(--spacing-lg);
    overflow-y: auto;
    max-height: 500px;
}
.revision-item {
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--radius-sm);
    cursor: pointer;
    margin-bottom: var(--spacing-xs);
    border: 1px solid var(--color-gray-200);
}
.revision-item:hover {
    background: var(--color-gray-50);
}
.revision-item.active {
    background: var(--color-black);
    color: white;
    border-color: var(--color-black);
}
.revision-item .revision-version {
    font-weight: 600;
    font-size: var(--d-fs-sm);
}
.revision-item .revision-date {
    font-size: var(--d-fs-xs);
    opacity: 0.7;
}
.revision-item .revision-words {
    font-size: var(--d-fs-xs);
    opacity: 0.6;
}
.revision-preview {
    overflow-y: auto;
    max-height: 500px;
}
.revision-preview-content {
    font-size: var(--d-fs-sm);
    line-height: 1.6;
}
.revision-preview-content h1,
.revision-preview-content h2,
.revision-preview-content h3 {
    margin: var(--spacing-md) 0 var(--spacing-sm) 0;
}
.revision-actions {
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--color-gray-200);
}

/* Other styles */
.suggestion-card {
    background: var(--color-gray-50);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}
.suggestion-input {
    width: 100%;
    padding: var(--spacing-sm);
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-sm);
}
.suggestion-explanation {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: var(--radius-sm);
    padding: var(--spacing-sm);
    font-size: var(--d-fs-sm);
    color: #92400e;
    margin-top: var(--spacing-md);
}
.suggestion-explanation:empty {
    display: none;
}
.scope-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-md);
}
.scope-option input {
    display: none;
}
.scope-box {
    display: block;
    padding: var(--spacing-md);
    background: white;
    border: 2px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    text-align: center;
    cursor: pointer;
}
.scope-option input:checked + .scope-box {
    border-color: var(--color-black);
    background: var(--color-gray-50);
}
.feedback-examples {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-md);
}
.example-chip {
    background: var(--color-gray-100);
    border: 1px solid var(--color-gray-200);
    border-radius: 20px;
    padding: 4px 12px;
    font-size: var(--d-fs-xs);
    cursor: pointer;
}
.example-chip:hover {
    background: var(--color-gray-200);
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    cursor: pointer;
}
.hidden {
    display: none !important;
}
.btn-loading {
    position: relative;
}

/* Generation Overlay */
.generation-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}
.generation-overlay.active {
    display: flex;
}
.generation-modal {
    background: white;
    border-radius: var(--radius-lg);
    padding: var(--spacing-xl);
    width: 90%;
    max-width: 450px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.generation-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}
.generation-header h3 {
    margin: 0;
    font-size: var(--d-fs-lg);
}
.generation-header .material-symbols-rounded {
    font-size: var(--d-fs-2xl);
    color: var(--color-primary);
}
.spinning {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.generation-progress {
    margin-bottom: var(--spacing-lg);
}
.progress-bar {
    height: 8px;
    background: var(--color-gray-200);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: var(--spacing-sm);
}
.progress-fill {
    height: 100%;
    background: var(--color-black);
    width: 0%;
    transition: width 0.3s ease;
    border-radius: 4px;
}
.progress-text {
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
    text-align: center;
}
.generation-info {
    background: var(--color-gray-50);
    border-radius: var(--radius-md);
    padding: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}
.info-row {
    display: flex;
    justify-content: space-between;
    padding: var(--spacing-xs) 0;
    font-size: var(--d-fs-sm);
}
.info-label {
    color: var(--color-gray-500);
}
.info-value {
    font-weight: 500;
}
.generation-actions {
    display: flex;
    justify-content: center;
    gap: var(--spacing-sm);
}
.generation-actions .btn {
    min-width: 120px;
}
.btn-outline {
    background: transparent;
    border: 1px solid var(--color-gray-300);
    color: var(--color-gray-700);
}
.btn-outline:hover {
    background: var(--color-gray-100);
    border-color: var(--color-gray-400);
}
.generation-hint {
    text-align: center;
    margin-top: var(--spacing-md);
    color: var(--color-gray-500);
}

/* Status Colors */
.status-pending { color: #f59e0b; }
.status-processing { color: #3b82f6; }
.status-completed { color: #22c55e; }
.status-failed { color: #ef4444; }

/* Minimized Overlay - Small progress indicator at bottom */
.generation-overlay.minimized {
    align-items: flex-end;
    justify-content: center;
    background: transparent;
    backdrop-filter: none;
    pointer-events: none;
}
.generation-overlay.minimized .generation-modal {
    margin-bottom: var(--spacing-lg);
    padding: var(--spacing-md) var(--spacing-lg);
    max-width: 350px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    pointer-events: auto;
    cursor: pointer;
}
.generation-overlay.minimized .generation-modal:hover {
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}
.generation-overlay.minimized .generation-info,
.generation-overlay.minimized .generation-actions,
.generation-overlay.minimized .generation-hint {
    display: none;
}
.generation-overlay.minimized .generation-header {
    margin-bottom: var(--spacing-sm);
}
.generation-overlay.minimized .generation-header h3 {
    font-size: var(--d-fs-sm);
}
.generation-overlay.minimized .generation-progress {
    margin-bottom: 0;
}

/* Skeleton Loading Sections */
.section.skeleton-section {
    opacity: 0.9;
}
.section.skeleton-section .drag-handle.disabled {
    opacity: 0.3;
    cursor: default;
}
.section.skeleton-section .heading-input {
    background: var(--color-gray-50);
}
.section.skeleton-section .skeleton-status {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: var(--d-fs-xs);
    color: var(--color-gray-500);
}
.section.skeleton-section .skeleton-status .material-symbols-rounded {
    font-size: var(--d-fs-base);
    color: var(--color-primary);
}
.section-content-skeleton {
    padding: var(--spacing-md);
    min-height: 120px;
}
.skeleton-loader {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}
.skeleton-line {
    height: 12px;
    background: linear-gradient(90deg, var(--color-gray-100) 25%, var(--color-gray-200) 50%, var(--color-gray-100) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 4px;
}
.skeleton-line.short {
    width: 60%;
}
.skeleton-line.medium {
    width: 80%;
}
@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
@keyframes fadeInSection {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.section.filled {
    animation: fadeInSection 0.3s ease;
}

/* Regenerate Section Animation */
.section.regenerating {
    position: relative;
    border-left: 3px solid var(--color-primary);
}
.section.regenerating::after {
    content: 'Wird neu generiert...';
    position: absolute;
    top: var(--spacing-sm);
    right: var(--spacing-sm);
    background: var(--color-primary);
    color: white;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-xs);
    font-weight: 500;
    animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.regenerate-skeleton {
    padding: var(--spacing-md);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
    min-height: 120px;
}
.section.regenerate-success {
    animation: successFlash 0.6s ease;
}
@keyframes successFlash {
    0% { background: white; }
    30% { background: rgba(34, 197, 94, 0.15); }
    100% { background: white; }
}

/* Currently generating indicator */
.section.skeleton-section[data-generating="true"] .skeleton-status .status-text {
    color: var(--color-primary);
    font-weight: 500;
}
.section.skeleton-section[data-generating="true"] {
    border-left: 3px solid var(--color-primary);
}

@media (max-width: 1000px) {
    .editor-main {
        grid-template-columns: 1fr;
    }
    .editor-sidebar {
        display: none;
    }
}
</style>

<script>
let projectId = document.getElementById('project-id').value;
let sectionCounter = <?= count($project['sections'] ?? []) ?>;
let autoSaveTimeout = null;
let lastSavedContent = '';
let isSaving = false;

// ==========================================
// Sidebar Sections Toggle
// ==========================================
function toggleSidebarSection(header) {
    const section = header.closest('.sidebar-section');
    section.classList.toggle('collapsed');
}

// ==========================================
// File Dropzone - Drag & Drop
// ==========================================
const fileDropzone = document.getElementById('topic-dropzone');
const topicInput = document.getElementById('topic-input');

function setupDropzone() {
    if (!fileDropzone) return;

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Klick auf Dropzone öffnet Datei-Dialog
    fileDropzone.addEventListener('click', (e) => {
        if (e.target.tagName !== 'BUTTON') {
            document.getElementById('topic-file-input').click();
        }
    });

    // Drag & Drop Events
    fileDropzone.addEventListener('dragenter', (e) => {
        preventDefaults(e);
        fileDropzone.classList.add('drag-over');
    });

    fileDropzone.addEventListener('dragleave', (e) => {
        preventDefaults(e);
        if (!fileDropzone.contains(e.relatedTarget)) {
            fileDropzone.classList.remove('drag-over');
        }
    });

    fileDropzone.addEventListener('dragover', (e) => {
        preventDefaults(e);
        e.dataTransfer.dropEffect = 'copy';
    });

    fileDropzone.addEventListener('drop', (e) => {
        preventDefaults(e);
        fileDropzone.classList.remove('drag-over');
        handleDrop(e);
    });
}

setupDropzone();

// File Input Handler (Fallback)
function handleFileSelect(input) {
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];
    console.log('File selected:', file.name, file.type);

    // Fake drop event erstellen
    handleDrop({
        dataTransfer: { files: input.files }
    });

    // Input zurücksetzen damit gleiche Datei nochmal gewählt werden kann
    input.value = '';
}

async function handleDrop(e) {
    console.log('handleDrop called', e);

    const files = e.dataTransfer?.files;
    if (!files || files.length === 0) {
        console.log('No files in drop event');
        return;
    }

    const file = files[0];
    console.log('File dropped:', file.name, file.type, file.size);

    // Erlaubte Dateitypen - auch leere MIME-Types akzeptieren und nach Extension prüfen
    const allowedExtensions = ['.pdf', '.docx', '.doc', '.txt'];
    const allowedTypes = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/msword'
    ];

    const extension = '.' + file.name.split('.').pop().toLowerCase();
    const isAllowed = allowedTypes.includes(file.type) || allowedExtensions.includes(extension);

    if (!isAllowed) {
        App.showNotification(`Dateityp "${extension}" nicht erlaubt. Erlaubt: PDF, DOCX, TXT`, 'error');
        return;
    }

    // Show loading state
    const originalValue = topicInput.value;
    topicInput.value = 'Dokument wird verarbeitet...';
    topicInput.disabled = true;

    try {
        const formData = new FormData();
        formData.append('file', file);

        console.log('Sending to API...');
        const response = await fetch('/api/v1/documents/extract', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.getElementById('csrf-token')?.value || ''
            }
        });

        const result = await response.json();
        console.log('API response:', result);

        if (result.success && result.data?.text) {
            // Text hinzufügen oder ersetzen
            const existingText = originalValue.trim();
            topicInput.value = existingText ? existingText + '\n\n' + result.data.text : result.data.text;
            App.showNotification(`"${file.name}" erfolgreich extrahiert`, 'success');
        } else {
            throw new Error(result.message || 'Fehler beim Extrahieren');
        }
    } catch (error) {
        console.error('Drop error:', error);
        App.showNotification('Fehler: ' + error.message, 'error');
        topicInput.value = originalValue;
    } finally {
        topicInput.disabled = false;
        topicInput.focus();
    }
}

// ==========================================
// Topic Optimization
// ==========================================
async function optimizeTopic() {
    const topic = topicInput.value.trim();
    if (!topic) {
        App.showNotification('Bitte gib zuerst ein Thema oder Stichworte ein', 'error');
        return;
    }

    const btn = document.getElementById('optimize-btn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded spinning">autorenew</span><span class="btn-text">...</span>';

    try {
        const response = await App.post('/generate/optimize-topic', {
            topic: topic,
            model: document.getElementById('gen-model').value
        });

        if (response.data.optimized_topic) {
            topicInput.value = response.data.optimized_topic;
            App.showNotification('Thema optimiert', 'success');
        }
    } catch (error) {
        App.showNotification('Fehler: ' + error.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

// ==========================================
// Auto-Resize Textarea
// ==========================================
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

// Initial auto-resize for all textareas
document.querySelectorAll('.section-content').forEach(textarea => {
    autoResize(textarea);
});

// ==========================================
// Auto-Save
// ==========================================
function triggerAutoSave() {
    const statusEl = document.getElementById('save-status');
    statusEl.textContent = 'Ungespeicherte Änderungen...';
    statusEl.className = 'save-status';

    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
        autoSave();
    }, 1000); // 1 Sekunde Debounce
}

async function autoSave() {
    if (isSaving) return;

    const currentContent = getProjectContent();
    if (currentContent === lastSavedContent) return;

    isSaving = true;
    const statusEl = document.getElementById('save-status');
    statusEl.textContent = 'Speichert...';
    statusEl.className = 'save-status saving';

    try {
        await saveProjectData();
        lastSavedContent = currentContent;
        statusEl.textContent = 'Gespeichert';
        statusEl.className = 'save-status saved';

        setTimeout(() => {
            if (statusEl.textContent === 'Gespeichert') {
                statusEl.textContent = '';
            }
        }, 3000);
    } catch (error) {
        statusEl.textContent = 'Fehler beim Speichern';
        statusEl.className = 'save-status error';
    } finally {
        isSaving = false;
    }
}

function getProjectContent() {
    const sections = [];
    document.querySelectorAll('.section').forEach(section => {
        sections.push({
            heading: section.querySelector('.heading-input').value,
            content: section.querySelector('.section-content').value
        });
    });
    return JSON.stringify({
        title: document.getElementById('project-title').value,
        status: document.getElementById('project-status').value,
        customer_id: getSelectedCustomerId(),
        topic: document.getElementById('topic-input').value,
        style: document.getElementById('gen-style').value,
        model: document.getElementById('gen-model').value,
        sections_count: document.getElementById('gen-sections').value,
        target_words: document.getElementById('gen-words').value,
        rule_ids: getSelectedRuleIds(),
        knowledge_ids: getSelectedKnowledgeIds(),
        sections: sections
    });
}

// Title input auto-save
let titleManuallyEdited = <?= !empty($project['id']) ? 'true' : 'false' ?>;
const titleInput = document.getElementById('project-title');
titleInput.addEventListener('input', () => {
    titleManuallyEdited = true;
    triggerAutoSave();
});

// Bei neuen Projekten: Titel automatisch aus Thema generieren
document.getElementById('topic-input').addEventListener('input', function() {
    if (!titleManuallyEdited || titleInput.value === 'Neues Projekt' || titleInput.value === '') {
        const firstLine = this.value.split('\n')[0].trim();
        if (firstLine) {
            titleInput.value = firstLine.substring(0, 100);
            titleManuallyEdited = false;
        }
    }
});

// ==========================================
// Tab wechseln
// ==========================================
function switchTab(tabName) {
    document.querySelectorAll('.sidebar-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}

// ==========================================
// Artikel generieren (Async mit Job-Queue)
// ==========================================
let currentJobId = null;
let pollInterval = null;

async function generateArticle() {
    const topic = document.getElementById('topic-input').value.trim();
    if (!topic) {
        App.showNotification('Bitte gib ein Thema ein', 'error');
        return;
    }

    const btn = document.getElementById('generate-btn');
    btn.classList.add('btn-loading');
    btn.disabled = true;
    btn.textContent = 'Starte...';

    try {
        // Job in Queue erstellen
        const response = await App.post('/generate', {
            topic: topic,
            project_id: projectId || null,
            target_words: parseInt(document.getElementById('gen-words').value),
            sections: parseInt(document.getElementById('gen-sections').value),
            style: document.getElementById('gen-style').value,
            model: document.getElementById('gen-model').value,
            customer_id: getSelectedCustomerId(),
            rule_ids: getSelectedRuleIds(),
            knowledge_ids: getSelectedKnowledgeIds()
        });

        currentJobId = response.data.job_id;

        // Falls neues Projekt erstellt wurde, URL und projectId aktualisieren
        if (!projectId && response.data.project_id) {
            projectId = response.data.project_id;
            document.getElementById('project-id').value = projectId;
            history.replaceState(null, '', '/projects/' + projectId);
        }

        document.getElementById('project-title').value = topic;

        // Progress Overlay anzeigen
        showGenerationOverlay(response.data);

        // Status-Polling starten
        startJobPolling(currentJobId);

    } catch (error) {
        App.showNotification('Fehler: ' + error.message, 'error');
        btn.classList.remove('btn-loading');
        btn.disabled = false;
        btn.textContent = 'Artikel generieren';
    }
}

function showGenerationOverlay(data) {
    const overlay = document.getElementById('generation-overlay');
    overlay.classList.add('active');
    overlay.classList.remove('minimized');

    // Live-Preview Flag zurücksetzen für neue Generierung
    livePreviewInitialized = false;

    updateGenerationProgress({
        status: 'pending',
        queue_position: data.queue_position || 1,
        pending_jobs: data.pending_jobs || 1,
        attempts: 0,
        max_attempts: 3
    });
}

function hideGenerationOverlay() {
    const overlay = document.getElementById('generation-overlay');
    overlay.classList.remove('active');
    overlay.classList.remove('minimized');

    const btn = document.getElementById('generate-btn');
    btn.classList.remove('btn-loading');
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-rounded">auto_awesome</span> Artikel generieren';
}

function updateGenerationProgress(job) {
    const statusText = document.getElementById('generation-status-text');
    const progressText = document.getElementById('generation-progress-text');
    const progressFill = document.getElementById('generation-progress-fill');
    const queueRow = document.getElementById('queue-position-row');
    const queuePos = document.getElementById('generation-queue-position');
    const attempts = document.getElementById('generation-attempts');

    // Status-Text und Farbe
    const statusLabels = {
        'pending': 'In Warteschlange',
        'processing': 'Wird generiert...',
        'completed': 'Abgeschlossen',
        'failed': 'Fehlgeschlagen',
        'cancelled': 'Abgebrochen'
    };

    statusText.textContent = statusLabels[job.status] || job.status;
    statusText.className = 'info-value status-' + job.status;

    // Progress-Bar
    let progress = 0;
    let progressMessage = '';

    switch (job.status) {
        case 'pending':
            progress = 10;
            progressMessage = `Position ${job.queue_position} in der Warteschlange`;
            queueRow.style.display = 'flex';
            queuePos.textContent = `${job.queue_position} von ${job.pending_jobs || job.queue_position}`;
            break;
        case 'processing':
            queueRow.style.display = 'none';

            // Reliable Generation: Detaillierter Fortschritt
            if (job.current_step && job.total_steps > 0) {
                progress = job.progress_percent || 50;

                if (job.current_step === 'outline') {
                    progressMessage = 'Erstelle Gliederung...';
                } else if (job.current_step === 'completed') {
                    progress = 100;
                    progressMessage = 'Finalisiere Artikel...';
                } else if (job.current_step.startsWith('section_')) {
                    const sectionNum = parseInt(job.current_step.replace('section_', ''));
                    const totalSections = job.total_steps - 1; // -1 weil outline auch ein Step ist
                    progressMessage = `Schreibe Abschnitt ${sectionNum} von ${totalSections}...`;
                } else {
                    progressMessage = `Schritt ${job.completed_steps} von ${job.total_steps}...`;
                }
            } else {
                // Fallback für "fast" Methode
                progress = 50;
                progressMessage = 'KI generiert den Artikel...';
            }
            break;
        case 'completed':
            progress = 100;
            progressMessage = 'Artikel erfolgreich generiert!';
            queueRow.style.display = 'none';
            break;
        case 'failed':
            progress = 100;
            progressMessage = job.error_message || 'Ein Fehler ist aufgetreten';
            queueRow.style.display = 'none';
            break;
    }

    progressFill.style.width = progress + '%';
    progressText.textContent = progressMessage;

    // Versuche anzeigen
    attempts.textContent = `${job.attempts || 1}/${job.max_attempts || 3}`;
}

function startJobPolling(jobId) {
    // Vorheriges Polling stoppen
    if (pollInterval) {
        clearInterval(pollInterval);
    }

    // Sofort ersten Status holen
    checkJobStatus(jobId);

    // Alle 2 Sekunden Status prüfen
    pollInterval = setInterval(() => checkJobStatus(jobId), 2000);
}

function stopJobPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

async function checkJobStatus(jobId) {
    try {
        const response = await App.get('/jobs/' + jobId);
        const job = response.data;

        console.log('[LivePreview] Status:', job.status, '| Step:', job.current_step, '| Outline:', job.outline?.length || 0, '| Sections:', Object.keys(job.generated_sections || {}).length);

        updateGenerationProgress(job);

        // Live-Vorschau: Outline und Sections im Editor anzeigen
        // Auch wenn Status nicht processing ist, aber Outline vorhanden
        if (job.status === 'processing' || (job.outline && job.outline.length > 0)) {
            updateLivePreview(job);
        }

        if (job.status === 'completed') {
            stopJobPolling();
            onGenerationCompleted(job);
        } else if (job.status === 'failed' || job.status === 'cancelled') {
            stopJobPolling();
            onGenerationFailed(job);
        }
    } catch (error) {
        console.error('Status-Abfrage fehlgeschlagen:', error);
        // Bei Netzwerkfehlern weiter versuchen
    }
}

// Live-Vorschau: Zeigt Outline als Skeleton und füllt fertige Sections
let livePreviewInitialized = false;

function updateLivePreview(job) {
    console.log('[LivePreview] updateLivePreview called, initialized:', livePreviewInitialized);

    // Wenn Outline vorhanden, Skeleton-Sections erstellen
    if (job.outline && job.outline.length > 0 && !livePreviewInitialized) {
        console.log('[LivePreview] Creating skeleton sections for outline:', job.outline);
        createSkeletonSections(job.outline);
        livePreviewInitialized = true;

        // Overlay minimieren - nur kleiner Fortschritts-Indikator
        minimizeOverlay();
    }

    // Fertige Sections mit Content befüllen
    if (job.generated_sections && Object.keys(job.generated_sections).length > 0) {
        console.log('[LivePreview] Filling sections:', Object.keys(job.generated_sections));
        for (const [orderStr, section] of Object.entries(job.generated_sections)) {
            const order = parseInt(orderStr);
            fillSkeletonSection(order, section);
        }
    }

    // Aktuell generierenden Abschnitt markieren
    if (job.current_step && job.current_step.startsWith('section_')) {
        const currentSectionNum = parseInt(job.current_step.replace('section_', ''));
        markCurrentSection(currentSectionNum);
    }
}

function markCurrentSection(sectionNum) {
    // Alle Markierungen entfernen
    document.querySelectorAll('.section.skeleton-section').forEach(el => {
        el.removeAttribute('data-generating');
        const statusText = el.querySelector('.status-text');
        if (statusText) statusText.textContent = 'Warte...';
    });

    // Aktuellen Abschnitt markieren
    const currentSection = document.querySelector(`.section[data-order="${sectionNum}"]`);
    if (currentSection && currentSection.classList.contains('skeleton-section')) {
        currentSection.setAttribute('data-generating', 'true');
        const statusText = currentSection.querySelector('.status-text');
        if (statusText) statusText.textContent = 'Wird geschrieben...';
    }
}

function createSkeletonSections(outline) {
    const container = document.getElementById('sections-container');
    container.innerHTML = '';
    sectionCounter = 0;

    outline.forEach((headingInfo, index) => {
        const heading = typeof headingInfo === 'string' ? headingInfo : (headingInfo.heading || headingInfo.title || `Abschnitt ${index + 1}`);
        const level = index === 0 ? 'h1' : 'h2';

        sectionCounter++;
        const sectionHtml = `
            <div class="section skeleton-section" data-order="${index + 1}" data-id="skeleton-${index + 1}" draggable="false">
                <div class="section-header">
                    <div class="drag-handle disabled">
                        <span class="material-symbols-rounded">drag_indicator</span>
                    </div>
                    <span class="section-number">${index + 1}</span>
                    <select class="heading-level" disabled>
                        <option value="${level}" selected>${level.toUpperCase()}</option>
                    </select>
                    <input type="text" class="heading-input" value="${escapeHtml(heading)}" readonly>
                    <div class="section-actions">
                        <span class="skeleton-status">
                            <span class="material-symbols-rounded spinning">sync</span>
                            <span class="status-text">Warte...</span>
                        </span>
                    </div>
                </div>
                <div class="section-content-skeleton">
                    <div class="skeleton-loader">
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line short"></div>
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line medium"></div>
                    </div>
                </div>
                <div class="section-footer">
                    <span class="section-words">-- Wörter</span>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', sectionHtml);
    });
}

function fillSkeletonSection(order, section) {
    const sectionEl = document.querySelector(`.section[data-order="${order}"]`);
    if (!sectionEl) return;

    // Bereits gefüllt? Skip
    if (sectionEl.classList.contains('filled')) return;

    // Skeleton durch echten Content ersetzen
    sectionEl.classList.remove('skeleton-section');
    sectionEl.classList.add('filled');
    sectionEl.setAttribute('draggable', 'true');

    // Header aktualisieren
    const headingInput = sectionEl.querySelector('.heading-input');
    if (headingInput) {
        headingInput.value = section.heading;
        headingInput.removeAttribute('readonly');
    }

    const levelSelect = sectionEl.querySelector('.heading-level');
    if (levelSelect) {
        levelSelect.disabled = false;
        levelSelect.value = section.level;
    }

    const dragHandle = sectionEl.querySelector('.drag-handle');
    if (dragHandle) {
        dragHandle.classList.remove('disabled');
    }

    // Status-Indikator durch Action-Buttons ersetzen
    const actionsDiv = sectionEl.querySelector('.section-actions');
    if (actionsDiv) {
        actionsDiv.innerHTML = `
            <button class="btn-icon" onclick="regenerateSection(this)" title="Neu generieren"><span class="material-symbols-rounded">refresh</span></button>
            <button class="btn-icon btn-danger" onclick="deleteSection(this)" title="Löschen"><span class="material-symbols-rounded">delete</span></button>
        `;
    }

    // Skeleton-Content durch Textarea ersetzen
    const skeletonContent = sectionEl.querySelector('.section-content-skeleton');
    if (skeletonContent) {
        const textarea = document.createElement('textarea');
        textarea.className = 'section-content';
        textarea.placeholder = 'Inhalt...';
        textarea.value = section.content;
        textarea.oninput = function() { autoResize(this); triggerAutoSave(); };
        skeletonContent.replaceWith(textarea);
        autoResize(textarea);
    }

    // Footer aktualisieren
    const wordsSpan = sectionEl.querySelector('.section-words');
    if (wordsSpan) {
        wordsSpan.textContent = `${section.word_count || countWords(section.content)} Wörter`;
    }

    // Footer mit Feedback-Buttons erweitern
    const footer = sectionEl.querySelector('.section-footer');
    if (footer && !footer.querySelector('.feedback-buttons')) {
        footer.innerHTML = `
            <span class="section-words">${section.word_count || countWords(section.content)} Wörter</span>
            <div class="feedback-buttons">
                <button class="feedback-btn negative" onclick="giveFeedback(this, 'negative')" title="Nicht gut - Regel vorschlagen"><span class="material-symbols-rounded">thumb_down</span></button>
                <button class="feedback-btn neutral" onclick="regenerateSection(this)" title="Neu generieren"><span class="material-symbols-rounded">refresh</span></button>
            </div>
        `;
    }

    // Kurze Animation
    sectionEl.style.animation = 'fadeInSection 0.3s ease';
}

function minimizeOverlay() {
    const overlay = document.getElementById('generation-overlay');
    overlay.classList.add('minimized');

    // Klick auf minimiertes Overlay zum Maximieren
    const modal = overlay.querySelector('.generation-modal');
    modal.onclick = function(e) {
        if (overlay.classList.contains('minimized')) {
            e.stopPropagation();
            maximizeOverlay();
        }
    };
}

function maximizeOverlay() {
    const overlay = document.getElementById('generation-overlay');
    overlay.classList.remove('minimized');
}

function onGenerationCompleted(job) {
    // Overlay kurz mit Erfolg anzeigen
    document.getElementById('generation-title').textContent = 'Artikel fertig!';
    document.getElementById('generation-progress-fill').style.width = '100%';
    document.getElementById('generation-progress-fill').style.background = '#22c55e';

    setTimeout(() => {
        hideGenerationOverlay();

        // Wenn Live-Preview aktiv war, nur noch fehlende Sections füllen
        if (livePreviewInitialized && job.result && Array.isArray(job.result)) {
            job.result.forEach((section, index) => {
                fillSkeletonSection(index + 1, {
                    heading: section.heading,
                    level: section.level || 'h2',
                    content: section.content,
                    word_count: section.word_count || countWords(section.content)
                });
            });
            App.showNotification(`Artikel generiert: ${job.total_words} Wörter`, 'success');
        }
        // Fallback: Ohne Live-Preview (fast-Methode)
        else if (job.result && Array.isArray(job.result)) {
            const container = document.getElementById('sections-container');
            container.innerHTML = '';
            sectionCounter = 0;

            job.result.forEach(section => {
                addSectionWithContent(
                    section.content,
                    section.heading,
                    section.level || 'h2',
                    job.model
                );
            });

            App.showNotification(`Artikel generiert: ${job.total_words} Wörter`, 'success');
        }

        // Live-Preview Flag zurücksetzen
        livePreviewInitialized = false;
    }, 1000);
}

function onGenerationFailed(job) {
    document.getElementById('generation-title').textContent = 'Generierung fehlgeschlagen';
    document.getElementById('generation-progress-fill').style.background = '#ef4444';

    // Cancel-Button zu Retry umwandeln
    const cancelBtn = document.getElementById('cancel-generation-btn');
    if (job.status === 'failed') {
        cancelBtn.textContent = 'Erneut versuchen';
        cancelBtn.onclick = () => retryGeneration(job.id);
    }

    App.showNotification(job.error_message || 'Generierung fehlgeschlagen', 'error');
}

async function cancelGeneration() {
    if (!currentJobId) return;

    try {
        await App.post('/jobs/' + currentJobId, { action: 'cancel' });
        stopJobPolling();
        hideGenerationOverlay();
        App.showNotification('Generierung abgebrochen', 'info');
    } catch (error) {
        App.showNotification('Fehler beim Abbrechen: ' + error.message, 'error');
    }
}

async function retryGeneration(jobId) {
    try {
        const response = await App.post('/jobs/' + jobId, { action: 'retry' });
        currentJobId = response.data.job_id || jobId;

        // Overlay zurücksetzen
        document.getElementById('generation-title').textContent = 'Artikel wird generiert...';
        document.getElementById('generation-progress-fill').style.background = 'var(--color-black)';

        const cancelBtn = document.getElementById('cancel-generation-btn');
        cancelBtn.textContent = 'Abbrechen';
        cancelBtn.onclick = cancelGeneration;

        // Polling neu starten
        startJobPolling(currentJobId);

        App.showNotification('Generierung wird wiederholt', 'info');
    } catch (error) {
        App.showNotification('Fehler: ' + error.message, 'error');
    }
}

// Bei Seitenlade prüfen ob Generierung läuft
function checkActiveGeneration() {
    const status = document.getElementById('generation-status')?.value;
    const jobId = document.getElementById('current-job-id')?.value;

    if (status === 'generating' && jobId) {
        currentJobId = parseInt(jobId);
        // Flag zurücksetzen falls Seite neu geladen wurde
        livePreviewInitialized = false;
        showGenerationOverlay({ queue_position: 1, pending_jobs: 1 });
        startJobPolling(currentJobId);
    }
}

// Beim Laden ausführen
document.addEventListener('DOMContentLoaded', checkActiveGeneration);

// ==========================================
// Chat senden
// ==========================================
async function sendMessage() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    if (!message) return;

    const model = document.getElementById('model-select').value;
    const messagesDiv = document.getElementById('chat-messages');

    messagesDiv.innerHTML += `<div class="chat-message user"><p>${escapeHtml(message)}</p></div>`;
    input.value = '';
    messagesDiv.scrollTop = messagesDiv.scrollHeight;

    const loadingId = 'loading-' + Date.now();
    messagesDiv.innerHTML += `<div class="chat-message assistant" id="${loadingId}"><p>Schreibe...</p></div>`;

    try {
        const response = await App.post('/chat', {
            project_id: projectId || null,
            message: message,
            model: model
        });

        document.getElementById(loadingId).remove();
        messagesDiv.innerHTML += `<div class="chat-message assistant"><p>${escapeHtml(response.data.content).replace(/\n/g, '<br>')}</p></div>`;
        messagesDiv.scrollTop = messagesDiv.scrollHeight;

        if (response.data.content.length > 200) {
            addSectionWithContent(response.data.content);
        }
    } catch (error) {
        document.getElementById(loadingId).innerHTML = `<p style="color: #ef4444;">${error.message}</p>`;
    }
}

document.getElementById('chat-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// ==========================================
// Abschnitt Funktionen
// ==========================================
function addSectionWithContent(content, heading = '', level = 'h2', modelUsed = null) {
    sectionCounter++;
    const container = document.getElementById('sections-container');
    const emptyState = container.querySelector('.empty-state');
    if (emptyState) emptyState.remove();

    const wordCount = countWords(content);
    const sectionNumber = container.querySelectorAll('.section').length + 1;
    const section = document.createElement('div');
    section.className = 'section';
    section.dataset.order = sectionCounter;
    section.draggable = true;
    if (modelUsed) section.dataset.modelUsed = modelUsed;
    section.innerHTML = `
        <div class="section-header">
            <div class="drag-handle" title="Ziehen zum Sortieren">
                <span class="material-symbols-rounded">drag_indicator</span>
            </div>
            <span class="section-number">${sectionNumber}</span>
            <select class="heading-level" onchange="triggerAutoSave()">
                <option value="h1" ${level === 'h1' ? 'selected' : ''}>H1</option>
                <option value="h2" ${level === 'h2' ? 'selected' : ''}>H2</option>
                <option value="h3" ${level === 'h3' ? 'selected' : ''}>H3</option>
            </select>
            <input type="text" class="heading-input" value="${escapeHtml(heading)}" placeholder="Überschrift..." oninput="triggerAutoSave()">
            <div class="section-actions">
                <button class="btn-icon" onclick="regenerateSection(this)" title="Neu generieren"><span class="material-symbols-rounded">refresh</span></button>
                <button class="btn-icon btn-danger" onclick="deleteSection(this)" title="Löschen"><span class="material-symbols-rounded">delete</span></button>
            </div>
        </div>
        <textarea class="section-content" placeholder="Inhalt..." oninput="autoResize(this); triggerAutoSave();">${escapeHtml(content)}</textarea>
        <div class="section-footer">
            <span class="section-words">${wordCount} Wörter</span>
            <div class="feedback-buttons">
                <button class="feedback-btn negative" onclick="giveFeedback(this, 'negative')" title="Nicht gut"><span class="material-symbols-rounded">thumb_down</span></button>
                <button class="feedback-btn neutral" onclick="regenerateSection(this)" title="Neu generieren"><span class="material-symbols-rounded">refresh</span></button>
            </div>
        </div>
    `;
    container.appendChild(section);

    // Auto-resize the new textarea
    const textarea = section.querySelector('.section-content');
    setTimeout(() => autoResize(textarea), 10);

    updateTotalWordCount();
    initSectionDragAndDrop(section);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function addSection() {
    addSectionWithContent('');
    triggerAutoSave();
}

function deleteSection(btn) {
    if (confirm('Abschnitt löschen?')) {
        btn.closest('.section').remove();
        updateSectionNumbers();
        updateTotalWordCount();
        triggerAutoSave();
    }
}

function updateSectionNumbers() {
    document.querySelectorAll('#sections-container .section').forEach((section, index) => {
        const numberEl = section.querySelector('.section-number');
        if (numberEl) {
            numberEl.textContent = index + 1;
        }
        section.dataset.order = index + 1;
    });
}

async function regenerateSection(btn) {
    const section = btn.closest('.section');
    const heading = section.querySelector('.heading-input').value;
    const contentArea = section.querySelector('.section-content');
    const currentContent = contentArea.value;
    const model = document.getElementById('gen-model')?.value || 'gpt-4';
    const wordsTarget = Math.round(parseInt(document.getElementById('gen-words')?.value || 1000) / parseInt(document.getElementById('gen-sections')?.value || 5));

    if (!heading && !currentContent) {
        App.showNotification('Abschnitt hat keinen Inhalt', 'error');
        return;
    }

    // Ausgewählte Regeln und Wissen holen
    const ruleIds = getSelectedRuleIds();
    const knowledgeIds = getSelectedKnowledgeIds();

    const originalContent = contentArea.value;

    // Skeleton-Animation anzeigen
    section.classList.add('regenerating');
    const skeletonHtml = `
        <div class="regenerate-skeleton">
            <div class="skeleton-line"></div>
            <div class="skeleton-line"></div>
            <div class="skeleton-line short"></div>
            <div class="skeleton-line"></div>
            <div class="skeleton-line medium"></div>
        </div>
    `;
    contentArea.style.display = 'none';
    contentArea.insertAdjacentHTML('afterend', skeletonHtml);
    btn.disabled = true;

    const prompt = `Schreibe einen Abschnitt mit der Überschrift: "${heading}".

ANFORDERUNGEN:
- Schreibe MINDESTENS ${wordsTarget} Wörter
- Fließender, natürlicher Text ohne Aufzählungen
- Mehrere Absätze mit je 4-6 Sätzen
- Keine Markdown-Formatierung
- NUR den Inhalt ausgeben, KEINE Überschrift wiederholen`;

    try {
        const response = await App.post('/chat', {
            project_id: projectId || null,
            message: prompt,
            model: model,
            rule_ids: ruleIds,
            knowledge_ids: knowledgeIds,
            is_regenerate: true
        });

        // Skeleton entfernen, Content anzeigen
        section.querySelector('.regenerate-skeleton')?.remove();
        contentArea.style.display = '';

        contentArea.value = response.data.content;
        section.dataset.modelUsed = model;
        const words = countWords(response.data.content);
        section.querySelector('.section-words').textContent = words + ' Wörter';
        autoResize(contentArea);
        updateTotalWordCount();
        triggerAutoSave();

        // Erfolgs-Animation
        section.classList.add('regenerate-success');
        setTimeout(() => section.classList.remove('regenerate-success'), 600);

        App.showNotification(`Abschnitt neu generiert (${words} Wörter)`, 'success');
    } catch (error) {
        // Skeleton entfernen, Original wiederherstellen
        section.querySelector('.regenerate-skeleton')?.remove();
        contentArea.style.display = '';
        contentArea.value = originalContent;
        App.showNotification(error.message, 'error');
    } finally {
        section.classList.remove('regenerating');
        btn.disabled = false;
    }
}

// ==========================================
// Revisionen
// ==========================================
async function openRevisionsModal() {
    if (!projectId) {
        App.showNotification('Projekt muss zuerst gespeichert werden', 'info');
        return;
    }

    document.getElementById('revisions-modal').classList.add('active');
    await loadRevisions();
}

function closeRevisionsModal() {
    document.getElementById('revisions-modal').classList.remove('active');
}

async function loadRevisions() {
    const listEl = document.getElementById('revisions-list');
    listEl.innerHTML = '<p class="text-muted">Lade Versionen...</p>';

    try {
        const response = await App.get('/projects/' + projectId + '/versions');
        const versions = response.data || [];

        if (versions.length === 0) {
            listEl.innerHTML = '<p class="text-muted">Keine Versionen vorhanden</p>';
            return;
        }

        listEl.innerHTML = versions.map(v => `
            <div class="revision-item" data-version="${v.version_number}" onclick="loadRevision(${v.version_number})">
                <div class="revision-version">Version ${v.version_number}</div>
                <div class="revision-date">${formatDate(v.created_at)}</div>
                <div class="revision-words">${v.word_count || 0} Wörter</div>
            </div>
        `).join('');
    } catch (error) {
        listEl.innerHTML = '<p class="text-muted">Fehler beim Laden</p>';
    }
}

async function loadRevision(versionNumber) {
    document.querySelectorAll('.revision-item').forEach(el => el.classList.remove('active'));
    document.querySelector(`.revision-item[data-version="${versionNumber}"]`)?.classList.add('active');

    const previewEl = document.getElementById('revision-preview');
    previewEl.innerHTML = '<p class="text-muted">Lade...</p>';

    try {
        const response = await App.get('/projects/' + projectId + '/versions/' + versionNumber);
        const version = response.data;

        let html = '<div class="revision-preview-content">';
        if (version.sections) {
            version.sections.forEach(s => {
                if (s.heading_text) {
                    const tag = s.heading_level || 'h2';
                    html += `<${tag}>${escapeHtml(s.heading_text)}</${tag}>`;
                }
                html += `<p>${escapeHtml(s.content || '').replace(/\n/g, '<br>')}</p>`;
            });
        }
        html += '</div>';

        html += `<div class="revision-actions">
            <button class="btn btn-primary" onclick="restoreRevision(${versionNumber})">
                Diese Version wiederherstellen
            </button>
        </div>`;

        previewEl.innerHTML = html;
    } catch (error) {
        previewEl.innerHTML = '<p class="text-muted">Fehler beim Laden</p>';
    }
}

async function restoreRevision(versionNumber) {
    if (!confirm('Aktuelle Version durch Version ' + versionNumber + ' ersetzen?')) return;

    try {
        const response = await App.post('/projects/' + projectId + '/versions/' + versionNumber + '/restore');
        App.showNotification('Version wiederhergestellt', 'success');
        closeRevisionsModal();
        window.location.reload();
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('de-DE') + ' ' + date.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});
}

// ==========================================
// Feedback
// ==========================================
let currentFeedbackSection = null;

function giveFeedback(btn, rating) {
    const section = btn.closest('.section');
    currentFeedbackSection = section;
    document.getElementById('feedback-section-id').value = section.dataset.id || '';
    document.getElementById('feedback-section-content').value = section.querySelector('.section-content').value;
    document.getElementById('feedback-text').value = '';
    document.getElementById('feedback-modal').classList.add('active');
}

function closeFeedbackModal() {
    document.getElementById('feedback-modal').classList.remove('active');
}

function setFeedbackExample(text) {
    document.getElementById('feedback-text').value = text;
}

async function analyzeFeedback() {
    const feedbackText = document.getElementById('feedback-text').value.trim();
    if (!feedbackText) {
        App.showNotification('Bitte beschreibe das Problem', 'error');
        return;
    }

    const btn = document.getElementById('analyze-feedback-btn');
    btn.disabled = true;

    try {
        const response = await App.post('/feedback/analyze', {
            section_id: document.getElementById('feedback-section-id').value || null,
            feedback: feedbackText,
            section_content: document.getElementById('feedback-section-content').value
        });

        closeFeedbackModal();
        const suggestion = response.data.suggestion;
        document.getElementById('suggestion-feedback-id').value = response.data.feedback_id || '';
        document.getElementById('suggestion-name').value = suggestion.rule_name || '';
        document.getElementById('suggestion-content').value = suggestion.rule_content || '';
        document.getElementById('suggestion-type').value = suggestion.rule_type || 'content';
        document.getElementById('suggestion-explanation').textContent = suggestion.explanation || '';
        document.getElementById('rule-suggestion-modal').classList.add('active');
    } catch (error) {
        App.showNotification(error.message, 'error');
    } finally {
        btn.disabled = false;
    }
}

function closeRuleSuggestionModal() {
    document.getElementById('rule-suggestion-modal').classList.remove('active');
}

async function applyRule() {
    const btn = document.getElementById('apply-rule-btn');
    btn.disabled = true;

    const typeSelect = document.getElementById('suggestion-type');
    const selectedOption = typeSelect.options[typeSelect.selectedIndex];

    try {
        await App.post('/feedback/apply-rule', {
            rule_name: document.getElementById('suggestion-name').value.trim(),
            rule_content: document.getElementById('suggestion-content').value.trim(),
            rule_type: selectedOption?.dataset?.slug || 'style',
            rule_type_id: typeSelect.value || null,
            category_id: document.getElementById('suggestion-category').value || null,
            scope: document.querySelector('input[name="rule-scope"]:checked').value,
            customer_id: getSelectedCustomerId(),
            feedback_id: document.getElementById('suggestion-feedback-id').value || null
        });

        closeRuleSuggestionModal();
        App.showNotification('Regel erstellt', 'success');
    } catch (error) {
        App.showNotification(error.message, 'error');
    } finally {
        btn.disabled = false;
    }
}

// ==========================================
// Hilfsfunktionen
// ==========================================
function countWords(text) {
    if (!text) return 0;
    return text.trim().split(/\s+/).filter(w => w.length > 0).length;
}

function updateTotalWordCount() {
    let total = 0;
    document.querySelectorAll('.section-content').forEach(textarea => {
        total += countWords(textarea.value);
    });
    document.getElementById('word-count').textContent = total + ' Wörter';
}

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('section-content')) {
        const words = countWords(e.target.value);
        e.target.closest('.section').querySelector('.section-words').textContent = words + ' Wörter';
        updateTotalWordCount();
    }
});

function getSelectedCustomerId() {
    const select = document.getElementById('customer-select');
    return select ? select.value : null;
}

function togglePanel(header) {
    header.closest('.info-panel').classList.toggle('collapsed');
}

function getSelectedRuleIds() {
    return Array.from(document.querySelectorAll('input[name="rules[]"]:checked')).map(cb => parseInt(cb.value));
}

function getSelectedKnowledgeIds() {
    return Array.from(document.querySelectorAll('input[name="knowledge[]"]:checked')).map(cb => parseInt(cb.value));
}

function updateRulesCount() {
    document.getElementById('rules-count').textContent = document.querySelectorAll('input[name="rules[]"]:checked').length;
}

function updateKnowledgeCount() {
    document.getElementById('knowledge-count').textContent = document.querySelectorAll('input[name="knowledge[]"]:checked').length;
}

function onCustomerChange() {
    const customerId = getSelectedCustomerId();

    // Regeln und Wissen aktualisieren wenn customerData vorhanden
    if (customerId && typeof customerData !== 'undefined' && customerData[customerId]) {
        const data = customerData[customerId];
        document.querySelectorAll('input[name="rules[]"]').forEach(cb => {
            cb.checked = data.rules && data.rules.includes(parseInt(cb.value));
        });
        updateRulesCount();

        document.querySelectorAll('input[name="knowledge[]"]').forEach(cb => {
            cb.checked = data.knowledge && data.knowledge.includes(parseInt(cb.value));
        });
        updateKnowledgeCount();
    }

    // Immer Auto-Save ausloesen
    triggerAutoSave();
}

// ==========================================
// Projekt speichern
// ==========================================
async function saveProjectData() {
    const title = document.getElementById('project-title').value;
    const targetWords = document.getElementById('gen-words').value;
    const status = document.getElementById('project-status').value;
    const customerId = getSelectedCustomerId();
    const topic = document.getElementById('topic-input').value;
    const style = document.getElementById('gen-style').value;
    const model = document.getElementById('gen-model').value;
    const sectionsCount = document.getElementById('gen-sections').value;

    const sections = [];
    document.querySelectorAll('.section').forEach((section, index) => {
        sections.push({
            order: index + 1,
            heading_level: section.querySelector('.heading-level').value,
            heading_text: section.querySelector('.heading-input').value,
            content: section.querySelector('.section-content').value,
            model_used: section.dataset.modelUsed || null
        });
    });

    const data = {
        title,
        target_word_count: targetWords,
        status,
        customer_id: customerId,
        topic,
        style,
        model,
        sections_count: sectionsCount,
        sections,
        rule_ids: getSelectedRuleIds(),
        knowledge_ids: getSelectedKnowledgeIds()
    };

    if (projectId) {
        const response = await App.put('/projects/' + projectId, data);
        // Version-Nummer aktualisieren wenn neue Version erstellt wurde
        if (response.data && response.data.version_number) {
            document.getElementById('version-info').textContent = 'v' + response.data.version_number;
        }
    } else {
        const response = await App.post('/projects', data);
        if (response.data.id) {
            // Projekt-ID setzen und URL aktualisieren ohne Reload
            projectId = response.data.id;
            document.getElementById('project-id').value = projectId;
            window.history.replaceState({}, '', '/projects/' + projectId);
        }
    }
}

async function saveProject() {
    const btn = document.getElementById('save-btn');
    btn.disabled = true;
    btn.textContent = 'Speichert...';

    try {
        await saveProjectData();
        App.showNotification('Projekt gespeichert', 'success');
        lastSavedContent = getProjectContent();
        document.getElementById('save-status').textContent = '';
    } catch (error) {
        App.showNotification(error.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Speichern';
    }
}

// ==========================================
// Export
// ==========================================
function exportAs(format) {
    let content = '';
    document.querySelectorAll('.section').forEach(section => {
        const level = section.querySelector('.heading-level').value;
        const heading = section.querySelector('.heading-input').value;
        const text = section.querySelector('.section-content').value;

        if (format === 'markdown') {
            const hashes = level === 'h1' ? '#' : (level === 'h2' ? '##' : '###');
            if (heading) content += `${hashes} ${heading}\n\n`;
            content += text + '\n\n';
        } else if (format === 'html') {
            if (heading) content += `<${level}>${heading}</${level}>\n`;
            content += `<p>${text.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>')}</p>\n\n`;
        } else {
            if (heading) content += heading.toUpperCase() + '\n\n';
            content += text + '\n\n';
        }
    });

    const ext = format === 'markdown' ? 'md' : (format === 'html' ? 'html' : 'txt');
    const blob = new Blob([content], { type: 'text/plain' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = (document.getElementById('project-title').value || 'artikel') + '.' + ext;
    a.click();
}

// ==========================================
// Wissensdatenbank
// ==========================================
function openSaveToKnowledgeModal() {
    document.getElementById('kb-title').value = document.getElementById('project-title').value;
    const customerSelect = document.getElementById('customer-select');
    const kbCustomerSelect = document.getElementById('kb-customer');
    if (customerSelect && kbCustomerSelect) {
        kbCustomerSelect.value = customerSelect.value;
    }
    App.openModal('save-knowledge-modal');
}

function closeSaveKnowledgeModal() {
    App.closeModal('save-knowledge-modal');
}

async function saveToKnowledge() {
    const title = document.getElementById('kb-title').value.trim();
    if (!title) {
        App.showNotification('Bitte Titel eingeben', 'error');
        return;
    }

    let content = '';
    document.querySelectorAll('.section').forEach(section => {
        const heading = section.querySelector('.heading-input').value;
        const text = section.querySelector('.section-content').value;
        if (heading) content += heading + '\n\n';
        content += text + '\n\n';
    });

    if (!content.trim()) {
        App.showNotification('Kein Inhalt vorhanden', 'error');
        return;
    }

    const btn = document.getElementById('save-knowledge-btn');
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('title', title);
        formData.append('content', content.trim());
        formData.append('entry_type', 'manual');
        formData.append('customer_id', document.getElementById('kb-customer')?.value || '');
        formData.append('category_id', document.getElementById('kb-category').value);
        if (document.getElementById('kb-auto-vectorize').checked) formData.append('auto_vectorize', '1');

        const response = await fetch('/api/v1/knowledge/upload', {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.getElementById('csrf-token').value },
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            App.showNotification('Zur Wissensdatenbank hinzugefügt', 'success');
            closeSaveKnowledgeModal();
        } else {
            App.showNotification(result.message || 'Fehler', 'error');
        }
    } catch (error) {
        App.showNotification(error.message, 'error');
    } finally {
        btn.disabled = false;
    }
}

// Modal close handlers
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => modal.classList.remove('active'));
    }
});

// Initial
updateTotalWordCount();
lastSavedContent = getProjectContent();

// ==========================================
// Drag & Drop für Abschnitte
// ==========================================
let draggedSection = null;

function initSectionDragAndDrop(section) {
    section.addEventListener('dragstart', handleDragStart);
    section.addEventListener('dragend', handleDragEnd);
    section.addEventListener('dragover', handleDragOver);
    section.addEventListener('dragleave', handleDragLeave);
    section.addEventListener('drop', handleSectionDrop);
}

function handleDragStart(e) {
    // Nur starten wenn auf drag-handle geklickt wurde
    if (!e.target.closest('.drag-handle') && e.target.closest('.section')) {
        // Erlaube Drag trotzdem, aber visuelles Feedback
    }
    draggedSection = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    document.querySelectorAll('.section').forEach(s => {
        s.classList.remove('drag-over');
    });
    draggedSection = null;
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';

    if (this !== draggedSection) {
        this.classList.add('drag-over');
    }
}

function handleDragLeave(e) {
    this.classList.remove('drag-over');
}

function handleSectionDrop(e) {
    e.preventDefault();
    e.stopPropagation();

    if (draggedSection && this !== draggedSection) {
        const container = document.getElementById('sections-container');
        const allSections = [...container.querySelectorAll('.section')];
        const fromIndex = allSections.indexOf(draggedSection);
        const toIndex = allSections.indexOf(this);

        if (fromIndex < toIndex) {
            this.parentNode.insertBefore(draggedSection, this.nextSibling);
        } else {
            this.parentNode.insertBefore(draggedSection, this);
        }

        updateSectionNumbers();
        triggerAutoSave();
    }

    this.classList.remove('drag-over');
}

// Initialisiere Drag & Drop für bestehende Abschnitte
document.querySelectorAll('#sections-container .section').forEach(section => {
    initSectionDragAndDrop(section);
});
</script>
