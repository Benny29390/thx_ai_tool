# Initialprompt für den SSH-Chat (KI-Tool, /var/www)

Diesen Text als ersten Prompt in den Claude-Code-Chat auf dem Server geben.
Pfad zum Ordner ggf. anpassen, je nachdem wohin Du die Dateien geschoben hast.

---

Im Ordner `docs/migration-kontext/` liegt der vollständige Anforderungskontext aus dem
früheren lokalen LAM-System (LAMS), das wir gerade hier ins KI-Tool migrieren. Es sind
ausschließlich meine eigenen Prompts aus den damaligen Bau-Chats, chronologisch in acht
nummerierte Dateien (01 bis 08) gegliedert, jeweils in Sinnabschnitte mit `## ▸`.

So gehst Du vor:

1. Lies zuerst `docs/migration-kontext/00-uebersicht.md` vollständig und halte Dich an die
   dort beschriebene Arbeitsweise.
2. Arbeite die Dateien **in der Reihenfolge 01 bis 08** ab, innerhalb jeder Datei
   **Sinnabschnitt für Sinnabschnitt** (`## ▸`), nicht Prompt für Prompt.
3. Pro Sinnabschnitt:
   - Zieh die **Anforderung / Entscheidung** heraus. Ich prompte iterativ mit Korrekturen,
     es gilt der **letzte** Stand im Abschnitt.
   - Gleich mit dem **tatsächlichen Ist-Stand hier im KI-Tool** ab (Code unter `/var/www`,
     migrierte LAM-Bereiche, vor allem Linkprofil und Linkquellen-Pool).
   - Dokumentiere **offene oder abweichende Punkte** in einer Sammeldatei
     `docs/migration-kontext/ABGLEICH.md` nach der Vorlage aus der Übersicht
     (Datei/Abschnitt, Anforderung, Ist im KI-Tool inkl. Fundstelle, Abweichung/offen,
     Priorität).
4. **Setze in dieser Phase noch nichts um.** Erst prüfen und dokumentieren.
5. Wenn Du eine ganze Datei durch hast, gib mir eine kurze Zwischenmeldung (was geprüft,
   wie viele Abweichungen) und mach dann mit der nächsten weiter.
6. Wenn alle acht Dateien durch sind, erstelle aus `ABGLEICH.md` einen **priorisierten
   Umsetzungsplan** (Optik/Corporate Design, Workflow, fehlende Funktionen getrennt) und
   leg ihn mir vor, bevor Du mit der Umsetzung beginnst.

Schwerpunkt für mich: der gestalterische Gap (Header/Navigation, Tabellen, Corporate
Design, siehe Datei 07 Styleguide und Datei 08) und dass der ursprüngliche Workflow wieder
stimmt. Halte Dich an die Stilvorgaben (Deutsch, keine Gedankenstriche, Höflichkeitsformen
groß).

Fang an mit `00-uebersicht.md` und danach `01-spec-zusammenfassung.md`.
