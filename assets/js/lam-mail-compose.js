/**
 * Wiederverwendbare Mail-Compose-Helpers für LAM-Views.
 * In x-data einbinden:
 *
 *   ...lamMailCompose(),
 *
 * Dann `oeffneMailCompose({ anbieterId, empfaenger, betreff, ... })` aufrufen
 * um das Modal zu öffnen.
 */
function lamMailCompose() {
    return {
        mailCompose: {
            offen: false,
            empfaenger: '',
            betreff: '',
            text: '',
            kontoId: '',
            kontoListe: [],
            anbieterId: '',
            kontaktId: '',
            massnahmeId: '',
            vorschlagslisteEintragId: '',
            hinweis: '',
            laeuft: false,
            fehler: '',
        },

        async ladeMailKonten() {
            if (this.mailCompose.kontoListe.length > 0) return;
            try {
                const r = await fetch('/api/v1/mail/konten', { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) {
                    this.mailCompose.kontoListe = (j.data || [])
                        .filter(k => k.ist_aktiv)
                        .map(k => ({
                            id: k.id,
                            label: (k.name ? k.name + ' · ' : '') + k.email_adresse,
                        }));
                    if (this.mailCompose.kontoListe.length === 1 && !this.mailCompose.kontoId) {
                        this.mailCompose.kontoId = this.mailCompose.kontoListe[0].id;
                    }
                }
            } catch (e) {}
        },

        async oeffneMailCompose(opts = {}) {
            await this.ladeMailKonten();
            this.mailCompose.empfaenger = opts.empfaenger || '';
            this.mailCompose.betreff = opts.betreff || '';
            this.mailCompose.text = opts.text || '';
            this.mailCompose.anbieterId = opts.anbieterId || '';
            this.mailCompose.kontaktId = opts.kontaktId || '';
            this.mailCompose.massnahmeId = opts.massnahmeId || '';
            this.mailCompose.vorschlagslisteEintragId = opts.vorschlagslisteEintragId || '';
            this.mailCompose.hinweis = opts.hinweis || '';
            this.mailCompose.fehler = '';
            this.mailCompose.offen = true;
        },

        async mailComposeSenden() {
            if (this.mailCompose.laeuft) return;
            this.mailCompose.fehler = '';
            this.mailCompose.laeuft = true;
            try {
                const body = {
                    konto_id: this.mailCompose.kontoId,
                    empfaenger: this.mailCompose.empfaenger,
                    betreff: this.mailCompose.betreff,
                    text: this.mailCompose.text,
                };
                if (this.mailCompose.anbieterId)               body.anbieter_id = this.mailCompose.anbieterId;
                if (this.mailCompose.kontaktId)                body.kontakt_id = this.mailCompose.kontaktId;
                if (this.mailCompose.massnahmeId)              body.massnahme_id = this.mailCompose.massnahmeId;
                if (this.mailCompose.vorschlagslisteEintragId) body.vorschlagsliste_eintrag_id = this.mailCompose.vorschlagslisteEintragId;

                const r = await fetch('/api/v1/mail/mail-senden', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message || 'Versand fehlgeschlagen');
                this.mailCompose.offen = false;
                // Nachricht an Hosting-View
                if (typeof this.onMailComposeGesendet === 'function') {
                    this.onMailComposeGesendet(j.data);
                } else {
                    alert('✓ Mail gesendet — wird in der Korrespondenz aufgeführt.');
                }
            } catch (e) {
                this.mailCompose.fehler = e.message;
            } finally { this.mailCompose.laeuft = false; }
        },
    };
}
