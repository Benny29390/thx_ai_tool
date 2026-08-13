<?php $activeModul = 'tags'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header" style="margin-bottom:8px;">
    <div>
        <h1 class="thx-page-title">Tags</h1>
        <div class="thx-page-subtitle">Kontrolliertes Vokabular für Kontakt-Tags · Anlegen, Umbenennen, Färben</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="crmTags()" x-init="laden()" x-cloak>
    <div class="thx-shell">

        <aside class="thx-shell-side">
            <div class="thx-shell-side-header">
                <span class="thx-shell-side-title">Tags</span>
                <button @click="oeffneNeu()" class="thx-icon-btn" title="Neuer Tag">
                    <span class="material-symbols-rounded">add</span>
                </button>
            </div>

            <div class="thx-shell-side-search">
                <span class="material-symbols-rounded thx-shell-search-icon">search</span>
                <input type="text" class="thx-shell-search-input" x-model="suche" @input.debounce.300ms="laden()" placeholder="Tag suchen …">
            </div>

            <div class="thx-shell-side-content">
                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">sort</span>Sortierung</div>
                    <div class="thx-shell-chips">
                        <button type="button" class="thx-shell-chip" :class="sort === 'anzahl' ? 'is-active' : ''" @click="sort = 'anzahl'">Häufigkeit</button>
                        <button type="button" class="thx-shell-chip" :class="sort === 'name' ? 'is-active' : ''" @click="sort = 'name'">Alphabetisch</button>
                    </div>
                </div>

                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">tune</span>Filter</div>
                    <div class="thx-shell-chips">
                        <button type="button" class="thx-shell-chip" :class="nurUngenutzt ? 'is-active' : ''" @click="nurUngenutzt = !nurUngenutzt">nur ungenutzt</button>
                        <button type="button" class="thx-shell-chip" :class="nurMitFarbe ? 'is-active' : ''" @click="nurMitFarbe = !nurMitFarbe">mit Farbe</button>
                    </div>
                </div>

                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">analytics</span>Statistik</div>
                    <div style="font-size:0.78rem;color:var(--slate-600);line-height:1.6;">
                        <div><strong x-text="tags.length"></strong> Tags gesamt</div>
                        <div><strong x-text="tagsGenutzt"></strong> mit Vergaben</div>
                        <div><strong x-text="tagsOhneFarbe"></strong> ohne Farbe</div>
                    </div>
                </div>
            </div>
        </aside>

        <main class="thx-shell-main">
            <div class="thx-shell-toolbar">
                <div style="font-size:0.85rem;color:var(--slate-600);">
                    <strong x-text="gefilterte.length"></strong> Tag(s) angezeigt
                </div>
            </div>

            <template x-if="gefilterte.length > 0">
                <div class="thx-shell-table-wrap">
                    <table class="thx-shell-table">
                        <thead>
                            <tr>
                                <th>Tag</th>
                                <th>Beschreibung</th>
                                <th class="center">Anzahl Kontakte</th>
                                <th style="width:160px;text-align:right;">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="t in gefilterte" :key="t.id">
                                <tr>
                                    <td>
                                        <span class="lam-chip" :style="t.farbe ? ('background:' + t.farbe + '20;color:' + t.farbe + ';border-color:' + t.farbe) : ''" x-text="t.name"></span>
                                    </td>
                                    <td style="color:var(--slate-500);font-size:0.85rem;" x-text="t.beschreibung || '—'"></td>
                                    <td class="center" style="font-variant-numeric:tabular-nums;">
                                        <a x-show="t.anzahl_kontakte > 0" :href="'/crm/kontakte?tag_ids[]=' + t.id" style="color:var(--thoxan-600);font-weight:500;" x-text="t.anzahl_kontakte"></a>
                                        <span x-show="!t.anzahl_kontakte" style="color:var(--slate-300);">0</span>
                                    </td>
                                    <td style="text-align:right;">
                                        <button class="thx-shell-btn" @click="oeffneEdit(t)">Bearbeiten</button>
                                        <button class="thx-shell-btn thx-shell-btn-danger" @click="loeschen(t)" style="color:var(--rose-700);">×</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <template x-if="gefilterte.length === 0">
                <div class="thx-card" style="padding:30px;text-align:center;color:var(--slate-500);">Keine Tags gefunden.</div>
            </template>
        </main>
    </div>

    <div x-show="dialogOffen" x-cloak class="thx-lightbox" style="background:rgba(15,23,42,0.55);z-index:1200;" @click.self="dialogOffen=false">
        <div class="thx-modal" style="max-width:480px;">
            <div style="padding:16px 22px;border-bottom:1px solid var(--slate-200);"><h3 style="margin:0;font-size:1rem;" x-text="formData.id ? 'Tag bearbeiten' : 'Neuer Tag'"></h3></div>
            <div style="padding:16px 22px;display:flex;flex-direction:column;gap:10px;">
                <div>
                    <label style="font-size:0.78rem;color:var(--slate-500);">Name</label>
                    <input type="text" x-model="formData.name" x-init="$nextTick(() => $el.focus())" class="thx-shell-search-input" style="padding-left:10px;">
                </div>
                <div>
                    <label style="font-size:0.78rem;color:var(--slate-500);">Farbe</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="color" x-model="formData.farbe" style="width:42px;height:32px;padding:0;border:1px solid var(--slate-200);border-radius:6px;cursor:pointer;">
                        <input type="text" x-model="formData.farbe" placeholder="#0369a1" class="thx-shell-search-input" style="padding-left:10px;flex:1;">
                        <button type="button" class="thx-shell-btn" @click="formData.farbe = ''">Zurücksetzen</button>
                    </div>
                    <div style="margin-top:6px;font-size:0.78rem;color:var(--slate-500);">Vorschau:
                        <span class="lam-chip" :style="formData.farbe ? ('background:' + formData.farbe + '20;color:' + formData.farbe + ';border-color:' + formData.farbe) : ''" x-text="formData.name || '— ohne Name —'" style="margin-left:6px;"></span>
                    </div>
                </div>
                <div>
                    <label style="font-size:0.78rem;color:var(--slate-500);">Beschreibung (optional)</label>
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
function crmTags() {
    return {
        tags: [], suche: '',
        sort: 'anzahl', nurUngenutzt: false, nurMitFarbe: false,
        dialogOffen: false,
        formData: { id: null, name: '', farbe: '', beschreibung: '' },

        async laden() {
            const r = await fetch('/api/v1/crm/tags' + (this.suche ? '?suche=' + encodeURIComponent(this.suche) : ''));
            const j = await r.json();
            if (j.success) this.tags = j.data.tags || [];
        },
        get gefilterte() {
            let arr = this.tags.slice();
            if (this.nurUngenutzt) arr = arr.filter(t => !t.anzahl_kontakte);
            if (this.nurMitFarbe) arr = arr.filter(t => t.farbe);
            if (this.sort === 'anzahl') arr.sort((a,b) => (b.anzahl_kontakte||0) - (a.anzahl_kontakte||0));
            else arr.sort((a,b) => (a.name||'').localeCompare(b.name||''));
            return arr;
        },
        get tagsGenutzt() { return this.tags.filter(t => t.anzahl_kontakte > 0).length; },
        get tagsOhneFarbe() { return this.tags.filter(t => !t.farbe).length; },

        oeffneNeu() { this.formData = { id: null, name: '', farbe: '', beschreibung: '' }; this.dialogOffen = true; },
        oeffneEdit(t) { this.formData = { id: t.id, name: t.name, farbe: t.farbe || '', beschreibung: t.beschreibung || '' }; this.dialogOffen = true; },
        async speichern() {
            const url = this.formData.id ? ('/api/v1/crm/tags/' + this.formData.id) : '/api/v1/crm/tags';
            const method = this.formData.id ? 'PUT' : 'POST';
            const r = await fetch(url, {
                method, credentials: 'same-origin', headers: {'Content-Type':'application/json'},
                body: JSON.stringify(this.formData)
            });
            const j = await r.json();
            if (j.success) {
                App.showNotification('Gespeichert', 'success');
                this.dialogOffen = false;
                this.laden();
            } else App.showNotification(j.message || 'Fehler', 'error');
        },
        async loeschen(t) {
            if (!confirm('Tag „' + t.name + '" wirklich löschen? Die Vergabe an ' + t.anzahl_kontakte + ' Kontakt(en) wird entfernt.')) return;
            await fetch('/api/v1/crm/tags/' + t.id, { method:'DELETE', credentials:'same-origin' });
            this.laden();
        },
    };
}
</script>
