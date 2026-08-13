<?php $activeModul = 'linkprofil'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="historienImport()" x-cloak>

<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Historien-Import (alte Daten übernehmen)</h1>
        <div class="thx-page-subtitle">Vorhandene Listen aus Excel/CSV manuell in das LAM-System überführen. Du wählst, welche Spalte zu welchem Feld gehört.</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<section class="lam-card">
    <h3>Schritt 1: Datei hochladen</h3>
    <p class="muted">CSV, TSV oder Excel (.xlsx) aus alten Listen (Maßnahmen-Historie, Auslagen, Kontaktverlauf). Maximal 10 MB.</p>
    <input type="file" @change="dateiGewaehlt($event)" accept=".csv,.tsv,.txt,.xlsx" :disabled="schritt > 1">
    <div x-show="datei" class="muted" style="margin-top:10px;font-size:var(--d-fs-sm);">
        ✓ <span x-text="datei?.name"></span> (<span x-text="(datei?.size / 1024).toFixed(1)"></span> KB)
    </div>
</section>

<section class="lam-card" x-show="schritt >= 2" style="margin-top:16px;">
    <h3>Schritt 2: Ziel-Tabelle wählen</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="lam-chip" :class="ziel === 'massnahmen' ? 'is-active' : ''" @click="ziel = 'massnahmen'">Maßnahmen-Historie</button>
        <button class="lam-chip" :class="ziel === 'auslagen' ? 'is-active' : ''" @click="ziel = 'auslagen'">Auslagen-Historie</button>
        <button class="lam-chip" :class="ziel === 'korrespondenz' ? 'is-active' : ''" @click="ziel = 'korrespondenz'">Korrespondenz-Historie</button>
        <button class="lam-chip" :class="ziel === 'linkprofil' ? 'is-active' : ''" @click="ziel = 'linkprofil'">Linkprofil (Verlinkungen)</button>
    </div>
</section>

<section class="lam-card" x-show="schritt >= 3 && spalten.length > 0" style="margin-top:16px;">
    <h3>Schritt 3: Spalten zuordnen</h3>
    <p class="muted">Für jede CSV-Spalte das passende Ziel-Feld wählen. Nicht zugeordnete Spalten werden ignoriert.</p>
    <table class="lam-table" style="font-size:var(--d-fs-sm);">
        <thead>
            <tr><th>CSV-Spalte</th><th>Beispiel-Wert</th><th>→ Zielfeld</th></tr>
        </thead>
        <tbody>
            <template x-for="(sp, idx) in spalten" :key="idx">
                <tr>
                    <td><strong x-text="sp.name"></strong></td>
                    <td class="muted" x-text="sp.beispiel || '—'"></td>
                    <td>
                        <select x-model="mapping[idx]">
                            <option value="">— ignorieren —</option>
                            <template x-for="f in zielFelder" :key="f.key">
                                <option :value="f.key" x-text="f.label"></option>
                            </template>
                        </select>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>

    <div style="margin-top:16px;display:flex;gap:8px;">
        <button class="lam-btn lam-btn-secondary" @click="kiMapping()" :disabled="kiMappingLaeuft || !ziel" title="KI schlägt das Spalten-Mapping anhand der Header + Beispielwerte vor.">
            <span x-show="!kiMappingLaeuft">🤖 KI-Vorschlag fürs Mapping</span>
            <span x-show="kiMappingLaeuft">… KI denkt</span>
        </button>
        <button class="lam-btn lam-btn-primary" @click="vorschauZeigen()" :disabled="!hatPflichtfelderZugeordnet()">
            Vorschau anzeigen
        </button>
        <span class="muted" style="font-size:var(--d-fs-xs);align-self:center;" x-show="!hatPflichtfelderZugeordnet()">
            Mindestens die Pflichtfelder (markiert mit *) müssen zugeordnet sein.
        </span>
    </div>
</section>

<section class="lam-card" x-show="vorschau.length > 0" style="margin-top:16px;">
    <h3>Schritt 4: Vorschau (<span x-text="vorschau.length"></span> Zeilen)</h3>
    <div class="lam-table-wrap" style="max-height:400px;overflow-y:auto;">
        <table class="lam-table" style="font-size:var(--d-fs-sm);">
            <thead>
                <tr>
                    <template x-for="f in zugeordneteFelder()" :key="f">
                        <th x-text="zielLabel(f)"></th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <template x-for="(zeile, idx) in vorschau" :key="idx">
                    <tr>
                        <template x-for="f in zugeordneteFelder()" :key="f">
                            <td x-text="zeile[f] || '—'"></td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;background:var(--amber-50);border:1px solid var(--amber-200);border-radius:6px;padding:12px;font-size:var(--d-fs-sm);">
        <strong>Hinweis:</strong> Vorschau zeigt die ersten 20 Zeilen, Import läuft über alle <span x-text="roh.length"></span> Zeilen.
        Bei Linkprofil-Importen werden Dubletten (Quell+Ziel-URL bereits vorhanden) automatisch übersprungen.
    </div>

    <div style="margin-top:16px;display:flex;gap:8px;align-items:center;">
        <button class="lam-btn lam-btn-primary" @click="importieren()" :disabled="importLaeuft || imported">
            <span x-show="!importLaeuft && !imported">→ Alle <span x-text="roh.length"></span> Zeilen importieren</span>
            <span x-show="importLaeuft">… Import läuft</span>
            <span x-show="imported">✓ Importiert</span>
        </button>
        <span x-show="importErgebnis" class="muted" style="font-size:var(--d-fs-sm);">
            <span x-text="importErgebnis"></span>
        </span>
    </div>
    <div x-show="importFehler.length > 0" style="margin-top:12px;background:var(--rose-50);border:1px solid var(--rose-200);padding:10px;border-radius:6px;font-size:var(--d-fs-xs);">
        <strong>Fehler-Liste (Auszug):</strong>
        <ul style="margin:6px 0 0;padding-left:20px;">
            <template x-for="(f, i) in importFehler" :key="i"><li x-text="f"></li></template>
        </ul>
    </div>
</section>

</div>

<style>[x-cloak]{display:none!important}</style>

<script>
const ZIEL_FELDER = {
    massnahmen: [
        { key: 'customer', label: 'Kunde-Kürzel *' },
        { key: 'domain', label: 'Domain (URL) *' },
        { key: 'vorgangstyp', label: 'Vorgangstyp' },
        { key: 'status', label: 'Status' },
        { key: 'geplant_am', label: 'Geplant am (TT.MM.JJJJ)' },
        { key: 'veroeffentlicht_am', label: 'Veröffentlicht am' },
        { key: 'veroeffentlichungs_url', label: 'Veröffentlichungs-URL' },
        { key: 'linktext', label: 'Linktext' },
    ],
    auslagen: [
        { key: 'massnahme', label: 'Maßnahme/Domain *' },
        { key: 'externe_kosten', label: 'Externe Kosten (EUR) *' },
        { key: 'weiterverrechnet', label: 'Weiterverrechnet (EUR)' },
        { key: 'rechnung_nr', label: 'Rechnung Thoxan Nr.' },
        { key: 'rechnung_datum', label: 'Rechnung Datum' },
        { key: 'sonderfall', label: 'Sonderfall' },
    ],
    korrespondenz: [
        { key: 'anbieter', label: 'Anbieter *' },
        { key: 'zeitpunkt', label: 'Zeitpunkt *' },
        { key: 'typ', label: 'Typ (mail/anruf/notiz)' },
        { key: 'betreff', label: 'Betreff' },
        { key: 'inhalt', label: 'Inhalt' },
    ],
    linkprofil: [
        { key: 'customer', label: 'Kunde *' },
        { key: 'verlinkende_url', label: 'Verlinkende URL *' },
        { key: 'ziel_url', label: 'Ziel-URL' },
        { key: 'linktext', label: 'Linktext' },
        { key: 'linkart', label: 'Linkart (alt: „Branchenverzeichnis", „Händler/Partner" usw. werden automatisch gemappt)' },
        { key: 'empfehlung', label: 'Empfehlung (alt: „lassen"/„gelöscht"/„ändern" usw. — wird als Domain-Wissen gelernt)' },
    ],
};

function historienImport() {
    return {
        datei: null,
        schritt: 1,
        ziel: '',
        roh: [],
        spalten: [],
        mapping: {},
        vorschau: [],
        importLaeuft: false,
        imported: false,
        importErgebnis: '',
        importFehler: [],
        kiMappingLaeuft: false,

        async kiMapping() {
            if (!this.ziel || this.spalten.length === 0) return;
            this.kiMappingLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/ki-spalten-mapping', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ziel: this.ziel, spalten: this.spalten }),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'KI-Mapping fehlgeschlagen.'); return; }
                this.mapping = {};
                Object.entries(j.data.mapping || {}).forEach(([idx, feld]) => {
                    this.mapping[parseInt(idx)] = feld;
                });
                alert(`Mapping vorgeschlagen (Quelle: ${j.data.quelle}). Bitte prüfen und ggf. anpassen.`);
            } finally { this.kiMappingLaeuft = false; }
        },

        get zielFelder() { return ZIEL_FELDER[this.ziel] || []; },

        zielLabel(key) {
            const f = this.zielFelder.find(x => x.key === key);
            return f ? f.label : key;
        },

        async dateiGewaehlt(ev) {
            this.datei = ev.target.files[0];
            if (!this.datei) return;
            const ist_xlsx = /\.xlsx$/i.test(this.datei.name);
            if (ist_xlsx) {
                // Server-seitiges Parsing
                const fd = new FormData();
                fd.append('xlsx', this.datei);
                const r = await fetch('/api/v1/lam/xlsx-parse', { method: 'POST', credentials: 'same-origin', body: fd });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'XLSX-Parsing fehlgeschlagen.'); return; }
                this.spalten = j.data.spalten;
                this.roh = j.data.roh;
            } else {
                const text = await this.datei.text();
                this.parsen(text);
            }
            this.mapping = {};
            this.schritt = 2;
        },

        parsen(text) {
            // Auto-detect delimiter
            const erstZeile = text.split(/\r?\n/)[0] || '';
            const delim = erstZeile.includes(';') ? ';' : (erstZeile.includes('\t') ? '\t' : ',');
            const zeilen = text.split(/\r?\n/).filter(z => z.trim());
            const header = this.splitCsv(zeilen[0], delim);
            this.roh = zeilen.slice(1, 200).map(z => this.splitCsv(z, delim));
            this.spalten = header.map((name, idx) => ({
                name,
                beispiel: this.roh[0] ? (this.roh[0][idx] || '') : '',
            }));
            this.mapping = {};
        },

        splitCsv(line, delim) {
            // einfacher CSV-Parser mit Quote-Handling
            const out = [];
            let cur = '', inQuote = false;
            for (let i = 0; i < line.length; i++) {
                const c = line[i];
                if (c === '"' && line[i+1] === '"') { cur += '"'; i++; continue; }
                if (c === '"') { inQuote = !inQuote; continue; }
                if (c === delim && !inQuote) { out.push(cur); cur = ''; continue; }
                cur += c;
            }
            out.push(cur);
            return out;
        },

        hatPflichtfelderZugeordnet() {
            if (!this.ziel) return false;
            const pflicht = this.zielFelder.filter(f => f.label.endsWith('*')).map(f => f.key);
            const zugeordnet = Object.values(this.mapping);
            return pflicht.every(p => zugeordnet.includes(p));
        },

        zugeordneteFelder() {
            return [...new Set(Object.values(this.mapping).filter(Boolean))];
        },

        vorschauZeigen() {
            this.vorschau = this.roh.slice(0, 20).map(zeile => {
                const out = {};
                Object.entries(this.mapping).forEach(([idx, feld]) => {
                    if (feld) out[feld] = zeile[parseInt(idx)] || '';
                });
                return out;
            });
        },

        gemappteZeilen() {
            return this.roh.map(zeile => {
                const out = {};
                Object.entries(this.mapping).forEach(([idx, feld]) => {
                    if (feld) out[feld] = zeile[parseInt(idx)] || '';
                });
                return out;
            });
        },

        async importieren() {
            if (!this.ziel || this.importLaeuft) return;
            if (!confirm(`Wirklich ${this.roh.length} Zeilen in "${this.ziel}" importieren? Das kann nicht rückgängig gemacht werden.`)) return;
            this.importLaeuft = true;
            this.importErgebnis = '';
            this.importFehler = [];
            try {
                const r = await fetch('/api/v1/lam/historien-import-ausfuehren', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ ziel: this.ziel, zeilen: this.gemappteZeilen() }),
                });
                const j = await r.json();
                if (j.success) {
                    this.imported = true;
                    this.importErgebnis = `✓ ${j.data.ok} angelegt, ${j.data.fehler} Fehler.`;
                    this.importFehler = j.data.fehler_liste || [];
                } else {
                    this.importErgebnis = '✗ ' + (j.message || j.error || 'Import fehlgeschlagen.');
                }
            } catch (e) {
                this.importErgebnis = '✗ Verbindungsfehler.';
            } finally { this.importLaeuft = false; }
        },
    };
}
</script>
