<?php
/**
 * Wiederverwendbares Mail-Compose-Modal für LAM-Views.
 * Erwartet im Alpine-x-data-Scope ein Objekt `mailCompose` mit folgenden Feldern:
 *   {
 *     offen: bool,
 *     empfaenger: string,
 *     betreff: string,
 *     text: string,
 *     kontoId: int,
 *     kontoListe: [{id, label}],
 *     anbieterId: string,           // wird in lam_kommunikation registriert
 *     kontaktId?: string,
 *     massnahmeId?: string,
 *     vorschlagslisteEintragId?: string,
 *     hinweis?: string,             // z.B. „An: Tobias Massow (evernine media)"
 *     laeuft: bool,
 *     fehler: string
 *   }
 *
 * Und eine Funktion `mailComposeSenden()` die die Felder als JSON ans Backend schickt.
 */
?>
<div x-show="mailCompose.offen" x-cloak
     style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding-top:60px;"
     @click.self="mailCompose.offen = false">
    <div style="background:#fff;border-radius:10px;width:640px;max-width:calc(100% - 40px);max-height:calc(100vh - 120px);box-shadow:0 14px 40px rgba(15,23,42,0.2);overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:18px 24px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;">📧 Neue Mail schreiben</h3>
            <button @click="mailCompose.offen = false" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
        </div>
        <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px;overflow-y:auto;flex:1;">
            <div x-show="mailCompose.hinweis" style="background:var(--thoxan-50);border:1px solid var(--thoxan-200);border-radius:6px;padding:8px 12px;font-size:0.85rem;color:var(--thoxan-800);" x-text="mailCompose.hinweis"></div>

            <div>
                <label style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:5px;">Von Konto</label>
                <select x-model.number="mailCompose.kontoId"
                        style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
                    <option value="">— Konto wählen —</option>
                    <template x-for="k in mailCompose.kontoListe" :key="k.id">
                        <option :value="k.id" x-text="k.label"></option>
                    </template>
                </select>
            </div>

            <div>
                <label style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:5px;">An <span style="color:var(--rose-600);">*</span></label>
                <input type="email" x-model="mailCompose.empfaenger"
                       placeholder="empfaenger@beispiel.de"
                       style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
            </div>

            <div>
                <label style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:5px;">Betreff <span style="color:var(--rose-600);">*</span></label>
                <input type="text" x-model="mailCompose.betreff"
                       style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
            </div>

            <div>
                <label style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:5px;">Text <span style="color:var(--rose-600);">*</span></label>
                <textarea x-model="mailCompose.text" rows="10"
                          style="width:100%;padding:10px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;font-family:inherit;line-height:1.5;resize:vertical;"></textarea>
                <p style="margin:4px 0 0;font-size:0.7rem;color:var(--slate-500);">Signatur wird automatisch angehängt. Plain-Text.</p>
            </div>

            <div x-show="mailCompose.fehler" style="background:var(--rose-50);border:1px solid var(--rose-200);border-radius:6px;padding:8px 12px;font-size:0.85rem;color:var(--rose-700);" x-text="mailCompose.fehler"></div>
        </div>
        <div style="padding:14px 24px;border-top:1px solid var(--slate-100);background:var(--slate-50);display:flex;justify-content:flex-end;gap:10px;">
            <button @click="mailCompose.offen = false" class="lam-btn lam-btn-secondary">Abbrechen</button>
            <button @click="mailComposeSenden()" :disabled="mailCompose.laeuft || !mailCompose.kontoId || !mailCompose.empfaenger || !mailCompose.betreff || !mailCompose.text"
                    class="lam-btn lam-btn-primary">
                <span x-show="!mailCompose.laeuft">📤 Mail senden</span>
                <span x-show="mailCompose.laeuft">… sendet</span>
            </button>
        </div>
    </div>
</div>
