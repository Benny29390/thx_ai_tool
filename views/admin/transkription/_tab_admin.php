<?php /* Transkription — Admin-Prompts (nur Admin) */ ?>
<?php if (!\Core\Auth::isAdmin()): ?>
    <div class="thx-card"><p>Nur fuer Administratoren.</p></div>
<?php return; endif; ?>
<style>
.tr-a-card { background:#fff; border:1px solid var(--slate-200); border-radius:var(--d-card-radius); padding:var(--d-card-pad); margin-bottom:var(--d-section-gap); }
.tr-a-grid { display:grid; grid-template-columns:160px 1fr 120px 80px 80px; gap:8px; align-items:center; padding:6px 0; border-bottom:1px solid var(--slate-100); }
.tr-a-grid:first-of-type { border-top:1px solid var(--slate-200); padding-top:12px; }
.tr-a-grid label { font-size:var(--d-fs-xs);color:var(--slate-600); }
.tr-a-prompt { width:100%; min-height:80px; padding:var(--d-control-pad-y) var(--d-control-pad-x); border:1px solid var(--slate-300); border-radius:var(--d-card-radius); font-size:var(--d-fs-sm); font-family:ui-monospace,monospace; }
</style>

<div class="tr-a-card">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Auto-Pipeline-Defaults</h3>
    <p style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Welche Vorlagen werden automatisch erzeugt, sobald die Transkription fertig ist? Der User kann pro Upload abweichen.
    </p>
    <div id="tr-set-tpls" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:var(--d-row-gap);">
        <?php foreach (['memo'=>'Kurz-Memo','workshop'=>'Workshop-DOCX','call'=>'Call-Notiz','tutorial'=>'Tutorial'] as $k => $l): ?>
            <label style="font-size:var(--d-fs-sm);display:flex;gap:6px;align-items:center;cursor:pointer;">
                <input type="checkbox" class="tr-set-tpl" value="<?= $k ?>"> <?= htmlspecialchars($l) ?>
            </label>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:var(--d-row-gap);">
        <label style="font-size:var(--d-fs-sm);">Default „Ins Wissen einspeisen mit":</label>
        <select id="tr-set-kn-tpl" style="padding:var(--d-control-pad-y) var(--d-control-pad-x);border:1px solid var(--slate-300);border-radius:var(--d-card-radius);font-size:var(--d-fs-sm);">
            <option value="">— nichts —</option>
            <option value="memo">Kurz-Memo</option>
            <option value="workshop">Workshop-DOCX</option>
            <option value="call">Call-Notiz</option>
            <option value="tutorial">Tutorial</option>
        </select>
    </div>

    <h3 style="margin:var(--d-section-gap) 0 var(--d-row-gap);font-size:var(--d-fs-base);">Sprecher-Erkennung (Diarization)</h3>
    <p style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Ohne Token landet alles als „SPEAKER_00". Mit einem HuggingFace-Token (kostenlos, <code>hf_...</code>) wird per pyannote
        automatisch zwischen Sprechern unterschieden. Lizenzbedingung des Modells: einmal auf
        <a href="https://huggingface.co/pyannote/speaker-diarization-3.1" target="_blank" style="color:var(--thoxan-700);">huggingface.co/pyannote/speaker-diarization-3.1</a>
        einloggen, „Accept" klicken, Token unter Settings → Access Tokens erzeugen.
    </p>
    <div style="display:flex;gap:8px;align-items:center;">
        <input id="tr-set-hf" type="password" placeholder="hf_xxxx (leer lassen = unveraendert)"
            style="flex:1;padding:var(--d-control-pad-y) var(--d-control-pad-x);border:1px solid var(--slate-300);border-radius:var(--d-card-radius);font-size:var(--d-fs-sm);">
        <span id="tr-set-hf-status" style="font-size:var(--d-fs-xs);color:var(--slate-500);"></span>
    </div>

    <h3 style="margin:var(--d-section-gap) 0 var(--d-row-gap);font-size:var(--d-fs-base);">Remote-Whisper (GPU)</h3>
    <p style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Schnelles <code>large-v3</code> auf eigener GPU als Alternative zum lokalen Runner — pro Upload unter „Verarbeitung" auswaehlbar.
        Diese Variante macht <strong>keine Sprecher-Trennung</strong>. Der erste Aufruf eines Modells kann 2–5 Min dauern (Modell wird geladen).
        Authentifizierung ueber denselben API-Key wie der lokale Inference-Server (Einstellungen → KI).
    </p>
    <div style="display:grid;gap:8px;">
        <label style="font-size:var(--d-fs-sm);color:var(--slate-700);">Endpoint-URL
            <input id="tr-set-whisper-url" type="text" placeholder="https://ki.thoxan.com/whisper/v1/audio/transcriptions"
                style="width:100%;margin-top:4px;padding:var(--d-control-pad-y) var(--d-control-pad-x);border:1px solid var(--slate-300);border-radius:var(--d-card-radius);font-size:var(--d-fs-sm);">
        </label>
        <label style="font-size:var(--d-fs-sm);color:var(--slate-700);">Modellname
            <input id="tr-set-whisper-model" type="text" placeholder="Systran/faster-whisper-large-v3"
                style="width:100%;margin-top:4px;padding:var(--d-control-pad-y) var(--d-control-pad-x);border:1px solid var(--slate-300);border-radius:var(--d-card-radius);font-size:var(--d-fs-sm);">
        </label>
    </div>

    <div style="margin-top:var(--d-section-gap);text-align:right;">
        <button class="thx-btn thx-btn-primary" onclick="trSetSave()">Defaults speichern</button>
    </div>
</div>

<div class="tr-a-card">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Output-Vorlagen / LLM-Prompts</h3>
    <p style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Diese Prompts werden als System-Prompt an das LLM geschickt, wenn der User auf „Erzeugen" klickt.
        Der Volltext kommt als User-Message. Aenderungen wirken sofort fuer alle nachfolgenden Generierungen.
    </p>
    <div id="tr-a-list"></div>
</div>

<script>
'use strict';
(function() {
    function trEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    async function load() {
        const r = await fetch('/api/v1/admin/transkription/templates');
        const j = await r.json();
        if (!j.success) return;
        document.getElementById('tr-a-list').innerHTML = j.data.templates.map(t => `
            <div style="margin-bottom:var(--d-section-gap);padding:var(--d-card-pad);border:1px solid var(--slate-100);border-radius:var(--d-card-radius);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong>${trEsc(t.label)}</strong>
                    <code style="font-size:var(--d-fs-xs);background:var(--slate-100);padding:2px 6px;border-radius:3px;">${trEsc(t.template_type)} · ${trEsc(t.output_format)}</code>
                </div>
                <textarea class="tr-a-prompt" data-tpl-id="${t.id}">${trEsc(t.prompt_text)}</textarea>
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
                    <label style="font-size:var(--d-fs-xs);color:var(--slate-600);">
                        <input type="checkbox" data-tpl-active="${t.id}" ${t.is_active ? 'checked' : ''}> aktiv
                    </label>
                    <button class="thx-btn thx-btn-primary thx-btn-small" onclick="trASave(${t.id})">Speichern</button>
                </div>
            </div>
        `).join('');
    }

    async function loadSettings() {
        try {
            const r = await fetch('/api/v1/admin/transkription/settings');
            const j = await r.json();
            if (!j.success) return;
            const tpls = (j.data.default_templates || '').split(',').filter(Boolean);
            document.querySelectorAll('.tr-set-tpl').forEach(el => { el.checked = tpls.includes(el.value); });
            document.getElementById('tr-set-kn-tpl').value = j.data.default_knowledge_template || '';
            document.getElementById('tr-set-hf-status').textContent = j.data.huggingface_token_set ? '✓ Token gespeichert' : '— kein Token gesetzt';
            document.getElementById('tr-set-whisper-url').value = j.data.whisper_remote_url || '';
            document.getElementById('tr-set-whisper-model').value = j.data.whisper_remote_model || '';
        } catch (e) {}
    }

    window.trSetSave = async function() {
        const tpls = Array.from(document.querySelectorAll('.tr-set-tpl:checked')).map(el => el.value);
        const body = {
            default_templates: tpls.join(','),
            default_knowledge_template: document.getElementById('tr-set-kn-tpl').value,
        };
        const hf = document.getElementById('tr-set-hf').value;
        if (hf !== '') body.huggingface_token = hf;
        body.whisper_remote_url = document.getElementById('tr-set-whisper-url').value;
        body.whisper_remote_model = document.getElementById('tr-set-whisper-model').value;
        try {
            const r = await fetch('/api/v1/admin/transkription/settings', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                body: JSON.stringify(body),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification('Einstellungen gespeichert', 'success');
            document.getElementById('tr-set-hf').value = '';
            loadSettings();
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    window.trASave = async function(id) {
        const promptText = document.querySelector('[data-tpl-id="' + id + '"]').value;
        const isActive = document.querySelector('[data-tpl-active="' + id + '"]').checked ? 1 : 0;
        try {
            const r = await fetch('/api/v1/admin/transkription/templates/' + id, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                body: JSON.stringify({ prompt_text: promptText, is_active: isActive }),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification(j.message, 'success');
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    load();
    loadSettings();
})();
</script>
