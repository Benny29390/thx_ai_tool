<?php
/** DOI-Token älter als 14 Tage als abgelaufen markieren. */
define('BASE_PATH', __DIR__ . '/..');
define('CONFIG_PATH', BASE_PATH . '/config');
require BASE_PATH . '/core/Database.php';
$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();
$n = $db->execute("UPDATE crm_opt_in_events SET abgelaufen_am = NOW() 
                   WHERE typ = 'erfasst' AND abgelaufen_am IS NULL AND erfolgt_am < DATE_SUB(NOW(), INTERVAL 14 DAY)");
echo "$n abgelaufene DOI-Tokens markiert.\n";
