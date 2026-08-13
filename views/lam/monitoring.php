<?php $activeModul = 'monitoring'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Monitoring</h1>
        <div class="thx-page-subtitle">HTTP-Erreichbarkeit und Link-Status der live geschalteten Maßnahmen.</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="lamMonitoring()" x-init="laden()">
    <section class="lam-filter-card">
        <div class="lam-filter-head">
            <h2>Filter</h2>
            <span style="font-size:var(--d-fs-xs);color:var(--slate-400);"
                  x-text="rows.length ? (rows.length + ' Checks') : ''"></span>
        </div>
        <div class="lam-filter-grid">
            <div class="lam-filter-col-12">
                <label class="lam-filter-label">Optionen</label>
                <div class="lam-chip-row">
                    <button class="lam-chip lam-chip-ohne"
                            :class="filter.nur_alerts ? 'is-active' : ''"
                            @click="filter.nur_alerts = !filter.nur_alerts; laden()">
                        nur Alerts
                    </button>
                    <button class="lam-chip lam-chip-ohne"
                            :class="filter.nur_unmuted ? 'is-active' : ''"
                            @click="filter.nur_unmuted = !filter.nur_unmuted; laden()"
                            title="Stumm-geschaltete Maßnahmen ausblenden">
                        ohne stumm-geschaltete
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Bulk-Toolbar -->
    <div class="thx-bulk-toolbar" x-show="auswahl.size > 0" x-cloak>
        <span class="thx-bulk-count"><span x-text="auswahl.size"></span> ausgewählt</span>
        <span class="thx-divider"></span>
        <button class="lam-btn lam-btn-primary lam-btn-small" @click="alertsQuittieren()" :disabled="bulkLaeuft">
            <span x-show="!bulkLaeuft">Alerts quittieren</span><span x-show="bulkLaeuft">…</span>
        </button>
        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="bulkPruefen()" :disabled="bulkLaeuft" title="HTTP-Check für alle ausgewählten Maßnahmen erneut ausführen">
            <span x-show="!bulkLaeuft">Erneut prüfen</span><span x-show="bulkLaeuft">…</span>
        </button>
        <button class="thx-bulk-clear" @click="auswahlLeeren()">Auswahl aufheben</button>
    </div>

    <section class="lam-table-card">
        <div class="lam-table-wrap">
            <table class="lam-table">
                <thead>
                    <tr>
                        <th class="thx-bulk-col">
                            <input type="checkbox" class="thx-bulk-checkbox" :checked="alleSichtbarGewaehlt()" @change="toggleAlleSichtbar()">
                        </th>
                        <th>Kunde</th>
                        <th>Domain</th>
                        <th>URL</th>
                        <th class="center">HTTP</th>
                        <th class="center">Link</th>
                        <th class="center">Link-Typ</th>
                        <th>Zeitpunkt</th>
                        <th class="center">Alert</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="c in rows" :key="c.id">
                        <tr :class="auswahl.has(c.id) ? 'is-bulk-selected' : ''">
                            <td class="thx-bulk-col">
                                <input type="checkbox" class="thx-bulk-checkbox" :checked="auswahl.has(c.id)" @change="toggleAuswahl(c.id)" @click.stop>
                            </td>
                            <td><strong x-text="c.customer_kuerzel"></strong></td>
                            <td class="url-cell">
                                <template x-if="c.massnahme_id">
                                    <a :href="'/lam/massnahmen/' + encodeURIComponent(c.massnahme_id)" style="color:var(--thoxan-700);" x-text="c.domain_url"></a>
                                </template>
                                <template x-if="!c.massnahme_id"><span x-text="c.domain_url"></span></template>
                            </td>
                            <td class="url-cell">
                                <template x-if="c.veroeffentlichungs_url">
                                    <a :href="c.veroeffentlichungs_url" target="_blank" rel="noopener" x-text="kurzUrl(c.veroeffentlichungs_url)"></a>
                                </template>
                                <template x-if="!c.veroeffentlichungs_url"><span class="empty">—</span></template>
                            </td>
                            <td class="center" x-text="c.http_status || '—'"></td>
                            <td class="center">
                                <template x-if="c.link_vorhanden == 1">
                                    <span class="lam-dot ok" title="Link vorhanden"></span>
                                </template>
                                <template x-if="c.link_vorhanden == 0">
                                    <span class="lam-dot error" title="Link nicht gefunden"></span>
                                </template>
                            </td>
                            <td class="center" x-text="c.link_typ || '—'"></td>
                            <td x-text="c.zeitpunkt"></td>
                            <td class="center">
                                <template x-if="c.alert_ausgeloest == 1">
                                    <span class="lam-badge" style="background:var(--rose-100);color:var(--rose-800);">Alert</span>
                                </template>
                                <template x-if="c.monitoring_muted == 1">
                                    <span class="lam-badge" style="background:var(--slate-200);color:var(--slate-700);margin-left:4px;" title="Monitoring stumm-geschaltet">🔕</span>
                                </template>
                            </td>
                            <td>
                                <button class="lam-btn lam-btn-secondary lam-btn-small"
                                        @click="pruefeEinzeln(c)" :disabled="bulkLaeuft || !c.massnahme_id"
                                        title="HTTP-Check jetzt ausführen">↻</button>
                                <button class="lam-btn lam-btn-secondary lam-btn-small"
                                        @click="toggleMute(c)" :disabled="bulkLaeuft || !c.massnahme_id"
                                        :title="c.monitoring_muted == 1 ? 'Monitoring wieder aktivieren' + (c.monitoring_stumm_bis ? ' (bis ' + c.monitoring_stumm_bis + ')' : '') : 'Monitoring stumm schalten'"
                                        x-text="c.monitoring_muted == 1 ? '🔔' : '🔕'"></button>
                                <button class="lam-btn lam-btn-secondary lam-btn-small"
                                        @click="muteBisDatum(c)" :disabled="bulkLaeuft || !c.massnahme_id"
                                        title="Stummschalten bis Datum (z.B. bekannte Wartung)">📅</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Monitoring-Daten.</div>
            <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span> Lade …</div>
        </div>
    </section>
</div>

<script>
function lamMonitoring() {
    return {
        laedt: true, rows: [], filter: { nur_alerts: false, nur_unmuted: false },
        auswahl: new Set(), bulkLaeuft: false,

        async muteBisDatum(c) {
            if (!c.massnahme_id) return;
            const heute = new Date();
            const vorschlag = new Date(heute.getTime() + 14 * 86400000).toISOString().slice(0, 10);
            const eingabe = prompt('Bis wann stumm schalten? (YYYY-MM-DD, leer = unbegrenzt)', vorschlag);
            if (eingabe === null) return;
            const wert = eingabe.trim() || null;
            const r = await fetch('/api/v1/lam/massnahme-inline', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id: c.massnahme_id, feld: 'monitoring_stumm_bis', wert }),
            });
            const j = await r.json();
            if (!j.success) { alert(j.error || 'Fehler.'); return; }
            // Wenn Datum gesetzt, automatisch mute aktivieren
            if (wert) {
                await fetch('/api/v1/lam/massnahme-inline', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ id: c.massnahme_id, feld: 'monitoring_muted', wert: 1 }),
                });
            }
            this.rows.forEach(x => {
                if (x.massnahme_id === c.massnahme_id) {
                    x.monitoring_stumm_bis = wert;
                    if (wert) x.monitoring_muted = 1;
                }
            });
        },

        async toggleMute(c) {
            if (!c.massnahme_id) return;
            const neu = c.monitoring_muted == 1 ? 0 : 1;
            const r = await fetch('/api/v1/lam/massnahme-inline', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id: c.massnahme_id, feld: 'monitoring_muted', wert: neu }),
            });
            const j = await r.json();
            if (!j.success) { alert(j.error || 'Stumm-Schalten fehlgeschlagen.'); return; }
            // optimistic in-place update für alle Checks dieser Maßnahme
            this.rows.forEach(x => { if (x.massnahme_id === c.massnahme_id) x.monitoring_muted = neu; });
        },

        toggleAuswahl(id) {
            const neu = new Set(this.auswahl);
            if (neu.has(id)) neu.delete(id); else neu.add(id);
            this.auswahl = neu;
        },
        alleSichtbarGewaehlt() { return this.rows.length > 0 && this.rows.every(r => this.auswahl.has(r.id)); },
        toggleAlleSichtbar() {
            const neu = new Set(this.auswahl);
            if (this.alleSichtbarGewaehlt()) this.rows.forEach(r => neu.delete(r.id));
            else this.rows.forEach(r => neu.add(r.id));
            this.auswahl = neu;
        },
        auswahlLeeren() { this.auswahl = new Set(); },
        async alertsQuittieren() {
            if (this.bulkLaeuft || this.auswahl.size === 0) return;
            this.bulkLaeuft = true;
            try {
                await fetch('/api/v1/lam/monitoring-aktion', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(this.auswahl), aktion: 'alerts_quittieren' })
                });
                this.auswahlLeeren();
                await this.laden();
            } finally { this.bulkLaeuft = false; }
        },
        async pruefeEinzeln(c) {
            if (!c.massnahme_id) return;
            this.bulkLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/monitoring-check', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ massnahme_id: c.massnahme_id })
                });
                const j = await r.json();
                if (!j.success) alert(j.message || 'Check fehlgeschlagen');
                else if (j.data.skipped) alert('Übersprungen: ' + j.data.grund);
                else await this.laden();
            } catch (e) { alert('Verbindungsfehler'); }
            finally { this.bulkLaeuft = false; }
        },
        async bulkPruefen() {
            if (this.bulkLaeuft || this.auswahl.size === 0) return;
            const massnahmeIds = this.rows.filter(r => this.auswahl.has(r.id) && r.massnahme_id).map(r => r.massnahme_id);
            if (massnahmeIds.length === 0) { alert('Keine prüfbaren Maßnahmen ausgewählt.'); return; }
            if (!confirm(`HTTP-Check für ${massnahmeIds.length} Maßnahmen starten? Kann ein paar Sekunden dauern.`)) return;
            this.bulkLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/monitoring-check', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: massnahmeIds })
                });
                const j = await r.json();
                if (j.success) {
                    alert(`Bulk-Check: ${j.data.ok} ok, ${j.data.fehler} Fehler, ${j.data.alerts} neue Alerts.`);
                    this.auswahlLeeren();
                    await this.laden();
                } else { alert(j.message || 'Fehler'); }
            } catch (e) { alert('Verbindungsfehler'); }
            finally { this.bulkLaeuft = false; }
        },
        async laden() {
            this.laedt = true;
            const p = new URLSearchParams();
            if (this.filter.nur_alerts) p.set('nur_alerts', '1');
            if (this.filter.nur_unmuted) p.set('nur_unmuted', '1');
            try {
                const r = await fetch('/api/v1/lam/monitoring?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.rows = j.success ? j.data : [];
            } finally { this.laedt = false; }
        },
        kurzUrl(u) { return u ? u.replace(/^https?:\/\//, '').replace(/\/$/, '') : ''; }
    };
}
</script>
