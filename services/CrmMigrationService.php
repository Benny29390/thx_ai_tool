<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * CrmMigrationService — Brevo-Migration (Initial + Delta).
 *
 * Workflow:
 *   1. Run-Eintrag in crm_migrations_runs anlegen (status='running')
 *   2. Pro Brevo-Contact:
 *      - finden via brevo_id oder email_primaer
 *      - insert oder update mit den Brevo-Daten
 *      - Listen-Mitgliedschaften synchronisieren (Brevo-Liste → crm_listen)
 *      - Custom-Field-Mapping (z.B. SMS, FIRSTNAME, LASTNAME)
 *   3. Run-Eintrag schließen (status='ok' oder 'error')
 *
 * Modus 'delta': nur Brevo-Kontakte mit modifiedSince > letzter erfolgreicher Lauf
 */
class CrmMigrationService
{
    public function __construct(
        private Database $db,
        private CrmBrevoService $brevo,
        private CrmKontaktService $kontaktSvc
    ) {}

    /**
     * Startet einen Migrations-Lauf.
     * @return int Run-ID
     */
    public function starteLauf(string $modus = 'full', ?int $actorUserId = null): int
    {
        return (int)$this->db->insert('crm_migrations_runs', [
            'quelle' => 'brevo',
            'modus' => $modus,
            'gestartet_durch' => $actorUserId,
            'status' => 'running',
        ]);
    }

    public function beendeLauf(int $runId, string $status, array $counters = [], ?string $fehler = null): void
    {
        $this->db->execute(
            "UPDATE crm_migrations_runs
             SET status = ?, beendet_am = NOW(),
                 anzahl_geprueft = ?, anzahl_insert = ?, anzahl_update = ?, anzahl_skip = ?, anzahl_error = ?,
                 fehler_text = ?
             WHERE id = ?",
            [
                $status,
                $counters['geprueft'] ?? 0, $counters['insert'] ?? 0, $counters['update'] ?? 0,
                $counters['skip'] ?? 0, $counters['error'] ?? 0,
                $fehler, $runId,
            ]
        );
    }

    public function letzterLauf(): ?array
    {
        return $this->db->queryOne(
            "SELECT * FROM crm_migrations_runs WHERE quelle = 'brevo' AND status = 'ok' ORDER BY gestartet_am DESC LIMIT 1"
        );
    }

    public function ladeLauf(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM crm_migrations_runs WHERE id = ?", [$id]);
    }

    public function laufHistorie(int $limit = 20): array
    {
        return $this->db->query("SELECT * FROM crm_migrations_runs ORDER BY gestartet_am DESC LIMIT $limit");
    }

    /**
     * Synchronisiert die Brevo-Listen mit crm_listen (1:1 Mapping).
     * Wird einmalig zu Beginn jeder Migration ausgeführt.
     */
    public function synchronisiereListen(): array
    {
        $brevoListen = $this->brevo->listLists(50, 0);
        $angelegt = 0; $aktualisiert = 0;
        foreach ($brevoListen as $bl) {
            $existing = $this->db->queryOne("SELECT id FROM crm_listen WHERE brevo_list_id = ?", [$bl['id']]);
            if ($existing) {
                $this->db->update('crm_listen',
                    ['name' => $bl['name'], 'anzahl_aktive' => (int)($bl['totalSubscribers'] ?? 0)],
                    'id = ?', [$existing['id']]);
                $aktualisiert++;
            } else {
                $this->db->insert('crm_listen', [
                    'name' => $bl['name'],
                    'brevo_list_id' => $bl['id'],
                    'anzahl_aktive' => (int)($bl['totalSubscribers'] ?? 0),
                ]);
                $angelegt++;
            }
        }
        return ['gesamt' => count($brevoListen), 'angelegt' => $angelegt, 'aktualisiert' => $aktualisiert];
    }

    /**
     * Importiert/aktualisiert Kontakte aus Brevo.
     *
     * @param int $runId
     * @param string $modus 'full' oder 'delta'
     * @param int $batchSize Brevo-API-Limit pro Call
     * @return array Counter
     */
    public function importiereKontakte(int $runId, string $modus = 'full', int $batchSize = 500): array
    {
        $counter = ['geprueft' => 0, 'insert' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];

        $modifiedSince = null;
        if ($modus === 'delta') {
            $letzter = $this->letzterLauf();
            if ($letzter) $modifiedSince = (new \DateTime($letzter['gestartet_am']))->format(\DateTime::ATOM);
        }

        $offset = 0;
        while (true) {
            $resp = $this->brevo->listContacts($batchSize, $offset, $modifiedSince);
            $contacts = $resp['contacts'] ?? [];
            if (empty($contacts)) break;

            foreach ($contacts as $c) {
                $counter['geprueft']++;
                try {
                    $aktion = $this->verarbeiteKontakt($c, $runId);
                    $counter[$aktion]++;
                } catch (\Throwable $e) {
                    $counter['error']++;
                    $this->db->insert('crm_migration_audit', [
                        'quelle' => 'brevo',
                        'quelle_id' => (string)($c['id'] ?? ''),
                        'aktion' => 'error',
                        'details_json' => json_encode(['email' => $c['email'] ?? '', 'fehler' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }
            $offset += $batchSize;
            // Soft-Limit: max 10k pro Lauf (sicherer Default)
            if ($counter['geprueft'] >= 20000) break;
        }
        return $counter;
    }

    /**
     * Verarbeitet einen einzelnen Brevo-Kontakt.
     * @return string 'insert' | 'update' | 'skip'
     */
    private function verarbeiteKontakt(array $c, int $runId): string
    {
        $email = trim((string)($c['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->protokollImport($runId, $c, 'skip', 'E-Mail ungueltig');
            return 'skip';
        }

        // Test-Adressen rausfiltern
        if (preg_match('/@(test|example|localhost)\.(de|com|local)$/i', $email)) {
            $this->protokollImport($runId, $c, 'skip', 'Test-Adresse');
            return 'skip';
        }

        $brevoId = (string)($c['id'] ?? '');
        $attrs = $c['attributes'] ?? [];

        // Mapping Brevo → CRM (Standard-Brevo-Felder)
        $felder = [
            'email_primaer' => $email,
            'vorname'  => trim((string)($attrs['FIRSTNAME'] ?? $attrs['VORNAME'] ?? '')),
            'nachname' => trim((string)($attrs['LASTNAME'] ?? $attrs['NACHNAME'] ?? '')) ?: '(unbekannt)',
            'telefon'  => trim((string)($attrs['PHONE'] ?? $attrs['TELEFON'] ?? '')),
            'mobil'    => trim((string)($attrs['SMS'] ?? $attrs['MOBIL'] ?? '')),
            'brevo_id' => $brevoId,
            'brevo_zuletzt_gepusht_am' => date('Y-m-d H:i:s'),
            'legacy_zoho_json' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            'opt_in_status' => $this->mappeBrevoStatus($c),
        ];

        // Vorhandenen Kontakt suchen (per brevo_id oder email)
        $existing = $this->db->queryOne(
            "SELECT id FROM crm_kontakte WHERE brevo_id = ? OR email_primaer = ?",
            [$brevoId, $email]
        );

        if ($existing) {
            $id = (int)$existing['id'];
            $this->kontaktSvc->aktualisieren($id, $felder);
            $this->synchronisiereListenMitgliedschaften($id, $c);
            $this->protokollImport($runId, $c, 'update', null, $id);
            return 'update';
        }

        try {
            $id = $this->kontaktSvc->anlegen(array_merge($felder, ['quelle' => 'brevo_migration']));
        } catch (\RuntimeException $e) {
            // E-Mail-Dublette: nicht angelegt, aber als skip protokollieren
            $this->protokollImport($runId, $c, 'skip', $e->getMessage());
            return 'skip';
        }
        $this->synchronisiereListenMitgliedschaften($id, $c);
        $this->protokollImport($runId, $c, 'insert', null, $id);
        return 'insert';
    }

    private function mappeBrevoStatus(array $c): ?string
    {
        if (!empty($c['emailBlacklisted']) || !empty($c['blacklisted'])) return 'unsubscribed';
        // Brevo-Spezifika: 'OPT_IN' Attribut, oder Listen-Mitgliedschaften
        $attrs = $c['attributes'] ?? [];
        if (!empty($attrs['DOUBLE_OPT-IN']) || !empty($attrs['DOI_DATE'])) return 'double_opted_in';
        if (!empty($c['listIds'])) return 'single_opted_in';
        return null;
    }

    private function synchronisiereListenMitgliedschaften(int $kontaktId, array $c): void
    {
        $listIds = $c['listIds'] ?? [];
        if (empty($listIds)) return;
        foreach ($listIds as $brevoListId) {
            $crmListenId = $this->db->queryValue(
                "SELECT id FROM crm_listen WHERE brevo_list_id = ?", [$brevoListId]
            );
            if ($crmListenId) {
                $this->kontaktSvc->setzeListenMitgliedschaft($kontaktId, (int)$crmListenId, 'aktiv');
            }
        }
    }

    private function protokollImport(int $runId, array $c, string $aktion, ?string $hinweis = null, ?int $kontaktId = null): void
    {
        $this->db->insert('crm_migration_audit', [
            'quelle' => 'brevo',
            'quelle_id' => (string)($c['id'] ?? ''),
            'aktion' => $aktion,
            'kontakt_id' => $kontaktId,
            'details_json' => json_encode([
                'email' => $c['email'] ?? '',
                'hinweis' => $hinweis,
                'run_id' => $runId,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
