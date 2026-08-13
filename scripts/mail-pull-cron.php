<?php
/**
 * Mail-Pull-Cron: zieht alle aktiven Konten ab, wenn ihr Intervall dran ist.
 * Wird alle 5 Minuten von /etc/cron.d/ki-tool-mail-pull getriggert.
 */
define('ROOT_PATH', '/var/www');
require_once '/var/www/config/constants.php';
require_once '/var/www/vendor/autoload.php';
require_once '/var/www/core/Database.php';
require_once '/var/www/core/Settings.php';
require_once '/var/www/core/Crypto.php';
require_once '/var/www/services/MailKontoService.php';
require_once '/var/www/services/MailImapService.php';

$cfg = require '/var/www/config/config.php';
$db = \Core\Database::getInstance($cfg['db']);

$svc = new \Services\MailImapService($db);
$start = date('Y-m-d H:i:s');
echo "[$start] Mail-Pull-Cron gestartet\n";
$r = $svc->pullAlle('cron');
echo "Konten geprüft: {$r['konten_geprueft']} — Erfolg {$r['erfolg']} / Dubletten {$r['dublette']} / Fehler {$r['fehler']}\n";
foreach (($r['details'] ?? []) as $d) echo "  · $d\n";
echo "[" . date('Y-m-d H:i:s') . "] Fertig\n";
