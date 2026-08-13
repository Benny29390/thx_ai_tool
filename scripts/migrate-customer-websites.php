<?php
/**
 * Einmalige, idempotente Migration: Kunden-Websites werden zur einzigen Quelle
 * in pm_monitors zusammengeführt.
 *   - customers.website (Hauptseite) + settings.domains -> pm_monitors
 *   - www/Schema/Slash-Dedup (keine Doppel-Eintraege)
 *   - is_primary markiert die Hauptseite, customers.website spiegelt sie
 *   - settings.domains wird danach entfernt
 * Monitoring-Historie und Website-Crawls (knowledge_documents) bleiben unangetastet.
 *
 * Aufruf:  php scripts/migrate-customer-websites.php
 */
require __DIR__ . '/../config/constants.php';
spl_autoload_register(function ($c) {
    foreach (['Core\\' => 'core/', 'Services\\' => 'services/'] as $p => $d) {
        if (strpos($c, $p) === 0) { $f = dirname(__DIR__) . '/' . $d . str_replace('\\', '/', substr($c, strlen($p))) . '.php'; if (is_file($f)) { require $f; return; } }
    }
});
$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();

try { $db->execute("ALTER TABLE pm_monitors ADD COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_id"); echo "is_primary ergänzt\n"; } catch (\Exception $e) { echo "is_primary: vorhanden\n"; }

$normHost = function ($u) {
    $u = strtolower(trim((string)$u));
    $u = preg_replace('#^https?://#', '', $u);
    $u = preg_replace('#^www\.#', '', $u);
    return rtrim((string)$u, '/');
};

$created = 0; $domainsMig = 0; $cleaned = 0; $deleted = 0; $synced = 0;
$customers = $db->query("SELECT id, name, abbreviation, website, settings FROM customers") ?: [];
foreach ($customers as $c) {
    $cid = (int)$c['id'];
    $mons = $db->query("SELECT id, url FROM pm_monitors WHERE customer_id = ?", [$cid]) ?: [];
    $byUrl = [];
    foreach ($mons as $m) $byUrl[$normHost($m['url'])] = (int)$m['id'];
    $label = $c['abbreviation'] ?: ($c['name'] ?: 'Website');

    $web = trim((string)$c['website']);
    if ($web !== '') {
        $k = $normHost($web);
        if (isset($byUrl[$k])) { $db->execute("UPDATE pm_monitors SET is_primary = 1 WHERE id = ?", [$byUrl[$k]]); }
        else { $byUrl[$k] = (int)$db->insert('pm_monitors', ['customer_id' => $cid, 'url' => $web, 'label' => $label, 'status' => 'up', 'is_primary' => 1, 'category' => 'Kunde', 'created_by' => 0]); $created++; }
    }

    $s = json_decode($c['settings'] ?? '{}', true) ?: [];
    foreach (($s['domains'] ?? []) as $d) {
        $u = trim((string)($d['url'] ?? '')); if ($u === '') continue;
        $k = $normHost($u);
        if (!isset($byUrl[$k])) { $byUrl[$k] = (int)$db->insert('pm_monitors', ['customer_id' => $cid, 'url' => $u, 'label' => trim((string)($d['label'] ?? '')) ?: $label, 'status' => 'up', 'is_primary' => 0, 'category' => 'Kunde', 'created_by' => 0]); $created++; }
        $domainsMig++;
    }
    if (isset($s['domains'])) { unset($s['domains']); $db->execute("UPDATE customers SET settings = ? WHERE id = ?", [json_encode($s), $cid]); $cleaned++; }
}

// www-Dedup + genau ein Primär + customers.website spiegeln
$cids = array_column($db->query("SELECT DISTINCT customer_id FROM pm_monitors WHERE customer_id IS NOT NULL") ?: [], 'customer_id');
foreach ($cids as $cid) {
    $cid = (int)$cid;
    $rows = $db->query("SELECT id, url, is_primary, last_check, (SELECT COUNT(*) FROM pm_monitor_log WHERE monitor_id = pm_monitors.id) AS logs FROM pm_monitors WHERE customer_id = ?", [$cid]) ?: [];
    $groups = [];
    foreach ($rows as $m) $groups[$normHost($m['url'])][] = $m;
    foreach ($groups as $g) {
        if (count($g) < 2) continue;
        usort($g, function ($a, $b) { $ah = ((int)$a['logs'] > 0 || $a['last_check']); $bh = ((int)$b['logs'] > 0 || $b['last_check']); if ($ah != $bh) return $bh - $ah; return $a['id'] - $b['id']; });
        $primaryAny = false; foreach ($g as $m) if ((int)$m['is_primary'] === 1) $primaryAny = true;
        for ($i = 1; $i < count($g); $i++) { if ((int)$g[$i]['logs'] === 0 && !$g[$i]['last_check']) { $db->execute("DELETE FROM pm_monitors WHERE id = ?", [$g[$i]['id']]); $deleted++; } }
        if ($primaryAny) $db->execute("UPDATE pm_monitors SET is_primary = 1 WHERE id = ?", [$g[0]['id']]);
    }
    $prims = $db->query("SELECT id FROM pm_monitors WHERE customer_id = ? AND is_primary = 1 ORDER BY (SELECT COUNT(*) FROM pm_monitor_log WHERE monitor_id = pm_monitors.id) DESC, id ASC", [$cid]) ?: [];
    for ($i = 1; $i < count($prims); $i++) $db->execute("UPDATE pm_monitors SET is_primary = 0 WHERE id = ?", [$prims[$i]['id']]);
    $p = $db->queryOne("SELECT url FROM pm_monitors WHERE customer_id = ? AND is_primary = 1 LIMIT 1", [$cid]);
    if ($p) {
        $db->execute("UPDATE customers SET website = ? WHERE id = ? AND (website IS NULL OR website <> ?)", [$p['url'], $cid, $p['url']]);
        $synced++;
        // Crawl-Start-URL an die Hauptseite angleichen (nur gleicher Host)
        $row = $db->queryOne("SELECT settings FROM customers WHERE id = ?", [$cid]);
        $s = json_decode($row['settings'] ?? '{}', true) ?: [];
        if (isset($s['website_crawl'])) {
            $su = trim((string)($s['website_crawl']['start_url'] ?? ''));
            if ($su !== '' && $su !== $p['url'] && $normHost($su) === $normHost($p['url'])) {
                $s['website_crawl']['start_url'] = $p['url'];
                $db->execute("UPDATE customers SET settings = ? WHERE id = ?", [json_encode($s), $cid]);
            }
        }
    }
}

echo "Fertig. Neu: $created, Domains migriert: $domainsMig, settings bereinigt: $cleaned, Dubletten entfernt: $deleted, website gespiegelt: $synced\n";
