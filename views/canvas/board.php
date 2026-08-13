<?php
$db = \Core\Database::getInstance();
$userId = \Core\Auth::id();
$canvasId = (int) ($canvasId ?? 0);
$isAdmin = \Core\Auth::isAdmin();
$users = $db->query("SELECT id, name, email FROM users WHERE is_active = 1 ORDER BY name");
?>

<style>
/* ===== Layout: Sidebar + Chat als eigene Cards, gap = --d-gutter ===== */
.cv-board {
    display: flex;
    gap: var(--d-gutter);
    height: calc(100vh - var(--topbar-h) - 2 * var(--d-gutter));
    min-height: 480px;
    background: transparent;
    overflow: visible;
}
.cv-sidebar,
.cv-chat {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    overflow: hidden;
}

/* ===== LEFT: Canvas-Felder (kompakt) ===== */
.cv-sidebar {
    width: 340px;
    min-width: 340px;
    border-right: 1px solid var(--slate-200);
    display: flex;
    flex-direction: column;
    background: var(--slate-50);
    overflow: hidden;
}
.cv-sidebar-header {
    padding: var(--d-gutter);
    border-bottom: 1px solid var(--color-border, #e2e8f0);
    background: var(--color-bg-primary, #fff);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-shrink: 0;
}
.cv-sidebar-header-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}
.cv-back { color: var(--color-text-secondary, #64748b); text-decoration: none; display: flex; }
.cv-project-title {
    font-weight: 600;
    font-size: var(--d-fs-sm);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
}
.cv-project-title:hover { opacity: 0.7; }
.cv-sidebar-actions {
    display: flex;
    gap: 0.25rem;
    flex-shrink: 0;
}
.cv-icon-btn {
    background: none;
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 6px;
    padding: 0.25rem;
    cursor: pointer;
    color: var(--color-text-secondary, #64748b);
    display: flex;
    align-items: center;
    transition: all 0.15s;
}
.cv-icon-btn:hover {
    border-color: var(--color-primary, #004c9b);
    color: var(--color-primary, #004c9b);
    background: var(--color-bg-tertiary, #f1f5f9);
}
.cv-icon-btn .material-symbols-rounded { font-size: 16px; }

/* Export Dropdown */
.cv-export-dropdown { position: relative; }
.cv-export-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 4px;
    background: var(--color-bg-primary, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 50;
    min-width: 180px;
    overflow: hidden;
}
.cv-export-menu.open { display: block; }
.cv-export-menu button {
    display: block;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: none;
    background: none;
    text-align: left;
    font-size: var(--d-fs-sm);
    cursor: pointer;
    color: var(--color-text-primary, #1e293b);
    transition: background 0.1s;
}
.cv-export-menu button:hover { background: var(--color-bg-secondary, #f8fafc); }
.cv-export-menu button:first-child { font-weight: 600; }

/* Progress Bar */
.cv-progress {
    display: flex;
    height: 3px;
    flex-shrink: 0;
}
.cv-progress-seg { flex: 1; transition: background 0.3s; }
.cv-progress-seg.empty { background: var(--color-bg-tertiary, #f1f5f9); }
.cv-progress-seg.partial { background: #f59e0b; }
.cv-progress-seg.complete { background: #22c55e; }

/* Feld-Tabs */
.cv-fields {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
}
.cv-field {
    border-radius: 8px;
    margin-bottom: 0.375rem;
    overflow: hidden;
    border: 1px solid transparent;
    transition: border-color 0.15s;
}
.cv-field.active {
    border-color: var(--color-primary, #004c9b);
    background: var(--color-bg-primary, #fff);
}
.cv-field-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.625rem;
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.15s;
    user-select: none;
}
.cv-field-header:hover { background: var(--color-bg-primary, #fff); }
.cv-field.active .cv-field-header { background: var(--color-bg-primary, #fff); }
.cv-field-left {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: var(--d-fs-sm);
    font-weight: 500;
}
.cv-field-left .material-symbols-rounded { font-size: 16px; color: var(--color-text-secondary, #64748b); }
.cv-field-right {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.cv-field-badge {
    font-size: var(--d-fs-xs);
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    font-weight: 600;
}
.cv-field-badge.empty { background: #f1f5f9; color: #94a3b8; }
.cv-field-badge.partial { background: #fef3c7; color: #d97706; }
.cv-field-badge.complete { background: #dcfce7; color: #16a34a; }
.cv-field-count {
    font-size: var(--d-fs-xs);
    color: var(--color-text-tertiary, #94a3b8);
}

/* Karten im Feld */
.cv-field-cards {
    padding: 0 0.5rem 0.5rem;
    display: none;
}
.cv-field.active .cv-field-cards { display: block; }
.cv-card {
    background: var(--color-bg-primary, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 6px;
    padding: 0.5rem 0.625rem;
    margin-bottom: 0.375rem;
    cursor: pointer;
    transition: border-color 0.15s;
    font-size: var(--d-fs-sm);
}
.cv-card:hover { border-color: var(--color-primary, #004c9b); }
.cv-card-head {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.cv-dot {
    width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
}
.cv-dot.entwurf { background: #94a3b8; }
.cv-dot.in_arbeit { background: #f59e0b; }
.cv-dot.vollstaendig { background: #22c55e; }
.cv-card-title {
    font-weight: 500;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cv-card-excerpt {
    font-size: var(--d-fs-xs);
    color: var(--color-text-secondary, #64748b);
    margin-top: 0.25rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.cv-add-card {
    text-align: center;
    padding-top: 0.25rem;
}
.cv-add-card-btn {
    font-size: var(--d-fs-xs);
    color: var(--color-text-secondary, #64748b);
    background: none;
    border: 1px dashed var(--color-border, #e2e8f0);
    border-radius: 6px;
    padding: 0.3rem 0.75rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    transition: all 0.15s;
}
.cv-add-card-btn:hover {
    border-color: var(--color-primary, #004c9b);
    color: var(--color-primary, #004c9b);
}
.cv-add-card-btn .material-symbols-rounded { font-size: 14px; }

/* Readiness-Anzeige unten */
.cv-readiness {
    padding: 0.5rem 1rem;
    border-top: 1px solid var(--color-border, #e2e8f0);
    background: var(--color-bg-primary, #fff);
    text-align: center;
    font-size: var(--d-fs-sm);
    color: var(--color-text-secondary, #64748b);
    flex-shrink: 0;
}
.cv-readiness strong { color: var(--color-text-primary, #1e293b); }

/* ===== RIGHT: KI-Sparring Chat (Hauptbereich) ===== */
.cv-chat {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--color-bg-primary, #fff);
}
.cv-chat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.625rem 1.25rem;
    border-bottom: 1px solid var(--color-border, #e2e8f0);
    flex-shrink: 0;
}
.cv-chat-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: var(--d-fs-sm);
}
.cv-chat-title .material-symbols-rounded { font-size: 20px; color: var(--color-primary, #004c9b); }
.cv-chat-field-name {
    font-size: var(--d-fs-sm);
    padding: 0.15rem 0.5rem;
    background: #d6e7f5;
    color: #004c9b;
    border-radius: 4px;
    font-weight: 500;
}
.cv-chat-header-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.cv-participants {
    display: flex;
    gap: -4px;
}
.cv-avatar {
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--color-primary, #004c9b); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: var(--d-fs-xs); font-weight: 600;
    border: 2px solid var(--color-bg-primary, #fff);
    margin-left: -4px;
}
.cv-avatar:first-child { margin-left: 0; }

/* Chat Messages */
.cv-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.cv-msg {
    max-width: 85%;
    padding: 0.75rem 1rem;
    border-radius: 14px;
    font-size: var(--d-fs-sm);
    line-height: 1.6;
    word-break: break-word;
}
.cv-msg.user { white-space: pre-wrap; }
.cv-msg.assistant strong { font-weight: 600; }
.cv-msg.assistant em { font-style: italic; }
.cv-msg.assistant ul { margin: 0.3em 0; padding-left: 1.25em; }
.cv-msg.assistant li { margin-bottom: 0.15em; }
.cv-md-table {
    border-collapse: collapse;
    font-size: 0.8em;
    margin: 0.5em 0;
    width: 100%;
}
.cv-md-table th, .cv-md-table td {
    border: 1px solid var(--color-border, #e2e8f0);
    padding: 0.3em 0.5em;
    text-align: left;
}
.cv-md-table th {
    background: var(--color-bg-tertiary, #f1f5f9);
    font-weight: 600;
}
.cv-msg.user {
    align-self: flex-end;
    background: var(--color-primary, #004c9b);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.cv-msg.assistant {
    align-self: flex-start;
    background: var(--color-bg-secondary, #f8fafc);
    border: 1px solid var(--color-border, #e2e8f0);
    border-bottom-left-radius: 4px;
    color: var(--color-text-primary, #1e293b);
}
.cv-msg.streaming::after {
    content: ' |';
    animation: blink 0.7s infinite;
}
@keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: 0; } }
.cv-typing {
    align-self: flex-start;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    color: var(--color-text-secondary, #64748b);
    font-size: var(--d-fs-sm);
}
.cv-typing-dots { display: flex; gap: 3px; }
.cv-typing-dots span {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--color-primary, #004c9b);
    animation: typingDot 1.2s infinite;
}
.cv-typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.cv-typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingDot { 0%,100% { opacity: 0.3; transform: scale(0.8); } 50% { opacity: 1; transform: scale(1); } }

/* Suggestion Buttons */
.cv-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-self: flex-start;
}
.cv-suggest-btn {
    padding: 0.5rem 0.875rem;
    background: var(--color-bg-primary, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 10px;
    cursor: pointer;
    font-size: var(--d-fs-sm);
    color: var(--color-text-primary, #1e293b);
    transition: all 0.15s;
    text-align: left;
    max-width: 100%;
}
.cv-suggest-btn:hover {
    border-color: var(--color-primary, #004c9b);
    background: #eaf3fc;
}
.cv-suggest-btn.selected {
    border-color: var(--color-primary, #004c9b);
    background: #d6e7f5;
    color: var(--color-primary, #004c9b);
    font-weight: 500;
}
.cv-suggest-send {
    padding: 0.4rem 1rem;
    background: var(--color-primary, #004c9b);
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: var(--d-fs-sm);
    font-weight: 600;
    display: none;
    transition: background 0.15s;
}
.cv-suggest-send:hover { background: #003a78; }
.cv-suggest-send.visible { display: inline-block; }

/* Action Cards (KI hat eine Karte erstellt/geaendert) */
.cv-action {
    align-self: flex-start;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    padding: 0.625rem 0.875rem;
    font-size: var(--d-fs-sm);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    max-width: 85%;
}
.cv-action .material-symbols-rounded { font-size: 18px; color: #16a34a; flex-shrink: 0; }
.cv-action-text { flex: 1; color: #166534; }
.cv-action-undo {
    background: none; border: none; color: #dc2626;
    cursor: pointer; font-size: var(--d-fs-xs); text-decoration: underline;
    flex-shrink: 0;
}

/* Welcome / Start */
.cv-welcome {
    text-align: center;
    padding: 2rem;
    color: var(--color-text-secondary, #64748b);
    align-self: center;
    max-width: 480px;
}
.cv-welcome .material-symbols-rounded {
    font-size: 40px;
    color: var(--color-primary, #004c9b);
    margin-bottom: 0.75rem;
}
.cv-welcome h3 {
    font-size: var(--d-fs-base);
    color: var(--color-text-primary, #1e293b);
    margin-bottom: 0.5rem;
}
.cv-welcome p { font-size: var(--d-fs-sm); line-height: 1.5; margin-bottom: 1rem; }
.cv-start-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.625rem 1.25rem;
    background: var(--color-primary, #004c9b);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: var(--d-fs-sm);
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
}
.cv-start-btn:hover { background: #003a78; }
.cv-start-btn .material-symbols-rounded { font-size: 18px; }

/* Chat Input */
.cv-input-area {
    padding: 0.75rem 1.25rem;
    border-top: 1px solid var(--color-border, #e2e8f0);
    display: flex;
    gap: 0.5rem;
    align-items: flex-end;
    flex-shrink: 0;
}
.cv-textarea {
    flex: 1;
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 12px;
    padding: 0.625rem 0.875rem;
    font-size: var(--d-fs-sm);
    resize: none;
    min-height: 44px;
    max-height: 150px;
    font-family: inherit;
    background: var(--color-bg-primary, #fff);
    color: var(--color-text-primary, #1e293b);
    line-height: 1.4;
}
.cv-textarea:focus { outline: none; border-color: var(--color-primary, #004c9b); }
.cv-send-btn {
    background: var(--color-primary, #004c9b);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 0.625rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: background 0.15s;
}
.cv-send-btn:hover { background: #003a78; }
.cv-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.cv-send-btn .material-symbols-rounded { font-size: 20px; }

/* ===== Card Edit Modal ===== */
.cv-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 200;
    justify-content: center;
    align-items: center;
}
.cv-modal-overlay.active { display: flex; }
.cv-modal {
    background: var(--color-bg-primary, #fff);
    border-radius: 12px;
    padding: 1.75rem;
    width: 90%;
    max-width: 520px;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.cv-modal h2 {
    margin: 0 0 1.25rem;
    font-size: var(--d-fs-lg);
}
.cv-modal .form-group { margin-bottom: 1rem; }
.cv-modal label {
    display: block;
    font-size: var(--d-fs-sm);
    font-weight: 500;
    margin-bottom: 0.3rem;
    color: var(--color-text-secondary, #64748b);
}
.cv-modal input, .cv-modal textarea, .cv-modal select {
    width: 100%;
    padding: 0.5rem 0.625rem;
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 8px;
    font-size: var(--d-fs-sm);
    background: var(--color-bg-primary, #fff);
    color: var(--color-text-primary, #1e293b);
    box-sizing: border-box;
    font-family: inherit;
}
.cv-modal textarea { min-height: 100px; resize: vertical; }
.cv-status-radios { display: flex; gap: 0.75rem; }
.cv-status-radio {
    display: flex; align-items: center; gap: 0.3rem;
    cursor: pointer; font-size: var(--d-fs-sm);
}
.cv-status-radio input[type="radio"] { width: auto; }
.cv-modal-actions {
    display: flex; justify-content: space-between; margin-top: 1.25rem;
}
.cv-modal-actions-right { display: flex; gap: 0.5rem; }

/* Participant Modal */
.cv-participant-list { display: flex; flex-direction: column; gap: 0.375rem; margin-bottom: 1rem; }
.cv-participant-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.4rem 0.625rem; background: var(--color-bg-secondary, #f8fafc); border-radius: 6px;
    font-size: var(--d-fs-sm);
}
.cv-participant-role {
    font-size: var(--d-fs-xs); padding: 0.1rem 0.35rem; border-radius: 3px;
    background: #cfe2f5; color: #003a78; margin-left: 0.375rem;
}

/* Card-New Animation */
.cv-card-new { animation: cardSlideIn 0.3s ease-out; }
@keyframes cardSlideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
.cv-card-highlight { animation: cardHighlight 1.2s ease-out; }
@keyframes cardHighlight { 0% { background: #fef3c7; } 100% { background: var(--color-bg-primary, #fff); } }

/* Spinner */
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ===== Responsive ===== */
@media (max-width: 768px) {
    .cv-board { flex-direction: column; }
    .cv-sidebar { width: 100%; min-width: 100%; max-height: 40vh; border-right: none; border-bottom: 1px solid var(--color-border, #e2e8f0); }
}
</style>

<div class="cv-board" data-canvas-id="<?= $canvasId ?>">
    <!-- LEFT: Canvas-Felder -->
    <div class="cv-sidebar">
        <div class="cv-sidebar-header">
            <div class="cv-sidebar-header-left">
                <a href="/canvas" class="thx-icon-btn" title="Zurück zur Übersicht"><span class="material-symbols-rounded">arrow_back</span></a>
                <h1 class="thx-page-title" id="cv-title" title="Klicken zum Bearbeiten" style="font-size:var(--d-fs-base);margin:0;cursor:pointer;">Laden...</h1>
            </div>
            <div class="cv-sidebar-actions">
                <button class="thx-icon-btn" onclick="openParticipantModal()" title="Teilnehmer"><span class="material-symbols-rounded">group_add</span></button>
                <div class="cv-export-dropdown">
                    <button class="thx-icon-btn" onclick="toggleExportMenu()" title="Export"><span class="material-symbols-rounded">download</span></button>
                    <div class="cv-export-menu" id="export-menu">
                        <button onclick="exportCanvas('docx', true)">Word (KI-optimiert)</button>
                        <button onclick="exportCanvas('docx', false)">Word (Rohdaten)</button>
                        <button onclick="exportCanvas('markdown', false)">Markdown</button>
                        <button onclick="exportCanvas('json', false)">JSON</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="cv-progress" id="cv-progress"></div>
        <div class="cv-fields" id="cv-fields"></div>
    </div>

    <!-- RIGHT: KI-Sparring Chat -->
    <div class="cv-chat">
        <div class="cv-chat-header">
            <div class="cv-chat-title">
                <span class="material-symbols-rounded">auto_awesome</span>
                KI-Sparring
            </div>
            <div class="cv-chat-header-right">
                <div class="cv-participants" id="cv-participants"></div>
            </div>
        </div>
        <div class="cv-messages" id="cv-messages">
            <!-- Welcome / Start -->
            <div class="cv-welcome" id="cv-welcome">
                <span class="material-symbols-rounded">auto_awesome</span>
                <h3>KI-Sparringspartner</h3>
                <p>Die KI fuehrt dich durch alle 8 Felder deines Projekts. Sie stellt gezielte Fragen, erstellt Karten aus deinen Antworten und prueft auf Vollstaendigkeit.</p>
                <button class="cv-start-btn" onclick="startSparring()">
                    <span class="material-symbols-rounded">play_arrow</span>
                    Sparring starten
                </button>
            </div>
        </div>
        <div class="cv-input-area">
            <textarea class="cv-textarea" id="cv-input" placeholder="Antwort eingeben..." rows="1"></textarea>
            <button class="cv-send-btn" id="cv-send" onclick="sendMessage()" disabled>
                <span class="material-symbols-rounded">send</span>
            </button>
        </div>
    </div>
</div>

<!-- Card Edit Modal -->
<div class="thx-modal-backdrop" id="card-modal" style="display:none;">
    <div class="thx-modal">
        <h2 id="card-modal-title">Karte bearbeiten</h2>
        <input type="hidden" id="card-edit-id">
        <input type="hidden" id="card-edit-field">
        <div class="form-group">
            <label>Titel *</label>
            <input type="text" id="card-edit-title" placeholder="Kartentitel">
        </div>
        <div class="form-group">
            <label>Inhalt</label>
            <textarea id="card-edit-content" placeholder="Details, Notizen..."></textarea>
        </div>
        <div class="form-group">
            <label>Status</label>
            <div class="cv-status-radios">
                <label class="cv-status-radio"><input type="radio" name="card-status" value="entwurf"> <span class="cv-dot entwurf"></span> Entwurf</label>
                <label class="cv-status-radio"><input type="radio" name="card-status" value="in_arbeit"> <span class="cv-dot in_arbeit"></span> In Arbeit</label>
                <label class="cv-status-radio"><input type="radio" name="card-status" value="vollstaendig"> <span class="cv-dot vollstaendig"></span> Vollstaendig</label>
            </div>
        </div>
        <div class="cv-modal-actions">
            <button class="thx-btn thx-btn-danger" id="card-delete-btn" onclick="deleteCard()" style="display:none;">Loeschen</button>
            <div class="cv-modal-actions-right">
                <button class="thx-btn thx-btn-secondary" onclick="closeCardModal()">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" onclick="saveCard()" id="card-save-btn">Speichern</button>
            </div>
        </div>
    </div>
</div>

<!-- Participant Modal -->
<div class="thx-modal-backdrop" id="participant-modal" style="display:none;">
    <div class="thx-modal">
        <h2>Teilnehmer</h2>
        <div class="cv-participant-list" id="participant-list"></div>
        <div class="form-group">
            <label>Teilnehmer hinzufuegen</label>
            <div style="display:flex;gap:0.5rem;">
                <select id="participant-user" style="flex:1;">
                    <option value="">Benutzer waehlen...</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select id="participant-role" style="width:130px;">
                    <option value="auftraggeber">Auftraggeber</option>
                    <option value="entwickler">Entwickler</option>
                </select>
                <button class="thx-btn thx-btn-primary" onclick="addParticipant()">+</button>
            </div>
        </div>
        <div class="cv-modal-actions">
            <div></div>
            <button class="thx-btn thx-btn-secondary" onclick="closeParticipantModal()">Schliessen</button>
        </div>
    </div>
</div>

<script>
(function() {
    const CANVAS_ID = <?= $canvasId ?>;
    const CSRF = '<?= \Core\Session::getCsrfToken() ?>';
    const FIELDS = <?= json_encode(\Services\CanvasService::FIELDS) ?>;
    const FIELD_LABELS = <?= json_encode(\Services\CanvasService::FIELD_LABELS) ?>;
    const FIELD_ICONS = <?= json_encode(\Services\CanvasService::FIELD_ICONS) ?>;

    let canvasData = null;
    let activeField = 'problem'; // Fuer Sidebar-Collapse
    let streaming = false;
    let chatLoaded = false; // Einmalig laden

    // ===== API =====
    async function api(url, opts = {}) {
        const resp = await fetch('/api/v1' + url, {
            ...opts,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }
        });
        return resp.json();
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    // ===== Load Data =====
    async function loadCanvas() {
        const r = await api('/canvas/projects/' + CANVAS_ID);
        if (!r.success) {
            document.getElementById('cv-fields').innerHTML = '<div style="padding:1rem;color:red;">' + esc(r.message) + '</div>';
            return;
        }
        canvasData = r.data;
        renderSidebar();
        renderParticipants();
    }

    function renderSidebar() {
        document.getElementById('cv-title').textContent = canvasData.title;

        // Progress bar
        const bar = document.getElementById('cv-progress');
        bar.innerHTML = '';
        FIELDS.forEach(f => {
            const seg = document.createElement('div');
            seg.className = 'cv-progress-seg ' + (canvasData.completeness.fields[f]?.status || 'empty');
            seg.title = FIELD_LABELS[f] + ': ' + (canvasData.completeness.fields[f]?.percent || 0) + '%';
            bar.appendChild(seg);
        });

        // Readiness
        // Readiness entfernt

        // Fields
        const container = document.getElementById('cv-fields');
        container.innerHTML = '';
        FIELDS.forEach(field => {
            const cards = canvasData.cards[field] || [];
            const info = canvasData.completeness.fields[field] || {};
            const isActive = field === activeField;

            const el = document.createElement('div');
            el.className = 'cv-field' + (isActive ? ' active' : '');
            el.dataset.field = field;

            let cardsHtml = '';
            cards.forEach(card => {
                const excerpt = card.content ? card.content.substring(0, 80) + (card.content.length > 80 ? '...' : '') : '';
                cardsHtml += `
                    <div class="cv-card" data-card-id="${card.id}" onclick="event.stopPropagation();openEditCardModal(${card.id})">
                        <div class="cv-card-title">${esc(card.title)}</div>
                        ${excerpt ? '<div class="cv-card-excerpt">' + esc(excerpt) + '</div>' : ''}
                    </div>
                `;
            });

            el.innerHTML = `
                <div class="cv-field-header" onclick="switchField('${field}')">
                    <div class="cv-field-left">
                        <span class="material-symbols-rounded">${FIELD_ICONS[field]}</span>
                        ${FIELD_LABELS[field]}
                    </div>
                    <div class="cv-field-right">
                        <span class="cv-field-count">${cards.length}</span>
                    </div>
                </div>
                <div class="cv-field-cards">
                    ${cardsHtml}
                    <div class="cv-add-card">
                        <button class="cv-add-card-btn" onclick="event.stopPropagation();openNewCardModal('${field}')">
                            <span class="material-symbols-rounded">add</span> Karte
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(el);
        });
    }

    function renderParticipants() {
        const c = document.getElementById('cv-participants');
        c.innerHTML = '';
        (canvasData.participants || []).forEach(p => {
            const initials = (p.name || '').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
            const av = document.createElement('div');
            av.className = 'cv-avatar';
            av.title = p.name + ' (' + p.canvas_role + ')';
            av.textContent = initials;
            c.appendChild(av);
        });
    }


    // ===== Field Switching =====
    window.switchField = function(field) {
        activeField = field;
        // Nur Sidebar-Collapse toggeln, Chat bleibt
        document.querySelectorAll('.cv-field').forEach(el => {
            el.classList.toggle('active', el.dataset.field === field);
        });
    };

    // ===== Chat (ein einziger Strang fuer das ganze Canvas) =====
    async function loadChatHistory() {
        if (chatLoaded) return; // Nur einmal laden
        const r = await api('/canvas/projects/' + CANVAS_ID + '/ai-messages');
        const container = document.getElementById('cv-messages');

        if (!r.success || !Array.isArray(r.data) || r.data.length === 0) {
            // Kein Chat vorhanden — Welcome bleibt sichtbar
            document.getElementById('cv-send').disabled = true;
            return;
        }

        // History vorhanden — Welcome entfernen, Messages anzeigen
        container.innerHTML = '';
        chatLoaded = true;
        document.getElementById('cv-send').disabled = false;
        r.data.forEach(msg => {
            // Skip trigger messages
            if (msg.role === 'user' && msg.content.startsWith('Starte das Sparring')) return;
            addMessage(msg.role, msg.content);
        });
        container.scrollTop = container.scrollHeight;
    }

    // Strip [ACTION:...]...[/ACTION] and <<Suggestion>> markers from display text
    function cleanDisplayText(text) {
        let clean = text.replace(/\[ACTION:\w+\][\s\S]*?\[\/ACTION\]/g, '');
        clean = clean.replace(/\[ACTION:\w+\][\s\S]*$/g, '');
        clean = clean.replace(/^<<.+?>>$/gm, '');
        clean = clean.replace(/\n{3,}/g, '\n\n').trim();
        return clean;
    }

    // Leichtgewichtiges Markdown → HTML
    function renderMarkdown(text) {
        // Zeilen einzeln verarbeiten
        const lines = text.split('\n');
        const out = [];
        let inTable = false;
        let tableRows = [];
        let inList = false;

        function flushTable() {
            if (tableRows.length < 2) { // Mindestens Header + Separator
                tableRows.forEach(r => out.push(esc(r)));
                tableRows = [];
                inTable = false;
                return;
            }
            let html = '<table class="cv-md-table">';
            tableRows.forEach((row, i) => {
                // Separator-Zeile (---|---) ueberspringen
                if (/^\|[\s\-:|]+\|$/.test(row.trim())) return;
                const cells = row.split('|').filter((_, idx, arr) => idx > 0 && idx < arr.length - 1);
                const tag = i === 0 ? 'th' : 'td';
                html += '<tr>' + cells.map(c => '<' + tag + '>' + inlineFormat(c.trim()) + '</' + tag + '>').join('') + '</tr>';
            });
            html += '</table>';
            out.push(html);
            tableRows = [];
            inTable = false;
        }

        function flushList() { inList = false; }

        for (const line of lines) {
            const trimmed = line.trim();

            // Tabellen-Zeile erkennen (|...|)
            if (/^\|.+\|$/.test(trimmed)) {
                inTable = true;
                tableRows.push(trimmed);
                continue;
            } else if (inTable) {
                flushTable();
            }

            // Leerzeile
            if (trimmed === '') {
                if (inList) flushList();
                out.push('');
                continue;
            }

            // Headings: ### text
            const headingMatch = trimmed.match(/^(#{1,4})\s+(.+)$/);
            if (headingMatch) {
                const level = headingMatch[1].length;
                const sizes = { 1: '1.2em', 2: '1.05em', 3: '0.95em', 4: '0.9em' };
                out.push('<strong style="font-size:' + (sizes[level] || '1em') + '">' + inlineFormat(headingMatch[2]) + '</strong>');
                continue;
            }

            // Bullet lists: - text or * text
            const bulletMatch = trimmed.match(/^[-*]\s+(.+)$/);
            if (bulletMatch) {
                if (!inList) { out.push('<ul style="margin:0.25em 0;padding-left:1.25em;">'); inList = true; }
                out.push('<li>' + inlineFormat(bulletMatch[1]) + '</li>');
                continue;
            } else if (inList) {
                out.push('</ul>');
                flushList();
            }

            // Numbered lists: 1. text
            const numMatch = trimmed.match(/^\d+\.\s+(.+)$/);
            if (numMatch) {
                out.push('<div style="padding-left:0.5em;">' + inlineFormat(trimmed) + '</div>');
                continue;
            }

            // Normal paragraph
            out.push(inlineFormat(trimmed));
        }

        if (inTable) flushTable();
        if (inList) out.push('</ul>');

        return out.join('<br>').replace(/(<br>){3,}/g, '<br><br>').replace(/(<\/?ul[^>]*>)<br>/g, '$1').replace(/<br>(<\/?ul[^>]*>)/g, '$1');
    }

    // Inline-Formatierung: bold, italic, code, links
    function inlineFormat(text) {
        let html = esc(text);
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');
        html = html.replace(/`(.+?)`/g, '<code style="background:#f1f5f9;padding:0.1em 0.3em;border-radius:3px;font-size:0.85em;">$1</code>');
        return html;
    }

    function parseSuggestions(content) {
        const suggestions = [];
        content.split('\n').forEach(line => {
            const match = line.match(/^<<(.+?)>>$/);
            if (match) suggestions.push(match[1]);
        });
        return suggestions;
    }

    function buildSuggestionButtons(suggestions) {
        const container = document.getElementById('cv-messages');
        const sugEl = document.createElement('div');
        sugEl.className = 'cv-suggestions';

        suggestions.forEach(s => {
            const btn = document.createElement('button');
            btn.className = 'cv-suggest-btn';
            btn.textContent = s;
            btn.onclick = () => {
                btn.classList.toggle('selected');
                // Senden-Button anzeigen wenn mind. 1 ausgewaehlt
                const sendBtn = sugEl.querySelector('.cv-suggest-send');
                const anySelected = sugEl.querySelectorAll('.cv-suggest-btn.selected').length > 0;
                if (sendBtn) sendBtn.classList.toggle('visible', anySelected);
            };
            sugEl.appendChild(btn);
        });

        // Senden-Button
        const sendBtn = document.createElement('button');
        sendBtn.className = 'cv-suggest-send';
        sendBtn.textContent = 'Auswahl senden';
        sendBtn.onclick = () => {
            const selected = Array.from(sugEl.querySelectorAll('.cv-suggest-btn.selected'))
                .map(b => b.textContent);
            if (selected.length === 0) return;
            document.getElementById('cv-input').value = selected.join('\n');
            sendMessage();
            sugEl.remove();
        };
        sugEl.appendChild(sendBtn);

        return sugEl;
    }

    function addMessage(role, content) {
        const container = document.getElementById('cv-messages');
        const msg = document.createElement('div');
        msg.className = 'cv-msg ' + role;
        const displayContent = role === 'assistant' ? cleanDisplayText(content) : content;
        if (role === 'assistant') {
            msg.innerHTML = renderMarkdown(displayContent);
        } else {
            msg.textContent = displayContent;
        }
        container.appendChild(msg);

        // Parse suggestions from assistant messages (<<Option>> pattern)
        if (role === 'assistant') {
            const suggestions = parseSuggestions(content);
            if (suggestions.length > 0) {
                container.appendChild(buildSuggestionButtons(suggestions));
            }
        }

        return msg;
    }

    function addAction(action, data) {
        const container = document.getElementById('cv-messages');
        const el = document.createElement('div');
        el.className = 'cv-action';

        let icon = 'add_card', text = '';

        if (action === 'create_card') {
            text = 'Karte erstellt: "' + (data.title || '') + '" in ' + (FIELD_LABELS[data.field] || data.field);
        } else if (action === 'update_card') {
            icon = 'edit_note';
            text = 'Karte aktualisiert' + (data.title ? ': "' + data.title + '"' : '');
        } else if (action === 'create_reference') {
            icon = 'link';
            text = 'Querverweis erstellt';
        } else if (action === 'suggest_status') {
            icon = 'recommend';
            text = 'Empfehlung: ' + (data.reason || '');
        }

        const undoHtml = (action === 'create_card' && data.created_id)
            ? `<button class="cv-action-undo" onclick="undoAction(${data.created_id},this)">Rueckgaengig</button>`
            : '';

        el.innerHTML = `<span class="material-symbols-rounded">${icon}</span><span class="cv-action-text">${esc(text)}</span>${undoHtml}`;
        container.appendChild(el);
        container.scrollTop = container.scrollHeight;
    }

    window.undoAction = async function(cardId, btn) {
        await api('/canvas/cards/' + cardId, { method: 'DELETE' });
        btn.closest('.cv-action').style.opacity = '0.3';
        btn.textContent = 'Geloescht';
        btn.disabled = true;
        await loadCanvas();
    };

    // ===== Start Sparring =====
    window.startSparring = async function() {
        document.getElementById('cv-send').disabled = false;
        document.getElementById('cv-welcome')?.remove();
        chatLoaded = true;

        // KI startet das Interview — geht alle Felder durch
        await streamAI('Starte das Canvas-Interview. Beginne mit dem ersten Feld das noch leer oder unvollstaendig ist. Stelle eine gezielte Frage mit 2-3 Antwort-Optionen im Format <<Option>>.');
    };

    // ===== Send Message =====
    window.sendMessage = async function() {
        if (streaming) return;
        const input = document.getElementById('cv-input');
        const message = input.value.trim();
        if (!message) return;

        input.value = '';
        input.style.height = 'auto';

        // Remove suggestions
        document.querySelectorAll('.cv-suggestions').forEach(el => el.remove());

        addMessage('user', message);
        document.getElementById('cv-messages').scrollTop = document.getElementById('cv-messages').scrollHeight;

        await streamAI(message);
    };

    async function streamAI(message) {
        streaming = true;
        document.getElementById('cv-send').disabled = true;

        // Typing-Indikator
        const typingEl = document.createElement('div');
        typingEl.className = 'cv-typing';
        typingEl.innerHTML = '<div class="cv-typing-dots"><span></span><span></span><span></span></div> KI denkt nach...';
        document.getElementById('cv-messages').appendChild(typingEl);
        document.getElementById('cv-messages').scrollTop = document.getElementById('cv-messages').scrollHeight;

        const streamMsg = document.createElement('div');
        streamMsg.className = 'cv-msg assistant streaming';
        streamMsg.style.display = 'none'; // Erst zeigen wenn Text kommt

        let fullContent = '';

        try {
            const response = await fetch('/api/v1/canvas/projects/' + CANVAS_ID + '/ai-chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ message })
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop();

                for (const line of lines) {
                    if (!line.startsWith('data: ')) continue;
                    const jsonStr = line.substring(6);
                    if (jsonStr === '[DONE]') continue;

                    try {
                        const event = JSON.parse(jsonStr);

                        if (event.type === 'token') {
                            if (!fullContent && typingEl.parentNode) {
                                typingEl.remove();
                                streamMsg.style.display = '';
                                document.getElementById('cv-messages').appendChild(streamMsg);
                            }
                            fullContent += event.content;
                            streamMsg.innerHTML = renderMarkdown(cleanDisplayText(fullContent));
                            document.getElementById('cv-messages').scrollTop = document.getElementById('cv-messages').scrollHeight;
                        } else if (event.type === 'action') {
                            addAction(event.action, event.data);
                            await loadCanvas(); // Sidebar aktualisieren
                        } else if (event.type === 'error') {
                            fullContent += '\n[Fehler: ' + (event.message || '') + ']';
                            streamMsg.innerHTML = renderMarkdown(cleanDisplayText(fullContent));
                        }
                    } catch (e) { /* ignore parse errors */ }
                }
            }
        } catch (e) {
            fullContent += '\n[Verbindungsfehler]';
            streamMsg.innerHTML = renderMarkdown(cleanDisplayText(fullContent));
        }

        // Typing-Indikator entfernen falls noch da
        if (typingEl.parentNode) {
            typingEl.remove();
            streamMsg.style.display = '';
            document.getElementById('cv-messages').appendChild(streamMsg);
        }

        // Final clean display
        streamMsg.innerHTML = renderMarkdown(cleanDisplayText(fullContent));
        streamMsg.classList.remove('streaming');
        streaming = false;
        document.getElementById('cv-send').disabled = false;
        document.getElementById('cv-input').focus();

        // Suggestions aus der Antwort extrahieren
        if (fullContent) {
            const suggestions = parseSuggestions(fullContent);
            if (suggestions.length > 0) {
                document.getElementById('cv-messages').appendChild(buildSuggestionButtons(suggestions));
                document.getElementById('cv-messages').scrollTop = document.getElementById('cv-messages').scrollHeight;
            }
        }
    }

    // ===== Card CRUD =====
    window.openNewCardModal = function(field) {
        document.getElementById('card-edit-id').value = '';
        document.getElementById('card-edit-field').value = field;
        document.getElementById('card-edit-title').value = '';
        document.getElementById('card-edit-content').value = '';
        document.querySelector('input[name="card-status"][value="entwurf"]').checked = true;
        document.getElementById('card-delete-btn').style.display = 'none';
        document.getElementById('card-modal-title').textContent = 'Neue Karte — ' + FIELD_LABELS[field];
        document.getElementById('card-modal').classList.add('active');
        document.getElementById('card-edit-title').focus();
    };

    window.openEditCardModal = function(cardId) {
        let card = null;
        for (const f of FIELDS) {
            card = (canvasData.cards[f] || []).find(c => c.id == cardId);
            if (card) break;
        }
        if (!card) return;

        document.getElementById('card-edit-id').value = card.id;
        document.getElementById('card-edit-field').value = card.field;
        document.getElementById('card-edit-title').value = card.title;
        document.getElementById('card-edit-content').value = card.content || '';
        const radio = document.querySelector('input[name="card-status"][value="' + card.status + '"]');
        if (radio) radio.checked = true;
        document.getElementById('card-delete-btn').style.display = 'block';
        document.getElementById('card-modal-title').textContent = 'Karte bearbeiten — ' + FIELD_LABELS[card.field];
        document.getElementById('card-modal').classList.add('active');
        document.getElementById('card-edit-title').focus();
    };

    window.closeCardModal = function() {
        document.getElementById('card-modal').classList.remove('active');
    };

    window.saveCard = async function() {
        const id = document.getElementById('card-edit-id').value;
        const field = document.getElementById('card-edit-field').value;
        const title = document.getElementById('card-edit-title').value.trim();
        if (!title) { document.getElementById('card-edit-title').focus(); return; }

        const status = document.querySelector('input[name="card-status"]:checked')?.value || 'entwurf';
        const content = document.getElementById('card-edit-content').value.trim();

        document.getElementById('card-save-btn').disabled = true;

        let result;
        if (id) {
            result = await api('/canvas/cards/' + id, { method: 'PUT', body: JSON.stringify({ title, content, status }) });
        } else {
            result = await api('/canvas/projects/' + CANVAS_ID + '/cards', { method: 'POST', body: JSON.stringify({ field, title, content, status }) });
        }

        document.getElementById('card-save-btn').disabled = false;
        if (result.success) {
            closeCardModal();
            await loadCanvas();
        } else {
            alert(result.message || 'Fehler');
        }
    };

    window.deleteCard = async function() {
        const id = document.getElementById('card-edit-id').value;
        if (!id || !confirm('Karte wirklich loeschen?')) return;
        const result = await api('/canvas/cards/' + id, { method: 'DELETE' });
        if (result.success) { closeCardModal(); await loadCanvas(); }
    };

    // ===== Participants =====
    window.openParticipantModal = function() {
        renderParticipantList();
        document.getElementById('participant-modal').classList.add('active');
    };
    window.closeParticipantModal = function() {
        document.getElementById('participant-modal').classList.remove('active');
    };

    function renderParticipantList() {
        const list = document.getElementById('participant-list');
        list.innerHTML = '';
        (canvasData.participants || []).forEach(p => {
            const item = document.createElement('div');
            item.className = 'cv-participant-item';
            item.innerHTML = `<div><strong>${esc(p.name)}</strong><span class="cv-participant-role">${p.canvas_role}</span></div>
                <button class="thx-icon-btn" onclick="removeParticipant(${p.id})" title="Entfernen"><span class="material-symbols-rounded" style="font-size:14px;">close</span></button>`;
            list.appendChild(item);
        });
    }

    window.addParticipant = async function() {
        const userId = document.getElementById('participant-user').value;
        const role = document.getElementById('participant-role').value;
        if (!userId) return;
        const r = await api('/canvas/projects/' + CANVAS_ID + '/participants', { method: 'POST', body: JSON.stringify({ user_id: parseInt(userId), canvas_role: role }) });
        if (r.success) { await loadCanvas(); renderParticipantList(); document.getElementById('participant-user').value = ''; }
        else alert(r.message || 'Fehler');
    };

    window.removeParticipant = async function(id) {
        if (!confirm('Teilnehmer entfernen?')) return;
        await api('/canvas/participants/' + id, { method: 'DELETE' });
        await loadCanvas();
        renderParticipantList();
    };

    // ===== Export =====
    window.toggleExportMenu = function() {
        const menu = document.getElementById('export-menu');
        menu.classList.toggle('open');
        // Close on outside click
        if (menu.classList.contains('open')) {
            setTimeout(() => {
                document.addEventListener('click', function closeMenu(e) {
                    if (!e.target.closest('.cv-export-dropdown')) {
                        menu.classList.remove('open');
                        document.removeEventListener('click', closeMenu);
                    }
                });
            }, 10);
        }
    };

    window.exportCanvas = async function(format, useAI) {
        document.getElementById('export-menu').classList.remove('open');

        if (format === 'docx' && useAI) {
            // Zweistufig: 1. KI-Zusammenfassung, 2. DOCX generieren
            showExportLoading(true);
            try {
                // Schritt 1: KI-Zusammenfassung
                updateExportStatus('KI erstellt Briefing-Zusammenfassung...');
                const summaryResp = await fetch('/api/v1/canvas/projects/' + CANVAS_ID + '/export', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify({ action: 'ai-summary' })
                });
                const summaryData = await summaryResp.json();
                if (!summaryData.success) throw new Error(summaryData.message || 'KI-Fehler');

                // Schritt 2: DOCX mit Zusammenfassung
                updateExportStatus('Word-Dokument wird erstellt...');
                const docxResp = await fetch('/api/v1/canvas/projects/' + CANVAS_ID + '/export', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify({ action: 'docx', ai_summary: summaryData.data.summary })
                });
                const blob = await docxResp.blob();

                // Download
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                const slug = (canvasData.title || 'canvas').toLowerCase().replace(/[^a-z0-9]+/g, '-').substring(0, 40);
                const date = new Date().toISOString().slice(0, 10);
                a.download = slug + '-briefing-' + date + '.docx';
                a.click();
                URL.revokeObjectURL(url);
            } catch (e) {
                alert('Export fehlgeschlagen: ' + (e.message || 'Unbekannter Fehler'));
            }
            showExportLoading(false);
        } else if (format === 'docx') {
            window.open('/api/v1/canvas/projects/' + CANVAS_ID + '/export?format=docx', '_blank');
        } else {
            window.open('/api/v1/canvas/projects/' + CANVAS_ID + '/export?format=' + format, '_blank');
        }
    };

    function showExportLoading(show) {
        let overlay = document.getElementById('export-loading');
        if (show) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'export-loading';
                overlay.className = 'cv-modal-overlay active';
                overlay.innerHTML = `<div class="thx-modal" style="text-align:center;max-width:360px;">
                    <div class="material-symbols-rounded" style="font-size:36px;color:var(--color-primary,#004c9b);margin-bottom:0.75rem;animation:spin 2s linear infinite;">progress_activity</div>
                    <h2 style="margin-bottom:0.5rem;">Export wird erstellt</h2>
                    <p id="export-status" style="font-size: var(--d-fs-sm);color:var(--color-text-secondary,#64748b);">KI erstellt Briefing-Zusammenfassung...</p>
                </div>`;
                document.body.appendChild(overlay);
            }
            overlay.classList.add('active');
        } else if (overlay) {
            overlay.remove();
        }
    }

    function updateExportStatus(text) {
        const el = document.getElementById('export-status');
        if (el) el.textContent = text;
    }

    // ===== Title Edit =====
    document.getElementById('cv-title').addEventListener('click', async function() {
        const t = prompt('Canvas-Titel:', canvasData.title);
        if (t && t.trim() && t !== canvasData.title) {
            await api('/canvas/projects/' + CANVAS_ID, { method: 'PUT', body: JSON.stringify({ title: t.trim() }) });
            await loadCanvas();
        }
    });

    // ===== Input Handling =====
    const inputEl = document.getElementById('cv-input');
    inputEl.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    inputEl.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 150) + 'px';
    });

    // Close modals on overlay click
    document.querySelectorAll('.cv-modal-overlay').forEach(el => {
        el.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
    });

    // Card modal Enter key
    document.getElementById('card-edit-title').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); saveCard(); }
    });

    // ===== Init =====
    loadCanvas().then(() => loadChatHistory());
})();
</script>
