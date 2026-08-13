<?php $activeModul = 'migration'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header">
    <div><h1 class="thx-page-title">Migration</h1><div class="thx-page-subtitle">Brevo-Kontakte ins CRM importieren — initial oder als Delta-Sync.</div></div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="crmMigration()" x-init="laden(); pollInterval = setInterval(() => laden(), 5000)" x-cloak>

    <div class="thx-card" style="margin-bottom:14px;">
        <div class="thx-card-title">Brevo-Migration</div>
        <div style="margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <div style="font-size:0.8rem;color:var(--slate-500);">Letzter erfolgreicher Lauf</div>
                <div style="font-size:0.95rem;margin-top:4px;">
                    <template x-if="letzterLauf">
                        <span>
                            <strong x-text="formatDateTime(letzterLauf.beendet_am)"></strong>
                            <span style="color:var(--slate-500);"> · <span x-text="letzterLauf.modus"></span></span>
                            <br><span style="color:var(--slate-500);font-size:0.8rem;" x-text="letzterLauf.anzahl_insert + ' insert · ' + letzterLauf.anzahl_update + ' update · ' + letzterLauf.anzahl_skip + ' skip · ' + letzterLauf.anzahl_error + ' error'"></span>
                        </span>
                    </template>
                    <template x-if="!letzterLauf"><span style="color:var(--slate-400);">noch keiner</span></template>
                </div>
            </div>
            <div>
                <div style="font-size:0.8rem;color:var(--slate-500);">Aktueller Lauf</div>
                <div style="font-size:0.95rem;margin-top:4px;">
                    <template x-if="laufendeRuns.length > 0">
                        <span style="color:var(--thoxan-600);">
                            <strong>läuft</strong> seit <span x-text="formatRelativ(laufendeRuns[0].gestartet_am)"></span> ·
                            <span x-text="(laufendeRuns[0].anzahl_geprueft||0).toLocaleString('de-DE')"></span> geprüft
                        </span>
                    </template>
                    <template x-if="laufendeRuns.length === 0"><span style="color:var(--slate-400);">kein aktiver Lauf</span></template>
                </div>
            </div>
        </div>
        <div style="margin-top:18px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button class="thx-btn thx-btn-primary" @click="starteMigration('full')" :disabled="laufendeRuns.length > 0">
                Vollständiger Import
            </button>
            <button class="thx-btn thx-btn-secondary" @click="starteMigration('delta')" :disabled="laufendeRuns.length > 0 || !letzterLauf">
                Delta-Import (nur Neue/Geänderte)
            </button>
            <span x-show="laufendeRuns.length > 0" style="color:var(--slate-500);font-size:0.8rem;">Worker läuft — Status aktualisiert sich automatisch.</span>
        </div>
    </div>

    <div class="thx-card">
        <div class="thx-card-title">Historie</div>
        <div class="thx-table-wrap" style="margin-top:10px;">
            <table class="lam-table">
                <thead><tr><th>Gestartet</th><th>Modus</th><th>Status</th><th class="center">Geprüft</th><th class="center">Insert</th><th class="center">Update</th><th class="center">Skip</th><th class="center">Error</th><th>Dauer</th></tr></thead>
                <tbody>
                    <template x-for="r in runs" :key="r.id">
                        <tr>
                            <td style="font-size:0.78rem;font-variant-numeric:tabular-nums;" x-text="formatDateTime(r.gestartet_am)"></td>
                            <td><span class="lam-chip" x-text="r.modus"></span></td>
                            <td><span class="lam-chip" :class="r.status === 'ok' ? 'lam-chip-status-geprueft' : (r.status === 'error' ? 'lam-chip-status-geloescht' : '')" x-text="r.status"></span></td>
                            <td class="center" x-text="(r.anzahl_geprueft||0).toLocaleString('de-DE')"></td>
                            <td class="center" style="color:var(--emerald-700);" x-text="r.anzahl_insert||0"></td>
                            <td class="center" style="color:var(--thoxan-600);" x-text="r.anzahl_update||0"></td>
                            <td class="center" style="color:var(--slate-500);" x-text="r.anzahl_skip||0"></td>
                            <td class="center" style="color:var(--rose-700);" x-text="r.anzahl_error||0"></td>
                            <td style="font-size:0.78rem;color:var(--slate-500);" x-text="berechneDauer(r.gestartet_am, r.beendet_am)"></td>
                        </tr>
                    </template>
                    <template x-if="runs.length === 0"><tr><td colspan="9" style="text-align:center;color:var(--slate-400);padding:30px;">Noch keine Migrations-Läufe.</td></tr></template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function crmMigration() {
    return {
        runs: [], letzterLauf: null, laufendeRuns: [], pollInterval: null,
        async laden() {
            try {
                const r = await fetch('/api/v1/crm/migration/status');
                const j = await r.json();
                if (j.success) {
                    this.runs = j.data.runs || [];
                    this.letzterLauf = j.data.letzter_erfolg || null;
                    this.laufendeRuns = this.runs.filter(x => x.status === 'running');
                }
            } catch (e) {}
        },
        async starteMigration(modus) {
            if (!confirm('Brevo-Migration im Modus „' + modus + '" starten?\n\nLäuft im Hintergrund (mehrere Minuten).')) return;
            const r = await fetch('/api/v1/crm/migration/start', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modus })
            });
            const j = await r.json();
            if (j.success) {
                App.showNotification('Migration gestartet (Run #' + j.data.run_id + ')', 'success');
                this.laden();
            } else App.showNotification(j.message || 'Fehler', 'error');
        },
        formatDateTime(d) { if (!d) return '—'; return new Date(d.replace(' ','T')).toLocaleString('de-DE'); },
        formatRelativ(d) {
            if (!d) return '—';
            const diff = (Date.now() - new Date(d.replace(' ','T')).getTime()) / 1000;
            if (diff < 60) return Math.floor(diff) + 's';
            if (diff < 3600) return Math.floor(diff/60) + 'min';
            return Math.floor(diff/3600) + 'h';
        },
        berechneDauer(a, b) {
            if (!a || !b) return '—';
            const diff = (new Date(b.replace(' ','T')) - new Date(a.replace(' ','T'))) / 1000;
            if (diff < 60) return diff.toFixed(0) + 's';
            return Math.floor(diff/60) + 'min ' + Math.floor(diff%60) + 's';
        },
    };
}
</script>
