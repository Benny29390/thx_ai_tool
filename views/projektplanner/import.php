<?php /** @var array $title */ ?>
<style>
.pi-page { /* max-width/padding entfernt — .main-content liefert die Gutter konsistent */ }

.pi-drop {
    border: 2px dashed var(--slate-300); border-radius: var(--d-card-radius);
    padding: 40px 20px; text-align: center;
    background: #fff; cursor: pointer; transition: all 0.15s;
    margin-bottom: 20px;
}
.pi-drop:hover, .pi-drop.is-dragover {
    border-color: var(--thoxan-500); background: var(--thoxan-50);
}
.pi-drop .material-symbols-rounded { font-size: var(--d-fs-2xl); color: var(--slate-400); }
.pi-drop.has-file .material-symbols-rounded { color: var(--emerald-600); }
.pi-drop h3 { margin: 12px 0 4px; color: var(--slate-800); font-size: var(--d-fs-base); }
.pi-drop p { margin: 0; color: var(--slate-500); font-size: var(--d-fs-xs); }
.pi-drop input[type="file"] { display: none; }

.pi-preview {
    background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius);
    padding: 20px; margin-bottom: 20px;
}
.pi-preview h3 { margin: 0 0 12px; font-size: var(--d-fs-base); color: var(--slate-700); }

.pi-row {
    display: grid; grid-template-columns: 1fr 80px 80px 80px;
    padding: 8px 12px; border-bottom: 1px solid var(--slate-100);
    font-size: var(--d-fs-sm); align-items: center;
}
.pi-row:last-child { border-bottom: none; }
.pi-row.is-head {
    font-weight: 600; color: var(--slate-500);
    text-transform: uppercase; font-size: var(--d-fs-xs); letter-spacing: 0.04em;
}
.pi-row.is-head > div:not(:first-child) { text-align: right; }
.pi-row > div:not(:first-child) { text-align: right; font-family: ui-monospace, monospace; }
.pi-num-new    { color: var(--emerald-600); font-weight: 700; }
.pi-num-mapped { color: var(--slate-500); }

.pi-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }

.pi-log {
    background: var(--slate-900); color: var(--slate-200);
    border-radius: var(--d-card-radius); padding: 16px;
    font-family: ui-monospace, monospace; font-size: var(--d-fs-sm);
    max-height: 400px; overflow-y: auto;
    margin-top: 20px; display: none;
}
.pi-log.is-show { display: block; }
.pi-log div { padding: 2px 0; }
</style>

<div class="pi-page">
    <div class="thx-page-header">
        <div>
            <h1 class="thx-page-title">Import aus Tallyr</h1>
            <div class="thx-page-subtitle">
                JSON-Export aus dem alten Tallyr-WordPress-Tool hier hochladen.
                Kunden werden per Slug/Name gemappt, Team-Mitglieder per Name. Pläne und Zeilen werden immer neu angelegt.
            </div>
        </div>
        <div class="thx-page-actions">
            <a href="/admin/projektplanner" class="thx-btn thx-btn-secondary">
                <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
                Zum Editor
            </a>
        </div>
    </div>

    <div style="background:var(--rose-50);border:1px solid var(--rose-200);border-radius:10px;padding:14px 18px;margin-bottom:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <strong style="color:var(--rose-700);">Vor Re-Import: Projektplanner-Daten leeren</strong>
                <div style="font-size:var(--d-fs-xs);color:var(--rose-600);margin-top:2px;">
                    Löscht alle Pläne, Plan-Zeilen, Feedback, Revisions, Plan-Permissions und Personen-Sharelinks.
                    <strong>Kunden, Team-Mitglieder und Settings bleiben unberührt.</strong>
                </div>
            </div>
            <button class="thx-btn thx-btn-secondary thx-btn-danger" onclick="piOpenResetModal()">
                <span class="material-symbols-rounded" style="font-size:16px;">delete_forever</span>
                Daten leeren
            </button>
        </div>
    </div>

    <div class="pi-drop" id="pi-drop">
        <input type="file" id="pi-file" accept="application/json,.json" onchange="piHandleFile(this.files[0])">
        <span class="material-symbols-rounded">cloud_upload</span>
        <h3 id="pi-drop-title">JSON-Datei hier ablegen oder klicken</h3>
        <p id="pi-drop-info">Max 20 MB. Erwartet wird Tallyr-Export-Format v1.0 oder das echte Tallyr-Wordpress-Format.</p>
    </div>

    <div class="pi-preview" id="pi-preview" style="display:none;">
        <h3>Vorschau</h3>
        <div class="pi-row is-head">
            <div>Objekt</div>
            <div>Total</div>
            <div>Neu</div>
            <div>Gemappt</div>
        </div>
        <div id="pi-preview-body"></div>
        <div class="pi-actions">
            <button class="thx-btn thx-btn-secondary" onclick="piReset()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" id="pi-start-btn" onclick="piStartImport()">
                <span class="material-symbols-rounded" style="font-size:16px;">play_arrow</span>
                Import starten
            </button>
        </div>
    </div>

    <div class="pi-log" id="pi-log"></div>

    <!-- Reset-Modal -->
    <div class="thx-modal-backdrop" id="pi-reset-modal" style="display:none;"
         onclick="if(event.target===this)piCloseResetModal()">
        <div class="thx-modal" style="width:520px;">
            <div class="thx-modal-header">
                <h3 class="thx-modal-title" style="color:var(--rose-700);">Projektplanner-Daten leeren</h3>
                <button class="thx-modal-close" onclick="piCloseResetModal()">&times;</button>
            </div>
            <div class="thx-modal-body" style="padding:18px;">
                <div style="background:var(--rose-50);border-radius:6px;padding:12px;margin-bottom:14px;font-size:var(--d-fs-sm);">
                    <strong style="color:var(--rose-700);">Das wird gelöscht:</strong>
                    <ul style="margin:6px 0 0;padding-left:20px;color:var(--slate-700);">
                        <li>Alle Pläne + Plan-Zeilen + Plan-Permissions + Feedback + Revisions</li>
                        <li>Alle Personen-Sharelinks</li>
                    </ul>
                    <strong style="color:var(--rose-700);">Das bleibt:</strong>
                    <ul style="margin:6px 0 0;padding-left:20px;color:var(--slate-700);">
                        <li>Kunden, Team-Mitglieder, Asana-Settings, Sistrix etc.</li>
                        <li>Kunden-Budgets (außer Du wählst es explizit ab)</li>
                    </ul>
                </div>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:var(--d-fs-sm);">
                    <input type="checkbox" id="pi-reset-also-budgets">
                    <span>Auch Kunden-Budgets löschen</span>
                </label>
                <div style="font-size:var(--d-fs-sm);color:var(--slate-700);margin-bottom:6px;">
                    Zur Bestätigung tippe exakt: <code style="background:var(--slate-100);padding:2px 6px;border-radius:3px;">PROJEKTPLANNER LEEREN</code>
                </div>
                <input type="text" id="pi-reset-confirm" placeholder="PROJEKTPLANNER LEEREN"
                       style="width:100%;padding:8px 10px;border:1px solid var(--slate-200);border-radius:6px;font-family:ui-monospace,monospace;font-size:var(--d-fs-sm);">
            </div>
            <div class="thx-modal-footer">
                <button class="thx-btn thx-btn-secondary" onclick="piCloseResetModal()">Abbrechen</button>
                <button class="thx-btn thx-btn-danger thx-btn-danger-solid" onclick="piDoReset()">Endgültig leeren</button>
            </div>
        </div>
    </div>

    <div style="background:var(--amber-50);color:var(--amber-800);border:1px solid var(--amber-200);border-radius:8px;padding:12px 16px;margin-top:20px;font-size:var(--d-fs-sm);">
        <strong>So gibt es einen Export aus dem alten Tallyr:</strong><br>
        Im laufenden WordPress-Tallyr den URL aufrufen:<br>
        <code style="background:var(--amber-100);padding:2px 6px;border-radius:3px;">/?tallyr_export=projektplanner&amp;key=&lt;SECRET&gt;</code><br>
        Der Secret-Key steht im Tallyr-Code (<code>inc/export-projektplanner.php</code>). Die heruntergeladene JSON-Datei dann hier importieren.
    </div>
</div>

<script>
'use strict';
const piState = { file: null, data: null, customers: [], mapping: {} };

// Kunden-Liste für Dropdowns laden
(async () => {
    try {
        const r = await fetch('/api/v1/admin/customers');
        const j = await r.json();
        piState.customers = (j.data || []).filter(c => c.is_active).sort((a,b) => (a.name||'').localeCompare(b.name||''));
    } catch (_) {}
})();

const dropZone = document.getElementById('pi-drop');
dropZone.addEventListener('click', () => document.getElementById('pi-file').click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('is-dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('is-dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('is-dragover');
    if (e.dataTransfer.files.length) piHandleFile(e.dataTransfer.files[0]);
});

async function piHandleFile(file) {
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.json')) {
        App.showNotification('Bitte eine .json-Datei wählen', 'error'); return;
    }
    piState.file = file;
    dropZone.classList.add('has-file');
    document.getElementById('pi-drop-title').textContent = file.name;
    document.getElementById('pi-drop-info').textContent = `${(file.size/1024).toFixed(1)} KB — Datei wird geprüft…`;
    try {
        const text = await file.text();
        piState.data = JSON.parse(text);
        await piPreview();
    } catch (e) {
        App.showNotification('Datei nicht lesbar: ' + e.message, 'error');
        piReset();
    }
}

async function piPreview() {
    try {
        const r = await fetch('/api/v1/admin/projektplanner/import/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify(piState.data),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        piRenderPreview(j.data.preview);
    } catch (e) {
        App.showNotification('Vorschau fehlgeschlagen: ' + e.message, 'error');
    }
}

function piRenderPreview(p) {
    document.getElementById('pi-drop-info').textContent = `${(piState.file.size/1024).toFixed(1)} KB — bereit für Import`;
    const labels = {
        customers: 'Kunden',
        team_members: 'Team-Mitglieder',
        plans: 'Pläne',
        plan_rows: 'Plan-Zeilen',
        customer_budgets: 'Kunden-Budgets',
        person_shares: 'Personen-Sharelinks',
    };
    const body = document.getElementById('pi-preview-body');
    body.innerHTML = Object.entries(labels).map(([k, label]) => {
        const v = p[k] || { total: 0, new: 0, mapped: 0 };
        const unmappedNote = v.unmapped ? ` <span style="color:var(--amber-600);font-size:10px;">(${v.unmapped} mit unbekanntem Kunden)</span>` : '';
        return `<div class="pi-row">
            <div>${label}${unmappedNote}</div>
            <div>${v.total || 0}</div>
            <div class="pi-num-new">${v.new || 0}</div>
            <div class="pi-num-mapped">${v.mapped || 0}</div>
        </div>`;
    }).join('');

    // Unmappable Kunden: Mapping-Dropdown pro Kunde
    const unmapped = (p.customers && p.customers.unmapped_list) || [];
    let extraHtml = '';
    if (unmapped.length > 0) {
        piState.mapping = {}; // reset
        const custOpts = piState.customers.map(c =>
            `<option value="${c.id}">${(c.name||'').replace(/</g,'&lt;')} (${c.abbreviation || c.slug || c.id})</option>`
        ).join('');
        extraHtml = `
            <div style="margin-top:18px;padding:12px 14px;background:var(--amber-50);border:1px solid var(--amber-200);border-radius:8px;">
                <div style="font-weight:600;color:var(--amber-800);margin-bottom:8px;">
                    ⚠️ ${unmapped.length} Tallyr-Kunde${unmapped.length === 1 ? '' : 'n'} ${unmapped.length === 1 ? 'wurde' : 'wurden'} im KI-Tool nicht automatisch erkannt — bitte zuordnen:
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    ${unmapped.map(c => `
                        <div style="display:grid;grid-template-columns:1fr 60px 280px;gap:8px;align-items:center;padding:6px 10px;background:#fff;border-radius:4px;border:1px solid var(--amber-200);">
                            <div style="font-size:var(--d-fs-xs);">
                                <strong>${c.title.replace(/</g,'&lt;')}</strong>
                                ${c.shortdesc ? ` <span style="color:var(--slate-400);">(${c.shortdesc.replace(/</g,'&lt;')})</span>` : ''}
                            </div>
                            <div style="font-size:11px;color:var(--slate-500);text-align:right;">${c.plans} Plan${c.plans === 1 ? '' : 'e'}</div>
                            <select class="pi-map-select" data-tallyr-id="${c.tallyr_id}" onchange="piSetMapping('${c.tallyr_id}', this.value)" style="padding:5px 8px;border:1px solid var(--slate-200);border-radius:4px;font-size:var(--d-fs-xs);">
                                <option value="skip" selected>↪ Überspringen (Pläne nicht importieren)</option>
                                <option value="new">＋ Als neuen Kunden anlegen</option>
                                <optgroup label="Auf existierenden Kunden mappen">
                                    ${custOpts}
                                </optgroup>
                            </select>
                        </div>
                    `).join('')}
                </div>
                <div style="font-size:11px;color:var(--slate-600);margin-top:10px;line-height:1.5;">
                    Standard ist „Überspringen". Wähle einen existierenden Kunden, wenn der Tallyr-Kunde nur anders heißt — die Tallyr-Pläne werden dann an Deinen KI-Tool-Kunden gehängt.
                </div>
            </div>`;
    }

    body.innerHTML += extraHtml;

    // Pre-fill der mapping-Map auf "skip" für alle unmappables
    unmapped.forEach(c => { piState.mapping[c.tallyr_id] = 'skip'; });
    document.getElementById('pi-preview').style.display = 'block';
}

function piSetMapping(tallyrId, value) {
    piState.mapping[tallyrId] = value;
}

function piOpenResetModal() {
    document.getElementById('pi-reset-confirm').value = '';
    document.getElementById('pi-reset-also-budgets').checked = false;
    document.getElementById('pi-reset-modal').style.display = 'flex';
    setTimeout(() => document.getElementById('pi-reset-confirm').focus(), 50);
}
function piCloseResetModal() { document.getElementById('pi-reset-modal').style.display = 'none'; }

async function piDoReset() {
    const confirm = document.getElementById('pi-reset-confirm').value.trim();
    if (confirm !== 'PROJEKTPLANNER LEEREN') {
        App.showNotification('Confirm-Text falsch — bitte exakt „PROJEKTPLANNER LEEREN" eingeben', 'error');
        return;
    }
    const alsoBudgets = document.getElementById('pi-reset-also-budgets').checked;
    try {
        const r = await fetch('/api/v1/admin/projektplanner/import/reset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ confirm, also_customer_budgets: alsoBudgets }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const stats = j.data.deleted || {};
        const total = Object.values(stats).reduce((a, b) => a + b, 0);
        App.showNotification(`${total} Datensätze gelöscht — bereit für den Re-Import`, 'success');
        piCloseResetModal();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function piStartImport() {
    if (!piState.data) return;
    const btn = document.getElementById('pi-start-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;">hourglass_top</span> Läuft…';
    try {
        const payload = Object.assign({}, piState.data, {
            _customer_mapping: piState.mapping || {},
        });
        const r = await fetch('/api/v1/admin/projektplanner/import', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify(payload),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const im = j.data.imported || {};
        App.showNotification(`Import: ${im.customers || 0} Kunden, ${im.plans || 0} Pläne, ${im.rows || 0} Zeilen`, 'success');
        const log = document.getElementById('pi-log');
        log.classList.add('is-show');
        log.innerHTML = (j.data.log || []).map(line => `<div>${line.replace(/&/g,'&amp;').replace(/</g,'&lt;')}</div>`).join('');
        log.innerHTML += '<div style="margin-top:10px;color:var(--emerald-400);">✓ Fertig. <a href="/admin/projektplanner" style="color:#58a6ff;">→ Zum Editor</a></div>';
    } catch (e) {
        App.showNotification('Import fehlgeschlagen: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;">play_arrow</span> Import starten';
    }
}

function piReset() {
    piState.file = null;
    piState.data = null;
    document.getElementById('pi-file').value = '';
    dropZone.classList.remove('has-file');
    document.getElementById('pi-drop-title').textContent = 'JSON-Datei hier ablegen oder klicken';
    document.getElementById('pi-drop-info').textContent = 'Max 20 MB.';
    document.getElementById('pi-preview').style.display = 'none';
    document.getElementById('pi-log').classList.remove('is-show');
}
</script>
