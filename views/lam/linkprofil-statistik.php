<?php $activeModul = 'linkprofil'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamStatistik()" x-init="laden()" x-cloak>

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Linkprofil-Statistik</h1>
        <div class="thx-page-subtitle">Top-Domains, Linkart-Verteilung und Follow-Anteil je Kunde.</div>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="lam-btn lam-btn-secondary" @click="druckenAlsPdf()" x-show="customerId && stats">🖨️ Als PDF drucken</button>
        <a class="lam-btn lam-btn-secondary" href="/lam/linkprofil">← zurück zum Linkprofil</a>
    </div>
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

<div x-show="stats && customerId">
    <!-- KPI + Donut: Follow-Anteil -->
    <section class="lam-card" style="margin-top:16px;display:grid;grid-template-columns:1fr 240px;gap:20px;align-items:center;">
        <div>
            <h3>Follow-Anteil</h3>
            <div class="lam-kpi-grid" style="grid-template-columns:repeat(4, 1fr);">
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Gesamt</div>
                    <div class="lam-kpi-value" x-text="zahl(stats?.follow_anteil?.gesamt || 0)"></div>
                </div>
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Follow</div>
                    <div class="lam-kpi-value is-accent" x-text="zahl(stats?.follow_anteil?.follow || 0) + (anteilProzent(stats?.follow_anteil?.follow, stats?.follow_anteil?.gesamt))"></div>
                </div>
                <div class="lam-kpi">
                    <div class="lam-kpi-label">Nofollow</div>
                    <div class="lam-kpi-value" x-text="zahl(stats?.follow_anteil?.nofollow || 0) + (anteilProzent(stats?.follow_anteil?.nofollow, stats?.follow_anteil?.gesamt))"></div>
                </div>
                <div class="lam-kpi">
                    <div class="lam-kpi-label">unbekannt</div>
                    <div class="lam-kpi-value" x-text="zahl(stats?.follow_anteil?.unbekannt || 0) + (anteilProzent(stats?.follow_anteil?.unbekannt, stats?.follow_anteil?.gesamt))"></div>
                </div>
            </div>
        </div>
        <!-- Donut-Chart (reines SVG, keine Lib) -->
        <div x-html="donutSvg(stats?.follow_anteil)" style="display:flex;justify-content:center;"></div>
    </section>

    <div class="lam-grid-2" style="margin-top:16px;">
        <!-- Top-Domains -->
        <section class="lam-card">
            <h3>Top-Domains (Verlinkungen)</h3>
            <div class="lam-table-wrap" style="max-height:400px;overflow-y:auto;">
                <table class="lam-table" style="font-size:var(--d-fs-sm);">
                    <thead><tr><th>Domain</th><th class="right">Anzahl</th><th class="right">davon follow</th></tr></thead>
                    <tbody>
                        <template x-for="d in (stats?.top_domains || [])" :key="d.domain">
                            <tr>
                                <td x-text="d.domain"></td>
                                <td class="right" x-text="d.anzahl"></td>
                                <td class="right muted" x-text="d.follow_anzahl + ' / ' + d.anzahl"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Linkart-Verteilung -->
        <section class="lam-card">
            <h3>Linkart-Verteilung</h3>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <template x-for="la in (stats?.linkart_verteilung || [])" :key="la.linkart">
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:var(--d-fs-sm);">
                            <span x-text="la.linkart"></span>
                            <span class="muted" x-text="la.anzahl + ' (' + anteilZahl(la.anzahl, stats?.follow_anteil?.gesamt) + ')'"></span>
                        </div>
                        <div style="height:6px;background:var(--slate-100);border-radius:3px;overflow:hidden;">
                            <div style="height:100%;background:var(--thoxan-500);" :style="'width:' + (anteilZahl(la.anzahl, stats?.follow_anteil?.gesamt) || '0%')"></div>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </div>

    <!-- Empfehlungs-Verteilung -->
    <section class="lam-card" style="margin-top:16px;">
        <h3>Empfehlungs-Status</h3>
        <div class="lam-table-wrap">
            <table class="lam-table" style="font-size:var(--d-fs-sm);">
                <thead><tr><th>Empfehlung</th><th class="right">Anzahl</th><th class="right">Anteil</th></tr></thead>
                <tbody>
                    <template x-for="e in (stats?.empfehlungs_verteilung || [])" :key="e.empfehlung">
                        <tr>
                            <td x-text="e.empfehlung"></td>
                            <td class="right" x-text="e.anzahl"></td>
                            <td class="right muted" x-text="anteilZahl(e.anzahl, stats?.follow_anteil?.gesamt)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="lam-empty" x-show="!customerId" style="text-align:center;padding:40px;">
    Bitte einen Kunden auswählen.
</div>

</div>

<style>
[x-cloak]{display:none!important}
@media print {
    /* Sidebar, Topbar, Tabs, Buttons ausblenden */
    .thx-sidebar, .thx-topbar, nav.thx-tabs, .lam-filter-card,
    .thx-page-header .thx-page-actions, .lam-btn, button {
        display: none !important;
    }
    body, .thx-main, .thx-content { background: #fff !important; padding: 0 !important; margin: 0 !important; }
    .lam-card { border: 1px solid #ddd !important; box-shadow: none !important; page-break-inside: avoid; }
    .thx-page-title { font-size: 22pt !important; }
    .thx-page-subtitle { font-size: 11pt !important; color: #555 !important; }
    table { font-size: 10pt !important; }
    h3 { font-size: 14pt !important; margin-top: 12pt !important; }
    @page { margin: 1.5cm; size: A4; }
}
</style>

<script>
function lamStatistik() {
    return {
        laedt: false,
        customerId: '',
        stats: null,
        kunden: [],

        async laden() {
            if (this.kunden.length === 0) {
                const rk = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
                const jk = await rk.json();
                this.kunden = jk.success ? jk.data : [];
                const url = new URL(window.location.href);
                const c = url.searchParams.get('customer_id');
                if (c) this.customerId = c;
            }
            if (!this.customerId) { this.stats = null; return; }
            this.laedt = true;
            try {
                const r = await fetch('/api/v1/lam/linkprofil-statistik?customer_id=' + encodeURIComponent(this.customerId), { credentials: 'same-origin' });
                const j = await r.json();
                this.stats = j.success ? j.data : null;
            } finally { this.laedt = false; }
        },

        zahl(n) { return new Intl.NumberFormat('de-DE').format(parseInt(n || 0)); },
        anteilProzent(n, total) {
            n = parseInt(n || 0); total = parseInt(total || 0);
            if (!total) return '';
            return ' (' + (Math.round(n / total * 100)) + '%)';
        },
        anteilZahl(n, total) {
            n = parseInt(n || 0); total = parseInt(total || 0);
            if (!total) return '0%';
            return Math.round(n / total * 100) + '%';
        },

        donutSvg(fa) {
            const total = parseInt(fa?.gesamt || 0);
            if (!total) return '';
            const follow = parseInt(fa.follow || 0);
            const nofollow = parseInt(fa.nofollow || 0);
            const unbekannt = parseInt(fa.unbekannt || 0);
            const r = 80, cx = 100, cy = 100, strokeW = 24;
            const circ = 2 * Math.PI * r;
            const segments = [
                { label: 'follow',    n: follow,    color: '#10b981' },
                { label: 'nofollow',  n: nofollow,  color: '#64748b' },
                { label: 'unbekannt', n: unbekannt, color: '#cbd5e1' },
            ];
            let offset = 0;
            let html = '<svg viewBox="0 0 200 200" style="width:200px;height:200px;">';
            for (const s of segments) {
                if (s.n === 0) continue;
                const len = (s.n / total) * circ;
                html += `<circle cx="${cx}" cy="${cy}" r="${r}" fill="transparent" stroke="${s.color}" stroke-width="${strokeW}" `
                     + `stroke-dasharray="${len.toFixed(2)} ${(circ - len).toFixed(2)}" `
                     + `stroke-dashoffset="${(-offset).toFixed(2)}" transform="rotate(-90 ${cx} ${cy})"/>`;
                offset += len;
            }
            const pct = Math.round(follow / total * 100);
            html += `<text x="${cx}" y="${cy - 4}" text-anchor="middle" style="font-size:28px;font-weight:600;fill:#10b981;">${pct}%</text>`;
            html += `<text x="${cx}" y="${cy + 16}" text-anchor="middle" style="font-size:11px;fill:#64748b;">follow</text>`;
            html += '</svg>';
            return html;
        },

        druckenAlsPdf() {
            // Print-Dialog öffnet sich; User wählt „in PDF speichern"
            window.print();
        },
    };
}
</script>
