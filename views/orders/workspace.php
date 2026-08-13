<?php
$statusLabels = [
    'briefing' => ['Briefing', '#f59e0b'],
    'briefing_approved' => ['Freigegeben', '#3b82f6'],
    'generating' => ['Generierung', '#1976d2'],
    'editing' => ['Bearbeitung', '#22c55e'],
    'completed' => ['Fertig', '#6b7280']
];
$st = $statusLabels[$order['status']] ?? ['Unbekannt', '#6b7280'];

// All chat messages with phase attribute available for JS filtering
$allMessages = $order['chat_messages'] ?? [];
$hasBriefingContent = !empty($order['briefing_content']);
$hasArticleContent = !empty($order['article_content']);
?>

<!-- Workspace Header -->
<div class="workspace-header">
    <div class="workspace-header-left">
        <a href="/orders" class="btn btn-secondary btn-small" style="margin-right: 12px;">
            <span class="material-symbols-rounded" style="font-size: 16px;">arrow_back</span>
        </a>
        <h2 class="workspace-title"><?= htmlspecialchars($order['title']) ?></h2>
        <span class="workspace-status" style="background: <?= $st[1] ?>20; color: <?= $st[1] ?>; border: 1px solid <?= $st[1] ?>40;">
            <?= $st[0] ?>
        </span>
        <span class="workspace-version" id="version-display">
            <?php if ($order['current_version'] > 0): ?>
                v<?= $order['current_version'] ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="workspace-header-right" id="header-actions">
        <!-- Dynamically rendered by updateHeaderActions() -->
    </div>
</div>

<!-- Workspace Split Pane -->
<div class="workspace-container">
    <!-- Left Panel (Chat / History Filter) -->
    <div class="workspace-chat" id="workspace-left-panel">
        <!-- History Sidebar -->
        <div class="left-panel-section" id="history-sidebar" style="display: none;">
            <div class="sidebar-header">
                <h3><span class="material-symbols-rounded" style="font-size: 18px;">filter_list</span> Filter</h3>
            </div>
            <div class="sidebar-body">
                <div class="form-group" style="margin-bottom: 12px;">
                    <input type="text" id="history-search" placeholder="Suchen..." oninput="filterHistory()" style="width: 100%; padding: 8px 10px; border: 1px solid var(--color-gray-300); border-radius: 6px; font-size: var(--d-fs-sm);">
                </div>
                <div class="sidebar-section-title">Typ</div>
                <label class="sidebar-checkbox"><input type="checkbox" checked onchange="filterHistory()" data-history-type="chat_user"><span class="material-symbols-rounded" style="font-size: 16px; color: var(--color-primary);">person</span> Nachrichten</label>
                <label class="sidebar-checkbox"><input type="checkbox" checked onchange="filterHistory()" data-history-type="chat_assistant"><span class="material-symbols-rounded" style="font-size: 16px; color: #1976d2;">smart_toy</span> KI-Antworten</label>
                <label class="sidebar-checkbox"><input type="checkbox" checked onchange="filterHistory()" data-history-type="version"><span class="material-symbols-rounded" style="font-size: 16px; color: #22c55e;">history</span> Versionen</label>
                <label class="sidebar-checkbox"><input type="checkbox" checked onchange="filterHistory()" data-history-type="usage"><span class="material-symbols-rounded" style="font-size: 16px; color: #f59e0b;">token</span> API-Calls</label>
                <label class="sidebar-checkbox"><input type="checkbox" checked onchange="filterHistory()" data-history-type="suggestion"><span class="material-symbols-rounded" style="font-size: 16px; color: #ec4899;">lightbulb</span> Regel-Vorschläge</label>
                <div class="nav-divider" style="margin: 12px 0;"></div>
                <div class="sidebar-section-title">Phase</div>
                <label class="sidebar-checkbox"><input type="checkbox" checked onchange="filterHistory()" data-history-phase="briefing"> Briefing</label>
                <label class="sidebar-checkbox"><input type="checkbox" checked onchange="filterHistory()" data-history-phase="editing"> Bearbeitung</label>
                <div class="nav-divider" style="margin: 12px 0;"></div>
                <div id="history-stats" class="history-stats"></div>
            </div>
        </div>

        <!-- Chat (default) -->
        <div class="left-panel-section" id="chat-section">

        <!-- Artifacts Info Bar -->
        <?php
        $artifactCounts = [];
        foreach (($artifacts ?? []) as $a) {
            $type = $a['meta']['type'] ?? 'Sonstiges';
            $artifactCounts[$type] = ($artifactCounts[$type] ?? 0) + 1;
        }
        $totalArtifacts = count($artifacts ?? []);
        ?>
        <?php if ($totalArtifacts > 0): ?>
        <div class="artifacts-info-bar" id="artifacts-info-bar" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; background: var(--color-primary-light, #eff6ff); border-bottom: 1px solid var(--color-primary-lighter, #dbeafe); font-size: var(--d-fs-sm); color: var(--color-gray-600); cursor: pointer;" onclick="switchTab('artifacts')" title="Artefakte anzeigen">
            <span class="material-symbols-rounded" style="font-size: 16px; color: var(--color-primary);">token</span>
            <span>
                <strong><?= $totalArtifacts ?> Artefakte</strong> aktiv
                <span style="color: var(--color-gray-400); margin-left: 2px;">(<?php
                    $parts = [];
                    foreach ($artifactCounts as $type => $count) {
                        $parts[] = $count . ' ' . htmlspecialchars($type);
                    }
                    echo implode(', ', $parts);
                ?>)</span>
            </span>
            <span class="material-symbols-rounded" style="font-size: 14px; margin-left: auto; color: var(--color-gray-400);">open_in_new</span>
        </div>
        <?php else: ?>
        <div class="artifacts-info-bar" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; background: #fef3c7; border-bottom: 1px solid #fde68a; font-size: var(--d-fs-sm); color: var(--color-gray-600);">
            <span class="material-symbols-rounded" style="font-size: 16px; color: #d97706;">warning</span>
            <span>Keine Artefakte vorhanden — <a href="/admin/artifacts" target="_blank" style="color: var(--color-primary);">Artefakte anlegen</a></span>
        </div>
        <?php endif; ?>

        <div class="chat-messages" id="chat-messages">
            <?php if (empty($allMessages)): ?>
                <div class="chat-welcome">
                    <span class="material-symbols-rounded" style="font-size: 40px; color: var(--color-gray-300);">smart_toy</span>
                    <p>Starte das Briefing-Interview – die KI stellt dir gezielte Fragen.</p>
                </div>
            <?php else: ?>
                <?php foreach ($allMessages as $msg): ?>
                    <div class="chat-message chat-<?= $msg['role'] ?>" data-phase="<?= htmlspecialchars($msg['phase']) ?>">
                        <div class="chat-message-content"><?= htmlspecialchars($msg['content']) ?></div>
                        <div class="chat-message-meta"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Learning Suggestions Banner -->
        <div class="learning-banner" id="learning-banner" style="display: none;">
            <button class="learning-btn" onclick="suggestRule()">
                <span class="material-symbols-rounded" style="font-size: 16px;">lightbulb</span> Neue Regel?
            </button>
            <button class="learning-btn" onclick="optimizeBriefing()">
                <span class="material-symbols-rounded" style="font-size: 16px;">auto_fix_high</span> Briefing optimieren?
            </button>
        </div>

        <!-- Chat Input (dynamically rendered by updateChatInput()) -->
        <div class="chat-input-area" id="chat-input-area">
        </div>
        </div>
    </div>

    <!-- Content Panel (Right) -->
    <div class="workspace-content">
        <!-- Content Tabs -->
        <div class="content-tabs">
            <button class="content-tab" id="tab-briefing" onclick="switchTab('briefing')">
                <span class="material-symbols-rounded">description</span> Briefing
            </button>
            <button class="content-tab" id="tab-article" onclick="switchTab('article')">
                <span class="material-symbols-rounded">article</span> Artikel
            </button>
            <button class="content-tab" id="tab-history" onclick="switchTab('history')">
                <span class="material-symbols-rounded">history</span> Historie
            </button>
            <button class="content-tab" id="tab-artifacts" onclick="switchTab('artifacts')">
                <span class="material-symbols-rounded">token</span> Artefakte
            </button>
        </div>

        <!-- Editor Panel (shared TipTap) -->
        <div class="content-panel" id="editor-panel" style="display: none;">
            <div class="editor-toolbar" id="editor-toolbar">
                <button onclick="editorCmd('toggleBold')" title="Fett" class="toolbar-btn" data-cmd="bold"><b>B</b></button>
                <button onclick="editorCmd('toggleItalic')" title="Kursiv" class="toolbar-btn" data-cmd="italic"><i>I</i></button>
                <button onclick="editorCmd('toggleUnderline')" title="Unterstrichen" class="toolbar-btn" data-cmd="underline"><u>U</u></button>
                <span class="toolbar-divider"></span>
                <button onclick="editorCmd('toggleHeading', {level:1})" title="H1" class="toolbar-btn" data-cmd="heading-1">H1</button>
                <button onclick="editorCmd('toggleHeading', {level:2})" title="H2" class="toolbar-btn" data-cmd="heading-2">H2</button>
                <button onclick="editorCmd('toggleHeading', {level:3})" title="H3" class="toolbar-btn" data-cmd="heading-3">H3</button>
                <span class="toolbar-divider"></span>
                <button onclick="editorCmd('toggleBulletList')" title="Liste" class="toolbar-btn" data-cmd="bulletList">
                    <span class="material-symbols-rounded" style="font-size: 18px;">format_list_bulleted</span>
                </button>
                <button onclick="editorCmd('toggleOrderedList')" title="Nummerierte Liste" class="toolbar-btn" data-cmd="orderedList">
                    <span class="material-symbols-rounded" style="font-size: 18px;">format_list_numbered</span>
                </button>
                <span class="toolbar-divider"></span>
                <button onclick="setLink()" title="Link" class="toolbar-btn" data-cmd="link">
                    <span class="material-symbols-rounded" style="font-size: 18px;">link</span>
                </button>
                <span class="toolbar-divider"></span>
                <button onclick="editorCmd('undo')" title="Rückgängig" class="toolbar-btn">
                    <span class="material-symbols-rounded" style="font-size: 18px;">undo</span>
                </button>
                <button onclick="editorCmd('redo')" title="Wiederholen" class="toolbar-btn">
                    <span class="material-symbols-rounded" style="font-size: 18px;">redo</span>
                </button>
            </div>
            <div id="editor" class="tiptap-editor"></div>
            <!-- Briefing approve action (shown only for briefing tab when status=briefing) -->
            <div class="briefing-actions" id="briefing-approve-actions" style="display: none;">
                <button class="btn btn-primary" onclick="approveBriefing()" id="approve-btn">
                    <span class="material-symbols-rounded" style="font-size: 18px;">check_circle</span> Briefing freigeben
                </button>
            </div>
        </div>

        <!-- Empty State Panel -->
        <div class="content-panel" id="empty-panel" style="display: none;">
            <div class="content-empty" id="empty-state">
                <span class="material-symbols-rounded" style="font-size: 48px; color: var(--color-gray-300);" id="empty-icon">description</span>
                <p id="empty-text">Kein Inhalt</p>
                <div class="content-empty-action" id="empty-action"></div>
            </div>
        </div>

        <!-- History Panel -->
        <div class="content-panel" id="history-panel" style="display: none;">
            <div class="history-timeline" id="history-timeline">
                <div style="text-align: center; padding: 40px 0; color: var(--color-gray-400);">
                    <span class="material-symbols-rounded rotating" style="font-size: 24px;">sync</span>
                    <p>Lade Historie...</p>
                </div>
            </div>
        </div>

        <!-- Artifacts Panel -->
        <div class="content-panel" id="artifacts-panel" style="display: none;">
            <div class="artifacts-list" id="artifacts-list"></div>
        </div>
        <script>
        const workspaceArtifacts = <?= json_encode($artifacts ?? [], JSON_UNESCAPED_UNICODE) ?>;
        </script>
    </div>
</div>

<!-- Rule Suggestion Modal (vollständiges Formular wie bei Projekte) -->
<div class="modal" id="rule-suggestion-modal">
    <div class="modal-content" style="max-width: 560px;">
        <div class="modal-header">
            <h2><span class="material-symbols-rounded" style="font-size: 20px; vertical-align: middle;">lightbulb</span> Regel-Vorschlag</h2>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body" id="rule-suggestion-body">
            <!-- Spinner (wird beim Laden angezeigt) -->
            <div id="rule-form-loading" style="text-align: center; padding: 24px 0;">
                <span class="material-symbols-rounded rotating" style="font-size: 24px;">sync</span>
                <br><br>KI analysiert...
            </div>
            <!-- Formular (wird nach KI-Antwort angezeigt) -->
            <form id="rule-suggestion-form" style="display: none;">
                <input type="hidden" id="rs-suggestion-id" value="">
                <div class="form-group">
                    <label for="rs-name">Name *</label>
                    <input type="text" id="rs-name" required placeholder="z.B. 'Keine Gedankenstriche'">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="rs-type-id">Typ *</label>
                        <select id="rs-type-id" required>
                            <option value="">Lade...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="rs-category-id">Kategorie</label>
                        <select id="rs-category-id">
                            <option value="">-- Keine --</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="rs-content">Regel *</label>
                    <textarea id="rs-content" rows="4" required placeholder="Die eigentliche Anweisung für die KI..."></textarea>
                    <small>Formuliere die Regel als klare Anweisung.</small>
                </div>
                <div class="form-group">
                    <label for="rs-description">Beschreibung (optional)</label>
                    <input type="text" id="rs-description" placeholder="Kurze Erklärung warum diese Regel existiert">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeRuleModal()">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-rounded" style="font-size: 14px;">check</span> Regel speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Versions Modal -->
<div class="modal" id="versions-modal">
    <div class="modal-content" style="max-width: 640px;">
        <div class="modal-header">
            <h2>Versions-History</h2>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body" id="versions-list">
            <p>Lade Versionen...</p>
        </div>
    </div>
</div>

<!-- Inline Edit Bubble Menu (positioned fixed via JS) -->
<div id="inline-edit-menu" class="inline-edit-menu" style="display: none;">
    <div class="inline-edit-actions" id="inline-edit-actions">
        <button class="inline-edit-btn" data-action="rewrite"><span class="material-symbols-rounded">edit_note</span> Umschreiben</button>
        <button class="inline-edit-btn" data-action="expand"><span class="material-symbols-rounded">expand</span> Ausführlicher</button>
        <button class="inline-edit-btn" data-action="shorten"><span class="material-symbols-rounded">compress</span> Kürzer</button>
        <button class="inline-edit-btn" data-action="improve"><span class="material-symbols-rounded">auto_fix_high</span> Verbessern</button>
        <span class="toolbar-divider"></span>
        <button class="inline-edit-btn" data-action="suggest-rule"><span class="material-symbols-rounded">lightbulb</span> Regel</button>
    </div>
    <div class="inline-edit-custom">
        <input type="text" id="inline-edit-input" placeholder="Eigene Anweisung..." />
        <button class="inline-edit-custom-send" id="inline-edit-send">
            <span class="material-symbols-rounded">send</span>
        </button>
    </div>
    <div class="inline-edit-loading" id="inline-edit-loading" style="display: none;">
        <span class="material-symbols-rounded rotating">sync</span>
        <span>Wird bearbeitet...</span>
    </div>
</div>

<!-- TipTap via ESM (always loaded) -->
<script type="importmap">
{
    "imports": {
        "@tiptap/core": "https://esm.sh/@tiptap/core@2.11.5",
        "@tiptap/starter-kit": "https://esm.sh/@tiptap/starter-kit@2.11.5",
        "@tiptap/extension-underline": "https://esm.sh/@tiptap/extension-underline@2.11.5",
        "@tiptap/extension-link": "https://esm.sh/@tiptap/extension-link@2.11.5",
        "@tiptap/extension-text-align": "https://esm.sh/@tiptap/extension-text-align@2.11.5",
        "@tiptap/extension-highlight": "https://esm.sh/@tiptap/extension-highlight@2.11.5"
    }
}
</script>
<script type="module">
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Highlight from '@tiptap/extension-highlight';

const editor = new Editor({
    element: document.getElementById('editor'),
    extensions: [
        StarterKit,
        Underline,
        Link.configure({ openOnClick: false }),
        Highlight.configure({
            HTMLAttributes: { class: 'ai-highlight' }
        })
    ],
    content: '<p></p>',
    onUpdate: ({ editor }) => {
        updateWordCount(editor);
        updateToolbar(editor);
        window.hasManualEdits = true;
    },
    onSelectionUpdate: ({ editor }) => {
        updateToolbar(editor);
        // Manual bubble menu positioning
        const { from, to } = editor.state.selection;
        const text = editor.state.doc.textBetween(from, to, ' ');
        const menu = document.getElementById('inline-edit-menu');
        if (!menu) return;
        if (window.activeTab !== 'article' || !window.articleContent || window.inlineEditLoading || text.trim().length < 3) {
            menu.style.display = 'none';
            return;
        }
        // Position menu above selection
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) { menu.style.display = 'none'; return; }
        const range = sel.getRangeAt(0);
        const rect = range.getBoundingClientRect();
        if (rect.width === 0) { menu.style.display = 'none'; return; }
        menu.style.display = '';
        const menuRect = menu.getBoundingClientRect();
        let left = rect.left + (rect.width / 2) - (menuRect.width / 2);
        let top = rect.top - menuRect.height - 8;
        // Keep in viewport
        if (left < 8) left = 8;
        if (left + menuRect.width > window.innerWidth - 8) left = window.innerWidth - menuRect.width - 8;
        if (top < 8) top = rect.bottom + 8; // flip below if no space above
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    }
});

window.tiptapEditor = editor;

function updateWordCount(editor) {
    const text = editor.getText();
    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
    const wc = document.getElementById('word-count'); if (wc) wc.textContent = words + ' Wörter';
}

function updateToolbar(editor) {
    document.querySelectorAll('.toolbar-btn[data-cmd]').forEach(btn => {
        const cmd = btn.dataset.cmd;
        let isActive = false;
        if (cmd === 'bold') isActive = editor.isActive('bold');
        else if (cmd === 'italic') isActive = editor.isActive('italic');
        else if (cmd === 'underline') isActive = editor.isActive('underline');
        else if (cmd === 'heading-1') isActive = editor.isActive('heading', { level: 1 });
        else if (cmd === 'heading-2') isActive = editor.isActive('heading', { level: 2 });
        else if (cmd === 'heading-3') isActive = editor.isActive('heading', { level: 3 });
        else if (cmd === 'bulletList') isActive = editor.isActive('bulletList');
        else if (cmd === 'orderedList') isActive = editor.isActive('orderedList');
        else if (cmd === 'link') isActive = editor.isActive('link');
        btn.classList.toggle('active', isActive);
    });
}

// Signal that TipTap is ready, then initialize tabs
window.tiptapReady = true;
if (window.onTiptapReady) window.onTiptapReady();
</script>

<script>
const orderId = <?= $order['id'] ?>;
let orderStatus = '<?= $order['status'] ?>';
window.hasManualEdits = false;
let activePollingInterval = null;

// Content storage
let briefingContent = <?= json_encode($order['briefing_content'] ?? '') ?>;
let articleContent = <?= json_encode($order['article_content'] ?? '') ?>;
window.articleContent = articleContent;
window.inlineEditLoading = false;
let activeTab = null; // 'briefing', 'article', 'history', 'artifacts'

// History cache
let historyCache = null;

// State flags (updated dynamically)
let hasBriefingMessages = <?= json_encode(!empty(array_filter($allMessages, fn($m) => $m['phase'] === 'briefing'))) ?>;
let hasEditingMessages = <?= json_encode(!empty(array_filter($allMessages, fn($m) => $m['phase'] === 'editing'))) ?>;

// ==========================================
// Tab System
// ==========================================
function getDefaultTab() {
    if (orderStatus === 'briefing') return 'briefing';
    return 'article'; // briefing_approved, editing, completed
}

function switchTab(tab) {
    if (tab === activeTab) return;

    // Save current editor content
    if (activeTab && window.tiptapEditor) {
        const currentHTML = window.tiptapEditor.getHTML();
        if (activeTab === 'briefing' && document.getElementById('editor-panel').style.display !== 'none') {
            briefingContent = currentHTML;
        } else if (activeTab === 'article' && document.getElementById('editor-panel').style.display !== 'none') {
            articleContent = currentHTML;
            window.articleContent = articleContent;
        }
    }

    activeTab = tab;
    window.activeTab = tab;

    // Update tab buttons
    ['briefing', 'article', 'history', 'artifacts'].forEach(t => {
        document.getElementById('tab-' + t).classList.toggle('active', tab === t);
    });

    // Toggle left panel sections based on active tab
    document.getElementById('history-sidebar').style.display = (tab === 'history') ? '' : 'none';
    document.getElementById('chat-section').style.display = (tab === 'briefing' || tab === 'article') ? '' : 'none';

    // Update all panels (header first to create word-count element)
    updateHeaderActions();
    updateContentPanel();
    updateChatDisplay();
    updateChatInput();
    window.hasManualEdits = false;
}

// ==========================================
// Content Panel
// ==========================================
function updateContentPanel() {
    const editorPanel = document.getElementById('editor-panel');
    const emptyPanel = document.getElementById('empty-panel');
    const historyPanel = document.getElementById('history-panel');
    const artifactsPanel = document.getElementById('artifacts-panel');
    const approveActions = document.getElementById('briefing-approve-actions');

    // Hide all special panels by default
    historyPanel.style.display = 'none';
    artifactsPanel.style.display = 'none';

    if (activeTab === 'history') {
        editorPanel.style.display = 'none';
        emptyPanel.style.display = 'none';
        approveActions.style.display = 'none';
        historyPanel.style.display = 'flex';
        loadHistory();
        return;
    }

    if (activeTab === 'artifacts') {
        editorPanel.style.display = 'none';
        emptyPanel.style.display = 'none';
        approveActions.style.display = 'none';
        artifactsPanel.style.display = 'flex';
        renderWorkspaceArtifacts();
        return;
    }

    if (activeTab === 'briefing') {
        if (briefingContent) {
            // Show editor with briefing content
            editorPanel.style.display = 'flex';
            emptyPanel.style.display = 'none';
            if (window.tiptapEditor) {
                window.tiptapEditor.commands.setContent(briefingContent);
                setTimeout(() => {
                    const text = window.tiptapEditor.getText();
                    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
                    const wc = document.getElementById('word-count'); if (wc) wc.textContent = words + ' Wörter';
                }, 50);
            }
            // Show approve button only if status is 'briefing'
            approveActions.style.display = (orderStatus === 'briefing') ? 'block' : 'none';
        } else {
            // Empty state
            editorPanel.style.display = 'none';
            emptyPanel.style.display = 'flex';
            approveActions.style.display = 'none';
            document.getElementById('empty-icon').textContent = 'description';
            document.getElementById('empty-text').textContent = hasBriefingMessages
                ? 'Beantworte die Fragen im Chat. Wenn du fertig bist, klicke auf „Briefing erstellen".'
                : 'Starte das Briefing-Interview im Chat.';
            document.getElementById('empty-action').innerHTML = '';
            const wc = document.getElementById('word-count'); if (wc) wc.textContent = '';
        }
    } else if (activeTab === 'article') {
        approveActions.style.display = 'none';
        if (orderStatus === 'briefing') {
            // Briefing not yet approved
            editorPanel.style.display = 'none';
            emptyPanel.style.display = 'flex';
            document.getElementById('empty-icon').textContent = 'lock';
            document.getElementById('empty-text').textContent = 'Das Briefing muss erst freigegeben werden.';
            document.getElementById('empty-action').innerHTML = '';
            const wc = document.getElementById('word-count'); if (wc) wc.textContent = '';
        } else if (!articleContent) {
            // No article yet (briefing_approved)
            editorPanel.style.display = 'none';
            emptyPanel.style.display = 'flex';
            document.getElementById('empty-icon').textContent = 'auto_awesome';
            document.getElementById('empty-text').textContent = 'Bereit zur Artikel-Generierung.';
            document.getElementById('empty-action').innerHTML = `
                <button class="btn btn-primary" onclick="generateArticle()" id="generate-article-btn">
                    <span class="material-symbols-rounded" style="font-size: 18px;">auto_awesome</span> Artikel generieren
                </button>
            `;
            const wc = document.getElementById('word-count'); if (wc) wc.textContent = '';
        } else {
            // Show editor with article content
            editorPanel.style.display = 'flex';
            emptyPanel.style.display = 'none';
            if (window.tiptapEditor) {
                window.tiptapEditor.commands.setContent(articleContent);
                setTimeout(() => {
                    const text = window.tiptapEditor.getText();
                    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
                    const wc = document.getElementById('word-count'); if (wc) wc.textContent = words + ' Wörter';
                }, 50);
            }
        }
    }
}

// ==========================================
// Chat Display
// ==========================================
function updateChatDisplay() {
    if (activeTab === 'history' || activeTab === 'artifacts') return;

    const container = document.getElementById('chat-messages');
    const messages = container.querySelectorAll('.chat-message[data-phase]');
    const phase = (activeTab === 'briefing') ? 'briefing' : 'editing';

    let hasVisibleMessages = false;
    messages.forEach(msg => {
        const show = msg.dataset.phase === phase;
        msg.style.display = show ? '' : 'none';
        if (show) hasVisibleMessages = true;
    });

    // Handle welcome message
    let welcome = container.querySelector('.chat-welcome');
    if (!hasVisibleMessages) {
        if (!welcome) {
            welcome = document.createElement('div');
            welcome.className = 'chat-welcome';
            container.prepend(welcome);
        }
        if (activeTab === 'briefing') {
            welcome.innerHTML = `
                <span class="material-symbols-rounded" style="font-size: 40px; color: var(--color-gray-300);">smart_toy</span>
                <p>Starte das Briefing-Interview – die KI stellt dir gezielte Fragen.</p>
            `;
        } else {
            welcome.innerHTML = `
                <span class="material-symbols-rounded" style="font-size: 40px; color: var(--color-gray-300);">edit_note</span>
                <p>Hier kannst du Änderungen am Artikel besprechen.</p>
            `;
        }
        welcome.style.display = '';
    } else if (welcome) {
        welcome.style.display = 'none';
    }

    // Handle suggestion buttons visibility
    const suggestions = container.querySelector('.chat-suggestions');
    if (suggestions) {
        suggestions.style.display = (activeTab === 'briefing') ? '' : 'none';
    }

    container.scrollTop = container.scrollHeight;
}

// ==========================================
// Chat Input
// ==========================================
function updateChatInput() {
    if (activeTab === 'history' || activeTab === 'artifacts') return;

    const area = document.getElementById('chat-input-area');

    if (activeTab === 'briefing') {
        if (!hasBriefingMessages && !briefingContent) {
            // No interview started yet
            area.innerHTML = `
                <button class="btn btn-primary btn-block" onclick="startInterview()" id="start-interview-btn">
                    <span class="material-symbols-rounded" style="font-size: 18px;">chat</span> Briefing-Interview starten
                </button>
            `;
        } else if (!briefingContent) {
            // Interview running, no briefing yet
            area.innerHTML = `
                <div class="chat-input-wrapper">
                    <textarea id="chat-input" rows="2" placeholder="Antwort eingeben..."
                              onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault(); sendChat();}"></textarea>
                    <button class="chat-send-btn" onclick="sendChat()" id="send-btn">
                        <span class="material-symbols-rounded">send</span>
                    </button>
                </div>
                <button class="btn btn-primary btn-block" onclick="generateBriefing()" id="generate-briefing-btn" style="margin-top: 6px;">
                    <span class="material-symbols-rounded" style="font-size: 18px;">auto_awesome</span> Briefing erstellen
                </button>
            `;
        } else {
            // Briefing exists - edit via chat
            area.innerHTML = `
                <div class="chat-input-wrapper">
                    <textarea id="chat-input" rows="2" placeholder="Briefing-Änderung beschreiben..."
                              onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault(); sendChat();}"></textarea>
                    <button class="chat-send-btn" onclick="sendChat()" id="send-btn">
                        <span class="material-symbols-rounded">send</span>
                    </button>
                </div>
            `;
        }
    } else if (activeTab === 'article') {
        if (!articleContent) {
            // No article yet
            area.innerHTML = `
                <div style="padding: 12px 16px; text-align: center; color: var(--color-gray-400); font-size: var(--d-fs-sm);">
                    Generiere zuerst einen Artikel.
                </div>
            `;
        } else {
            // Article exists - edit via chat
            area.innerHTML = `
                <div class="chat-input-wrapper">
                    <textarea id="chat-input" rows="2" placeholder="Artikel-Änderung beschreiben..."
                              onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault(); sendChat();}"></textarea>
                    <button class="chat-send-btn" onclick="sendChat()" id="send-btn">
                        <span class="material-symbols-rounded">send</span>
                    </button>
                </div>
            `;
        }
    }
}

// ==========================================
// Header Actions
// ==========================================
function updateHeaderActions() {
    const container = document.getElementById('header-actions');
    let html = '<span class="workspace-wordcount" id="word-count"></span>';

    if (activeTab === 'briefing') {
        if (briefingContent) {
            html += `
                <button class="btn btn-primary btn-small" onclick="saveBriefing()" id="save-briefing-btn">
                    <span class="material-symbols-rounded" style="font-size: 16px;">save</span> Speichern
                </button>
            `;
        }
    } else if (activeTab === 'article') {
        if (articleContent) {
            html += `
                <button class="btn btn-secondary btn-small" onclick="openVersionsModal()">
                    <span class="material-symbols-rounded" style="font-size: 16px;">history</span> Versionen
                </button>
                <button class="btn btn-primary btn-small" onclick="saveArticle()" id="save-btn">
                    <span class="material-symbols-rounded" style="font-size: 16px;">save</span> Speichern
                </button>
            `;
        }
    } else if (activeTab === 'history') {
        html = `
            <button class="btn btn-secondary btn-small" onclick="historyCache = null; loadHistory();">
                <span class="material-symbols-rounded" style="font-size: 16px;">refresh</span> Aktualisieren
            </button>
        `;
    } else if (activeTab === 'artifacts') {
        html = `
            <a href="/admin/artifacts" class="btn btn-secondary btn-small" target="_blank">
                <span class="material-symbols-rounded" style="font-size: 16px;">open_in_new</span> Artefakte verwalten
            </a>
        `;
    }

    container.innerHTML = html;

    // Refresh word count if editor is visible
    if (window.tiptapEditor && document.getElementById('editor-panel').style.display !== 'none') {
        const text = window.tiptapEditor.getText();
        const words = text.trim() ? text.trim().split(/\s+/).length : 0;
        const wc = document.getElementById('word-count'); if (wc) wc.textContent = words + ' Wörter';
    }
}

// ==========================================
// Streaming Helper
// ==========================================
async function streamRequest(url, body, onToken, onDone, onError) {
    try {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const headers = { 'Content-Type': 'application/json' };
        if (csrfMeta) headers['X-CSRF-Token'] = csrfMeta.content;

        const response = await fetch('/api/v1' + url, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(body || {})
        });

        if (!response.ok) {
            const text = await response.text();
            let msg = 'Fehler ' + response.status;
            try { const j = JSON.parse(text); msg = j.message || msg; } catch(e) {}
            throw new Error(msg);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });

            let idx;
            while ((idx = buffer.indexOf('\n\n')) !== -1) {
                const block = buffer.substring(0, idx);
                buffer = buffer.substring(idx + 2);

                for (const line of block.split('\n')) {
                    if (!line.startsWith('data: ')) continue;
                    try {
                        const data = JSON.parse(line.slice(6));
                        if (data.type === 'token' && onToken) onToken(data.content);
                        else if (data.type === 'done' && onDone) { onDone(data); return; }
                        else if (data.type === 'error' && onError) { onError(data.message); return; }
                    } catch(e) {}
                }
            }
        }
    } catch (error) {
        if (onError) onError(error.message);
    }
}

// ==========================================
// Job Polling System (Fallback)
// ==========================================
function pollJob(jobId, onProgress, onComplete, onError) {
    const interval = setInterval(async () => {
        try {
            const res = await App.get('/jobs/' + jobId);
            const job = res.data;
            if (job.status === 'completed') {
                clearInterval(interval);
                if (activePollingInterval === interval) activePollingInterval = null;
                onComplete(job);
            } else if (job.status === 'failed') {
                clearInterval(interval);
                if (activePollingInterval === interval) activePollingInterval = null;
                onError(job.error_message || 'Job fehlgeschlagen');
            } else if (onProgress) {
                onProgress(job);
            }
        } catch (e) {
            clearInterval(interval);
            if (activePollingInterval === interval) activePollingInterval = null;
            onError(e.message);
        }
    }, 2000);
    activePollingInterval = interval;
    return interval;
}

// ==========================================
// Editor commands
// ==========================================
function editorCmd(command, attrs) {
    if (!window.tiptapEditor) return;
    const chain = window.tiptapEditor.chain().focus();
    if (attrs) {
        chain[command](attrs).run();
    } else {
        chain[command]().run();
    }
}

function setLink() {
    if (!window.tiptapEditor) return;
    const url = prompt('URL eingeben:');
    if (url) {
        window.tiptapEditor.chain().focus().setLink({ href: url }).run();
    } else {
        window.tiptapEditor.chain().focus().unsetLink().run();
    }
}

function getEditorHTML() {
    return window.tiptapEditor ? window.tiptapEditor.getHTML() : '';
}

function setEditorHTML(html) {
    if (window.tiptapEditor) {
        window.tiptapEditor.commands.setContent(html);
    }
}

// ==========================================
// Chat functions
// ==========================================
function addChatMessage(role, content, phase) {
    const container = document.getElementById('chat-messages');
    const welcome = container.querySelector('.chat-welcome');
    if (welcome) welcome.style.display = 'none';

    // Remove old suggestion buttons
    const oldSuggestions = container.querySelector('.chat-suggestions');
    if (oldSuggestions) oldSuggestions.remove();

    // Extract suggestions from <<...>>
    let suggestions = [];
    let displayContent = content;
    if (role === 'assistant') {
        const matches = content.match(/<<([^>]+)>>/g);
        if (matches) {
            suggestions = matches.map(m => m.replace(/^<<|>>$/g, ''));
            displayContent = content.replace(/\s*<<[^>]+>>/g, '').trim();
        }
    }

    const msgPhase = phase || (activeTab === 'briefing' ? 'briefing' : 'editing');

    const div = document.createElement('div');
    div.className = 'chat-message chat-' + role;
    div.dataset.phase = msgPhase;
    div.innerHTML = `
        <div class="chat-message-content">${App.escapeHtml(displayContent)}</div>
        <div class="chat-message-meta">${new Date().toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'})}</div>
    `;
    container.appendChild(div);

    // Suggestion buttons
    if (suggestions.length > 0) {
        const sugDiv = document.createElement('div');
        sugDiv.className = 'chat-suggestions';
        suggestions.forEach(s => {
            const btn = document.createElement('button');
            btn.className = 'suggestion-btn';
            btn.textContent = s;
            btn.onclick = function() {
                const input = document.getElementById('chat-input');
                if (input) {
                    input.value = s;
                    sendChat();
                }
            };
            sugDiv.appendChild(btn);
        });
        container.appendChild(sugDiv);
    }

    container.scrollTop = container.scrollHeight;
}

function setChatLoading(loading) {
    const sendBtn = document.getElementById('send-btn');
    const input = document.getElementById('chat-input');
    if (sendBtn) sendBtn.disabled = loading;
    if (input) input.disabled = loading;

    if (loading) {
        const container = document.getElementById('chat-messages');
        const loader = document.createElement('div');
        loader.className = 'chat-message chat-assistant chat-loading';
        loader.id = 'chat-loader';
        loader.dataset.phase = (activeTab === 'briefing') ? 'briefing' : 'editing';
        loader.innerHTML = '<div class="chat-message-content"><span class="loading-dots">Denke nach...</span></div>';
        container.appendChild(loader);
        container.scrollTop = container.scrollHeight;
    } else {
        const loader = document.getElementById('chat-loader');
        if (loader) loader.remove();
    }
}

// ==========================================
// Briefing Phase - Interview + Generierung
// ==========================================

// Interview starten
async function startInterview() {
    const btn = document.getElementById('start-interview-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded rotating" style="font-size: 18px;">sync</span> KI bereitet Fragen vor...';

    // Remove welcome message
    const welcome = document.querySelector('.chat-welcome');
    if (welcome) welcome.style.display = 'none';

    let responseText = '';

    await streamRequest(
        '/orders/' + orderId + '/stream/briefing-interview',
        {},
        function(token) {
            responseText += token;
            let loader = document.getElementById('interview-stream');
            if (!loader) {
                const container = document.getElementById('chat-messages');
                const div = document.createElement('div');
                div.className = 'chat-message chat-assistant';
                div.id = 'interview-stream';
                div.dataset.phase = 'briefing';
                div.innerHTML = '<div class="chat-message-content streaming-content"></div>';
                container.appendChild(div);
            }
            document.querySelector('#interview-stream .chat-message-content').textContent = responseText;
            document.getElementById('chat-messages').scrollTop = document.getElementById('chat-messages').scrollHeight;
        },
        function(data) {
            const streamEl = document.getElementById('interview-stream');
            if (streamEl) streamEl.remove();

            hasBriefingMessages = true;
            addChatMessage('assistant', data.response || responseText, 'briefing');

            // Update input to show textarea + generate button
            updateChatInput();
        },
        function(error) {
            App.showNotification(error, 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded" style="font-size: 18px;">chat</span> Briefing-Interview starten';
        }
    );
}

// Briefing aus Interview generieren
async function generateBriefing() {
    const btn = document.getElementById('generate-briefing-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded rotating" style="font-size: 18px;">sync</span> Erstelle Briefing...';

    // Show streaming preview in empty panel
    const emptyPanel = document.getElementById('empty-panel');
    const editorPanel = document.getElementById('editor-panel');
    emptyPanel.style.display = 'flex';
    emptyPanel.querySelector('.content-empty').innerHTML = '<div class="briefing-streaming" id="briefing-streaming-preview" style="padding: 32px 40px; line-height: 1.7; font-size: var(--d-fs-sm); width: 100%; text-align: left;"></div>';

    let content = '';

    await streamRequest(
        '/orders/' + orderId + '/stream/briefing',
        {},
        function(token) {
            content += token;
            const preview = document.getElementById('briefing-streaming-preview');
            if (preview) preview.innerHTML = content;
        },
        function(data) {
            const finalContent = data.briefing_content || content;
            briefingContent = finalContent;

            App.showNotification('Briefing erstellt!', 'success');
            // Reload to get TipTap fresh init with content
            setTimeout(() => location.reload(), 500);
        },
        function(error) {
            App.showNotification(error, 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded" style="font-size: 18px;">auto_awesome</span> Briefing erstellen';
            updateContentPanel(); // Reset to empty state
        }
    );
}

// ==========================================
// Briefing Save
// ==========================================
async function saveBriefing() {
    const btn = document.getElementById('save-briefing-btn');
    if (btn) btn.disabled = true;

    try {
        const html = getEditorHTML();
        await App.post('/orders/' + orderId + '/briefing/save', {
            briefing_content: html
        });
        briefingContent = html;
        App.showNotification('Briefing gespeichert', 'success');
    } catch (error) {
        App.showNotification(error.message, 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// ==========================================
// Approve Briefing (only approval, no article generation)
// ==========================================
async function approveBriefing() {
    const btn = document.getElementById('approve-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded rotating" style="font-size: 18px;">sync</span> Freigabe...';

    try {
        // Save current briefing content first
        const html = getEditorHTML();
        briefingContent = html;

        // Approve briefing
        await App.post('/orders/' + orderId + '/briefing/approve');
        orderStatus = 'briefing_approved';

        App.showNotification('Briefing freigegeben', 'success');

        // Update status badge
        const statusBadge = document.querySelector('.workspace-status');
        if (statusBadge) {
            statusBadge.style.background = '#3b82f620';
            statusBadge.style.color = '#3b82f6';
            statusBadge.style.borderColor = '#3b82f640';
            statusBadge.textContent = 'Freigegeben';
        }

        // Switch to article tab - user sees generate button
        switchTab('article');
    } catch (error) {
        App.showNotification(error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded" style="font-size: 18px;">check_circle</span> Briefing freigeben';
    }
}

// ==========================================
// Generate Article (separate from approve)
// ==========================================
async function generateArticle() {
    const btn = document.getElementById('generate-article-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-rounded rotating" style="font-size: 18px;">sync</span> Generiere Artikel...';
    }

    // Replace empty state with streaming preview
    const emptyPanel = document.getElementById('empty-panel');
    emptyPanel.querySelector('.content-empty').innerHTML = `
        <div class="streaming-preview" style="width: 100%; text-align: left;">
            <div class="streaming-header" style="display: flex; align-items: center; gap: 8px; padding: 16px 40px; color: var(--color-gray-500);">
                <span class="material-symbols-rounded rotating" style="font-size: 20px;">edit_note</span>
                <span>Artikel wird geschrieben...</span>
            </div>
            <div class="article-preview streaming-content" id="article-preview" style="padding: 16px 40px; line-height: 1.7;"></div>
        </div>
    `;

    let content = '';

    await streamRequest(
        '/orders/' + orderId + '/stream/article',
        {},
        function(token) {
            content += token;
            const preview = document.getElementById('article-preview');
            if (preview) preview.innerHTML = content;
        },
        function(data) {
            App.showNotification('Artikel generiert! Seite wird neu geladen...', 'success');
            setTimeout(() => location.reload(), 500);
        },
        function(error) {
            App.showNotification('Artikel-Generierung fehlgeschlagen: ' + error, 'error');
            setTimeout(() => location.reload(), 1500);
        }
    );
}

// ==========================================
// Chat Send
// ==========================================
async function sendChat() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    if (!message) return;

    const phase = (activeTab === 'briefing') ? 'briefing' : 'editing';
    addChatMessage('user', message, phase);
    input.value = '';
    setChatLoading(true);

    if (activeTab === 'briefing') {
        if (!briefingContent) {
            // Interview mode
            let responseText = '';

            await streamRequest(
                '/orders/' + orderId + '/stream/briefing-interview',
                { message: message },
                function(token) {
                    responseText += token;
                    const loader = document.getElementById('chat-loader');
                    if (loader) {
                        loader.querySelector('.chat-message-content').textContent = responseText;
                        loader.querySelector('.chat-message-content').classList.add('streaming-content');
                    }
                },
                function(data) {
                    setChatLoading(false);
                    addChatMessage('assistant', data.response || responseText, 'briefing');
                },
                function(error) {
                    setChatLoading(false);
                    addChatMessage('assistant', 'Fehler: ' + error, 'briefing');
                    App.showNotification(error, 'error');
                }
            );
        } else {
            // Briefing edit via chat
            let content = '';

            await streamRequest(
                '/orders/' + orderId + '/stream/briefing-chat',
                { message: message },
                function(token) {
                    content += token;
                },
                function(data) {
                    setChatLoading(false);
                    const finalContent = data.briefing_content || content;
                    briefingContent = finalContent;
                    setEditorHTML(finalContent);
                    addChatMessage('assistant', 'Briefing wurde überarbeitet.', 'briefing');
                },
                function(error) {
                    setChatLoading(false);
                    addChatMessage('assistant', 'Fehler: ' + error, 'briefing');
                    App.showNotification(error, 'error');
                }
            );
        }
    } else {
        // Article chat
        const payload = { message: message };
        if (window.tiptapEditor) {
            payload.current_article = getEditorHTML();
        }

        let streamContent = '';

        await streamRequest(
            '/orders/' + orderId + '/stream/article-chat',
            payload,
            function(token) {
                streamContent += token;
            },
            function(data) {
                setChatLoading(false);
                addChatMessage('assistant', data.chat_response || 'Erledigt.', 'editing');

                if (data.changed && data.article_html) {
                    articleContent = data.article_html;
                    window.articleContent = articleContent;
                    setEditorHTML(data.article_html);
                    if (data.version_number) {
                        document.getElementById('version-display').textContent = 'v' + data.version_number;
                    }
                    App.showNotification('Artikel aktualisiert: ' + (data.change_description || ''), 'success');

                    const learningBanner = document.getElementById('learning-banner');
                    if (learningBanner) learningBanner.style.display = 'flex';
                }
            },
            function(error) {
                setChatLoading(false);
                addChatMessage('assistant', 'Fehler: ' + error, 'editing');
                App.showNotification(error, 'error');
            }
        );
    }
}

// ==========================================
// Article Functions
// ==========================================
async function saveArticle() {
    const btn = document.getElementById('save-btn');
    btn.disabled = true;

    try {
        const html = getEditorHTML();
        const response = await App.put('/orders/' + orderId + '/article/content', {
            article_content: html,
            change_description: 'Manuelle Speicherung'
        });

        articleContent = html;
        window.articleContent = articleContent;

        if (response.data && response.data.version_number) {
            document.getElementById('version-display').textContent = 'v' + response.data.version_number;
        }

        App.showNotification('Gespeichert', 'success');

        if (window.hasManualEdits) {
            const learningBanner = document.getElementById('learning-banner');
            if (learningBanner) learningBanner.style.display = 'flex';
            window.hasManualEdits = false;
        }
    } catch (error) {
        App.showNotification(error.message, 'error');
    } finally {
        btn.disabled = false;
    }
}

// ==========================================
// Versions
// ==========================================
async function openVersionsModal() {
    App.openModal('versions-modal');
    const container = document.getElementById('versions-list');
    container.innerHTML = '<p>Lade...</p>';

    try {
        const response = await App.get('/orders/' + orderId + '/versions');
        const versions = response.data || [];

        if (versions.length === 0) {
            container.innerHTML = '<p>Noch keine Versionen vorhanden.</p>';
            return;
        }

        let html = '<div class="versions-table">';
        versions.forEach(v => {
            html += `
                <div class="version-row">
                    <div class="version-info">
                        <strong>Version ${v.version_number}</strong>
                        <span class="version-meta">${v.word_count || 0} Wörter | ${v.change_source || 'manual'}</span>
                        <span class="version-desc">${App.escapeHtml(v.change_description || '')}</span>
                        <span class="version-date">${App.formatDateTime(v.created_at)}</span>
                    </div>
                    <button class="btn btn-secondary btn-small" onclick="restoreVersion(${v.version_number})">
                        Wiederherstellen
                    </button>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    } catch (error) {
        container.innerHTML = '<p>Fehler: ' + App.escapeHtml(error.message) + '</p>';
    }
}

async function restoreVersion(versionNumber) {
    if (!confirm('Version ' + versionNumber + ' wiederherstellen?')) return;

    try {
        const response = await App.post('/orders/' + orderId + '/versions/' + versionNumber + '/restore');
        App.showNotification('Version wiederhergestellt', 'success');
        App.closeModal('versions-modal');
        location.reload();
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

// ==========================================
// Learning Loop
// ==========================================
// Cache für Regel-Metadaten (Typen + Kategorien)
let ruleMetadataCache = null;

async function loadRuleMetadata() {
    if (ruleMetadataCache) return ruleMetadataCache;
    try {
        const response = await App.get('/rules');
        ruleMetadataCache = {
            types: response.data.types || [],
            categories: response.data.categories || []
        };
        return ruleMetadataCache;
    } catch (error) {
        return { types: [], categories: [] };
    }
}

function populateRuleSelects(types, categories, selectedTypeSlug) {
    const typeSelect = document.getElementById('rs-type-id');
    const catSelect = document.getElementById('rs-category-id');

    // Typen
    typeSelect.innerHTML = '';
    types.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.dataset.slug = t.slug;
        opt.textContent = t.name;
        typeSelect.appendChild(opt);
    });

    // Typ vorselektieren nach Slug
    if (selectedTypeSlug) {
        for (const opt of typeSelect.options) {
            if (opt.dataset.slug === selectedTypeSlug) {
                typeSelect.value = opt.value;
                break;
            }
        }
    }

    // Kategorien
    catSelect.innerHTML = '<option value="">-- Keine --</option>';
    categories.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        catSelect.appendChild(opt);
    });
}

function openRuleFormModal(suggestion) {
    const form = document.getElementById('rule-suggestion-form');
    const loading = document.getElementById('rule-form-loading');

    // Formular anzeigen, Spinner verstecken
    loading.style.display = 'none';
    form.style.display = '';

    // Felder befüllen
    document.getElementById('rs-suggestion-id').value = suggestion.id || '';
    document.getElementById('rs-name').value = suggestion.rule_name || '';
    document.getElementById('rs-content').value = suggestion.rule_content || '';
    document.getElementById('rs-description').value = '';

    // Typen + Kategorien laden und Selects befüllen
    loadRuleMetadata().then(meta => {
        populateRuleSelects(meta.types, meta.categories, suggestion.rule_type || 'content');
    });
}

function resetRuleModal() {
    const form = document.getElementById('rule-suggestion-form');
    const loading = document.getElementById('rule-form-loading');
    form.style.display = 'none';
    loading.style.display = '';
    loading.innerHTML = '<span class="material-symbols-rounded rotating" style="font-size: 24px;">sync</span><br><br>KI analysiert...';
}

function closeRuleModal() {
    const suggestionId = document.getElementById('rs-suggestion-id').value;
    document.getElementById('rule-suggestion-modal').classList.remove('open');
    resetRuleModal();

    // Vorschlag als abgelehnt markieren
    if (suggestionId) {
        App.post('/orders/' + orderId + '/learning/reject-rule/' + suggestionId).catch(() => {});
    }
}

async function saveRuleFromModal(e) {
    e.preventDefault();

    const suggestionId = document.getElementById('rs-suggestion-id').value;
    if (!suggestionId) return;

    const name = document.getElementById('rs-name').value.trim();
    const ruleContent = document.getElementById('rs-content').value.trim();
    if (!name || !ruleContent) {
        App.showNotification('Name und Regelinhalt erforderlich', 'error');
        return;
    }

    const typeSelect = document.getElementById('rs-type-id');
    const ruleTypeId = typeSelect.value ? parseInt(typeSelect.value) : null;
    const ruleTypeSlug = typeSelect.selectedOptions[0]?.dataset?.slug || null;
    const categoryId = document.getElementById('rs-category-id').value ? parseInt(document.getElementById('rs-category-id').value) : null;
    const description = document.getElementById('rs-description').value.trim() || null;

    try {
        await App.post('/orders/' + orderId + '/learning/accept-rule/' + suggestionId, {
            name: name,
            rule_content: ruleContent,
            rule_type: ruleTypeSlug,
            rule_type_id: ruleTypeId,
            category_id: categoryId,
            description: description
        });
        App.showNotification('Regel erstellt und zum Kontext hinzugefügt', 'success');
        document.getElementById('rule-suggestion-modal').classList.remove('open');
        resetRuleModal();
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

async function suggestRule() {
    document.getElementById('learning-banner').style.display = 'none';
    resetRuleModal();
    document.getElementById('rule-suggestion-modal').classList.add('open');

    try {
        const response = await App.post('/orders/' + orderId + '/learning/suggest-rule');
        if (response.data) {
            openRuleFormModal(response.data);
        } else {
            document.getElementById('rule-suggestion-modal').classList.remove('open');
        }
    } catch (error) {
        document.getElementById('rule-suggestion-modal').classList.remove('open');
        resetRuleModal();
        App.showNotification(error.message, 'error');
    }
}

async function optimizeBriefing() {
    document.getElementById('learning-banner').style.display = 'none';

    try {
        const response = await App.post('/orders/' + orderId + '/learning/optimize-briefing');
        App.showNotification('Briefing wurde optimiert', 'success');
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

// ==========================================
// Regel aus Selektion vorschlagen
// ==========================================
async function suggestRuleFromSelection() {
    const editor = window.tiptapEditor;
    if (!editor) return;

    const { from, to } = editor.state.selection;
    const selectedText = editor.state.doc.textBetween(from, to, ' ');
    if (!selectedText || selectedText.trim().length < 3) return;

    // Bubble Menu verstecken
    const menu = document.getElementById('inline-edit-menu');
    if (menu) menu.style.display = 'none';

    // Modal mit Spinner öffnen
    resetRuleModal();
    document.getElementById('rule-suggestion-modal').classList.add('open');

    try {
        const response = await App.post('/orders/' + orderId + '/learning/suggest-rule', {
            selected_text: selectedText
        });
        if (response.data) {
            openRuleFormModal(response.data);
        } else {
            document.getElementById('rule-suggestion-modal').classList.remove('open');
            resetRuleModal();
        }
    } catch (error) {
        document.getElementById('rule-suggestion-modal').classList.remove('open');
        resetRuleModal();
        App.showNotification(error.message, 'error');
    }
}

// ==========================================
// Inline Edit (Bubble Menu)
// ==========================================
const inlineActionMap = {
    'rewrite': 'Schreibe diesen Text um, gleiche Aussage in anderen Worten',
    'expand': 'Schreibe diesen Text ausführlicher und detaillierter',
    'shorten': 'Fasse diesen Text kürzer zusammen, behalte die Kernaussage',
    'improve': 'Verbessere Stil, Grammatik und Lesbarkeit dieses Textes'
};

async function executeInlineEdit(instruction) {
    const editor = window.tiptapEditor;
    if (!editor) return;

    const { from, to } = editor.state.selection;
    const selectedText = editor.state.doc.textBetween(from, to, ' ');
    if (!selectedText || selectedText.trim().length < 3) return;

    // Extract context (~1500 chars before/after)
    const fullText = editor.state.doc.textContent;
    const beforePos = editor.state.doc.textBetween(0, from, ' ');
    const afterEnd = Math.min(to + 1500, editor.state.doc.content.size);
    const afterPos = editor.state.doc.textBetween(to, afterEnd, ' ');

    const contextBefore = beforePos.slice(-1500);
    const contextAfter = afterPos.slice(0, 1500);

    // Show loading state
    window.inlineEditLoading = true;
    document.getElementById('inline-edit-actions').style.display = 'none';
    document.querySelector('.inline-edit-custom').style.display = 'none';
    document.getElementById('inline-edit-loading').style.display = 'flex';

    let replacementText = '';

    await streamRequest(
        '/orders/' + orderId + '/stream/inline-edit',
        {
            selected_text: selectedText,
            instruction: instruction,
            context_before: contextBefore,
            context_after: contextAfter
        },
        function(token) {
            replacementText += token;
        },
        function(data) {
            const finalText = data.replacement_text || replacementText;

            // Replace selected text and apply highlight
            editor.chain()
                .focus()
                .deleteRange({ from, to })
                .insertContentAt(from, finalText)
                .setTextSelection({ from: from, to: from + finalText.length })
                .setHighlight()
                .run();

            window.hasManualEdits = true;
            articleContent = editor.getHTML();
            window.articleContent = articleContent;

            // Reset loading state
            resetInlineEditMenu();

            // Schedule highlight clear
            scheduleHighlightClear();
        },
        function(error) {
            App.showNotification('Inline-Edit fehlgeschlagen: ' + error, 'error');
            resetInlineEditMenu();
        }
    );
}

function resetInlineEditMenu() {
    window.inlineEditLoading = false;
    document.getElementById('inline-edit-actions').style.display = 'flex';
    document.querySelector('.inline-edit-custom').style.display = 'flex';
    document.getElementById('inline-edit-loading').style.display = 'none';
    const input = document.getElementById('inline-edit-input');
    if (input) input.value = '';
}

let highlightClearTimeout = null;

function scheduleHighlightClear() {
    if (highlightClearTimeout) clearTimeout(highlightClearTimeout);
    highlightClearTimeout = setTimeout(clearAllHighlights, 5000);
}

function clearAllHighlights() {
    if (highlightClearTimeout) {
        clearTimeout(highlightClearTimeout);
        highlightClearTimeout = null;
    }
    const editor = window.tiptapEditor;
    if (!editor) return;
    editor.chain().selectAll().unsetHighlight().run();
    // Restore cursor to end to deselect
    editor.commands.focus('end');
}

// Event listeners for inline edit menu
document.addEventListener('DOMContentLoaded', function() {
    // Quick action buttons
    document.querySelectorAll('.inline-edit-btn[data-action]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const action = this.dataset.action;
            if (action === 'suggest-rule') {
                suggestRuleFromSelection();
                return;
            }
            const instruction = inlineActionMap[action];
            if (instruction) executeInlineEdit(instruction);
        });
    });

    // Custom instruction input
    const customInput = document.getElementById('inline-edit-input');
    const customSend = document.getElementById('inline-edit-send');

    if (customInput) {
        customInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                const val = this.value.trim();
                if (val) executeInlineEdit(val);
            }
        });
    }

    if (customSend) {
        customSend.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const val = customInput ? customInput.value.trim() : '';
            if (val) executeInlineEdit(val);
        });
    }

    // Prevent menu clicks from stealing editor focus/selection
    const menu = document.getElementById('inline-edit-menu');
    if (menu) {
        menu.addEventListener('mousedown', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    }

    // Hide menu on outside click
    document.addEventListener('mousedown', function(e) {
        const menu = document.getElementById('inline-edit-menu');
        if (menu && menu.style.display !== 'none' && !menu.contains(e.target)) {
            menu.style.display = 'none';
        }
    });

    // Clear highlights on editor click
    const editorEl = document.getElementById('editor');
    if (editorEl) {
        editorEl.addEventListener('click', function() {
            if (highlightClearTimeout) clearAllHighlights();
        });
    }

    // Rule suggestion form submit
    const ruleForm = document.getElementById('rule-suggestion-form');
    if (ruleForm) {
        ruleForm.addEventListener('submit', saveRuleFromModal);
    }
});

// ==========================================
// Artifacts Tab — JS Rendering
// ==========================================
const WS_TYPE_PRIORITY = ['Profil', 'Autor', 'Regel', 'Wissen', 'Namespace', 'Sonstiges'];
const WS_TYPE_COLORS = {
    'Profil': { bg: 'rgba(5, 150, 105, 0.1)', color: '#059669' },
    'Regel': { bg: 'rgba(239, 68, 68, 0.1)', color: '#dc2626' },
    'Wissen': { bg: 'rgba(37, 99, 235, 0.1)', color: '#2563eb' },
    'Autor': { bg: 'rgba(168, 85, 247, 0.1)', color: '#003a78' },
    'Namespace': { bg: 'rgba(234, 179, 8, 0.1)', color: '#ca8a04' },
    'Sonstiges': { bg: 'rgba(107, 114, 128, 0.1)', color: '#6b7280' }
};

let expandedArtifactId = null;

function wsTypeBadge(type) {
    if (!type) return '';
    const c = WS_TYPE_COLORS[type] || WS_TYPE_COLORS['Sonstiges'];
    return `<span style="font-size: var(--d-fs-xs);padding:1px 6px;border-radius:4px;background:${c.bg};color:${c.color};font-weight:500;">${escHtml(type)}</span>`;
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderWorkspaceArtifacts() {
    const container = document.getElementById('artifacts-list');
    if (!container) return;

    const artifacts = window.workspaceArtifacts || [];

    if (!artifacts.length) {
        container.innerHTML = `<div style="text-align: center; padding: 40px 0; color: var(--color-gray-400);">
            <span class="material-symbols-rounded" style="font-size: 48px;">token</span>
            <p>Keine Artefakte vorhanden.</p>
            <a href="/admin/artifacts" target="_blank" class="btn btn-secondary btn-small" style="margin-top: 12px;">Artefakte verwalten</a>
        </div>`;
        return;
    }

    // Group by type with priority sorting
    const grouped = {};
    artifacts.forEach(a => {
        const meta = a.meta || {};
        const type = meta.type || 'Sonstiges';
        if (!grouped[type]) grouped[type] = [];
        grouped[type].push(a);
    });

    const sortedTypes = Object.keys(grouped).sort((a, b) => {
        const ia = WS_TYPE_PRIORITY.indexOf(a);
        const ib = WS_TYPE_PRIORITY.indexOf(b);
        return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
    });

    let html = '';
    sortedTypes.forEach(type => {
        const items = grouped[type];
        html += `<div class="artifact-group">
            <h4 style="padding: 12px 16px; margin: 0; font-size: var(--d-fs-sm); color: var(--color-gray-500); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--color-gray-200); display: flex; align-items: center; gap: 8px;">
                ${wsTypeBadge(type)} (${items.length})
            </h4>`;

        items.forEach(a => {
            const meta = a.meta || {};
            const name = meta.name || meta.title || a.slug;
            const content = a.resolved_content || '';
            const preview = content.substring(0, 120);
            const isExpanded = expandedArtifactId === a.id;

            html += `<div class="artifact-card" style="padding: 12px 16px; border-bottom: 1px solid var(--color-gray-100); cursor: pointer; ${isExpanded ? 'background: var(--color-gray-50);' : ''}" onclick="toggleArtifactExpand(${a.id})">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                    <span class="material-symbols-rounded" style="font-size: 16px; color: var(--color-primary); transition: transform 0.2s; ${isExpanded ? 'transform: rotate(90deg);' : ''}">${isExpanded ? 'expand_more' : 'chevron_right'}</span>
                    <strong style="font-size: var(--d-fs-sm);">${escHtml(name)}</strong>
                </div>`;

            if (isExpanded) {
                html += `<div style="padding: 8px 0 8px 24px; font-size: var(--d-fs-sm); color: var(--color-gray-600); line-height: 1.5; white-space: pre-wrap;">${escHtml(content)}</div>`;
                html += `<div id="ws-artifact-links-${a.id}" style="padding: 4px 0 4px 24px;"><span class="material-symbols-rounded rotating" style="font-size: 14px;">sync</span></div>`;
            } else if (preview) {
                html += `<p style="margin: 0; font-size: var(--d-fs-sm); color: var(--color-gray-500); line-height: 1.4; padding-left: 24px;">${escHtml(preview)}${content.length > 120 ? '...' : ''}</p>`;
            }

            html += `</div>`;
        });

        html += `</div>`;
    });

    html += `<div style="text-align: center; padding: 16px;">
        <a href="/admin/artifacts" target="_blank" class="btn btn-secondary btn-small">
            Artefakte verwalten <span class="material-symbols-rounded" style="font-size: 14px; vertical-align: middle;">open_in_new</span>
        </a>
    </div>`;

    container.innerHTML = html;

    // Load links for expanded artifact
    if (expandedArtifactId) {
        loadArtifactLinksForWorkspace(expandedArtifactId);
    }
}

async function toggleArtifactExpand(id) {
    expandedArtifactId = (expandedArtifactId === id) ? null : id;
    renderWorkspaceArtifacts();
}

async function loadArtifactLinksForWorkspace(artifactId) {
    const container = document.getElementById(`ws-artifact-links-${artifactId}`);
    if (!container) return;

    try {
        const res = await App.request('GET', `/admin/artifacts/${artifactId}/links`);
        if (!res.success || !res.data || res.data.length === 0) {
            container.innerHTML = '';
            return;
        }

        let html = '<div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px;">';
        res.data.forEach(link => {
            const linked = link.artifact;
            const lMeta = linked.meta || {};
            const lName = lMeta.name || lMeta.title || linked.slug;
            const lType = lMeta.type || '';
            html += `<span onclick="event.stopPropagation(); expandedArtifactId = ${linked.id}; renderWorkspaceArtifacts();"
                style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 4px; border: 1px solid var(--color-gray-200); font-size: var(--d-fs-xs); cursor: pointer; background: var(--color-white);"
                title="${escHtml(linked.slug)}">
                ${wsTypeBadge(lType)} ${escHtml(lName)}
            </span>`;
        });
        html += '</div>';
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '';
    }
}

// ==========================================
// History Tab
// ==========================================
async function loadHistory(forceReload = false) {
    if (historyCache && !forceReload) {
        renderHistory(historyCache);
        return;
    }

    const container = document.getElementById('history-timeline');
    container.innerHTML = '<div style="text-align: center; padding: 40px 0; color: var(--color-gray-400);"><span class="material-symbols-rounded rotating" style="font-size: 24px;">sync</span><p>Lade Historie...</p></div>';

    try {
        const response = await App.get('/orders/' + orderId + '/history');
        historyCache = response.data;
        renderHistory(historyCache);
    } catch (error) {
        container.innerHTML = '<div style="text-align: center; padding: 40px 0; color: var(--color-error);"><p>Fehler: ' + App.escapeHtml(error.message) + '</p></div>';
    }
}

function renderHistory(data) {
    const container = document.getElementById('history-timeline');

    // Merge all events into a single timeline
    const events = [];

    (data.messages || []).forEach(m => {
        events.push({
            type: m.role === 'user' ? 'chat_user' : 'chat_assistant',
            date: m.created_at,
            data: m
        });
    });

    (data.versions || []).forEach(v => {
        events.push({
            type: 'version',
            date: v.created_at,
            data: v
        });
    });

    (data.usage || []).forEach(u => {
        events.push({
            type: 'usage',
            date: u.created_at,
            data: u
        });
    });

    (data.suggestions || []).forEach(s => {
        events.push({
            type: 'suggestion',
            date: s.created_at,
            data: s
        });
    });

    // Sort DESC (newest first)
    events.sort((a, b) => new Date(b.date) - new Date(a.date));

    if (events.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px 0; color: var(--color-gray-400);"><span class="material-symbols-rounded" style="font-size: 48px;">history</span><p>Noch keine Aktivitäten</p></div>';
        return;
    }

    let html = '';
    let lastDate = '';

    events.forEach(event => {
        const eventDate = new Date(event.date);
        const dateStr = eventDate.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const timeStr = eventDate.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });

        // Date separator
        if (dateStr !== lastDate) {
            html += '<div class="history-date-separator">' + dateStr + '</div>';
            lastDate = dateStr;
        }

        html += renderHistoryEvent(event, timeStr);
    });

    container.innerHTML = html;
    renderHistoryStats();
}

function renderHistoryEvent(event, timeStr) {
    const d = event.data;
    let icon, iconColor, title, details, badge = '';

    switch (event.type) {
        case 'chat_user':
            icon = 'person';
            iconColor = 'var(--color-primary)';
            badge = '<span class="history-badge" style="background: #dbeafe; color: #1d4ed8;">' + App.escapeHtml(d.phase) + '</span>';
            title = 'Nachricht';
            details = '<div class="history-content">' + App.escapeHtml(d.content).substring(0, 200) + (d.content.length > 200 ? '...' : '') + '</div>';
            break;

        case 'chat_assistant':
            icon = 'smart_toy';
            iconColor = '#1976d2';
            badge = '<span class="history-badge" style="background: #dbeafe; color: #1d4ed8;">' + App.escapeHtml(d.phase) + '</span>';
            title = 'KI-Antwort';
            details = '<div class="history-content">' + App.escapeHtml(d.content).substring(0, 200) + (d.content.length > 200 ? '...' : '') + '</div>';
            if (d.tokens_used || d.model_used) {
                details += '<div class="history-meta-row">';
                if (d.model_used) details += '<span class="history-meta-tag">' + App.escapeHtml(d.model_used) + '</span>';
                if (d.tokens_used) details += '<span class="history-meta-tag">' + d.tokens_used + ' Tokens</span>';
                details += '</div>';
            }
            break;

        case 'version':
            icon = 'history';
            iconColor = '#22c55e';
            title = 'Version ' + d.version_number;
            const sourceLabels = { ai: 'KI', manual: 'Manuell', generation: 'Generierung', rollback: 'Wiederherstellung' };
            badge = '<span class="history-badge" style="background: #dcfce7; color: #15803d;">' + (sourceLabels[d.change_source] || d.change_source) + '</span>';
            details = '';
            if (d.change_description) details += '<div class="history-content">' + App.escapeHtml(d.change_description) + '</div>';
            details += '<div class="history-meta-row">';
            if (d.word_count) details += '<span class="history-meta-tag">' + d.word_count + ' Wörter</span>';
            if (d.creator_name) details += '<span class="history-meta-tag">' + App.escapeHtml(d.creator_name) + '</span>';
            details += '</div>';
            break;

        case 'usage':
            icon = 'token';
            iconColor = '#f59e0b';
            const actionLabels = {
                order_briefing: 'Briefing generiert',
                order_briefing_chat: 'Briefing-Chat',
                order_briefing_interview: 'Briefing-Interview',
                order_article_generate: 'Artikel generiert',
                order_article_chat: 'Artikel-Chat',
                order_suggest_rule: 'Regel-Vorschlag',
                order_optimize_briefing: 'Briefing-Optimierung',
                order_inline_edit: 'Inline-Edit'
            };
            title = actionLabels[d.action_type] || d.action_type;
            details = '<div class="history-meta-row">';
            if (d.model_used) details += '<span class="history-meta-tag">' + App.escapeHtml(d.model_used) + '</span>';
            details += '<span class="history-meta-tag">' + (d.tokens_input || 0) + ' In / ' + (d.tokens_output || 0) + ' Out</span>';
            if (d.cost_estimate && parseFloat(d.cost_estimate) > 0) details += '<span class="history-meta-tag">' + parseFloat(d.cost_estimate).toFixed(4) + ' $</span>';
            details += '</div>';
            break;

        case 'suggestion':
            icon = 'lightbulb';
            iconColor = '#ec4899';
            title = 'Regel-Vorschlag: ' + App.escapeHtml(d.rule_name || 'Unbenannt');
            const statusLabels = { pending: ['Offen', '#f59e0b', '#fef3c7'], accepted: ['Angenommen', '#22c55e', '#dcfce7'], rejected: ['Abgelehnt', '#ef4444', '#fef2f2'] };
            const st = statusLabels[d.status] || ['?', '#888', '#eee'];
            badge = '<span class="history-badge" style="background: ' + st[2] + '; color: ' + st[1] + ';">' + st[0] + '</span>';
            details = '<div class="history-content">' + App.escapeHtml(d.suggested_rule).substring(0, 200) + '</div>';
            break;
    }

    const phase = d.phase || '';

    return `
        <div class="history-event history-event-${event.type}" data-history-type="${event.type}" data-history-phase="${phase}" data-history-text="${App.escapeHtml((title + ' ' + (d.content || d.change_description || d.rule_name || '')).toLowerCase())}">
            <div class="history-event-icon" style="color: ${iconColor};">
                <span class="material-symbols-rounded">${icon}</span>
            </div>
            <div class="history-event-body">
                <div class="history-event-header">
                    <span class="history-event-title">${title}</span>
                    ${badge}
                    <span class="history-event-time">${timeStr}</span>
                </div>
                ${details}
            </div>
        </div>
    `;
}

function filterHistory() {
    const searchTerm = (document.getElementById('history-search').value || '').toLowerCase().trim();
    const activeTypes = [];
    document.querySelectorAll('#history-sidebar input[data-history-type]:checked').forEach(cb => {
        activeTypes.push(cb.dataset.historyType);
    });
    const activePhases = [];
    document.querySelectorAll('#history-sidebar input[data-history-phase]:checked').forEach(cb => {
        activePhases.push(cb.dataset.historyPhase);
    });

    let visibleCount = 0;
    let lastDateSep = null;

    document.querySelectorAll('#history-timeline .history-event, #history-timeline .history-date-separator').forEach(el => {
        if (el.classList.contains('history-date-separator')) {
            el.style.display = 'none'; // hide by default, show if a child event is visible
            lastDateSep = el;
            return;
        }

        const type = el.dataset.historyType;
        const phase = el.dataset.historyPhase;
        const text = el.dataset.historyText || '';

        let show = activeTypes.includes(type);
        if (show && phase && !activePhases.includes(phase)) show = false;
        if (show && searchTerm && !text.includes(searchTerm)) show = false;

        el.style.display = show ? '' : 'none';
        if (show) {
            visibleCount++;
            if (lastDateSep) lastDateSep.style.display = '';
        }
    });

    // Update stats
    const statsEl = document.getElementById('history-stats');
    if (statsEl && historyCache) {
        const totalEvents = (historyCache.messages || []).length + (historyCache.versions || []).length +
                           (historyCache.usage || []).length + (historyCache.suggestions || []).length;
        statsEl.innerHTML = `
            <div style="font-size: var(--d-fs-sm); color: var(--color-gray-500);">
                ${visibleCount} / ${totalEvents} Einträge
            </div>
        `;
    }
}

function renderHistoryStats() {
    const statsEl = document.getElementById('history-stats');
    if (!statsEl || !historyCache) return;

    const msgs = (historyCache.messages || []).length;
    const vers = (historyCache.versions || []).length;
    const usage = (historyCache.usage || []).length;
    const sugg = (historyCache.suggestions || []).length;
    const total = msgs + vers + usage + sugg;

    // Cost summary
    let totalCost = 0;
    let totalTokens = 0;
    (historyCache.usage || []).forEach(u => {
        totalCost += parseFloat(u.cost_estimate || 0);
        totalTokens += (u.tokens_input || 0) + (u.tokens_output || 0);
    });

    statsEl.innerHTML = `
        <div style="font-size: var(--d-fs-sm); color: var(--color-gray-500); line-height: 1.6;">
            <strong>${total}</strong> Einträge gesamt<br>
            ${msgs} Nachrichten, ${vers} Versionen<br>
            ${usage} API-Calls, ${sugg} Vorschläge
            ${totalTokens > 0 ? '<br>' + totalTokens.toLocaleString('de-DE') + ' Tokens' : ''}
            ${totalCost > 0 ? ' | ' + totalCost.toFixed(4) + ' $' : ''}
        </div>
    `;
}

// ==========================================
// Initialization
// ==========================================
function initTabs() {
    const defaultTab = getDefaultTab();
    switchTab(defaultTab);
}

// Wait for TipTap, then init tabs
if (window.tiptapReady) {
    initTabs();
} else {
    window.onTiptapReady = initTabs;
}

// ==========================================
// Page Load: Check for active jobs
// ==========================================
document.addEventListener('DOMContentLoaded', async function() {
    const chatContainer = document.getElementById('chat-messages');

    // Parse <<...>> quick-reply options in PHP-rendered assistant messages
    chatContainer.querySelectorAll('.chat-assistant .chat-message-content').forEach(contentEl => {
        const text = contentEl.textContent;
        const matches = text.match(/<<([^>]+)>>/g);
        if (!matches) return;
        const suggestions = matches.map(m => m.replace(/^<<|>>$/g, ''));
        contentEl.textContent = text.replace(/\s*<<[^>]+>>/g, '').trim();
        const sugDiv = document.createElement('div');
        sugDiv.className = 'chat-suggestions';
        suggestions.forEach(s => {
            const btn = document.createElement('button');
            btn.className = 'suggestion-btn';
            btn.textContent = s;
            btn.onclick = function() {
                const input = document.getElementById('chat-input');
                if (input) { input.value = s; sendChat(); }
            };
            sugDiv.appendChild(btn);
        });
        contentEl.parentElement.insertBefore(sugDiv, contentEl.nextSibling);
    });

    chatContainer.scrollTop = chatContainer.scrollHeight;

    try {
        const res = await App.get('/orders/' + orderId + '/active-job');
        if (res.data && res.data.job_id) {
            const job = res.data;
            const jobType = job.job_type || '';

            if (jobType === 'order_briefing') {
                const btn = document.getElementById('generate-briefing-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="material-symbols-rounded rotating" style="font-size: 18px;">sync</span> Generiere Briefing...';
                }
            } else if (jobType === 'order_briefing_chat' || jobType === 'order_article_chat') {
                setChatLoading(true);
            } else if (jobType === 'order_article') {
                const btn = document.getElementById('generate-article-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="material-symbols-rounded rotating" style="font-size: 18px;">sync</span> Generiere Artikel...';
                }
            }

            pollJob(
                job.job_id,
                null,
                function(completedJob) {
                    App.showNotification('Verarbeitung abgeschlossen. Seite wird neu geladen...', 'success');
                    setTimeout(() => location.reload(), 1000);
                },
                function(error) {
                    setChatLoading(false);
                    App.showNotification('Fehler: ' + error, 'error');
                    setTimeout(() => location.reload(), 2000);
                }
            );
        }
    } catch (e) {
        // No active job - no error to show
    }
});
</script>
