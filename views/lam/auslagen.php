<?php $activeModul = 'auslagen'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamAuslagen()" x-init="laden()">

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Auslagen</h1>
        <div class="thx-page-subtitle">Externe Kosten, Weiterverrechnung und Marge pro Maßnahme.</div>
    </div>
    <a class="lam-btn lam-btn-secondary"
       :href="'/api/v1/lam/auslagen-export?' + new URLSearchParams({jahr: filter.jahr || '', monat: filter.monat || '', sonderfall: filter.sonderfall || ''}).toString()">
       📤 CSV-Export
    </a>
    <a class="lam-btn lam-btn-secondary" x-show="filter.customer_id"
       :href="'/api/v1/lam/auslagen-excel?' + new URLSearchParams({customer_id: filter.customer_id || '', jahr: filter.jahr || new Date().getFullYear(), monat: filter.monat || ''}).toString()">
       📊 Excel pro Kunde
    </a>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>
    <section class="lam-filter-card">
        <div class="lam-filter-head">
            <h2>Filter</h2>
            <span style="font-size:var(--d-fs-xs);color:var(--slate-400);"
                  x-text="rows.length ? (rows.length + ' Einträge') : ''"></span>
        </div>
        <div class="lam-filter-grid">
            <div class="lam-filter-col-6">
                <label class="lam-filter-label">Sonderfall</label>
                <div class="lam-chip-row">
                    <button class="lam-chip lam-chip-reset" :class="filter.sonderfall === '' ? 'is-active' : ''" @click="filter.sonderfall = ''; laden()">alle</button>
                    <button class="lam-chip" :class="filter.sonderfall === 'normal' ? 'is-active' : ''" @click="filter.sonderfall = 'normal'; laden()">normal</button>
                    <button class="lam-chip" :class="filter.sonderfall === 'storno_mit_weiterberechnung' ? 'is-active' : ''" @click="filter.sonderfall = 'storno_mit_weiterberechnung'; laden()">Storno+Berech.</button>
                    <button class="lam-chip" :class="filter.sonderfall === 'intern' ? 'is-active' : ''" @click="filter.sonderfall = 'intern'; laden()">intern</button>
                    <button class="lam-chip" :class="filter.sonderfall === 'sammelposten' ? 'is-active' : ''" @click="filter.sonderfall = 'sammelposten'; laden()">Sammelposten</button>
                    <button class="lam-chip" :class="filter.sonderfall === 'jahresueberhang' ? 'is-active' : ''" @click="filter.sonderfall = 'jahresueberhang'; laden()">Jahresüberhang</button>
                    <button class="lam-chip" :class="filter.sonderfall === 'negative_marge' ? 'is-active' : ''" @click="filter.sonderfall = 'negative_marge'; laden()">neg. Marge</button>
                </div>
            </div>
            <div class="lam-filter-col-3">
                <label class="lam-filter-label">Jahr</label>
                <input type="number" class="lam-filter-input" placeholder="z.B. 2026"
                       x-model="filter.jahr" @input.debounce.300ms="laden()">
            </div>
            <div class="lam-filter-col-3">
                <label class="lam-filter-label">Monat</label>
                <select class="lam-filter-select" x-model="filter.monat" @change="laden()">
                    <option value="">alle</option>
                    <option value="1">Januar</option>
                    <option value="2">Februar</option>
                    <option value="3">März</option>
                    <option value="4">April</option>
                    <option value="5">Mai</option>
                    <option value="6">Juni</option>
                    <option value="7">Juli</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">Oktober</option>
                    <option value="11">November</option>
                    <option value="12">Dezember</option>
                </select>
            </div>
        </div>
    </section>

    <!-- Quartals-Summen -->
    <section class="lam-card" style="margin-bottom:16px;" x-show="rows.length > 0">
        <h3>Summen (gefiltert)</h3>
        <div class="lam-kpi-grid" style="grid-template-columns:repeat(4, 1fr);">
            <div class="lam-kpi">
                <div class="lam-kpi-label">Einträge</div>
                <div class="lam-kpi-value" x-text="rows.length"></div>
            </div>
            <div class="lam-kpi">
                <div class="lam-kpi-label">Externe Kosten</div>
                <div class="lam-kpi-value" x-text="euro(summe('externe_kosten'))"></div>
            </div>
            <div class="lam-kpi">
                <div class="lam-kpi-label">Weiterverrechnet</div>
                <div class="lam-kpi-value" x-text="euro(summe('weiterverrechnet'))"></div>
            </div>
            <div class="lam-kpi">
                <div class="lam-kpi-label">Marge</div>
                <div class="lam-kpi-value" :class="summe('marge') < 0 ? 'is-rose' : 'is-accent'" x-text="euro(summe('marge'))"></div>
            </div>
        </div>
    </section>

    <section class="lam-table-card">
        <div class="lam-table-wrap">
            <table class="lam-table">
                <thead>
                    <tr>
                        <th>Kunde</th>
                        <th>Domain (Maßnahme)</th>
                        <th class="right">Externe Kosten</th>
                        <th class="right">Weiterverr.</th>
                        <th class="right">Marge</th>
                        <th>Rechnung Thoxan</th>
                        <th>Sonderfall</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="a in rows" :key="a.id">
                        <tr :class="(a.sonderfall && a.sonderfall !== 'normal') ? 'auslage-sonderfall-' + a.sonderfall : ''">
                            <td><strong x-text="a.customer_kuerzel"></strong></td>
                            <td class="url-cell">
                                <template x-if="a.massnahme_id">
                                    <a :href="'/lam/massnahmen/' + encodeURIComponent(a.massnahme_id)" style="color:var(--thoxan-700);" x-text="a.domain_url"></a>
                                </template>
                                <template x-if="!a.massnahme_id"><span x-text="a.domain_url"></span></template>
                            </td>
                            <td class="right" x-text="euro(a.externe_kosten)"></td>
                            <td class="right" x-text="euro(a.weiterverrechnet)"></td>
                            <td class="right" :style="parseFloat(a.marge) < 0 ? 'color:var(--rose-600);font-weight:600;' : ''" x-text="euro(a.marge)"></td>
                            <td>
                                <template x-if="a.thoxan_rechnung_nr">
                                    <span><span x-text="a.thoxan_rechnung_nr"></span> <span class="muted" x-text="'(' + (a.thoxan_rechnung_datum || '') + ')'"></span></span>
                                </template>
                                <template x-if="!a.thoxan_rechnung_nr"><span class="empty">—</span></template>
                            </td>
                            <td>
                                <span x-show="a.sonderfall && a.sonderfall !== 'normal'"
                                      class="lam-badge"
                                      :style="sonderfallStyle(a.sonderfall)"
                                      x-text="a.sonderfall"></span>
                                <span x-show="!a.sonderfall || a.sonderfall === 'normal'" class="muted">—</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Auslagen.</div>
            <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span> Lade …</div>
        </div>
    </section>
</div>

<script>
function lamAuslagen() {
    return {
        laedt: true, rows: [], filter: { sonderfall: '', jahr: '', monat: '', customer_id: '' },
        async laden() {
            this.laedt = true;
            const p = new URLSearchParams();
            if (this.filter.sonderfall) p.set('sonderfall', this.filter.sonderfall);
            if (this.filter.jahr) p.set('jahr', this.filter.jahr);
            if (this.filter.quartal) p.set('quartal', this.filter.quartal);
            try {
                const r = await fetch('/api/v1/lam/auslagen?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.rows = j.success ? j.data : [];
            } finally { this.laedt = false; }
        },
        euro(n) { return n == null ? '—' : parseFloat(n).toLocaleString('de-DE', {style:'currency', currency:'EUR'}); },
        summe(feld) {
            return this.rows.reduce((acc, a) => acc + parseFloat(a[feld] || 0), 0);
        },
        sonderfallStyle(s) {
            const map = {
                'storno_mit_weiterberechnung': 'background:var(--rose-100);color:var(--rose-800);',
                'jahresueberhang': 'background:#fef3c7;color:#92400e;',
                'sammelposten': 'background:#e0e7ff;color:#3730a3;',
                'intern': 'background:var(--slate-200);color:var(--slate-700);',
            };
            return map[s] || 'background:var(--slate-100);color:var(--slate-600);';
        }
    };
}
</script>
