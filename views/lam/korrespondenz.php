<?php $activeModul = 'korrespondenz'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Korrespondenz</h1>
        <div class="thx-page-subtitle">Kommunikationsverlauf mit Anbietern (Anrufe, Mails, Vermerke).</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="lamKorrespondenz()" x-init="laden()">
    <div style="margin-bottom:14px;">
        <button class="lam-btn lam-btn-primary" @click="oeffneNeu()">+ Neuer Eintrag</button>
    </div>
    <section class="lam-filter-card">
        <div class="lam-filter-head">
            <h2>Filter</h2>
            <span style="font-size:var(--d-fs-xs);color:var(--slate-400);"
                  x-text="rows.length ? (rows.length + ' Einträge') : ''"></span>
        </div>
        <div class="lam-filter-grid">
            <div class="lam-filter-col-6">
                <label class="lam-filter-label">Suche (Inhalt, Betreff)</label>
                <input type="text" class="lam-filter-input"
                       x-model="filter.suche" @input.debounce.300ms="laden()">
            </div>
            <div class="lam-filter-col-6">
                <label class="lam-filter-label">Typ</label>
                <div class="lam-chip-row">
                    <button class="lam-chip lam-chip-reset" :class="filter.typ === '' ? 'is-active' : ''" @click="filter.typ = ''; laden()">alle</button>
                    <template x-for="t in typListe" :key="t">
                        <button class="lam-chip" :class="filter.typ === t ? 'is-active' : ''" @click="filter.typ = t; laden()" x-text="typLabel(t)"></button>
                    </template>
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
                        <th>Typ</th>
                        <th>Anbieter</th>
                        <th>Kontakt</th>
                        <th>Betreff</th>
                        <th>Inhalt</th>
                        <th>Anhang</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="k in rows" :key="k.id">
                        <tr>
                            <td x-text="k.zeitpunkt"></td>
                            <td>
                                <span class="lam-badge"
                                      :style="k.typ === 'mail_eingang' ? 'background:var(--thoxan-100);color:var(--thoxan-700);' : (k.typ === 'mail_ausgang' ? 'background:var(--emerald-100);color:var(--emerald-800);' : '')"
                                      x-text="typLabel(k.typ)"></span>
                            </td>
                            <td>
                                <template x-if="k.anbieter_id">
                                    <a :href="'/lam/anbieter/' + encodeURIComponent(k.anbieter_id)" style="color:var(--thoxan-700);" x-text="k.anbieter_name"></a>
                                </template>
                                <template x-if="!k.anbieter_id"><span class="empty">—</span></template>
                            </td>
                            <td x-text="k.kontakt_name || '—'"></td>
                            <td>
                                <template x-if="k.betreff"><span x-text="k.betreff"></span></template>
                                <template x-if="!k.betreff"><span class="empty">—</span></template>
                            </td>
                            <td>
                                <template x-if="k.inhalt">
                                    <div>
                                        <span class="muted" x-show="!aufgeklappt.has(k.id)" x-text="kurz(k.inhalt, 80)"></span>
                                        <div x-show="aufgeklappt.has(k.id)" style="white-space:pre-wrap;font-size:var(--d-fs-sm);color:var(--slate-700);" x-text="k.inhalt"></div>
                                        <button x-show="k.inhalt.length > 80" class="thx-inline-edit"
                                                @click="toggleAufklappen(k.id)"
                                                style="margin-top:2px;font-size:var(--d-fs-xs);padding:0;background:none;color:var(--thoxan-700);">
                                            <span x-text="aufgeklappt.has(k.id) ? '◂ weniger' : 'mehr ▸'"></span>
                                        </button>
                                    </div>
                                </template>
                                <template x-if="!k.inhalt"><span class="empty">—</span></template>
                                <template x-if="k.massnahme_id">
                                    <a :href="'/lam/massnahmen/' + encodeURIComponent(k.massnahme_id)" style="color:var(--emerald-700);font-size:var(--d-fs-xs);display:block;margin-top:2px;">→ Maßnahme</a>
                                </template>
                            </td>
                            <td>
                                <template x-if="k.anhang_originalname">
                                    <a :href="'/api/v1/lam/korrespondenz-anhang?id=' + encodeURIComponent(k.id)" x-text="k.anhang_originalname" style="color:var(--thoxan-700);" title="Anhang herunterladen"></a>
                                </template>
                                <template x-if="!k.anhang_originalname"><span class="empty">—</span></template>
                            </td>
                            <td>
                                <template x-if="k.typ === 'mail_eingang' && k.mail_id_extern">
                                    <a :href="'/mail?reply=' + encodeURIComponent(k.mail_id_extern)" target="_blank"
                                       title="Im Mail-Modul antworten"
                                       style="color:var(--thoxan-700);text-decoration:none;font-size:0.85rem;">↩ Antworten</a>
                                </template>
                                <template x-if="k.typ === 'mail_ausgang' && k.mail_id_extern">
                                    <a :href="'/mail?reply=' + encodeURIComponent(k.mail_id_extern)" target="_blank"
                                       title="Im Mail-Modul öffnen"
                                       style="color:var(--slate-500);text-decoration:none;font-size:0.85rem;">↗ öffnen</a>
                                </template>
                                <template x-if="!k.mail_id_extern"><span class="empty" style="font-size:0.85rem;">—</span></template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Korrespondenz.</div>
            <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span> Lade …</div>
        </div>
    </section>

    <!-- Drawer: Neuer Korrespondenz-Eintrag -->
    <div class="thx-drawer-backdrop" x-show="drawer.offen" @click.self="drawer.offen = false" x-cloak>
        <div class="thx-drawer">
            <div class="thx-drawer-header">
                <h2 class="thx-drawer-title">Neuer Korrespondenz-Eintrag</h2>
                <button class="thx-modal-close" @click="drawer.offen = false">×</button>
            </div>
            <div class="thx-drawer-body">
                <div class="thx-form-field">
                    <label>Typ</label>
                    <select x-model="drawer.typ">
                        <option value="notiz">Notiz</option>
                        <option value="anruf">Anruf</option>
                        <option value="email">E-Mail (manuell)</option>
                        <option value="sms">SMS</option>
                    </select>
                </div>
                <div class="thx-form-field">
                    <label>Zeitpunkt</label>
                    <input type="datetime-local" x-model="drawer.zeitpunkt">
                </div>
                <div class="thx-form-field">
                    <label>Anbieter *</label>
                    <select x-model="drawer.anbieter_id">
                        <option value="">— wählen —</option>
                        <template x-for="a in anbieterListe" :key="a.id">
                            <option :value="a.id" x-text="a.name"></option>
                        </template>
                    </select>
                </div>
                <div class="thx-form-field">
                    <label>Betreff (optional)</label>
                    <input type="text" x-model="drawer.betreff">
                </div>
                <div class="thx-form-field">
                    <label>Inhalt</label>
                    <textarea x-model="drawer.inhalt" rows="6" placeholder="z.B. Notizen aus dem Anruf"></textarea>
                </div>
                <div class="thx-form-field">
                    <label>Anhang (optional, max 25 MB)</label>
                    <input type="file" x-ref="anhang">
                </div>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="drawer.offen = false">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichere()" :disabled="drawer.laeuft || !drawer.anbieter_id">
                    <span x-show="!drawer.laeuft">Speichern</span><span x-show="drawer.laeuft">…</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function lamKorrespondenz() {
    return {
        laedt: true, rows: [], filter: { suche: '', typ: '' },
        typListe: ['mail_eingang','mail_ausgang','telefon','notiz','sonstiges'],
        typLabels: {
            mail_eingang: '📥 Mail-Eingang',
            mail_ausgang: '📤 Mail-Ausgang',
            telefon:      '☎ Telefon',
            notiz:        '📝 Notiz',
            sonstiges:    'Sonstiges',
        },
        typLabel(t) { return this.typLabels[t] || t; },
        drawer: { offen: false, laeuft: false, typ: 'notiz', zeitpunkt: '', anbieter_id: '', betreff: '', inhalt: '' },
        anbieterListe: [],
        aufgeklappt: new Set(),

        toggleAufklappen(id) {
            const neu = new Set(this.aufgeklappt);
            if (neu.has(id)) neu.delete(id); else neu.add(id);
            this.aufgeklappt = neu;
        },

        async laden() {
            this.laedt = true;
            const p = new URLSearchParams();
            if (this.filter.suche) p.set('suche', this.filter.suche);
            if (this.filter.typ) p.set('typ', this.filter.typ);
            try {
                const r = await fetch('/api/v1/lam/korrespondenz?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.rows = j.success ? j.data : [];
            } finally { this.laedt = false; }
        },
        async oeffneNeu() {
            const jetzt = new Date();
            const local = new Date(jetzt.getTime() - jetzt.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            this.drawer = { offen: true, laeuft: false, typ: 'notiz', zeitpunkt: local, anbieter_id: '', betreff: '', inhalt: '' };
            if (this.anbieterListe.length === 0) {
                try {
                    const r = await fetch('/api/v1/lam/anbieter-kurz', { credentials: 'same-origin' });
                    const j = await r.json();
                    if (j.success) this.anbieterListe = j.data || [];
                } catch (e) {}
            }
        },
        async speichere() {
            if (!this.drawer.anbieter_id) return;
            this.drawer.laeuft = true;
            try {
                const fd = new FormData();
                fd.append('typ', this.drawer.typ);
                fd.append('zeitpunkt', this.drawer.zeitpunkt.replace('T', ' '));
                fd.append('anbieter_id', this.drawer.anbieter_id);
                if (this.drawer.betreff) fd.append('betreff', this.drawer.betreff);
                if (this.drawer.inhalt) fd.append('inhalt', this.drawer.inhalt);
                const datei = this.$refs.anhang?.files[0];
                if (datei) fd.append('anhang', datei);
                const r = await fetch('/api/v1/lam/korrespondenz-save', {
                    method: 'POST', credentials: 'same-origin', body: fd,
                });
                const j = await r.json();
                if (j.success) {
                    this.drawer.offen = false;
                    this.laden();
                } else { alert(j.message || 'Fehler'); }
            } catch (e) { alert('Verbindungsfehler'); }
            finally { this.drawer.laeuft = false; }
        },
        kurz(t, n) { return t && t.length > n ? t.substring(0, n) + '…' : t; }
    };
}
</script>
