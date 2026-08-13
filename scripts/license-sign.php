<?php
/**
 * license-sign.php — Lizenzdatei erzeugen und signieren (NUR beim Hersteller).
 *
 * Nutzt den privaten Ed25519-Schluessel (config/license-signing-key.SECRET),
 * der NIEMALS auf einen Kundenserver gehoert. Ausgabe ist eine license.json,
 * die auf dem Zielserver nach config/license.json gelegt wird.
 *
 * Aufruf:
 *   php scripts/license-sign.php \
 *       --installation=kunde-mueller-01 \
 *       --customer="Mueller GmbH" \
 *       --modules=chat,knowledge,crm,mail \
 *       --expires=2027-08-13            # optional; weglassen = unbefristet
 *       --out=/pfad/license.json        # optional; Default: STDOUT
 *
 *   Alle Module freischalten:  --modules='*'
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/License.php';

function arg(string $name, array $argv): ?string
{
    foreach ($argv as $i => $a) {
        if (strpos($a, "--$name=") === 0) return substr($a, strlen("--$name="));
        if ($a === "--$name" && isset($argv[$i + 1])) return $argv[$i + 1];
    }
    return null;
}

$keyFile = CONFIG_PATH . '/license-signing-key.SECRET';
if (!is_file($keyFile)) {
    fwrite(STDERR, "Signierschluessel fehlt: $keyFile\n");
    fwrite(STDERR, "Dieses Skript darf nur auf dem Hersteller-Rechner laufen.\n");
    exit(1);
}
$secHex = trim((string) file_get_contents($keyFile));
$sec = @hex2bin($secHex);
if ($sec === false || strlen($sec) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    fwrite(STDERR, "Ungueltiger Signierschluessel.\n");
    exit(1);
}

$installation = trim((string) arg('installation', $argv));
$customer     = trim((string) arg('customer', $argv));
$modulesRaw   = trim((string) arg('modules', $argv));
$expires      = arg('expires', $argv);
$out          = arg('out', $argv);

if ($installation === '' || $customer === '' || $modulesRaw === '') {
    fwrite(STDERR, "Pflichtangaben: --installation, --customer, --modules\n");
    exit(1);
}

$modules = $modulesRaw === '*'
    ? ['*']
    : array_values(array_filter(array_map('trim', explode(',', $modulesRaw)), fn($m) => $m !== ''));

$data = [
    'installation_id' => $installation,
    'customer'        => $customer,
    'modules'         => $modules,
    'issued_at'       => gmdate('Y-m-d'),
];
if ($expires !== null && $expires !== '') {
    if (!\DateTime::createFromFormat('Y-m-d', $expires, new \DateTimeZone('UTC'))) {
        fwrite(STDERR, "--expires muss YYYY-MM-DD sein.\n");
        exit(1);
    }
    $data['expires_at'] = $expires;
}

$canonical = \Core\License::canonical($data);
$sig = sodium_crypto_sign_detached($canonical, $sec);

$license = ['data' => $data, 'sig' => bin2hex($sig)];
$json = json_encode($license, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

// Selbstkontrolle: sofort gegenpruefen.
if (!\Core\License::verify($license)) {
    fwrite(STDERR, "INTERN: Selbstpruefung der Signatur fehlgeschlagen — Abbruch.\n");
    exit(1);
}

if ($out) {
    file_put_contents($out, $json);
    echo "Lizenz geschrieben: $out\n";
    echo "Auf dem Zielserver nach config/license.json legen.\n";
} else {
    echo $json;
}
exit(0);
