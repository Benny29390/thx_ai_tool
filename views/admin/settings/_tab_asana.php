<?php
/** Asana-Tab — Personal Access Token + Workspace. */
?>
<div class="settings-card">
    <h2>Asana-Integration</h2>
    <p class="settings-card-sub">
        Lesezugriff auf Asana — verbindet pro Kunde Projekte mit dem Wissen-System.
        <strong>Wir schreiben niemals zu Asana.</strong>
        Verwende einen Personal Access Token von einem Account mit Lesezugriff auf alle relevanten Projekte.
        <a href="https://app.asana.com/0/my-apps" target="_blank" rel="noopener">PAT in Asana erstellen →</a>
    </p>
    <form id="form-asana" onsubmit="event.preventDefault(); SettingsSave(this);">
        <div class="settings-row two-col">
            <div class="settings-field">
                <label for="asana_pat">
                    Personal Access Token
                    <?php if ($isConfigured('asana_pat')): ?>
                        <span class="key-status ja">Gesetzt</span>
                    <?php else: ?>
                        <span class="key-status nein">Nicht gesetzt</span>
                    <?php endif; ?>
                </label>
                <input type="password" id="asana_pat" name="asana_pat" autocomplete="new-password"
                       placeholder="<?= $isConfigured('asana_pat') ? 'Neu eingeben zum Ersetzen…' : '1/12345…' ?>">
            </div>
            <div class="settings-field">
                <label for="asana_workspace_gid">Workspace-GID</label>
                <input type="text" id="asana_workspace_gid" name="asana_workspace_gid"
                       value="<?= htmlspecialchars($valueOf('asana_workspace_gid')) ?>"
                       placeholder="auto-erkannt nach Test">
                <p class="field-hint">Optional, leer lassen — wird beim ersten Test gesetzt.</p>
            </div>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
            <button type="button" class="thx-btn thx-btn-secondary" id="asana-test-btn"
                    onclick="testeAsana()">Verbindung testen</button>
            <span id="asana-test-status" class="status-msg muted"></span>
        </div>
    </form>
    <div id="asana-workspaces" style="margin-top:14px;"></div>
</div>

<script>
async function testeAsana() {
    const btn = document.getElementById('asana-test-btn');
    const status = document.getElementById('asana-test-status');
    const wsBox = document.getElementById('asana-workspaces');
    btn.disabled = true;
    status.textContent = 'Verbinde mit Asana…';
    status.className = 'status-msg muted';
    wsBox.innerHTML = '';
    try {
        const resp = await App.request('POST', '/admin/asana-test', {});
        if (resp.success) {
            const user = resp.data.user || {};
            const ws = resp.data.workspaces || [];
            status.textContent = 'OK — eingeloggt als ' + (user.name || user.email || 'unbekannt');
            status.className = 'status-msg ok';
            if (ws.length) {
                let html = '<div style="background:var(--slate-50,#f8fafc);border:1px solid var(--slate-200,#e2e8f0);border-radius:8px;padding:12px;">';
                html += '<strong style="font-size: var(--d-fs-sm);">Workspaces:</strong><br>';
                ws.forEach(w => {
                    html += '<div style="font-size: var(--d-fs-sm);margin-top:6px;display:flex;gap:8px;align-items:center;">' +
                            '<code>' + w.gid + '</code><span>—</span><span>' + (w.name || '?') + '</span>' +
                            ' <button class="thx-btn thx-btn-secondary" style="padding:2px 8px;font-size: var(--d-fs-xs);" type="button" onclick="setzeWorkspace(\'' + w.gid + '\')">Verwenden</button>' +
                            '</div>';
                });
                html += '</div>';
                wsBox.innerHTML = html;
            }
            App.showNotification('Asana-Verbindung erfolgreich', 'success');
        } else {
            status.textContent = resp.message || 'Fehler';
            status.className = 'status-msg err';
        }
    } catch (e) {
        status.textContent = e.message || 'Verbindungsfehler';
        status.className = 'status-msg err';
    }
    btn.disabled = false;
}

function setzeWorkspace(gid) {
    const input = document.getElementById('asana_workspace_gid');
    if (input) {
        input.value = gid;
        App.showNotification('Workspace gesetzt — bitte speichern', 'info');
    }
}
</script>

<!-- ===== Projektplanner-Asana (war in /admin/projektplanner/settings) ===== -->
<div class="settings-card">
    <h2>Querschnittsaufgaben</h2>
    <p class="settings-card-sub">
        Ziel-Projekt für Sammelaufgaben, die mehrere Kunden betreffen (z. B. „alle Kunden über eine neue Funktion informieren").
        Angelegt werden sie über die Kunden-Seite: Kunden auswählen → Sammelaufgabe erstellen.
    </p>

    <div class="settings-row">
        <div class="settings-field">
            <label for="qs-asana-project-gid">Asana-Projekt für Sammelaufgaben (GID)</label>
            <div style="display:flex;gap:6px;align-items:center;">
                <input type="text" id="qs-asana-project-gid" placeholder="z. B. 1234567890123456" style="flex:1;">
                <button type="button" class="thx-btn thx-btn-primary" onclick="qsSaveProject()">Speichern</button>
            </div>
            <p class="field-hint">
                Hier landet die <strong>Sammelaufgabe</strong>; die Unteraufgaben (eine je Kunde) hängen darunter.
                Sinnvoll ist ein internes Projekt (z. B. „Thoxan intern"). Die GID steht in der Projekt-URL:
                <code>app.asana.com/0/&lt;GID&gt;/list</code>.
                Optional zusätzlich eine Section-GID, wenn die Sammelaufgabe in einem bestimmten Abschnitt landen soll.
            </p>
        </div>
    </div>
    <div class="settings-row">
        <div class="settings-field">
            <label for="qs-asana-section-gid">Section-GID (optional)</label>
            <div style="display:flex;gap:6px;align-items:center;">
                <input type="text" id="qs-asana-section-gid" placeholder="leer lassen = kein Abschnitt" style="flex:1;">
                <button type="button" class="thx-btn thx-btn-secondary" onclick="qsSaveProject()">Speichern</button>
            </div>
        </div>
    </div>
</div>

<div class="settings-card">
    <h2>Projektplanner</h2>
    <p class="settings-card-sub">
        Einstellungen rund um die Asana-Anbindung im Projektplanner — Templates-Quelle, Aufräumen verwaister Verknüpfungen, Komplett-Backup.
    </p>

    <div class="settings-row">
        <div class="settings-field">
            <label for="pps-asana-tmpl-gid">Templates-Asana-Projekt-GID</label>
            <div style="display:flex;gap:6px;align-items:center;">
                <input type="text" id="pps-asana-tmpl-gid" placeholder="z. B. 1234567890123456" style="flex:1;">
                <button type="button" class="thx-btn thx-btn-primary" onclick="ppsSaveAsanaTmpl()">Speichern</button>
                <button type="button" class="thx-btn thx-btn-secondary" onclick="ppsAsanaRefreshCache()" title="Asana-Cache leeren">
                    <span class="material-symbols-rounded" style="font-size:16px;">refresh</span>
                </button>
            </div>
            <p class="field-hint">
                Tasks aus diesem Projekt werden im Editor als Autocomplete-Vorschläge in der Beschreibungs-Spalte angeboten
                (Format: <code>Logo-Design // 3.5 Std</code> — Stunden gehen automatisch ins „Soll").
                Die GID steht in Asana in der Projekt-URL: <code>app.asana.com/0/&lt;GID&gt;/list</code>.
            </p>
        </div>
    </div>

    <div style="margin-top:18px;padding-top:18px;border-top:1px solid var(--slate-200);">
        <h3 style="margin:0 0 6px;font-size: var(--d-fs-base);color:var(--slate-700);display:flex;align-items:center;gap:6px;">
            <span class="material-symbols-rounded" style="font-size:18px;color:var(--amber-600);">cleaning_services</span>
            Verwaiste Asana-Verknüpfungen aufräumen
        </h3>
        <p class="field-hint" style="margin:0 0 10px;">
            Prüft alle Plan-Zeilen mit Asana-Verknüpfung. Wenn der Asana-Task gelöscht wurde, wird die Zeile als „verwaist" markiert und kann unverknüpft werden.
        </p>
        <button type="button" class="thx-btn thx-btn-secondary" onclick="ppsAsanaScanOrphans()">
            <span class="material-symbols-rounded" style="font-size:16px;">search</span> Scannen
        </button>
        <div id="pps-orphans-result" style="margin-top:10px;"></div>
    </div>

    <div style="margin-top:18px;padding-top:18px;border-top:1px solid var(--slate-200);">
        <h3 style="margin:0 0 6px;font-size: var(--d-fs-base);color:var(--slate-700);display:flex;align-items:center;gap:6px;">
            <span class="material-symbols-rounded" style="font-size:18px;color:var(--thoxan-600);">cloud_download</span>
            Komplett-Backup als JSON
        </h3>
        <p class="field-hint" style="margin:0 0 10px;">
            Lädt alle Projektplanner-Daten als JSON-Backup-Datei (Pläne, Zeilen, Team, Kunden-Budgets, Feedback, Permissions, Personen-Sharelinks).
        </p>
        <a href="/api/v1/admin/projektplanner/backup-json" class="thx-btn thx-btn-secondary" style="text-decoration:none;">
            <span class="material-symbols-rounded" style="font-size:16px;">download</span>
            JSON-Backup herunterladen
        </a>
    </div>
</div>

<script>
(async () => {
    try {
        const r = await fetch('/api/v1/admin/settings');
        const j = await r.json();
        if (!j.success || !j.data) return;
        // Die Settings-API liefert pro Key die ganze Zeile ({setting_key, setting_value, …}),
        // nicht den nackten Wert — daher hier robust auspacken.
        const sval = (v) => (v && typeof v === 'object') ? (v.setting_value ?? '') : (v ?? '');
        const set = (id, key) => { const el = document.getElementById(id); if (el) el.value = sval(j.data[key]); };
        set('pps-asana-tmpl-gid',    'asana_templates_project_gid');
        set('qs-asana-project-gid',  'asana_querschnitt_project_gid');
        set('qs-asana-section-gid',  'asana_querschnitt_section_gid');
    } catch (_) {}
})();

/** Ziel-Projekt (+ optionale Section) fuer Querschnitts-Sammelaufgaben speichern. */
async function qsSaveProject() {
    const gid = document.getElementById('qs-asana-project-gid').value.trim();
    const sec = document.getElementById('qs-asana-section-gid').value.trim();
    try {
        const r = await fetch('/api/v1/admin/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ asana_querschnitt_project_gid: gid, asana_querschnitt_section_gid: sec }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Ziel-Projekt für Sammelaufgaben gespeichert', 'success');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppsSaveAsanaTmpl() {
    const gid = document.getElementById('pps-asana-tmpl-gid').value.trim();
    try {
        const r = await fetch('/api/v1/admin/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ asana_templates_project_gid: gid }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Gespeichert', 'success');
        ppsAsanaRefreshCache(true);
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppsAsanaScanOrphans() {
    const out = document.getElementById('pps-orphans-result');
    out.innerHTML = '<div style="padding:10px;color:var(--slate-400);font-size: var(--d-fs-sm);">Scanne (kann je nach Tasks 1–2 Min. dauern)…</div>';
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/orphans');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const orphans = j.data.orphans || [];
        if (!orphans.length) {
            out.innerHTML = `<div style="padding:10px;background:var(--emerald-50);color:var(--emerald-700);border-radius:6px;font-size: var(--d-fs-sm);">${j.data.checked} Tasks geprüft — keine Verwaisten gefunden.</div>`;
            return;
        }
        out.innerHTML = `
            <div style="background:var(--amber-50);border:1px solid var(--amber-200);border-radius:6px;padding:12px;">
                <div style="font-weight:600;margin-bottom:8px;color:var(--amber-800);">${orphans.length} verwaiste Asana-Verknüpfung(en) gefunden (von ${j.data.checked} geprüft):</div>
                <div style="max-height:240px;overflow-y:auto;background:#fff;border-radius:4px;">
                    ${orphans.map(o => `<div style="padding:6px 10px;border-bottom:1px solid var(--slate-100);font-size:var(--d-fs-xs);">
                        <strong>${(o.plan_title||'').replace(/</g,'&lt;')}</strong> — ${(o.description||'').slice(0,80).replace(/</g,'&lt;')}
                        <span style="color:var(--slate-400);"> · GID ${o.asana_gid}</span>
                    </div>`).join('')}
                </div>
                <button class="thx-btn thx-btn-primary" style="margin-top:8px;padding:4px 10px;font-size: var(--d-fs-xs);" onclick='ppsAsanaUnlinkOrphans(${JSON.stringify(orphans.map(o => o.id))})'>
                    Alle ${orphans.length} unverknüpfen
                </button>
            </div>`;
    } catch (e) {
        out.innerHTML = '<div style="color:var(--rose-600);padding:10px;">' + e.message + '</div>';
    }
}

async function ppsAsanaUnlinkOrphans(rowIds) {
    if (!confirm(`${rowIds.length} Asana-Verknüpfungen unverknüpfen? Die Plan-Zeilen bleiben erhalten, nur die Asana-Refs werden entfernt.`)) return;
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/unlink-orphans', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ row_ids: rowIds }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification(j.message, 'success');
        document.getElementById('pps-orphans-result').innerHTML = '';
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppsAsanaRefreshCache(silent) {
    try {
        await fetch('/api/v1/admin/projektplanner/asana/refresh-cache', {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        if (!silent) App.showNotification('Asana-Cache geleert', 'success');
    } catch (e) { if (!silent) App.showNotification(e.message, 'error'); }
}
</script>
