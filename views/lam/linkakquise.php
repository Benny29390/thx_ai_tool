<?php $activeModul = 'linkakquise'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Linkakquise</h1>
        <div class="thx-page-subtitle">Vorab-Klärung mit Anbietern. Einträge mit Status „in Akquise".</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="lamLinkakquise()" x-init="laden()">
    <section class="lam-filter-card">
        <div class="lam-filter-head">
            <h2>Filter</h2>
            <span style="font-size:var(--d-fs-xs);color:var(--slate-400);"
                  x-text="rows.length ? (rows.length + ' Einträge') : ''"></span>
        </div>
        <div class="lam-filter-grid">
            <div class="lam-filter-col-12">
                <label class="lam-filter-label">Suche (Domain, Notiz, Artikelthema)</label>
                <input type="text" class="lam-filter-input"
                       placeholder="z.B. familienkasse"
                       x-model="filter.suche" @input.debounce.300ms="laden()">
            </div>
        </div>
    </section>

    <section class="lam-table-card">
        <div class="lam-table-wrap">
            <table class="lam-table">
                <thead>
                    <tr>
                        <th>Kunde</th>
                        <th>Domain</th>
                        <th>Anbieter</th>
                        <th>Liste</th>
                        <th>Kontakt am</th>
                        <th>Letzte Rückmeldung</th>
                        <th>Nächste Aktion</th>
                        <th class="right">Preis</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="e in rows" :key="e.id">
                        <tr>
                            <td><strong x-text="e.customer_kuerzel"></strong></td>
                            <td class="url-cell">
                                <a :href="'/lam/linkquellen/' + encodeURIComponent(e.domain_id)" style="color:var(--thoxan-700);" x-text="e.domain_url"></a>
                                <a :href="'https://' + e.domain_url" target="_blank" rel="noopener" title="extern öffnen" style="color:var(--slate-400);margin-left:4px;">↗</a>
                            </td>
                            <td>
                                <template x-if="e.anbieter_id">
                                    <a :href="'/lam/anbieter/' + encodeURIComponent(e.anbieter_id)" style="color:var(--thoxan-700);" x-text="e.anbieter_name"></a>
                                </template>
                                <template x-if="!e.anbieter_id"><span class="empty">—</span></template>
                            </td>
                            <td>
                                <a :href="'/lam/vorschlagslisten/' + encodeURIComponent(e.liste_id)" style="color:var(--slate-700);" x-text="e.liste_titel"></a>
                                <div x-show="e.artikelthema" class="muted" style="font-size:var(--d-fs-xs);color:var(--slate-500);" x-text="e.artikelthema"></div>
                            </td>
                            <td x-text="e.kontakt_am || '—'"></td>
                            <td>
                                <template x-if="e.letzte_rueckmeldung_am">
                                    <span><span x-text="e.letzte_rueckmeldung_am"></span> <span class="muted" x-text="'(' + (e.letzte_rueckmeldung_typ || '') + ')'"></span></span>
                                </template>
                                <template x-if="!e.letzte_rueckmeldung_am"><span class="empty">—</span></template>
                            </td>
                            <td x-text="e.naechste_aktion_am || '—'"></td>
                            <td class="right">
                                <template x-if="e.preis_kunde !== null">
                                    <span x-text="euro(e.preis_kunde)"></span>
                                </template>
                                <template x-if="e.preis_kunde === null"><span class="empty">—</span></template>
                            </td>
                            <td style="white-space:nowrap;display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
                                <template x-if="e.primaer_email">
                                    <a :href="'mailto:' + e.primaer_email + '?subject=' + encodeURIComponent('Linkanfrage: ' + (e.artikelthema || e.domain_url))"
                                       class="lam-btn lam-btn-secondary lam-btn-small"
                                       @click="markiereKontaktiert(e)"
                                       title="E-Mail an Primärkontakt">
                                        ✉
                                    </a>
                                </template>
                                <button class="lam-btn lam-btn-accent lam-btn-small" @click="oeffneRueckmeldung(e)"
                                        title="Rückmeldung erfassen — setzt Status je nach Typ automatisch weiter">
                                    Rückmeldung …
                                </button>
                                <!-- Quick-Aktionen: direkter Status-Wechsel ohne Modal -->
                                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schnellStatus(e, 'bestaetigt')"
                                        style="color:var(--emerald-700);" title="Bestätigt — Anbieter ist offen, Kunde fragen">👍</button>
                                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schnellStatus(e, 'abgelehnt')"
                                        style="color:var(--rose-700);" title="Abgelehnt — Anbieter sagt ab">👎</button>
                                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schnellStatus(e, 'ohne_antwort')"
                                        style="color:var(--amber-700);" title="Ohne Antwort — keine Reaktion">🤷</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="lam-empty" x-show="!laedt && rows.length === 0" style="padding:40px 20px;text-align:center;">
                <div style="font-size:2rem;margin-bottom:8px;">📭</div>
                <div style="font-weight:600;color:var(--slate-700);margin-bottom:6px;">Keine Einträge im Status „in Akquise"</div>
                <div style="color:var(--slate-500);font-size:var(--d-fs-sm);max-width:520px;margin:0 auto 14px;">
                    Einträge erscheinen hier, sobald sie in der <a href="/lam/linkoptionen" style="color:var(--thoxan-700);">Linkoptionen</a>-Sicht auf Status <code>in_akquise</code> gesetzt werden. Vorher landen sie als <code>vorgeschlagen</code> in einer Vorschlagsliste.
                </div>
                <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                    <a href="/lam/linkoptionen" class="lam-btn lam-btn-primary lam-btn-small">
                        → Linkoptionen öffnen
                    </a>
                    <a href="/lam/linkquellen" class="lam-btn lam-btn-secondary lam-btn-small">
                        Linkquellen-Pool durchsuchen
                    </a>
                    <a href="/lam/vorschlagslisten" class="lam-btn lam-btn-secondary lam-btn-small">
                        Vorschlagslisten verwalten
                    </a>
                </div>
                <div style="margin-top:18px;padding:12px 16px;background:var(--slate-50);border-radius:6px;font-size:var(--d-fs-xs);color:var(--slate-600);max-width:600px;margin-left:auto;margin-right:auto;text-align:left;">
                    <strong>Workflow:</strong> Linkquellen-Pool → Domains anhaken → „📋 Auf Vorschlagsliste" → in Linkoptionen Status auf <code>in_akquise</code> setzen → erscheint hier mit Rückmeldung-/Mail-Aktionen.
                </div>
            </div>
            <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span> Lade …</div>
        </div>
    </section>

    <!-- Modal: Rückmeldung erfassen -->
    <div class="thx-modal-backdrop" x-show="rueckmeldung.offen" @click.self="rueckmeldung.offen = false" x-cloak>
        <div class="thx-modal" style="max-width:560px;">
            <div class="thx-modal-header">
                <h2 class="thx-modal-title">Rückmeldung erfassen</h2>
                <button class="thx-modal-close" @click="rueckmeldung.offen = false">×</button>
            </div>
            <div class="thx-modal-body" style="display:flex;flex-direction:column;gap:14px;">
                <div class="muted" style="font-size:var(--d-fs-sm);color:var(--slate-600);">
                    <strong x-text="rueckmeldung.eintrag?.customer_kuerzel"></strong> ·
                    <span x-text="rueckmeldung.eintrag?.domain_url"></span>
                </div>
                <div class="thx-form-field">
                    <label>Rückmeldung am *</label>
                    <input type="date" x-model="rueckmeldung.rueckmeldung_am">
                </div>
                <div class="thx-form-field">
                    <label>Art der Rückmeldung *</label>
                    <select x-model="rueckmeldung.rueckmeldung_typ">
                        <option value="">— wählen —</option>
                        <option value="interesse">Interesse — Anbieter ist offen → bestätigt</option>
                        <option value="preisangebot">Preisangebot bekommen → bestätigt</option>
                        <option value="absage">Absage → abgelehnt</option>
                        <option value="spam">Spam / unseriös → abgelehnt</option>
                        <option value="keine_reaktion">Keine Reaktion → ohne_antwort</option>
                        <option value="rueckfrage">Rückfrage (bleibt in Akquise)</option>
                    </select>
                    <div x-show="rueckmeldung.rueckmeldung_typ" style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-top:4px;"
                         x-text="autoStatusHinweis(rueckmeldung.rueckmeldung_typ)"></div>
                </div>
                <div class="thx-form-field" x-show="rueckmeldung.rueckmeldung_typ === 'preisangebot'">
                    <label>Preis (Anbieter-Angebot, optional)</label>
                    <input type="number" step="0.01" x-model="rueckmeldung.preis_kunde" placeholder="z.B. 350.00">
                </div>
                <div class="thx-form-field">
                    <label>Nächste Aktion am</label>
                    <input type="date" x-model="rueckmeldung.naechste_aktion_am">
                </div>
                <div class="thx-form-field">
                    <label>Notiz zur nächsten Aktion</label>
                    <textarea x-model="rueckmeldung.naechste_aktion_notiz" rows="2"
                              placeholder="z.B. Preis verhandeln / Inhalt anpassen / Erinnerung in 1 Woche"></textarea>
                </div>
            </div>
            <div class="thx-modal-footer">
                <button class="lam-btn lam-btn-secondary" @click="rueckmeldung.offen = false">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichereRueckmeldung()"
                        :disabled="rueckmeldung.laeuft || !rueckmeldung.rueckmeldung_am || !rueckmeldung.rueckmeldung_typ">
                    <span x-show="!rueckmeldung.laeuft">Speichern</span><span x-show="rueckmeldung.laeuft">…</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function lamLinkakquise() {
    return {
        laedt: true, rows: [], filter: { suche: '' },
        rueckmeldung: {
            offen: false, laeuft: false, eintrag: null,
            rueckmeldung_am: '', rueckmeldung_typ: '',
            preis_kunde: '',
            naechste_aktion_am: '', naechste_aktion_notiz: '',
        },
        oeffneRueckmeldung(e) {
            this.rueckmeldung = {
                offen: true, laeuft: false, eintrag: e,
                rueckmeldung_am: new Date().toISOString().slice(0, 10),
                rueckmeldung_typ: '',
                preis_kunde: e.preis_kunde ?? '',
                naechste_aktion_am: e.naechste_aktion_am || '',
                naechste_aktion_notiz: e.naechste_aktion_notiz || '',
            };
        },
        async speichereRueckmeldung() {
            const r = this.rueckmeldung;
            if (!r.eintrag || !r.rueckmeldung_am || !r.rueckmeldung_typ) return;
            r.laeuft = true;
            try {
                const payload = {
                    id: r.eintrag.id,
                    rueckmeldung_am: r.rueckmeldung_am,
                    rueckmeldung_typ: r.rueckmeldung_typ,
                    naechste_aktion_am: r.naechste_aktion_am || null,
                    naechste_aktion_notiz: r.naechste_aktion_notiz || null,
                };
                if (r.rueckmeldung_typ === 'preisangebot' && r.preis_kunde !== '') {
                    payload.preis_kunde = r.preis_kunde;
                }
                const resp = await fetch('/api/v1/lam/linkoption-rueckmeldung', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const json = await resp.json();
                if (json.success) {
                    this.rueckmeldung.offen = false;
                    this.laden();
                } else {
                    alert(json.message || 'Fehler');
                }
            } catch (e) { alert('Verbindungsfehler'); }
            finally { r.laeuft = false; }
        },
        autoStatusHinweis(typ) {
            const map = {
                interesse: '→ Eintrag wird nach Speichern automatisch auf „bestätigt" gesetzt.',
                preisangebot: '→ Eintrag wird automatisch auf „bestätigt" gesetzt; Preis-Feld erscheint.',
                absage: '→ Eintrag wird automatisch auf „abgelehnt" gesetzt und verschwindet aus der Akquise-Liste.',
                spam: '→ Eintrag wird automatisch auf „abgelehnt" gesetzt.',
                keine_reaktion: '→ Eintrag wird automatisch auf „ohne_antwort" gesetzt.',
                rueckfrage: 'Bleibt in „in_akquise" — wartet auf weitere Klärung.',
            };
            return map[typ] || '';
        },

        /** Quick-Status: setzt sofort den gewählten Status ohne Modal. */
        async schnellStatus(e, neuerStatus) {
            const map = { bestaetigt: 'Bestätigt', abgelehnt: 'Abgelehnt', ohne_antwort: 'Ohne Antwort' };
            if (!confirm(`Eintrag „${e.domain_url}" auf „${map[neuerStatus] || neuerStatus}" setzen?`)) return;
            try {
                const r = await fetch('/api/v1/lam/linkoption-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: e.id, feld: 'status', wert: neuerStatus }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.laden(); // Liste neu — der Eintrag verschwindet (wechselt aus in_akquise raus)
            } catch (err) { alert('Fehler: ' + err.message); }
        },

        async markiereKontaktiert(e) {
            // Setzt das Kontakt-Datum auf heute, falls noch nicht gesetzt — als Nebenwirkung
            // beim Klick auf den Mail-Button. Wenn schon ein Kontakt-Datum existiert, nichts tun.
            if (e.kontakt_am) return;
            const heute = new Date().toISOString().slice(0, 10);
            try {
                await fetch('/api/v1/lam/linkoption-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: e.id, feld: 'kontakt_am', wert: heute })
                });
                e.kontakt_am = heute;
            } catch (err) {}
        },
        async laden() {
            this.laedt = true;
            const p = new URLSearchParams();
            if (this.filter.suche) p.set('suche', this.filter.suche);
            try {
                const r = await fetch('/api/v1/lam/linkakquise?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.rows = j.success ? j.data : [];
            } finally { this.laedt = false; }
        },
        euro(n) { return n == null ? '—' : parseFloat(n).toLocaleString('de-DE', {style:'currency', currency:'EUR'}); }
    };
}
</script>
