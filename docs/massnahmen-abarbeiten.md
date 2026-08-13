# Runbook: Maßnahmen abarbeiten

Diese Datei beschreibt, wie eine (auch frische, kontextlose) Claude-Code-Session die
offenen Produkt-Maßnahmen (To-dos aus Nutzer-Feedback) systematisch abarbeitet.

## Auslöser (lies das zuerst)
Wurdest Du mit einem Verweis auf diese Datei gestartet (z. B. `Attacke /var/www/docs/massnahmen-abarbeiten.md`,
`folge .../massnahmen-abarbeiten.md` oder nur dem Pfad): Das ist der Auftrag. Lies diese Datei KOMPLETT
und arbeite die offenen Maßnahmen exakt nach der unten beschriebenen Logik ab. Beginne damit, die offene
Liste mit kurzer Reihenfolge zu zeigen, dann eine Maßnahme nach der anderen. Keine Rückfrage nötig, um zu
starten (nur bei unklaren/riskanten Einzel-Maßnahmen rückfragen, siehe Ablauf).

## Kontext
- App unter `/var/www`: eigenes PHP ohne Framework, MySQL, Apache, Vanilla-JS/Alpine, kein Build-Step. Domain `ai.thoxan-dev.de`.
- Zuerst `/var/www/CLAUDE.md` lesen (Architektur + Konventionen). Stil strikt einhalten: Deutsch, echte Umlaute (ä ö ü ß), keine Gedankenstriche, Höflichkeitsformen (Du, Dein) gross.
- Die Maßnahmen liegen in der MySQL-Tabelle `feedback_measures`. Diese Session IST der Bearbeiter, es gibt keinen Übergabe-Schritt.

## Datenmodell
- `feedback_measures`: `id, title, description` (enthaelt die naechsten Schritte), `area`, `status` ENUM(`offen,in_arbeit,erledigt,verworfen`), `priority` ENUM(`hoch,mittel,niedrig`), `source` (`ki|manuell`).
- `feedback_measure_links` (`measure_id, feedback_id`) -> `internal_feedback` (`title, description, page_url`) liefert den Ursprung/Kontext je Maßnahme.
- `feedback_media` (`feedback_id, media_type` [`screenshot|video`], `media_path`) = Anhaenge je Ursprungs-Feedback. **Screenshots sind oft mit Pfeil/Rechteck/Stift markiert und zeigen das Problem direkt.** Datei-Pfad zum Anschauen = `/var/www` + `media_path` (z. B. `/var/www/uploads/feedback/feedback_...png`). Du kannst diese Bilder mit dem Read-Tool oeffnen und SEHEN.

## DB-Zugriff (Bootstrap fuer `php -r` oder ein Skript)
```php
require "/var/www/config/constants.php";
spl_autoload_register(function($c){foreach(["Core\\"=>"core/","Services\\"=>"services/","Models\\"=>"models/"] as $n=>$d){if(strpos($c,$n)===0){$f=ROOT_PATH."/".$d.str_replace("\\","/",substr($c,strlen($n))).".php";if(file_exists($f)){require $f;return;}}}});
$cfg=require CONFIG_PATH."/config.php"; \Core\Database::getInstance($cfg["db"]); $db=\Core\Database::getInstance();
// Lesen:  $db->query("SELECT ...", [...])
// Aendern: $db->execute("UPDATE feedback_measures SET status=? WHERE id=?", ["in_arbeit", $id]);
```

## Ablauf (genau diese Logik)
1. **Offene Maßnahmen holen**, sortiert nach Prioritaet, dann aelteste zuerst:
   ```sql
   SELECT * FROM feedback_measures
   WHERE status IN ('offen','in_arbeit')
   ORDER BY FIELD(priority,'hoch','mittel','niedrig'), id;
   ```
   Zu jeder Maßnahme die verknuepften Feedbacks UND deren Screenshots laden (Kontext):
   ```sql
   SELECT f.id, f.title, f.description, f.page_url,
          GROUP_CONCAT(CASE WHEN fm.media_type='screenshot' THEN fm.media_path END) AS screenshots
   FROM feedback_measure_links l
   JOIN internal_feedback f ON f.id = l.feedback_id
   LEFT JOIN feedback_media fm ON fm.feedback_id = f.id
   WHERE l.measure_id = ?
   GROUP BY f.id;
   ```
   (Aeltere Feedbacks koennen den Screenshot stattdessen in `internal_feedback.media_path` haben.)
2. **Liste + kurze Reihenfolge/Einschaetzung** zeigen. Dann **eine Maßnahme nach der anderen**.
3. **Pro Maßnahme:**
   a. Status auf `in_arbeit` setzen.
   b. `title` + `description` (naechste Schritte) + verknuepfte Feedbacks lesen, um den Auftrag zu verstehen.
      **Gibt es zu einem verknuepften Feedback Screenshots, oeffne die Bilddatei(en) mit dem Read-Tool** (`/var/www` + `media_path`) und schau sie an: Die Markierungen (Pfeil/Rechteck) zeigen oft genau die Stelle. Dann bei Bedarf im Code nachsehen, wo das Problem sitzt.
   c. **Einordnen:**
      - **Klare Code-/Konfig-Aufgabe** -> umsetzen (Konventionen aus CLAUDE.md), `php -l` auf jede geaenderte PHP-Datei, bei View/PHP-Aenderungen OPcache leeren:
        ```bash
        echo '<?php opcache_reset();' > /var/www/assets/_oc.php && curl -s https://ai.thoxan-dev.de/assets/_oc.php && rm -f /var/www/assets/_oc.php
        ```
        soweit moeglich verifizieren (Lint, Render-Test via `ob_start()`/`include`, CLI-Test, `node --check` fuer extrahiertes Inline-JS). **Eingeloggte Seiten lassen sich wegen Pflicht-2FA nicht selbst im Browser testen** -> klar benennen, was ungetestet bleibt.
      - **Unklar / Designentscheidung / Prozess / externe Abhaengigkeit / riskant** -> NICHT raten. Status zurueck auf `offen`, einen Vermerk `RÜCKFRAGE: ...` an die `description` anhaengen und die Frage an den Nutzer stellen. NICHT auf `erledigt` setzen.
   d. Kurzen Vermerk an `description` haengen: `\n\n[erledigt JJJJ-MM-TT] <was gemacht, welche Dateien>`.
   e. **Erst wenn wirklich umgesetzt UND verifiziert:** Status auf `erledigt`.
4. **Nach jeder Maßnahme** ein bis zwei Saetze Bericht (was, welche Dateien, getestet/ungetestet).
5. **Am Ende** Zusammenfassung: erledigt / offen geblieben (mit Grund) / Rueckfragen.

## Sicherheit
- Nichts loeschen, keine fremden Bereiche anfassen, im Zweifel fragen.
- Status nie auf `erledigt` ohne Verifikation.
- Keine destruktiven DB-Operationen ausser dem Status-Update / Description-Vermerk der jeweiligen Maßnahme.
- Keine Aktionen nach aussen (Mails, Deploys) ohne ausdrueckliche Freigabe.
