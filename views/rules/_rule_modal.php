<?php
/**
 * Shared Regel-Modal — Anlegen + Bearbeiten einer Regel.
 *
 * Wird eingebunden von:
 *  - /views/rules/index.php (zentrale Regel-Verwaltung)
 *  - /views/admin/customer-steckbrief.php (Steckbrief-Karte "Regeln")
 *
 * Public API:
 *   window.rmOpen(ruleId?, customerId?)
 *   window.rmClose()
 *
 * ruleId    : null/0 = Anlegen, sonst Bearbeiten
 * customerId: null = global, sonst kundenspezifisch (nur fuer Anlegen relevant —
 *             bei Edit wird die customer_id der Regel beibehalten)
 *
 * Nach erfolgreichem Save feuert das Modal CustomEvent 'rm:saved' am document
 * mit detail = { rule_id, customer_id, mode: 'create'|'edit' }. Konsumenten
 * (Listen-Reload) lauschen drauf.
 */
?>
<div id="rm-modal" class="thx-modal-backdrop" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,0.5);align-items:center;justify-content:center;padding:20px;">
    <div class="thx-modal" style="background:#fff;border-radius:14px;width:600px;max-width:96vw;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;align-items:center;padding:14px 18px;border-bottom:1px solid var(--slate-200);gap:8px;">
            <span class="material-symbols-rounded" style="color:#8b5cf6;">rule</span>
            <h3 id="rm-title" style="margin:0;flex:1;font-size:var(--d-fs-lg);color:var(--slate-900);">Neue Regel anlegen</h3>
            <button type="button" id="rm-scope-badge" onclick="rmToggleScope()" title="Klick: Scope wechseln (Global ⇄ Kunde)"
                    style="font-size:11px;padding:3px 10px;border-radius:9px;background:#eff6ff;color:#2563eb;border:1px solid transparent;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                Global
            </button>
            <button type="button" onclick="rmClose()" style="background:transparent;border:none;font-size:24px;color:var(--slate-500);cursor:pointer;line-height:1;">&times;</button>
        </div>
        <!-- Tabs: Selbst tippen / KI-Hilfe -->
        <div id="rm-tabs" style="display:flex;gap:0;padding:0 18px;border-bottom:1px solid var(--slate-200);background:#fafbfc;">
            <button type="button" class="rm-tab is-active" data-tab="form" onclick="rmSwitchTab('form')">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">edit</span>
                Selbst tippen
            </button>
            <button type="button" class="rm-tab" data-tab="ai" onclick="rmSwitchTab('ai')">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;color:#8b5cf6;">auto_awesome</span>
                KI-Hilfe
            </button>
        </div>

        <div style="padding:18px;overflow-y:auto;" id="rm-body">
            <input type="hidden" id="rm-rule-id" value="">
            <input type="hidden" id="rm-customer-id" value="">
            <input type="hidden" id="rm-original-customer-id" value="">

            <div id="rm-tab-form" style="display:flex;flex-direction:column;gap:12px;">

            <div>
                <label style="display:block;font-size:var(--d-fs-xs);color:var(--slate-600);font-weight:600;margin-bottom:4px;">Name *</label>
                <input type="text" id="rm-name" placeholder="z. B. Worte beginnen mit B" class="rm-input">
            </div>

            <div>
                <label style="display:block;font-size:var(--d-fs-xs);color:var(--slate-600);font-weight:600;margin-bottom:4px;">Regel-Inhalt *</label>
                <textarea id="rm-content" rows="5" placeholder="Klare Anweisung an die KI. Wird 1:1 in den System-Prompt übernommen." class="rm-input"></textarea>
                <small style="color:var(--slate-400);font-size:10px;display:block;margin-top:3px;">Schreibe als Aufforderung: „Verwende Du-Form.", „Vermeide Anglizismen.", etc.</small>
            </div>

            <div>
                <label style="display:block;font-size:var(--d-fs-xs);color:var(--slate-600);font-weight:600;margin-bottom:4px;">Beschreibung <span style="color:var(--slate-400);font-weight:normal;">(optional, für interne Doku)</span></label>
                <input type="text" id="rm-description" placeholder="Kurze Erklärung warum diese Regel existiert" class="rm-input">
            </div>

            <!-- Kategorien abgeschafft — Geltungsbereich (Projekt-Arten) ist die Achse. -->
            <input type="hidden" id="rm-category" value="">
            <div>
                <label style="display:block;font-size:var(--d-fs-xs);color:var(--slate-600);font-weight:600;margin-bottom:4px;">Typ *</label>
                <select id="rm-type" class="rm-input">
                    <option value="style">Schreibstil</option>
                    <option value="format">Format</option>
                    <option value="content">Inhalt</option>
                    <option value="tone">Tonalität</option>
                    <option value="link">Link</option>
                    <option value="seo">SEO</option>
                    <option value="language">Sprache</option>
                    <option value="projektplanner">Projektplanner</option>
                </select>
                <small style="color:var(--slate-400);font-size:10px;">Was die Regel funktional macht. Wird in der KI im System-Prompt gruppiert.</small>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div>
                    <label style="display:block;font-size:var(--d-fs-xs);color:var(--slate-600);font-weight:600;margin-bottom:4px;">Einhaltung</label>
                    <select id="rm-enforcement" class="rm-input">
                        <option value="strict">Strikt (100% — Pflicht)</option>
                        <option value="soft">Empfehlung (80/20 — bei Konflikt darf abgewichen werden)</option>
                    </select>
                    <small style="color:var(--slate-400);font-size:10px;">Strikt = MUSS, Empfehlung = SOLL.</small>
                </div>
                <div>
                    <label style="display:block;font-size:var(--d-fs-xs);color:var(--slate-600);font-weight:600;margin-bottom:4px;">Wirkungsbereich</label>
                    <select id="rm-applies-to" class="rm-input">
                        <option value="both">Beides (Tool-Antworten + Content)</option>
                        <option value="content">Nur Content (erzeugte Texte)</option>
                        <option value="tool">Nur Tool (Dialog mit Dir)</option>
                    </select>
                    <small style="color:var(--slate-400);font-size:10px;">Wo gilt die Regel?</small>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:end;">
                <div>
                    <label style="display:block;font-size:var(--d-fs-xs);color:var(--slate-600);font-weight:600;margin-bottom:4px;">Priorität</label>
                    <input type="number" id="rm-priority" value="0" min="-100" max="100" class="rm-input">
                    <small style="color:var(--slate-400);font-size:10px;">Höher = wichtiger. Default 0.</small>
                </div>
                <div></div>
            </div>
            <label style="display:flex;align-items:center;gap:6px;font-size:var(--d-fs-sm);color:var(--slate-700);">
                <input type="checkbox" id="rm-active" checked>
                Aktiv
            </label>

            <!-- Geltungsbereich (Projekt-Arten) — nur bei globalen Regeln sinnvoll -->
            <div id="rm-scope-block">
                <label style="display:flex;justify-content:space-between;align-items:center;font-size:var(--d-fs-xs);color:var(--slate-600);font-weight:600;margin-bottom:6px;">
                    <span>Geltungsbereich (Projekt-Arten)</span>
                    <span style="font-weight:normal;color:var(--slate-400);">leer = gilt für alle</span>
                </label>
                <div id="rm-scope-pills" style="display:flex;flex-wrap:wrap;gap:5px;padding:8px;border:1px solid var(--slate-200);border-radius:6px;background:#fafbfc;min-height:38px;">
                    <span style="font-size:var(--d-fs-xs);color:var(--slate-400);">Lädt…</span>
                </div>
            </div>

            <div id="rm-error" style="font-size:var(--d-fs-xs);color:var(--rose-700);display:none;padding:8px 10px;background:#fef2f2;border-radius:6px;"></div>
            <div id="rm-warn" style="font-size:var(--d-fs-xs);color:var(--amber-700);display:none;padding:8px 10px;background:#fef3c7;border-radius:6px;"></div>

            </div> <!-- /rm-tab-form -->

            <!-- KI-Hilfe Tab -->
            <div id="rm-tab-ai" style="display:none;flex-direction:column;gap:12px;">
                <div style="font-size:var(--d-fs-xs);color:var(--slate-600);line-height:1.5;">
                    Beschreibe in eigenen Worten, was die KI tun (oder lassen) soll.
                    Die KI macht daraus eine oder mehrere klare Regeln, die Du einzeln übernehmen kannst.
                </div>
                <textarea id="rm-ai-prompt" rows="6" placeholder="z. B. „Verwende immer die Du-Anrede, vermeide Anglizismen, schreibe in kurzen Sätzen und nutze niemals Gedankenstriche."
                          class="rm-input"></textarea>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="button" class="thx-btn thx-btn-primary" onclick="rmAiSuggest()" id="rm-ai-go" style="font-size:var(--d-fs-sm);">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">auto_awesome</span>
                        Vorschläge generieren
                    </button>
                    <span id="rm-ai-status" style="font-size:var(--d-fs-xs);color:var(--slate-500);"></span>
                </div>

                <div id="rm-ai-suggestions" style="display:none;border-top:1px solid var(--slate-200);padding-top:12px;">
                    <div style="font-size:var(--d-fs-xs);color:var(--slate-600);font-weight:600;margin-bottom:8px;">Vorschläge — Haken setzen und übernehmen:</div>
                    <div id="rm-ai-list" style="display:flex;flex-direction:column;gap:8px;max-height:380px;overflow-y:auto;"></div>
                    <div style="display:flex;gap:6px;margin-top:10px;align-items:center;font-size:var(--d-fs-xs);">
                        <button type="button" onclick="rmAiSelectAll(true)" style="border:1px solid var(--slate-200);background:#fff;padding:3px 8px;border-radius:5px;cursor:pointer;">Alle</button>
                        <button type="button" onclick="rmAiSelectAll(false)" style="border:1px solid var(--slate-200);background:#fff;padding:3px 8px;border-radius:5px;cursor:pointer;">Keine</button>
                        <span id="rm-ai-sel-count" style="margin-left:auto;color:var(--slate-500);">0 ausgewählt</span>
                    </div>
                </div>
            </div>
        </div>
        <div style="padding:14px 18px;border-top:1px solid var(--slate-200);background:#fafbfc;display:flex;justify-content:space-between;align-items:center;gap:8px;">
            <button id="rm-delete" type="button" onclick="rmDelete()" style="display:none;color:var(--rose-700);background:transparent;border:1px solid var(--rose-200);padding:6px 12px;border-radius:6px;cursor:pointer;font-size:var(--d-fs-sm);">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">delete</span>
                Löschen
            </button>
            <div style="display:flex;gap:8px;margin-left:auto;">
                <button type="button" class="thx-btn thx-btn-secondary" onclick="rmClose()">Abbrechen</button>
                <button type="button" class="thx-btn thx-btn-primary" id="rm-save" onclick="rmSave()">
                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">save</span>
                    <span id="rm-save-label">Anlegen</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.rm-input {
    width: 100%; padding: 7px 10px; border: 1px solid var(--slate-300);
    border-radius: 6px; font-size: var(--d-fs-sm); color: var(--slate-800);
    background: #fff; font-family: inherit;
}
.rm-input:focus { outline: none; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px var(--thoxan-100); }
/* Geltungsbereich-Pillen — eigene Definition damit Modal unabhaengig von /rules-View ist */
#rm-scope-pills .rl-pill {
    padding: 3px 9px; border: 1px solid var(--slate-200); background: #fff;
    border-radius: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
    transition: all 0.12s;
}
#rm-scope-pills .rl-pill:hover { border-color: var(--slate-300); }
#rm-scope-pills .rl-pill.is-active { background: var(--thoxan-700); color: #fff; border-color: var(--thoxan-700); }

.rm-tab {
    padding: 9px 14px; border: none; background: transparent; cursor: pointer;
    font-size: var(--d-fs-sm); color: var(--slate-500); border-bottom: 2px solid transparent;
    display: inline-flex; align-items: center; gap: 5px; font-weight: 500;
}
.rm-tab:hover { color: var(--slate-800); }
.rm-tab.is-active { color: var(--thoxan-800); border-bottom-color: var(--thoxan-700); font-weight: 600; }

.rm-ai-card {
    background: #f8fafc; border: 1px solid var(--slate-200); border-radius: 8px;
    padding: 10px; display: flex; gap: 8px; align-items: flex-start;
}
.rm-ai-card.is-selected { background: var(--thoxan-50); border-color: var(--thoxan-200); }
.rm-ai-card input[type="checkbox"] { margin-top: 3px; }
.rm-ai-card-body { flex: 1; min-width: 0; }
.rm-ai-card-name { font-weight: 600; color: var(--slate-800); font-size: var(--d-fs-sm); }
.rm-ai-card-content { color: var(--slate-600); font-size: var(--d-fs-xs); margin-top: 3px; line-height: 1.45; }
.rm-ai-card-meta { font-size: 10px; color: var(--slate-500); margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap; }
.rm-ai-edit-btn { background: transparent; border: none; cursor: pointer; color: var(--slate-400); padding: 2px; }
.rm-ai-edit-btn:hover { color: var(--thoxan-700); }
</style>

<script>
(function () {
    // Idempotent: Wenn das Modal mehrfach included wird (z.B. Steckbrief + /rules-Seite
    // gleichzeitig), bleibt die erste Definition gueltig.
    if (window.rmOpen) return;

    let rmCategoriesCache = null;
    let rmProjectTypesCache = null;
    let rmScopeSelected = new Set();
    async function rmLoadProjectTypes() {
        if (rmProjectTypesCache) return rmProjectTypesCache;
        try {
            const r = await App.get('/admin/project-types');
            if (r.success) {
                rmProjectTypesCache = r.data?.project_types || [];
                return rmProjectTypesCache;
            }
        } catch (e) {}
        return [];
    }
    function rmRenderScopePills(selectedIds) {
        const wrap = document.getElementById('rm-scope-pills');
        const types = rmProjectTypesCache || [];
        rmScopeSelected = new Set((selectedIds || []).map(id => parseInt(id, 10)));
        if (!types.length) { wrap.innerHTML = '<span style="font-size:var(--d-fs-xs);color:var(--slate-400);">Keine Projekt-Arten definiert. Lege welche im Master-Pool an.</span>'; return; }
        wrap.innerHTML = types.map(pt => {
            const active = rmScopeSelected.has(parseInt(pt.id, 10));
            return `<button type="button" class="rl-pill ${active ? 'is-active' : ''}" data-pt-id="${pt.id}" style="font-size:var(--d-fs-xs);">
                <span style="display:inline-block;width:7px;height:7px;border-radius:2px;background:${rmEsc(pt.color)};"></span>
                ${rmEsc(pt.name)}
            </button>`;
        }).join('');
        wrap.querySelectorAll('button[data-pt-id]').forEach(b => {
            b.addEventListener('click', () => {
                const id = parseInt(b.dataset.ptId, 10);
                if (rmScopeSelected.has(id)) rmScopeSelected.delete(id); else rmScopeSelected.add(id);
                b.classList.toggle('is-active');
            });
        });
    }
    let rmCustomersCache = {};

    async function rmLoadCategories() {
        if (rmCategoriesCache) return rmCategoriesCache;
        try {
            const r = await App.get('/admin/rule-categories');
            if (r.success) {
                // API liefert das Array direkt unter data ODER unter data.categories
                rmCategoriesCache = Array.isArray(r.data) ? r.data : (r.data?.categories || []);
                return rmCategoriesCache;
            }
        } catch (e) {}
        return [];
    }

    function rmRenderCategoryOptions(selected) {
        const sel = document.getElementById('rm-category');
        const cats = rmCategoriesCache || [];
        sel.innerHTML = '<option value="">(keine)</option>' +
            cats.map(c => `<option value="${c.id}" ${parseInt(selected||0,10) === parseInt(c.id,10) ? 'selected' : ''}>${rmEsc(c.name)}</option>`).join('');
    }

    function rmEsc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

    function rmSetScope(customerId) {
        const badge = document.getElementById('rm-scope-badge');
        // 'rm-original-customer-id' merkt sich, woher die Regel kam, damit der Toggle wieder dorthin zurueck kann
        const original = parseInt(document.getElementById('rm-original-customer-id')?.value || '0', 10) || null;
        if (customerId) {
            const cached = rmCustomersCache[customerId];
            if (cached) {
                badge.innerHTML = rmEsc(cached) + ' <span style="font-size:9px;opacity:0.7;">⇄</span>';
            } else {
                badge.innerHTML = 'Kunde #' + customerId + ' <span style="font-size:9px;opacity:0.7;">⇄</span>';
                App.get('/admin/customers?id=' + customerId).then(r => {
                    if (r.success && r.data?.name) {
                        rmCustomersCache[customerId] = r.data.name;
                        badge.innerHTML = rmEsc(r.data.name) + ' <span style="font-size:9px;opacity:0.7;">⇄</span>';
                    }
                }).catch(()=>{});
            }
            badge.style.background = '#fef3c7';
            badge.style.color = '#92400e';
            badge.title = 'Kundenspezifisch · Klick: nach „Global" verschieben';
        } else {
            badge.innerHTML = 'Global ' + (original ? '<span style="font-size:9px;opacity:0.7;">⇄</span>' : '');
            badge.style.background = '#eff6ff';
            badge.style.color = '#2563eb';
            badge.title = original ? 'Global · Klick: zurueck zu Kunde #' + original : 'Global';
        }
    }

    window.rmToggleScope = function () {
        const cur = parseInt(document.getElementById('rm-customer-id').value || '0', 10) || null;
        const original = parseInt(document.getElementById('rm-original-customer-id')?.value || '0', 10) || null;
        if (cur) {
            // aktuell kundenspezifisch → global
            document.getElementById('rm-customer-id').value = '';
            rmSetScope(null);
        } else if (original) {
            // wieder zurueck zum urspruenglichen Kunden
            document.getElementById('rm-customer-id').value = original;
            rmSetScope(original);
        }
        // Wenn nie ein Kunde gesetzt war, bleibt der Toggle ohne Effekt — User koennte
        // theoretisch eine globale Regel "zu Kunde X" verschieben, aber dafuer fehlt die
        // Customer-Auswahl-UI. Heute aus Scope (Sidebar) eindeutig.
    };

    window.rmOpen = async function (ruleId, customerId) {
        await rmLoadCategories();
        await rmLoadProjectTypes();
        const isEdit = ruleId && ruleId > 0;
        document.getElementById('rm-rule-id').value = isEdit ? ruleId : '';
        document.getElementById('rm-customer-id').value = customerId || '';
        document.getElementById('rm-title').textContent = isEdit ? 'Regel bearbeiten' : 'Neue Regel anlegen';
        document.getElementById('rm-save-label').textContent = isEdit ? 'Speichern' : 'Anlegen';
        document.getElementById('rm-delete').style.display = isEdit ? 'inline-flex' : 'none';
        document.getElementById('rm-error').style.display = 'none';
        document.getElementById('rm-warn').style.display = 'none';

        if (isEdit) {
            // Bestehende Regel laden
            try {
                const r = await App.get('/rules?id=' + ruleId);
                if (!r.success) throw new Error(r.message || 'Fehler');
                const rule = r.data;
                document.getElementById('rm-name').value = rule.name || '';
                document.getElementById('rm-content').value = rule.rule_content || '';
                document.getElementById('rm-description').value = rule.description || '';
                document.getElementById('rm-type').value = rule.rule_type || 'style';
                document.getElementById('rm-priority').value = rule.priority || 0;
                document.getElementById('rm-enforcement').value = rule.enforcement || 'strict';
                document.getElementById('rm-applies-to').value = rule.applies_to || 'both';
                document.getElementById('rm-active').checked = !!rule.is_active;
                rmRenderCategoryOptions(rule.category_id);
                document.getElementById('rm-customer-id').value = rule.customer_id || '';
                document.getElementById('rm-original-customer-id').value = rule.customer_id || '';
                rmSetScope(rule.customer_id);
                // Geltungsbereich laden
                try {
                    const sc = await App.get('/admin/rules/' + ruleId + '/scope');
                    rmRenderScopePills(sc.success ? (sc.data?.project_type_ids || []) : []);
                } catch (_) { rmRenderScopePills([]); }
                // Warnhinweis bei globalen Regeln
                if (!rule.customer_id) {
                    const w = document.getElementById('rm-warn');
                    w.innerHTML = '<span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">warning</span> Diese Regel ist <strong>global</strong> — Änderungen wirken auf alle Kunden.';
                    w.style.display = 'block';
                }
            } catch (e) {
                rmError(e.message || 'Konnte Regel nicht laden');
                return;
            }
        } else {
            // Neu — Formular zuruecksetzen
            document.getElementById('rm-name').value = '';
            document.getElementById('rm-content').value = '';
            document.getElementById('rm-description').value = '';
            document.getElementById('rm-type').value = 'style';
            document.getElementById('rm-priority').value = 0;
            document.getElementById('rm-enforcement').value = 'strict';
            document.getElementById('rm-applies-to').value = 'both';
            document.getElementById('rm-active').checked = true;
            rmRenderCategoryOptions(null);
            document.getElementById('rm-original-customer-id').value = customerId || '';
            rmSetScope(customerId);
            rmRenderScopePills([]);
        }
        // Beim oeffnen immer auf Form-Tab starten; KI-Vorschläge zuruecksetzen
        rmAiSuggestions = [];
        const aiList = document.getElementById('rm-ai-list'); if (aiList) aiList.innerHTML = '';
        const aiSug = document.getElementById('rm-ai-suggestions'); if (aiSug) aiSug.style.display = 'none';
        const aiPrompt = document.getElementById('rm-ai-prompt'); if (aiPrompt) aiPrompt.value = '';
        const aiStatus = document.getElementById('rm-ai-status'); if (aiStatus) aiStatus.textContent = '';
        rmSwitchTab('form');

        document.getElementById('rm-modal').style.display = 'flex';
        setTimeout(() => document.getElementById('rm-name').focus(), 50);
    };

    window.rmClose = function () {
        document.getElementById('rm-modal').style.display = 'none';
    };

    function rmError(msg) {
        const e = document.getElementById('rm-error');
        e.textContent = msg;
        e.style.display = 'block';
    }

    window.rmSave = async function () {
        const id = parseInt(document.getElementById('rm-rule-id').value, 10) || 0;
        const customerIdRaw = document.getElementById('rm-customer-id').value;
        const customerId = customerIdRaw ? parseInt(customerIdRaw, 10) : null;
        const payload = {
            name: document.getElementById('rm-name').value.trim(),
            rule_content: document.getElementById('rm-content').value.trim(),
            description: document.getElementById('rm-description').value.trim(),
            rule_type: document.getElementById('rm-type').value,
            category_id: document.getElementById('rm-category').value ? parseInt(document.getElementById('rm-category').value, 10) : null,
            priority: parseInt(document.getElementById('rm-priority').value, 10) || 0,
            enforcement: document.getElementById('rm-enforcement').value || 'strict',
            applies_to: document.getElementById('rm-applies-to').value || 'both',
            is_active: document.getElementById('rm-active').checked ? 1 : 0,
        };
        // Bei Edit: nur dann customer_id mitsenden, wenn der User sie ueber den Toggle
        // veraendert hat (vergleichen mit Original).
        const originalCust = parseInt(document.getElementById('rm-original-customer-id').value || '0', 10) || null;
        if (!id) {
            payload.customer_id = customerId;
        } else if (customerId !== originalCust) {
            // Scope wurde verschoben — null bedeutet "global"
            payload.customer_id = customerId === null ? null : customerId;
        }
        payload.is_active = document.getElementById('rm-active').checked;
        if (!payload.name) { rmError('Name fehlt'); return; }
        if (!payload.rule_content) { rmError('Regel-Inhalt fehlt'); return; }

        const btn = document.getElementById('rm-save');
        btn.disabled = true;
        try {
            const r = id
                ? await App.put('/rules?id=' + id, payload)
                : await App.post('/rules', payload);
            if (!r.success) throw new Error(r.message || 'Fehler');
            const savedId = id || parseInt(r.data?.id, 10) || 0;
            // Geltungsbereich persistieren (nur fuer globale Regeln sinnvoll, aber kostet nix bei custom)
            if (savedId) {
                try {
                    await App.post('/admin/rules/' + savedId + '/scope', { project_type_ids: Array.from(rmScopeSelected) });
                } catch (_) {}
            }
            rmClose();
            document.dispatchEvent(new CustomEvent('rm:saved', {
                detail: { rule_id: savedId, customer_id: customerId, mode: id ? 'edit' : 'create' }
            }));
            App.showNotification(id ? 'Regel gespeichert' : 'Regel "' + payload.name + '" angelegt', 'success');
        } catch (e) {
            rmError(e.message || 'Speichern fehlgeschlagen');
        } finally {
            btn.disabled = false;
        }
    };

    // ===== Tab-Wechsel =====
    let rmAiSuggestions = [];
    window.rmSwitchTab = function (which) {
        document.querySelectorAll('.rm-tab').forEach(t => t.classList.toggle('is-active', t.dataset.tab === which));
        document.getElementById('rm-tab-form').style.display = which === 'form' ? 'flex' : 'none';
        document.getElementById('rm-tab-ai').style.display = which === 'ai' ? 'flex' : 'none';
        const saveBtn = document.getElementById('rm-save');
        const saveLabel = document.getElementById('rm-save-label');
        const delBtn = document.getElementById('rm-delete');
        if (which === 'ai') {
            saveLabel.textContent = 'Ausgewählte übernehmen';
            saveBtn.onclick = rmAiCommit;
            if (delBtn) delBtn.style.display = 'none';
        } else {
            saveLabel.textContent = parseInt(document.getElementById('rm-rule-id').value, 10) ? 'Speichern' : 'Anlegen';
            saveBtn.onclick = rmSave;
        }
    };

    // ===== KI-Vorschläge generieren =====
    window.rmAiSuggest = async function () {
        const text = document.getElementById('rm-ai-prompt').value.trim();
        const status = document.getElementById('rm-ai-status');
        const goBtn = document.getElementById('rm-ai-go');
        if (text.length < 8) { status.textContent = 'Bitte etwas mehr Text.'; status.style.color = 'var(--rose-700)'; return; }
        goBtn.disabled = true;
        status.style.color = 'var(--slate-500)';
        status.innerHTML = '<span class="cw-spinner" style="display:inline-block;width:11px;height:11px;border:2px solid rgba(99,102,241,0.3);border-top-color:#8b5cf6;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:4px;"></span>KI generiert Vorschläge…';
        try {
            const r = await App.post('/admin/rules/ai-suggest', { text, max: 8 });
            if (!r.success) throw new Error(r.message || 'Fehler');
            rmAiSuggestions = (r.data?.suggestions || []).map((s, i) => ({ ...s, _selected: true, _idx: i }));
            rmAiRenderSuggestions();
            status.innerHTML = '<strong>' + rmAiSuggestions.length + '</strong> Vorschlag/Vorschläge — prüfen, ggf. anpassen, dann unten übernehmen.';
            status.style.color = 'var(--emerald-700)';
        } catch (e) {
            rmAiSuggestions = [];
            document.getElementById('rm-ai-suggestions').style.display = 'none';
            status.textContent = e.message || 'Fehler';
            status.style.color = 'var(--rose-700)';
        } finally {
            goBtn.disabled = false;
        }
    };

    function rmAiRenderSuggestions() {
        const wrap = document.getElementById('rm-ai-suggestions');
        const list = document.getElementById('rm-ai-list');
        if (!rmAiSuggestions.length) { wrap.style.display = 'none'; return; }
        wrap.style.display = 'block';
        list.innerHTML = rmAiSuggestions.map((s, i) => `
            <label class="rm-ai-card ${s._selected ? 'is-selected' : ''}" data-idx="${i}">
                <input type="checkbox" ${s._selected ? 'checked' : ''} onchange="rmAiToggle(${i}, this.checked)">
                <div class="rm-ai-card-body">
                    <div class="rm-ai-card-name">${rmEsc(s.name)}</div>
                    <div class="rm-ai-card-content">${rmEsc(s.rule_content)}</div>
                    <div class="rm-ai-card-meta">
                        <span style="background:#fff;padding:1px 6px;border-radius:6px;border:1px solid var(--slate-200);">${rmEsc(s.rule_type)}</span>
                        ${s.category_name ? `<span style="background:#fff;padding:1px 6px;border-radius:6px;border:1px solid var(--slate-200);">${rmEsc(s.category_name)}</span>` : ''}
                        ${s.description ? `<span style="color:var(--slate-400);">· ${rmEsc(s.description)}</span>` : ''}
                    </div>
                </div>
                <button type="button" class="rm-ai-edit-btn" onclick="rmAiEdit(${i})" title="Bearbeiten">
                    <span class="material-symbols-rounded" style="font-size:16px;">edit</span>
                </button>
            </label>
        `).join('');
        rmAiUpdateCount();
    }

    function rmAiUpdateCount() {
        const n = rmAiSuggestions.filter(s => s._selected).length;
        const el = document.getElementById('rm-ai-sel-count');
        if (el) el.textContent = n + ' von ' + rmAiSuggestions.length + ' ausgewählt';
    }

    window.rmAiToggle = function (i, checked) {
        if (rmAiSuggestions[i]) {
            rmAiSuggestions[i]._selected = checked;
            const card = document.querySelector(`.rm-ai-card[data-idx="${i}"]`);
            if (card) card.classList.toggle('is-selected', checked);
            rmAiUpdateCount();
        }
    };
    window.rmAiSelectAll = function (val) {
        rmAiSuggestions.forEach(s => s._selected = val);
        rmAiRenderSuggestions();
    };

    window.rmAiEdit = function (i) {
        const s = rmAiSuggestions[i];
        if (!s) return;
        // Vorschlag ins Form-Tab übernehmen → User kann dort feinjustieren und manuell speichern
        rmSwitchTab('form');
        document.getElementById('rm-name').value = s.name || '';
        document.getElementById('rm-content').value = s.rule_content || '';
        document.getElementById('rm-description').value = s.description || '';
        document.getElementById('rm-type').value = s.rule_type || 'style';
        document.getElementById('rm-category').value = s.category_id || '';
        document.getElementById('rm-active').checked = true;
        document.getElementById('rm-priority').value = 0;
    };

    // Multi-Commit: alle markierten Vorschläge speichern (POST /rules pro Eintrag)
    async function rmAiCommit() {
        const sel = rmAiSuggestions.filter(s => s._selected);
        if (!sel.length) { document.getElementById('rm-error').textContent = 'Bitte mindestens einen Vorschlag auswählen'; document.getElementById('rm-error').style.display = 'block'; return; }
        const customerIdRaw = document.getElementById('rm-customer-id').value;
        const customerId = customerIdRaw ? parseInt(customerIdRaw, 10) : null;
        const btn = document.getElementById('rm-save');
        btn.disabled = true;
        let ok = 0, fail = 0;
        for (const s of sel) {
            try {
                const r = await App.post('/rules', {
                    name: s.name,
                    rule_content: s.rule_content,
                    description: s.description || null,
                    rule_type: s.rule_type,
                    category_id: s.category_id,
                    priority: 0,
                    customer_id: customerId,
                });
                if (r.success) ok++; else fail++;
            } catch (_) { fail++; }
        }
        btn.disabled = false;
        rmClose();
        document.dispatchEvent(new CustomEvent('rm:saved', { detail: { rule_id: 0, customer_id: customerId, mode: 'create-bulk', ok, fail } }));
        App.showNotification(ok + ' Regel(n) angelegt' + (fail ? ' · ' + fail + ' Fehler' : ''), fail ? 'warning' : 'success');
    }

    window.rmDelete = async function () {
        const id = parseInt(document.getElementById('rm-rule-id').value, 10) || 0;
        if (!id) return;
        const name = document.getElementById('rm-name').value;
        if (!confirm('Regel "' + name + '" wirklich löschen?')) return;
        try {
            const r = await App.delete('/rules?id=' + id);
            if (!r.success) throw new Error(r.message || 'Fehler');
            rmClose();
            document.dispatchEvent(new CustomEvent('rm:saved', { detail: { rule_id: id, mode: 'delete' } }));
            App.showNotification('Regel gelöscht', 'success');
        } catch (e) {
            rmError(e.message || 'Löschen fehlgeschlagen');
        }
    };

    // Bei Aenderung der Projekt-Arten (Event aus _project_types_modal.php): Cache invalidieren + Pillen neu rendern
    document.addEventListener('pt:saved', () => {
        rmProjectTypesCache = null;
        rmLoadProjectTypes().then(() => rmRenderScopePills(Array.from(rmScopeSelected)));
    });

    // Esc schliesst Modal
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.getElementById('rm-modal')?.style.display === 'flex') rmClose();
    });
    // Klick auf Backdrop schliesst
    document.getElementById('rm-modal')?.addEventListener('click', e => {
        if (e.target.id === 'rm-modal') rmClose();
    });
})();
</script>
