<?php
/**
 * Artefakt-Maintenance Batch-Job
 *
 * Ausfuehren: php /var/www/scripts/artifact-maintenance.php
 * Cronjob:    0 3 * * 1  php /var/www/scripts/artifact-maintenance.php  (woechentlich Mo 3:00)
 *
 * Checks:
 * 1. Nie abgerufene Artefakte (kein Eintrag in artifacts_used seit 60 Tagen)
 * 2. Veraltete Artefakte (nicht aktualisiert seit 180 Tagen)
 * 3. Artefakte die immer zusammen abgerufen werden (Cluster-Erkennung)
 */

require_once dirname(__DIR__) . '/config/constants.php';

spl_autoload_register(function ($class) {
    $namespaces = ['Core\\' => 'core/', 'Services\\' => 'services/'];
    foreach ($namespaces as $namespace => $dir) {
        if (strpos($class, $namespace) === 0) {
            $relativeClass = substr($class, strlen($namespace));
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

$config = require CONFIG_PATH . '/config.php';
$db = \Core\Database::getInstance($config['db']);

$eventService = new \Services\ArtifactEventService($db);

$now = date('Y-m-d H:i:s');
echo "=== Artefakt-Maintenance: {$now} ===\n\n";

// 1. Nie abgerufene Artefakte (aktiv, aber nie in artifacts_used aufgetaucht)
echo "1. Pruefe nie abgerufene Artefakte...\n";
$unusedDays = 60;
$cutoff = date('Y-m-d', strtotime("-{$unusedDays} days"));

// Alle aktiven Artefakte die aelter als 60 Tage sind
$activeArtifacts = $db->query(
    "SELECT id, slug, meta, created_at FROM artifacts
     WHERE (JSON_EXTRACT(meta, '$.is_active') IS NULL OR JSON_EXTRACT(meta, '$.is_active') != false)
     AND created_at < ?",
    [$cutoff]
);

$usedIds = [];
$usageRows = $db->query(
    "SELECT DISTINCT artifacts_used FROM chat_conversation_messages
     WHERE artifacts_used IS NOT NULL AND created_at > ?",
    [$cutoff]
);
foreach ($usageRows as $row) {
    $artifacts = json_decode($row['artifacts_used'], true) ?? [];
    foreach ($artifacts as $a) {
        $usedIds[(int)($a['id'] ?? 0)] = true;
    }
}

$unusedCount = 0;
foreach ($activeArtifacts as $art) {
    if (isset($usedIds[(int)$art['id']])) continue;
    $meta = json_decode($art['meta'], true) ?? [];
    if (($meta['type'] ?? '') === 'Namespace') continue; // Namespaces ueberspringen

    // Pruefen ob schon ein unused-Event existiert (nicht doppelt melden)
    $existing = $db->queryOne(
        "SELECT id FROM artifact_events WHERE artifact_id = ? AND event_type = 'unused' AND created_at > ?",
        [$art['id'], $cutoff]
    );
    if ($existing) continue;

    $name = $meta['name'] ?? $art['slug'];
    $eventService->createEvent((int)$art['id'], 'unused',
        "Artefakt \"{$name}\" wurde seit {$unusedDays} Tagen nicht abgerufen", [
        'created_at' => $art['created_at'],
        'days_unused' => $unusedDays,
    ]);
    $unusedCount++;
}
echo "   {$unusedCount} ungenutzte Artefakte gemeldet\n\n";

// 2. Veraltete Artefakte (nicht aktualisiert seit 180 Tagen)
echo "2. Pruefe veraltete Artefakte...\n";
$staleDays = 180;
$staleCutoff = date('Y-m-d', strtotime("-{$staleDays} days"));

$staleArtifacts = $db->query(
    "SELECT id, slug, meta, updated_at FROM artifacts
     WHERE (JSON_EXTRACT(meta, '$.is_active') IS NULL OR JSON_EXTRACT(meta, '$.is_active') != false)
     AND updated_at < ?",
    [$staleCutoff]
);

$staleCount = 0;
foreach ($staleArtifacts as $art) {
    $meta = json_decode($art['meta'], true) ?? [];
    if (($meta['type'] ?? '') === 'Namespace') continue;

    // Nicht doppelt melden
    $existing = $db->queryOne(
        "SELECT id FROM artifact_events WHERE artifact_id = ? AND event_type = 'stale' AND created_at > ?",
        [$art['id'], $cutoff]
    );
    if ($existing) continue;

    $name = $meta['name'] ?? $art['slug'];
    $daysSince = (int)((time() - strtotime($art['updated_at'])) / 86400);
    $eventService->createEvent((int)$art['id'], 'stale',
        "Artefakt \"{$name}\" seit {$daysSince} Tagen nicht aktualisiert", [
        'updated_at' => $art['updated_at'],
        'days_stale' => $daysSince,
    ]);
    $staleCount++;
}
echo "   {$staleCount} veraltete Artefakte gemeldet\n\n";

// 3. Cluster-Erkennung: Artefakte die oft zusammen abgerufen werden
echo "3. Pruefe Abruf-Cluster...\n";
$pairCounts = [];
foreach ($usageRows as $row) {
    $artifacts = json_decode($row['artifacts_used'], true) ?? [];
    $ids = array_map(fn($a) => (int)($a['id'] ?? 0), $artifacts);
    $ids = array_unique(array_filter($ids));
    sort($ids);
    // Alle Paare zaehlen
    for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
            $key = $ids[$i] . '-' . $ids[$j];
            $pairCounts[$key] = ($pairCounts[$key] ?? 0) + 1;
        }
    }
}

// Paare die >= 5x zusammen vorkommen und nicht verlinkt sind
$clusterCount = 0;
foreach ($pairCounts as $key => $count) {
    if ($count < 5) continue;
    [$idA, $idB] = explode('-', $key);
    $idA = (int)$idA;
    $idB = (int)$idB;

    // Bereits verlinkt?
    $linked = $db->queryOne(
        "SELECT id FROM artifact_links WHERE (source_id = ? AND target_id = ?) OR (source_id = ? AND target_id = ?)",
        [min($idA, $idB), max($idA, $idB), min($idA, $idB), max($idA, $idB)]
    );
    if ($linked) continue;

    // Bereits gemeldet?
    $existing = $db->queryOne(
        "SELECT id FROM artifact_events WHERE artifact_id = ? AND event_type = 'link_suggested'
         AND JSON_CONTAINS(details, ?, '$.pair_with') AND created_at > ?",
        [$idA, json_encode($idB), $cutoff]
    );
    if ($existing) continue;

    $artA = $db->queryOne("SELECT slug, meta FROM artifacts WHERE id = ?", [$idA]);
    $artB = $db->queryOne("SELECT slug, meta FROM artifacts WHERE id = ?", [$idB]);
    if (!$artA || !$artB) continue;

    $nameA = (json_decode($artA['meta'], true) ?? [])['name'] ?? $artA['slug'];
    $nameB = (json_decode($artB['meta'], true) ?? [])['name'] ?? $artB['slug'];

    $eventService->createEvent($idA, 'link_suggested',
        "Wird oft zusammen mit \"{$nameB}\" abgerufen ({$count}x) — Verbindung vorschlagen?", [
        'pair_with' => $idB,
        'pair_name' => $nameB,
        'co_occurrence_count' => $count,
    ]);
    $clusterCount++;
}
echo "   {$clusterCount} Cluster-Vorschlaege erstellt\n\n";

// 4. Kalender-Events: Saisonale Artefakte die bald relevant werden
echo "4. Pruefe saisonale Artefakte...\n";
$in7days = date('Y-m-d', strtotime('+7 days'));
$today = date('Y-m-d');

$seasonalArts = $db->query(
    "SELECT id, slug, meta FROM artifacts
     WHERE JSON_UNQUOTE(JSON_EXTRACT(meta, '$.season_from')) IS NOT NULL
     AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.season_from')) != 'null'
     AND (JSON_EXTRACT(meta, '$.is_active') IS NULL OR JSON_EXTRACT(meta, '$.is_active') != false)"
);

$seasonalCount = 0;
foreach ($seasonalArts as $art) {
    $meta = json_decode($art['meta'], true) ?? [];
    $from = $meta['season_from'] ?? '';
    $to = $meta['season_to'] ?? '';
    if (empty($from)) continue;

    $name = $meta['name'] ?? $art['slug'];

    // Beginnt in den naechsten 7 Tagen?
    if ($from >= $today && $from <= $in7days) {
        $existing = $db->queryOne(
            "SELECT id FROM artifact_events WHERE artifact_id = ? AND event_type = 'review_needed' AND title LIKE '%saisonal%' AND created_at > ?",
            [$art['id'], $cutoff]
        );
        if (!$existing) {
            $eventService->createEvent((int)$art['id'], 'review_needed',
                "Artefakt \"{$name}\" wird saisonal relevant ab {$from}", [
                'season_from' => $from,
                'season_to' => $to,
            ]);
            $seasonalCount++;
        }
    }

    // Ist abgelaufen?
    if (!empty($to) && $to < $today) {
        $existing = $db->queryOne(
            "SELECT id FROM artifact_events WHERE artifact_id = ? AND event_type = 'stale' AND title LIKE '%Saison%' AND created_at > ?",
            [$art['id'], $cutoff]
        );
        if (!$existing) {
            $eventService->createEvent((int)$art['id'], 'stale',
                "Saison von \"{$name}\" ist abgelaufen (bis {$to})", [
                'season_from' => $from,
                'season_to' => $to,
            ]);
            $seasonalCount++;
        }
    }
}
echo "   {$seasonalCount} saisonale Events erstellt\n\n";

// 5. Widerspruchserkennung via LLM (nur mit --check-conflicts Flag)
if (in_array('--check-conflicts', $argv ?? [])) {
    echo "5. Pruefe Widersprueche via LLM...\n";

    $settings = [];
    foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $oaiKey = $settings['openai_api_key'] ?? '';

    if (empty($oaiKey)) {
        echo "   SKIP: Kein OpenAI API Key konfiguriert\n\n";
    } else {
        require_once SERVICES_PATH . '/AIService.php';
        require_once SERVICES_PATH . '/EntityService.php';
        require_once SERVICES_PATH . '/ArtifactService.php';

        $ai = new \Services\AIService($oaiKey, 'openai');
        $ai->setModel('gpt-4o-mini');
        $entityService = new \Services\EntityService($db);
        $artifactService = new \Services\ArtifactService($db, $entityService);

        // Artefakte nach Namespace+Typ gruppieren (gleicher Scope = Konfliktkandidaten)
        $all = $db->query("SELECT id, slug, meta FROM artifacts WHERE JSON_EXTRACT(meta, '$.is_active') IS NULL OR JSON_EXTRACT(meta, '$.is_active') != false");
        $groups = [];
        foreach ($all as $art) {
            $meta = json_decode($art['meta'], true) ?? [];
            $ns = $meta['namespace'] ?? 'global';
            $type = $meta['type'] ?? 'sonstig';
            $key = $ns . '/' . $type;
            $groups[$key][] = $art;
        }

        $conflictCount = 0;
        foreach ($groups as $groupKey => $groupArts) {
            if (count($groupArts) < 2 || count($groupArts) > 15) continue;

            // Artefakte auflisten fuer LLM
            $listing = '';
            foreach ($groupArts as $art) {
                $meta = json_decode($art['meta'], true) ?? [];
                $name = $meta['name'] ?? $art['slug'];
                $enriched = $artifactService->getById((int)$art['id']);
                $content = mb_substr($enriched['resolved_content'] ?? '', 0, 300);
                $listing .= "- [{$art['id']}] {$name}: {$content}\n";
            }

            $prompt = "Pruefe die folgenden Artefakte (Gruppe: {$groupKey}) auf Widersprueche.\n"
                . "Ein Widerspruch ist wenn zwei Artefakte gegensaetzliche Aussagen machen (z.B. 'per Du' vs 'per Sie').\n"
                . "Kein Widerspruch: Unterschiedliche Themen, Ergaenzungen, verschiedene Aspekte.\n\n"
                . $listing
                . "\nAntwort NUR als JSON-Array (leer wenn keine Widersprueche):\n"
                . '[{"id_a": <id>, "id_b": <id>, "reason": "<kurze Erklaerung>"}]';

            try {
                $result = $ai->chat([['role' => 'user', 'content' => $prompt]], 'Du bist ein Qualitaetspruefer fuer eine Wissensdatenbank. Antworte NUR mit JSON.');
                $text = trim($result['content'] ?? '');
                // JSON extrahieren
                if (preg_match('/\[.*\]/s', $text, $m)) {
                    $conflicts = json_decode($m[0], true) ?? [];
                    foreach ($conflicts as $c) {
                        $idA = (int)($c['id_a'] ?? 0);
                        $idB = (int)($c['id_b'] ?? 0);
                        if (!$idA || !$idB) continue;

                        // Nicht doppelt melden
                        $existing = $db->queryOne(
                            "SELECT id FROM artifact_events WHERE artifact_id = ? AND event_type = 'conflict_detected' AND JSON_EXTRACT(details, '$.conflict_with') = ? AND created_at > ?",
                            [$idA, $idB, $cutoff]
                        );
                        if ($existing) continue;

                        $eventService->createEvent($idA, 'conflict_detected',
                            "Moeglicher Widerspruch: " . mb_substr($c['reason'] ?? '', 0, 200), [
                            'conflict_with' => $idB,
                            'reason' => $c['reason'] ?? '',
                            'group' => $groupKey,
                        ]);
                        $conflictCount++;
                    }
                }
            } catch (\Exception $e) {
                echo "   Fehler bei Gruppe {$groupKey}: " . $e->getMessage() . "\n";
            }

            usleep(500000); // Rate-Limiting
        }
        echo "   {$conflictCount} Widersprueche gefunden\n\n";
    }
} else {
    echo "5. Widerspruchserkennung uebersprungen (--check-conflicts Flag fuer LLM-Pruefung)\n\n";
}

// Zusammenfassung
$totalUnread = $eventService->countUnread();
echo "=== Fertig. {$totalUnread} ungelesene Events insgesamt. ===\n";
