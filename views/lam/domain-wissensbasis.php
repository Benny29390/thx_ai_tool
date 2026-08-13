<?php $activeModul = 'linkprofil'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamDomainWissen()" x-init="init()">

<div class="thx-page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
        <h1 class="thx-page-title">Domain-Wissensbasis</h1>
        <div class="thx-page-subtitle">
            Kundenuebergreifend gepflegte Klassifikationen für den Aufraeum-Modus. Manuelle Eintraege haben Vorrang vor KI-Vorschlaegen.
            <span x-show="gesamt" x-cloak> · <strong x-text="zahl(gesamt)"></strong> Domains</span>
        </div>
    </div>
    <a class="thx-btn thx-btn-secondary thx-btn-small" href="/lam/linkprofil">
        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">arrow_back</span> Zurueck zur Tabelle
    </a>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<style>
.dw-filter-card { background:#fff; border:1px solid var(--slate-200); border-radius:var(--d-card-radius); padding:10px 14px; margin-bottom:var(--d-section-gap); display:flex;flex-wrap:wrap;gap:8px;align-items:center; }
.dw-filter-card .thx-input, .dw-filter-card .thx-select { padding-top:4px; padding-bottom:4px; }
.dw-confidence { padding:2px 8px; border-radius:999px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; display:inline-block; }
.dw-confidence-manuell       { background:var(--emerald-50);  color:var(--emerald-700); }
.dw-confidence-ki_bestaetigt { background:#dbeafe;            color:#1e40af; }
.dw-confidence-ki            { background:#fef3c7;            color:#92400e; }
.dw-table-card { background:#fff; border:1px solid var(--slate-200); border-radius:var(--d-card-radius); overflow:hidden; }
.dw-empty { padding:30px; text-align:center; color:var(--slate-500); }
.dw-konflikt-badge { background:var(--rose-50); color:var(--rose-700); padding:1px 6px; border-radius:4px; font-size:10px; font-weight:700; margin-left:6px; }
</style>

<section class="dw-filter-card">
    <input type="text" class="thx-input" style="flex:1;min-width:260px;" placeholder="Domain suchen …"
           x-model="filter.suche" @input.debounce.300ms="reload(true)">
    <select class="thx-select" x-model="filter.linkart" @change="reload(true)" style="min-width:140px;">
        <option value="">alle Linkarten</option>
        <template x-for="la in linkarten" :key="la.wert"><option :value="la.wert" x-text="la.label"></option></template>
    </select>
    <select class="thx-select" x-model="filter.confidence" @change="reload(true)" style="min-width:160px;">
        <option value="">alle Confidence</option>
        <option value="manuell">manuell</option>
        <option value="ki_bestaetigt">KI bestaetigt</option>
        <option value="ki">nur KI</option>
    </select>
    <label style="display:flex;align-items:center;gap:6px;font-size:var(--d-fs-sm);">
        <input type="checkbox" x-model="filter.nur_konflikte" @change="reload(true)"> nur Konflikte
    </label>
    <span style="margin-left:auto;font-size:var(--d-fs-xs);color:var(--slate-500);" x-text="rows.length + ' / ' + zahl(gesamt) + ' angezeigt'"></span>
</section>

<section class="dw-table-card">
    <table class="lam-table" id="dw-table">
        <thead>
            <tr>
                <th>Domain</th>
                <th>Linkart</th>
                <th>Strategie</th>
                <th>Confidence</th>
                <th class="center">Klassifik.</th>
                <th class="center">Bestand</th>
                <th>Zuletzt bei</th>
                <th>Notiz</th>
                <th style="width:160px;">Aktion</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="r in rows" :key="r.id">
                <tr>
                    <td>
                        <a :href="'https://' + r.domain" target="_blank" rel="noopener" x-text="r.domain"></a>
                        <span x-show="istKonflikt(r)" class="dw-konflikt-badge" title="Bestand > Klassifikationen oder mehrfach klassifiziert">Konflikt</span>
                    </td>
                    <td x-text="linkartLabel(r.linkart)"></td>
                    <td x-text="strategieLabel(r.reduktionsstrategie)"></td>
                    <td><span :class="'dw-confidence dw-confidence-' + (r.confidence || 'manuell')" x-text="r.confidence || 'manuell'"></span></td>
                    <td class="center" x-text="r.anzahl_klassifikationen"></td>
                    <td class="center" x-text="r.bestand"></td>
                    <td x-text="r.letzter_customer_name || (r.letzter_customer_kuerzel || '—')"></td>
                    <td><span :title="r.notiz" x-text="(r.notiz || '—').slice(0, 60)"></span></td>
                    <td>
                        <button class="lam-btn lam-btn-small lam-btn-primary" @click="anwenden(r)" :disabled="!r.bestand"
                                :title="r.bestand ? 'Auf ' + r.bestand + ' aktive Verlinkung(en) anwenden' : 'Keine aktiven Verlinkungen'">
                            Anwenden <span x-show="r.bestand">(<span x-text="r.bestand"></span>)</span>
                        </button>
                        <button class="lam-btn lam-btn-small" style="color:var(--rose-700);" @click="loeschen(r)" title="Eintrag loeschen">Loeschen</button>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
    <div class="dw-empty" x-show="!laedt && rows.length === 0">Keine Eintraege mit diesen Filtern.</div>
    <div class="dw-empty" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span> Laedt …</div>
</section>

</div>

<script>
function lamDomainWissen() {
    return {
        rows: [], gesamt: 0, laedt: false,
        filter: { suche: '', linkart: '', confidence: '', nur_konflikte: false, limit: 100, offset: 0 },
        linkarten: [
            { wert: 'spam',               label: 'Spam' },
            { wert: 'branchenverzeichnis',label: 'Branchenverzeichnis' },
            { wert: 'fachverzeichnis',    label: 'Fachverzeichnis' },
            { wert: 'online_magazin',     label: 'Online-Magazin' },
            { wert: 'portal',             label: 'Portal' },
            { wert: 'blog',               label: 'Blog' },
            { wert: 'presseportal',       label: 'Presseportal' },
            { wert: 'forum',              label: 'Forum' },
            { wert: 'social_media',       label: 'Social Media' },
            { wert: 'referenzprojekt',    label: 'Referenzprojekt' },
            { wert: 'partner',            label: 'Partner' },
            { wert: 'sponsoring',         label: 'Sponsoring' },
            { wert: 'stellenboerse',      label: 'Stellenbörse' },
            { wert: 'veranstaltung',      label: 'Veranstaltung' },
            { wert: 'kommentarlink',      label: 'Kommentarlink' },
            { wert: 'podcast',            label: 'Podcast' },
            { wert: 'weiterleitung',      label: 'Weiterleitung' },
            { wert: 'sonstiges',          label: 'Sonstiges' },
        ],

        async init() { await this.reload(); },

        async reload(resetOffset = false) {
            if (resetOffset) this.filter.offset = 0;
            this.laedt = true;
            const p = new URLSearchParams();
            if (this.filter.suche)        p.set('suche', this.filter.suche);
            if (this.filter.linkart)      p.set('linkart', this.filter.linkart);
            if (this.filter.confidence)   p.set('confidence', this.filter.confidence);
            if (this.filter.nur_konflikte) p.set('nur_konflikte', '1');
            p.set('limit', this.filter.limit);
            p.set('offset', this.filter.offset);
            try {
                const r = await fetch('/api/v1/lam/domain-wissen?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) { this.rows = j.data.rows; this.gesamt = j.data.gesamt; }
            } finally { this.laedt = false; }
        },

        linkartLabel(w) { const l = this.linkarten.find(x => x.wert === w); return l ? l.label : (w || '—'); },
        strategieLabel(s) {
            if (s === 'reduktion_auf_1') return 'auf 1 reduzieren';
            if (s === 'alle_behalten')   return 'alle behalten';
            return s || '—';
        },
        istKonflikt(r) { return (r.bestand > r.anzahl_klassifikationen) || (r.anzahl_klassifikationen > 1); },
        zahl(n) { return n == null ? '0' : new Intl.NumberFormat('de-DE').format(n); },

        async anwenden(r) {
            if (!r || !r.domain) { alert('Keine Domain ausgewählt.'); return; }
            if (!confirm('Auf ' + (r.bestand || 0) + ' Verlinkung(en) anwenden? Bestehende Werte werden überschrieben.')) return;
            try {
                const csrf = window.App?.csrfToken
                    || document.querySelector('meta[name="csrf-token"]')?.content
                    || '';
                const res = await fetch('/api/v1/lam/domain-wissen-anwenden', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ domain: r.domain, force: true }),
                });
                const j = await res.json();
                if (!j.success) throw new Error(j.error || j.message || 'Fehler');
                const linkart = j.data.linkart_aktualisiert || 0;
                const empf = j.data.empfehlung_aktualisiert || 0;
                if (linkart === 0 && empf === 0) {
                    alert('Es war nichts zu aktualisieren — alle Verlinkungen sind bereits auf dem Domain-Wissens-Stand.');
                } else {
                    alert('✓ Angewendet: ' + linkart + ' Linkart' + (linkart !== 1 ? 'en' : '') + ', ' + empf + ' Empfehlung' + (empf !== 1 ? 'en' : '') + '.');
                }
                this.reload();
            } catch (e) { alert('Anwenden fehlgeschlagen: ' + e.message); }
        },

        async loeschen(r) {
            if (!confirm('Eintrag für „' + r.domain + '" loeschen? Bestehende Verlinkungen bleiben unveraendert.')) return;
            try {
                const csrf = window.App?.csrfToken
                    || document.querySelector('meta[name="csrf-token"]')?.content
                    || '';
                const res = await fetch('/api/v1/lam/domain-wissen-delete', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ id: r.id }),
                });
                const j = await res.json();
                if (!j.success) throw new Error(j.message || 'Fehler');
                this.reload();
            } catch (e) { alert(e.message); }
        },
    };
}
</script>
