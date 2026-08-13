<div class="page-header">
    <h1>Prompt Optimizer</h1>
    <p style="color:var(--color-gray-500);font-size: var(--d-fs-sm);margin-top:4px;">Iterativ den Analyse-Prompt verbessern: Testen → Feedback → Optimieren → Testen</p>
</div>

<style>
.po-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); }
.po-card { background: var(--color-white); border: 1px solid var(--color-gray-200); border-radius: var(--radius-lg); padding: var(--spacing-lg); }
.po-card h3 { font-size: var(--d-fs-base); margin-bottom: var(--spacing-md); padding-bottom: var(--spacing-sm); border-bottom: 1px solid var(--color-gray-200); }
.po-version { padding: 10px; border: 1px solid var(--color-gray-200); border-radius: var(--radius-md); margin-bottom: 8px; cursor: pointer; transition: background 0.15s; }
.po-version:hover { background: var(--color-gray-50); }
.po-version.active { border-color: var(--color-primary); background: rgba(59,130,246,0.03); }
.po-version-header { display: flex; align-items: center; gap: 8px; font-size: var(--d-fs-sm); }
.po-version-badge { font-size: var(--d-fs-xs); padding: 1px 6px; border-radius: 8px; font-weight: 600; }
.po-prompt-text { font-family: 'SF Mono', 'Fira Code', monospace; font-size: var(--d-fs-sm); line-height: 1.5; white-space: pre-wrap; max-height: 400px; overflow-y: auto; padding: 12px; background: var(--color-gray-50); border-radius: var(--radius-md); border: 1px solid var(--color-gray-200); }
.po-feedback { font-size: var(--d-fs-sm); margin-top: 6px; padding: 6px 8px; border-radius: var(--radius-sm); }
.po-feedback-good { background: rgba(22,163,74,0.05); color: #16a34a; }
.po-feedback-bad { background: rgba(220,38,38,0.05); color: #dc2626; }
@media (max-width: 900px) { .po-grid { grid-template-columns: 1fr; } }
</style>

<div class="po-grid">
    <div class="po-card">
        <h3>Aktueller Prompt</h3>
        <div id="po-current"></div>
        <div style="margin-top:var(--spacing-md);">
            <button class="btn btn-secondary" onclick="editPrompt()">Manuell bearbeiten</button>
            <button class="btn btn-primary" onclick="initPrompt()" id="po-init-btn" style="display:none;">Initialisieren</button>
        </div>
        <div id="po-editor" style="display:none;margin-top:var(--spacing-md);">
            <textarea id="po-edit-text" style="width:100%;min-height:300px;font-family:monospace;font-size: var(--d-fs-sm);padding:10px;border:1px solid var(--color-gray-200);border-radius:var(--radius-md);"></textarea>
            <div style="margin-top:8px;display:flex;gap:8px;">
                <button class="btn btn-primary" onclick="saveEditedPrompt()">Speichern</button>
                <button class="btn btn-secondary" onclick="document.getElementById('po-editor').style.display='none';">Abbrechen</button>
            </div>
            <div style="font-size: var(--d-fs-xs);color:var(--color-gray-400);margin-top:4px;">Platzhalter: <code>{CONTEXT_BLOCK}</code>, <code>{TYPES_BLOCK}</code>, <code>{SCOPE_BLOCK}</code> werden beim Ausfuehren dynamisch ersetzt.</div>
        </div>
    </div>

    <div class="po-card">
        <h3>Versions-Historie</h3>
        <div id="po-history" style="max-height:500px;overflow-y:auto;"></div>
    </div>
</div>

<script>
function waitForApp(fn) {
    if (window.App && typeof window.App.request === 'function') { fn(); return; }
    const i = setInterval(() => { if (window.App && typeof window.App.request === 'function') { clearInterval(i); fn(); } }, 50);
}

waitForApp(async () => {
    async function api(m, e, d) { try { return await App.request(m, e, d); } catch(e) { return { success: false, message: e.message }; } }
    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    let data = null;

    async function load() {
        const res = await api('GET', '/admin/prompt-optimizer');
        if (!res.success) return;
        data = res.data;
        render();
    }

    function render() {
        const currentEl = document.getElementById('po-current');
        const historyEl = document.getElementById('po-history');
        const initBtn = document.getElementById('po-init-btn');

        if (!data.current) {
            currentEl.innerHTML = '<div style="color:var(--color-gray-400);text-align:center;padding:20px;">Noch kein Prompt gespeichert. Klicke "Initialisieren" um den Default-Prompt als v1 zu speichern.</div>';
            initBtn.style.display = '';
            historyEl.innerHTML = '';
            return;
        }

        initBtn.style.display = 'none';
        const c = data.current;
        currentEl.innerHTML = `
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <span class="po-version-badge" style="background:var(--color-primary);color:white;">v${c.version}</span>
                <span style="font-size: var(--d-fs-sm);color:var(--color-gray-500);">${new Date(c.created_at).toLocaleString('de-DE')}</span>
                ${c.optimization_reasoning ? '<span style="font-size: var(--d-fs-sm);color:var(--color-gray-600);">— ' + esc(c.optimization_reasoning) + '</span>' : ''}
            </div>
            <div class="po-prompt-text">${esc(c.prompt_text)}</div>
            ${c.feedback_positive ? '<div class="po-feedback po-feedback-good">+ ' + esc(c.feedback_positive) + '</div>' : ''}
            ${c.feedback_negative ? '<div class="po-feedback po-feedback-bad">- ' + esc(c.feedback_negative) + '</div>' : ''}
            ${c.performance_score ? '<div style="font-size: var(--d-fs-sm);color:var(--color-gray-500);margin-top:4px;">Score: ' + c.performance_score + '/10</div>' : ''}
        `;

        // Historie
        if (data.history.length === 0) {
            historyEl.innerHTML = '<div style="color:var(--color-gray-400);text-align:center;padding:20px;">Keine Historie</div>';
        } else {
            historyEl.innerHTML = data.history.map(h => {
                const isCurrent = h.version === data.version;
                return `<div class="po-version ${isCurrent ? 'active' : ''}">
                    <div class="po-version-header">
                        <span class="po-version-badge" style="background:${isCurrent ? 'var(--color-primary)' : 'var(--color-gray-200)'};color:${isCurrent ? 'white' : 'var(--color-gray-600)'};">v${h.version}</span>
                        <span style="color:var(--color-gray-500);font-size: var(--d-fs-sm);">${new Date(h.created_at).toLocaleString('de-DE')}</span>
                        ${h.user_name ? '<span style="font-size: var(--d-fs-xs);color:var(--color-gray-400);">' + esc(h.user_name) + '</span>' : ''}
                        ${!isCurrent ? '<button class="btn btn-secondary" style="margin-left:auto;padding:2px 8px;font-size: var(--d-fs-xs);" onclick="event.stopPropagation();rollbackTo(' + h.version + ')">Wiederherstellen</button>' : ''}
                    </div>
                    ${h.optimization_reasoning ? '<div style="font-size: var(--d-fs-sm);color:var(--color-gray-600);margin-top:4px;">' + esc(h.optimization_reasoning) + '</div>' : ''}
                    ${h.feedback_positive ? '<div class="po-feedback po-feedback-good" style="font-size: var(--d-fs-xs);">+ ' + esc(h.feedback_positive) + '</div>' : ''}
                    ${h.feedback_negative ? '<div class="po-feedback po-feedback-bad" style="font-size: var(--d-fs-xs);">- ' + esc(h.feedback_negative) + '</div>' : ''}
                </div>`;
            }).join('');
        }
    }

    window.initPrompt = async function() {
        const res = await api('POST', '/admin/prompt-optimizer/init');
        if (res.success) { App.showNotification('Prompt v1 initialisiert', 'success'); load(); }
        else App.showNotification(res.message, 'error');
    };

    window.editPrompt = function() {
        const editor = document.getElementById('po-editor');
        const textarea = document.getElementById('po-edit-text');
        if (data?.current?.prompt_text) {
            textarea.value = data.current.prompt_text;
        }
        editor.style.display = '';
    };

    window.saveEditedPrompt = async function() {
        const text = document.getElementById('po-edit-text').value.trim();
        if (!text) { App.showNotification('Prompt darf nicht leer sein', 'error'); return; }
        const res = await api('POST', '/admin/prompt-optimizer/save', { prompt_text: text, reasoning: 'Manuell bearbeitet' });
        if (res.success) {
            App.showNotification('Neue Version gespeichert (v' + res.data.version + ')', 'success');
            document.getElementById('po-editor').style.display = 'none';
            load();
        } else {
            App.showNotification(res.message, 'error');
        }
    };

    window.rollbackTo = async function(version) {
        if (!confirm('Zu Version ' + version + ' zurueckkehren?')) return;
        const res = await api('POST', '/admin/prompt-optimizer/rollback', { version });
        if (res.success) { App.showNotification('Rollback zu v' + version, 'success'); load(); }
        else App.showNotification(res.message, 'error');
    };

    load();
});
</script>
