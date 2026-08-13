<?php
/**
 * Personen-Sharelinks für den Projektplanner — vorher in /admin/projektplanner/settings.
 * Read-only-Ansicht pro Person (alle ihre Aufgaben aus allen aktiven Plänen).
 */
?>
<style>
.pps-share-table { width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm); background:#fff; border:1px solid var(--slate-200); border-radius:10px; overflow:hidden; }
.pps-share-table th {
    text-align: left; padding: 8px 10px; color: var(--slate-500); font-size: 10px;
    text-transform: uppercase; letter-spacing: 0.04em;
    border-bottom: 2px solid var(--slate-200); font-weight: 600;
}
.pps-share-table td { padding: 8px 10px; border-bottom: 1px solid var(--slate-100); vertical-align: middle; }
.pps-share-table tr:last-child td { border-bottom: none; }
.pps-share-actions { display: flex; gap: 4px; opacity: 0; transition: opacity 0.15s; }
tr:hover .pps-share-actions { opacity: 1; }
.pps-share-actions button, .pps-share-actions a {
    background: none; border: none; cursor: pointer; padding: 4px;
    border-radius: 4px; color: var(--slate-400); text-decoration:none;
    display: inline-flex; align-items: center;
}
.pps-share-actions button:hover, .pps-share-actions a:hover { background: var(--slate-100); color: var(--rose-600); }
.pps-share-hint {
    background: var(--thoxan-50); color: var(--thoxan-800);
    border: 1px solid var(--thoxan-200); border-radius: 8px;
    padding: 10px 14px; font-size: var(--d-fs-sm); margin-bottom: 14px;
    display: flex; align-items: flex-start; gap: 10px;
}
.pps-share-hint .material-symbols-rounded { color: var(--thoxan-600); font-size: 18px; flex-shrink: 0; }
.pps-share-modal-body .field { margin-bottom: 12px; }
.pps-share-modal-body label {
    display: block; font-size: var(--d-fs-sm); font-weight: 600;
    color: var(--slate-600); margin-bottom: 4px;
}
.pps-share-modal-body select {
    width: 100%; padding: 8px 10px; border: 1px solid var(--slate-200);
    border-radius: 6px; font-size: var(--d-fs-sm); font-family: inherit;
}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;">
    <h2 style="margin:0;font-size: var(--d-fs-base);color:var(--slate-800);">Personen-Sharelinks (Projektplanner)</h2>
    <button class="thx-btn thx-btn-primary" onclick="ppshOpenGen()">
        <span class="material-symbols-rounded" style="font-size:16px;">link</span>
        Sharelink generieren
    </button>
</div>

<div class="pps-share-hint">
    <span class="material-symbols-rounded">info</span>
    <div>
        Personen-Sharelinks zeigen einer Person ohne Login alle ihre Aufgaben aus allen aktiven Plänen.
        Ideal für freie Mitarbeiter, die nur ihre Tasks sehen müssen.
        Team-Mitglieder pflegst Du im <a href="/admin/users?tab=pp-team" style="color:var(--thoxan-700);">Projektplanner-Team-Tab</a>.
    </div>
</div>

<table class="pps-share-table">
    <thead>
        <tr>
            <th>Person</th>
            <th>Sharelink</th>
            <th style="width:140px;">Erzeugt</th>
            <th style="width:80px;"></th>
        </tr>
    </thead>
    <tbody id="ppsh-tbody">
        <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--slate-400);">Lädt…</td></tr>
    </tbody>
</table>

<!-- Generate-Modal -->
<div class="thx-modal-backdrop" id="ppsh-gen-modal" style="display:none;"
     onclick="if(event.target===this)ppshCloseGen()">
    <div class="thx-modal" style="width:440px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Personen-Sharelink generieren</h3>
            <button class="thx-modal-close" onclick="ppshCloseGen()">&times;</button>
        </div>
        <div class="thx-modal-body pps-share-modal-body">
            <div class="field">
                <label>Team-Mitglied</label>
                <select id="ppsh-gen-name">
                    <option value="">— Lädt… —</option>
                </select>
                <div style="font-size:11px;color:var(--slate-400);margin-top:6px;">
                    Existiert bereits ein Sharelink für die Person, wird der bestehende zurückgegeben (nicht neu erzeugt).
                </div>
            </div>
        </div>
        <div class="thx-modal-footer">
            <button class="thx-btn thx-btn-secondary" onclick="ppshCloseGen()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="ppshDoGen()">Generieren</button>
        </div>
    </div>
</div>

<script>
'use strict';
const ppshState = { team: [] };

async function ppshLoad() {
    try {
        const [sharesR, teamR] = await Promise.all([
            fetch('/api/v1/admin/projektplanner/person-shares').then(r => r.json()),
            fetch('/api/v1/admin/projektplanner/team').then(r => r.json()),
        ]);
        if (!sharesR.success) throw new Error(sharesR.message);
        ppshState.team = (teamR.data?.team || []).filter(t => t.is_active);
        ppshRender(sharesR.data.shares);
    } catch (e) { App.showNotification('Fehler: ' + e.message, 'error'); }
}

function ppshRender(shares) {
    const tbody = document.getElementById('ppsh-tbody');
    if (!shares.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--slate-400);">Noch keine Sharelinks.</td></tr>';
        return;
    }
    tbody.innerHTML = shares.map(s => {
        const url = window.location.origin + '/personen-aufgaben/' + s.share_hash;
        return `<tr>
            <td><strong>${(s.person_name || '').replace(/</g, '&lt;')}</strong></td>
            <td>
                <input type="text" readonly value="${url}" onclick="this.select()"
                       style="width:100%;padding:4px 8px;border:1px solid var(--slate-200);border-radius:4px;font-family:ui-monospace,monospace;font-size:11px;background:var(--slate-50);">
            </td>
            <td style="font-size:11px;color:var(--slate-500);">${new Date(s.created_at).toLocaleDateString('de-DE')}</td>
            <td>
                <div class="pps-share-actions">
                    <a href="/api/v1/admin/projektplanner/export-person?name=${encodeURIComponent(s.person_name || '')}" title="Aufgaben als Excel exportieren">
                        <span class="material-symbols-rounded" style="font-size:16px;">download</span>
                    </a>
                    <button onclick="ppshDelete(${s.id})" title="Sharelink löschen">
                        <span class="material-symbols-rounded" style="font-size:16px;">delete</span>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function ppshOpenGen() {
    const sel = document.getElementById('ppsh-gen-name');
    sel.innerHTML = '<option value="">— Person wählen —</option>' +
        ppshState.team.map(t =>
            `<option value="${(t.name||'').replace(/"/g,'&quot;')}">${t.name}</option>`
        ).join('');
    document.getElementById('ppsh-gen-modal').style.display = 'flex';
}
function ppshCloseGen() { document.getElementById('ppsh-gen-modal').style.display = 'none'; }

async function ppshDoGen() {
    const name = document.getElementById('ppsh-gen-name').value;
    if (!name) { App.showNotification('Person wählen', 'error'); return; }
    try {
        const r = await fetch('/api/v1/admin/projektplanner/person-shares', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ person_name: name }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification(j.message || 'Sharelink erzeugt', 'success');
        ppshCloseGen();
        ppshLoad();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppshDelete(id) {
    if (!confirm('Sharelink löschen? Der Link wird sofort ungültig.')) return;
    try {
        await fetch('/api/v1/admin/projektplanner/person-shares/' + id, {
            method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        App.showNotification('Sharelink gelöscht', 'success');
        ppshLoad();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

document.addEventListener('DOMContentLoaded', ppshLoad);
</script>
