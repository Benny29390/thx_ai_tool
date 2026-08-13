<?php
namespace Services;

/**
 * Minimalistischer XLSX-Writer ohne externe Library.
 * Erzeugt eine .xlsx-Datei mit einem Sheet, Header-Zeile, Datenzeilen
 * und einer optionalen Statistik-Spalte rechts daneben.
 *
 * Eingeschränkter Funktionsumfang:
 * - Ein Sheet ("Daten")
 * - Header in Zeile 1 mit hellgrauer Hintergrundfarbe
 * - Alle Zell-Werte als String (Excel erkennt Zahlen automatisch)
 * - AutoFilter auf erster Zeile
 * - Reicht für Kunden-Reports im BKK/SMV-Layout.
 */
class XlsxWriter
{
    /**
     * Schreibt eine .xlsx-Datei.
     * @param array $header Spalten-Namen
     * @param array $zeilen Array von Arrays (Werte pro Zeile)
     * @param array $statistik Optionale Liste von Zeilen [['label' => '…', 'wert' => …], …]
     */
    public static function schreibe(string $pfad, array $header, array $zeilen, array $statistik = []): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($pfad, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Kann XLSX-Datei nicht schreiben: ' . $pfad);
        }

        // Shared Strings sammeln
        $stringPool = [];
        $stringIndex = [];
        $addString = function ($s) use (&$stringPool, &$stringIndex) {
            $s = (string)$s;
            if (!isset($stringIndex[$s])) {
                $stringIndex[$s] = count($stringPool);
                $stringPool[] = $s;
            }
            return $stringIndex[$s];
        };

        // Sheet-XML aufbauen
        $rows = [];
        $rowIdx = 1;

        // Header
        $cells = [];
        foreach ($header as $i => $h) {
            $ref = self::colLetter($i) . $rowIdx;
            $cells[] = '<c r="' . $ref . '" t="s" s="1"><v>' . $addString($h) . '</v></c>';
        }
        $rows[] = '<row r="' . $rowIdx . '">' . implode('', $cells) . '</row>';
        $rowIdx++;

        // Datenzeilen
        foreach ($zeilen as $zeile) {
            $cells = [];
            foreach ($zeile as $i => $wert) {
                $ref = self::colLetter($i) . $rowIdx;
                if ($wert === null || $wert === '') continue;
                // Zahl?
                if (is_numeric($wert) && !is_string($wert)) {
                    $cells[] = '<c r="' . $ref . '"><v>' . $wert . '</v></c>';
                } else {
                    $cells[] = '<c r="' . $ref . '" t="s"><v>' . $addString((string)$wert) . '</v></c>';
                }
            }
            $rows[] = '<row r="' . $rowIdx . '">' . implode('', $cells) . '</row>';
            $rowIdx++;
        }

        // Statistik-Block rechts (ab Spalte nach den Datenspalten + 1 Lücke)
        $statSpalte = count($header) + 2;
        if (!empty($statistik)) {
            $statRowIdx = 1;
            $cells = [
                '<c r="' . self::colLetter($statSpalte - 1) . $statRowIdx . '" t="s" s="1"><v>' . $addString('Kennzahl') . '</v></c>',
                '<c r="' . self::colLetter($statSpalte)     . $statRowIdx . '" t="s" s="1"><v>' . $addString('Wert') . '</v></c>',
            ];
            // In bestehende erste Zeile einbauen geht nicht ohne Re-Iteration — wir bauen die Stat als separate Zeilen
            $statRows = [];
            $statRows[] = '<row r="' . $statRowIdx . '">' . implode('', $cells) . '</row>';
            $statRowIdx++;
            foreach ($statistik as $s) {
                $rowCells = [
                    '<c r="' . self::colLetter($statSpalte - 1) . $statRowIdx . '" t="s"><v>' . $addString((string)$s['label']) . '</v></c>',
                ];
                $w = $s['wert'] ?? '';
                if (is_numeric($w) && !is_string($w)) {
                    $rowCells[] = '<c r="' . self::colLetter($statSpalte) . $statRowIdx . '"><v>' . $w . '</v></c>';
                } else {
                    $rowCells[] = '<c r="' . self::colLetter($statSpalte) . $statRowIdx . '" t="s"><v>' . $addString((string)$w) . '</v></c>';
                }
                $statRows[] = '<row r="' . $statRowIdx . '">' . implode('', $rowCells) . '</row>';
                $statRowIdx++;
            }
            // Statistik-Zeilen vorne ranhängen war einfacher: wir mergen
            // Wir merge alle Zeilen via Zeilen-Index in einem Array
            $merged = [];
            foreach ($rows as $r) {
                if (preg_match('/^<row r="(\d+)"/', $r, $m)) $merged[(int)$m[1]] = $r;
            }
            foreach ($statRows as $r) {
                if (preg_match('/^<row r="(\d+)"/', $r, $m)) {
                    $rNum = (int)$m[1];
                    // statistik schreibt rechts in dieselben Zeilen — bei Konflikt: append children
                    if (isset($merged[$rNum])) {
                        // Cells aus statRow rauslösen und in merged einbauen
                        if (preg_match('/<row[^>]*>(.*)<\/row>/s', $r, $cm)) {
                            $merged[$rNum] = preg_replace('/<\/row>$/', $cm[1] . '</row>', $merged[$rNum]);
                        }
                    } else {
                        $merged[$rNum] = $r;
                    }
                }
            }
            ksort($merged);
            $rows = array_values($merged);
        }

        // AutoFilter-Range
        $maxCol = max(count($header), 1) + (!empty($statistik) ? 2 : 0);
        $maxRow = $rowIdx - 1;
        $autoFilterRef = 'A1:' . self::colLetter($maxCol - 1) . $maxRow;

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                  . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                  . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                  . '<sheetData>' . implode('', $rows) . '</sheetData>'
                  . '<autoFilter ref="' . $autoFilterRef . '"/>'
                  . '</worksheet>';

        // Shared Strings XML
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
               . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($stringPool) . '" uniqueCount="' . count($stringPool) . '">';
        foreach ($stringPool as $s) {
            $ssXml .= '<si><t xml:space="preserve">' . self::xmlEscape($s) . '</t></si>';
        }
        $ssXml .= '</sst>';

        // Styles: Style 0 = default, Style 1 = bold mit grauem Hintergrund (Header)
        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                   . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                   . '<fonts count="2">'
                   . '<font><sz val="10"/><name val="Arial"/></font>'
                   . '<font><b/><sz val="10"/><name val="Arial"/></font>'
                   . '</fonts>'
                   . '<fills count="3">'
                   . '<fill><patternFill patternType="none"/></fill>'
                   . '<fill><patternFill patternType="gray125"/></fill>'
                   . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/></patternFill></fill>'
                   . '</fills>'
                   . '<borders count="1"><border/></borders>'
                   . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                   . '<cellXfs count="2">'
                   . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                   . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"/>'
                   . '</cellXfs>'
                   . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                   . '</styleSheet>';

        // Boilerplate
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                      . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                      . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                      . '<Default Extension="xml" ContentType="application/xml"/>'
                      . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                      . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                      . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
                      . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                      . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
              . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
              . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
              . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                  . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                  .   'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                  . '<sheets><sheet name="Daten" sheetId="1" r:id="rId1"/></sheets>'
                  . '</workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                      . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
                      . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                      . '</Relationships>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);
        $zip->addFromString('xl/styles.xml', $stylesXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();
    }

    private static function colLetter(int $idx): string
    {
        // 0 → A, 25 → Z, 26 → AA, …
        $letters = '';
        $idx++;
        while ($idx > 0) {
            $rest = ($idx - 1) % 26;
            $letters = chr(65 + $rest) . $letters;
            $idx = (int)(($idx - 1) / 26);
        }
        return $letters;
    }

    private static function xmlEscape(string $s): string
    {
        // Steuerzeichen entfernen (außer \t \n \r), die Excel nicht akzeptiert
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
