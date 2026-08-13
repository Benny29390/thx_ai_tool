<style>
/* KI-Kompass nutzt jetzt das gleiche Wrapper- und Token-System wie Chat / Kunden.
   Die alten --cw-*-Variablen sind durch die globalen THX-Tokens ersetzt. */
:root {
    --cw-bg: #fff;
    --cw-bg-panel: var(--slate-50);
    --cw-bg-elevated: var(--slate-100);
    --cw-border: var(--slate-200);
    --cw-border-hover: var(--slate-300);
    --cw-text: var(--slate-900);
    --cw-text-muted: var(--slate-500);
    --cw-text-dim: var(--slate-400);
    --cw-accent: var(--thoxan-600);
    --cw-accent-green: var(--emerald-600);
    --cw-accent-yellow: var(--amber-600);
    --cw-accent-red: var(--rose-600);
    --cw-accent-purple: var(--indigo-600);
    --cw-mono: var(--font-mono);
}
.cw-page {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: var(--d-gutter);
    height: calc(100vh - var(--topbar-h) - 2 * var(--d-gutter));
    min-height: 480px;
    background: transparent;
    color: var(--slate-900);
    overflow: visible;
    font-size: var(--d-fs-sm);
}
.cw-sidebar,
.cw-main {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    overflow: hidden;
}
.cw-sidebar {
    background: var(--cw-bg-panel);
    border-right: 1px solid var(--cw-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.cw-sidebar-header {
    padding: var(--d-gutter);
    border-bottom: 1px solid var(--cw-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.cw-sidebar-title {
    font-weight: 600;
    color: var(--cw-text);
    display: flex;
    align-items: center;
    gap: 8px;
}
.cw-new-btn {
    background: var(--cw-accent);
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.cw-new-btn:hover { background: #4a96ee; }
.cw-sidebar-filter {
    padding: 8px 12px;
    border-bottom: 1px solid var(--cw-border);
    display: flex;
    gap: 6px;
}
.cw-filter-btn {
    background: transparent;
    color: var(--cw-text-muted);
    border: 1px solid var(--cw-border);
    padding: 4px 10px;
    font-size: 12px;
    border-radius: 14px;
    cursor: pointer;
}
.cw-filter-btn.active {
    background: var(--cw-accent);
    color: #fff;
    border-color: var(--cw-accent);
}
.cw-sessions { flex: 1; overflow-y: auto; padding: 8px; }
.cw-session-item {
    padding: 10px 12px;
    border-radius: 8px;
    cursor: pointer;
    margin-bottom: 4px;
    border: 1px solid transparent;
    transition: background 0.1s;
}
.cw-session-item:hover { background: var(--cw-bg-elevated); }
.cw-session-item.active { background: var(--cw-bg-elevated); border-color: var(--cw-border-hover); }
.cw-session-title {
    color: var(--cw-text);
    font-weight: 500;
    font-size: 13px;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 6px;
}
.cw-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.cw-status-dot.active { background: var(--cw-accent-green); animation: cw-pulse 1.4s infinite; }
.cw-status-dot.idle { background: var(--cw-text-dim); }
.cw-status-dot.awaiting_user { background: var(--cw-accent-yellow); }
.cw-status-dot.failed { background: var(--cw-accent-red); }
.cw-status-dot.closed { background: var(--cw-text-dim); opacity: 0.5; }
@keyframes cw-pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
.cw-session-meta {
    color: var(--cw-text-muted);
    font-size: 11px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cw-session-cost { color: var(--cw-accent-green); font-family: var(--cw-mono); }

.cw-main { display: flex; flex-direction: column; overflow: hidden; }
.cw-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--cw-text-muted);
    flex-direction: column;
    gap: 16px;
    padding: 40px;
    text-align: center;
}
.cw-empty .material-symbols-rounded { font-size: 64px; color: var(--cw-text-dim); }
.cw-empty h2 { color: var(--cw-text); margin: 0; font-size: var(--d-fs-xl); font-weight: 600; }
.cw-empty p { margin: 0; max-width: 480px; line-height: 1.6; }

.cw-chat-header {
    padding: 12px 20px;
    border-bottom: 1px solid var(--cw-border);
    background: var(--cw-bg-panel);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.cw-chat-title {
    font-weight: 600;
    flex: 1;
    min-width: 200px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
}
.cw-chat-title:hover { background: var(--cw-bg-elevated); }
.cw-chat-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--cw-text-muted);
    font-size: 12px;
}
.cw-cost {
    font-family: var(--cw-mono);
    color: var(--cw-accent-green);
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
}
.cw-cost:hover { background: var(--cw-bg-elevated); }
.cw-cost.over { color: var(--cw-accent-red); }
.cw-chat-actions { display: flex; gap: 6px; }
.cw-btn-icon {
    background: transparent;
    color: var(--cw-text-muted);
    border: 1px solid var(--cw-border);
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.cw-btn-icon:hover { color: var(--cw-text); border-color: var(--cw-border-hover); }
.cw-btn-icon.danger:hover { color: var(--cw-accent-red); border-color: var(--cw-accent-red); }

.cw-banner {
    padding: 10px 20px;
    background: rgba(247, 138, 8, 0.1);
    border-bottom: 1px solid rgba(247, 138, 8, 0.3);
    color: #f0b072;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.cw-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.cw-msg {
    max-width: 100%;
    line-height: 1.55;
    word-wrap: break-word;
}
.cw-msg-user {
    background: var(--cw-bg-elevated);
    border: 1px solid var(--cw-border);
    border-radius: 10px;
    padding: 10px 14px;
    margin-left: auto;
    max-width: 80%;
    color: var(--cw-text);
}
.cw-msg-user .cw-msg-label { color: var(--cw-accent); font-weight: 600; font-size: 12px; margin-bottom: 4px; }
.cw-msg-assistant { color: var(--cw-text); }
.cw-msg-assistant .cw-msg-label {
    color: var(--cw-accent-green);
    font-weight: 600;
    font-size: 12px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.cw-msg-assistant pre {
    background: var(--cw-bg-panel);
    border: 1px solid var(--cw-border);
    border-radius: 6px;
    padding: 10px 12px;
    overflow-x: auto;
    font-family: var(--cw-mono);
    font-size: 12.5px;
    margin: 8px 0;
}
.cw-msg-assistant code {
    background: var(--cw-bg-panel);
    padding: 1px 5px;
    border-radius: 3px;
    font-family: var(--cw-mono);
    font-size: 12.5px;
}
.cw-msg-assistant pre code { background: transparent; padding: 0; }
.cw-msg-rollback-btn {
    margin-left: 10px;
    background: transparent;
    color: var(--cw-text-dim);
    border: 1px solid var(--cw-border);
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 11px;
    cursor: pointer;
}
.cw-msg-rollback-btn:hover { color: var(--cw-accent-red); border-color: var(--cw-accent-red); }

/* Datei-Änderungen direkt an der Assistant-Message */
.cw-msg-files {
    margin-top: 8px;
    background: var(--cw-bg-panel);
    border: 1px solid var(--cw-border);
    border-radius: 6px;
    overflow: hidden;
}
.cw-msg-files-head {
    padding: 6px 12px;
    font-size: 11px;
    color: var(--cw-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: var(--cw-bg-elevated);
    border-bottom: 1px solid var(--cw-border);
    display: flex;
    align-items: center;
    gap: 6px;
}
.cw-file-row {
    padding: 6px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--cw-mono);
    font-size: 12px;
    border-bottom: 1px solid var(--cw-border);
}
.cw-file-row:last-child { border-bottom: none; }
.cw-file-row.rolled-back .cw-file-path { text-decoration: line-through; color: var(--cw-text-dim); }
.cw-file-row.rolled-back .cw-file-op { background: rgba(248, 81, 73, 0.15); color: var(--cw-accent-red); }
.cw-file-op {
    background: rgba(63, 185, 80, 0.15);
    color: var(--cw-accent-green);
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 10px;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.04em;
    flex-shrink: 0;
}
.cw-file-op.create { background: rgba(88, 166, 255, 0.15); color: var(--cw-accent); }
.cw-file-op.delete { background: rgba(248, 81, 73, 0.15); color: var(--cw-accent-red); }
.cw-file-op.bash { background: rgba(163, 113, 247, 0.15); color: var(--cw-accent-purple); }
.cw-file-path { flex: 1; color: var(--cw-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cw-file-size { color: var(--cw-text-dim); font-size: 11px; }
.cw-file-rolled-tag {
    color: var(--cw-accent-red);
    font-size: 10px;
    font-style: italic;
    margin-left: 6px;
}

/* Assistant-Message wenn ALLES rückgerollt ist */
.cw-msg-assistant.cw-msg-fully-rolled-back {
    opacity: 0.55;
}
.cw-msg-assistant.cw-msg-fully-rolled-back .cw-msg-label::after {
    content: "↩ zurückgerollt";
    margin-left: 8px;
    padding: 1px 6px;
    background: rgba(248, 81, 73, 0.15);
    color: var(--cw-accent-red);
    border-radius: 3px;
    font-size: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* File-Counter im Header */
.cw-files-counter {
    background: var(--cw-bg-elevated);
    border: 1px solid var(--cw-border);
    padding: 4px 10px;
    border-radius: 14px;
    font-size: 12px;
    color: var(--cw-text-muted);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.cw-files-counter:hover { color: var(--cw-text); border-color: var(--cw-border-hover); }
.cw-files-counter .cw-files-count { color: var(--cw-accent-green); font-weight: 600; font-family: var(--cw-mono); }
.cw-files-counter .cw-files-rolled { color: var(--cw-accent-red); font-family: var(--cw-mono); }

/* Files-Panel (Modal-style Liste aller Snapshots) */
.cw-files-panel-list {
    padding: 12px;
    max-height: 70vh;
    overflow-y: auto;
}
.cw-files-panel-row {
    padding: 10px 12px;
    border: 1px solid var(--cw-border);
    border-radius: 6px;
    margin-bottom: 8px;
    background: var(--cw-bg);
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: var(--cw-mono);
    font-size: 12.5px;
}
.cw-files-panel-row.rolled-back { opacity: 0.6; }
.cw-files-panel-row.rolled-back .cw-file-path { text-decoration: line-through; }
.cw-files-panel-time { color: var(--cw-text-dim); font-size: 11px; flex-shrink: 0; }

.cw-tool-call {
    background: var(--cw-bg-panel);
    border: 1px solid var(--cw-border);
    border-radius: 6px;
    margin: 4px 0;
    font-family: var(--cw-mono);
    font-size: 12px;
}
.cw-tool-head {
    padding: 8px 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    user-select: none;
}
.cw-tool-head:hover { background: var(--cw-bg-elevated); }
.cw-tool-name { color: var(--cw-accent-purple); font-weight: 600; }
.cw-tool-summary { color: var(--cw-text-muted); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cw-tool-status { font-size: 11px; padding: 1px 6px; border-radius: 3px; }
.cw-tool-status.ok { background: rgba(63, 185, 80, 0.15); color: var(--cw-accent-green); }
.cw-tool-status.error { background: rgba(248, 81, 73, 0.15); color: var(--cw-accent-red); }
.cw-tool-status.running { background: rgba(88, 166, 255, 0.15); color: var(--cw-accent); }
.cw-tool-body {
    border-top: 1px solid var(--cw-border);
    padding: 10px 12px;
    max-height: 400px;
    overflow-y: auto;
    background: var(--cw-bg);
}
.cw-tool-body pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; font-size: 11.5px; line-height: 1.5; color: var(--cw-text); }
.cw-tool-body .cw-tool-args { color: var(--cw-text-muted); margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid var(--cw-border); }
.cw-tool-diff-btn {
    margin-top: 8px;
    background: transparent;
    color: var(--cw-accent);
    border: 1px solid var(--cw-border);
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
}
.cw-tool-diff-btn:hover { background: var(--cw-bg-elevated); }

.cw-input-wrap {
    border-top: 1px solid var(--cw-border);
    background: var(--cw-bg-panel);
    padding: 14px 20px;
}
.cw-input-wrap.awaiting { background: rgba(210, 153, 34, 0.08); border-top-color: rgba(210, 153, 34, 0.4); }
.cw-input-wrap.awaiting::before {
    content: "🟡 KI wartet auf deine Antwort";
    display: block;
    color: var(--cw-accent-yellow);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
}
.cw-input-row {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}
.cw-input {
    flex: 1;
    background: var(--cw-bg);
    color: var(--cw-text);
    border: 1px solid var(--cw-border);
    border-radius: 8px;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 14px;
    resize: none;
    min-height: 40px;
    max-height: 200px;
    line-height: 1.5;
}
.cw-input:focus { outline: none; border-color: var(--cw-accent); }
.cw-input:disabled { opacity: 0.5; cursor: not-allowed; }
.cw-send {
    background: var(--cw-accent);
    color: #fff;
    border: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    height: 40px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.cw-send:hover { background: #4a96ee; }
.cw-send:disabled { opacity: 0.5; cursor: not-allowed; }
.cw-input-hint { font-size: 11px; color: var(--cw-text-dim); margin-top: 6px; }

.cw-modal-bg {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.cw-modal-bg.show { display: flex; }
.cw-modal {
    background: var(--cw-bg-panel);
    border: 1px solid var(--cw-border);
    border-radius: 10px;
    max-width: 95vw;
    max-height: 90vh;
    width: 1100px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.cw-modal-head {
    padding: 12px 18px;
    border-bottom: 1px solid var(--cw-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: var(--cw-text);
    font-family: var(--cw-mono);
    font-size: 13px;
}
.cw-modal-close { background: transparent; border: none; color: var(--cw-text-muted); cursor: pointer; font-size: 24px; line-height: 1; padding: 0 8px; }
.thx-modal-body { flex: 1; overflow: auto; padding: 0; }
.cw-diff {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    height: 100%;
}
.cw-diff-side {
    overflow: auto;
    padding: 12px;
    border-right: 1px solid var(--cw-border);
}
.cw-diff-side:last-child { border-right: none; }
.cw-diff-side h4 { margin: 0 0 8px; color: var(--cw-text-muted); font-size: 12px; font-weight: 600; text-transform: uppercase; }
.cw-diff-side pre {
    margin: 0;
    font-family: var(--cw-mono);
    font-size: 12px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-all;
    color: var(--cw-text);
}

.cw-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--cw-bg-elevated);
    color: var(--cw-text);
    border: 1px solid var(--cw-border);
    border-radius: 8px;
    padding: 12px 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    z-index: 99999;
    transform: translateY(100px);
    transition: transform 0.2s;
}
.cw-toast.show { transform: translateY(0); }
.cw-toast.error { border-color: var(--cw-accent-red); color: var(--cw-accent-red); }

.cw-user-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #58a6ff, #a371f7);
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}
.cw-user-chip-abbr {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
}
</style>

<div class="cw-page">

    <!-- Sidebar: Session-Liste -->
    <aside class="cw-sidebar">
        <div class="cw-sidebar-header">
            <h2 class="thx-page-title" style="font-size:var(--d-fs-base);margin:0;display:flex;align-items:center;gap:8px;">
                <span class="material-symbols-rounded" style="color:var(--thoxan-600);">terminal</span>
                Sessions
            </h2>
            <button class="thx-btn thx-btn-primary thx-btn-small" onclick="cwNewSession()">
                <span class="material-symbols-rounded" style="font-size:16px;">add</span>
                Neu
            </button>
        </div>
        <div class="cw-sidebar-filter">
            <button class="thx-chip is-active" data-filter="all" onclick="cwSetFilter('all')">Alle</button>
            <button class="thx-chip" data-filter="mine" onclick="cwSetFilter('mine')">Meine</button>
            <button class="thx-chip" data-filter="active" onclick="cwSetFilter('active')">Aktiv</button>
        </div>
        <div class="cw-sessions" id="cw-sessions-list">
            <div style="text-align:center;color:#6e7681;padding:20px;">Lädt…</div>
        </div>
    </aside>

    <!-- Main: Chat-View -->
    <main class="cw-main" id="cw-main">
        <div class="cw-empty" id="cw-empty">
            <span class="material-symbols-rounded">smart_toy</span>
            <h2>KI-Coworker — Chat-Modus</h2>
            <p>
                Freier Dialog mit Claude. Persistenter Verlauf, volle Shell-Power, automatische Snapshots vor jeder Datei-Änderung.<br><br>
                Anders als der <a href="/admin/tasks" style="color:#58a6ff;">Auftrags-Modus</a>: hier ist der KI alles erlaubt was nicht in der harten Sperre steht (config/config.php, sql/, vendor/, …). Bash umgeht die write_file-Whitelist — sei vorsichtig.
            </p>
            <button class="thx-btn thx-btn-primary thx-btn-small" onclick="cwNewSession()" style="padding:10px 20px;font-size:14px;">
                <span class="material-symbols-rounded" style="font-size:18px;">add</span>
                Neue Session starten
            </button>
        </div>
        <div id="cw-session-view" style="display:none;flex:1;flex-direction:column;overflow:hidden;"></div>
    </main>
</div>

<!-- Diff-Modal -->
<div class="thx-modal-backdrop" id="cw-diff-modal" style="display:none;" onclick="if(event.target===this)cwCloseDiff()">
    <div class="thx-modal">
        <div class="thx-modal-header">
            <span id="cw-diff-title">Diff</span>
            <button class="thx-modal-close" onclick="cwCloseDiff()">&times;</button>
        </div>
        <div class="thx-modal-body">
            <div class="cw-diff">
                <div class="cw-diff-side">
                    <h4>Vorher</h4>
                    <pre id="cw-diff-before"></pre>
                </div>
                <div class="cw-diff-side">
                    <h4>Nachher</h4>
                    <pre id="cw-diff-after"></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="cw-toast" id="cw-toast"></div>

<script>
'use strict';
const cwState = {
    sessions: [],
    activeSessionId: null,
    activeSession: null,
    filter: 'all',
    eventSource: null,
    /** Map<tool_use_id, {tool, args, status, output}> */
    toolCalls: new Map(),
};

function cwToast(text, isError) {
    const t = document.getElementById('cw-toast');
    t.textContent = text;
    t.classList.toggle('error', !!isError);
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function cwEscape(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function cwFormatCost(cost, cap) {
    const c = Number(cost || 0);
    const out = c >= 1 ? c.toFixed(2) : c.toFixed(4);
    if (cap !== null && cap !== undefined) {
        return `$${out} / $${Number(cap).toFixed(2)}`;
    }
    return `$${out}`;
}

function cwStatusLabel(status) {
    return {
        active: 'aktiv',
        idle: 'idle',
        awaiting_user: 'wartet auf Antwort',
        failed: 'Fehler',
        closed: 'geschlossen',
    }[status] || status;
}

// ====== Sessions ======
async function cwLoadSessions() {
    const params = new URLSearchParams();
    if (cwState.filter === 'mine') params.set('mine', '1');
    if (cwState.filter === 'active') params.set('status', 'active');
    try {
        const r = await fetch('/api/v1/admin/coworker/sessions?' + params.toString());
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        cwState.sessions = j.data.sessions || [];
        cwRenderSessions();
    } catch (e) { cwToast('Sessions laden fehlgeschlagen: ' + e.message, true); }
}

function cwRenderSessions() {
    const wrap = document.getElementById('cw-sessions-list');
    if (!cwState.sessions.length) {
        wrap.innerHTML = '<div style="text-align:center;color:#6e7681;padding:20px;font-size:13px;">Noch keine Sessions.<br>„Neu" anklicken.</div>';
        return;
    }
    wrap.innerHTML = cwState.sessions.map(s => {
        const isActive = s.id === cwState.activeSessionId;
        const title = s.title || `Session #${s.id}`;
        return `
        <div class="cw-session-item ${isActive ? 'active' : ''}" onclick="cwOpenSession(${s.id})">
            <div class="cw-session-title">
                <span class="cw-status-dot ${s.status}"></span>
                ${cwEscape(title)}
            </div>
            <div class="cw-session-meta">
                <span>${cwEscape(s.created_by_name || '')}</span>
                <span class="cw-session-cost">${cwFormatCost(s.cost_usd, s.cost_cap_usd)}</span>
                <span>${s.messages_count || 0} msg</span>
            </div>
        </div>`;
    }).join('');
}

function cwSetFilter(f) {
    cwState.filter = f;
    document.querySelectorAll('.cw-filter-btn').forEach(b => b.classList.toggle('active', b.dataset.filter === f));
    cwLoadSessions();
}

async function cwNewSession() {
    try {
        const r = await fetch('/api/v1/admin/coworker/sessions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        await cwLoadSessions();
        cwOpenSession(j.data.id);
        cwToast('Neue Session erstellt');
    } catch (e) { cwToast('Anlegen fehlgeschlagen: ' + e.message, true); }
}

async function cwOpenSession(id) {
    cwCloseStream();
    cwState.activeSessionId = id;
    cwState.toolCalls = new Map();
    document.getElementById('cw-empty').style.display = 'none';
    const view = document.getElementById('cw-session-view');
    view.style.display = 'flex';
    view.innerHTML = '<div style="flex:1;display:flex;align-items:center;justify-content:center;color:#8b949e;">Lädt…</div>';

    try {
        const r = await fetch('/api/v1/admin/coworker/sessions/' + id);
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        cwState.activeSession = j.data;
        cwRenderSession();
        cwRenderSessions(); // active-Highlight aktualisieren
    } catch (e) { cwToast('Session laden fehlgeschlagen: ' + e.message, true); }
}

function cwRenderSession() {
    const s = cwState.activeSession;
    if (!s) return;
    const view = document.getElementById('cw-session-view');

    // Tool-Calls + Tool-Results aus messages zusammenführen
    const toolCalls = new Map();
    (s.messages || []).forEach(m => {
        if (m.kind === 'tool_call') {
            toolCalls.set(m.tool_use_id, { tool: m.tool, args: m.args, status: 'running', output: '', is_error: false, message_id: m.message_id });
        } else if (m.kind === 'tool_result' && toolCalls.has(m.tool_use_id)) {
            const tc = toolCalls.get(m.tool_use_id);
            tc.output = m.text;
            tc.is_error = m.is_error;
            tc.status = m.is_error ? 'error' : 'ok';
        }
    });
    cwState.toolCalls = toolCalls;

    // Snapshots nach message_id gruppieren (für Inline-File-Listen)
    const snapshotsByMsg = {};
    (s.snapshots || []).forEach(sn => {
        const k = sn.message_id || 0;
        if (!snapshotsByMsg[k]) snapshotsByMsg[k] = [];
        snapshotsByMsg[k].push(sn);
    });

    const messages = (s.messages || []).map(m => cwRenderMessage(m, toolCalls, snapshotsByMsg)).filter(Boolean);

    // Counter
    const totalSnaps = (s.snapshots || []).length;
    const rolledSnaps = (s.snapshots || []).filter(sn => sn.rolled_back_at).length;
    const activeSnaps = totalSnaps - rolledSnaps;

    const cap = s.cost_cap_usd !== null ? Number(s.cost_cap_usd) : null;
    const cost = Number(s.cost_usd || 0);
    const costOver = cap !== null && cost >= cap;

    const isAwaiting = s.status === 'awaiting_user';
    const isActive = s.status === 'active';
    const isClosed = s.status === 'closed';

    view.innerHTML = `
        <div class="thx-page-header" style="margin:0;padding:12px 16px;border-bottom:1px solid var(--slate-200);">
            <div>
                <h1 class="thx-page-title" style="font-size:var(--d-fs-base);cursor:pointer;" onclick="cwEditTitle()" title="Klicken zum Umbenennen">
                    <span class="cw-status-dot ${s.status}" style="display:inline-block;margin-right:6px;"></span>
                    ${cwEscape(s.title || `Session #${s.id}`)}
                </h1>
                <div class="thx-page-subtitle" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                    <span class="thx-chip">
                        <span class="cw-user-chip-abbr">${cwEscape(s.created_by_abbr || (s.created_by_name||'?').substr(0,1))}</span>
                        ${cwEscape(s.created_by_name || 'Admin')}
                    </span>
                    <span class="thx-chip">${cwStatusLabel(s.status)}</span>
                    <span class="thx-chip ${costOver ? 'cw-cost-over' : ''}" onclick="cwEditCap()" style="cursor:pointer;" title="Klicken zum Ändern">
                        ${cwFormatCost(cost, cap)}
                    </span>
                    ${totalSnaps > 0 ? `
                    <span class="thx-chip" onclick="cwShowFilesPanel()" style="cursor:pointer;" title="Alle geänderten Dateien anzeigen">
                        <span class="material-symbols-rounded" style="font-size:14px;">edit_note</span>
                        ${activeSnaps}${rolledSnaps > 0 ? ` · ${rolledSnaps} ↩` : ''} Datei${totalSnaps === 1 ? '' : 'en'}
                    </span>` : ''}
                </div>
            </div>
            <div class="thx-page-actions">
                ${isActive ? `<button class="thx-btn thx-btn-danger thx-btn-small" onclick="cwStopSession()">
                    <span class="material-symbols-rounded" style="font-size:14px;">stop_circle</span> Stop
                </button>` : ''}
                ${!isClosed ? `<button class="thx-btn thx-btn-danger thx-btn-small" onclick="cwRollbackSession()">
                    <span class="material-symbols-rounded" style="font-size:14px;">undo</span> Rollback alles
                </button>` : ''}
                <button class="thx-icon-btn" onclick="cwOpenSession(${s.id})">
                    <span class="material-symbols-rounded" style="font-size:14px;">refresh</span>
                </button>
                ${!isClosed ? `<button class="thx-icon-btn" onclick="cwCloseSession()">
                    <span class="material-symbols-rounded" style="font-size:14px;">close</span>
                </button>` : ''}
            </div>
        </div>
        <div class="cw-banner">
            <span class="material-symbols-rounded" style="font-size:16px;">warning</span>
            Volle Shell aktiv — Bash-Befehle können Dateien außerhalb der Whitelist verändern.
            Snapshots fangen das Meiste, aber nicht alles.
        </div>
        <div class="cw-messages" id="cw-messages">
            ${messages.join('')}
            ${messages.length === 0 ? '<div style="color:#6e7681;text-align:center;padding:40px;">Schreib unten was die KI tun soll.</div>' : ''}
        </div>
        <div class="cw-input-wrap ${isAwaiting ? 'awaiting' : ''}" id="cw-input-wrap">
            <div class="cw-input-row">
                <textarea
                    class="thx-input"
                    id="cw-input"
                    placeholder="${isAwaiting ? 'Deine Antwort an die KI…' : (isClosed ? 'Session ist geschlossen.' : (isActive ? 'KI arbeitet — warte oder klick Stop.' : 'Was soll die KI tun?'))}"
                    rows="1"
                    ${(isActive || isClosed) ? 'disabled' : ''}
                    onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();cwSend();} else if(event.key==='Enter' && event.shiftKey){return;}"
                    oninput="cwAutoSize(this)"
                ></textarea>
                <button class="thx-btn thx-btn-primary" id="cw-send-btn" onclick="cwSend()" ${(isActive || isClosed) ? 'disabled' : ''}>
                    <span class="material-symbols-rounded" style="font-size:18px;">${isAwaiting ? 'reply' : 'arrow_upward'}</span>
                    ${isAwaiting ? 'Antworten' : 'Senden'}
                </button>
            </div>
            <div class="cw-input-hint">Enter = senden · Shift+Enter = Zeilenumbruch · max 60 Tool-Calls/Turn</div>
        </div>
    `;
    setTimeout(() => {
        const m = document.getElementById('cw-messages');
        if (m) m.scrollTop = m.scrollHeight;
        const inp = document.getElementById('cw-input');
        if (inp && !inp.disabled) inp.focus();
    }, 50);
}

function cwAutoSize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(200, el.scrollHeight) + 'px';
}

function cwRenderMessage(m, toolCalls, snapshotsByMsg) {
    if (m.kind === 'user') {
        return `<div class="cw-msg cw-msg-user">
            <div class="cw-msg-label">Du</div>
            <div>${cwEscape(m.text).replace(/\n/g, '<br>')}</div>
        </div>`;
    }
    if (m.kind === 'assistant') {
        const rollback = m.message_id ? `<button class="thx-btn thx-btn-secondary thx-btn-small" onclick="cwRollbackMessage(${m.message_id})" title="Diese Antwort und alle späteren zurückrollen">↩ rollback</button>` : '';
        const snaps = (snapshotsByMsg && snapshotsByMsg[m.message_id]) || [];
        const allRolledBack = snaps.length > 0 && snaps.every(s => s.rolled_back_at);
        const fileList = snaps.length > 0 ? cwRenderFileList(snaps) : '';
        return `<div class="cw-msg cw-msg-assistant ${allRolledBack ? 'cw-msg-fully-rolled-back' : ''}">
            <div class="cw-msg-label">
                <span class="material-symbols-rounded" style="font-size:14px;">smart_toy</span>
                Claude
                ${rollback}
            </div>
            <div>${cwFormatAssistantText(m.text)}</div>
            ${fileList}
        </div>`;
    }
    if (m.kind === 'tool_call') {
        return cwRenderToolCall(m.tool_use_id, m.tool, m.args, toolCalls.get(m.tool_use_id) || {});
    }
    // tool_result: bereits in tool_call eingebaut
    return '';
}

function cwRenderFileList(snaps) {
    const rows = snaps.map(s => {
        const op = s.source === 'bash' ? 'bash' : (s.operation || 'write');
        const opLabel = {write: 'write', create: 'neu', delete: 'gelöscht', bash_touch: 'bash', bash: 'bash'}[op] || op;
        const size = s.new_len ? `${s.new_len} B` : (s.orig_len ? `${s.orig_len} B` : '');
        const rolledTag = s.rolled_back_at ? `<span class="cw-file-rolled-tag">↩ rückgerollt ${s.rolled_back_at.replace(/\.\d+$/, '')}</span>` : '';
        return `<div class="cw-file-row ${s.rolled_back_at ? 'rolled-back' : ''}">
            <span class="cw-file-op ${op}">${opLabel}</span>
            <span class="cw-file-path">${cwEscape(s.relative_path)}</span>
            <span class="cw-file-size">${size}</span>
            ${rolledTag}
        </div>`;
    }).join('');
    return `<div class="cw-msg-files">
        <div class="cw-msg-files-head">
            <span class="material-symbols-rounded" style="font-size:14px;">edit_note</span>
            ${snaps.length} Datei${snaps.length === 1 ? '' : 'en'} geändert
        </div>
        ${rows}
    </div>`;
}

function cwFormatAssistantText(t) {
    // Sehr einfache Markdown-Substitution: Code-Blöcke + Inline-Code + Linebreaks
    let html = cwEscape(t);
    html = html.replace(/```([\s\S]*?)```/g, (m, c) => `<pre><code>${c}</code></pre>`);
    html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');
    html = html.replace(/\n/g, '<br>');
    return html;
}

function cwRenderToolCall(useId, tool, args, state) {
    const status = state.status || 'running';
    const summary = cwToolSummary(tool, args);
    const output = state.output || '';
    const isError = state.is_error;

    const hasDiff = tool === 'write_file' && args && args.path;
    const diffBtn = hasDiff ? `<button class="thx-btn thx-btn-secondary thx-btn-small" onclick="cwShowDiff('${cwEscape(args.path)}')">Diff anzeigen</button>` : '';

    return `<div class="cw-tool-call" data-use-id="${cwEscape(useId)}">
        <div class="cw-tool-head" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'">
            <span class="material-symbols-rounded" style="font-size:14px;color:#a371f7;">${cwToolIcon(tool)}</span>
            <span class="cw-tool-name">${cwEscape(tool)}</span>
            <span class="cw-tool-summary">${cwEscape(summary)}</span>
            <span class="cw-tool-status ${status}">${status}</span>
        </div>
        <div class="cw-tool-body" style="display:none;">
            <div class="cw-tool-args">Args: <code>${cwEscape(JSON.stringify(args))}</code></div>
            <pre>${cwEscape(output) || '<em>(läuft)</em>'}</pre>
            ${diffBtn}
        </div>
    </div>`;
}

function cwToolIcon(tool) {
    return {
        list_files: 'folder_open',
        read_file: 'description',
        search_code: 'search',
        write_file: 'edit_document',
        bash: 'terminal',
        ask_user: 'help',
        done: 'check_circle',
    }[tool] || 'build';
}

function cwToolSummary(tool, args) {
    if (!args) return '';
    if (tool === 'list_files') return args.directory;
    if (tool === 'read_file') return args.path;
    if (tool === 'search_code') return `"${args.query}"` + (args.directory ? ` in ${args.directory}` : '');
    if (tool === 'write_file') return args.path;
    if (tool === 'bash') return args.command;
    if (tool === 'ask_user') return args.question;
    if (tool === 'done') return args.summary;
    return JSON.stringify(args).slice(0, 80);
}

// ====== Senden + SSE ======
function cwCloseStream() {
    if (cwState.eventSource) {
        cwState.eventSource.close();
        cwState.eventSource = null;
    }
}

async function cwSend() {
    const inp = document.getElementById('cw-input');
    const text = (inp.value || '').trim();
    if (!text) return;
    if (!cwState.activeSessionId) return;
    const isAwaiting = cwState.activeSession && cwState.activeSession.status === 'awaiting_user';
    const endpoint = isAwaiting ? 'reply' : 'message';

    inp.value = '';
    cwAutoSize(inp);
    inp.disabled = true;
    document.getElementById('cw-send-btn').disabled = true;

    // Optimistic: User-Nachricht direkt anhängen
    const messagesEl = document.getElementById('cw-messages');
    if (messagesEl) {
        messagesEl.insertAdjacentHTML('beforeend', `<div class="cw-msg cw-msg-user">
            <div class="cw-msg-label">Du</div>
            <div>${cwEscape(text).replace(/\n/g, '<br>')}</div>
        </div>`);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }
    document.querySelector('.cw-status-dot.idle, .cw-status-dot.awaiting_user, .cw-status-dot.failed')?.classList.replace(
        cwState.activeSession.status, 'active'
    );

    try {
        const resp = await fetch(`/api/v1/admin/coworker/sessions/${cwState.activeSessionId}/${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text }),
        });
        if (!resp.ok || !resp.body) {
            const j = await resp.json().catch(() => null);
            throw new Error(j && j.message ? j.message : 'HTTP ' + resp.status);
        }
        cwState.activeSession.status = 'active';
        cwStreamSse(resp.body);
    } catch (e) {
        cwToast('Senden fehlgeschlagen: ' + e.message, true);
        inp.disabled = false;
        document.getElementById('cw-send-btn').disabled = false;
    }
}

async function cwStreamSse(body) {
    const reader = body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let currentAssistantText = '';
    let currentAssistantEl = null;

    const messagesEl = document.getElementById('cw-messages');

    const ensureAssistant = () => {
        if (currentAssistantEl) return;
        const html = `<div class="cw-msg cw-msg-assistant">
            <div class="cw-msg-label">
                <span class="material-symbols-rounded" style="font-size:14px;">smart_toy</span>
                Claude
            </div>
            <div class="cw-msg-body"></div>
        </div>`;
        messagesEl.insertAdjacentHTML('beforeend', html);
        currentAssistantEl = messagesEl.lastElementChild.querySelector('.cw-msg-body');
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const processEvent = (event, data) => {
        try {
            const d = data ? JSON.parse(data) : {};
            if (event === 'session_active') {
                // nichts spezielles
            } else if (event === 'assistant_message') {
                ensureAssistant();
                currentAssistantText += (currentAssistantText ? '\n\n' : '') + d.text;
                currentAssistantEl.innerHTML = cwFormatAssistantText(currentAssistantText);
                messagesEl.scrollTop = messagesEl.scrollHeight;
            } else if (event === 'tool_call') {
                currentAssistantEl = null;
                currentAssistantText = '';
                const useId = d.tool_use_id || 't' + Date.now() + Math.random();
                const html = cwRenderToolCall(useId, d.tool, d.args, { status: 'running' });
                messagesEl.insertAdjacentHTML('beforeend', html);
                messagesEl.scrollTop = messagesEl.scrollHeight;
                cwState.toolCalls.set(useId, { tool: d.tool, args: d.args, status: 'running', output: '' });
                cwState._lastUseId = useId;
            } else if (event === 'tool_result') {
                const useId = cwState._lastUseId;
                if (useId) {
                    const el = messagesEl.querySelector(`.cw-tool-call[data-use-id="${useId}"]`);
                    if (el) {
                        const statusEl = el.querySelector('.cw-tool-status');
                        statusEl.classList.remove('running');
                        statusEl.classList.add(d.ok ? 'ok' : 'error');
                        statusEl.textContent = d.ok ? 'ok' : 'error';
                        const preEl = el.querySelector('.cw-tool-body pre');
                        if (preEl) preEl.textContent = d.snippet || '';
                    }
                }
            } else if (event === 'awaiting_user') {
                ensureAssistant();
                currentAssistantEl.innerHTML += `<div style="margin-top:8px;padding:8px 12px;background:rgba(210,153,34,0.1);border-left:3px solid #d29922;border-radius:4px;"><strong>🟡 Rückfrage:</strong> ${cwEscape(d.question)}</div>`;
                cwState.activeSession.status = 'awaiting_user';
                messagesEl.scrollTop = messagesEl.scrollHeight;
            } else if (event === 'done') {
                ensureAssistant();
                currentAssistantEl.innerHTML += `<div style="margin-top:8px;padding:6px 10px;background:rgba(63,185,80,0.1);border-left:3px solid #3fb950;border-radius:4px;color:#3fb950;font-size:12px;"><strong>✓ Abgeschlossen.</strong> ${cwEscape(d.summary)}</div>`;
                cwState.activeSession.status = 'idle';
            } else if (event === 'idle') {
                cwState.activeSession.status = 'idle';
            } else if (event === 'cost_update') {
                cwState.activeSession.tokens_input = d.tokens_input;
                cwState.activeSession.tokens_output = d.tokens_output;
                cwState.activeSession.cost_usd = d.cost_usd;
                const costEl = document.querySelector('.cw-cost');
                const cap = cwState.activeSession.cost_cap_usd;
                if (costEl) {
                    costEl.textContent = cwFormatCost(d.cost_usd, cap);
                    costEl.classList.toggle('over', cap !== null && Number(d.cost_usd) >= Number(cap));
                }
            } else if (event === 'cost_cap_reached') {
                cwToast(`Cost-Cap erreicht: $${Number(d.cost_usd).toFixed(4)} / $${Number(d.cap).toFixed(2)}`, true);
            } else if (event === 'error') {
                cwToast('Fehler: ' + (d.message || 'unbekannt'), true);
                cwState.activeSession.status = 'failed';
            }
        } catch (e) {
            console.error('SSE parse', e, event, data);
        }
    };

    try {
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            let idx;
            while ((idx = buffer.indexOf('\n\n')) !== -1) {
                const block = buffer.slice(0, idx);
                buffer = buffer.slice(idx + 2);
                let evName = 'message';
                let dataStr = '';
                block.split('\n').forEach(line => {
                    if (line.startsWith('event: ')) evName = line.slice(7);
                    else if (line.startsWith('data: ')) dataStr += (dataStr ? '\n' : '') + line.slice(6);
                });
                if (evName) processEvent(evName, dataStr);
            }
        }
    } catch (e) {
        console.warn('SSE read error', e);
    } finally {
        // Reload Session-Detail (frische Messages aus DB)
        await cwOpenSession(cwState.activeSessionId);
        cwLoadSessions();
    }
}

// ====== Aktionen ======
async function cwStopSession() {
    if (!cwState.activeSessionId) return;
    try {
        await fetch(`/api/v1/admin/coworker/sessions/${cwState.activeSessionId}/stop`, { method: 'POST' });
        cwToast('Stop gesendet (Loop bricht nach aktueller Iteration ab)');
    } catch (e) { cwToast('Stop fehlgeschlagen: ' + e.message, true); }
}

async function cwRollbackSession() {
    if (!confirm('Wirklich ALLE Datei-Änderungen dieser Session zurückrollen?')) return;
    try {
        const r = await fetch(`/api/v1/admin/coworker/sessions/${cwState.activeSessionId}/rollback`, { method: 'POST' });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        cwToast(`${j.data.restored} Datei(en) wiederhergestellt`);
        cwOpenSession(cwState.activeSessionId);
    } catch (e) { cwToast('Rollback fehlgeschlagen: ' + e.message, true); }
}

async function cwRollbackMessage(messageId) {
    if (!confirm('Diese Antwort und alle späteren Datei-Änderungen zurückrollen?')) return;
    try {
        const r = await fetch(`/api/v1/admin/coworker/sessions/${cwState.activeSessionId}/messages/${messageId}/rollback`, { method: 'POST' });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        cwToast(`${j.data.restored} Datei(en) wiederhergestellt`);
        cwOpenSession(cwState.activeSessionId);
    } catch (e) { cwToast('Rollback fehlgeschlagen: ' + e.message, true); }
}

async function cwCloseSession() {
    if (!confirm('Session schließen? (Verlauf bleibt erhalten, aber nicht mehr in der Liste)')) return;
    try {
        await fetch(`/api/v1/admin/coworker/sessions/${cwState.activeSessionId}`, { method: 'DELETE' });
        cwState.activeSessionId = null;
        document.getElementById('cw-session-view').style.display = 'none';
        document.getElementById('cw-empty').style.display = 'flex';
        cwLoadSessions();
    } catch (e) { cwToast('Schließen fehlgeschlagen: ' + e.message, true); }
}

async function cwEditTitle() {
    if (!cwState.activeSession) return;
    const current = cwState.activeSession.title || '';
    const t = prompt('Session-Titel:', current);
    if (t === null || t === current) return;
    try {
        await fetch(`/api/v1/admin/coworker/sessions/${cwState.activeSessionId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title: t }),
        });
        cwState.activeSession.title = t;
        cwOpenSession(cwState.activeSessionId);
    } catch (e) { cwToast('Speichern fehlgeschlagen: ' + e.message, true); }
}

async function cwEditCap() {
    if (!cwState.activeSession) return;
    const current = cwState.activeSession.cost_cap_usd;
    const v = prompt('Cost-Cap in USD (leer = kein Limit):', current !== null ? String(current) : '');
    if (v === null) return;
    const cap = v.trim() === '' ? null : parseFloat(v);
    if (cap !== null && isNaN(cap)) { cwToast('Ungültige Zahl', true); return; }
    try {
        await fetch(`/api/v1/admin/coworker/sessions/${cwState.activeSessionId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cost_cap_usd: cap }),
        });
        cwState.activeSession.cost_cap_usd = cap;
        cwOpenSession(cwState.activeSessionId);
    } catch (e) { cwToast('Speichern fehlgeschlagen: ' + e.message, true); }
}

// ====== Diff-Modal ======
async function cwShowDiff(path) {
    if (!cwState.activeSession) return;
    const snap = (cwState.activeSession.snapshots || []).slice().reverse().find(s => s.relative_path === path);
    if (!snap) { cwToast('Kein Snapshot für ' + path, true); return; }
    document.getElementById('cw-diff-title').textContent = path;
    document.getElementById('cw-diff-modal').querySelector('.thx-modal-body').innerHTML = `
        <div class="cw-diff">
            <div class="cw-diff-side">
                <h4>Vorher</h4>
                <pre>Original: ${snap.orig_len || 0} Bytes\n(Inhalt-Vorschau im Browser deaktiviert — siehe DB-Tabelle coworker_snapshots für Vollinhalt)</pre>
            </div>
            <div class="cw-diff-side">
                <h4>Nachher</h4>
                <pre>Neu: ${snap.new_len || 0} Bytes${snap.rolled_back_at ? '\n(zurückgerollt am '+snap.rolled_back_at+')' : ''}</pre>
            </div>
        </div>`;
    document.getElementById('cw-diff-modal').classList.add('show');
}

function cwCloseDiff() {
    document.getElementById('cw-diff-modal').classList.remove('show');
}

function cwShowFilesPanel() {
    if (!cwState.activeSession) return;
    const snaps = (cwState.activeSession.snapshots || []).slice().reverse();
    if (!snaps.length) return;
    const rows = snaps.map(s => {
        const op = s.source === 'bash' ? 'bash' : (s.operation || 'write');
        const opLabel = {write: 'write', create: 'neu', delete: 'gelöscht', bash_touch: 'bash', bash: 'bash'}[op] || op;
        const time = s.applied_at ? s.applied_at.replace(/\.\d+$/, '').slice(11, 19) : '';
        const rolledInfo = s.rolled_back_at ? `<span class="cw-file-rolled-tag">↩ ${s.rolled_back_at.slice(11, 19)}</span>` : '';
        return `<div class="cw-files-panel-row ${s.rolled_back_at ? 'rolled-back' : ''}">
            <span class="cw-files-panel-time">${time}</span>
            <span class="cw-file-op ${op}">${opLabel}</span>
            <span class="cw-file-path">${cwEscape(s.relative_path)}</span>
            ${rolledInfo}
        </div>`;
    }).join('');
    document.getElementById('cw-diff-title').textContent = `Alle Datei-Änderungen dieser Session (${snaps.length})`;
    document.getElementById('cw-diff-modal').querySelector('.thx-modal-body').innerHTML = `<div class="cw-files-panel-list">${rows}</div>`;
    document.getElementById('cw-diff-modal').classList.add('show');
}

// ====== Init ======
document.addEventListener('DOMContentLoaded', () => {
    cwLoadSessions();
    setInterval(() => {
        // Sessions-Liste alle 8s refreshen, aber nur wenn keine Session offen ist oder die offene nicht gerade streamt
        if (!cwState.eventSource) cwLoadSessions();
    }, 8000);
});
</script>
