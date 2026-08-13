<?php $activeModul = 'dsgvo'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<div class="thx-page-header"><div><h1 class="thx-page-title">DSGVO-Tools</h1><div class="thx-page-subtitle">Datenauskunft erstellen und Hard-Delete ausführen.</div></div></div>
<?php include __DIR__ . '/_tabs.php'; ?>
<div x-data="crmDsgvo()" x-cloak>
    <div class="thx-card" style="margin-bottom:14px;">
        <div class="thx-card-title">Kontakt suchen</div>
        <div style="margin-top:10px;">
            <input type="text" x-model="suche" @input.debounce.300ms="laden()" placeholder="Name oder E-Mail …" style="width:100%;max-width:400px;padding:8px;border:1px solid var(--slate-300);border-radius:6px;">
        </div>
        <div style="margin-top:12px;">
            <template x-for="k in treffer" :key="k.id">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-bottom:1px solid var(--slate-100);">
                    <div>
                        <strong x-text="(k.vorname||'') + ' ' + (k.nachname||'')"></strong>
                        <span style="color:var(--slate-500);margin-left:10px;" x-text="k.email_primaer"></span>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <a class="thx-btn thx-btn-secondary thx-btn-small" :href="'/api/v1/crm/dsgvo/auskunft/' + k.id" target="_blank">Auskunft (JSON)</a>
                        <button class="thx-btn thx-btn-secondary thx-btn-small" @click="hardDelete(k)" style="color:var(--rose-700);">Hard-Delete</button>
                    </div>
                </div>
            </template>
            <template x-if="treffer.length === 0 && suche.length > 1"><div style="color:var(--slate-400);padding:10px;">Keine Treffer.</div></template>
        </div>
    </div>
    <div class="thx-card" style="background:var(--amber-50);border-color:var(--amber-200);">
        <div class="thx-card-title" style="color:var(--amber-800);">⚠ Hard-Delete-Regeln</div>
        <div style="margin-top:8px;font-size:0.85rem;color:var(--amber-900);">
            <ul style="margin:0;padding-left:20px;">
                <li>Stammdaten, Adressen, Aktivitäten, Lead-Magnet-Events, Tags, Listen-Mitgliedschaften werden physisch gelöscht.</li>
                <li>Brevo-Events bleiben für Statistik, aber die E-Mail-Spalte wird auf NULL gesetzt (anonymisiert).</li>
                <li>Tombstone (ID + Zeitpunkt + Löscher) bleibt erhalten — für späteren Embedding-Sync.</li>
                <li>Audit-Eintrag wird mit „hard_deleted_dsgvo" geschrieben.</li>
            </ul>
        </div>
    </div>
</div>
<script>
function crmDsgvo() {
    return {
        suche: '', treffer: [],
        async laden() {
            if (this.suche.length < 2) { this.treffer = []; return; }
            const r = await fetch('/api/v1/crm/kontakte?suche=' + encodeURIComponent(this.suche) + '&limit=20');
            const j = await r.json();
            if (j.success) this.treffer = j.data.eintraege || [];
        },
        async hardDelete(k) {
            if (!confirm('Kontakt ' + k.email_primaer + ' UNWIDERRUFLICH löschen?\n\nDSGVO-Anonymisierung der Brevo-Events erfolgt automatisch.')) return;
            const r = await fetch('/api/v1/crm/dsgvo/hard-delete/' + k.id, { method: 'POST', credentials: 'same-origin' });
            const j = await r.json();
            if (j.success) { App.showNotification('Hard-Delete ausgeführt', 'success'); this.laden(); }
            else App.showNotification(j.message || 'Fehler', 'error');
        },
    };
}
</script>
