<?php
/**
 * System-Prompts-Tab — zentrale Verwaltung aller LLM-System-Prompts.
 * Liste aus SystemPromptService::defaults(); Override pro Prompt editierbar,
 * "Standard wiederherstellen" entfernt den Override.
 */
use Services\SystemPromptService;

$defs = SystemPromptService::defaults();

// Nach Kategorie gruppieren (Reihenfolge der Erstnennung beibehalten)
$byCat = [];
foreach ($defs as $key => $d) {
    $byCat[$d['category']][$key] = $d;
}
?>
<div class="settings-card">
    <h2>System-Prompts</h2>
    <p class="settings-card-sub">
        Hier verwaltest Du zentral die Grundanweisungen, die das System an die KI schickt.
        Jeder Prompt hat einen eingebauten Standard. Du kannst ihn überschreiben; mit
        „Standard wiederherstellen" kommt der ursprüngliche Text zurück. Leeres Speichern
        setzt ebenfalls auf den Standard zurück.
    </p>
</div>

<?php foreach ($byCat as $cat => $prompts): ?>
    <h3 style="margin:24px 0 12px;font-size:var(--d-fs-base);color:var(--slate-700,#334155);"><?= htmlspecialchars($cat) ?></h3>
    <?php foreach ($prompts as $key => $d):
        $effective = SystemPromptService::get($key);
        $isOverride = SystemPromptService::hasOverride($key);
    ?>
        <div class="settings-card prompt-card" data-key="<?= htmlspecialchars($key) ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div>
                    <h2 style="margin-bottom:2px;"><?= htmlspecialchars($d['label']) ?></h2>
                    <p class="settings-card-sub" style="margin-bottom:8px;"><?= htmlspecialchars($d['description']) ?></p>
                </div>
                <span class="prompt-status key-status <?= $isOverride ? 'ja' : '' ?>"
                      style="white-space:nowrap;<?= $isOverride ? '' : 'background:var(--slate-100,#f1f5f9);color:var(--slate-600,#475569);' ?>">
                    <?= $isOverride ? 'Angepasst' : 'Standard' ?>
                </span>
            </div>
            <div class="settings-field">
                <textarea class="prompt-text" rows="7" style="line-height:1.5;font-size:var(--d-fs-sm);"><?= htmlspecialchars($effective) ?></textarea>
            </div>
            <div class="settings-actions">
                <button type="button" class="thx-btn thx-btn-primary" onclick="savePrompt(this)">Speichern</button>
                <button type="button" class="thx-btn" onclick="resetPrompt(this)">Standard wiederherstellen</button>
                <button type="button" class="thx-btn" onclick="togglePromptHistory(this)">Verlauf</button>
            </div>
            <div class="prompt-history" style="display:none;margin-top:14px;border-top:1px solid var(--slate-200,#e2e8f0);padding-top:12px;"></div>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<script>
(function () {
    async function postPrompt(payload) {
        const resp = await App.request('POST', '/admin/system-prompts', payload);
        return resp;
    }
    function cardOf(btn) { return btn.closest('.prompt-card'); }

    window.savePrompt = async function (btn) {
        const card = cardOf(btn);
        const key = card.dataset.key;
        const content = card.querySelector('.prompt-text').value;
        try {
            const resp = await postPrompt({ action: 'save', key, content });
            if (resp.success) {
                App.showNotification(resp.message || 'Gespeichert', 'success');
                applyState(card, resp.data);
            } else {
                App.showNotification(resp.message || 'Fehler beim Speichern', 'error');
            }
        } catch (e) { App.showNotification(e.message || 'Verbindungsfehler', 'error'); }
    };

    window.resetPrompt = async function (btn) {
        const card = cardOf(btn);
        const key = card.dataset.key;
        if (!confirm('Diesen Prompt auf den eingebauten Standard zurücksetzen?')) return;
        try {
            const resp = await postPrompt({ action: 'reset', key });
            if (resp.success) {
                App.showNotification(resp.message || 'Zurückgesetzt', 'success');
                applyState(card, resp.data);
            } else {
                App.showNotification(resp.message || 'Fehler', 'error');
            }
        } catch (e) { App.showNotification(e.message || 'Verbindungsfehler', 'error'); }
    };

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function fmtDate(s) {
        if (!s) return '';
        const d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return s;
        return d.toLocaleString('de-DE', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    }

    window.togglePromptHistory = async function (btn) {
        const card = cardOf(btn);
        const box = card.querySelector('.prompt-history');
        if (box.style.display !== 'none') { box.style.display = 'none'; return; }
        box.style.display = 'block';
        box.innerHTML = '<p style="color:#64748b;margin:0;">Lade Verlauf…</p>';
        try {
            const resp = await postPrompt({ action: 'history', key: card.dataset.key });
            if (!resp.success) { box.innerHTML = '<p style="color:#be123c;margin:0;">' + escHtml(resp.message || 'Fehler') + '</p>'; return; }
            const versions = (resp.data && resp.data.versions) || [];
            if (!versions.length) { box.innerHTML = '<p style="color:#64748b;margin:0;">Noch keine Änderungen protokolliert.</p>'; return; }
            box.innerHTML = versions.map(v =>
                '<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--slate-100,#f1f5f9);">'
                + '<div style="min-width:0;">'
                +   '<div style="font-size:var(--d-fs-sm);color:var(--slate-700,#334155);">' + fmtDate(v.created_at)
                +     (v.user_name ? ' · ' + escHtml(v.user_name) : '') + (v.note ? ' · <em>' + escHtml(v.note) + '</em>' : '') + '</div>'
                +   '<div style="font-size:var(--d-fs-xs);color:var(--slate-500,#64748b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:42ch;">' + escHtml((v.content || '').slice(0, 120)) + '</div>'
                + '</div>'
                + '<button type="button" class="thx-btn thx-btn-small" onclick="restorePromptVersion(this,' + v.id + ')">Wiederherstellen</button>'
                + '</div>'
            ).join('');
        } catch (e) { box.innerHTML = '<p style="color:#be123c;margin:0;">' + escHtml(e.message || 'Verbindungsfehler') + '</p>'; }
    };

    window.restorePromptVersion = async function (btn, versionId) {
        const card = cardOf(btn);
        if (!confirm('Diese Version als aktuellen Prompt übernehmen?')) return;
        try {
            const resp = await postPrompt({ action: 'restore', key: card.dataset.key, version_id: versionId });
            if (resp.success) {
                App.showNotification(resp.message || 'Wiederhergestellt', 'success');
                applyState(card, resp.data);
                const histBtn = card.querySelector('.prompt-history');
                if (histBtn) histBtn.style.display = 'none';
            } else {
                App.showNotification(resp.message || 'Fehler', 'error');
            }
        } catch (e) { App.showNotification(e.message || 'Verbindungsfehler', 'error'); }
    };

    function applyState(card, data) {
        if (!data) return;
        if (typeof data.content === 'string') card.querySelector('.prompt-text').value = data.content;
        const badge = card.querySelector('.prompt-status');
        if (data.isDefault) {
            badge.textContent = 'Standard';
            badge.classList.remove('ja');
            badge.style.background = 'var(--slate-100,#f1f5f9)';
            badge.style.color = 'var(--slate-600,#475569)';
        } else {
            badge.textContent = 'Angepasst';
            badge.classList.add('ja');
            badge.style.background = '';
            badge.style.color = '';
        }
    }
})();
</script>
