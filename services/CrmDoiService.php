<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * CrmDoiService — Double-Opt-In-Flow.
 *
 * Workflow:
 *   1) erfassen($email, $quelle, $listen[]): erzeugt Kontakt (falls neu), Status='pending'
 *      + DOI-Token, sendet Bestätigungs-Mail via Brevo Transactional
 *   2) bestaetigen($token): setzt Status auf 'double_opted_in', protokolliert Beleg
 *   3) widerruf($token): unsubscribed
 */
class CrmDoiService
{
    public function __construct(
        private Database $db,
        private CrmKontaktService $kontaktSvc,
        private CrmBrevoService $brevo
    ) {}

    public function erfassen(string $email, string $quelle, array $listenIds = [], ?string $textEinwilligung = null, ?string $ip = null, ?string $userAgent = null): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-Mail ungueltig');
        }

        // Kontakt suchen/erstellen
        $kontaktId = $this->db->queryValue("SELECT id FROM crm_kontakte WHERE email_primaer = ?", [$email]);
        if (!$kontaktId) {
            $kontaktId = $this->kontaktSvc->anlegen([
                'email_primaer' => $email,
                'nachname' => '(noch unbekannt)',
                'opt_in_status' => 'pending',
                'lead_quelle' => $quelle,
                'kontakt_status' => 'lead',
            ]);
        } else {
            $this->kontaktSvc->aktualisieren((int)$kontaktId, [
                'opt_in_status' => 'pending',
                'lead_quelle' => $quelle,
            ]);
        }

        $token = bin2hex(random_bytes(32));
        $abgelaufenAm = (new \DateTime('+14 days'))->format('Y-m-d H:i:s');

        $this->db->insert('crm_opt_in_events', [
            'kontakt_id' => $kontaktId,
            'typ' => 'erfasst',
            'doi_token' => $token,
            'quelle' => $quelle,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'text_einwilligung' => $textEinwilligung,
            'abgelaufen_am' => $abgelaufenAm,
        ]);

        // Pending-Listen-Mitgliedschaften vormerken
        foreach ($listenIds as $lid) {
            $this->kontaktSvc->setzeListenMitgliedschaft((int)$kontaktId, (int)$lid, 'pending');
        }

        // Mail via Brevo Transactional
        $confirmUrl = \Core\Brand::url('/api/v1/crm/doi/bestaetigen/' . $token);
        $template = (string)Settings::get('crm_doi_text_default', '');

        if ($template === '') {
            $template = '<p>Hallo,</p><p>bitte bestätige Deine Anmeldung mit einem Klick auf den folgenden Link:</p><p><a href="{LINK}">{LINK}</a></p><p>Wenn Du Dich nicht angemeldet hast, ignoriere diese Mail.</p>';
        }
        $htmlContent = str_replace('{LINK}', $confirmUrl, $template);

        try {
            $this->brevo->sendTransactionalEmail([
                'sender' => ['name' => 'Thoxan', 'email' => 'info@thoxan.com'],
                'to' => [['email' => $email]],
                'subject' => 'Bitte bestätige Deine Anmeldung',
                'htmlContent' => $htmlContent,
            ]);
            $this->db->insert('crm_opt_in_events', [
                'kontakt_id' => $kontaktId,
                'typ' => 'doi_mail_gesendet',
                'doi_token' => $token,
            ]);
        } catch (\Throwable $e) {
            // Mail-Versand fehlgeschlagen: Token bleibt, kann manuell nochmal verschickt werden
            return ['kontakt_id' => $kontaktId, 'token' => $token, 'mail_versand' => false, 'fehler' => $e->getMessage()];
        }

        return ['kontakt_id' => $kontaktId, 'token' => $token, 'mail_versand' => true];
    }

    public function bestaetigen(string $token): array
    {
        $event = $this->db->queryOne(
            "SELECT * FROM crm_opt_in_events WHERE doi_token = ? AND typ = 'erfasst' AND (abgelaufen_am IS NULL OR abgelaufen_am > NOW()) ORDER BY id DESC LIMIT 1",
            [$token]
        );
        if (!$event) {
            throw new \RuntimeException('Token ungültig oder abgelaufen');
        }
        $kontaktId = (int)$event['kontakt_id'];

        $this->db->update('crm_kontakte', ['opt_in_status' => 'double_opted_in'], 'id = ?', [$kontaktId]);
        $this->db->insert('crm_opt_in_events', [
            'kontakt_id' => $kontaktId,
            'typ' => 'doi_bestaetigt',
            'doi_token' => $token,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        // Pending-Listen → aktiv
        $this->db->execute(
            "UPDATE crm_kontakt_listen SET status = 'aktiv', beigetreten_am = NOW() WHERE kontakt_id = ? AND status = 'pending'",
            [$kontaktId]
        );

        $this->kontaktSvc->logAktivitaet($kontaktId, 'doi_bestaetigt', 'Double-Opt-In bestätigt', null, ['token_prefix' => substr($token, 0, 8)], null, 'system');

        return ['kontakt_id' => $kontaktId, 'ok' => true];
    }

    public function widerruf(string $token): array
    {
        $event = $this->db->queryOne("SELECT kontakt_id FROM crm_opt_in_events WHERE doi_token = ? LIMIT 1", [$token]);
        if (!$event) throw new \RuntimeException('Token ungültig');

        $kontaktId = (int)$event['kontakt_id'];
        $this->db->update('crm_kontakte', ['opt_in_status' => 'unsubscribed'], 'id = ?', [$kontaktId]);
        $this->db->insert('crm_opt_in_events', [
            'kontakt_id' => $kontaktId,
            'typ' => 'unsubscribe',
            'doi_token' => $token,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $this->db->execute("UPDATE crm_kontakt_listen SET status = 'unsubscribed' WHERE kontakt_id = ?", [$kontaktId]);
        $this->kontaktSvc->logAktivitaet($kontaktId, 'opt_in_erfasst', 'Widerruf erfasst', null, [], null, 'system');
        return ['kontakt_id' => $kontaktId, 'ok' => true];
    }
}
