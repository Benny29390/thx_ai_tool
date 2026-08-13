<?php
/**
 * Project-Types-Modal — verwaltet die Master-Liste der Projekt-Arten.
 * Wird sowohl von /rules genutzt (Header-Button "Projekt-Arten") als auch
 * von der Kunden-Seite (wenn man dort eine neue Art anlegen will).
 *
 * Public API: window.ptOpen() / window.ptClose()
 * Feuert: CustomEvent 'pt:saved' nach Aenderungen.
 */
?>
<div id="pt-modal" class="thx-modal-backdrop" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(15,23,42,0.5);align-items:center;justify-content:center;padding:20px;">
    <div class="thx-modal" style="background:#fff;border-radius:14px;width:560px;max-width:96vw;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;align-items:center;padding:14px 18px;border-bottom:1px solid var(--slate-200);gap:8px;">
            <span class="material-symbols-rounded" style="color:#0891b2;">workspaces</span>
            <h3 style="margin:0;flex:1;font-size:var(--d-fs-lg);color:var(--slate-900);">Projekt-Arten</h3>
            <button type="button" onclick="ptClose()" style="background:transparent;border:none;font-size:24px;color:var(--slate-500);cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div style="padding:14px 18px;overflow-y:auto;display:flex;flex-direction:column;gap:14px;">
            <div style="font-size:var(--d-fs-xs);color:var(--slate-500);line-height:1.5;">
                Master-Liste der Projekt-Arten. Wird gleichermaßen für Kunden (Sidebar-Filter, Karten-Badges)
                und für Regeln (Geltungsbereich) genutzt. Eine Projekt-Art = eine Stelle, kein Doppelpflegen.
            </div>

            <div id="pt-list" style="display:flex;flex-direction:column;gap:5px;"></div>

            <div style="background:#f8fafc;border:1px solid var(--slate-200);border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:8px;">
                <input type="hidden" id="pt-edit-id" value="">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <div>
                        <label style="display:block;font-size:10px;color:var(--slate-600);font-weight:600;margin-bottom:3px;">Name *</label>
                        <input type="text" id="pt-name" placeholder="z. B. Affiliate" class="rm-input" style="font-size:var(--d-fs-sm);">
                    </div>
                    <div>
                        <label style="display:block;font-size:10px;color:var(--slate-600);font-weight:600;margin-bottom:3px;">Slug</label>
                        <input type="text" id="pt-slug" placeholder="affiliate" class="rm-input" style="font-size:var(--d-fs-sm);">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;">
                    <div>
                        <label style="display:block;font-size:10px;color:var(--slate-600);font-weight:600;margin-bottom:3px;">Farbe</label>
                        <input type="color" id="pt-color" value="#9ca3af" class="rm-input" style="height:32px;padding:2px;">
                    </div>
                    <div>
                        <label style="display:block;font-size:10px;color:var(--slate-600);font-weight:600;margin-bottom:3px;">Icon (Material Symbol)</label>
                        <input type="text" id="pt-icon" value="category" placeholder="category" class="rm-input" style="font-size:var(--d-fs-sm);">
                    </div>
                </div>
                <div id="pt-error" style="font-size:11px;color:var(--rose-700);display:none;"></div>
                <div style="display:flex;gap:6px;justify-content:flex-end;">
                    <button type="button" id="pt-cancel" onclick="ptResetForm()" style="display:none;background:transparent;border:1px solid var(--slate-300);padding:4px 10px;border-radius:5px;cursor:pointer;font-size:11px;">Abbrechen</button>
                    <button type="button" class="thx-btn thx-btn-primary" onclick="ptSave()" style="padding:4px 14px;font-size:var(--d-fs-sm);">
                        <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">save</span>
                        <span id="pt-save-label">Anlegen</span>
                    </button>
                </div>
            </div>
        </div>
        <div style="padding:10px 18px;border-top:1px solid var(--slate-200);background:#fafbfc;display:flex;justify-content:flex-end;">
            <button type="button" class="thx-btn thx-btn-secondary" onclick="ptClose()">Schliessen</button>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.ptOpen) return;
    function ptEsc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function ptErr(m) { const e = document.getElementById('pt-error'); e.textContent = m; e.style.display = 'block'; }

    async function ptRenderList() {
        const list = document.getElementById('pt-list');
        list.innerHTML = '<div style="color:var(--slate-400);font-size:var(--d-fs-xs);">Lädt…</div>';
        try {
            const r = await App.get('/admin/project-types');
            if (!r.success) throw new Error(r.message || 'Fehler');
            const pts = r.data?.project_types || [];
            if (!pts.length) { list.innerHTML = '<div style="color:var(--slate-400);font-size:var(--d-fs-xs);text-align:center;padding:6px;">Noch keine Projekt-Arten.</div>'; return; }
            list.innerHTML = pts.map(p => `
                <div style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--slate-200);border-radius:6px;background:#fff;font-size:var(--d-fs-sm);">
                    <span class="material-symbols-rounded" style="font-size:18px;color:${ptEsc(p.color || '#9ca3af')};">${ptEsc(p.icon || 'category')}</span>
                    <strong style="flex:1;min-width:0;">${ptEsc(p.name)}</strong>
                    <span style="font-size:10px;color:var(--slate-500);font-family:ui-monospace,monospace;">${ptEsc(p.slug)}</span>
                    <span style="font-size:10px;color:var(--slate-500);" title="${p.customer_count} Kunden · ${p.rule_count} Regeln">${p.customer_count}K · ${p.rule_count}R</span>
                    <button onclick="ptEdit(${p.id})" style="background:transparent;border:none;color:var(--slate-500);cursor:pointer;padding:2px;" title="Bearbeiten">
                        <span class="material-symbols-rounded" style="font-size:16px;">edit</span>
                    </button>
                    <button onclick="ptDelete(${p.id}, ${JSON.stringify(p.name)}, ${p.customer_count}, ${p.rule_count})" style="background:transparent;border:none;color:var(--rose-500);cursor:pointer;padding:2px;" title="Deaktivieren">
                        <span class="material-symbols-rounded" style="font-size:16px;">delete</span>
                    </button>
                </div>
            `).join('');
        } catch (e) { list.innerHTML = '<div style="color:var(--rose-600);font-size:var(--d-fs-xs);">' + ptEsc(e.message || 'Fehler') + '</div>'; }
    }

    window.ptOpen = function () { document.getElementById('pt-modal').style.display = 'flex'; ptResetForm(); ptRenderList(); };
    window.ptClose = function () { document.getElementById('pt-modal').style.display = 'none'; };

    window.ptResetForm = function () {
        document.getElementById('pt-edit-id').value = '';
        document.getElementById('pt-name').value = '';
        document.getElementById('pt-slug').value = '';
        document.getElementById('pt-color').value = '#9ca3af';
        document.getElementById('pt-icon').value = 'category';
        document.getElementById('pt-save-label').textContent = 'Anlegen';
        document.getElementById('pt-cancel').style.display = 'none';
        document.getElementById('pt-error').style.display = 'none';
    };

    window.ptEdit = async function (id) {
        try {
            const r = await App.get('/admin/project-types?id=' + id);
            if (!r.success) throw new Error(r.message || 'Fehler');
            const p = r.data;
            document.getElementById('pt-edit-id').value = id;
            document.getElementById('pt-name').value = p.name || '';
            document.getElementById('pt-slug').value = p.slug || '';
            document.getElementById('pt-color').value = p.color || '#9ca3af';
            document.getElementById('pt-icon').value = p.icon || 'category';
            document.getElementById('pt-save-label').textContent = 'Speichern';
            document.getElementById('pt-cancel').style.display = 'inline-block';
        } catch (e) { ptErr(e.message || 'Fehler'); }
    };

    window.ptDelete = async function (id, name, custCount, ruleCount) {
        const usage = (custCount > 0 || ruleCount > 0)
            ? '\n\nAchtung: Wird aktuell von ' + custCount + ' Kunden und ' + ruleCount + ' Regeln verwendet. Die Zuordnungen bleiben bestehen, die Art ist nur nicht mehr neu wählbar.'
            : '';
        if (!confirm('Projekt-Art "' + name + '" deaktivieren?' + usage)) return;
        try {
            const r = await App.delete('/admin/project-types?id=' + id);
            if (!r.success) throw new Error(r.message || 'Fehler');
            await ptRenderList();
            document.dispatchEvent(new CustomEvent('pt:saved'));
        } catch (e) { ptErr(e.message || 'Fehler'); }
    };

    window.ptSave = async function () {
        const id = parseInt(document.getElementById('pt-edit-id').value, 10) || 0;
        const payload = {
            name: document.getElementById('pt-name').value.trim(),
            slug: document.getElementById('pt-slug').value.trim(),
            color: document.getElementById('pt-color').value,
            icon: document.getElementById('pt-icon').value.trim() || 'category',
        };
        if (!payload.name) { ptErr('Name fehlt'); return; }
        try {
            const r = id
                ? await App.put('/admin/project-types?id=' + id, payload)
                : await App.post('/admin/project-types', payload);
            if (!r.success) throw new Error(r.message || 'Fehler');
            ptResetForm();
            await ptRenderList();
            document.dispatchEvent(new CustomEvent('pt:saved'));
            App.showNotification(id ? 'Projekt-Art aktualisiert' : 'Projekt-Art angelegt', 'success');
        } catch (e) { ptErr(e.message || 'Fehler'); }
    };

    // Slug auto aus Name
    document.addEventListener('DOMContentLoaded', () => {
        const nameEl = document.getElementById('pt-name'), slugEl = document.getElementById('pt-slug');
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
        if (e.key === 'Escape' && document.getElementById('pt-modal')?.style.display === 'flex') ptClose();
    });
    document.getElementById('pt-modal')?.addEventListener('click', e => { if (e.target.id === 'pt-modal') ptClose(); });
})();
</script>
