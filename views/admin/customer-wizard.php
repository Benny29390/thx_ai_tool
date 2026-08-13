<?php
$csrfToken = \Core\Session::getCsrfToken();
?>
<style>
.cw-wrap {
    max-width: 1100px;
    /* margin/padding entfernt — .main-content liefert die Gutter konsistent */
}
.cw-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.cw-header h1 {
    margin: 0;
    font-size: var(--d-fs-xl);
    font-weight: 600;
}
.cw-grid {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 1.5rem;
    align-items: start;
}
@media (max-width: 768px) {
    .cw-grid { grid-template-columns: 1fr; }
}

.cw-stepper {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.75rem;
    position: sticky;
    top: 1rem;
}
.cw-step {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.5rem;
    border-radius: 8px;
    color: #64748b;
    font-size: var(--d-fs-sm);
    cursor: pointer;
    transition: background 0.15s;
}
.cw-step:hover { background: #f8fafc; }
.cw-step.active {
    background: #e6f0fa;
    color: var(--thoxan-900);
    font-weight: 600;
}
.cw-step.done {
    color: #059669;
}
.cw-step .cw-step-num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--d-fs-xs);
    font-weight: 600;
    flex-shrink: 0;
}
.cw-step.active .cw-step-num { background: var(--thoxan-700); color: #fff; }
.cw-step.done .cw-step-num { background: #10b981; color: #fff; }
.cw-step.done .cw-step-num::after { content: '✓'; }
.cw-step.done .cw-step-num span { display: none; }

.cw-content {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 2rem;
    min-height: 400px;
}
.cw-step-title {
    font-size: var(--d-fs-lg);
    font-weight: 600;
    margin: 0 0 0.25rem;
}
.cw-step-subtitle {
    color: #64748b;
    font-size: var(--d-fs-sm);
    margin: 0 0 1.5rem;
}
.cw-form-group { margin-bottom: 1rem; }
.cw-form-group label {
    display: block;
    font-size: var(--d-fs-sm);
    font-weight: 500;
    margin-bottom: 0.3rem;
    color: #475569;
}
.cw-form-group input, .cw-form-group textarea, .cw-form-group select {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: var(--d-fs-sm);
    box-sizing: border-box;
    font-family: inherit;
}
.cw-form-group textarea { min-height: 90px; resize: vertical; }
.cw-form-group input:focus, .cw-form-group textarea:focus, .cw-form-group select:focus {
    outline: none; border-color: var(--thoxan-700);
}
.cw-form-group small { color: #94a3b8; font-size: var(--d-fs-xs); }
.cw-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

/* URL-Hero (Schnellstart per KI) */
.cw-url-hero {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 14px 18px; margin-bottom: 0.75rem;
    background: linear-gradient(135deg, var(--thoxan-100) 0%, #f0fdf4 100%);
    border: 2px solid #c5dcf3; border-radius: 14px;
    box-shadow: 0 4px 16px rgba(0,76,155,0.06);
    transition: all 0.2s;
}
.cw-url-hero:focus-within {
    border-color: var(--thoxan-700);
    box-shadow: 0 4px 20px rgba(0,76,155,0.15);
}
.cw-url-hero .cw-url-icon {
    color: var(--thoxan-700); font-size: 26px; flex-shrink: 0;
}
.cw-url-hero input {
    flex: 1; border: 0; background: transparent !important; padding: 8px 4px;
    font-size: var(--d-fs-base); outline: none; color: #1e293b; min-width: 0;
}
.cw-url-hero input::placeholder { color: #94a3b8; }
.cw-url-hero button {
    flex-shrink: 0; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px;
    background: linear-gradient(135deg, var(--thoxan-700), var(--thoxan-600)) !important;
    border: 0; color: #fff !important;
    font-weight: 600;
}
.cw-url-hero button:hover { background: linear-gradient(135deg, var(--thoxan-900), var(--thoxan-700)) !important; }
.cw-url-hero button[disabled] { opacity: 0.7; cursor: wait; }
.cw-url-hero button .material-symbols-rounded { font-size: 18px; }

.cw-divider {
    text-align: center; margin: 0.9rem 0; position: relative;
    color: #94a3b8; font-size: var(--d-fs-sm); text-transform: uppercase; letter-spacing: 0.5px;
}
.cw-divider::before, .cw-divider::after {
    content: ''; position: absolute; top: 50%; width: 35%; height: 1px; background: #e2e8f0;
}
.cw-divider::before { left: 0; }
.cw-divider::after { right: 0; }
.cw-divider span { background: #fff; padding: 0 12px; position: relative; }

/* Quick-Result-Vorschau (nach KI-Analyse) */
.cw-quick-result {
    background: linear-gradient(180deg, #f0fdf4 0%, #fff 100%);
    border: 1px solid #bbf7d0; border-radius: 14px; padding: 14px 16px;
    margin-top: 0.75rem;
}
.cw-quick-result-head {
    display: flex; gap: 0.6rem; align-items: flex-start; margin-bottom: 0.7rem;
}
.cw-quick-result-head > .material-symbols-rounded {
    color: #047857; font-size: 26px; flex-shrink: 0;
}
.cw-quick-result-head strong { display: block; font-size: var(--d-fs-base); color: #065f46; }
.cw-quick-result-head small { display: block; font-size: var(--d-fs-sm); color: #047857; margin-top: 2px; }
.cw-quick-preview {
    background: #fff; border: 1px solid #bbf7d0; border-radius: 10px;
    padding: 10px 12px; margin-bottom: 0.7rem;
    max-height: 280px; overflow-y: auto;
}
.cw-quick-row {
    display: grid; grid-template-columns: 130px 1fr; gap: 8px;
    padding: 5px 0; border-bottom: 1px solid #f1f5f9; font-size: var(--d-fs-sm);
}
.cw-quick-row:last-child { border-bottom: none; }
.cw-quick-key { color: #64748b; font-weight: 500; }
.cw-quick-val { color: #1e293b; word-wrap: break-word; }
.cw-quick-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.cw-quick-actions .thx-btn { display: inline-flex; align-items: center; gap: 4px; }

.cw-actions {
    margin-top: 2rem;
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    border-top: 1px solid #e2e8f0;
    padding-top: 1.25rem;
}
.cw-actions-right { display: flex; gap: 0.5rem; }

.cw-source-tabs {
    display: flex;
    gap: 0.25rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
}
.cw-source-tab {
    padding: 0.6rem 1rem;
    background: none;
    border: none;
    cursor: pointer;
    font-size: var(--d-fs-sm);
    color: #64748b;
    border-bottom: 2px solid transparent;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.cw-source-tab.active {
    color: var(--thoxan-700);
    border-bottom-color: var(--thoxan-700);
    font-weight: 600;
}

.cw-suggestion-row {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.75rem;
    background: #fafbff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}
.cw-suggestion-row > div { flex: 1; min-width: 0; }
.cw-suggestion-label {
    font-size: var(--d-fs-xs);
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 0.2rem;
}
.cw-suggestion-value {
    font-size: var(--d-fs-sm);
    white-space: pre-wrap;
    word-break: break-word;
}
.cw-suggestion-row button {
    flex-shrink: 0;
}

.cw-asana-project {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.7rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 0.4rem;
    cursor: pointer;
    transition: all 0.15s;
}
.cw-asana-project:hover { border-color: var(--thoxan-700); background: #fafbff; }
.cw-asana-project.selected {
    border-color: var(--thoxan-700);
    background: #e6f0fa;
}
.cw-asana-project input { margin-top: 3px; }
.cw-asana-project-info { flex: 1; min-width: 0; }
.cw-asana-project-name { font-weight: 600; font-size: var(--d-fs-sm); }
.cw-asana-project-meta { font-size: var(--d-fs-xs); color: #94a3b8; margin-top: 2px; }

.cw-summary-section {
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}
.cw-summary-section:last-child { border-bottom: none; padding-bottom: 0; }
.cw-summary-row {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 1rem;
    padding: 0.4rem 0;
    font-size: var(--d-fs-sm);
}
.cw-summary-label { color: #64748b; font-weight: 500; }
.cw-summary-value { white-space: pre-wrap; word-break: break-word; }

.cw-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(0, 76, 155,0.3);
    border-top-color: var(--thoxan-700);
    border-radius: 50%;
    animation: cw-spin 1s linear infinite;
    margin-right: 0.4rem;
    vertical-align: middle;
}
@keyframes cw-spin { to { transform: rotate(360deg); } }
.cw-empty { text-align: center; color: #94a3b8; padding: 2rem; }
</style>

<div class="cw-wrap">
    <div class="thx-page-header">
        <div>
            <h1 class="thx-page-title">Neuer Kunde</h1>
            <div class="thx-page-subtitle">KI-gestützter Schnellstart fuer neue Kunden</div>
        </div>
        <div class="thx-page-actions">
            <a href="/admin/customers" class="thx-btn thx-btn-secondary thx-btn-small">Abbrechen</a>
        </div>
    </div>

    <div class="cw-grid">
        <div class="cw-stepper" id="cw-stepper"></div>
        <div class="cw-content" id="cw-content">
            <div class="cw-empty"><span class="cw-spinner"></span>Wizard wird gestartet...</div>
        </div>
    </div>
</div>

<script>
(function() {
    const csrf = '<?= htmlspecialchars($csrfToken) ?>';

    const STEPS = [
        { key: 'basics', label: 'Basis', icon: 'badge' },
        { key: 'ki_marken', label: 'KI-Schnellstart', icon: 'auto_awesome' },
        { key: 'asana_done', label: 'Asana + Anlegen', icon: 'task_alt' },
    ];

    let state = null;
    let currentStep = 'basics';

    async function api(method, path, body = null) {
        const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf } };
        if (body) opts.body = JSON.stringify(body);
        const r = await fetch('/api/v1' + path, opts);
        return r.json();
    }

    async function start() {
        const r = await api('POST', '/admin/customer-wizard/start');
        if (!r.success) { document.getElementById('cw-content').innerHTML = '<div class="cw-empty">Fehler: ' + (r.message || 'unbekannt') + '</div>'; return; }
        state = r.data;
        renderStepper();
        renderStep('basics');
    }

    function renderStepper() {
        const el = document.getElementById('cw-stepper');
        const completed = state?.completed_steps || [];
        const html = STEPS.map((s, i) => {
            let cls = 'cw-step';
            if (s.key === currentStep) cls += ' active';
            else if (completed.includes(s.key)) cls += ' done';
            return `<div class="${cls}" onclick="cwGoto('${s.key}')">
                <div class="cw-step-num"><span>${i+1}</span></div>
                <div>${s.label}</div>
            </div>`;
        }).join('');
        el.innerHTML = html;
    }

    window.cwGoto = function(stepKey) {
        const completed = state?.completed_steps || [];
        if (stepKey !== currentStep && !completed.includes(stepKey) && stepKey !== nextStep(completed)) return;
        renderStep(stepKey);
    };

    function nextStep(completed) {
        for (const s of STEPS) {
            if (!completed.includes(s.key)) return s.key;
        }
        return STEPS[STEPS.length - 1].key;
    }

    function renderStep(stepKey) {
        currentStep = stepKey;
        renderStepper();
        const c = document.getElementById('cw-content');
        const fn = stepRenderers[stepKey];
        if (fn) fn(c);
    }

    const stepRenderers = {
        basics(c) {
            const d = state.data;
            const hasFilled = !!(d.name || d.slug);
            c.innerHTML = `
                <h2 class="cw-step-title">Neuer Kunde — KI-Schnellstart</h2>
                <p class="cw-step-subtitle">Gib einfach die Website-URL ein. Die KI füllt Firmenname, Kürzel, Branche, Beschreibung, Tonalität und mehr automatisch aus. Felder kannst du danach noch anpassen.</p>

                <div class="cw-url-hero">
                    <span class="material-symbols-rounded cw-url-icon">link</span>
                    <input type="url" id="f-website" value="${esc(d.website)}" placeholder="https://firma.de"
                           autofocus
                           onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('cw-quick-analyze').click();}">
                    <button class="thx-btn thx-btn-primary" id="cw-quick-analyze" onclick="cwQuickAnalyze()">
                        <span class="material-symbols-rounded">auto_awesome</span>
                        Analysieren
                    </button>
                </div>
                <div id="cw-quick-status" style="margin-top:0.5rem;font-size: var(--d-fs-sm);"></div>

                <div class="cw-divider"><span>oder manuell</span></div>

                <div class="cw-form-group">
                    <label>Firmenname *</label>
                    <input type="text" id="f-name" value="${esc(d.name)}" placeholder="z.B. Wittekind GmbH">
                </div>
                <div class="cw-form-row">
                    <div class="cw-form-group">
                        <label>Kürzel</label>
                        <input type="text" id="f-abbr" value="${esc(d.abbreviation)}" placeholder="WTK · WTÜK · B&Q" maxlength="10">
                    </div>
                    <div class="cw-form-group">
                        <label>Slug</label>
                        <input type="text" id="f-slug" value="${esc(d.slug)}" placeholder="wittekind">
                        <small>Automatisch aus Name. Wird intern für Dateipfade gebraucht und ist nach Anlage nicht mehr änderbar.</small>
                    </div>
                </div>
                <div class="cw-form-group">
                    <label>Branche</label>
                    <input type="text" id="f-industry" value="${esc(d.industry)}" placeholder="z.B. Maschinenbau">
                </div>
                ${actionsHtml(false, true)}
            `;
            // Auto-Slug aus Name, solange der User ihn nicht selber editiert hat
            document.getElementById('f-name').addEventListener('input', function() {
                const slugEl = document.getElementById('f-slug');
                if (!slugEl.dataset.userEdited) {
                    slugEl.value = this.value.toLowerCase()
                        .replace(/[äöüß]/g, m => ({ä:'ae',ö:'oe',ü:'ue',ß:'ss'}[m]))
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-|-$/g, '');
                }
            });
            document.getElementById('f-slug').addEventListener('input', function() { this.dataset.userEdited = '1'; });
            attachNextHandler(async () => {
                const data = {
                    name: document.getElementById('f-name').value.trim(),
                    slug: document.getElementById('f-slug').value.trim(),
                    abbreviation: document.getElementById('f-abbr').value.trim(),
                    industry: document.getElementById('f-industry').value.trim(),
                    website: document.getElementById('f-website').value.trim(),
                    // KI-extrahierte Profil-Felder mitsenden, damit sie im Backend-State persistiert
                    // werden und die Folgesteps (Profil prüfen / Tonalität) sie vorausgefüllt zeigen.
                    description: state.data.description || '',
                    target_audience: state.data.target_audience || '',
                    products_services: state.data.products_services || '',
                    unique_selling_points: state.data.unique_selling_points || '',
                    tone_of_voice: state.data.tone_of_voice || '',
                    brand_values: state.data.brand_values || '',
                };
                if (!data.name) { App.showNotification('Firmenname erforderlich', 'error'); return false; }
                if (!data.slug || !/^[a-z0-9-]+$/.test(data.slug)) {
                    // Defensive: aus Name regenerieren falls Slug leer oder unsauber
                    data.slug = data.name.toLowerCase()
                        .replace(/[äöüß]/g, m => ({ä:'ae',ö:'oe',ü:'ue',ß:'ss'}[m]))
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-|-$/g, '');
                }
                if (!data.slug) { App.showNotification('Konnte keinen Slug aus dem Namen ableiten', 'error'); return false; }
                return saveStepAndAdvance('basics', data, 'ki_marken');
            });
        },

        // ===== Neuer Schritt 2: KI-Schnellstart (Source + Review + Tonalität in einem) =====
        ki_marken(c) {
            const d = state.data;
            c.innerHTML = `
                <h2 class="cw-step-title">KI-Schnellstart</h2>
                <p class="cw-step-subtitle">Quelle wählen → KI füllt Beschreibung, Zielgruppe, USPs, Tonalität und Markenwerte. Du kannst jedes Feld nachjustieren.</p>

                <div class="cw-source-tabs">
                    <button class="cw-source-tab active" data-src="url" onclick="cwSrcTab('url')"><span class="material-symbols-rounded" style="font-size:16px;">link</span> URL</button>
                    <button class="cw-source-tab" data-src="file" onclick="cwSrcTab('file')"><span class="material-symbols-rounded" style="font-size:16px;">upload_file</span> Datei</button>
                    <button class="cw-source-tab" data-src="text" onclick="cwSrcTab('text')"><span class="material-symbols-rounded" style="font-size:16px;">edit_note</span> Text</button>
                </div>
                <div id="src-url">
                    <div class="cw-form-group">
                        <label>Website-URL</label>
                        <input type="url" id="src-url-input" value="${esc(d.website || '')}" placeholder="https://firma.de">
                    </div>
                </div>
                <div id="src-file" style="display:none;">
                    <div class="cw-form-group">
                        <label>Dokument hochladen</label>
                        <input type="file" id="src-file-input" accept=".pdf,.docx,.txt,.md,.html,.htm">
                    </div>
                </div>
                <div id="src-text" style="display:none;">
                    <div class="cw-form-group">
                        <label>Text</label>
                        <textarea id="src-text-input" rows="5" placeholder="Firmenbeschreibung, Kick-Off-Notes, Angebotstext..."></textarea>
                    </div>
                </div>
                <div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:1.2rem;">
                    <button class="thx-btn thx-btn-primary" id="cw-analyze-btn" onclick="cwAnalyze()">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">auto_awesome</span>
                        KI-Analyse starten
                    </button>
                    <span style="font-size:var(--d-fs-xs);color:var(--slate-500);">oder direkt unten ausfüllen — alles optional</span>
                </div>
                <div id="cw-analysis-status" style="margin-bottom:1rem;"></div>

                <h3 style="margin:1.5rem 0 0.8rem;font-size:var(--d-fs-base);color:var(--slate-800);">Marken-Felder</h3>
                <div class="cw-form-row">
                    <div class="cw-form-group">
                        <label>Beschreibung</label>
                        <textarea id="f-description" rows="3">${esc(d.description)}</textarea>
                    </div>
                    <div class="cw-form-group">
                        <label>Zielgruppe</label>
                        <textarea id="f-target-audience" rows="3">${esc(d.target_audience)}</textarea>
                    </div>
                </div>
                <div class="cw-form-row">
                    <div class="cw-form-group">
                        <label>Produkte/Services</label>
                        <textarea id="f-products-services" rows="3">${esc(d.products_services)}</textarea>
                    </div>
                    <div class="cw-form-group">
                        <label>USPs</label>
                        <textarea id="f-unique-selling-points" rows="3">${esc(d.unique_selling_points)}</textarea>
                    </div>
                </div>
                <div class="cw-form-row">
                    <div class="cw-form-group">
                        <label>Tonalität</label>
                        <textarea id="f-tone-of-voice" rows="2" placeholder="z.B. seriös, professionell, sachlich">${esc(d.tone_of_voice)}</textarea>
                    </div>
                    <div class="cw-form-group">
                        <label>Markenwerte</label>
                        <textarea id="f-brand-values" rows="2" placeholder="z.B. Vertrauen, Transparenz, Innovation">${esc(d.brand_values)}</textarea>
                    </div>
                </div>

                ${actionsHtml(true, true)}
            `;
            attachNextHandler(async () => saveStepAndAdvance('ki_marken', {
                description: document.getElementById('f-description').value.trim(),
                target_audience: document.getElementById('f-target-audience').value.trim(),
                products_services: document.getElementById('f-products-services').value.trim(),
                unique_selling_points: document.getElementById('f-unique-selling-points').value.trim(),
                tone_of_voice: document.getElementById('f-tone-of-voice').value.trim(),
                brand_values: document.getElementById('f-brand-values').value.trim(),
            }, 'asana_done'));
        },

        profile_source(c) {
            const d = state.data;
            c.innerHTML = `
                <h2 class="cw-step-title">Profil automatisch erstellen</h2>
                <p class="cw-step-subtitle">Die KI füllt Beschreibung, Zielgruppe, USPs und Co aus Quellen. Du kannst auch überspringen und alles selbst eingeben.</p>
                <div class="cw-source-tabs">
                    <button class="cw-source-tab active" data-src="url" onclick="cwSrcTab('url')"><span class="material-symbols-rounded" style="font-size:16px;">link</span> URL</button>
                    <button class="cw-source-tab" data-src="file" onclick="cwSrcTab('file')"><span class="material-symbols-rounded" style="font-size:16px;">upload_file</span> Datei</button>
                    <button class="cw-source-tab" data-src="text" onclick="cwSrcTab('text')"><span class="material-symbols-rounded" style="font-size:16px;">edit_note</span> Text</button>
                </div>
                <div id="src-url">
                    <div class="cw-form-group">
                        <label>Website-URL</label>
                        <input type="url" id="src-url-input" value="${esc(d.website || '')}" placeholder="https://firma.de">
                    </div>
                </div>
                <div id="src-file" style="display:none;">
                    <div class="cw-form-group">
                        <label>Dokument hochladen</label>
                        <input type="file" id="src-file-input" accept=".pdf,.docx,.txt,.md,.html,.htm">
                    </div>
                </div>
                <div id="src-text" style="display:none;">
                    <div class="cw-form-group">
                        <label>Text</label>
                        <textarea id="src-text-input" rows="6" placeholder="Firmenbeschreibung, Kick-Off-Notes, Angebotstext..."></textarea>
                    </div>
                </div>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <button class="thx-btn thx-btn-primary" id="cw-analyze-btn" onclick="cwAnalyze()">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">auto_awesome</span>
                        Analyse starten
                    </button>
                    <button class="thx-btn thx-btn-secondary" onclick="cwSkipAnalysis()">Überspringen</button>
                </div>
                <div id="cw-analysis-status" style="margin-top:1rem;"></div>
                ${actionsHtml(true, true)}
            `;
            attachNextHandler(async () => {
                // KI-Analyse-Ergebnisse (falls vorhanden) zum Server schicken
                const data = {
                    description: state.data.description || '',
                    target_audience: state.data.target_audience || '',
                    products_services: state.data.products_services || '',
                    unique_selling_points: state.data.unique_selling_points || '',
                    industry: state.data.industry || '',
                    tone_of_voice: state.data.tone_of_voice || '',
                    brand_values: state.data.brand_values || '',
                };
                return saveStepAndAdvance('profile_source', data, 'profile_review');
            });
            attachBackHandler('basics');
        },

        profile_review(c) {
            const d = state.data;
            c.innerHTML = `
                <h2 class="cw-step-title">Profil prüfen &amp; anpassen</h2>
                <p class="cw-step-subtitle">Die wichtigsten Felder für einen guten KI-Output. Änderbar.</p>
                <div class="cw-form-group">
                    <label>Beschreibung</label>
                    <textarea id="f-description">${esc(d.description)}</textarea>
                    <small>Was macht das Unternehmen? Fuer wen?</small>
                </div>
                <div class="cw-form-group">
                    <label>Zielgruppe</label>
                    <textarea id="f-audience">${esc(d.target_audience)}</textarea>
                </div>
                <div class="cw-form-group">
                    <label>Produkte / Dienstleistungen</label>
                    <textarea id="f-products">${esc(d.products_services)}</textarea>
                </div>
                <div class="cw-form-group">
                    <label>Alleinstellungsmerkmale (USPs)</label>
                    <textarea id="f-usps">${esc(d.unique_selling_points)}</textarea>
                </div>
                ${actionsHtml(true, true)}
            `;
            attachNextHandler(async () => saveStepAndAdvance('profile_review', {
                description: document.getElementById('f-description').value.trim(),
                target_audience: document.getElementById('f-audience').value.trim(),
                products_services: document.getElementById('f-products').value.trim(),
                unique_selling_points: document.getElementById('f-usps').value.trim(),
            }, 'tone_values'));
            attachBackHandler('basics');
        },

        tone_values(c) {
            const d = state.data;
            const tones = [
                { val: 'formal', label: 'Formal / Sie-Form' },
                { val: 'informal', label: 'Informal / Du-Form' },
                { val: 'professional', label: 'Professionell-sachlich' },
                { val: 'friendly', label: 'Freundlich-nahbar' },
                { val: 'technical', label: 'Technisch-fachlich' },
                { val: 'casual', label: 'Locker-umgangssprachlich' },
            ];
            c.innerHTML = `
                <h2 class="cw-step-title">Tonalität &amp; Markenwerte</h2>
                <p class="cw-step-subtitle">Wie soll die KI für diesen Kunden klingen?</p>
                <div class="cw-form-group">
                    <label>Tonalität</label>
                    <select id="f-tone">
                        <option value="">-- bitte waehlen --</option>
                        ${tones.map(t => `<option value="${t.val}" ${d.tone_of_voice === t.val ? 'selected' : ''}>${t.label}</option>`).join('')}
                    </select>
                </div>
                <div class="cw-form-group">
                    <label>Markenwerte</label>
                    <input type="text" id="f-values" value="${esc(d.brand_values)}" placeholder="z.B. Innovation, Nachhaltigkeit, Qualitaet">
                    <small>Komma-separiert. Auch hier hilft die KI:</small>
                </div>
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="cwSuggestTone()">
                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">auto_awesome</span>
                    KI-Vorschlag basierend auf bisherigen Daten
                </button>
                <div id="cw-tone-suggestion" style="margin-top:1rem;"></div>
                ${actionsHtml(true, true)}
            `;
            attachNextHandler(async () => saveStepAndAdvance('tone_values', {
                tone_of_voice: document.getElementById('f-tone').value,
                brand_values: document.getElementById('f-values').value.trim(),
            }, 'asana_projects'));
            attachBackHandler('profile_review');
        },

        async asana_projects(c) {
            const d = state.data;
            c.innerHTML = `
                <h2 class="cw-step-title">Asana-Projekte verbinden</h2>
                <p class="cw-step-subtitle">Wähle die Projekte deren Tasks ins Wissen synchronisiert werden sollen. Vorschläge zu „${esc(d.name || 'diesem Kunden')}" erscheinen oben mit <span style="background:var(--thoxan-700);color:#fff;font-size: var(--d-fs-xs);padding:1px 7px;border-radius:10px;">Match</span>-Badge.</p>
                <div id="asana-state" style="margin-bottom:0.6rem;"><span class="cw-spinner"></span>Prüfe Asana-Verbindung…</div>
                <div style="display:flex;gap:0.4rem;margin-bottom:0.5rem;flex-wrap:wrap;align-items:center;">
                    <input type="text" id="cw-asana-search" placeholder="Projekt suchen…" oninput="cwAsanaFilter()"
                           style="flex:1;min-width:200px;padding:0.55rem 0.75rem;border:1px solid #e2e8f0;border-radius:8px;font-size: var(--d-fs-sm);font-family:inherit;">
                    <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="cwAsanaToggleSelected()" id="cw-asana-only-selected">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">checklist</span>
                        Nur ausgewählte
                    </button>
                </div>
                <div id="asana-projects-list" style="max-height:400px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;"></div>
                <div class="cw-form-group" style="margin-top:1rem;display:flex;gap:1.5rem;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:0.5rem;font-weight:normal;">
                        <input type="checkbox" id="f-sync-enabled" ${d.asana_sync_enabled ? 'checked' : ''}>
                        Automatischen Sync aktivieren
                    </label>
                    <label style="display:flex;align-items:center;gap:0.5rem;font-weight:normal;">
                        Intervall:
                        <select id="f-sync-interval" style="width:auto;display:inline-block;">
                            <option value="1" ${d.asana_sync_interval_hours == 1 ? 'selected' : ''}>1 Stunde</option>
                            <option value="4" ${d.asana_sync_interval_hours == 4 ? 'selected' : ''}>4 Stunden</option>
                            <option value="12" ${d.asana_sync_interval_hours == 12 ? 'selected' : ''}>12 Stunden</option>
                            <option value="24" ${d.asana_sync_interval_hours == 24 ? 'selected' : ''}>Täglich</option>
                            <option value="72" ${(d.asana_sync_interval_hours == 72 || !d.asana_sync_interval_hours) ? 'selected' : ''}>Alle 3 Tage</option>
                            <option value="168" ${d.asana_sync_interval_hours == 168 ? 'selected' : ''}>Wöchentlich</option>
                        </select>
                    </label>
                </div>
                ${actionsHtml(true, true)}
            `;
            attachBackHandler('tone_values');
            attachNextHandler(async () => {
                const gids = Array.from(cwAsanaSelected);
                return saveStepAndAdvance('asana_projects', {
                    asana_project_gids: gids,
                    asana_sync_enabled: document.getElementById('f-sync-enabled').checked,
                    asana_sync_interval_hours: parseInt(document.getElementById('f-sync-interval').value) || 4,
                }, 'summary');
            });

            // Asana-Projekte laden + Match-Score gegen Kunden berechnen
            const asanaState = document.getElementById('asana-state');
            cwAsanaSelected = new Set((d.asana_project_gids || []).map(String));
            cwAsanaShowSelected = false;
            try {
                const r = await api('GET', '/admin/asana-projects');
                if (!r.success) {
                    asanaState.innerHTML = '<span style="color:var(--rose-600);">Asana nicht konfiguriert: ' + (r.message || '') + '</span> &mdash; ' +
                        '<a href="/admin/settings" target="_blank">in Einstellungen einrichten</a>';
                    return;
                }
                cwAsanaProjects = r.data?.projects || [];
                if (cwAsanaProjects.length === 0) {
                    asanaState.innerHTML = '<span style="color:#94a3b8;">Keine Projekte gefunden.</span>';
                    return;
                }

                // Match-Score: Vergleich Project-Name gegen Customer-Name/Slug/Kürzel
                const needles = [d.name, d.slug, d.abbreviation]
                    .filter(Boolean)
                    .map(s => String(s).toLowerCase().trim())
                    .filter(s => s.length >= 2);
                cwAsanaProjects.forEach(p => {
                    const pname = (p.name || '').toLowerCase();
                    let score = 0;
                    for (const n of needles) {
                        if (!n) continue;
                        if (pname === n) score = Math.max(score, 100);
                        else if (pname.includes(n)) score = Math.max(score, 50);
                        else {
                            const re = new RegExp('\\b' + n.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&') + '\\b', 'i');
                            if (re.test(p.name || '')) score = Math.max(score, 70);
                        }
                    }
                    p._matchScore = score;
                });
                cwAsanaProjects.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'de', { sensitivity: 'base' }));

                const matches = cwAsanaProjects.filter(p => p._matchScore > 0).length;
                const selCount = cwAsanaProjects.filter(p => cwAsanaSelected.has(String(p.gid))).length;
                asanaState.innerHTML = `<strong>${cwAsanaProjects.length}</strong> Projekte verfügbar` +
                    (matches > 0 ? `, <strong style="color:var(--thoxan-700);">${matches}</strong> Vorschläge zu „${esc(d.name || '')}"` : '') +
                    (selCount > 0 ? `, <strong style="color:var(--emerald-600);">${selCount}</strong> ausgewählt` : '');

                cwAsanaRender();
            } catch (e) {
                asanaState.innerHTML = '<span style="color:var(--rose-600);">Verbindung fehlgeschlagen: ' + esc(e.message || '') + '</span>';
            }
        },

        // ===== Neuer Schritt 3: Asana + Summary + Commit in einem =====
        async asana_done(c) {
            const d = state.data;
            const filled = ['description','target_audience','products_services','unique_selling_points','tone_of_voice','brand_values']
                .filter(k => (d[k] || '').trim().length > 0).length;
            c.innerHTML = `
                <h2 class="cw-step-title">Letzter Schritt</h2>
                <p class="cw-step-subtitle">Asana-Projekte verbinden (optional) — danach legen wir den Kunden an.</p>

                <div class="cw-summary-section" style="margin-bottom:1rem;">
                    <div class="cw-summary-row"><div class="cw-summary-label">Firmenname</div><div class="cw-summary-value">${esc(d.name)}</div></div>
                    <div class="cw-summary-row"><div class="cw-summary-label">Kürzel · Branche</div><div class="cw-summary-value">${esc(d.abbreviation || '-')} · ${esc(d.industry || '-')}</div></div>
                    <div class="cw-summary-row"><div class="cw-summary-label">Marken-Felder</div><div class="cw-summary-value">${filled} von 6 ausgefüllt</div></div>
                </div>

                <h3 style="margin:1.5rem 0 0.5rem;font-size:var(--d-fs-base);color:var(--slate-800);">Asana-Projekte (optional)</h3>
                <div id="asana-state" style="margin-bottom:0.6rem;"><span class="cw-spinner"></span>Prüfe Asana-Verbindung…</div>
                <div style="display:flex;gap:0.4rem;margin-bottom:0.5rem;flex-wrap:wrap;align-items:center;">
                    <input type="text" id="cw-asana-search" placeholder="Projekt suchen…" oninput="cwAsanaFilter()"
                           style="flex:1;min-width:200px;padding:0.55rem 0.75rem;border:1px solid #e2e8f0;border-radius:8px;font-size: var(--d-fs-sm);font-family:inherit;">
                    <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="cwAsanaToggleSelected()" id="cw-asana-only-selected">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">checklist</span>
                        Nur ausgewählte
                    </button>
                </div>
                <div id="asana-projects-list" style="max-height:300px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;"></div>
                <div class="cw-form-group" style="margin-top:1rem;display:flex;gap:1.5rem;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:0.5rem;font-weight:normal;">
                        <input type="checkbox" id="f-sync-enabled" ${d.asana_sync_enabled ? 'checked' : ''}>
                        Automatischen Sync aktivieren
                    </label>
                    <label style="display:flex;align-items:center;gap:0.5rem;font-weight:normal;">
                        Intervall:
                        <select id="f-sync-interval" style="width:auto;display:inline-block;">
                            <option value="1" ${d.asana_sync_interval_hours == 1 ? 'selected' : ''}>1 Stunde</option>
                            <option value="4" ${d.asana_sync_interval_hours == 4 ? 'selected' : ''}>4 Stunden</option>
                            <option value="12" ${d.asana_sync_interval_hours == 12 ? 'selected' : ''}>12 Stunden</option>
                            <option value="24" ${d.asana_sync_interval_hours == 24 ? 'selected' : ''}>Täglich</option>
                            <option value="72" ${(d.asana_sync_interval_hours == 72 || !d.asana_sync_interval_hours) ? 'selected' : ''}>Alle 3 Tage</option>
                            <option value="168" ${d.asana_sync_interval_hours == 168 ? 'selected' : ''}>Wöchentlich</option>
                        </select>
                    </label>
                </div>

                <div class="cw-actions" style="margin-top:1.5rem;">
                    <button class="thx-btn thx-btn-secondary" id="cw-back">← Zurück</button>
                    <button class="thx-btn thx-btn-primary" id="cw-commit" onclick="cwCommit()">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">check</span>
                        Kunde anlegen
                    </button>
                </div>
            `;
            attachBackHandler('ki_marken');

            // Asana-Projekte laden (analog zum alten asana_projects-Step)
            const asanaState = document.getElementById('asana-state');
            cwAsanaSelected = new Set((d.asana_project_gids || []).map(String));
            cwAsanaShowSelected = false;
            try {
                const r = await api('GET', '/admin/asana-projects');
                if (!r.success) {
                    asanaState.innerHTML = '<span style="color:var(--rose-600);">Asana nicht konfiguriert: ' + (r.message || '') + '</span> &mdash; ' +
                        '<a href="/admin/settings" target="_blank">in Einstellungen einrichten</a>';
                    return;
                }
                cwAsanaProjects = r.data?.projects || [];
                if (cwAsanaProjects.length === 0) {
                    asanaState.innerHTML = '<span style="color:#94a3b8;">Keine Projekte gefunden.</span>';
                    return;
                }
                const needles = [d.name, d.slug, d.abbreviation]
                    .filter(Boolean).map(s => String(s).toLowerCase().trim()).filter(s => s.length >= 2);
                cwAsanaProjects.forEach(p => {
                    const pname = (p.name || '').toLowerCase();
                    let score = 0;
                    for (const n of needles) {
                        if (!n) continue;
                        if (pname === n) score = Math.max(score, 100);
                        else if (pname.includes(n)) score = Math.max(score, 50);
                        else {
                            const re = new RegExp('\\b' + n.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&') + '\\b', 'i');
                            if (re.test(p.name || '')) score = Math.max(score, 70);
                        }
                    }
                    p._matchScore = score;
                });
                cwAsanaProjects.sort((a, b) => (b._matchScore - a._matchScore) || (a.name || '').localeCompare(b.name || ''));
                asanaState.innerHTML = '<span style="color:var(--slate-500);font-size:var(--d-fs-xs);">' + cwAsanaProjects.length + ' Projekte gefunden — die mit „Match"-Badge passen vermutlich zu „' + esc(d.name || '') + '".</span>';
                cwAsanaRender();
            } catch (e) {
                asanaState.innerHTML = '<span style="color:var(--rose-600);">' + esc(e.message || 'Fehler') + '</span>';
            }
        },

        summary(c) {
            const d = state.data;
            c.innerHTML = `
                <h2 class="cw-step-title">Steckbrief</h2>
                <p class="cw-step-subtitle">Alles bereit. Bei Anlage wird sofort der erste Asana-Sync gestartet (falls Projekte gewaehlt).</p>
                <div class="cw-summary-section">
                    <div class="cw-summary-row"><div class="cw-summary-label">Firmenname</div><div class="cw-summary-value">${esc(d.name)}</div></div>
                    <div class="cw-summary-row"><div class="cw-summary-label">Kürzel</div><div class="cw-summary-value">${esc(d.abbreviation || '-')}</div></div>
                    <div class="cw-summary-row"><div class="cw-summary-label">Branche · Website</div><div class="cw-summary-value">${esc(d.industry || '-')} · ${esc(d.website || '-')}</div></div>
                </div>
                <div class="cw-summary-section">
                    <div class="cw-summary-row"><div class="cw-summary-label">Beschreibung</div><div class="cw-summary-value">${esc(d.description || '-')}</div></div>
                    <div class="cw-summary-row"><div class="cw-summary-label">Zielgruppe</div><div class="cw-summary-value">${esc(d.target_audience || '-')}</div></div>
                    <div class="cw-summary-row"><div class="cw-summary-label">Produkte/Services</div><div class="cw-summary-value">${esc(d.products_services || '-')}</div></div>
                    <div class="cw-summary-row"><div class="cw-summary-label">USPs</div><div class="cw-summary-value">${esc(d.unique_selling_points || '-')}</div></div>
                </div>
                <div class="cw-summary-section">
                    <div class="cw-summary-row"><div class="cw-summary-label">Tonalität</div><div class="cw-summary-value">${esc(d.tone_of_voice || '-')}</div></div>
                    <div class="cw-summary-row"><div class="cw-summary-label">Markenwerte</div><div class="cw-summary-value">${esc(d.brand_values || '-')}</div></div>
                </div>
                <div class="cw-summary-section">
                    <div class="cw-summary-row"><div class="cw-summary-label">Asana-Projekte</div><div class="cw-summary-value">${(d.asana_project_gids || []).length} ausgewaehlt</div></div>
                    <div class="cw-summary-row"><div class="cw-summary-label">Auto-Sync</div><div class="cw-summary-value">${d.asana_sync_enabled ? 'Ja, alle ' + d.asana_sync_interval_hours + 'h' : 'Aus'}</div></div>
                </div>
                <div class="cw-actions">
                    <button class="thx-btn thx-btn-secondary" onclick="cwGoto('asana_projects')">← Zurück</button>
                    <button class="thx-btn thx-btn-primary" id="cw-commit" onclick="cwCommit()">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">check</span>
                        Kunde anlegen
                    </button>
                </div>
            `;
        },
    };

    function actionsHtml(showBack, showNext) {
        return `<div class="cw-actions">
            ${showBack ? '<button class="thx-btn thx-btn-secondary" id="cw-back">← Zurück</button>' : '<div></div>'}
            <div class="cw-actions-right">
                ${showNext ? '<button class="thx-btn thx-btn-primary" id="cw-next">Weiter →</button>' : ''}
            </div>
        </div>`;
    }

    function attachNextHandler(fn) {
        const btn = document.getElementById('cw-next');
        if (!btn) return;
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            const ok = await fn();
            if (!ok) btn.disabled = false;
        });
    }

    function attachBackHandler(prevStep) {
        const btn = document.getElementById('cw-back');
        if (!btn) return;
        btn.addEventListener('click', () => renderStep(prevStep));
    }

    async function saveStepAndAdvance(stepKey, data, nextKey) {
        const r = await api('POST', `/admin/customer-wizard/${state.wizard_id}/step/${stepKey}`, { data });
        if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); return false; }
        state = r.data;
        renderStepper();
        if (nextKey) renderStep(nextKey);
        return true;
    }

    window.cwSrcTab = function(src) {
        document.querySelectorAll('.cw-source-tab').forEach(b => b.classList.toggle('active', b.dataset.src === src));
        ['url', 'file', 'text'].forEach(k => {
            document.getElementById('src-' + k).style.display = k === src ? '' : 'none';
        });
    };

    // Asana-Step Globals
    let cwAsanaProjects = [];
    let cwAsanaSelected = new Set();
    let cwAsanaShowSelected = false;

    window.cwAsanaFilter = function() { cwAsanaRender(); };
    window.cwAsanaToggleSelected = function() {
        cwAsanaShowSelected = !cwAsanaShowSelected;
        const btn = document.getElementById('cw-asana-only-selected');
        if (cwAsanaShowSelected) { btn.classList.remove('thx-btn-secondary'); btn.classList.add('thx-btn-primary'); }
        else { btn.classList.add('thx-btn-secondary'); btn.classList.remove('thx-btn-primary'); }
        cwAsanaRender();
    };
    window.cwAsanaToggle = function(cb, gid) {
        const id = String(gid);
        if (cb.checked) cwAsanaSelected.add(id); else cwAsanaSelected.delete(id);
        cb.closest('.cw-asana-project').classList.toggle('selected', cb.checked);
        // Counter aktualisieren
        const stateEl = document.getElementById('asana-state');
        if (stateEl && cwAsanaProjects.length) {
            const matches = cwAsanaProjects.filter(p => p._matchScore > 0).length;
            const selCount = cwAsanaProjects.filter(p => cwAsanaSelected.has(String(p.gid))).length;
            const cName = state.data.name || '';
            stateEl.innerHTML = `<strong>${cwAsanaProjects.length}</strong> Projekte verfügbar` +
                (matches > 0 ? `, <strong style="color:var(--thoxan-700);">${matches}</strong> Vorschläge zu „${esc(cName)}"` : '') +
                (selCount > 0 ? `, <strong style="color:var(--emerald-600);">${selCount}</strong> ausgewählt` : '');
        }
    };

    function cwAsanaRender() {
        const list = document.getElementById('asana-projects-list');
        if (!list) return;
        const search = (document.getElementById('cw-asana-search')?.value || '').toLowerCase().trim();
        let filtered = cwAsanaProjects.filter(p => {
            if (cwAsanaShowSelected && !cwAsanaSelected.has(String(p.gid))) return false;
            if (search && !(p.name || '').toLowerCase().includes(search)) return false;
            return true;
        });
        if (filtered.length === 0) {
            list.innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8;font-size: var(--d-fs-sm);">Keine Treffer</div>';
            return;
        }
        const suggested = filtered.filter(p => (p._matchScore || 0) > 0)
            .sort((a, b) => (b._matchScore - a._matchScore) || (a.name || '').localeCompare(b.name || '', 'de'));
        const others = filtered.filter(p => !(p._matchScore > 0));
        const cName = state.data.name || '';

        let html = '';
        if (suggested.length > 0) {
            html += `<div style="font-size: var(--d-fs-xs);font-weight:700;color:var(--thoxan-700);text-transform:uppercase;letter-spacing:0.5px;padding:0.6rem 0.75rem 0.3rem;background:var(--thoxan-100);">
                Vorschläge zu „${esc(cName)}"
            </div>`;
            html += suggested.map(p => cwAsanaRowHtml(p, true)).join('');
        }
        if (others.length > 0) {
            if (suggested.length > 0) {
                html += `<div style="font-size: var(--d-fs-xs);font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;padding:0.6rem 0.75rem 0.3rem;background:#f8fafc;">
                    Alle weiteren (${others.length})
                </div>`;
            }
            html += others.map(p => cwAsanaRowHtml(p, false)).join('');
        }
        list.innerHTML = html;
    }

    function cwAsanaRowHtml(p, isMatch) {
        const sel = cwAsanaSelected.has(String(p.gid));
        const meta = [
            p.team?.name ? esc(p.team.name) : '',
            p.archived ? '<span style="color:var(--rose-600);">archiviert</span>' : '',
            p.modified_at ? 'Geändert: ' + p.modified_at.substring(0, 10) : '',
        ].filter(Boolean).join(' · ');
        return `
            <label class="cw-asana-project ${sel ? 'selected' : ''}" style="display:flex;align-items:flex-start;gap:0.5rem;padding:0.55rem 0.75rem;border-bottom:1px solid #f1f5f9;cursor:pointer;">
                <input type="checkbox" name="asana-project" value="${p.gid}" ${sel ? 'checked' : ''} onchange="cwAsanaToggle(this, '${p.gid}')" style="margin-top:3px;">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size: var(--d-fs-sm);display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;">
                        ${esc(p.name || '?')}
                        ${isMatch ? '<span style="background:var(--thoxan-700);color:#fff;font-size: var(--d-fs-xs);padding:1px 6px;border-radius:8px;font-weight:700;">Match</span>' : ''}
                    </div>
                    <div style="font-size: var(--d-fs-xs);color:#94a3b8;margin-top:2px;">${meta}</div>
                </div>
            </label>
        `;
    }

    window.cwSkipAnalysis = function() {
        saveStepAndAdvance('profile_source', {}, 'profile_review');
    };

    // ===== Speedrun: alle Profile-Steps auf einen Schlag speichern und zu Asana springen =====
    window.cwSpeedrunToAsana = async function() {
        const d = state.data;
        // Pflichtfelder
        if (!d.name || !d.slug) {
            App.showNotification('Firmenname / Slug fehlen — KI hat das nicht erkennen können. Bitte unten manuell ergänzen.', 'error');
            return;
        }
        const btns = document.querySelectorAll('.cw-quick-actions button');
        btns.forEach(b => b.disabled = true);
        const status = document.getElementById('cw-quick-status');
        const orig = status.innerHTML;
        status.insertAdjacentHTML('beforeend', '<div style="margin-top:0.5rem;color:#64748b;font-size: var(--d-fs-sm);"><span class="cw-spinner"></span> Speichere alle Profil-Felder…</div>');

        try {
            const stepsToSave = [
                ['basics', { name: d.name, slug: d.slug, abbreviation: d.abbreviation || '', industry: d.industry || '', website: d.website || '' }],
                ['profile_review', {
                    description: d.description || '',
                    target_audience: d.target_audience || '',
                    products_services: d.products_services || '',
                    unique_selling_points: d.unique_selling_points || '',
                    industry: d.industry || '',
                }],
                ['tone_values', {
                    tone_of_voice: d.tone_of_voice || '',
                    brand_values: d.brand_values || '',
                }],
            ];
            for (const [stepKey, data] of stepsToSave) {
                const r = await api('POST', `/admin/customer-wizard/${state.wizard_id}/step/${stepKey}`, { data });
                if (!r.success) throw new Error(r.message || ('Fehler in Step ' + stepKey));
                state = r.data;
            }
            renderStepper();
            renderStep('asana_projects');
        } catch (e) {
            App.showNotification(e.message || 'Fehler', 'error');
            btns.forEach(b => b.disabled = false);
            status.innerHTML = orig;
        }
    };

    // ===== Schnellstart-Analyse aus dem Basics-Step (nur URL → alles) =====
    window.cwQuickAnalyze = async function() {
        const btn = document.getElementById('cw-quick-analyze');
        const status = document.getElementById('cw-quick-status');
        const url = document.getElementById('f-website').value.trim();
        if (!url) {
            status.innerHTML = '<span style="color:var(--rose-600);">Bitte URL eingeben</span>';
            return;
        }
        if (!/^https?:\/\//i.test(url)) {
            document.getElementById('f-website').value = 'https://' + url;
        }
        btn.disabled = true;
        const origLabel = btn.innerHTML;
        btn.innerHTML = '<span class="cw-spinner" style="border-color:rgba(255,255,255,0.4);border-top-color:#fff;"></span> Analysiere…';
        status.innerHTML = '<span style="color:#64748b;">KI liest die Website und extrahiert Firmenprofil — kann 10–30 Sek dauern…</span>';

        try {
            const r = await api('POST', '/admin/customer-profile-suggest', {
                source: 'url',
                url: document.getElementById('f-website').value.trim(),
            });
            if (!r.success) throw new Error(r.message || 'Fehler');
            const s = r.data.suggestion || {};

            // Felder im Wizard-State und im DOM setzen
            const setField = (id, val) => {
                const el = document.getElementById(id);
                if (el && val) el.value = val;
            };
            if (s.name) { state.data.name = s.name; setField('f-name', s.name); }
            if (s.abbreviation) { state.data.abbreviation = s.abbreviation; setField('f-abbr', s.abbreviation); }
            if (s.industry) { state.data.industry = s.industry; setField('f-industry', s.industry); }

            // Slug aus Name ableiten (nur wenn leer / nicht user-edited)
            const slugEl = document.getElementById('f-slug');
            if (slugEl && !slugEl.dataset.userEdited && s.name) {
                slugEl.value = s.name.toLowerCase()
                    .replace(/[äöüß]/g, m => ({ä:'ae',ö:'oe',ü:'ue',ß:'ss'}[m]))
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '');
                state.data.slug = slugEl.value;
            }

            // Profile-Felder im State (für späteren profile_review-Step)
            ['description','target_audience','products_services','unique_selling_points','tone_of_voice','brand_values'].forEach(k => {
                if (s[k]) state.data[k] = s[k];
            });

            const fieldsPreview = [
                ['Firmenname', s.name],
                ['Kürzel', s.abbreviation],
                ['Branche', s.industry],
                ['Beschreibung', s.description],
                ['Zielgruppe', s.target_audience],
                ['Produkte/Services', s.products_services],
                ['USPs', s.unique_selling_points],
                ['Tonalität', s.tone_of_voice],
                ['Markenwerte', s.brand_values],
            ].filter(([_, v]) => v && v.trim() !== '');

            status.innerHTML = `<div class="cw-quick-result">
                <div class="cw-quick-result-head">
                    <span class="material-symbols-rounded">check_circle</span>
                    <div>
                        <strong>${fieldsPreview.length} Felder von der KI ausgefüllt</strong>
                        <small>Alles soweit erkannt — du kannst direkt zur Asana-Auswahl springen oder die Felder vorher prüfen.</small>
                    </div>
                </div>
                <div class="cw-quick-preview">
                    ${fieldsPreview.map(([k, v]) => `
                        <div class="cw-quick-row"><div class="cw-quick-key">${k}</div><div class="cw-quick-val">${esc(String(v).length > 180 ? String(v).substring(0, 180) + '…' : String(v))}</div></div>
                    `).join('')}
                </div>
                <div class="cw-quick-actions">
                    <button class="thx-btn thx-btn-primary" onclick="cwSpeedrunToAsana()">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">arrow_forward</span>
                        Direkt zu Asana
                    </button>
                    <button class="thx-btn thx-btn-secondary" onclick="cwGoto('profile_review')">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">tune</span>
                        Felder vorher prüfen
                    </button>
                </div>
            </div>`;
        } catch (e) {
            status.innerHTML = `<div style="background:var(--rose-50);border:1px solid #fecaca;padding:0.7rem 0.9rem;border-radius:10px;color:var(--rose-600);">${esc(e.message || 'Fehler')}</div>`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = origLabel;
        }
    };

    window.cwAnalyze = async function() {
        const activeBtn = document.querySelector('.cw-source-tab.active');
        const src = activeBtn ? activeBtn.dataset.src : 'url';
        const status = document.getElementById('cw-analysis-status');
        status.innerHTML = '<span class="cw-spinner"></span>KI analysiert die Quelle...';

        try {
            let resp;
            if (src === 'file') {
                const fileInput = document.getElementById('src-file-input');
                if (!fileInput.files[0]) throw new Error('Bitte Datei auswaehlen');
                const fd = new FormData();
                fd.append('source', 'file');
                fd.append('file', fileInput.files[0]);
                const res = await fetch('/api/v1/admin/customer-profile-suggest', {
                    method: 'POST', body: fd,
                    headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' }
                });
                resp = await res.json();
            } else if (src === 'url') {
                const url = document.getElementById('src-url-input').value.trim();
                if (!url) throw new Error('URL eingeben');
                resp = await api('POST', '/admin/customer-profile-suggest', { source: 'url', url });
            } else {
                const text = document.getElementById('src-text-input').value.trim();
                if (text.length < 50) throw new Error('Mindestens 50 Zeichen');
                resp = await api('POST', '/admin/customer-profile-suggest', { source: 'text', text });
            }
            if (!resp.success) throw new Error(resp.message || 'Fehler');
            const s = resp.data.suggestion;

            // KI-Vorschlaege direkt in den State + in die jetzigen Input-Felder schreiben.
            // Konvention: Form-Inputs heißen f-{slug-mit-dash}.
            const fields = ['description', 'target_audience', 'products_services',
                            'unique_selling_points', 'industry', 'tone_of_voice', 'brand_values'];
            let befuellt = 0;
            for (const k of fields) {
                if (!s[k]) continue;
                state.data[k] = s[k];
                const inp = document.getElementById('f-' + k.replace(/_/g, '-'));
                if (inp) { inp.value = s[k]; befuellt++; }
            }

            status.innerHTML = `<div style="background:#ecfdf5;border:1px solid #bbf7d0;padding:0.75rem;border-radius:8px;color:#047857;">
                <strong>${befuellt} Felder übernommen.</strong> Du kannst alle Felder unten direkt nachjustieren.
            </div>`;
        } catch (e) {
            status.innerHTML = `<div style="background:var(--rose-50);border:1px solid #fecaca;padding:0.75rem;border-radius:8px;color:var(--rose-600);">Fehler: ${esc(e.message || '')}</div>`;
        }
    };

    window.cwSuggestTone = async function() {
        const out = document.getElementById('cw-tone-suggestion');
        out.innerHTML = '<span class="cw-spinner"></span>Vorschlag wird generiert...';
        const d = state.data;
        const ctx = `${d.description}\n${d.target_audience}\n${d.products_services}\n${d.unique_selling_points}`.trim();
        if (!ctx) { out.innerHTML = '<span style="color:#94a3b8;">Erst die Profil-Schritte ausfüllen.</span>'; return; }
        try {
            const r = await api('POST', '/admin/customer-profile-suggest', { source: 'text', text: ctx });
            if (r.success) {
                const s = r.data.suggestion;
                if (s.tone_of_voice) document.getElementById('f-tone').value = s.tone_of_voice;
                if (s.brand_values) document.getElementById('f-values').value = s.brand_values;
                out.innerHTML = `<div style="background:#ecfdf5;border:1px solid #bbf7d0;padding:0.5rem;border-radius:6px;color:#047857;font-size: var(--d-fs-sm);">Tonalität: <strong>${esc(s.tone_of_voice || '-')}</strong> &middot; Werte: <strong>${esc(s.brand_values || '-')}</strong></div>`;
            } else {
                out.innerHTML = `<span style="color:var(--rose-600);">${esc(r.message || 'Fehler')}</span>`;
            }
        } catch (e) {
            out.innerHTML = `<span style="color:var(--rose-600);">${esc(e.message || '')}</span>`;
        }
    };

    window.cwCommit = async function() {
        const btn = document.getElementById('cw-commit');
        btn.disabled = true; btn.innerHTML = '<span class="cw-spinner"></span>Lege an...';
        // Asana-Auswahl + Sync-Einstellungen aus dem Step in den State speichern
        const gids = Array.from(cwAsanaSelected || []);
        const syncEl = document.getElementById('f-sync-enabled');
        const intEl = document.getElementById('f-sync-interval');
        if (syncEl && intEl) {
            await saveStepAndAdvance('asana_done', {
                asana_project_gids: gids,
                asana_sync_enabled: syncEl.checked,
                asana_sync_interval_hours: parseInt(intEl.value) || 4,
            }, null);
        }
        const r = await api('POST', `/admin/customer-wizard/${state.wizard_id}/commit`);
        if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); btn.disabled = false; btn.textContent = 'Kunde anlegen'; return; }
        App.showNotification('Kunde angelegt' + (r.data.asana_sync_queued ? ' — Asana-Sync gestartet' : ''), 'success');
        window.location.href = '/admin/customers/' + r.data.customer_id + '/steckbrief';
    };

    function esc(s) {
        const div = document.createElement('div');
        div.textContent = s ?? '';
        return div.innerHTML;
    }

    start();
})();
</script>
