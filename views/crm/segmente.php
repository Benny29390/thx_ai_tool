<?php $activeModul = 'segmente'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header" style="margin-bottom:8px;">
    <div>
        <h1 class="thx-page-title">Segmente</h1>
        <div class="thx-page-subtitle">Gespeicherte Filter-Kombinationen für schnellen Zugriff in der Kontakt-Liste</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="crmSegmente()" x-init="laden()" x-cloak>
    <div class="thx-shell">

        <aside class="thx-shell-side">
            <div class="thx-shell-side-header">
                <span class="thx-shell-side-title">Segmente</span>
            </div>

            <div class="thx-shell-side-search">
                <span class="material-symbols-rounded thx-shell-search-icon">search</span>
                <input type="text" class="thx-shell-search-input" x-model.debounce.300ms="suche" placeholder="Segment suchen …">
            </div>

            <div class="thx-shell-side-content">
                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">visibility</span>Sichtbarkeit</div>
                    <div class="thx-shell-chips">
                        <button type="button" class="thx-shell-chip" :class="filter.sichtbarkeit.includes('privat') ? 'is-active' : ''" @click="toggleSicht('privat')">Privat</button>
                        <button type="button" class="thx-shell-chip" :class="filter.sichtbarkeit.includes('team') ? 'is-active' : ''" @click="toggleSicht('team')">Team</button>
                        <button type="button" class="thx-shell-chip" :class="filter.sichtbarkeit.includes('global') ? 'is-active' : ''" @click="toggleSicht('global')">Global</button>
                    </div>
                </div>

                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">help_outline</span>So legst Du ein Segment an</div>
                    <p style="font-size:0.78rem;color:var(--slate-600);line-height:1.6;margin:0;">
                        Geh in die <a href="/crm/kontakte" style="color:var(--thoxan-600);">Kontakt-Liste</a>, setze die gewünschten Filter (Status, Tags, Listen, etc.) und klicke links auf <strong>„Aktuelle Filter speichern"</strong>.
                    </p>
                </div>
            </div>
        </aside>

        <main class="thx-shell-main">
            <div class="thx-shell-toolbar">
                <div style="font-size:0.85rem;color:var(--slate-600);">
                    <strong x-text="gefilterte.length"></strong> Segment(e)
                </div>
            </div>

            <template x-if="gefilterte.length > 0">
                <div class="thx-shell-table-wrap">
                    <table class="thx-shell-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Beschreibung</th>
                                <th>Sichtbarkeit</th>
                                <th>Filter</th>
                                <th>Erstellt</th>
                                <th style="width:200px;text-align:right;">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="s in gefilterte" :key="s.id">
                                <tr>
                                    <td>
                                        <a :href="'/crm/kontakte#segment=' + s.id" style="color:var(--thoxan-600);font-weight:500;" x-text="s.name"></a>
                                    </td>
                                    <td style="color:var(--slate-500);font-size:0.85rem;" x-text="s.beschreibung || '—'"></td>
                                    <td><span class="lam-chip" :class="sichtbarkeitsKlasse(s.sichtbarkeit)" x-text="sichtbarkeitsLabel(s.sichtbarkeit)"></span></td>
                                    <td>
                                        <button class="thx-shell-btn" @click="zeigeFilter(s)" style="font-size:0.72rem;display:inline-flex;align-items:center;gap:4px;">
                                            <span class="material-symbols-rounded" style="font-size:14px;">visibility</span>Vorschau
                                        </button>
                                    </td>
                                    <td style="font-size:0.78rem;color:var(--slate-500);font-variant-numeric:tabular-nums;" x-text="formatDate(s.erstellt_am)"></td>
                                    <td style="text-align:right;">
                                        <a :href="'/crm/kontakte#segment=' + s.id" class="thx-shell-btn">→ Anwenden</a>
                                        <button class="thx-shell-btn thx-shell-btn-danger" @click="loeschen(s)">Löschen</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <template x-if="gefilterte.length === 0">
                <div class="thx-card" style="padding:30px;text-align:center;color:var(--slate-500);">
                    <p>Noch keine Segmente gespeichert.</p>
                    <p style="font-size:0.8rem;color:var(--slate-400);margin-top:8px;">In der <a href="/crm/kontakte" style="color:var(--thoxan-600);">Kontakt-Liste</a> Filter setzen und über die Sidebar speichern.</p>
                </div>
            </template>
        </main>
    </div>

    <!-- Filter-Vorschau-Dialog -->
    <div x-show="filterDialog.offen" x-cloak class="thx-lightbox" style="background:rgba(15,23,42,0.55);z-index:1200;" @click.self="filterDialog.offen=false">
        <div class="thx-modal" style="max-width:560px;">
            <div style="padding:14px 22px;border-bottom:1px solid var(--slate-200);"><h3 style="margin:0;font-size:1rem;" x-text="'Filter von „' + (filterDialog.segment?.name||'') + '“'"></h3></div>
            <div style="padding:14px 22px;">
                <pre style="margin:0;padding:10px;background:var(--slate-50);border:1px solid var(--slate-200);border-radius:6px;font-size:0.78rem;overflow:auto;max-height:300px;" x-text="filterDialog.text"></pre>
            </div>
            <div style="padding:10px 22px;border-top:1px solid var(--slate-200);display:flex;justify-content:flex-end;gap:6px;">
                <button class="thx-shell-btn" @click="filterDialog.offen=false">Schließen</button>
            </div>
        </div>
    </div>
</div>

<script>
function crmSegmente() {
    return {
        segmente: [], suche: '',
        filter: { sichtbarkeit: [] },
        filterDialog: { offen: false, segment: null, text: '' },

        async laden() {
            const j = await (await fetch('/api/v1/crm/segmente')).json();
            if (j.success) this.segmente = j.data.segmente || [];
        },
        get gefilterte() {
            let arr = this.segmente.slice();
            if (this.suche) {
                const s = this.suche.toLowerCase();
                arr = arr.filter(x => (x.name||'').toLowerCase().includes(s) || (x.beschreibung||'').toLowerCase().includes(s));
            }
            if (this.filter.sichtbarkeit.length) {
                arr = arr.filter(x => this.filter.sichtbarkeit.includes(x.sichtbarkeit));
            }
            return arr;
        },
        toggleSicht(s) {
            const arr = this.filter.sichtbarkeit;
            const i = arr.indexOf(s);
            if (i >= 0) arr.splice(i, 1); else arr.push(s);
        },
        sichtbarkeitsLabel(s) { return ({privat:'Privat',team:'Team',global:'Global'})[s] || s; },
        sichtbarkeitsKlasse(s) {
            if (s === 'global') return 'lam-chip-status-geprueft';
            if (s === 'team') return '';
            return '';
        },
        zeigeFilter(s) {
            let t;
            try { t = JSON.stringify(typeof s.filter_json === 'string' ? JSON.parse(s.filter_json) : s.filter_json, null, 2); }
            catch (e) { t = s.filter_json || '(leer)'; }
            this.filterDialog = { offen: true, segment: s, text: t };
        },
        async loeschen(s) {
            if (!confirm('Segment „' + s.name + '" wirklich löschen?')) return;
            await fetch('/api/v1/crm/segmente/' + s.id, { method:'DELETE', credentials:'same-origin' });
            App.showNotification('Gelöscht', 'success');
            this.laden();
        },
        formatDate(d) { if (!d) return ''; return new Date(d.replace(' ','T')).toLocaleDateString('de-DE', { day:'2-digit', month:'2-digit', year:'2-digit' }); },
    };
}
</script>
