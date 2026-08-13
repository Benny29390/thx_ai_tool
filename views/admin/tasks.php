<div class="tk-page">
    <div class="tk-header">
        <div>
            <h1 style="margin:0;font-size: var(--d-fs-2xl);display:flex;align-items:center;gap:0.5rem;">
                <span class="material-symbols-rounded" style="color:#10b981;font-size:28px;">auto_awesome</span>
                KI-Coworker
            </h1>
            <p style="margin:4px 0 0;color:#64748b;font-size: var(--d-fs-sm);">
                Beschreibe in Klartext, was geändert werden soll — die KI editiert direkt. Bei Unklarheiten fragt sie nach. Jede Änderung ist rückgängig zu machen.
            </p>
        </div>
    </div>

    <!-- Eingabe -->
    <div class="tk-input-box">
        <textarea id="tk-prompt" placeholder="z.B. „Mach den Speichern-Button im Customer-Edit grün und 10% größer"
                  rows="3"
                  onkeydown="if(event.key==='Enter' && (event.ctrlKey||event.metaKey)){event.preventDefault();tkSubmit();}"></textarea>
        <div class="tk-input-foot">
            <div class="tk-select-wrap">
                <label class="tk-select-label">Modul:</label>
                <div class="tk-search-select" id="tk-module-select">
                    <button type="button" class="tk-search-trigger" onclick="tkToggleModuleDropdown()">
                        <span id="tk-module-current">Auto (KI sucht selbst)</span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </button>
                    <div class="tk-search-dropdown" id="tk-module-dropdown">
                        <input type="text" id="tk-module-search" placeholder="Modul suchen…" oninput="tkFilterModules(this.value)">
                        <div class="tk-module-list" id="tk-module-list"></div>
                    </div>
                </div>
            </div>
            <label class="tk-scope-label">
                Scope:
                <select id="tk-scope">
                    <option value="frontend" selected>Frontend</option>
                    <option value="frontend_backend">Frontend + UI-Backend</option>
                    <option value="all">Alles</option>
                </select>
            </label>
            <span style="flex:1;"></span>
            <button class="btn btn-primary" id="tk-send-btn" onclick="tkSubmit()">
                <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">bolt</span>
                Senden (⌘/Strg+Enter)
            </button>
        </div>
    </div>

    <!-- Live-Stream (während neue Task läuft) -->
    <div class="tk-stream" id="tk-stream" style="display:none;">
        <div class="tk-stream-head">
            <span class="material-symbols-rounded tk-stream-spin">sync</span>
            <strong id="tk-stream-title">KI arbeitet…</strong>
            <span class="tk-stream-elapsed" id="tk-stream-elapsed">0s</span>
        </div>
        <div class="tk-stream-log" id="tk-stream-log"></div>
    </div>

    <div class="tk-layout">
        <!-- History (links) -->
        <div class="tk-history-pane">
            <div class="tk-section-head">
                <h2 style="margin:0;font-size: var(--d-fs-base);">Bisherige Tasks</h2>
            </div>
            <div class="tk-history" id="tk-history">
                <div style="text-align:center;color:#94a3b8;padding:1.5rem;font-size: var(--d-fs-sm);">Lade…</div>
            </div>
        </div>

        <!-- Task-Detail (rechts) — Conversation-View -->
        <div class="tk-detail-pane" id="tk-detail-pane">
            <div class="tk-detail-empty" id="tk-detail-empty">
                <span class="material-symbols-rounded">forum</span>
                <p>Wähle links eine Task für Details, Verlauf und Rückfragen-Antworten.</p>
            </div>
            <div class="tk-detail" id="tk-detail" style="display:none;">
                <div class="tk-detail-head">
                    <div>
                        <strong id="tk-detail-title">Task</strong>
                        <small id="tk-detail-meta"></small>
                    </div>
                    <div class="tk-detail-actions">
                        <button class="tk-icon-btn" onclick="tkOpenDiff(window._tkCurrentTaskId)" id="tk-detail-diff-btn" title="Diff anzeigen"><span class="material-symbols-rounded">compare</span></button>
                        <button class="tk-icon-btn danger" onclick="tkRollback(window._tkCurrentTaskId)" id="tk-detail-rollback-btn" title="Rollback" style="display:none;"><span class="material-symbols-rounded">undo</span></button>
                    </div>
                </div>
                <div class="tk-detail-body" id="tk-detail-body"></div>
                <div class="tk-reply-bar" id="tk-reply-bar" style="display:none;">
                    <textarea id="tk-reply-text" rows="2" placeholder="Antwort an die KI…"
                              onkeydown="if(event.key==='Enter' && (event.ctrlKey||event.metaKey)){event.preventDefault();tkReply();}"></textarea>
                    <button class="btn btn-primary" onclick="tkReply()" id="tk-reply-btn">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">send</span>
                        Antworten
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Diff-Modal -->
<div class="tk-modal-overlay" id="tk-diff-overlay" onclick="if(event.target===this)tkCloseDiff()">
    <div class="tk-modal" id="tk-diff-modal">
        <div class="tk-modal-head">
            <strong id="tk-diff-title">Änderungen</strong>
            <button class="tk-icon-btn" onclick="tkCloseDiff()"><span class="material-symbols-rounded">close</span></button>
        </div>
        <div class="tk-modal-body" id="tk-diff-body"></div>
    </div>
</div>

<style>
.tk-page { max-width: 1600px; margin: 0 auto; padding: 1.5rem 2rem; }
.tk-header { margin-bottom: 1.25rem; }

.tk-input-box {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    padding: 0.85rem 1rem; margin-bottom: 1.2rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.04);
}
#tk-prompt {
    width: 100%; border: 0; outline: none; resize: vertical;
    font-size: var(--d-fs-base); line-height: 1.55; color: #1e293b; padding: 6px 4px;
    font-family: inherit; background: transparent;
}
.tk-input-foot {
    display: flex; align-items: center; gap: 0.7rem; flex-wrap: wrap;
    padding-top: 0.5rem; border-top: 1px solid #f1f5f9;
}
.tk-select-wrap { display: inline-flex; align-items: center; gap: 6px; }
.tk-select-label, .tk-scope-label { font-size: var(--d-fs-sm); color: #64748b; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
#tk-scope {
    padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 8px;
    background: #fff; font-size: var(--d-fs-sm); font-family: inherit; color: #1e293b;
}

/* Searchable Select */
.tk-search-select { position: relative; }
.tk-search-trigger {
    display: inline-flex; align-items: center; gap: 6px; min-width: 220px;
    padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 8px;
    background: #fff; font-size: var(--d-fs-sm); color: #1e293b;
    cursor: pointer; font-family: inherit;
}
.tk-search-trigger:hover { border-color: #cbd5e1; }
.tk-search-trigger .material-symbols-rounded { margin-left: auto; font-size: 18px; color: #94a3b8; }
.tk-search-dropdown {
    display: none; position: absolute; top: calc(100% + 4px); left: 0;
    width: 360px; max-width: 90vw; background: #fff;
    border: 1px solid #e2e8f0; border-radius: 10px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.12); z-index: 60;
    padding: 6px;
}
.tk-search-dropdown.open { display: block; }
#tk-module-search {
    width: 100%; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 7px;
    font-size: var(--d-fs-sm); font-family: inherit; margin-bottom: 6px;
}
#tk-module-search:focus { outline: none; border-color: #004c9b; }
.tk-module-list { max-height: 320px; overflow-y: auto; }
.tk-module-item {
    padding: 8px 10px; cursor: pointer; border-radius: 7px;
    transition: background 0.1s;
}
.tk-module-item:hover { background: #f8fafc; }
.tk-module-item.active { background: #eaf3fc; color: #003a78; }
.tk-module-item-label { font-weight: 600; font-size: var(--d-fs-sm); color: #1e293b; }
.tk-module-item-desc { font-size: var(--d-fs-xs); color: #64748b; margin-top: 2px; }

#tk-send-btn:disabled { opacity: 0.6; cursor: wait; }

/* Live-Stream */
.tk-stream {
    background: #0f172a; color: #e2e8f0; border-radius: 12px;
    padding: 0.85rem 1rem; margin-bottom: 1.2rem;
    font-family: ui-monospace, monospace; font-size: var(--d-fs-sm);
    box-shadow: 0 8px 28px rgba(15,23,42,0.18);
}
.tk-stream-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem; }
.tk-stream-spin { color: #10b981; animation: tk-spin 1.4s linear infinite; font-size: 18px; }
.tk-stream.done .tk-stream-spin { animation: none; color: #10b981; }
.tk-stream.error .tk-stream-spin { animation: none; color: #f87171; }
.tk-stream.awaiting .tk-stream-spin { animation: none; color: #fbbf24; }
@keyframes tk-spin { to { transform: rotate(360deg); } }
.tk-stream-elapsed { margin-left: auto; color: #64748b; font-size: var(--d-fs-sm); }
.tk-stream-log { max-height: 320px; overflow-y: auto; padding: 4px 0; }
.tk-stream-line { padding: 3px 0; word-break: break-word; }
.tk-stream-line.tool { color: #fbbf24; }
.tk-stream-line.result { color: #94a3b8; padding-left: 18px; }
.tk-stream-line.msg { color: #e2e8f0; padding-left: 6px; border-left: 2px solid #1e293b; margin: 4px 0 4px 4px; }
.tk-stream-line.done { color: #10b981; font-weight: 700; }
.tk-stream-line.error { color: #f87171; }
.tk-stream-line.ask { color: #fbbf24; font-weight: 700; padding: 6px; background: rgba(251,191,36,0.08); border-radius: 6px; }

/* Layout: links History, rechts Detail */
.tk-layout { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 1.2rem; }
@media (max-width: 1100px) { .tk-layout { grid-template-columns: 1fr; } }

.tk-history-pane, .tk-detail-pane {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    display: flex; flex-direction: column; min-height: 480px;
}
.tk-section-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px; border-bottom: 1px solid #f1f5f9;
}
.tk-history { flex: 1; overflow-y: auto; }
.tk-task {
    display: grid; grid-template-columns: auto 1fr; gap: 0.65rem;
    padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer;
    align-items: start; transition: background 0.1s;
}
.tk-task:hover { background: #fafbfc; }
.tk-task.active { background: #eaf3fc; }
.tk-task:last-child { border-bottom: 0; }
.tk-task-status {
    width: 24px; height: 24px; border-radius: 7px; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.tk-task-status.completed { background: #ecfdf5; color: #047857; }
.tk-task-status.failed { background: #fef2f2; color: #b91c1c; }
.tk-task-status.rolled_back { background: #f1f5f9; color: #64748b; }
.tk-task-status.running { background: #fef3c7; color: #92400e; }
.tk-task-status.awaiting_user { background: #fef3c7; color: #92400e; animation: tk-pulse 1.8s ease-in-out infinite; }
.tk-task-status.pending { background: #f1f5f9; color: #94a3b8; }
.tk-task-status .material-symbols-rounded { font-size: 15px; }
@keyframes tk-pulse { 0%,100%{opacity:1;} 50%{opacity:0.6;} }
.tk-task-prompt { font-size: var(--d-fs-sm); color: #1e293b; line-height: 1.4; }
.tk-task-meta { font-size: var(--d-fs-xs); color: #94a3b8; margin-top: 2px; }
.tk-task-meta .scope-pill { background: #f1f5f9; padding: 0 5px; border-radius: 4px; margin-right: 4px; font-weight: 600; color: #475569; font-size: var(--d-fs-xs); }
.tk-user-chip {
    display: inline-flex; align-items: center; justify-content: center;
    width: 18px; height: 18px; border-radius: 50%;
    background: linear-gradient(135deg, #004c9b, #1565b8); color: #fff;
    font-size: var(--d-fs-xs); font-weight: 700; letter-spacing: 0.3px;
    margin-right: 5px; vertical-align: middle;
}

.tk-icon-btn {
    background: none; border: 0; cursor: pointer; padding: 6px;
    border-radius: 7px; color: #94a3b8; display: flex; align-items: center;
    transition: all 0.1s;
}
.tk-icon-btn:hover { background: #f1f5f9; color: #1e293b; }
.tk-icon-btn.danger:hover { background: #fef2f2; color: #dc2626; }
.tk-icon-btn .material-symbols-rounded { font-size: 18px; }

/* Detail (Chat-View) */
.tk-detail-empty {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: #94a3b8; padding: 2rem; text-align: center;
}
.tk-detail-empty .material-symbols-rounded { font-size: 48px; color: #cbd5e1; margin-bottom: 0.5rem; }
.tk-detail-empty p { margin: 0; font-size: var(--d-fs-sm); }

.tk-detail { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.tk-detail-head {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px; border-bottom: 1px solid #f1f5f9;
}
.tk-detail-head strong { display: block; font-size: var(--d-fs-sm); color: #1e293b; }
.tk-detail-head small { display: block; color: #94a3b8; font-size: var(--d-fs-xs); margin-top: 2px; }
.tk-detail-head > div:first-child { flex: 1; min-width: 0; }
.tk-detail-actions { display: flex; gap: 2px; }
.tk-detail-body { flex: 1; overflow-y: auto; padding: 0.9rem 1rem; }

.tk-msg { margin-bottom: 12px; }
.tk-msg.user { padding-left: 0; }
.tk-msg.user .tk-msg-content {
    background: #eaf3fc; color: #003a78; padding: 10px 14px;
    border-radius: 14px 14px 4px 14px; display: inline-block;
    max-width: 90%; font-size: var(--d-fs-sm); line-height: 1.55; white-space: pre-wrap;
}
.tk-msg.user .tk-msg-head { font-size: var(--d-fs-xs); color: #64748b; margin-bottom: 4px; font-weight: 600; }
.tk-msg.assistant .tk-msg-content {
    background: #f8fafc; color: #1e293b; padding: 10px 14px;
    border-radius: 14px 14px 14px 4px; display: inline-block;
    max-width: 90%; font-size: var(--d-fs-sm); line-height: 1.55; white-space: pre-wrap;
    border-left: 3px solid #10b981;
}
.tk-msg.assistant.ask .tk-msg-content {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-left-color: #f59e0b; font-weight: 500;
}
.tk-msg.tool {
    font-family: ui-monospace, monospace; font-size: var(--d-fs-sm); color: #64748b;
    padding: 4px 12px; background: #f8fafc;
    border-left: 2px solid #e2e8f0; margin: 4px 0 4px 8px;
    border-radius: 0 6px 6px 0;
}
.tk-msg.tool .tk-msg-tool-name { color: #b45309; font-weight: 700; }
.tk-msg.tool.error { background: #fef2f2; color: #b91c1c; border-left-color: #dc2626; }
.tk-msg.tool .tk-msg-content { white-space: pre-wrap; word-break: break-word; max-height: 200px; overflow-y: auto; }

.tk-reply-bar {
    display: flex; gap: 8px; padding: 10px 16px;
    border-top: 1px solid #f1f5f9; background: linear-gradient(180deg, #fef3c7 0%, #fffbeb 100%);
}
#tk-reply-text {
    flex: 1; padding: 8px 12px; border: 1px solid #fcd34d; border-radius: 9px;
    font-family: inherit; font-size: var(--d-fs-sm); resize: vertical; outline: none;
}
#tk-reply-text:focus { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.15); }

/* Diff-Modal */
.tk-modal-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(6px);
    z-index: 410; display: none; align-items: center; justify-content: center; padding: 2vh;
}
.tk-modal-overlay.open { display: flex; }
.tk-modal {
    width: 95vw; max-width: 1100px; height: 92vh;
    background: #fff; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    display: flex; flex-direction: column; overflow: hidden;
}
.tk-modal-head {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.9rem 1.2rem; border-bottom: 1px solid #f1f5f9; background: #f8fafc;
}
.tk-modal-head strong { flex: 1; }
.tk-modal-body { flex: 1; overflow-y: auto; padding: 1rem 1.2rem; min-height: 0; }
.tk-diff-file { margin-bottom: 1.5rem; }
.tk-diff-file-head {
    display: flex; align-items: center; gap: 0.5rem;
    background: #0f172a; color: #fff; padding: 8px 12px; border-radius: 8px 8px 0 0;
    font-family: ui-monospace, monospace; font-size: var(--d-fs-sm);
}
.tk-diff-file-op { font-size: var(--d-fs-xs); padding: 1px 7px; border-radius: 4px; background: rgba(255,255,255,0.15); font-weight: 700; text-transform: uppercase; }
.tk-diff-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 0; border: 1px solid #1e293b; border-top: 0; border-radius: 0 0 8px 8px; overflow: hidden; }
.tk-diff-panel {
    font-family: ui-monospace, monospace; font-size: var(--d-fs-sm); line-height: 1.55;
    max-height: 420px; overflow: auto; white-space: pre; padding: 8px 12px;
}
.tk-diff-panel.old { background: #fef2f2; border-right: 1px solid #1e293b; }
.tk-diff-panel.new { background: #ecfdf5; }
.tk-diff-panel-label {
    background: #f1f5f9; padding: 4px 12px; font-family: inherit; font-size: var(--d-fs-xs);
    text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700;
}
@media (max-width: 800px) {
    .tk-diff-panels { grid-template-columns: 1fr; }
    .tk-diff-panel.old { border-right: 0; border-bottom: 1px solid #1e293b; }
}
</style>

<script>
function waitForApp(fn) {
    if (window.App && typeof window.App.get === 'function') { fn(); return; }
    const i = setInterval(() => {
        if (window.App && typeof window.App.get === 'function') { clearInterval(i); fn(); }
    }, 50);
}

// ===== Module-Select =====
let tkModules = [];
let tkSelectedModule = null;

async function tkLoadModules() {
    try {
        const r = await App.get('/admin/tasks/modules');
        if (r.success) tkModules = r.data.modules || [];
        tkRenderModuleList('');
    } catch (e) { console.error(e); }
}

function tkRenderModuleList(filter) {
    const list = document.getElementById('tk-module-list');
    const q = (filter || '').toLowerCase().trim();
    let items = [{ key: null, label: 'Auto (KI sucht selbst)', description: 'Keine Vorgabe — KI findet die richtigen Dateien selbst' }];
    items = items.concat(tkModules);
    if (q) items = items.filter(m => (m.label || '').toLowerCase().includes(q) || (m.description || '').toLowerCase().includes(q));
    list.innerHTML = items.map(m => {
        const active = (tkSelectedModule === null && m.key === null) || tkSelectedModule === m.key;
        return `<div class="tk-module-item ${active ? 'active' : ''}" onclick="tkSelectModule(${m.key === null ? 'null' : `'${m.key}'`})">
            <div class="tk-module-item-label">${tkEsc(m.label)}</div>
            <div class="tk-module-item-desc">${tkEsc(m.description || '')}</div>
        </div>`;
    }).join('');
}

window.tkSelectModule = function(key) {
    tkSelectedModule = key;
    const item = key ? tkModules.find(m => m.key === key) : null;
    document.getElementById('tk-module-current').textContent = item ? item.label : 'Auto (KI sucht selbst)';
    document.getElementById('tk-module-dropdown').classList.remove('open');
};

window.tkToggleModuleDropdown = function() {
    const dd = document.getElementById('tk-module-dropdown');
    dd.classList.toggle('open');
    if (dd.classList.contains('open')) {
        document.getElementById('tk-module-search').focus();
        tkRenderModuleList('');
    }
};

window.tkFilterModules = function(v) { tkRenderModuleList(v); };

// Outside-Click schließt Dropdown
document.addEventListener('click', (e) => {
    if (!e.target.closest('.tk-search-select')) {
        document.getElementById('tk-module-dropdown')?.classList.remove('open');
    }
});

// ===== Submit / Stream =====
let tkStartTs = 0;
let tkElapsedTimer = null;

window.tkSubmit = async function() {
    const promptEl = document.getElementById('tk-prompt');
    const scope = document.getElementById('tk-scope').value;
    const prompt = promptEl.value.trim();
    if (!prompt) { App.showNotification('Bitte eine Anforderung formulieren', 'error'); return; }

    const sendBtn = document.getElementById('tk-send-btn');
    sendBtn.disabled = true;

    try {
        // 1. Task anlegen
        const r = await App.post('/admin/tasks', { prompt, scope, module: tkSelectedModule });
        if (!r.success) throw new Error(r.message || 'Fehler');
        promptEl.value = '';
        App.showNotification(r.message || 'Task angelegt', 'success');

        // 2. Sofort versuchen zu starten — Auto-Pickup kümmert sich darum
        tkLoadHistory();
        await tkTryRunNext();
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    } finally {
        sendBtn.disabled = false;
    }
};

// Auto-Pickup: startet die nächste pending-Task wenn keine läuft
let tkRunningStream = false;
async function tkTryRunNext() {
    if (tkRunningStream) return;
    try {
        const r = await App.get('/admin/tasks?status=pending');
        const tasks = (r.data && r.data.tasks) || [];
        if (!tasks.length) return;
        // Älteste pending-Task zuerst
        const next = tasks.reverse()[0];

        // Check ob etwas anderes läuft
        const runR = await App.get('/admin/tasks?status=running');
        if (((runR.data && runR.data.tasks) || []).length > 0) return;

        // Starten
        tkRunningStream = true;
        tkShowStream();
        try {
            const response = await fetch('/api/v1/admin/tasks/' + next.id + '/run', {
                method: 'POST',
                headers: { 'X-CSRF-Token': App.csrfToken },
            });
            if (response.status === 409) return; // andere Task hat in der Zwischenzeit gestartet
            await tkReadStream(response);
        } finally {
            tkRunningStream = false;
            tkStopElapsedTimer();
            tkLoadHistory();
            // Direkt nächste prüfen
            setTimeout(tkTryRunNext, 500);
        }
    } catch (e) {
        tkRunningStream = false;
    }
}

window.tkReply = async function() {
    const taskId = window._tkCurrentTaskId;
    if (!taskId) return;
    const ta = document.getElementById('tk-reply-text');
    const text = ta.value.trim();
    if (!text) return;
    const btn = document.getElementById('tk-reply-btn');
    btn.disabled = true;
    tkShowStream();
    try {
        const response = await fetch('/api/v1/admin/tasks/' + taskId + '/reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ reply: text }),
        });
        await tkReadStream(response);
        ta.value = '';
        tkLoadHistory();
        tkOpenTask(taskId);
    } catch (e) {
        tkAppendStream('error', { message: e.message });
    } finally {
        btn.disabled = false;
        tkStopElapsedTimer();
    }
};

async function tkReadStream(response) {
    if (!response.ok && response.headers.get('content-type')?.includes('application/json')) {
        const err = await response.json();
        throw new Error(err.message || 'HTTP ' + response.status);
    }
    if (!response.body) throw new Error('Kein Stream-Body');
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
                if (line.startsWith('event: ')) event = line.substring(7).trim();
                else if (line.startsWith('data: ')) {
                    try { data = JSON.parse(line.substring(6)); } catch (e) {}
                }
            }
            tkAppendStream(event, data);
        }
    }
}

function tkShowStream() {
    const streamEl = document.getElementById('tk-stream');
    streamEl.classList.remove('done', 'error', 'awaiting');
    streamEl.style.display = 'block';
    document.getElementById('tk-stream-title').textContent = 'KI arbeitet…';
    document.getElementById('tk-stream-log').innerHTML = '';
    tkStartTs = Date.now();
    if (tkElapsedTimer) clearInterval(tkElapsedTimer);
    tkElapsedTimer = setInterval(() => {
        document.getElementById('tk-stream-elapsed').textContent = Math.floor((Date.now() - tkStartTs) / 1000) + 's';
    }, 500);
}

function tkStopElapsedTimer() { if (tkElapsedTimer) { clearInterval(tkElapsedTimer); tkElapsedTimer = null; } }

function tkAppendStream(event, data) {
    const log = document.getElementById('tk-stream-log');
    const stream = document.getElementById('tk-stream');
    const title = document.getElementById('tk-stream-title');
    let html = '';
    switch (event) {
        case 'task_created':
            window._tkCurrentTaskId = data.task_id;
            html = `<div class="tk-stream-line">⋯ Task #${data.task_id} angelegt</div>`;
            break;
        case 'resume':
            window._tkCurrentTaskId = data.task_id;
            html = `<div class="tk-stream-line">▶ Setze Task #${data.task_id} fort</div>`;
            break;
        case 'start':
            html = `<div class="tk-stream-line">▶ Start — Scope: <strong>${data.scope}</strong>${data.module ? ' · Modul: <strong>' + tkEsc(data.module) + '</strong>' : ''}</div>`;
            break;
        case 'tool_call':
            const args = JSON.stringify(data.args || {}).substring(0, 200);
            html = `<div class="tk-stream-line tool">⚙ <strong>${tkEsc(data.tool)}</strong>(${tkEsc(args)})</div>`;
            break;
        case 'tool_result':
            const snip = (data.snippet || '').replace(/\n/g, ' ⏎ ').substring(0, 240);
            html = `<div class="tk-stream-line result">${data.ok ? '✓' : '✕'} ${tkEsc(snip)}</div>`;
            break;
        case 'assistant_message':
            html = `<div class="tk-stream-line msg">💬 ${tkEsc(data.text || '')}</div>`;
            break;
        case 'awaiting_user':
            html = `<div class="tk-stream-line ask">❓ ${tkEsc(data.question || 'Rückfrage')}</div>`;
            title.textContent = 'Wartet auf deine Antwort';
            stream.classList.add('awaiting');
            break;
        case 'done':
            html = `<div class="tk-stream-line done">🎉 ${tkEsc(data.summary || 'Fertig')} — ${data.files_changed || 0} Dateien</div>`;
            title.textContent = 'Fertig';
            stream.classList.add('done');
            break;
        case 'error':
            html = `<div class="tk-stream-line error">⚠ Fehler: ${tkEsc(data.message || 'Unbekannt')}${data.rolled_back ? ' — Rollback durchgeführt' : ''}</div>`;
            title.textContent = 'Fehlgeschlagen';
            stream.classList.add('error');
            break;
        default:
            return;
    }
    log.insertAdjacentHTML('beforeend', html);
    log.scrollTop = log.scrollHeight;
}

function tkEsc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

// ===== History =====
async function tkLoadHistory() {
    const list = document.getElementById('tk-history');
    try {
        const r = await App.get('/admin/tasks');
        if (!r.success) throw new Error(r.message);
        const tasks = r.data.tasks || [];
        if (!tasks.length) {
            list.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:2rem;font-size: var(--d-fs-sm);">Noch keine Tasks.</div>';
            return;
        }
        list.innerHTML = tasks.map(t => tkRenderTaskRow(t)).join('');
        // Auto-Refresh wenn etwas läuft oder wartet
        const stillActive = tasks.some(t => ['running', 'awaiting_user', 'pending'].includes(t.status));
        if (stillActive) {
            clearTimeout(window._tkRefreshTimer);
            window._tkRefreshTimer = setTimeout(tkLoadHistory, 3000);
        }
        // Auto-Pickup: pending vorhanden + nichts läuft → starten
        const hasPending = tasks.some(t => t.status === 'pending');
        const hasRunning = tasks.some(t => t.status === 'running');
        if (hasPending && !hasRunning && !tkRunningStream) {
            tkTryRunNext();
        }
    } catch (e) {
        list.innerHTML = '<div style="color:#dc2626;padding:1rem;">' + tkEsc(e.message) + '</div>';
    }
}

function tkRenderTaskRow(t) {
    const statusIcon = {
        completed: 'check_circle', failed: 'error', rolled_back: 'history',
        running: 'autorenew', pending: 'schedule', awaiting_user: 'help',
    }[t.status] || 'help';
    const created = new Date(t.created_at.replace(' ', 'T'));
    const ago = tkAgo(created);
    const promptShort = t.prompt.length > 80 ? t.prompt.substring(0, 80) + '…' : t.prompt;
    const active = window._tkCurrentTaskId == t.id ? 'active' : '';
    // User-Initialen
    const userAbbr = (t.created_by_abbr || '').trim() || tkInitials(t.created_by_name || '?');
    // Tokens + Kosten
    const totalTokens = (t.tokens_input || 0) + (t.tokens_output || 0);
    const tokensStr = totalTokens > 0 ? Number(totalTokens).toLocaleString('de-DE') + ' tok' : '';
    const costStr = (t.cost_usd && t.cost_usd > 0) ? '$' + Number(t.cost_usd).toFixed(t.cost_usd < 0.01 ? 4 : 2) : '';

    return `
        <div class="tk-task ${active}" onclick="tkOpenTask(${t.id})">
            <div class="tk-task-status ${t.status}">
                <span class="material-symbols-rounded">${statusIcon}</span>
            </div>
            <div style="min-width:0;">
                <div class="tk-task-prompt">${tkEsc(promptShort)}</div>
                <div class="tk-task-meta">
                    <span class="tk-user-chip" title="${tkEsc(t.created_by_name || '')}">${tkEsc(userAbbr)}</span>
                    <span class="scope-pill">${tkEsc(t.scope)}</span>
                    #${t.id} · ${ago}
                    ${t.files_changed ? ' · ' + t.files_changed + ' Datei' + (t.files_changed === 1 ? '' : 'en') : ''}
                    ${tokensStr ? ' · ' + tokensStr : ''}
                    ${costStr ? ' · <strong style="color:#047857;">' + costStr + '</strong>' : ''}
                </div>
            </div>
        </div>
    `;
}

function tkInitials(name) {
    const parts = (name || '').trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0].charAt(0) + parts[parts.length-1].charAt(0)).toUpperCase();
    return (parts[0] || '?').substring(0, 2).toUpperCase();
}

function tkAgo(d) {
    const s = Math.floor((Date.now() - d.getTime()) / 1000);
    if (s < 60) return 'gerade eben';
    if (s < 3600) return 'vor ' + Math.floor(s/60) + ' Min';
    if (s < 86400) return 'vor ' + Math.floor(s/3600) + ' Std';
    if (s < 7*86400) return 'vor ' + Math.floor(s/86400) + ' T';
    return d.toLocaleDateString('de-DE', {day:'2-digit',month:'2-digit',year:'numeric'});
}

// ===== Task-Detail (Chat-View) =====
window.tkOpenTask = async function(taskId) {
    window._tkCurrentTaskId = taskId;
    // History-Markup highlight
    document.querySelectorAll('.tk-task').forEach(el => el.classList.remove('active'));
    document.getElementById('tk-detail-empty').style.display = 'none';
    document.getElementById('tk-detail').style.display = 'flex';
    const body = document.getElementById('tk-detail-body');
    body.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:1rem;">Lade…</div>';

    try {
        const r = await App.get('/admin/tasks/' + taskId);
        if (!r.success) throw new Error(r.message);
        const t = r.data;

        document.getElementById('tk-detail-title').textContent = 'Task #' + t.id;
        const meta = [];
        if (t.created_by_name) meta.push('von ' + t.created_by_name);
        meta.push(t.scope);
        if (t.module) meta.push('Modul: ' + t.module);
        const totalTok = (t.tokens_input || 0) + (t.tokens_output || 0);
        if (totalTok > 0) {
            meta.push(Number(totalTok).toLocaleString('de-DE') + ' tok');
            if (t.cost_usd > 0) meta.push('$' + Number(t.cost_usd).toFixed(t.cost_usd < 0.01 ? 4 : 2));
        }
        if (t.summary) meta.push(t.summary);
        document.getElementById('tk-detail-meta').textContent = meta.join(' · ');

        document.getElementById('tk-detail-diff-btn').style.display = (t.snapshots && t.snapshots.length) ? '' : 'none';
        // Rollback-Button bei jeder Task mit nicht-zurückgerollten Snapshots — auch bei running/failed (für Notfall)
        const hasLiveSnapshots = (t.snapshots || []).some(s => !s.rolled_back_at);
        document.getElementById('tk-detail-rollback-btn').style.display = hasLiveSnapshots ? '' : 'none';
        document.getElementById('tk-reply-bar').style.display = (t.status === 'awaiting_user') ? 'flex' : 'none';

        body.innerHTML = tkRenderMessages(t.messages || [], t.status);
        body.scrollTop = body.scrollHeight;

        // Re-Highlight in History
        const row = document.querySelector(`.tk-task[onclick*="tkOpenTask(${taskId})"]`);
        if (row) row.classList.add('active');
    } catch (e) {
        body.innerHTML = '<div style="color:#dc2626;padding:1rem;">' + tkEsc(e.message) + '</div>';
    }
};

function tkRenderMessages(messages, status) {
    if (!messages.length) return '<div style="text-align:center;color:#94a3b8;padding:1rem;">Noch keine Nachrichten.</div>';
    let html = '';
    messages.forEach(m => {
        if (m.kind === 'user') {
            html += `<div class="tk-msg user">
                <div class="tk-msg-head">Du</div>
                <div class="tk-msg-content">${tkEsc(m.text || '')}</div>
            </div>`;
        } else if (m.kind === 'assistant') {
            const isAsk = (m.text || '').endsWith('?') && status === 'awaiting_user';
            html += `<div class="tk-msg assistant ${isAsk ? 'ask' : ''}">
                <div class="tk-msg-content">${tkEsc(m.text || '')}</div>
            </div>`;
        } else if (m.kind === 'tool_call') {
            const argStr = JSON.stringify(m.args || {}, null, 0).substring(0, 200);
            html += `<div class="tk-msg tool">
                <span class="tk-msg-tool-name">⚙ ${tkEsc(m.tool)}</span>
                <span style="color:#94a3b8;">(${tkEsc(argStr)})</span>
            </div>`;
        } else if (m.kind === 'tool_result') {
            const cls = m.is_error ? 'error' : '';
            html += `<div class="tk-msg tool ${cls}">
                <div class="tk-msg-content">${m.is_error ? '✕ ' : '✓ '}${tkEsc((m.text || '').substring(0, 800))}</div>
            </div>`;
        }
    });
    return html;
}

window.tkRollback = async function(id) {
    if (!confirm('Diese Task wirklich zurückrollen?')) return;
    try {
        const r = await App.post('/admin/tasks/' + id + '/rollback', {});
        if (!r.success) throw new Error(r.message);
        App.showNotification(`Zurückgerollt: ${r.data.restored} Dateien`, 'success');
        tkLoadHistory();
        tkOpenTask(id);
    } catch (e) { App.showNotification(e.message, 'error'); }
};

window.tkOpenDiff = async function(taskId) {
    const overlay = document.getElementById('tk-diff-overlay');
    const body = document.getElementById('tk-diff-body');
    const title = document.getElementById('tk-diff-title');
    overlay.classList.add('open');
    body.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:2rem;">Lade…</div>';
    try {
        const r = await App.get('/admin/tasks/' + taskId);
        if (!r.success) throw new Error(r.message);
        const t = r.data;
        title.textContent = `Task #${t.id} · ${t.snapshots?.length || 0} Datei(en)`;
        if (!t.snapshots || !t.snapshots.length) {
            body.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:1rem;">Keine Snapshots.</div>';
            return;
        }
        body.innerHTML = t.snapshots.map(s => `
            <div class="tk-diff-file">
                <div class="tk-diff-file-head">
                    <span class="material-symbols-rounded" style="font-size:16px;">edit_note</span>
                    <span style="flex:1;">${tkEsc(s.relative_path)}</span>
                    <span class="tk-diff-file-op">${s.operation}</span>
                    ${s.rolled_back_at ? '<span class="tk-diff-file-op" style="background:#dc2626;">rolled back</span>' : ''}
                </div>
                <div class="tk-diff-panels">
                    <div>
                        <div class="tk-diff-panel-label">Vorher${s.original_content ? ' · ' + s.original_content.length + ' Zeichen' : ''}</div>
                        <div class="tk-diff-panel old">${tkEsc(s.original_content || (s.file_existed == 1 ? '(leer)' : '(Datei existierte nicht)'))}</div>
                    </div>
                    <div>
                        <div class="tk-diff-panel-label">Nachher${s.new_content ? ' · ' + s.new_content.length + ' Zeichen' : ''}${s.rolled_back_at ? ' · zurückgerollt' : ''}</div>
                        <div class="tk-diff-panel new">${tkEsc(s.new_content || (s.operation === 'delete' ? '(gelöscht)' : '(leer)'))}</div>
                    </div>
                </div>
            </div>
        `).join('');
    } catch (e) {
        body.innerHTML = '<div style="color:#dc2626;padding:1rem;">' + tkEsc(e.message) + '</div>';
    }
};

window.tkCloseDiff = function() {
    document.getElementById('tk-diff-overlay').classList.remove('open');
};

// Init
waitForApp(() => { tkLoadModules(); tkLoadHistory(); });
</script>
