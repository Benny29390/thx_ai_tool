<?php
/* Transkription — Upload-Tab */
$db = \Core\Database::getInstance();
$customers = [];
if (\Core\Auth::isAdmin()) {
    $customers = $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
} else {
    $customers = \Core\Auth::customers();
}
$models = [
    ['id' => 'tiny',     'label' => 'Tiny (sehr schnell, leichte Verlust)'],
    ['id' => 'base',     'label' => 'Base (schnell)'],
    ['id' => 'small',    'label' => 'Small'],
    ['id' => 'medium',   'label' => 'Medium (empfohlen)'],
    ['id' => 'large-v3', 'label' => 'Large v3 (beste Qualitaet, langsam)'],
];
$activeCustomerId = \Core\Auth::customerId() ?? 0;

// Auto-Pipeline-Defaults aus Settings
$_defTplStr = (string)\Core\Settings::get('transkription_default_templates');
$defaultAutoTpls = $_defTplStr !== '' ? array_filter(array_map('trim', explode(',', $_defTplStr))) : [];
$defaultKnowledgeTpl = (string)\Core\Settings::get('transkription_default_knowledge_template');
$loomSdkAppId = (string)\Core\Settings::get('loom_sdk_public_app_id');

$autoTplOptions = [
    ['id' => 'memo',     'label' => 'Kurz-Memo'],
    ['id' => 'workshop', 'label' => 'Workshop-Protokoll (DOCX)'],
    ['id' => 'call',     'label' => 'Call-Notiz'],
    ['id' => 'tutorial', 'label' => 'Tutorial-Doku'],
];
?>
<style>
.tr-upload-card { background:#fff; border:1px solid var(--slate-200); border-radius:var(--d-card-radius); padding:var(--d-card-pad); margin-bottom:var(--d-section-gap); }
.tr-upload-zone {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:8px; min-height:120px;
    border:2px dashed var(--slate-300); border-radius:var(--d-card-radius);
    padding:24px; text-align:center; cursor:pointer;
    transition:background .15s, border-color .15s;
}
.tr-upload-zone:hover, .tr-upload-zone.is-drag { background:var(--thoxan-50); border-color:var(--thoxan-400); }
.tr-upload-zone .material-symbols-rounded { font-size:36px; line-height:1; color:var(--slate-400); display:block; }
.tr-upload-zone p { margin:0; color:var(--slate-600); font-size:var(--d-fs-sm); }
.tr-form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:var(--d-row-gap); margin-top:var(--d-section-gap); }
.tr-form-grid label { font-size:var(--d-fs-xs); color:var(--slate-600); display:block; margin-bottom:4px; }
.tr-form-grid .thx-input, .tr-form-grid .thx-select { width:100%; }
.tr-loom-row { display:flex; gap:8px; align-items:flex-end; margin-top:var(--d-section-gap); }
.tr-loom-row > div { flex:1; }
</style>

<?php if ($loomSdkAppId !== ''): ?>
<div class="tr-upload-card" id="tr-loom-record-card" style="border-left:4px solid var(--thoxan-600);display:none;">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Direkt im Tool aufnehmen (optional)</h3>
    <p style="margin:0 0 var(--d-section-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Loom-Recorder im Browser. Fuer den Regelfall ist Make.com / Zapier (Auto-Import-Tab) besser geeignet — die fangen
        Aufnahmen unabhaengig davon ab, ob dieser Tab gerade offen ist.
    </p>
    <button id="tr-loom-record-btn" class="thx-btn thx-btn-primary" style="font-size:var(--d-fs-base);padding:10px 18px;">
        <span class="material-symbols-rounded" style="font-size:20px;vertical-align:-4px;">videocam</span>
        Aufnahme starten
    </button>
    <span id="tr-loom-record-status" style="margin-left:12px;font-size:var(--d-fs-xs);color:var(--slate-500);"></span>
</div>
<?php endif; ?>

<div class="tr-upload-card">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Audio oder Video hochladen</h3>
    <p style="margin:0 0 var(--d-section-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Erlaubte Formate: mp3, m4a, wav, ogg, flac, aac, webm, mp4, mov, mkv. Maximal 500 MB.
        Die Datei wird at-rest verschluesselt und nach erfolgreicher Transkription geloescht.
    </p>

    <label class="tr-upload-zone" id="tr-drop">
        <span class="material-symbols-rounded">graphic_eq</span>
        <p><strong>Datei waehlen</strong> oder hier hineinziehen</p>
        <input type="file" id="tr-file" accept="audio/*,video/*,.mp3,.m4a,.wav,.ogg,.flac,.aac,.webm,.mp4,.mov,.mkv" style="display:none;">
    </label>

    <div class="tr-form-grid">
        <?php if (count($customers) > 1 || \Core\Auth::isAdmin()): ?>
        <div>
            <label>Kunde <span style="color:var(--rose-600);">*</span></label>
            <select class="thx-select" id="tr-customer" required>
                <option value="">⚡ KI wählt Kunden</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$activeCustomerId?'selected':'' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color:var(--slate-500);font-size:var(--d-fs-xs);">Wenn leer: KI versucht den Kunden automatisch zu erkennen.</small>
        </div>
        <?php endif; ?>
        <div>
            <label>Modell</label>
            <select class="thx-select" id="tr-model">
                <?php foreach ($models as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $m['id']==='medium'?'selected':'' ?>><?= htmlspecialchars($m['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Verarbeitung</label>
            <select class="thx-select" id="tr-backend">
                <option value="local" selected>Lokal (mit Sprecher-Trennung)</option>
                <option value="remote">Remote-GPU (schneller, ohne Sprecher-Trennung)</option>
            </select>
            <small style="color:var(--slate-500);font-size:var(--d-fs-xs);">Remote: schnelles large-v3 auf GPU. Erster Aufruf kann 2–5 Min dauern. Keine Sprecher-Trennung.</small>
        </div>
        <div>
            <label>Sprache (optional)</label>
            <select class="thx-select" id="tr-language">
                <option value="">automatisch</option>
                <option value="de">Deutsch</option>
                <option value="en">Englisch</option>
                <option value="fr">Franzoesisch</option>
                <option value="es">Spanisch</option>
                <option value="it">Italienisch</option>
            </select>
        </div>
        <div>
            <label>Sprecher-Modus</label>
            <select class="thx-select" id="tr-speaker-mode">
                <option value="multi">Mehrere Sprecher (Diarization)</option>
                <option value="single">Einzelsprecher (Diktat)</option>
            </select>
        </div>
        <div>
            <label>Einwilligungs-Notiz (optional)</label>
            <input class="thx-input" type="text" id="tr-consent" placeholder="z.B. 'OK von allen Teilnehmern, 25.05.2026'">
        </div>
    </div>

    <div style="margin-top:var(--d-section-gap);padding-top:var(--d-row-gap);border-top:1px dashed var(--slate-200);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <strong style="font-size:var(--d-fs-sm);">Automatisch nach Transkription erzeugen</strong>
            <small style="color:var(--slate-500);font-size:var(--d-fs-xs);">Defaults aus den Einstellungen — pro Upload anpassbar</small>
        </div>
        <div id="tr-auto-tpls" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:8px;">
            <?php foreach ($autoTplOptions as $opt): ?>
                <label style="font-size:var(--d-fs-sm);display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" class="tr-auto-tpl" value="<?= $opt['id'] ?>"
                        <?= in_array($opt['id'], $defaultAutoTpls, true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($opt['label']) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <label style="font-size:var(--d-fs-sm);color:var(--slate-700);">Ins Wissen einspeisen mit:</label>
            <select class="thx-select" id="tr-auto-kn-tpl">
                <option value="" <?= $defaultKnowledgeTpl === '' ? 'selected' : '' ?>>— nicht ins Wissen —</option>
                <?php foreach ($autoTplOptions as $opt): ?>
                    <option value="<?= $opt['id'] ?>" <?= $defaultKnowledgeTpl === $opt['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div id="tr-upload-status" style="margin-top:var(--d-section-gap);font-size:var(--d-fs-sm);"></div>
</div>

<div class="tr-upload-card">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Loom-Aufzeichnung importieren</h3>
    <p style="margin:0 0 var(--d-section-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Loom-Share-URL einfuegen — wir holen das Audio per yt-dlp und reihen einen Transkriptions-Job ein.
        Es gelten dieselben Optionen wie oben (Kunde, Modell, Sprache, Sprecher).
    </p>
    <div class="tr-loom-row">
        <div>
            <label style="font-size:var(--d-fs-xs);color:var(--slate-600);display:block;margin-bottom:4px;">Loom-URL</label>
            <input class="thx-input" type="text" id="tr-loom-url" placeholder="https://www.loom.com/share/..." style="width:100%;">
        </div>
        <button class="thx-btn thx-btn-primary" onclick="trImportLoom()">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">download</span>
            Importieren
        </button>
    </div>
    <div id="tr-loom-status" style="margin-top:var(--d-row-gap);font-size:var(--d-fs-sm);"></div>
</div>

<div class="tr-upload-card">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Bulk-Import: viele Loom-URLs auf einmal</h3>
    <p style="margin:0 0 var(--d-section-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Eine Loom-URL pro Zeile (oder durch Komma getrennt). Bereits importierte URLs werden uebersprungen.
        Praktisch fuer das initiale Einsammeln aller bestehenden Aufnahmen.
    </p>
    <textarea id="tr-bulk-urls" class="thx-textarea" rows="6" placeholder="https://www.loom.com/share/aaaa...&#10;https://www.loom.com/share/bbbb...&#10;https://www.loom.com/share/cccc..." style="width:100%;font-family:ui-monospace,monospace;font-size:var(--d-fs-sm);"></textarea>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:var(--d-row-gap);">
        <span id="tr-bulk-status" style="font-size:var(--d-fs-sm);color:var(--slate-500);"></span>
        <button class="thx-btn thx-btn-primary" onclick="trBulkImport()">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">playlist_add</span>
            Alle einreihen
        </button>
    </div>
</div>

<script>
'use strict';
// "Neuen Kunden anlegen…" an Kunden-Select anhaengen
(function() {
    function attach() {
        const sel = document.getElementById('tr-customer');
        if (sel && window.ThxQuickCustomer) ThxQuickCustomer.attachToSelect(sel);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attach);
    else attach();
})();
function trEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function trOpts() {
    const cust = document.getElementById('tr-customer');
    const autoTpls = Array.from(document.querySelectorAll('.tr-auto-tpl:checked')).map(el => el.value);
    return {
        customer_id:             cust ? parseInt(cust.value, 10) || 0 : 0,
        model:                   document.getElementById('tr-model').value,
        transcription_backend:   document.getElementById('tr-backend').value,
        language:                document.getElementById('tr-language').value || null,
        speaker_mode:            document.getElementById('tr-speaker-mode').value,
        consent_ref:             document.getElementById('tr-consent').value || null,
        auto_templates:          autoTpls.join(','),
        auto_knowledge_template: document.getElementById('tr-auto-kn-tpl').value || '',
    };
}

async function trUpload(file) {
    const status = document.getElementById('tr-upload-status');
    status.innerHTML = '<span style="color:var(--thoxan-700);">Lade hoch + reihe Job ein: ' + trEsc(file.name) + ' …</span>';
    const fd = new FormData();
    fd.append('file', file);
    const o = trOpts();
    Object.keys(o).forEach(k => { if (o[k] !== null && o[k] !== '') fd.append(k, o[k]); });
    try {
        const r = await fetch('/api/v1/admin/transkription/jobs', {
            method: 'POST',
            headers: { 'X-CSRF-Token': App.csrfToken },
            body: fd,
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        status.innerHTML = '<span style="color:var(--emerald-700);">✓ ' + trEsc(j.message) + '</span>';
        App.showNotification(j.message, 'success');
        setTimeout(() => { window.location.href = '/admin/transkription?tab=jobs'; }, 800);
    } catch (e) {
        status.innerHTML = '<span style="color:var(--rose-700);">✗ ' + trEsc(e.message) + '</span>';
        App.showNotification(e.message, 'error');
    }
}

async function trImportLoom() {
    const url = document.getElementById('tr-loom-url').value.trim();
    const status = document.getElementById('tr-loom-status');
    if (!url) { status.innerHTML = '<span style="color:var(--rose-700);">Bitte eine Loom-URL eintragen.</span>'; return; }
    status.innerHTML = '<span style="color:var(--thoxan-700);">Hole Audio per yt-dlp …</span>';
    const body = Object.assign({ loom_url: url }, trOpts());
    try {
        const r = await fetch('/api/v1/admin/transkription/jobs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify(body),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        status.innerHTML = '<span style="color:var(--emerald-700);">✓ ' + trEsc(j.message) + '</span>';
        App.showNotification(j.message, 'success');
        setTimeout(() => { window.location.href = '/admin/transkription?tab=jobs'; }, 800);
    } catch (e) {
        status.innerHTML = '<span style="color:var(--rose-700);">✗ ' + trEsc(e.message) + '</span>';
        App.showNotification(e.message, 'error');
    }
}

(function() {
    const zone = document.getElementById('tr-drop');
    const input = document.getElementById('tr-file');
    zone.addEventListener('click', () => input.click());
    input.addEventListener('change', () => { if (input.files[0]) trUpload(input.files[0]); });
    ['dragenter','dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('is-drag'); }));
    ['dragleave','drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('is-drag'); }));
    zone.addEventListener('drop', e => { if (e.dataTransfer.files[0]) trUpload(e.dataTransfer.files[0]); });
})();

window.trBulkImport = async function() {
    const txt = document.getElementById('tr-bulk-urls').value;
    const urls = txt.split(/[\s,]+/).map(s => s.trim()).filter(s => /^https?:\/\/(www\.)?loom\.com\/(share|embed)\//i.test(s));
    if (!urls.length) { App.showNotification('Keine gueltigen Loom-URLs gefunden', 'error'); return; }
    if (!confirm(urls.length + ' Loom-URL(s) einreihen?')) return;
    const status = document.getElementById('tr-bulk-status');
    const opts = trOpts();
    let ok = 0, skip = 0, fail = 0;
    for (let i = 0; i < urls.length; i++) {
        status.textContent = `Importiere ${i+1} / ${urls.length} …`;
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                body: JSON.stringify(Object.assign({ loom_url: urls[i] }, opts)),
            });
            const j = await r.json();
            if (j.success) ok++;
            else if (/bereits|duplikat|exist/i.test(j.message || '')) skip++;
            else { fail++; console.warn('Bulk-Fail', urls[i], j.message); }
        } catch (e) { fail++; console.warn('Bulk-Error', urls[i], e); }
    }
    status.innerHTML = `<span style="color:var(--emerald-700);">✓ ${ok} eingereiht</span>${skip?` · <span style="color:var(--slate-600);">${skip} uebersprungen</span>`:''}${fail?` · <span style="color:var(--rose-700);">${fail} fehlgeschlagen</span>`:''}`;
    if (ok > 0) {
        document.getElementById('tr-bulk-urls').value = '';
        App.showNotification(ok + ' Loom-Jobs eingereiht', 'success');
    }
};

<?php if ($loomSdkAppId !== ''): ?>
/* Loom Record SDK: optional. Bei Fehler einfach Card ausgeblendet lassen. */
(async function() {
    const PUBLIC_APP_ID = <?= json_encode($loomSdkAppId) ?>;
    const card = document.getElementById('tr-loom-record-card');
    const button = document.getElementById('tr-loom-record-btn');
    const statusEl = document.getElementById('tr-loom-record-status');
    if (!button) return;

    let setup, isSupported;
    try {
        ({ setup }       = await import('https://esm.sh/@loomhq/record-sdk'));
        ({ isSupported } = await import('https://esm.sh/@loomhq/record-sdk/is-supported'));
    } catch (e) {
        console.warn('[Loom SDK] CDN-Import fehlgeschlagen — Recorder bleibt aus.', e);
        return; // Card bleibt versteckt
    }

    try {
        const sup = await isSupported();
        if (!sup.supported) {
            console.warn('[Loom SDK] Browser nicht unterstuetzt:', sup.error);
            return;
        }
        const { configureButton } = await setup({ publicAppId: PUBLIC_APP_ID });
        const sdkButton = configureButton({ element: button });
        card.style.display = ''; // erst jetzt sichtbar
        statusEl.textContent = 'bereit';
        statusEl.style.color = 'var(--emerald-700)';

        sdkButton.on('recording-start', () => { statusEl.textContent = 'Aufnahme laeuft …'; statusEl.style.color = 'var(--amber-700)'; });
        sdkButton.on('cancel',          () => { statusEl.textContent = 'abgebrochen';      statusEl.style.color = 'var(--slate-500)'; });
        sdkButton.on('complete', async (video) => {
            statusEl.textContent = 'Upload zu Loom fertig — reihe in Pipeline ein …';
            statusEl.style.color = 'var(--thoxan-700)';
            const o = trOpts();
            try {
                const r = await fetch('/api/v1/admin/transkription/jobs', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                    body: JSON.stringify(Object.assign({ loom_url: video.sharedUrl }, o)),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                statusEl.innerHTML = '✓ Job #' + j.data.job_id + ' eingereiht — <a href="/admin/transkription?tab=jobs" style="color:var(--thoxan-700);">Jobs ansehen</a>';
                statusEl.style.color = 'var(--emerald-700)';
                App.showNotification(j.message, 'success');
            } catch (e) {
                statusEl.textContent = '✗ ' + e.message;
                statusEl.style.color = 'var(--rose-700)';
                App.showNotification(e.message, 'error');
            }
        });
    } catch (e) {
        console.warn('[Loom SDK] Init fehlgeschlagen:', e);
        // Card bleibt versteckt
    }
})();
<?php endif; ?>
</script>
