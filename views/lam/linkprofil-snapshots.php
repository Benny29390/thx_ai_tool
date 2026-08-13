<?php $activeModul = 'linkprofil'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamSnapshots()" x-init="laden()" x-cloak>

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Linkprofil-Snapshots</h1>
        <div class="thx-page-subtitle">Bestand zu einem Zeitpunkt einfrieren — zeigt was seit dem letzten Import dazugekommen oder verschwunden ist.</div>
    </div>
    <a class="lam-btn lam-btn-secondary" href="/lam/linkprofil">← zurück zum Linkprofil</a>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<section class="lam-filter-card">
    <div class="lam-filter-head"><h2>Kunde</h2></div>
    <div class="lam-filter-grid">
        <div class="lam-filter-col-6">
            <select class="lam-filter-select" x-model="customerId" @change="laden()">
                <option value="">— Kunde wählen —</option>
                <template x-for="k in kunden" :key="k.id">
                    <option :value="k.id" x-text="(k.kuerzel || k.abbreviation || k.name) + ' — ' + k.name"></option>
                </template>
            </select>
        </div>
    </div>
</section>

<section class="lam-table-card" x-show="customerId">
    <div class="lam-table-wrap">
        <table class="lam-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th class="right">Verlinkungen</th>
                    <th class="right">+ neu</th>
                    <th class="right">− verschwunden</th>
                    <th>Import</th>
                    <th class="right" style="width:140px;">Diff</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="s in snapshots" :key="s.id">
                    <tr>
                        <td><strong x-text="s.snapshot_datum"></strong> <span class="muted" style="font-size:var(--d-fs-xs);" x-text="s.erstellt_am?.substring(11,16)"></span></td>
                        <td class="right" x-text="s.eintraege_count"></td>
                        <td class="right" :style="parseInt(s.diff_neu_count) > 0 ? 'color:var(--emerald-700);font-weight:600;' : ''" x-text="s.diff_neu_count"></td>
                        <td class="right" :style="parseInt(s.diff_weggefallen_count) > 0 ? 'color:var(--rose-600);font-weight:600;' : ''" x-text="s.diff_weggefallen_count"></td>
                        <td class="muted" x-text="s.import_id ? ('Import #' + s.import_id) : 'manuell'"></td>
                        <td class="right">
                            <button class="lam-btn lam-btn-sm" @click="oeffneDiff(s.id)" :disabled="!s.vorgaenger_id">anzeigen</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div class="lam-empty" x-show="!laedt && customerId && snapshots.length === 0">Keine Snapshots für diesen Kunden.</div>
        <div class="lam-loading" x-show="laedt"><span class="lam-spinner"></span> Lade …</div>
    </div>
</section>

<!-- Diff Modal -->
<div class="thx-modal-backdrop" x-show="diff.offen" @click.self="diff.offen = false" x-cloak>
    <div class="thx-modal" style="max-width:1000px;">
        <div class="thx-modal-header">
            <h2>Diff zum vorigen Snapshot</h2>
            <button class="thx-icon-btn" @click="diff.offen = false">✕</button>
        </div>
        <div class="thx-modal-body">
            <div class="lam-grid-2">
                <div>
                    <h3 style="color:var(--emerald-700);">+ neu (<span x-text="diff.neu.length"></span>)</h3>
                    <div style="max-height:480px;overflow-y:auto;">
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Quelle</th><th>Ziel</th><th>Linkart</th></tr></thead>
                            <tbody>
                                <template x-for="v in diff.neu" :key="v.quell_url + '|' + v.ziel_url">
                                    <tr>
                                        <td class="url-cell"><a :href="v.quell_url" target="_blank" rel="noopener" style="color:var(--thoxan-700);" x-text="v.quell_url"></a></td>
                                        <td class="url-cell"><span class="muted" x-text="v.ziel_url"></span></td>
                                        <td x-text="v.linkart || '—'"></td>
                                    </tr>
                                </template>
                                <tr x-show="diff.neu.length === 0"><td colspan="3" class="empty" style="text-align:center;padding:14px;">keine neuen Verlinkungen</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <h3 style="color:var(--rose-600);">− verschwunden (<span x-text="diff.weggefallen.length"></span>)</h3>
                    <div style="max-height:480px;overflow-y:auto;">
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Quelle</th><th>Ziel</th><th>Linkart</th></tr></thead>
                            <tbody>
                                <template x-for="v in diff.weggefallen" :key="v.quell_url + '|' + v.ziel_url">
                                    <tr>
                                        <td class="url-cell"><span class="muted" x-text="v.quell_url"></span></td>
                                        <td class="url-cell"><span class="muted" x-text="v.ziel_url"></span></td>
                                        <td x-text="v.linkart || '—'"></td>
                                    </tr>
                                </template>
                                <tr x-show="diff.weggefallen.length === 0"><td colspan="3" class="empty" style="text-align:center;padding:14px;">nichts verschwunden</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function lamSnapshots() {
    return {
        laedt: false,
        customerId: '',
        snapshots: [],
        kunden: [],
        diff: { offen: false, neu: [], weggefallen: [] },

        async laden() {
            // Kunden einmalig laden
            if (this.kunden.length === 0) {
                const rk = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
                const jk = await rk.json();
                this.kunden = jk.success ? jk.data : [];
                // URL-Param ?customer_id=X
                const url = new URL(window.location.href);
                const c = url.searchParams.get('customer_id');
                if (c) this.customerId = c;
            }
            if (!this.customerId) return;
            this.laedt = true;
            try {
                const r = await fetch('/api/v1/lam/linkprofil-snapshots?customer_id=' + encodeURIComponent(this.customerId), { credentials: 'same-origin' });
                const j = await r.json();
                this.snapshots = j.success ? j.data : [];
            } finally { this.laedt = false; }
        },

        async oeffneDiff(id) {
            const r = await fetch('/api/v1/lam/linkprofil-snapshot-diff?id=' + encodeURIComponent(id), { credentials: 'same-origin' });
            const j = await r.json();
            if (!j.success) { alert(j.error || 'Diff konnte nicht geladen werden.'); return; }
            this.diff.neu = j.data.neu || [];
            this.diff.weggefallen = j.data.weggefallen || [];
            this.diff.offen = true;
        },
    };
}
</script>
