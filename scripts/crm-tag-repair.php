<?php
/**
 * Repariert Tags, die als Kombo-String (mit ; oder ,) angelegt wurden.
 *
 * Vorgehen pro kaputtem Tag:
 *   1. Splitten an ; oder ,
 *   2. Einzelne Tags anlegen (falls noch nicht vorhanden)
 *   3. Alle Kontakte des Kombo-Tags den Einzeltags zuweisen (INSERT IGNORE)
 *   4. Kombo-Tag samt Zuweisungen löschen
 *
 * Aktualisiert am Ende anzahl_kontakte in crm_tags.
 */
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/config/constants.php';
require BASE_PATH . '/core/Database.php';

$opts = getopt('', ['dry-run']);
$dryRun = isset($opts['dry-run']);

$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();

echo "─── Tag-Repair ───\n";
echo "Modus: " . ($dryRun ? "DRY-RUN" : "LIVE") . "\n\n";

$kaputt = $db->query("SELECT id, name FROM crm_tags WHERE name LIKE '%;%' OR name LIKE '%,%' ORDER BY name");
echo "Kaputte Tags gefunden: " . count($kaputt) . "\n\n";

$stats = ['tags_geloescht' => 0, 'tags_neu_angelegt' => 0, 'kontakte_neuzuweisung' => 0, 'altzuweisung_geloescht' => 0];

foreach ($kaputt as $tag) {
    $teile = array_filter(array_map('trim', preg_split('/[,;]/', $tag['name'])));
    if (count($teile) <= 1) continue;

    echo "  Splitte #{$tag['id']} \"{$tag['name']}\" → " . count($teile) . " Tags\n";

    // Welche Kontakte hatten diesen Kombo-Tag?
    $kontakte = $db->query("SELECT kontakt_id FROM crm_kontakt_tags WHERE tag_id = ?", [$tag['id']]);

    foreach ($teile as $teilName) {
        // Existierenden Tag finden oder neu anlegen
        $teilId = $db->queryValue("SELECT id FROM crm_tags WHERE LOWER(name) = LOWER(?)", [$teilName]);
        if (!$teilId) {
            if (!$dryRun) {
                $teilId = $db->insert('crm_tags', [
                    'name' => $teilName,
                    'slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $teilName)),
                ]);
            }
            $stats['tags_neu_angelegt']++;
        }

        // Alle Kontakte des Kombo-Tags dem Einzel-Tag zuordnen
        if (!$dryRun && $teilId) {
            foreach ($kontakte as $k) {
                try {
                    $rows = $db->execute("INSERT IGNORE INTO crm_kontakt_tags (kontakt_id, tag_id) VALUES (?, ?)",
                                          [$k['kontakt_id'], $teilId]);
                    if ($rows > 0) $stats['kontakte_neuzuweisung']++;
                } catch (\Throwable $e) {}
            }
        }
    }

    // Kombo-Tag und seine Zuweisungen löschen
    if (!$dryRun) {
        $del = $db->execute("DELETE FROM crm_kontakt_tags WHERE tag_id = ?", [$tag['id']]);
        $stats['altzuweisung_geloescht'] += (int)$del;
        $db->execute("DELETE FROM crm_tags WHERE id = ?", [$tag['id']]);
    }
    $stats['tags_geloescht']++;
}

// anzahl_kontakte in crm_tags neu berechnen — damit Filter-Chips korrekte Zahlen zeigen
if (!$dryRun) {
    echo "\n  Berechne anzahl_kontakte in crm_tags neu …\n";
    $db->execute("UPDATE crm_tags t SET anzahl_kontakte = (
        SELECT COUNT(*) FROM crm_kontakt_tags kt WHERE kt.tag_id = t.id
    )");

    // Auch bei crm_listen
    echo "  Berechne anzahl_aktive in crm_listen neu …\n";
    $db->execute("UPDATE crm_listen l SET anzahl_aktive = (
        SELECT COUNT(*) FROM crm_kontakt_listen kl WHERE kl.listen_id = l.id AND kl.status = 'aktiv'
    )");
}

echo "\n─── Statistik ───\n";
foreach ($stats as $k => $v) printf("  %-30s : %d\n", $k, $v);
echo "\n" . ($dryRun ? "DRY-RUN — nichts geändert." : "Fertig.") . "\n";
