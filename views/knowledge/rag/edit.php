<?php
$isAdmin = \Core\Auth::isAdmin();
$documentId = $documentId ?? null;
?>

<style>
.kb-edit-container { /* max-width/padding entfernt — .main-content liefert die Gutter konsistent */ }
.kb-edit-header { display:flex; align-items:center; gap: 0.75rem; margin-bottom: 1.5rem; }
.kb-edit-header .kb-back { color: var(--color-text-secondary,#64748b); text-decoration: none; display: flex; }
.kb-edit-header h1 { font-size: var(--d-fs-xl); font-weight: 600; margin: 0; }

.kb-tabs { display: flex; gap: 0.25rem; border-bottom: 1px solid var(--color-border,#e2e8f0); margin-bottom: 1.5rem; }
.kb-tab { padding: 0.625rem 1rem; border: none; background: none; cursor: pointer; font-size: var(--d-fs-sm); color: var(--color-text-secondary,#64748b); border-bottom: 2px solid transparent; display: flex; align-items: center; gap: 0.375rem; font-family: inherit; }
.kb-tab.active { color: var(--color-primary,var(--thoxan-700)); border-bottom-color: var(--color-primary,var(--thoxan-700)); font-weight: 600; }
.kb-tab .material-symbols-rounded { font-size: 18px; }

.kb-tab-content { display: none; }
.kb-tab-content.active { display: block; }

.kb-dropzone {
    border: 2px dashed var(--color-border,#e2e8f0);
    border-radius: 12px;
    padding: 3rem 2rem;
    text-align: center;
    transition: border-color 0.2s, background 0.2s;
    cursor: pointer;
}
.kb-dropzone:hover, .kb-dropzone.drag-over { border-color: var(--color-primary,var(--thoxan-700)); background: var(--thoxan-100); }
.kb-dropzone .material-symbols-rounded { font-size: 48px; color: var(--color-text-tertiary,#94a3b8); margin-bottom: 0.5rem; }

.kb-form-group { margin-bottom: 1rem; }
.kb-form-group label { display: block; font-size: var(--d-fs-sm); font-weight: 500; margin-bottom: 0.3rem; color: var(--color-text-secondary,#64748b); }
.kb-form-group input, .kb-form-group textarea, .kb-form-group select {
    width: 100%; padding: 0.575rem 0.75rem; border: 1px solid var(--color-border,#e2e8f0);
    border-radius: 8px; font-size: var(--d-fs-sm); background: var(--color-bg-primary,#fff);
    color: var(--color-text-primary,#1e293b); box-sizing: border-box; font-family: inherit;
}
.kb-form-group textarea { min-height: 120px; resize: vertical; }
.kb-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

.kb-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; }

/* Preview Modal */
.kb-modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 200;
    justify-content: center; align-items: center;
}
.kb-modal-overlay.active { display: flex; }
.kb-modal {
    background: var(--color-bg-primary,#fff); border-radius: 14px; width: 90%;
    max-width: 640px; max-height: 85vh; overflow-y: auto; padding: 1.75rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.kb-modal h2 { margin: 0 0 1rem; font-size: var(--d-fs-lg); }
.kb-stats-row { display: flex; gap: 0.75rem; margin: 0.75rem 0 1rem; flex-wrap: wrap; }
.kb-chip { padding: 0.3rem 0.6rem; background: var(--color-bg-secondary,#f8fafc); border: 1px solid var(--color-border,#e2e8f0); border-radius: 6px; font-size: var(--d-fs-sm); }

.kb-tags-input { display:flex; flex-wrap: wrap; gap: 0.3rem; padding: 0.4rem; border: 1px solid var(--color-border,#e2e8f0); border-radius: 8px; }
.kb-tags-input .kb-tag { cursor: default; display: inline-flex; align-items: center; gap: 0.3rem; }
.kb-tags-input .kb-tag button { background: none; border: none; cursor: pointer; padding: 0; color: var(--color-text-tertiary,#94a3b8); }
.kb-tags-input input { flex: 1; min-width: 80px; border: none; outline: none; padding: 0.3rem; background: transparent; }

.kb-entities-preview { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.5rem; }
.kb-entity-chip { padding: 0.2rem 0.5rem; background: #d6e7f5; color: var(--thoxan-700); border-radius: 4px; font-size: var(--d-fs-xs); }
.kb-entity-chip .type { opacity: 0.6; font-size: var(--d-fs-xs); margin-left: 3px; }

.kb-spinner {
    display:inline-block; width:16px; height:16px; border:2px solid #e2e8f0; border-top-color:var(--thoxan-700);
    border-radius:50%; animation: kb-spin 0.8s linear infinite; vertical-align: middle; margin-right: 6px;
}
@keyframes kb-spin { to { transform: rotate(360deg); } }

.kb-detail-card { background: var(--color-bg-primary,#fff); border: 1px solid var(--color-border,#e2e8f0); border-radius: 10px; padding: 1.25rem; margin-bottom: 1rem; }
.kb-chunk { border-bottom: 1px solid var(--color-border,#e2e8f0); padding: 0.75rem 0; font-size: var(--d-fs-sm); }
.kb-chunk:last-child { border-bottom: none; }
.kb-chunk-header { font-size: var(--d-fs-xs); color: var(--color-text-tertiary,#94a3b8); margin-bottom: 0.3rem; }
</style>

<div class="kb-edit-container">
    <div class="thx-page-header">
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <a href="/wissen" class="kb-back" style="color:var(--color-text-secondary,#64748b); text-decoration:none; display:flex;"><span class="material-symbols-rounded">arrow_back</span></a>
            <div>
                <h1 class="thx-page-title" id="kb-page-title"><?= $documentId ? 'Eintrag bearbeiten' : 'Neuer Wissens-Eintrag' ?></h1>
                <div class="thx-page-subtitle"><?= $documentId ? 'Inhalt, Metadaten und Tags bearbeiten' : 'Dokument, URL, Website, Text oder Chat-Beitrag als Wissen aufnehmen' ?></div>
            </div>
        </div>
    </div>

    <?php if (!$documentId): ?>
    <!-- NEW: 4 Tabs -->
    <div class="kb-tabs">
        <button class="kb-tab active" onclick="switchTab('upload')">
            <span class="material-symbols-rounded">upload_file</span> Dokument
        </button>
        <button class="kb-tab" onclick="switchTab('url')">
            <span class="material-symbols-rounded">link</span> URL
        </button>
        <button class="kb-tab" onclick="switchTab('website')">
            <span class="material-symbols-rounded">language</span> Website
        </button>
        <button class="kb-tab" onclick="switchTab('text')">
            <span class="material-symbols-rounded">edit_note</span> Text
        </button>
        <button class="kb-tab" onclick="switchTab('chat')">
            <span class="material-symbols-rounded">forum</span> Aus Chat
        </button>
    </div>

    <!-- Tab 1: Upload -->
    <div class="kb-tab-content active" id="tab-upload">
        <input type="file" id="file-input" style="display:none" onchange="handleFileSelected(event)" accept=".pdf,.docx,.txt,.md,.html,.htm">
        <div class="kb-dropzone" id="dropzone" onclick="document.getElementById('file-input').click()">
            <span class="material-symbols-rounded">cloud_upload</span>
            <p style="margin:0.5rem 0; font-weight:600;">Datei hier ablegen oder klicken</p>
            <p style="margin:0; font-size: var(--d-fs-sm); color: var(--color-text-secondary,#64748b);">PDF, Word, Text, Markdown, HTML — max 25 MB</p>
        </div>
        <?php if ($isAdmin && !empty($customers)): ?>
        <div class="kb-form-group" style="margin-top:1rem;">
            <label>Kunde</label>
            <select id="upload-customer">
                <option value="">⚡ KI wählt Kunden</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tab 2: URL -->
    <div class="kb-tab-content" id="tab-url">
        <div class="kb-form-group">
            <label>URL</label>
            <input type="url" id="url-input" placeholder="https://example.com/seite">
        </div>
        <?php if ($isAdmin && !empty($customers)): ?>
        <div class="kb-form-group">
            <label>Kunde</label>
            <select id="url-customer">
                <option value="">⚡ KI wählt Kunden</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button class="thx-btn thx-btn-primary" onclick="submitUrl()" id="url-btn">Crawlen &amp; analysieren</button>
    </div>

    <!-- Tab: Website (Multi-Page Crawl) -->
    <div class="kb-tab-content" id="tab-website">
        <div class="kb-form-group">
            <label>Start-URL</label>
            <input type="url" id="website-input" placeholder="https://example.com">
            <small style="color:var(--color-text-secondary,#64748b);">Die KI folgt internen Links der gleichen Domain und legt pro Seite ein Dokument an.</small>
        </div>
        <div class="kb-form-row">
            <div class="kb-form-group">
                <label>Max. Seiten</label>
                <input type="number" id="website-max-pages" value="15" min="1" max="50">
            </div>
            <div class="kb-form-group">
                <label>Max. Tiefe</label>
                <select id="website-max-depth">
                    <option value="1">1 (nur Start-URL)</option>
                    <option value="2" selected>2 (Start + verlinkte Seiten)</option>
                    <option value="3">3 (2 Klicks tief)</option>
                </select>
            </div>
        </div>
        <?php if ($isAdmin && !empty($customers)): ?>
        <div class="kb-form-group">
            <label>Kunde</label>
            <select id="website-customer">
                <option value="">⚡ KI wählt Kunden</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button class="thx-btn thx-btn-primary" onclick="submitWebsite()" id="website-btn">Website crawlen</button>

        <div id="website-progress" style="display:none; margin-top:1.5rem;">
            <div style="background:var(--color-bg-secondary,#f8fafc);border:1px solid var(--color-border,#e2e8f0);border-radius:10px;padding:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                    <strong id="website-progress-title">Crawl laeuft…</strong>
                    <span id="website-progress-counter" style="font-size: var(--d-fs-sm);color:var(--color-text-secondary,#64748b);"></span>
                </div>
                <div style="background:#e5e7eb;height:6px;border-radius:3px;overflow:hidden;margin-bottom:0.75rem;">
                    <div id="website-progress-bar" style="height:100%;background:linear-gradient(90deg,var(--thoxan-700),#1976d2);width:0%;transition:width 0.3s;"></div>
                </div>
                <div id="website-progress-log" style="font-size: var(--d-fs-sm);max-height:200px;overflow-y:auto;color:var(--color-text-secondary,#64748b);font-family:monospace;"></div>
            </div>
        </div>

        <div id="website-summary" style="display:none; margin-top:1.5rem;"></div>
    </div>

    <!-- Tab 3: Text -->
    <div class="kb-tab-content" id="tab-text">
        <div class="kb-form-group">
            <label>Titel (optional)</label>
            <input type="text" id="text-title" placeholder="Wird von KI vorgeschlagen wenn leer">
        </div>
        <div class="kb-form-group">
            <label>Inhalt</label>
            <textarea id="text-content" rows="10" placeholder="Dein Text..."></textarea>
        </div>
        <?php if ($isAdmin && !empty($customers)): ?>
        <div class="kb-form-group">
            <label>Kunde</label>
            <select id="text-customer">
                <option value="">⚡ KI wählt Kunden</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button class="thx-btn thx-btn-primary" onclick="submitText()" id="text-btn">Analysieren</button>
    </div>

    <!-- Tab 4: Chat-Import -->
    <div class="kb-tab-content" id="tab-chat">
        <p style="color:var(--color-text-secondary,#64748b);font-size: var(--d-fs-sm);">
            Oeffne einen Chat, klicke bei einer Antwort auf "Als Wissen speichern". Oder gib hier die Message-ID ein:
        </p>
        <div class="kb-form-group">
            <label>Chat Message-ID</label>
            <input type="number" id="chat-msg-id" placeholder="z.B. 12345">
        </div>
        <button class="thx-btn thx-btn-primary" onclick="submitChat()" id="chat-btn">Analysieren</button>
    </div>

    <?php else: ?>
    <!-- EDIT/DETAIL -->
    <div id="kb-detail-wrapper">
        <div style="text-align:center;padding:2rem;"><span class="kb-spinner"></span>Lade...</div>
    </div>
    <?php endif; ?>
</div>

<!-- Preview Modal (Commit-Flow) -->
<div class="kb-modal-overlay" id="preview-modal">
    <div class="kb-modal">
        <h2>KI-Vorschlag bestaetigen</h2>
        <div id="preview-stats" class="kb-stats-row"></div>

        <input type="hidden" id="preview-prepared-id">

        <div class="kb-form-group">
            <label>Titel</label>
            <input type="text" id="preview-title">
        </div>
        <div class="kb-form-group">
            <label>Beschreibung</label>
            <textarea id="preview-description" rows="3"></textarea>
        </div>
        <div class="kb-form-row">
            <div class="kb-form-group">
                <label>Kunde</label>
                <select id="preview-customer">
                    <option value="">⚡ KI wählt Kunden</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="kb-form-group">
                <label>Kategorie</label>
                <select id="preview-category">
                    <option>Produktinfo</option>
                    <option>Referenz</option>
                    <option>Prozess</option>
                    <option>FAQ</option>
                    <option>Notiz</option>
                    <option>Rechtlich</option>
                    <option>Technik</option>
                    <option>Marketing</option>
                    <option>Sonstige</option>
                </select>
            </div>
        </div>
        <div class="kb-form-group">
            <label>Tags</label>
            <div class="kb-tags-input" id="preview-tags-input" onclick="document.getElementById('preview-tag-input').focus()">
                <input type="text" id="preview-tag-input" placeholder="Tag eingeben + Enter" onkeydown="handleTagKey(event)">
            </div>
        </div>
        <div class="kb-form-group">
            <label>Top Entities</label>
            <div id="preview-entities" class="kb-entities-preview"></div>
        </div>

        <div class="kb-actions">
            <button class="thx-btn thx-btn-secondary" onclick="closePreviewModal()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" id="commit-btn" onclick="commitPrepared()">
                <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">save</span>
                Speichern
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    const csrfToken = '<?= \Core\Session::getCsrfToken() ?>';
    const documentId = <?= $documentId ? (int) $documentId : 'null' ?>;
    let previewTags = [];

    async function apiFetch(url, options = {}) {
        const headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
        const resp = await fetch('/api/v1' + url, { ...options, headers });
        return resp.json();
    }

    async function apiUpload(url, formData) {
        const resp = await fetch('/api/v1' + url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData
        });
        return resp.json();
    }

    function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    // Wissensdatenbank verlangt einen Kunden — sonst Ingest abgelehnt.
    // requireCustomer fokussiert das passende Select + Fehlermeldung, gibt customerId zurueck oder null.
    function requireCustomer(selectId, label) {
        const sel = document.getElementById(selectId);
        const val = (sel?.value || '').trim();
        if (!val) {
            alert('Bitte erst einen Kunden für „' + label + '" wählen. Die Wissensdatenbank speichert nur kundenbezogene Einträge.');
            sel?.focus();
            return null;
        }
        return val;
    }

    // ===== Tabs =====
    window.switchTab = function(name) {
        document.querySelectorAll('.kb-tab').forEach(t => t.classList.remove('active'));
        event.target.closest('.kb-tab').classList.add('active');
        document.querySelectorAll('.kb-tab-content').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
    };

    // ===== Upload =====
    window.handleFileSelected = async function(event) {
        const file = event.target.files[0];
        if (!file) return;
        await uploadFile(file);
    };

    async function uploadFile(file) {
        const customerId = requireCustomer('upload-customer', 'Datei-Upload');
        if (!customerId) return;
        const dropzone = document.getElementById('dropzone');
        dropzone.innerHTML = '<span class="kb-spinner"></span>Analysiere ' + esc(file.name) + '...';

        const fd = new FormData();
        fd.append('file', file);
        fd.append('customer_id', customerId);

        try {
            const r = await apiUpload('/knowledge/upload', fd);
            if (r.success) {
                openPreviewModal(r.data);
                // Dropzone zuruecksetzen
                dropzone.innerHTML = '<span class="material-symbols-rounded">cloud_upload</span><p style="margin:0.5rem 0;font-weight:600;">Datei hier ablegen oder klicken</p><p style="margin:0;font-size: var(--d-fs-sm);color:var(--color-text-secondary,#64748b);">PDF, Word, Text, Markdown, HTML — max 25 MB</p>';
            } else {
                alert(r.message || 'Fehler');
                dropzone.innerHTML = '<span class="material-symbols-rounded">cloud_upload</span><p style="margin:0.5rem 0;font-weight:600;">Datei hier ablegen oder klicken</p>';
            }
        } catch (e) {
            alert('Fehler: ' + e.message);
        }
    }

    // Drag & Drop
    const dropzone = document.getElementById('dropzone');
    if (dropzone) {
        dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
        dropzone.addEventListener('drop', e => {
            e.preventDefault();
            dropzone.classList.remove('drag-over');
            if (e.dataTransfer.files.length) uploadFile(e.dataTransfer.files[0]);
        });
    }

    // ===== URL =====
    window.submitUrl = async function() {
        const url = document.getElementById('url-input').value.trim();
        if (!url) { alert('URL eingeben'); return; }
        const customerId = requireCustomer('url-customer', 'URL-Import');
        if (!customerId) return;
        const btn = document.getElementById('url-btn');
        btn.disabled = true; btn.innerHTML = '<span class="kb-spinner"></span>Crawle & analysiere...';
        try {
            const r = await apiFetch('/knowledge/url', {
                method: 'POST',
                body: JSON.stringify({ url, customer_id: customerId })
            });
            if (r.success) openPreviewModal(r.data);
            else alert(r.message || 'Fehler');
        } finally {
            btn.disabled = false; btn.textContent = 'Crawlen & analysieren';
        }
    };

    // ===== Website (SSE, Multi-Page) =====
    window.submitWebsite = async function() {
        const url = document.getElementById('website-input').value.trim();
        if (!url) { alert('Start-URL eingeben'); return; }
        const customerId = requireCustomer('website-customer', 'Website-Crawl');
        if (!customerId) return;
        const maxPages = parseInt(document.getElementById('website-max-pages').value) || 15;
        const maxDepth = parseInt(document.getElementById('website-max-depth').value) || 2;

        const btn = document.getElementById('website-btn');
        const progressBox = document.getElementById('website-progress');
        const progressTitle = document.getElementById('website-progress-title');
        const progressCounter = document.getElementById('website-progress-counter');
        const progressBar = document.getElementById('website-progress-bar');
        const progressLog = document.getElementById('website-progress-log');
        const summary = document.getElementById('website-summary');

        btn.disabled = true;
        btn.innerHTML = '<span class="kb-spinner"></span>Crawl laeuft...';
        progressBox.style.display = 'block';
        summary.style.display = 'none';
        progressLog.innerHTML = '';
        progressBar.style.width = '0%';
        progressCounter.textContent = '';

        const logLine = (text, color) => {
            const div = document.createElement('div');
            div.textContent = text;
            if (color) div.style.color = color;
            progressLog.appendChild(div);
            progressLog.scrollTop = progressLog.scrollHeight;
        };

        let created = 0, skipped = 0, failed = 0;
        let crawlTotal = maxPages;

        try {
            const response = await fetch('/api/v1/knowledge/website', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= \Core\Session::getCsrfToken() ?>' },
                body: JSON.stringify({ url, customer_id: customerId, max_pages: maxPages, max_depth: maxDepth })
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });
                const parts = buffer.split('\n\n');
                buffer = parts.pop();

                for (const chunk of parts) {
                    if (!chunk.trim()) continue;
                    let event = 'message', data = {};
                    for (const line of chunk.split('\n')) {
                        if (line.startsWith('event: ')) event = line.substring(7);
                        else if (line.startsWith('data: ')) {
                            try { data = JSON.parse(line.substring(6)); } catch (e) {}
                        }
                    }

                    if (event === 'start') {
                        logLine('Start-Crawl: ' + data.url + ' (max ' + data.max_pages + ' Seiten, Tiefe ' + data.max_depth + ')');
                    } else if (event === 'fetching') {
                        progressCounter.textContent = data.done + '/' + data.total + ' gecrawlt';
                    } else if (event === 'fetched') {
                        logLine('✓ ' + data.url + ' (' + data.chars + ' Zeichen)');
                        progressBar.style.width = Math.min(50, (data.done / data.total) * 50) + '%';
                    } else if (event === 'crawled') {
                        crawlTotal = data.count;
                        logLine('── Crawl fertig: ' + data.count + ' Seiten gefunden. Starte Analyse…', 'var(--thoxan-700)');
                        progressTitle.textContent = 'Analyse & Speichern…';
                    } else if (event === 'processing') {
                        progressCounter.textContent = data.index + '/' + data.total + ' analysiert';
                        progressBar.style.width = (50 + (data.index / data.total) * 50) + '%';
                    } else if (event === 'created') {
                        created++;
                        logLine('+ ' + data.title + ' (' + data.chunks + ' Chunks, ' + data.entities + ' Entities)', '#059669');
                    } else if (event === 'skipped') {
                        skipped++;
                        logLine('~ ' + data.url + ' (' + data.reason + ')', '#94a3b8');
                    } else if (event === 'failed') {
                        failed++;
                        logLine('✗ ' + data.url + ': ' + data.error, 'var(--rose-600)');
                    } else if (event === 'done') {
                        progressBar.style.width = '100%';
                        progressTitle.textContent = 'Fertig';
                        summary.innerHTML = `
                            <div style="padding:1rem;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;">
                                <div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;">
                                    <div><strong style="color:#059669;font-size: var(--d-fs-2xl);">${data.created}</strong> <span style="color:#047857;">neu angelegt</span></div>
                                    <div><strong style="color:#64748b;font-size: var(--d-fs-xl);">${data.skipped}</strong> <span style="color:#64748b;">uebersprungen</span></div>
                                    ${data.failed > 0 ? `<div><strong style="color:var(--rose-600);font-size: var(--d-fs-xl);">${data.failed}</strong> <span style="color:var(--rose-600);">fehlgeschlagen</span></div>` : ''}
                                    <a href="/wissen" class="thx-btn thx-btn-primary thx-btn-small" style="margin-left:auto;">Zur Uebersicht</a>
                                </div>
                            </div>
                        `;
                        summary.style.display = 'block';
                    } else if (event === 'error') {
                        throw new Error(data.message || 'Unbekannter Fehler');
                    }
                }
            }
        } catch (err) {
            logLine('Fehler: ' + (err.message || err), 'var(--rose-600)');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Website crawlen';
        }
    };

    // ===== Text =====
    window.submitText = async function() {
        const title = document.getElementById('text-title').value.trim();
        const content = document.getElementById('text-content').value.trim();
        if (content.length < 20) { alert('Inhalt zu kurz'); return; }
        const customerId = requireCustomer('text-customer', 'Text-Import');
        if (!customerId) return;
        const btn = document.getElementById('text-btn');
        btn.disabled = true; btn.innerHTML = '<span class="kb-spinner"></span>Analysiere...';
        try {
            const r = await apiFetch('/knowledge/text', {
                method: 'POST',
                body: JSON.stringify({ title, content, customer_id: customerId })
            });
            if (r.success) openPreviewModal(r.data);
            else alert(r.message || 'Fehler');
        } finally {
            btn.disabled = false; btn.textContent = 'Analysieren';
        }
    };

    // ===== Chat-Import =====
    window.submitChat = async function() {
        const msgId = parseInt(document.getElementById('chat-msg-id').value);
        if (!msgId) { alert('Message-ID eingeben'); return; }
        const btn = document.getElementById('chat-btn');
        btn.disabled = true; btn.innerHTML = '<span class="kb-spinner"></span>Analysiere...';
        try {
            const r = await apiFetch('/knowledge/chat-import', {
                method: 'POST',
                body: JSON.stringify({ message_id: msgId })
            });
            if (r.success) openPreviewModal(r.data);
            else alert(r.message || 'Fehler');
        } finally {
            btn.disabled = false; btn.textContent = 'Analysieren';
        }
    };

    // ===== Preview Modal =====
    function openPreviewModal(data) {
        document.getElementById('preview-prepared-id').value = data.prepared_id;
        document.getElementById('preview-title').value = data.suggestion.title;
        document.getElementById('preview-description').value = data.suggestion.description;
        document.getElementById('preview-category').value = data.suggestion.category;

        // Kunde: aus context extra oder Vorschlag
        const custSelect = document.getElementById('preview-customer');
        if (data.context && data.context.customer_id_suggestion) {
            custSelect.value = data.context.customer_id_suggestion;
        }

        // Tags
        previewTags = Array.isArray(data.suggestion.tags) ? data.suggestion.tags : [];
        renderTags();

        // Stats
        const s = data.stats || {};
        document.getElementById('preview-stats').innerHTML = `
            <span class="kb-chip">${s.chunks || 0} Chunks</span>
            <span class="kb-chip">${s.entities || 0} Entities</span>
            <span class="kb-chip">${s.relations || 0} Relations</span>
        `;

        // Entities preview
        const ents = data.top_entities || [];
        document.getElementById('preview-entities').innerHTML = ents.map(e =>
            `<span class="kb-entity-chip">${esc(e.name)}<span class="type">(${e.type})</span></span>`
        ).join('');

        document.getElementById('preview-modal').classList.add('active');
    }

    function renderTags() {
        const container = document.getElementById('preview-tags-input');
        const input = container.querySelector('input');
        // Alle vorhandenen Tags entfernen, input beibehalten
        container.querySelectorAll('.kb-tag').forEach(t => t.remove());
        previewTags.forEach((tag, i) => {
            const el = document.createElement('span');
            el.className = 'kb-tag';
            el.innerHTML = '#' + esc(tag) + ' <button onclick="removeTag(' + i + ')">&times;</button>';
            container.insertBefore(el, input);
        });
    }

    window.handleTagKey = function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const input = e.target;
            const val = input.value.trim().replace(/,$/, '').toLowerCase();
            if (val && !previewTags.includes(val)) {
                previewTags.push(val);
                renderTags();
            }
            input.value = '';
        } else if (e.key === 'Backspace' && e.target.value === '' && previewTags.length > 0) {
            previewTags.pop();
            renderTags();
        }
    };

    window.removeTag = function(i) {
        previewTags.splice(i, 1);
        renderTags();
    };

    window.closePreviewModal = function() {
        document.getElementById('preview-modal').classList.remove('active');
    };

    window.commitPrepared = async function() {
        const btn = document.getElementById('commit-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="kb-spinner"></span>Speichere...';

        const payload = {
            prepared_id: document.getElementById('preview-prepared-id').value,
            title: document.getElementById('preview-title').value.trim(),
            description: document.getElementById('preview-description').value.trim(),
            customer_id: document.getElementById('preview-customer').value,
            category: document.getElementById('preview-category').value,
            tags: previewTags,
        };

        try {
            const r = await apiFetch('/knowledge/commit', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            if (r.success) {
                // Wenn im Embed-Modus (Iframe in Modal): Parent benachrichtigen, sonst weiterleiten
                if (window.parent !== window && document.body.classList.contains('thx-embed')) {
                    window.parent.postMessage({ type: 'wissen-saved', document_id: r.data.document_id }, '*');
                } else {
                    window.location.href = '/wissen/' + r.data.document_id;
                }
            } else {
                alert(r.message || 'Fehler');
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">save</span> Speichern';
            }
        } catch (e) {
            alert('Fehler: ' + e.message);
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">save</span> Speichern';
        }
    };

    // Click outside to close modal
    document.getElementById('preview-modal').addEventListener('click', function(e) {
        if (e.target === this) closePreviewModal();
    });

    // ===== Preselect Customer via ?customer_id=X =====
    (function preselectCustomer() {
        const qs = new URLSearchParams(window.location.search);
        const cid = qs.get('customer_id');
        if (!cid) return;
        ['upload-customer', 'url-customer', 'text-customer', 'website-customer'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = cid;
        });
    })();

    // ===== Detail-Ansicht =====
    if (documentId) {
        loadDetail();
    }

    let currentDoc = null;

    async function loadDetail() {
        const r = await apiFetch('/knowledge/documents/' + documentId);
        const wrap = document.getElementById('kb-detail-wrapper');
        if (!r.success) {
            wrap.innerHTML = '<div style="padding:1rem;color:red;">' + esc(r.message) + '</div>';
            return;
        }
        currentDoc = r.data;
        renderDetailView();
    }

    function renderDetailView() {
        const d = currentDoc;
        const wrap = document.getElementById('kb-detail-wrapper');
        document.getElementById('kb-page-title').textContent = d.title;

        const tagsHtml = (d.tags || []).map(t => '<span class="kb-tag">#' + esc(t) + '</span>').join(' ');
        const date = new Date(d.updated_at).toLocaleDateString('de-DE');

        wrap.innerHTML = `
            <div class="kb-detail-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                    <div style="flex:1;">
                        <p style="margin:0 0 0.5rem;color:var(--color-text-secondary,#64748b);">${esc(d.description || '')}</p>
                        <div style="font-size: var(--d-fs-sm);color:var(--color-text-tertiary,#94a3b8);">
                            ${d.customer_name ? esc(d.customer_name) + ' · ' : ''}
                            ${esc(d.category || '')} · ${d.source_type} · ${date}
                        </div>
                        <div style="margin-top:0.5rem;">${tagsHtml}</div>
                    </div>
                    <div style="display:flex;gap:0.5rem;">
                        <button class="thx-btn thx-btn-primary" onclick="enterEditMode()"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">edit</span> Bearbeiten</button>
                        <a href="/wissen/${d.id}/graph" class="thx-btn thx-btn-secondary"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">hub</span> Graph</a>
                        <button class="thx-btn thx-btn-danger" onclick="deleteDoc()">Loeschen</button>
                    </div>
                </div>
            </div>

            <div class="kb-detail-card">
                <h3 style="margin:0 0 0.75rem;font-size: var(--d-fs-base);">Chunks (${(d.chunks || []).length})</h3>
                ${(d.chunks || []).slice(0, 10).map(c =>
                    '<div class="kb-chunk"><div class="kb-chunk-header">#' + c.chunk_index + ' · ' + (c.word_count || 0) + ' Woerter</div>' + esc(c.content).substring(0, 400) + (c.content.length > 400 ? '...' : '') + '</div>'
                ).join('')}
                ${(d.chunks || []).length > 10 ? '<p style="margin:0.75rem 0 0;font-size: var(--d-fs-sm);color:var(--color-text-tertiary,#94a3b8);">... und ' + ((d.chunks || []).length - 10) + ' weitere Chunks</p>' : ''}
            </div>

            ${d.original_content ? `
            <div class="kb-detail-card">
                <details>
                    <summary style="cursor:pointer;font-weight:600;font-size: var(--d-fs-base);list-style:none;display:flex;align-items:center;gap:0.5rem;">
                        <span class="material-symbols-rounded" style="font-size:18px;">description</span>
                        Original-Text (${(d.original_content || '').length.toLocaleString('de-DE')} Zeichen)
                        <span class="material-symbols-rounded" style="font-size:18px;margin-left:auto;">expand_more</span>
                    </summary>
                    <pre style="margin-top:0.75rem;padding:0.75rem;background:var(--color-bg-secondary,#f8fafc);border-radius:6px;white-space:pre-wrap;word-break:break-word;font-family:inherit;font-size: var(--d-fs-sm);max-height:400px;overflow-y:auto;">${esc(d.original_content)}</pre>
                </details>
            </div>
            ` : ''}

            <div class="kb-detail-card">
                <h3 style="margin:0 0 0.75rem;font-size: var(--d-fs-base);">Entities (${(d.entities || []).length})</h3>
                <div class="kb-entities-preview">
                    ${(d.entities || []).map(e =>
                        '<span class="kb-entity-chip">' + esc(e.name) + '<span class="type">(' + e.type + ')</span></span>'
                    ).join('')}
                </div>
            </div>

            <div class="kb-detail-card">
                <h3 style="margin:0 0 0.75rem;font-size: var(--d-fs-base);">Relations (${(d.relations || []).length})</h3>
                ${(d.relations || []).slice(0, 30).map(r =>
                    '<div style="font-size: var(--d-fs-sm);padding:0.3rem 0;"><b>' + esc(r.from_name) + '</b> <span style="color:var(--color-primary,var(--thoxan-700))">' + esc(r.type) + '</span> <b>' + esc(r.to_name) + '</b> <span style="color:var(--color-text-tertiary,#94a3b8);margin-left:0.5rem;">(w=' + (r.weight || 0).toFixed(2) + ')</span></div>'
                ).join('')}
            </div>
        `;
    }

    // ===== Edit-Modus =====
    let editTags = [];

    window.enterEditMode = function() {
        const d = currentDoc;
        editTags = Array.isArray(d.tags) ? [...d.tags] : [];
        const wrap = document.getElementById('kb-detail-wrapper');

        wrap.innerHTML = `
            <div class="kb-detail-card">
                <h3 style="margin:0 0 0.75rem;font-size: var(--d-fs-base);display:flex;align-items:center;gap:0.5rem;">
                    <span class="material-symbols-rounded" style="font-size:20px;">edit</span>
                    Eintrag bearbeiten
                </h3>
                <p style="margin:0 0 1rem;color:var(--color-text-secondary,#64748b);font-size: var(--d-fs-sm);">
                    Bei Aenderungen am <strong>Inhalt</strong> werden Chunks, Embeddings, Entities und Relations automatisch neu berechnet (~10-30 Sekunden). Nur Metadata-Aenderungen sind sofort.
                </p>

                <div class="form-group">
                    <label>Titel</label>
                    <input type="text" id="edit-title" value="${esc(d.title)}">
                </div>
                <div class="form-group">
                    <label>Beschreibung</label>
                    <textarea id="edit-description" rows="2">${esc(d.description || '')}</textarea>
                </div>
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <div class="form-group">
                        <label>Kunde</label>
                        <select id="edit-customer">
                            <option value="">⚡ KI wählt Kunden</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kategorie</label>
                        <select id="edit-category">
                            <option>Produktinfo</option>
                            <option>Referenz</option>
                            <option>Prozess</option>
                            <option>FAQ</option>
                            <option>Notiz</option>
                            <option>Rechtlich</option>
                            <option>Technik</option>
                            <option>Marketing</option>
                            <option>Sonstige</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Tags</label>
                    <div class="kb-tags-input" id="edit-tags-input" onclick="document.getElementById('edit-tag-input').focus()">
                        <input type="text" id="edit-tag-input" placeholder="Tag + Enter" onkeydown="editHandleTagKey(event)">
                    </div>
                </div>
                <div class="form-group">
                    <label>Inhalt <span style="color:var(--color-text-tertiary,#94a3b8);font-weight:400;font-size: var(--d-fs-sm);">— Aenderungen triggern Neu-Verarbeitung</span></label>
                    <textarea id="edit-content" rows="15" style="font-family:inherit;">${esc(d.original_content || '')}</textarea>
                </div>
                <div class="cv-modal-actions" style="display:flex;justify-content:space-between;margin-top:1rem;">
                    <button class="thx-btn thx-btn-secondary" onclick="cancelEdit()">Abbrechen</button>
                    <button class="thx-btn thx-btn-primary" id="edit-save-btn" onclick="saveEdit()">
                        <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">save</span>
                        Speichern
                    </button>
                </div>
            </div>
        `;

        document.getElementById('edit-customer').value = d.customer_id || '';
        document.getElementById('edit-category').value = d.category || 'Sonstige';
        renderEditTags();
    };

    function renderEditTags() {
        const container = document.getElementById('edit-tags-input');
        container.querySelectorAll('.kb-tag').forEach(t => t.remove());
        const input = container.querySelector('input');
        editTags.forEach((tag, i) => {
            const el = document.createElement('span');
            el.className = 'kb-tag';
            el.innerHTML = '#' + esc(tag) + ' <button onclick="editRemoveTag(' + i + ')">&times;</button>';
            container.insertBefore(el, input);
        });
    }

    window.editHandleTagKey = function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const input = e.target;
            const val = input.value.trim().replace(/,$/, '').toLowerCase();
            if (val && !editTags.includes(val)) { editTags.push(val); renderEditTags(); }
            input.value = '';
        } else if (e.key === 'Backspace' && e.target.value === '' && editTags.length > 0) {
            editTags.pop(); renderEditTags();
        }
    };

    window.editRemoveTag = function(i) { editTags.splice(i, 1); renderEditTags(); };

    window.cancelEdit = function() { renderDetailView(); };

    window.saveEdit = async function() {
        const btn = document.getElementById('edit-save-btn');
        const payload = {
            title: document.getElementById('edit-title').value.trim(),
            description: document.getElementById('edit-description').value.trim(),
            customer_id: document.getElementById('edit-customer').value,
            category: document.getElementById('edit-category').value,
            tags: editTags,
            content: document.getElementById('edit-content').value.trim(),
        };

        const contentChanged = payload.content !== (currentDoc.original_content || '').trim();

        btn.disabled = true;
        btn.innerHTML = '<span class="kb-spinner"></span>' + (contentChanged ? 'Neu-Verarbeitung...' : 'Speichere...');

        try {
            const r = await apiFetch('/knowledge/documents/' + documentId + '/reprocess', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            if (r.success) {
                currentDoc = null;
                await loadDetail();
                App.showNotification(r.message || 'Gespeichert', 'success');
            } else {
                alert(r.message || 'Fehler');
            }
        } catch (e) {
            alert('Fehler: ' + e.message);
        }
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">save</span> Speichern';
    };

    window.deleteDoc = async function() {
        if (!confirm('Wissens-Eintrag wirklich loeschen?')) return;
        const r = await apiFetch('/knowledge/documents/' + documentId, { method: 'DELETE' });
        if (r.success) {
            window.location.href = '/wissen';
        } else {
            alert(r.message || 'Fehler');
        }
    };

    // "Neuen Kunden anlegen…" an alle Kunden-Selects anhaengen
    function attachQuickCreateToAllCustomerSelects() {
        if (!window.ThxQuickCustomer) return;
        const ids = ['upload-customer', 'url-customer', 'website-customer', 'text-customer', 'preview-customer'];
        ids.forEach(id => {
            const sel = document.getElementById(id);
            if (sel) ThxQuickCustomer.attachToSelect(sel);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachQuickCreateToAllCustomerSelects);
    } else {
        attachQuickCreateToAllCustomerSelects();
    }
})();
</script>
