<?php $activeModul = 'dubletten'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Dubletten</h1>
        <div class="thx-page-subtitle">Mögliche Dublettenpaare nach E-Mail, Telefon und Firma+Name.</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="crmDub()" x-init="initial()" x-cloak>

    <!-- ───────── Scan-Startbereich ───────── -->
    <div class="thx-card" style="padding:18px 22px;margin-bottom:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
            <div>
                <strong>Dubletten-Suche</strong>
                <div style="font-size:0.82rem;color:var(--slate-500);margin-top:3px;">
                    Prüft alle Kontakte auf Übereinstimmungen in E-Mail, Telefon (normalisiert) und Firma+Nachname.
                    <strong>Dauer: 10-60 Sekunden</strong> je nach Datenmenge.
                </div>
            </div>
            <button class="thx-btn thx-btn-primary" @click="starteScan()" :disabled="scanLaeuft" style="white-space:nowrap;">
                <span x-show="!scanLaeuft" style="display:inline-flex;align-items:center;gap:6px;"><span class="material-symbols-rounded" style="font-size:18px;">search</span>Dubletten-Scan starten</span>
                <span x-show="scanLaeuft">⏳ Scan läuft …</span>
            </button>
        </div>
        <div x-show="letzterScan" style="margin-top:10px;font-size:0.78rem;color:var(--slate-500);">
            Letzter Scan: <strong x-text="letzterScan"></strong> · <strong x-text="dubletten.length"></strong> Paare gefunden
        </div>
    </div>

    <!-- ───────── Ergebnistabelle ───────── -->
    <template x-if="!scanLaeuft && dubletten.length > 0">
        <div class="thx-table-wrap">
            <table class="lam-table">
                <thead>
                    <tr>
                        <th>Kontakt A</th>
                        <th>Kontakt B</th>
                        <th>Match-Typ</th>
                        <th>Match-Wert</th>
                        <th style="width:200px;">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="d in dubletten" :key="d.id1 + '-' + d.id2">
                        <tr>
                            <td>
                                <a :href="'/crm/kontakte/' + d.id1" target="_blank"><strong x-text="(d.v1||'') + ' ' + (d.n1||'')"></strong></a><br>
                                <span style="color:var(--slate-500);font-size:0.78rem;" x-text="d.e1 || '—'"></span>
                                <span x-show="d.fn1" style="color:var(--slate-400);font-size:0.7rem;display:block;" x-text="d.fn1"></span>
                            </td>
                            <td>
                                <a :href="'/crm/kontakte/' + d.id2" target="_blank"><strong x-text="(d.v2||'') + ' ' + (d.n2||'')"></strong></a><br>
                                <span style="color:var(--slate-500);font-size:0.78rem;" x-text="d.e2 || '—'"></span>
                                <span x-show="d.fn2" style="color:var(--slate-400);font-size:0.7rem;display:block;" x-text="d.fn2"></span>
                            </td>
                            <td><span class="lam-chip" x-text="d.match_typ"></span></td>
                            <td style="font-size:0.78rem;color:var(--slate-600);" x-text="d.match_wert || '—'"></td>
                            <td>
                                <button class="thx-btn thx-btn-primary thx-btn-small" @click="oeffneMerge(d.id1, d.id2)">Zusammenführen …</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <template x-if="!scanLaeuft && letzterScan && dubletten.length === 0">
        <div class="thx-card" style="padding:30px;text-align:center;color:var(--slate-500);">
            <span class="material-symbols-rounded" style="vertical-align:middle;color:var(--emerald-600);">check_circle</span> Keine Dubletten gefunden.
        </div>
    </template>

    <!-- ───────── Loading-Modal ───────── -->
    <div x-show="scanLaeuft" x-cloak class="thx-lightbox" style="background:rgba(15,23,42,0.6);z-index:1300;">
        <div class="thx-modal" style="max-width:420px;text-align:center;padding:30px;">
            <div style="font-size:1.4rem;margin-bottom:14px;">⏳</div>
            <h3 style="margin:0 0 8px 0;font-size:1.05rem;">Dubletten-Scan läuft</h3>
            <p style="margin:0;color:var(--slate-500);font-size:0.85rem;">
                Bitte einen Moment Geduld — bei 2300+ Kontakten kann der Scan bis zu 60 Sekunden dauern.<br><br>
                <span style="color:var(--slate-400);font-size:0.78rem;">Du kannst diese Seite verlassen und später zurückkehren — das Ergebnis kommt in der gleichen Browser-Session an.</span>
            </p>
            <div style="margin-top:20px;height:4px;background:var(--slate-100);border-radius:2px;overflow:hidden;position:relative;">
                <div style="position:absolute;top:0;left:0;height:100%;width:40%;background:var(--thoxan-500);animation:scanslide 1.2s ease-in-out infinite;"></div>
            </div>
        </div>
    </div>

    <!-- ───────── MERGE-DIALOG (unverändert) ───────── -->
    <div x-show="mergeDialog.offen" x-cloak class="thx-lightbox" style="background:rgba(15,23,42,0.6);z-index:1300;" @click.self="schliesseMerge()">
        <div class="thx-modal" style="max-width:1100px;width:95%;max-height:90vh;display:flex;flex-direction:column;">
            <div style="padding:14px 22px;border-bottom:1px solid var(--slate-200);">
                <h3 style="margin:0;font-size:1.05rem;">Kontakte zusammenführen — Feld für Feld</h3>
                <div style="font-size:0.8rem;color:var(--slate-500);margin-top:3px;">Wähle pro Zeile, welcher Wert übernommen wird. Kontakt B wird nach dem Merge als gelöscht markiert.</div>
            </div>
            <div x-show="mergeDialog.laedt" style="padding:30px;text-align:center;color:var(--slate-400);">Lade Daten …</div>

            <template x-if="!mergeDialog.laedt && mergeDialog.primary && mergeDialog.secondary">
                <div style="overflow-y:auto;flex:1;padding:14px 22px;">
                    <div style="background:var(--thoxan-50);border:1px solid var(--thoxan-200);border-radius:6px;padding:10px 14px;margin-bottom:14px;">
                        <strong>Behalten:</strong>
                        <label style="margin-left:14px;cursor:pointer;">
                            <input type="radio" :value="mergeDialog.idA" x-model="mergeDialog.primaryId" @change="aktualisiereSeiten()">
                            <span x-text="(mergeDialog.kontaktA.vorname||'') + ' ' + (mergeDialog.kontaktA.nachname||'')" style="font-weight:500;"></span>
                            <span style="color:var(--slate-500);font-size:0.78rem;" x-text="' (#' + mergeDialog.idA + ')'"></span>
                        </label>
                        <label style="margin-left:14px;cursor:pointer;">
                            <input type="radio" :value="mergeDialog.idB" x-model="mergeDialog.primaryId" @change="aktualisiereSeiten()">
                            <span x-text="(mergeDialog.kontaktB.vorname||'') + ' ' + (mergeDialog.kontaktB.nachname||'')" style="font-weight:500;"></span>
                            <span style="color:var(--slate-500);font-size:0.78rem;" x-text="' (#' + mergeDialog.idB + ')'"></span>
                        </label>
                    </div>
                    <table class="lam-table" style="font-size:0.85rem;">
                        <thead><tr><th style="width:140px;">Feld</th><th>Primary (bleibt)</th><th>Secondary (übernehmen?)</th><th class="center" style="width:200px;">Übernehmen</th></tr></thead>
                        <tbody>
                            <template x-for="feld in mergeFelder" :key="feld.key">
                                <tr :class="mergeDialog.feldwahl[feld.key] === 'secondary' ? 'is-bulk-selected' : ''">
                                    <td style="color:var(--slate-500);" x-text="feld.label"></td>
                                    <td x-text="formatFeldwert(feld.key, mergeDialog.primary[feld.key]) || '—'" :style="!mergeDialog.primary[feld.key] ? 'color:var(--slate-400);' : ''"></td>
                                    <td x-text="formatFeldwert(feld.key, mergeDialog.secondary[feld.key]) || '—'" :style="!mergeDialog.secondary[feld.key] ? 'color:var(--slate-400);' : (sindGleich(feld.key) ? 'color:var(--slate-400);' : 'font-weight:500;')"></td>
                                    <td class="center">
                                        <template x-if="sindGleich(feld.key)"><span style="color:var(--slate-400);font-size:0.78rem;">identisch</span></template>
                                        <template x-if="!sindGleich(feld.key)">
                                            <div style="display:flex;gap:2px;justify-content:center;font-size:0.78rem;">
                                                <label style="cursor:pointer;padding:2px 6px;border-radius:3px;" :style="mergeDialog.feldwahl[feld.key] === 'primary' ? 'background:var(--thoxan-100);' : ''">
                                                    <input type="radio" :name="'mwahl-' + feld.key" value="primary" x-model="mergeDialog.feldwahl[feld.key]" style="margin:0;"> A
                                                </label>
                                                <label style="cursor:pointer;padding:2px 6px;border-radius:3px;" :style="mergeDialog.feldwahl[feld.key] === 'secondary' ? 'background:var(--thoxan-100);' : ''">
                                                    <input type="radio" :name="'mwahl-' + feld.key" value="secondary" x-model="mergeDialog.feldwahl[feld.key]" style="margin:0;"> B
                                                </label>
                                                <label style="cursor:pointer;padding:2px 6px;border-radius:3px;color:var(--rose-700);" :style="mergeDialog.feldwahl[feld.key] === 'leer' ? 'background:var(--rose-50);' : ''">
                                                    <input type="radio" :name="'mwahl-' + feld.key" value="leer" x-model="mergeDialog.feldwahl[feld.key]" style="margin:0;"> leer
                                                </label>
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div style="margin-top:14px;padding:10px 14px;background:var(--slate-50);border-radius:6px;font-size:0.8rem;color:var(--slate-600);">
                        <strong>Wird zusätzlich vereinigt</strong> (immer alle übernommen): Tags · Listen-Mitgliedschaften · Adressen · Aktivitäten/Zeitlinie · Brevo-Events
                    </div>
                </div>
            </template>

            <div style="padding:10px 22px;border-top:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;gap:4px;">
                    <button class="thx-btn thx-btn-secondary thx-btn-small" @click="setzeAlle('primary')">Alle ← A</button>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" @click="setzeAlle('secondary')">Alle B →</button>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" @click="setzeBesteWahl()">Beste Wahl</button>
                </div>
                <div style="display:flex;gap:6px;">
                    <button class="thx-btn thx-btn-secondary" @click="schliesseMerge()">Abbrechen</button>
                    <button class="thx-btn thx-btn-primary" @click="fuehreMergeAus()" :disabled="mergeDialog.laeuft">
                        <span x-text="mergeDialog.laeuft ? 'Führe zusammen …' : 'Zusammenführen'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes scanslide {
    0%   { left: -40%; }
    100% { left: 100%; }
}
</style>

<script>
function crmDub() {
    return {
        scanLaeuft: false,
        dubletten: [],
        letzterScan: null,
        mergeFelder: [
            {key:'anrede',label:'Anrede'},{key:'titel',label:'Titel'},
            {key:'vorname',label:'Vorname'},{key:'nachname',label:'Nachname'},
            {key:'funktion',label:'Funktion'},{key:'abteilung',label:'Abteilung'},
            {key:'geburtsdatum',label:'Geburtsdatum'},
            {key:'email_primaer',label:'E-Mail (primär)'},{key:'email_zweit',label:'E-Mail (zweit)'},
            {key:'telefon',label:'Telefon'},{key:'telefon_alt',label:'Telefon (alt)'},
            {key:'mobil',label:'Mobil'},{key:'fax',label:'Fax'},{key:'website',label:'Website'},
            {key:'firma_id',label:'Firma-ID'},
            {key:'bevorzugtes_thema',label:'Bevorzugtes Thema'},
            {key:'interessen',label:'Interessen'},{key:'merkmale',label:'Merkmale'},
            {key:'beschreibung',label:'Beschreibung'},
            {key:'kontakt_status',label:'Status'},{key:'lead_quelle',label:'Lead-Quelle'},
            {key:'opt_in_status',label:'Opt-In'},{key:'thx_score',label:'THX-Score'},
            {key:'asana_task_gid',label:'Asana-Task'},{key:'deal_wert',label:'Deal-Wert'},
            {key:'deal_stufe',label:'Deal-Stufe'},{key:'foto_path',label:'Foto'},
        ],
        mergeDialog: {
            offen: false, laedt: false, laeuft: false,
            idA: 0, idB: 0, kontaktA: null, kontaktB: null,
            primaryId: 0, primary: null, secondary: null, feldwahl: {},
        },

        initial() {
            // Letzten Scan-Stand aus sessionStorage zeigen (kein Auto-Reload)
            const cached = sessionStorage.getItem('crm_dub_cache');
            if (cached) {
                try {
                    const c = JSON.parse(cached);
                    this.dubletten = c.dubletten || [];
                    this.letzterScan = c.zeitpunkt || null;
                } catch (e) {}
            }
        },

        async starteScan() {
            this.scanLaeuft = true;
            try {
                const r = await fetch('/api/v1/crm/dubletten?scan=1', { credentials: 'same-origin' });
                if (!r.ok) throw new Error('Server-Fehler ' + r.status);
                const j = await r.json();
                if (j.success) {
                    this.dubletten = j.data.dubletten || [];
                    this.letzterScan = new Date().toLocaleString('de-DE', { day:'2-digit', month:'2-digit', year:'2-digit', hour:'2-digit', minute:'2-digit' });
                    sessionStorage.setItem('crm_dub_cache', JSON.stringify({
                        dubletten: this.dubletten, zeitpunkt: this.letzterScan,
                    }));
                    App.showNotification(this.dubletten.length + ' Dublettenpaare gefunden', this.dubletten.length > 0 ? 'success' : 'info');
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) {
                App.showNotification('Scan abgebrochen: ' + e.message, 'error');
            }
            this.scanLaeuft = false;
        },

        // Merge-Dialog
        async oeffneMerge(idA, idB) {
            this.mergeDialog = { ...this.mergeDialog, offen: true, laedt: true, idA, idB, primaryId: idA };
            try {
                const [a, b] = await Promise.all([
                    fetch('/api/v1/crm/kontakte/' + idA).then(r => r.json()),
                    fetch('/api/v1/crm/kontakte/' + idB).then(r => r.json()),
                ]);
                if (!a.success || !b.success) throw new Error('Konnte Kontaktdaten nicht laden');
                this.mergeDialog.kontaktA = a.data;
                this.mergeDialog.kontaktB = b.data;
                this.aktualisiereSeiten();
                this.setzeBesteWahl();
            } catch (e) {
                App.showNotification(e.message, 'error');
                this.schliesseMerge();
            }
            this.mergeDialog.laedt = false;
        },
        aktualisiereSeiten() {
            if (this.mergeDialog.primaryId === this.mergeDialog.idA) {
                this.mergeDialog.primary = this.mergeDialog.kontaktA;
                this.mergeDialog.secondary = this.mergeDialog.kontaktB;
            } else {
                this.mergeDialog.primary = this.mergeDialog.kontaktB;
                this.mergeDialog.secondary = this.mergeDialog.kontaktA;
            }
            const wahl = {}; this.mergeFelder.forEach(f => { wahl[f.key] = 'primary'; });
            this.mergeDialog.feldwahl = wahl;
        },
        sindGleich(key) {
            const a = this.mergeDialog.primary[key] ?? null;
            const b = this.mergeDialog.secondary[key] ?? null;
            return (a == b) || (!a && !b);
        },
        setzeAlle(quelle) {
            const wahl = {...this.mergeDialog.feldwahl};
            this.mergeFelder.forEach(f => { if (!this.sindGleich(f.key)) wahl[f.key] = quelle; });
            this.mergeDialog.feldwahl = wahl;
        },
        setzeBesteWahl() {
            const wahl = {...this.mergeDialog.feldwahl};
            this.mergeFelder.forEach(f => {
                if (this.sindGleich(f.key)) { wahl[f.key] = 'primary'; return; }
                const p = this.mergeDialog.primary[f.key];
                const s = this.mergeDialog.secondary[f.key];
                if (!p && s) wahl[f.key] = 'secondary'; else wahl[f.key] = 'primary';
            });
            this.mergeDialog.feldwahl = wahl;
        },
        async fuehreMergeAus() {
            const md = this.mergeDialog;
            const feldwahl = {};
            this.mergeFelder.forEach(f => {
                if (this.sindGleich(f.key)) return;
                feldwahl[f.key] = md.feldwahl[f.key] || 'primary';
            });
            if (!confirm('Wirklich zusammenführen?\n\nKontakt #' + md.secondary.id + ' wird als gelöscht markiert.')) return;
            md.laeuft = true;
            try {
                const r = await fetch('/api/v1/crm/merge', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ primary_id: md.primary.id, secondary_id: md.secondary.id, feldwahl })
                });
                const j = await r.json();
                if (j.success) {
                    App.showNotification('Kontakte zusammengeführt', 'success');
                    this.schliesseMerge();
                    // Aus Cache entfernen
                    this.dubletten = this.dubletten.filter(d => d.id1 !== md.idA && d.id1 !== md.idB && d.id2 !== md.idA && d.id2 !== md.idB);
                    sessionStorage.setItem('crm_dub_cache', JSON.stringify({ dubletten: this.dubletten, zeitpunkt: this.letzterScan }));
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
            md.laeuft = false;
        },
        schliesseMerge() { this.mergeDialog.offen = false; },
        formatFeldwert(key, v) {
            if (v === null || v === undefined || v === '') return '';
            if (key === 'kontakt_status') return ({lead:'Lead',interessent:'Interessent',kunde:'Kunde',ehemaliger_kunde:'Ehemalig',partner:'Partner',wunschkunde:'Wunschkunde',dienstleister:'Dienstleister',sonstiges:'Sonstiges'})[v] || v;
            if (key === 'opt_in_status') return ({pending:'Pending',single_opted_in:'Single-OI',double_opted_in:'Double-OI',unsubscribed:'Abgemeldet',hard_bounce:'Hard Bounce',invalid:'Invalid'})[v] || v;
            if (key === 'firma_id') return '#' + v;
            return v;
        },
    };
}
</script>
