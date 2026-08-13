<?php
namespace Services;

/**
 * Brevo API v3 Client — minimal fuer Phase 1.
 *
 * Aktuell nur: testConnection + listLists.
 * Voller Funktionsumfang (Contacts-Push, Webhook-Empfang, DOI) folgt im
 * CRM-Phase-1-Schritt 7.
 *
 * API-Doku: https://developers.brevo.com/reference/getaccount-1
 * Authentifizierung: Header `api-key: xkeysib-...`
 */
class CrmBrevoService
{
    private const API_BASE = 'https://api.brevo.com/v3';
    private const TIMEOUT_DEFAULT = 15;

    public function __construct(private string $apiKey, private int $timeout = self::TIMEOUT_DEFAULT) {}

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Testet die API-Verbindung — liefert Account-Info zurueck.
     * @return array ['ok' => bool, 'account' => array|null, 'error' => string|null]
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'account' => null, 'error' => 'Kein API-Key gesetzt'];
        }
        try {
            $account = $this->get('/account');
            return ['ok' => true, 'account' => $account, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'account' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Liste aller Brevo-Listen (Mailing-Listen).
     */
    public function listLists(int $limit = 50, int $offset = 0): array
    {
        $resp = $this->get('/contacts/lists', ['limit' => $limit, 'offset' => $offset]);
        return $resp['lists'] ?? [];
    }

    /**
     * Holt Kontakte paginiert (max 1000 pro Call).
     * @return array ['contacts' => [...], 'count' => int]
     */
    public function listContacts(int $limit = 100, int $offset = 0, ?string $modifiedSince = null): array
    {
        $params = ['limit' => min(1000, $limit), 'offset' => $offset];
        if ($modifiedSince) $params['modifiedSince'] = $modifiedSince;
        return $this->get('/contacts', $params);
    }

    /**
     * Liefert die Attribute-Definitionen (Custom-Fields) eines Brevo-Accounts.
     */
    public function listAttributes(): array
    {
        $resp = $this->get('/contacts/attributes');
        return $resp['attributes'] ?? [];
    }

    /**
     * Holt die Mitglieder einer bestimmten Liste.
     */
    public function listContactsInList(int $listId, int $limit = 500, int $offset = 0): array
    {
        return $this->get('/contacts/lists/' . $listId . '/contacts', ['limit' => $limit, 'offset' => $offset]);
    }

    /**
     * Einzelnen Kontakt per E-Mail abfragen (z.B. für gezielten Re-Sync).
     */
    public function getContactByEmail(string $email): ?array
    {
        try {
            return $this->get('/contacts/' . urlencode($email));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Erstellt oder aktualisiert einen Kontakt in Brevo (Upsert via E-Mail).
     */
    public function upsertContact(array $data): array
    {
        return $this->request('POST', self::API_BASE . '/contacts', $data);
    }

    /**
     * Transaktionsmail senden (z.B. fuer DOI-Bestaetigungs-Mail).
     * @param array $payload {sender:{name,email}, to:[{email,name}], subject, htmlContent, params, headers, ...}
     */
    public function sendTransactionalEmail(array $payload): array
    {
        return $this->request('POST', self::API_BASE . '/smtp/email', $payload);
    }

    // ─── HTTP-Helfer ──────────────────────────────────────────────────────

    private function get(string $path, array $params = []): array
    {
        $url = self::API_BASE . $path;
        if ($params) $url .= '?' . http_build_query($params);
        return $this->request('GET', $url);
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init();
        $headers = [
            'api-key: ' . $this->apiKey,
            'accept: application/json',
            'content-type: application/json',
        ];
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('Brevo-API nicht erreichbar' . ($err ? " ($err)" : ''));
        }
        if ($code === 401) {
            throw new \RuntimeException('Brevo-API-Key ungueltig (401)');
        }
        if ($code === 429) {
            throw new \RuntimeException('Brevo-API Rate-Limit erreicht (429)');
        }
        if ($code >= 400) {
            $msg = $raw ? json_decode($raw, true)['message'] ?? $raw : 'HTTP ' . $code;
            throw new \RuntimeException("Brevo-API-Fehler HTTP $code: " . (is_string($msg) ? $msg : json_encode($msg)));
        }
        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('Brevo-Antwort nicht parsebar');
        }
        return $parsed;
    }
}
