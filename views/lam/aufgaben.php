<?php $activeModul = 'aufgaben'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamAufgaben()" x-init="laden()" x-cloak>

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Aufgaben</h1>
        <div class="thx-page-subtitle">Update-Aufgaben („veraltet markieren") und manuelle Erinnerungen, die nicht in den Maßnahmen-Workflow gehören.</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<section class="lam-filter-card">
    <div class="lam-filter-grid">
        <div class="lam-filter-col-6">
            <label class="lam-filter-label">Status</label>
            <div class="lam-chip-row">
                <button class="lam-chip lam-chip-reset" :class="filter.status === '' ? 'is-active' : ''" @click="filter.status = ''; laden()">alle</button>
                <button class="lam-chip" :class="filter.status === 'offen' ? 'is-active' : ''" @click="filter.status = 'offen'; laden()">offen</button>
                <button class="lam-chip" :class="filter.status === 'in_arbeit' ? 'is-active' : ''" @click="filter.status = 'in_arbeit'; laden()">in Arbeit</button>
                <button class="lam-chip" :class="filter.status === 'erledigt' ? 'is-active' : ''" @click="filter.status = 'erledigt'; laden()">erledigt</button>
            </div>
        </div>
        <div class="lam-filter-col-6">
            <label class="lam-filter-label">Typ</label>
            <div class="lam-chip-row">
                <button class="lam-chip lam-chip-reset" :class="filter.typ === '' ? 'is-active' : ''" @click="filter.typ = ''; laden()">alle</button>
                <button class="lam-chip" :class="filter.typ === 'update_pruefung' ? 'is-active' : ''" @click="filter.typ = 'update_pruefung'; laden()">Update-Prüfung</button>
                <button class="lam-chip" :class="filter.typ === 'rueckfrage' ? 'is-active' : ''" @click="filter.typ = 'rueckfrage'; laden()">Rückfrage</button>
                <button class="lam-chip" :class="filter.typ === 'manuell' ? 'is-active' : ''" @click="filter.typ = 'manuell'; laden()">manuell</button>
            </div>
        </div>
    </div>
</section>

<section class="lam-table-card">
    <div class="lam-table-wrap">
        <table class="lam-table">
            <thead>
                <tr>
                    <th>Typ</th>
                    <th>Titel</th>
                    <th>Bezug</th>
                    <th>Fällig</th>
                    <th>Status</th>
                    <th class="right" style="width:160px;">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="a in rows" :key="a.id">
                    <tr>
                        <td><span class="lam-badge" style="background:var(--slate-100);color:var(--slate-700);" x-text="a.typ"></span></td>
                        <td>
                            <strong x-text="a.titel"></strong>
                            <div x-show="a.beschreibung" class="muted" style="font-size:var(--d-fs-xs);margin-top:2px;" x-text="a.beschreibung"></div>
                        </td>
                        <td class="muted" style="font-size:var(--d-fs-xs);">
                            <template x-if="a.bezug_typ === 'domain'">
                                <a :href="'/lam/linkquellen/' + encodeURIComponent(a.bezug_id)" style="color:var(--thoxan-700);" x-text="'Domain'"></a>
                            </template>
                            <template x-if="a.bezug_typ === 'massnahme'">
                                <a :href="'/lam/massnahmen/' + encodeURIComponent(a.bezug_id)" style="color:var(--thoxan-700);" x-text="'Maßnahme'"></a>
                            </template>
                            <template x-if="a.bezug_typ === 'anbieter'">
                                <a :href="'/lam/anbieter/' + encodeURIComponent(a.bezug_id)" style="color:var(--thoxan-700);" x-text="'Anbieter'"></a>
                            </template>
                            <template x-if="!['domain','massnahme','anbieter'].includes(a.bezug_typ)">
                                <span x-text="a.bezug_typ + ': ' + a.bezug_id"></span>
                            </template>
                        </td>
                        <td x-text="a.faellig_am || '—'"></td>
                        <td><span class="lam-badge" :style="statusStyle(a.status)" x-text="a.status"></span></td>
                        <td class="right">
                            <button x-show="a.status !== 'erledigt'" class="lam-btn lam-btn-sm" @click="setzeStatus(a, 'erledigt')">erledigen</button>
                            <button x-show="a.status === 'offen'" class="lam-btn lam-btn-sm" @click="setzeStatus(a, 'in_arbeit')">übernehmen</button>
                            <button class="lam-btn lam-btn-sm lam-btn-danger" @click="loeschen(a)">löschen</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Aufgaben.</div>
        <div class="lam-loading" x-show="laedt"><span class="lam-spinner"></span> Lade …</div>
    </div>
</section>

</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function lamAufgaben() {
    return {
        laedt: false,
        rows: [],
        filter: { status: 'offen', typ: '' },

        async laden() {
            this.laedt = true;
            try {
                const p = new URLSearchParams();
                if (this.filter.status) p.set('status', this.filter.status);
                if (this.filter.typ) p.set('typ', this.filter.typ);
                const r = await fetch('/api/v1/lam/aufgaben?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.rows = j.success ? j.data : [];
            } finally { this.laedt = false; }
        },

        statusStyle(s) {
            const map = {
                'offen': 'background:var(--amber-100);color:#92400e;',
                'in_arbeit': 'background:var(--thoxan-100);color:var(--thoxan-700);',
                'erledigt': 'background:var(--emerald-100);color:var(--emerald-800);',
            };
            return map[s] || 'background:var(--slate-100);color:var(--slate-700);';
        },

        async setzeStatus(a, status) {
            const r = await fetch('/api/v1/lam/aufgabe-aktualisieren', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id: a.id, status }),
            });
            const j = await r.json();
            if (!j.success) { alert(j.error || 'Fehler.'); return; }
            await this.laden();
        },

        async loeschen(a) {
            if (!confirm('Aufgabe „' + a.titel + '" wirklich löschen?')) return;
            const r = await fetch('/api/v1/lam/aufgabe-aktualisieren', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id: a.id, status: 'geloescht' }),
            });
            // Lösch-Endpoint nutzen wäre sauberer, aber Soft-Delete via Status reicht
            await this.laden();
        },
    };
}
</script>
