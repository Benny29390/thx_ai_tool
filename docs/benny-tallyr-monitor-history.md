# Tallyr → KI-Tool: Site-Monitor History-Export reparieren

**Empfänger:** Benny
**Status:** Site-Monitor-Migration läuft, aber History-Export aus Tallyr greift nicht.
**Symptom:** `?tallyr_export=monitor&history=1&days=30` lädt nur die ~50 KB große Monitors+Clients-JSON herunter — **Logs und Incidents fehlen komplett**. Dateiname hat keinen `-history-Xd`-Suffix.

## Was vermutlich los ist

Die alte Snippet-Version (ohne `history`-Support) hängt noch in der Tallyr-`functions.php` und feuert vor der neuen Version. Sie matcht `?tallyr_export=monitor` und beendet die Anfrage mit `exit;`, bevor die neue Version dran kommt.

Plus möglicherweise: WordPress-Page-Cache / OPcache cached die alte Response.

## Was Du tun musst

### 1) Alte Snippet-Version entfernen (oder ersetzen)

Im **Tallyr-Childtheme** (`kreation-tallyr/functions.php` oder einer dort eingebundenen `inc/*.php`) den **alten** Block suchen, der so anfängt:

```php
add_action('init', function () {
    if (($_GET['tallyr_export'] ?? '') !== 'monitor') return;
    // ...
});
```

→ **komplett löschen**.

### 2) Neues Snippet v2 einsetzen

Folgenden Block in `functions.php` einfügen (oder als `inc/export-monitor-v2.php` und per `require_once` einbinden):

```php
<?php // === Site-Monitor-Export v2 für KI-Tool — in functions.php pasten ===
add_action('init', function () {
    if (($_GET['tallyr_monitor_export'] ?? '') !== 'v2') return;
    header('X-Tallyr-Export-Version: v2');
    if (($_GET['key'] ?? '') !== get_option('tallyr_monitor_cron_key')) {
        status_header(403); echo 'Forbidden — Snippet v2 aktiv, aber Key falsch.'; exit;
    }
    @ini_set('memory_limit', '1024M'); @set_time_limit(180);
    global $wpdb; $p = $wpdb->prefix; $name = 'tallyr-monitors-' . date('Y-m-d');
    $data = [
        'export_version' => '2.0',
        'snippet_version' => 'v2',
        'exported_at'    => date('c'),
        'monitors'       => $wpdb->get_results("SELECT * FROM {$p}tallyr_monitors", ARRAY_A),
        'clients'        => $wpdb->get_results("SELECT id, title, shortdesc FROM {$p}tallyr_clients WHERE state = 1", ARRAY_A),
    ];
    if (!empty($_GET['history'])) {
        $days = max(1, min(365, (int)($_GET['days'] ?? 30)));
        $name .= "-history-{$days}d";
        $data['incidents'] = $wpdb->get_results("SELECT i.*, m.url AS monitor_url FROM {$p}tallyr_monitor_incidents i JOIN {$p}tallyr_monitors m ON m.id = i.monitor_id", ARRAY_A);
        $data['logs']      = $wpdb->get_results($wpdb->prepare("SELECT l.monitor_id, l.checked_url, l.status_code, l.response_time_ms, l.is_up, l.checked_at, m.url AS monitor_url FROM {$p}tallyr_monitor_log l JOIN {$p}tallyr_monitors m ON m.id = l.monitor_id WHERE l.checked_at >= DATE_SUB(NOW(), INTERVAL %d DAY) ORDER BY l.checked_at ASC", $days), ARRAY_A);
        $data['stats']     = ['log_count' => count($data['logs']), 'incident_count' => count($data['incidents'])];
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '.json"');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); exit;
}, 1); // Priority 1 = feuert vor allen anderen init-Hooks
```

**Drei wichtige Unterschiede zur alten Version:**

| | Alt | v2 |
|---|---|---|
| URL-Pfad | `?tallyr_export=monitor` | **`?tallyr_monitor_export=v2`** |
| Action-Priority | 10 (default) | **1** (feuert zuerst) |
| Memory + Timeout | Default (oft 128 MB / 30 s) | **1024 MB / 180 s** |

### 3) Caches leeren

- **WP-Cache:** falls W3 Total Cache / WP Rocket / WP Super Cache aktiv → komplett purgen
- **OPcache:** im Server-Terminal `sudo systemctl reload php8.x-fpm` (oder per Tallyr-Admin „OPcache flush")
- **Browser:** Strg+F5 / Cmd+Shift+R

### 4) Verifizieren

Im Browser aufrufen:

```
https://tallyr.de/?tallyr_monitor_export=v2&key=laNeFwgzuEfmGOts2Mg42ntR5O40OvHY
```

**Erwartet:**
- Download mit Dateiname `tallyr-monitors-YYYY-MM-DD.json`
- In der JSON drin steht `"snippet_version": "v2"` ← **das ist der Beweis dass v2 läuft**
- Größe ~50 KB

Wenn das passt:

```
https://tallyr.de/?tallyr_monitor_export=v2&key=laNeFwgzuEfmGOts2Mg42ntR5O40OvHY&history=1&days=7
```

**Erwartet:**
- Download mit Dateiname `tallyr-monitors-YYYY-MM-DD-history-7d.json`
- Größe ~15 MB
- In der JSON drin: `logs` (Array mit ~70k Einträgen), `incidents` (Array), `stats` (Counts)

### 5) Datei an Thomas weitergeben

Die JSON-Datei downloaden und Thomas zukommen lassen. Er lädt sie unter `/admin/site-monitor` → „Tallyr-JSON" hoch — der History-Import läuft dann automatisch im KI-Tool.

## Falls v2 NICHT funktioniert

### A) Header prüfen
Im Browser-DevTools (Netzwerk-Tab) sollte beim Aufruf der v2-URL der Response-Header `X-Tallyr-Export-Version: v2` stehen. Wenn nicht → das Snippet ist nicht aktiv (Cache, falscher Theme-Ordner, Syntax-Error).

### B) Plan B — MySQL-Dump direkt
Wenn der WordPress-Weg sich weigert, kann Thomas mit einem direkten MySQL-Dump arbeiten. Per phpMyAdmin oder Konsole im Tallyr-Server:

```sql
-- Logs der letzten 30 Tage (als CSV)
SELECT l.monitor_id, l.checked_url, l.status_code, l.response_time_ms, l.is_up, l.checked_at, m.url AS monitor_url
FROM wp_tallyr_monitor_log l
JOIN wp_tallyr_monitors m ON m.id = l.monitor_id
WHERE l.checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
INTO OUTFILE '/tmp/tallyr_logs.csv'
FIELDS TERMINATED BY ';' OPTIONALLY ENCLOSED BY '"';

-- Incidents (komplett)
SELECT i.*, m.url AS monitor_url
FROM wp_tallyr_monitor_incidents i
JOIN wp_tallyr_monitors m ON m.id = i.monitor_id
INTO OUTFILE '/tmp/tallyr_incidents.csv'
FIELDS TERMINATED BY ';' OPTIONALLY ENCLOSED BY '"';
```

Die beiden CSVs an Thomas — er macht den Rest.

### C) Tabellen-Präfix prüfen
Falls Tallyr nicht `wp_` als DB-Präfix nutzt, in der Snippet-Datei `$wpdb->prefix` ersetzen durch den tatsächlichen Präfix (z.B. `wp_tallyr_`).

## Hintergrund (zur Info)

Thomas migriert das gesamte Site-Monitor-Modul von Tallyr ins KI-Tool. Monitore + Kunden-Zuordnung sind schon komplett drüben (35 Websites laufen schon im 2-Min-Cron). Was noch fehlt: die Verlaufs-Historie für die Detail-Ansicht pro Website (Uptime-Tagesbalken, Response-Time-Verlauf, Incidents-Liste). Dafür brauchen wir die `wp_tallyr_monitor_log` + `wp_tallyr_monitor_incidents` aus Tallyr.

**Eilig?** Nein — die Site-Monitor-Cron läuft schon, ab heute werden neue Daten gesammelt. Die historischen Daten wären „nur" für die schöne Detail-Anzeige rückwirkend. Wenn Du erst nach dem Wochenende dazu kommst, ist das in Ordnung.

Bei Rückfragen → Thomas direkt fragen.
