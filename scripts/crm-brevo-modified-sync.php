<?php
/**
 * Synct den modifiedAt-Zeitstempel aus Brevo in crm_kontakte.brevo_modified_at.
 *
 * Brevo aktualisiert modifiedAt bei jeder Änderung am Kontakt-Profil — Webhook-Events
 * wie Open/Click/Sent updaten den Kontakt mit. Damit ist das ein guter Aktualitäts-
 * Proxy für die Pflege-Center-Sortierung („wer war zuletzt mit uns in Berührung?").
 *
 * Performant: paginierter listContacts-Endpoint (1000 pro Seite). 2300 Kontakte
 * → ~3 API-Calls, Sekunden statt Minuten.
 *
 * Aufruf: php /var/www/scripts/crm-brevo-modified-sync.php
 */

require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    foreach (['/var/www/core/', '/var/www/services/'] as $p) {
        $f = $p . str_replace(['\\', 'Services/', 'Core/'], ['/', '', ''], $class) . '.php';
        if (file_exists($f)) { require_once $f; return; }
    }
});

$cfg = require '/var/www/config/config.php';
$db = \Core\Database::getInstance($cfg['db']);

$key = (string) \Core\Settings::get('brevo_api_key');
if ($key === '') {
    fwrite(STDERR, "Kein Brevo-API-Key in Settings.\n");
    exit(1);
}
$svc = new \Services\CrmBrevoService($key);

$offset = 0;
$limit  = 1000;
$total  = 0;
$updated = 0;

echo "Starte Brevo-Modified-Sync …\n";
$t0 = microtime(true);

while (true) {
    try {
        $resp = $svc->listContacts($limit, $offset);
    } catch (\Throwable $e) {
        fwrite(STDERR, "API-Fehler bei offset=$offset: " . $e->getMessage() . "\n");
        break;
    }
    $kontakte = $resp['contacts'] ?? [];
    if (empty($kontakte)) break;
    foreach ($kontakte as $bc) {
        $total++;
        $email = strtolower(trim((string) ($bc['email'] ?? '')));
        $modAt = $bc['modifiedAt'] ?? null;
        if ($email === '' || !$modAt) continue;
        // Brevo liefert ISO-8601 mit Zeitzone — in UTC normalisieren + DB-Format
        try {
            $dt = new \DateTime($modAt);
            $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            $dtStr = $dt->format('Y-m-d H:i:s');
        } catch (\Throwable $e) { continue; }
        // Match per E-Mail (primär + zweit)
        $r = $db->execute(
            "UPDATE crm_kontakte
                SET brevo_modified_at = ?
              WHERE geloescht_am IS NULL
                AND (LOWER(email_primaer) = ? OR LOWER(email_zweit) = ?)
                AND (brevo_modified_at IS NULL OR brevo_modified_at < ?)",
            [$dtStr, $email, $email, $dtStr]
        );
        if ($r > 0) $updated++;
    }
    $offset += $limit;
    echo "  · offset=$offset · gelesen=$total · upgedatet=$updated\n";
    if (count($kontakte) < $limit) break;
}

$dt = round(microtime(true) - $t0, 1);
echo "\nFertig in {$dt}s: $total Brevo-Kontakte gelesen, $updated CRM-Kontakte mit neuem brevo_modified_at\n";
