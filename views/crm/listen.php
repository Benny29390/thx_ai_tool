<?php $activeModul = 'listen'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header" style="margin-bottom:8px;">
    <div>
        <h1 class="thx-page-title">Listen</h1>
        <div class="thx-page-subtitle">Marketing-Listen mit Brevo-Sync · Mitglieder-Verwaltung pro Kontakt</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="crmListen()" x-init="laden()" x-cloak>
    <div class="thx-shell">

        <aside class="thx-shell-side">
            <div class="thx-shell-side-header">
                <span class="thx-shell-side-title">Listen</span>
                <button @click="oeffneNeu()" class="thx-icon-btn" title="Neue Liste">
                    <span class="material-symbols-rounded">add</span>
                </button>
            </div>

            <div class="thx-shell-side-search">
                <span class="material-symbols-rounded thx-shell-search-icon">search</span>
                <input type="text" class="thx-shell-search-input" x-model.debounce.300ms="suche" placeholder="Liste suchen …">
            </div>

            <div class="thx-shell-side-content">
                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">sync</span>Brevo-Sync</div>
                    <div class="thx-shell-chips">
                        <button type="button" class="thx-shell-chip" :class="filter.mitBrevo ? 'is-active' : ''" @click="filter.mitBrevo = !filter.mitBrevo; filter.ohneBrevo = false">verknüpft</button>
                        <button type="button" class="thx-shell-chip" :class="filter.ohneBrevo ? 'is-active' : ''" @click="filter.ohneBrevo = !filter.ohneBrevo; filter.mitBrevo = false">nicht verknüpft</button>
                    </div>
                </div>

                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">inventory_2</span>Archiv</div>
                    <div class="thx-shell-chips">
                        <button type="button" class="thx-shell-chip" :class="!zeigeArchiv ? 'is-active' : ''" @click="zeigeArchiv = false; laden()">aktive</button>
                        <button type="button" class="thx-shell-chip" :class="zeigeArchiv ? 'is-active' : ''" @click="zeigeArchiv = true; laden()">inkl. archiviert</button>
                    </div>
                </div>

                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">sort</span>Sortierung</div>
                    <div class="thx-shell-chips">
                        <button type="button" class="thx-shell-chip" :class="sort === 'name' ? 'is-active' : ''" @click="sort = 'name'">Alphabetisch</button>
                        <button type="button" class="thx-shell-chip" :class="sort === 'mitglieder' ? 'is-active' : ''" @click="sort = 'mitglieder'">Mitglieder</button>
                    </div>
                </div>

                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">analytics</span>Statistik</div>
                    <div style="font-size:0.78rem;color:var(--slate-600);line-height:1.6;">
                        <div><strong x-text="listen.length"></strong> Listen gesamt</div>
                        <div><strong x-text="listenMitBrevo"></strong> mit Brevo-Sync</div>
                        <div><strong x-text="mitgliederGesamt.toLocaleString('de-DE')"></strong> Mitgliedschaften</div>
                    </div>
                </div>
            </div>
        </aside>

        <main class="thx-shell-main">
            <div class="thx-shell-toolbar">
                <div style="font-size:0.85rem;color:var(--slate-600);">
                    <strong x-text="gefilterte.length"></strong> Liste(n)
                </div>
            </div>

            <template x-if="gefilterte.length > 0">
                <div class="thx-shell-table-wrap">
                    <table class="thx-shell-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Beschreibung</th>
                                <th class="center">Brevo-ID</th>
                                <th class="center">Mitglieder</th>
                                <th class="center">Status</th>
                                <th style="width:200px;text-align:right;">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="l in gefilterte" :key="l.id">
                                <tr :style="l.archiviert ? 'opacity:0.55;' : ''">
                                    <td style="font-weight:500;color:var(--slate-900);" x-text="l.name"></td>
                                    <td style="color:var(--slate-500);font-size:0.85rem;" x-text="l.beschreibung || '—'"></td>
                                    <td class="center" style="font-variant-numeric:tabular-nums;color:var(--slate-500);" x-text="l.brevo_list_id || '—'"></td>
                                    <td class="center" style="font-variant-numeric:tabular-nums;">
                                        <a x-show="l.anzahl_aktive > 0" :href="'/crm/kontakte?listen_ids[]=' + l.id" style="color:var(--thoxan-600);font-weight:500;" x-text="l.anzahl_aktive"></a>
                                        <span x-show="!l.anzahl_aktive" style="color:var(--slate-300);">0</span>
                                    </td>
                                    <td class="center">
                                        <span x-show="l.archiviert" class="lam-chip lam-chip-status-geloescht">Archiviert</span>
                                        <span x-show="!l.archiviert" class="lam-chip lam-chip-status-geprueft">Aktiv</span>
                                    </td>
                                    <td style="text-align:right;">
                                        <button class="thx-shell-btn" @click="oeffneEdit(l)">Bearbeiten</button>
                                        <button class="thx-shell-btn" @click="toggleArchiv(l)" x-text="l.archiviert ? '↻ Aktivieren' : 'Archivieren'"></button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <template x-if="gefilterte.length === 0">
                <div class="thx-card" style="padding:30px;text-align:center;color:var(--slate-500);">Keine Listen gefunden.</div>
            </template>
        </main>
    </div>

    <!-- Edit/Neu-Dialog -->
    <div x-show="dialogOffen" x-cloak class="thx-lightbox" style="background:rgba(15,23,42,0.55);z-index:1200;" @click.self="dialogOffen=false">
        <div class="thx-modal" style="max-width:480px;">
            <div style="padding:16px 22px;border-bottom:1px solid var(--slate-200);"><h3 style="margin:0;font-size:1rem;" x-text="formData.id ? 'Liste bearbeiten' : 'Neue Liste'"></h3></div>
            <div style="padding:16px 22px;display:flex;flex-direction:column;gap:10px;">
                <div>
                    <label style="font-size:0.78rem;color:var(--slate-500);">Name</label>
                    <input type="text" x-model="formData.name" x-init="$nextTick(() => $el.focus())" class="thx-shell-search-input" style="padding-left:10px;">
                </div>
                <div>
                    <label style="font-size:0.78rem;color:var(--slate-500);">Brevo-Listen-ID (optional)</label>
                    <input type="number" x-model="formData.brevo_list_id" class="thx-shell-search-input" style="padding-left:10px;">
                    <div style="font-size:0.72rem;color:var(--slate-400);margin-top:3px;">Verknüpft die Liste mit einer bestehenden Brevo-Liste für automatischen Sync.</div>
                </div>
                <div>
                    <label style="font-size:0.78rem;color:var(--slate-500);">Beschreibung</label>
                    <input type="text" x-model="formData.beschreibung" class="thx-shell-search-input" style="padding-left:10px;">
                </div>
            </div>
            <div style="padding:12px 22px;border-top:1px solid var(--slate-200);display:flex;justify-content:flex-end;gap:8px;">
                <button class="thx-shell-btn" @click="dialogOffen=false">Abbrechen</button>
                <button class="thx-shell-btn thx-shell-btn-primary" @click="speichern()" :disabled="!formData.name.trim()">Speichern</button>
            </div>
        </div>
    </div>
</div>

<script>
function crmListen() {
    return {
        listen: [], suche: '',
        sort: 'name', zeigeArchiv: false,
        filter: { mitBrevo: false, ohneBrevo: false },
        dialogOffen: false,
        formData: { id: null, name: '', brevo_list_id: '', beschreibung: '' },

        async laden() {
            const p = this.zeigeArchiv ? '?inkl_archiviert=1' : '';
            const j = await (await fetch('/api/v1/crm/listen' + p)).json();
            if (j.success) this.listen = j.data.listen || [];
        },
        get gefilterte() {
            let arr = this.listen.slice();
            if (this.suche) {
                const s = this.suche.toLowerCase();
                arr = arr.filter(x => (x.name||'').toLowerCase().includes(s) || (x.beschreibung||'').toLowerCase().includes(s));
            }
            if (this.filter.mitBrevo) arr = arr.filter(x => x.brevo_list_id);
            if (this.filter.ohneBrevo) arr = arr.filter(x => !x.brevo_list_id);
            if (this.sort === 'mitglieder') arr.sort((a,b) => (b.anzahl_aktive||0) - (a.anzahl_aktive||0));
            else arr.sort((a,b) => (a.name||'').localeCompare(b.name||''));
            return arr;
        },
        get listenMitBrevo() { return this.listen.filter(l => l.brevo_list_id).length; },
        get mitgliederGesamt() { return this.listen.reduce((s,l) => s + (l.anzahl_aktive||0), 0); },

        oeffneNeu() { this.formData = { id: null, name: '', brevo_list_id: '', beschreibung: '' }; this.dialogOffen = true; },
        oeffneEdit(l) { this.formData = { id: l.id, name: l.name, brevo_list_id: l.brevo_list_id || '', beschreibung: l.beschreibung || '' }; this.dialogOffen = true; },
        async speichern() {
            const url = this.formData.id ? ('/api/v1/crm/listen/' + this.formData.id) : '/api/v1/crm/listen';
            const method = this.formData.id ? 'PUT' : 'POST';
            const r = await fetch(url, {
                method, credentials: 'same-origin', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    name: this.formData.name,
                    brevo_list_id: this.formData.brevo_list_id ? Number(this.formData.brevo_list_id) : null,
                    beschreibung: this.formData.beschreibung,
                })
            });
            const j = await r.json();
            if (j.success) { App.showNotification('Gespeichert', 'success'); this.dialogOffen = false; this.laden(); }
            else App.showNotification(j.message || 'Fehler', 'error');
        },
        async toggleArchiv(l) {
            await fetch('/api/v1/crm/listen/' + l.id, {
                method: 'PUT', credentials: 'same-origin', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ archiviert: l.archiviert ? 0 : 1 })
            });
            this.laden();
        },
    };
}
</script>
