<?php $activeModul = 'linkoptionen'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamKiVorschlaege()" x-init="laden()" x-cloak>

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">KI-Domain-Vorschläge</h1>
        <div class="thx-page-subtitle">Welche Domains aus dem Pool passen thematisch zu welchem Kunden? Basis: Tags + Linkziele + Sichtbarkeitsindex.</div>
    </div>
    <a class="lam-btn lam-btn-secondary" href="/lam/linkoptionen">← zur Linkoptionen-Liste</a>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<section class="lam-filter-card">
    <div class="lam-filter-grid">
        <div class="lam-filter-col-6">
            <label class="lam-filter-label">Kunde *</label>
            <select class="lam-filter-select" x-model="customerId">
                <option value="">— Kunde wählen —</option>
                <template x-for="k in kunden" :key="k.id">
                    <option :value="k.id" x-text="(k.abbreviation || k.name) + ' — ' + k.name"></option>
                </template>
            </select>
        </div>
        <div class="lam-filter-col-3">
            <label class="lam-filter-label">Anzahl Vorschläge</label>
            <input type="number" class="lam-filter-input" min="1" max="50" x-model="anzahl">
        </div>
        <div class="lam-filter-col-3">
            <label class="lam-filter-label">&nbsp;</label>
            <button class="lam-btn lam-btn-primary" @click="vorschlagen()" :disabled="!customerId || laedt">
                <span x-show="!laedt">🤖 Vorschläge holen</span>
                <span x-show="laedt">… KI denkt</span>
            </button>
        </div>
    </div>
</section>

<section class="lam-table-card" x-show="ergebnis.length > 0">
    <div class="lam-table-wrap">
        <table class="lam-table">
            <thead>
                <tr>
                    <th class="right">Score</th>
                    <th>Domain</th>
                    <th>Tags</th>
                    <th class="right">SI</th>
                    <th>KI-Grund</th>
                    <th class="right">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="v in ergebnis" :key="v.id">
                    <tr>
                        <td class="right">
                            <strong :style="'color:' + scoreFarbe(v.score)" x-text="v.score || '—'"></strong>
                        </td>
                        <td class="url-cell">
                            <a :href="'/lam/linkquellen/' + encodeURIComponent(v.id)" style="color:var(--thoxan-700);" x-text="v.url"></a>
                        </td>
                        <td class="muted" style="font-size:var(--d-fs-xs);" x-text="v.tags || '—'"></td>
                        <td class="right" x-text="v.si || '—'"></td>
                        <td class="muted" style="font-size:var(--d-fs-xs);" x-text="v.grund || '—'"></td>
                        <td class="right">
                            <a :href="'/lam/massnahmen?plan_b_zu=&kunde=' + encodeURIComponent(customerId)"
                               class="lam-btn lam-btn-sm" style="display:inline-block;text-decoration:none;"
                               title="Maßnahme aus dieser Domain anlegen">+ Maßnahme</a>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <p class="muted" x-show="quelle" style="margin-top:10px;font-size:var(--d-fs-xs);">Quelle: <span x-text="quelle"></span></p>
</section>

<div class="lam-empty" x-show="!laedt && hatAngefragt && ergebnis.length === 0">
    Keine Vorschläge gefunden. <span x-show="hinweis" x-text="hinweis"></span>
</div>

</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function lamKiVorschlaege() {
    return {
        laedt: false,
        kunden: [],
        customerId: '',
        anzahl: 10,
        ergebnis: [],
        quelle: '',
        hinweis: '',
        hatAngefragt: false,

        async laden() {
            const rk = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
            const jk = await rk.json();
            this.kunden = jk.success ? jk.data : [];
        },

        async vorschlagen() {
            if (!this.customerId) return;
            this.laedt = true;
            this.hatAngefragt = true;
            try {
                const r = await fetch('/api/v1/lam/ki-domain-matching?customer_id=' + encodeURIComponent(this.customerId) + '&anzahl=' + this.anzahl, { credentials: 'same-origin' });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'Fehler.'); return; }
                this.ergebnis = j.data.vorschlaege || [];
                this.quelle = j.data.quelle || '';
                this.hinweis = j.data.hinweis || '';
            } finally { this.laedt = false; }
        },

        scoreFarbe(s) {
            const n = parseInt(s || 0);
            if (n >= 80) return 'var(--emerald-700)';
            if (n >= 60) return 'var(--thoxan-700)';
            if (n >= 40) return 'var(--amber-700)';
            return 'var(--slate-500)';
        },
    };
}
</script>
