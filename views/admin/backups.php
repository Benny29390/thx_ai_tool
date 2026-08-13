<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:var(--emerald-600);font-size:22px;">backup</span>
            Backups
        </h1>
        <p class="thx-page-subtitle">
            Automatische tägliche Sicherung von Datenbank und Code-Tree um 03:00 Server-Zeit.
            Letzte 14 Tage werden behalten. Backup-Verzeichnis: <code style="background:var(--slate-100);padding:2px 6px;border-radius:4px;">/var/backups/ki-tool/</code>
        </p>
    </div>
</div>

<div class="bk-page">

    <!-- Status -->
    <div class="thx-card" id="bk-status-card">
        <div id="bk-status" style="color:var(--slate-500);font-size:var(--d-fs-sm);">Lädt…</div>
    </div>

    <!-- Listen -->
    <div class="bk-grid">
        <div class="thx-card thx-card-flush">
            <div class="bk-list-head">
                <span class="material-symbols-rounded" style="color:var(--thoxan-700);font-size:18px;">database</span>
                Datenbank-Backups
            </div>
            <div id="bk-db-list" class="bk-list"></div>
        </div>
        <div class="thx-card thx-card-flush">
            <div class="bk-list-head">
                <span class="material-symbols-rounded" style="color:var(--thoxan-700);font-size:18px;">folder_zip</span>
                Datei-Backups
            </div>
            <div id="bk-files-list" class="bk-list"></div>
        </div>
    </div>
</div>

<style>
.bk-page {
    max-width: 1100px;
    display: flex; flex-direction: column;
    gap: var(--d-gutter);
}
.bk-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: var(--d-gutter);
}
@media (max-width: 900px) {
    .bk-grid { grid-template-columns: 1fr; }
}
.bk-list-head {
    display: flex; align-items: center; gap: 8px;
    padding: 12px var(--d-gutter);
    border-bottom: 1px solid var(--slate-200);
    font-size: var(--d-fs-sm); font-weight: 600;
    color: var(--slate-700);
    background: var(--slate-50);
}
.bk-list { max-height: 420px; overflow-y: auto; }
.bk-row {
    padding: 10px var(--d-gutter);
    border-bottom: 1px solid var(--slate-100);
    display: flex; justify-content: space-between; align-items: center;
    font-size: var(--d-fs-xs);
}
.bk-row:last-child { border-bottom: none; }
.bk-row-name { font-family: ui-monospace, "JetBrains Mono", Consolas, monospace; color: var(--slate-700); }
.bk-row-size { color: var(--emerald-700); font-weight: 600; font-family: ui-monospace, monospace; }
.bk-row-time { color: var(--slate-400); font-size: 11px; margin-top: 2px; }
.bk-empty { padding: 24px; text-align: center; color: var(--slate-400); font-size: var(--d-fs-xs); }

.bk-status-bar {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 12px;
}
.bk-status-info { font-size: var(--d-fs-sm); color: var(--slate-700); display: flex; flex-direction: column; gap: 4px; }
.bk-status-dot { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
.bk-status-dot.is-ok { color: var(--emerald-600); }
.bk-status-dot.is-warn { color: var(--amber-600); }
.bk-status-meta { color: var(--slate-500); font-size: var(--d-fs-xs); }
.bk-status-note { color: var(--amber-700); font-size: var(--d-fs-xs); background: var(--amber-50); padding: 6px 10px; border-radius: var(--d-control-radius); }
.bk-status-error { color: var(--rose-700); background: var(--rose-50); padding: 10px; border-radius: var(--d-control-radius); font-size: var(--d-fs-sm); }
</style>

<script>
'use strict';
async function bkLoad() {
    try {
        const r = await fetch('/api/v1/admin/backups');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const d = j.data;
        const s = d.status;

        const cronDot = d.cron_active
            ? '<span class="bk-status-dot is-ok">●&nbsp;Cron läuft</span>'
            : '<span class="bk-status-dot is-warn">●&nbsp;Kein aktueller Lauf</span>';

        const lastRun = s && s.last_run ? s.last_run : 'noch nie';
        const totalSize = s && s.total_size ? s.total_size : '–';

        document.getElementById('bk-status').innerHTML = `
            <div class="bk-status-bar">
                <div class="bk-status-info">
                    <div>${cronDot} <span style="color:var(--slate-400);">·</span> ${d.cron_schedule} <span style="color:var(--slate-400);">·</span> Aufbewahrung: ${s ? s.keep_days : '?'} Tage <span style="color:var(--slate-400);">·</span> Gesamt: <strong>${totalSize}</strong></div>
                    <div class="bk-status-meta">Letzter Lauf: <strong>${lastRun}</strong></div>
                    ${d.note ? '<div class="bk-status-note">' + d.note + '</div>' : ''}
                </div>
                <button class="thx-btn thx-btn-primary" onclick="bkManual()" id="bk-manual-btn">
                    <span class="material-symbols-rounded" style="font-size:16px;">play_arrow</span>
                    Manuell jetzt
                </button>
            </div>
        `;

        const backups = (s && s.backups) ? s.backups : [];
        const dbBackups = backups.filter(b => b.type === 'db');
        const fileBackups = backups.filter(b => b.type === 'files');

        document.getElementById('bk-db-list').innerHTML = dbBackups.length
            ? dbBackups.map(b => `
                <div class="bk-row">
                    <div>
                        <div class="bk-row-name">${b.name}</div>
                        <div class="bk-row-time">${b.mtime}</div>
                    </div>
                    <div class="bk-row-size">${b.size}</div>
                </div>`).join('')
            : '<div class="bk-empty">Noch keine DB-Backups</div>';

        document.getElementById('bk-files-list').innerHTML = fileBackups.length
            ? fileBackups.map(b => `
                <div class="bk-row">
                    <div>
                        <div class="bk-row-name">${b.name}</div>
                        <div class="bk-row-time">${b.mtime}</div>
                    </div>
                    <div class="bk-row-size">${b.size}</div>
                </div>`).join('')
            : '<div class="bk-empty">Noch keine Datei-Backups</div>';
    } catch (e) {
        document.getElementById('bk-status').innerHTML = '<div class="bk-status-error">Fehler beim Laden: ' + e.message + '</div>';
    }
}

async function bkManual() {
    const btn = document.getElementById('bk-manual-btn');
    if (!confirm('Backup jetzt manuell ausführen?\n\nAchtung: Manuell aus dem Frontend läuft das Backup als www-data und braucht Schreibrechte auf /var/backups/ki-tool/ — der Cron-Lauf (root) klappt aber unabhängig davon nachts.')) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;">hourglass_top</span> Läuft… (1–3 Min)';
    try {
        const r = await fetch('/api/v1/admin/backups/run', { method: 'POST' });
        const j = await r.json();
        alert((j.success ? '✓ ' : '✗ ') + (j.message || '') + '\n\n' + (j.data?.output || '').slice(0, 2000));
    } catch (e) {
        alert('Fehler: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;">play_arrow</span> Manuell jetzt';
        bkLoad();
    }
}

document.addEventListener('DOMContentLoaded', bkLoad);
</script>
