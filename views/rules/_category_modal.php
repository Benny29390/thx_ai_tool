<?php
/**
 * Shared Kategorie-Modal — Liste + Anlegen + Bearbeiten + Loeschen von Regel-Kategorien.
 *
 * Wird vom Rule-Modal aus per "+ neue Kategorie" geoeffnet.
 *
 * Public API:
 *   window.cmOpen()
 *   window.cmClose()
 *
 * Feuert CustomEvent 'cm:saved' nach jeder erfolgreichen Aktion, damit das
 * Rule-Modal seine Kategorie-Liste neu laedt.
 */
?>
<div id="cm-modal" class="thx-modal-backdrop" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(15,23,42,0.5);align-items:center;justify-content:center;padding:20px;">
    <div class="thx-modal" style="background:#fff;border-radius:14px;width:520px;max-width:96vw;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;align-items:center;padding:14px 18px;border-bottom:1px solid var(--slate-200);gap:8px;">
            <span class="material-symbols-rounded" style="color:#8b5cf6;">category</span>
            <h3 style="margin:0;flex:1;font-size:var(--d-fs-lg);color:var(--slate-900);">Regel-Kategorien</h3>
            <button type="button" onclick="cmClose()" style="background:transparent;border:none;font-size:24px;color:var(--slate-500);cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div style="padding:18px;overflow-y:auto;display:flex;flex-direction:column;gap:14px;">
            <!-- Liste -->
            <div id="cm-list" style="display:flex;flex-direction:column;gap:5px;"></div>

            <!-- Anlegen / Bearbeiten -->
            <div style="background:#f8fafc;border:1px solid var(--slate-200);border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:8px;">
                <input type="hidden" id="cm-edit-id" value="">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <div>
                        <label style="display:block;font-size:10px;color:var(--slate-600);font-weight:600;margin-bottom:3px;">Name *</label>
                        <input type="text" id="cm-name" placeholder="z. B. Sprache" class="rm-input" style="font-size:var(--d-fs-sm);">
                    </div>
                    <div>
                        <label style="display:block;font-size:10px;color:var(--slate-600);font-weight:600;margin-bottom:3px;">Slug *</label>
                        <input type="text" id="cm-slug" placeholder="sprache" class="rm-input" style="font-size:var(--d-fs-sm);">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:120px 120px 1fr;gap:8px;">
                    <div>
                        <label style="display:block;font-size:10px;color:var(--slate-600);font-weight:600;margin-bottom:3px;">Farbe</label>
                        <input type="color" id="cm-color" value="#8b5cf6" class="rm-input" style="height:32px;padding:2px;">
                    </div>
                    <div>
                        <label style="display:block;font-size:10px;color:var(--slate-600);font-weight:600;margin-bottom:3px;">Icon</label>
                        <input type="text" id="cm-icon" value="rule" placeholder="rule" class="rm-input" style="font-size:var(--d-fs-sm);">
                        <small style="color:var(--slate-400);font-size:10px;">Material Symbol</small>
                    </div>
                    <div>
                        <label style="display:block;font-size:10px;color:var(--slate-600);font-weight:600;margin-bottom:3px;">Beschreibung</label>
                        <input type="text" id="cm-description" placeholder="optional" class="rm-input" style="font-size:var(--d-fs-sm);">
                    </div>
                </div>
                <div id="cm-error" style="font-size:11px;color:var(--rose-700);display:none;"></div>
                <div style="display:flex;gap:6px;justify-content:flex-end;">
                    <button type="button" id="cm-cancel" onclick="cmResetForm()" style="display:none;background:transparent;border:1px solid var(--slate-300);padding:4px 10px;border-radius:5px;cursor:pointer;font-size:11px;">Abbrechen</button>
                    <button type="button" class="thx-btn thx-btn-primary" onclick="cmSave()" style="padding:4px 14px;font-size:var(--d-fs-sm);">
                        <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">save</span>
                        <span id="cm-save-label">Anlegen</span>
                    </button>
                </div>
            </div>
        </div>
        <div style="padding:10px 18px;border-top:1px solid var(--slate-200);background:#fafbfc;display:flex;justify-content:flex-end;gap:8px;">
            <button type="button" class="thx-btn thx-btn-secondary" onclick="cmClose()">Schliessen</button>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.cmOpen) return;

    function cmEsc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function cmShowError(msg) { const e = document.getElementById('cm-error'); e.textContent = msg; e.style.display = 'block'; }

    async function cmRenderList() {
        const list = document.getElementById('cm-list');
        list.innerHTML = '<div style="color:var(--slate-400);font-size:var(--d-fs-xs);">Lädt…</div>';
        try {
            const r = await App.get('/admin/rule-categories');
            if (!r.success) throw new Error(r.message || 'Fehler');
            const cats = Array.isArray(r.data) ? r.data : (r.data?.categories || []);
            if (!cats.length) {
                list.innerHTML = '<div style="color:var(--slate-400);font-size:var(--d-fs-xs);text-align:center;padding:6px;">Noch keine Kategorien — lege unten die erste an.</div>';
                return;
            }
            list.innerHTML = cats.map(c => `
                <div style="display:flex;align-items:center;gap:8px;padding:6px 9px;border:1px solid var(--slate-200);border-radius:6px;background:#fff;font-size:var(--d-fs-sm);">
                    <span class="material-symbols-rounded" style="font-size:18px;color:${cmEsc(c.color || '#9ca3af')};">${cmEsc(c.icon || 'rule')}</span>
                    <strong style="flex:1;min-width:0;">${cmEsc(c.name)}</strong>
                    <span style="font-size:10px;color:var(--slate-500);font-family:ui-monospace,monospace;">${cmEsc(c.slug)}</span>
                    <span style="font-size:10px;color:var(--slate-500);">${c.rules_count ?? c.rule_count ?? 0} Regeln</span>
                    <button onclick="cmEdit(${c.id})" style="background:transparent;border:none;color:var(--slate-500);cursor:pointer;padding:2px;" title="Bearbeiten">
                        <span class="material-symbols-rounded" style="font-size:16px;">edit</span>
                    </button>
                    <button onclick="cmDelete(${c.id}, ${JSON.stringify(c.name)})" style="background:transparent;border:none;color:var(--rose-500);cursor:pointer;padding:2px;" title="Löschen">
                        <span class="material-symbols-rounded" style="font-size:16px;">delete</span>
                    </button>
                </div>
            `).join('');
        } catch (e) {
            list.innerHTML = '<div style="color:var(--rose-600);font-size:var(--d-fs-xs);">' + cmEsc(e.message || 'Fehler') + '</div>';
        }
    }

    window.cmOpen = function () {
        document.getElementById('cm-modal').style.display = 'flex';
        cmResetForm();
        cmRenderList();
    };
    window.cmClose = function () { document.getElementById('cm-modal').style.display = 'none'; };

    window.cmResetForm = function () {
        document.getElementById('cm-edit-id').value = '';
        document.getElementById('cm-name').value = '';
        document.getElementById('cm-slug').value = '';
        document.getElementById('cm-color').value = '#8b5cf6';
        document.getElementById('cm-icon').value = 'rule';
        document.getElementById('cm-description').value = '';
        document.getElementById('cm-save-label').textContent = 'Anlegen';
        document.getElementById('cm-cancel').style.display = 'none';
        document.getElementById('cm-error').style.display = 'none';
    };

    window.cmEdit = async function (id) {
        try {
            const r = await App.get('/admin/rule-categories?id=' + id);
            if (!r.success) throw new Error(r.message || 'Fehler');
            const c = r.data;
            document.getElementById('cm-edit-id').value = id;
            document.getElementById('cm-name').value = c.name || '';
            document.getElementById('cm-slug').value = c.slug || '';
            document.getElementById('cm-color').value = c.color || '#8b5cf6';
            document.getElementById('cm-icon').value = c.icon || 'rule';
            document.getElementById('cm-description').value = c.description || '';
            document.getElementById('cm-save-label').textContent = 'Speichern';
            document.getElementById('cm-cancel').style.display = 'inline-block';
        } catch (e) { cmShowError(e.message || 'Fehler'); }
    };

    window.cmDelete = async function (id, name) {
        if (!confirm('Kategorie "' + name + '" löschen?\n\nDie Regeln dieser Kategorie behalten existieren, aber bekommen keine Kategorie mehr.')) return;
        try {
            const r = await App.delete('/admin/rule-categories?id=' + id);
            if (!r.success) throw new Error(r.message || 'Fehler');
            await cmRenderList();
            document.dispatchEvent(new CustomEvent('cm:saved'));
        } catch (e) { cmShowError(e.message || 'Fehler'); }
    };

    window.cmSave = async function () {
        const id = parseInt(document.getElementById('cm-edit-id').value, 10) || 0;
        const payload = {
            name: document.getElementById('cm-name').value.trim(),
            slug: document.getElementById('cm-slug').value.trim() ||
                document.getElementById('cm-name').value.toLowerCase()
                    .replace(/[äöüß]/g, m => ({ä:'ae',ö:'oe',ü:'ue',ß:'ss'}[m]))
                    .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''),
            color: document.getElementById('cm-color').value,
            icon: document.getElementById('cm-icon').value.trim() || 'rule',
            description: document.getElementById('cm-description').value.trim(),
        };
        if (!payload.name) { cmShowError('Name fehlt'); return; }
        if (!payload.slug) { cmShowError('Slug konnte nicht abgeleitet werden'); return; }
        if (!/^[a-z0-9-]+$/.test(payload.slug)) { cmShowError('Slug nur a-z, 0-9, Bindestriche'); return; }
        try {
            const r = id
                ? await App.put('/admin/rule-categories?id=' + id, payload)
                : await App.post('/admin/rule-categories', payload);
            if (!r.success) throw new Error(r.message || 'Fehler');
            cmResetForm();
            await cmRenderList();
            document.dispatchEvent(new CustomEvent('cm:saved'));
            App.showNotification(id ? 'Kategorie gespeichert' : 'Kategorie angelegt', 'success');
        } catch (e) { cmShowError(e.message || 'Fehler'); }
    };

    // Slug auto aus Name (solange nicht manuell editiert)
    document.addEventListener('DOMContentLoaded', () => {
        const nameEl = document.getElementById('cm-name');
        const slugEl = document.getElementById('cm-slug');
        if (nameEl && slugEl) {
            nameEl.addEventListener('input', () => {
                if (!slugEl.dataset.userEdited) {
                    slugEl.value = nameEl.value.toLowerCase()
                        .replace(/[äöüß]/g, m => ({ä:'ae',ö:'oe',ü:'ue',ß:'ss'}[m]))
                        .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
                }
            });
            slugEl.addEventListener('input', () => { slugEl.dataset.userEdited = '1'; });
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.getElementById('cm-modal')?.style.display === 'flex') cmClose();
    });
    document.getElementById('cm-modal')?.addEventListener('click', e => {
        if (e.target.id === 'cm-modal') cmClose();
    });
})();
</script>
