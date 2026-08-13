<?php
namespace Services;

/**
 * Minimalistischer XLSX-Reader ohne externe Library.
 * Liest Sheet 1 als Array von Zeilen (jede Zeile = Array der Zell-Werte).
 *
 * Eingeschränkter Funktionsumfang:
 * - Nur erstes Sheet (Sheet1)
 * - Shared-Strings werden aufgelöst
 * - Formeln werden ausgewertet zu ihrem cached value (<v>-Element)
 * - Datums-Zellen kommen als Excel-Datums-Serial (z.B. 45000) zurück; bei
 *   numFmt mit "yyyy"/"dd"/"mm" wird automatisch in ISO-Datum konvertiert
 *
 * Reicht für Historien-Importe von BKK/SMV-Excels.
 */
class XlsxReader
{
    public static function leseZeilen(string $pfad, int $maxZeilen = 5000): array
    {
        if (!file_exists($pfad)) throw new \RuntimeException('Datei nicht gefunden.');
        $zip = new \ZipArchive();
        if ($zip->open($pfad) !== true) throw new \RuntimeException('Datei ist kein gültiges XLSX (ZIP-Fehler).');

        // Shared Strings laden
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = @simplexml_load_string($ssXml);
            if ($ss) {
                foreach ($ss->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string)$si->t;
                    } else {
                        // Rich-Text: alle <r><t>...</t></r> konkatenieren
                        foreach ($si->r as $r) $text .= (string)$r->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        // Styles für Datums-Erkennung (optional)
        $datumsStyles = [];
        $stXml = $zip->getFromName('xl/styles.xml');
        if ($stXml) {
            $st = @simplexml_load_string($stXml);
            if ($st && isset($st->cellXfs->xf)) {
                $i = 0;
                foreach ($st->cellXfs->xf as $xf) {
                    $numFmt = (int)$xf['numFmtId'];
                    // Standard-Datums-Formate in Excel
                    if (in_array($numFmt, [14, 15, 16, 17, 22], true)) {
                        $datumsStyles[$i] = true;
                    }
                    $i++;
                }
            }
        }

        // Erstes Sheet finden
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) {
            $zip->close();
            throw new \RuntimeException('Sheet1 nicht gefunden.');
        }
        $zip->close();

        $sheet = @simplexml_load_string($sheetXml);
        if (!$sheet) throw new \RuntimeException('Sheet1 nicht parsbar.');

        $zeilen = [];
        $zeilenZahl = 0;
        foreach ($sheet->sheetData->row as $row) {
            if ($zeilenZahl >= $maxZeilen) break;
            $zellen = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $col = self::spaltenIndex($ref);
                $typ = (string)$c['t'];
                $style = (int)$c['s'];
                $wert = (string)$c->v;
                if ($typ === 's') {
                    $idx = (int)$wert;
                    $wert = $sharedStrings[$idx] ?? '';
                } elseif ($typ === 'inlineStr') {
                    $wert = isset($c->is->t) ? (string)$c->is->t : '';
                } elseif ($typ === 'b') {
                    $wert = $wert ? '1' : '0';
                } elseif (isset($datumsStyles[$style]) && is_numeric($wert)) {
                    // Excel-Datums-Serial → ISO
                    $wert = self::excelDatumZuIso((float)$wert);
                }
                $zellen[$col] = $wert;
            }
            // Lücken auffüllen + zu indizierter Liste
            $maxCol = $zellen ? max(array_keys($zellen)) : -1;
            $zeile = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $zeile[] = $zellen[$i] ?? '';
            }
            // Leere Zeilen überspringen
            if (array_filter($zeile, fn($v) => trim((string)$v) !== '')) {
                $zeilen[] = $zeile;
                $zeilenZahl++;
            }
        }
        return $zeilen;
    }

    private static function spaltenIndex(string $ref): int
    {
        // "A1" → 0, "B1" → 1, "AA1" → 26, …
        $col = 0;
        for ($i = 0; $i < strlen($ref); $i++) {
            $c = $ref[$i];
            if ($c < 'A' || $c > 'Z') break;
            $col = $col * 26 + (ord($c) - ord('A') + 1);
        }
        return $col - 1;
    }

    private static function excelDatumZuIso(float $serial): string
    {
        // Excel-Epoche: 1900-01-01 (mit Schaltjahr-Bug)
        $offset = ($serial >= 60) ? 25569 : 25568;
        $ts = ($serial - $offset) * 86400;
        return gmdate('Y-m-d', (int)$ts);
    }
}
