<?php $activeModul = 'audit'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamAudit()" x-init="laden()" x-cloak>

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Audit-Log</h1>
        <div class="thx-page-subtitle">Wer hat wann was geändert? Bulk-Aktionen kommen als ein konsolidierter Eintrag.</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<section class="lam-filter-card">
    <div class="lam-filter-grid">
        <div class="lam-filter-col-3">
            <label class="lam-filter-label">Entität</label>
            <select class="lam-filter-select" x-model="filter.entity_typ" @change="laden()">
                <option value="">alle</option>
                <option value="domain">Domain</option>
                <option value="massnahme">Maßnahme</option>
                <option value="anbieter">Anbieter</option>
                <option value="kontakt">Kontakt</option>
                <option value="kondition">Kondition</option>
                <option value="linkoption">Linkoption</option>
                <option value="verlinkung">Verlinkung</option>
            </select>
        </div>
        <div class="lam-filter-col-3">
            <label class="lam-filter-label">Aktion enthält</label>
            <input type="text" class="lam-filter-input" placeholder="z.B. bulk, klassifikation" x-model="filter.aktion" @input.debounce.400ms="laden()">
        </div>
        <div class="lam-filter-col-3">
            <label class="lam-filter-label">Ab Datum</label>
            <input type="date" class="lam-filter-input" x-model="filter.ab_datum" @change="laden()">
        </div>
        <div class="lam-filter-col-3">
            <label class="lam-filter-label">&nbsp;</label>
            <div class="lam-chip-row">
                <button class="lam-chip" :class="filter.nur_bulk ? 'is-active' : ''" @click="filter.nur_bulk = !filter.nur_bulk; laden()">nur Bulk</button>
            </div>
        </div>
    </div>
</section>

<section class="lam-table-card">
    <div class="lam-table-wrap">
        <table class="lam-table">
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>User</th>
                    <th>Aktion</th>
                    <th>Entität</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="e in rows" :key="e.id">
                    <tr>
                        <td x-text="e.zeitpunkt"></td>
                        <td x-text="e.user_name || 'System'"></td>
                        <td>
                            <code x-text="e.aktion"></code>
                            <span x-show="e.ist_bulk == 1" class="lam-badge" style="background:var(--amber-100);color:#92400e;font-size:var(--d-fs-xs);margin-left:4px;" x-text="(e.anzahl_betroffen || '?') + '×'"></span>
                        </td>
                        <td>
                            <span x-text="e.entity_typ"></span>
                            <template x-if="e.entity_id && entityLink(e)">
                                <a :href="entityLink(e)" style="color:var(--thoxan-700);margin-left:6px;font-size:var(--d-fs-xs);" x-text="'→ öffnen'"></a>
                            </template>
                        </td>
                        <td class="muted" style="font-size:var(--d-fs-xs);" x-text="formatPayload(e.payload)"></td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Audit-Einträge.</div>
        <div class="lam-loading" x-show="laedt"><span class="lam-spinner"></span> Lade …</div>
    </div>
</section>

</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function lamAudit() {
    return {
        laedt: false, rows: [],
        filter: { entity_typ: '', aktion: '', ab_datum: '', nur_bulk: false },

        async laden() {
            this.laedt = true;
            try {
                const p = new URLSearchParams();
                Object.entries(this.filter).forEach(([k, v]) => { if (v) p.set(k, v === true ? '1' : v); });
                const r = await fetch('/api/v1/lam/audit-log?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.rows = j.success ? j.data : [];
            } finally { this.laedt = false; }
        },

        entityLink(e) {
            if (!e.entity_id) return null;
            const map = {
                'domain': '/lam/linkquellen/',
                'massnahme': '/lam/massnahmen/',
                'anbieter': '/lam/anbieter/',
                'linkoption': '/lam/linkoptionen/',
            };
            return map[e.entity_typ] ? map[e.entity_typ] + encodeURIComponent(e.entity_id) : null;
        },

        formatPayload(payload) {
            if (!payload) return '';
            try {
                const obj = typeof payload === 'string' ? JSON.parse(payload) : payload;
                return Object.entries(obj).map(([k, v]) => k + '=' + (typeof v === 'object' ? JSON.stringify(v) : String(v))).join(' · ');
            } catch (e) { return String(payload).slice(0, 200); }
        },
    };
}
</script>
