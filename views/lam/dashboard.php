<?php $activeModul = 'dashboard'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">LAM-System</h1>
        <div class="thx-page-subtitle">Pool-Bestand, Linkprofil-Status und Maßnahmen-Pipeline auf einen Blick.</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="lamDashboard()" x-init="laden()">
    <template x-if="laedt">
        <div class="lam-loading"><span class="lam-spinner"></span> Lade LAM-Daten …</div>
    </template>

    <template x-if="!laedt && fehler">
        <div class="lam-flash lam-flash-fehler">Fehler beim Laden: <span x-text="fehler"></span></div>
    </template>

    <template x-if="!laedt && !fehler">
        <div>
            <!-- Schnellzugriffe (Quick-Action-Tiles) -->
            <div class="lam-quick-tiles" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px;">
                <a class="lam-tile" href="/lam/linkquellen">
                    <div class="lam-tile-icon">🌐</div>
                    <div class="lam-tile-label">Linkquellen-Pool</div>
                    <div class="lam-tile-hint">Domains anlegen & filtern</div>
                </a>
                <a class="lam-tile" href="/lam/linkakquise">
                    <div class="lam-tile-icon">📨</div>
                    <div class="lam-tile-label">In Akquise</div>
                    <div class="lam-tile-hint">Anbieter anschreiben</div>
                </a>
                <a class="lam-tile" href="/lam/massnahmen/kanban">
                    <div class="lam-tile-icon">📋</div>
                    <div class="lam-tile-label">Kanban-Sicht</div>
                    <div class="lam-tile-hint">Status per Drag-and-Drop</div>
                </a>
                <a class="lam-tile" href="/lam/monitoring">
                    <div class="lam-tile-icon">🩺</div>
                    <div class="lam-tile-label">Monitoring</div>
                    <div class="lam-tile-hint">HTTP-Checks & Alerts</div>
                </a>
                <a class="lam-tile" href="/lam/auslagen">
                    <div class="lam-tile-icon">💶</div>
                    <div class="lam-tile-label">Auslagen</div>
                    <div class="lam-tile-hint">Marge & Weiterverrechnung</div>
                </a>
                <a class="lam-tile" href="/lam/korrespondenz">
                    <div class="lam-tile-icon">💬</div>
                    <div class="lam-tile-label">Korrespondenz</div>
                    <div class="lam-tile-hint">Notizen & Anhänge</div>
                </a>
            </div>

            <!-- Widget-Reihe: anstehende Maßnahmen + Monitoring-Alerts + Linkakquise + Auslagen-Monat -->
            <div class="lam-grid-2" style="margin-bottom:20px;">
                <div class="lam-card">
                    <h3>Anstehende Maßnahmen</h3>
                    <div class="lam-table-wrap">
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Kunde</th><th>Domain</th><th>Status</th><th>Geplant</th></tr></thead>
                            <tbody>
                                <template x-for="m in (widgets?.anstehende_massnahmen || [])" :key="m.id">
                                    <tr>
                                        <td><strong x-text="m.customer_kuerzel"></strong></td>
                                        <td x-text="m.domain_url"></td>
                                        <td x-text="m.status"></td>
                                        <td x-text="m.geplant_am || '—'"></td>
                                    </tr>
                                </template>
                                <tr x-show="!(widgets?.anstehende_massnahmen || []).length"><td colspan="4" class="empty" style="text-align:center;padding:14px;">keine</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lam-card">
                    <h3>Monitoring-Alerts</h3>
                    <div class="lam-table-wrap">
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Kunde</th><th>Domain</th><th class="center">HTTP</th><th>Zeitpunkt</th></tr></thead>
                            <tbody>
                                <template x-for="a in (widgets?.monitoring_alerts || [])" :key="a.id">
                                    <tr>
                                        <td><strong x-text="a.customer_kuerzel"></strong></td>
                                        <td x-text="a.domain_url"></td>
                                        <td class="center" x-text="a.http_status || '—'"></td>
                                        <td x-text="a.zeitpunkt"></td>
                                    </tr>
                                </template>
                                <tr x-show="!(widgets?.monitoring_alerts || []).length"><td colspan="4" class="empty" style="text-align:center;padding:14px;">keine</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="lam-grid-2" style="margin-bottom:20px;">
                <div class="lam-card">
                    <h3>Linkakquise offen</h3>
                    <div class="lam-table-wrap">
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Kunde</th><th>Domain</th><th>Kontakt am</th><th>Nächste Aktion</th></tr></thead>
                            <tbody>
                                <template x-for="e in (widgets?.linkakquise_offen || [])" :key="e.id">
                                    <tr>
                                        <td><strong x-text="e.customer_kuerzel"></strong></td>
                                        <td x-text="e.domain_url"></td>
                                        <td x-text="e.kontakt_am || '—'"></td>
                                        <td x-text="e.naechste_aktion_am || '—'"></td>
                                    </tr>
                                </template>
                                <tr x-show="!(widgets?.linkakquise_offen || []).length"><td colspan="4" class="empty" style="text-align:center;padding:14px;">keine</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lam-card">
                    <h3>Auslagen im laufenden Monat</h3>
                    <table class="lam-table" style="font-size:var(--d-fs-sm);">
                        <tbody>
                            <tr><td class="muted">Anzahl Auslagen</td><td x-text="widgets?.auslagen_monat?.anzahl ?? 0"></td></tr>
                            <tr><td class="muted">Gesamt externe Kosten</td><td x-text="euro(widgets?.auslagen_monat?.gesamt_kosten)"></td></tr>
                            <tr><td class="muted">Gesamt weiterverrechnet</td><td x-text="euro(widgets?.auslagen_monat?.gesamt_weiterverr)"></td></tr>
                            <tr><td class="muted">Gesamt Marge</td><td x-text="euro(widgets?.auslagen_monat?.gesamt_marge)"></td></tr>
                        </tbody>
                    </table>
                    <!-- Marge-Trend 12 Monate (SVG-Sparkline) -->
                    <div x-show="(widgets?.marge_trend || []).length > 0" style="margin-top:12px;">
                        <div class="muted" style="font-size:var(--d-fs-xs);margin-bottom:4px;">Marge-Verlauf (12 Monate)</div>
                        <div x-html="sparkline(widgets?.marge_trend || [])"></div>
                    </div>
                </div>

                <!-- Sammelposten (Wiederkehrende Buchungen) -->
                <div class="lam-card">
                    <h3>Sammelposten <span style="font-size:var(--d-fs-xs);color:var(--slate-500);font-weight:normal;">(wiederkehrend)</span>
                        <a href="/lam/massnahmen?sonderstatus=sammelposten" style="float:right;font-size:var(--d-fs-xs);color:var(--thoxan-700);">alle ›</a>
                    </h3>
                    <div class="lam-table-wrap">
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Kunde</th><th>Domain</th><th>Status</th><th class="right">Kosten</th></tr></thead>
                            <tbody>
                                <template x-for="s in (widgets?.sammelposten || [])" :key="s.id">
                                    <tr>
                                        <td><strong x-text="s.customer_kuerzel || '—'"></strong></td>
                                        <td class="url-cell">
                                            <a :href="'/lam/massnahmen/' + encodeURIComponent(s.id)" style="color:var(--thoxan-700);" x-text="s.domain_url"></a>
                                        </td>
                                        <td x-text="s.status"></td>
                                        <td class="right" x-text="euro(s.externe_kosten)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!(widgets?.sammelposten || []).length"><td colspan="4" class="empty" style="text-align:center;padding:14px;">keine Sammelposten</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Letzte LAM-Aktivitäten -->
                <div class="lam-card">
                    <h3>Letzte Aktivitäten</h3>
                    <div class="lam-table-wrap">
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <tbody>
                                <template x-for="a in (widgets?.letzte_aktivitaeten || [])" :key="a.typ + a.ref_id">
                                    <tr>
                                        <td style="width:55%;">
                                            <template x-if="a.basepath">
                                                <a :href="a.basepath + encodeURIComponent(a.ref_id)" style="color:var(--thoxan-700);text-decoration:none;" x-text="a.text"></a>
                                            </template>
                                            <template x-if="!a.basepath">
                                                <span x-text="a.text"></span>
                                            </template>
                                        </td>
                                        <td><span class="lam-badge" x-text="a.typ"></span></td>
                                        <td class="muted" style="color:var(--slate-500);font-size:var(--d-fs-xs);" x-text="formatRelativ(a.zeitpunkt)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!(widgets?.letzte_aktivitaeten || []).length"><td colspan="3" class="empty" style="text-align:center;padding:14px;">keine</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sistrix-Kontingent dieser Woche -->
                <div class="lam-card" x-show="widgets?.sistrix">
                    <h3 style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;">
                        <span>Sistrix-Wochenkontingent</span>
                        <a href="/admin/settings?tab=sistrix" style="font-size:var(--d-fs-xs);color:var(--thoxan-700);font-weight:400;">Einstellungen →</a>
                    </h3>
                    <template x-if="widgets?.sistrix?.konfiguriert">
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:var(--d-fs-xs);color:var(--slate-500);margin-bottom:4px;">
                                <span><strong x-text="zahl(widgets?.sistrix?.credits_verbraucht)"></strong> verbraucht</span>
                                <span><strong x-text="zahl(widgets?.sistrix?.credits_verbleibend)"></strong> übrig</span>
                            </div>
                            <div style="background:var(--slate-100);border-radius:6px;height:8px;overflow:hidden;">
                                <div :style="`width:${Math.min(100, (widgets?.sistrix?.credits_verbraucht / Math.max(1, widgets?.sistrix?.wochenkontingent)) * 100).toFixed(1)}%;height:100%;background:${widgets?.sistrix?.credits_verbleibend < widgets?.sistrix?.wochenkontingent*0.1 ? 'var(--rose-500)' : 'var(--thoxan-600)'};`"></div>
                            </div>
                            <div style="font-size:var(--d-fs-xs);color:var(--slate-400);margin-top:6px;">
                                von <span x-text="zahl(widgets?.sistrix?.wochenkontingent)"></span> · Reset Mo
                                · <span x-text="zahl(widgets?.sistrix?.abfragen)"></span> Abfragen
                            </div>
                        </div>
                    </template>
                    <template x-if="!widgets?.sistrix?.konfiguriert">
                        <div class="muted" style="font-size:var(--d-fs-sm);color:var(--slate-500);">
                            Kein API-Key gesetzt — <a href="/admin/settings?tab=sistrix" style="color:var(--thoxan-700);">hier einrichten</a>.
                        </div>
                    </template>
                </div>
            </div>
            <div class="lam-kpi-grid">
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Domains im Pool</div>
                    <div class="lam-kpi-value is-accent" x-text="zahl(stats.kennzahlen.domains_gesamt)"></div>
                    <div class="lam-kpi-sub"><span x-text="zahl(stats.kennzahlen.domains_verifiziert)"></span> verifiziert · <span x-text="zahl(stats.kennzahlen.domains_disqualifiziert)"></span> disqualifiziert</div>
                </div>
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Anbieter</div>
                    <div class="lam-kpi-value" x-text="zahl(stats.kennzahlen.anbieter_gesamt)"></div>
                    <div class="lam-kpi-sub"><span x-text="zahl(stats.kennzahlen.kontakte_gesamt)"></span> Kontakte</div>
                </div>
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Konditionen</div>
                    <div class="lam-kpi-value" x-text="zahl(stats.kennzahlen.konditionen_gesamt)"></div>
                    <div class="lam-kpi-sub">Preise / Buchungstypen</div>
                </div>
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Verlinkungen</div>
                    <div class="lam-kpi-value is-accent" x-text="zahl(stats.kennzahlen.verlinkungen_gesamt)"></div>
                    <div class="lam-kpi-sub">aus Linkprofil-Analyse</div>
                </div>
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Domain-Wissen</div>
                    <div class="lam-kpi-value" x-text="zahl(stats.kennzahlen.domain_wissen_gesamt)"></div>
                    <div class="lam-kpi-sub">kundenübergreifend</div>
                </div>
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Aktive Kunden</div>
                    <div class="lam-kpi-value" x-text="zahl(stats.kennzahlen.kunden_aktiv)"></div>
                </div>
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Maßnahmen offen</div>
                    <div class="lam-kpi-value" x-text="zahl(stats.kennzahlen.massnahmen_offen)"></div>
                    <div class="lam-kpi-sub"><span x-text="zahl(stats.kennzahlen.massnahmen_live)"></span> live</div>
                </div>
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Monitoring-Alerts</div>
                    <div :class="stats.kennzahlen.monitoring_alerts > 0 ? 'lam-kpi-value is-warn' : 'lam-kpi-value is-ok'" x-text="zahl(stats.kennzahlen.monitoring_alerts)"></div>
                </div>
            </div>

            <div class="lam-grid-2">
                <div class="lam-card">
                    <h3>Linkprofil pro Kunde</h3>
                    <div class="lam-table-wrap">
                        <table class="lam-table">
                            <thead>
                                <tr>
                                    <th>Kunde</th>
                                    <th class="right">Verlinkungen</th>
                                    <th class="right">Disavow</th>
                                    <th class="right">Unsicher</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in stats.pro_kunde" :key="row.id">
                                    <tr>
                                        <td>
                                            <strong x-text="row.abbreviation"></strong>
                                            <span class="muted" x-text="row.name"></span>
                                        </td>
                                        <td class="right" x-text="zahl(row.verlinkungen)"></td>
                                        <td class="right" x-text="zahl(row.disavow)"></td>
                                        <td class="right" x-text="zahl(row.unsicher)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!stats.pro_kunde.length">
                                    <td colspan="4" class="empty" style="text-align:center;padding:20px;">Keine Verlinkungs-Daten</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lam-card">
                    <h3>Top Anbieter (nach Domains)</h3>
                    <div class="lam-table-wrap">
                        <table class="lam-table">
                            <thead>
                                <tr>
                                    <th>Anbieter</th>
                                    <th class="right">Domains</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in stats.top_anbieter" :key="row.id">
                                    <tr>
                                        <td x-text="row.name"></td>
                                        <td class="right" x-text="zahl(row.domain_anzahl)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!stats.top_anbieter.length">
                                    <td colspan="2" class="empty" style="text-align:center;padding:20px;">Keine Anbieter</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function lamDashboard() {
    return {
        laedt: true,
        fehler: null,
        stats: { kennzahlen: {}, pro_kunde: [], top_anbieter: [] },
        widgets: null,

        async laden() {
            try {
                const [resStats, resWidgets] = await Promise.all([
                    fetch('/api/v1/lam/dashboard', { credentials: 'same-origin' }),
                    fetch('/api/v1/lam/dashboard-widgets', { credentials: 'same-origin' })
                ]);
                const jsonStats = await resStats.json();
                const jsonWidgets = await resWidgets.json();
                if (!jsonStats.success) throw new Error(jsonStats.message || 'Fehler');
                this.stats = jsonStats.data;
                this.widgets = jsonWidgets.success ? jsonWidgets.data : null;
            } catch (e) {
                this.fehler = e.message;
            } finally {
                this.laedt = false;
            }
        },

        zahl(n) {
            if (n === null || n === undefined) return '0';
            return new Intl.NumberFormat('de-DE').format(n);
        },
        euro(n) {
            if (n === null || n === undefined) return '—';
            return parseFloat(n).toLocaleString('de-DE', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        sparkline(daten) {
            if (!daten || daten.length === 0) return '';
            const werte = daten.map(d => parseFloat(d.marge_summe));
            const max = Math.max(...werte.map(Math.abs), 1);
            const w = 280, h = 40, n = werte.length;
            const punkte = werte.map((v, i) => {
                const x = (i / Math.max(n - 1, 1)) * w;
                const y = h / 2 - (v / max) * (h / 2 - 4);
                return `${x.toFixed(1)},${y.toFixed(1)}`;
            }).join(' ');
            const farbe = werte[werte.length - 1] >= 0 ? '#10b981' : '#ef4444';
            return `<svg viewBox="0 0 ${w} ${h}" style="width:100%;height:${h}px;">`
                + `<line x1="0" y1="${h/2}" x2="${w}" y2="${h/2}" stroke="#cbd5e1" stroke-width="0.5" stroke-dasharray="2,2"/>`
                + `<polyline points="${punkte}" fill="none" stroke="${farbe}" stroke-width="2"/>`
                + `</svg>`;
        },
        formatRelativ(isoOrMysql) {
            if (!isoOrMysql) return '';
            const t = new Date(isoOrMysql.replace(' ', 'T')).getTime();
            if (!t) return '';
            const sek = Math.floor((Date.now() - t) / 1000);
            if (sek < 60) return 'gerade eben';
            const min = Math.floor(sek / 60);
            if (min < 60) return min + ' Min';
            const std = Math.floor(min / 60);
            if (std < 24) return 'vor ' + std + ' Std';
            const tag = Math.floor(std / 24);
            if (tag < 7) return 'vor ' + tag + ' Tag' + (tag === 1 ? '' : 'en');
            if (tag < 60) return 'vor ' + Math.floor(tag / 7) + ' Wo';
            return new Date(t).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
        }
    };
}
</script>

<style>
.lam-tile {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    padding: 14px 16px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: border-color .15s, transform .15s, box-shadow .15s;
}
.lam-tile:hover {
    border-color: var(--thoxan-400);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,.05);
}
.lam-tile-icon { font-size: 24px; }
.lam-tile-label { font-weight: 600; color: var(--slate-800); }
.lam-tile-hint { font-size: var(--d-fs-xs); color: var(--slate-500); }
</style>
