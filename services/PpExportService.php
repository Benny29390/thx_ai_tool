<?php
namespace Services;

use Core\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * PpExportService — Excel-Export von Projektplänen im Thoxan-Reporting-Layout.
 *
 * 2-spaltiger Aufbau (Beschreibung + Aufwand-Std.), Header-Block mit Kunde +
 * Plan-Titel + URL + Zeitraum, Sektions-Subtotals, Footer mit Gesamt + TS.
 * Identisch zum bisherigen Tallyr-Export (siehe docs/projektplanner-export/).
 */
class PpExportService
{
    private Database $db;

    public function __construct(Database $db) { $this->db = $db; }

    /** Liefert einen vollständigen .xlsx-Export für einen Plan. */
    public function exportPlan(int $planId): void
    {
        $plan = $this->db->queryOne(
            "SELECT p.*, c.name AS customer_name, c.abbreviation AS customer_abbr,
                    c.website AS customer_url
             FROM pp_plans p LEFT JOIN customers c ON c.id = p.customer_id
             WHERE p.id = ?", [$planId]
        );
        if (!$plan) {
            http_response_code(404); echo 'Plan nicht gefunden'; return;
        }
        $rows = $this->db->query(
            "SELECT * FROM pp_plan_rows WHERE plan_id = ? ORDER BY position ASC, id ASC",
            [$planId]
        ) ?: [];

        $kuerzelMap = $this->buildKuerzelMap();

        $spreadsheet = $this->buildPlanSpreadsheet($plan, $rows, $kuerzelMap);

        // Dateiname: KUERZEL-Reporting_YYYY-MM.xlsx (Kürzel + aktuelles Jahr-Monat)
        $abbr = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plan['customer_abbr'] ?: $plan['customer_name'] ?: 'Plan'));
        if ($abbr === '') $abbr = 'PLAN';
        $filename = $abbr . '-Reporting_' . date('Y-m') . '.xlsx';

        $this->emitXlsxHeaders($filename);
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /** Person-basierter Export: alle Aufgaben einer Person aus allen aktiven Plänen. */
    public function exportPerson(string $personName): void
    {
        $lower = mb_strtolower($personName);
        $rows = $this->db->query(
            "SELECT r.*, p.title AS plan_title, c.name AS customer_name, c.abbreviation AS customer_abbr
             FROM pp_plan_rows r
             JOIN pp_plans p ON p.id = r.plan_id AND p.state = 1
             LEFT JOIN customers c ON c.id = p.customer_id
             WHERE r.row_type = 'item'
               AND (LOWER(r.lead_responsible) = ? OR LOWER(r.responsible) LIKE ?)
             ORDER BY (c.name IS NULL), c.name ASC, p.id ASC, r.position ASC",
            [$lower, '%' . $lower . '%']
        ) ?: [];

        $filtered = [];
        foreach ($rows as $r) {
            $isLead = mb_strtolower((string) $r['lead_responsible']) === $lower;
            $names = array_map(fn($s) => mb_strtolower(trim($s)), explode(',', (string) $r['responsible']));
            if (!$isLead && !in_array($lower, $names, true)) continue;
            $r['_role'] = $isLead ? 'HV' : 'Umsetzung';
            $filtered[] = $r;
        }

        $spreadsheet = $this->buildPersonSpreadsheet($personName, $filtered);
        $filename = $this->sanitizeFilename('Aufgaben_' . $personName) . '.xlsx';
        $this->emitXlsxHeaders($filename);
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    // ============================================================
    // Plan-Spreadsheet (Reporting-Layout, 2 Spalten)
    // ============================================================

    private function buildPlanSpreadsheet(array $plan, array $rows, array $kuerzelMap): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $title = $plan['customer_abbr'] ?: $plan['customer_name'] ?: 'Report';
        $sheet->setTitle(substr($title, 0, 31));

        // Default-Font + Spaltenbreiten
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(57.5);
        $sheet->getColumnDimension('B')->setWidth(23);
        $sheet->getSheetView()->setZoomScale(150);

        $thinBorder = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $grayFill = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D8D8D8']],
        ];

        $r = 1;
        $periodLabel = '';
        if ($plan['period_from'] && $plan['period_to']) {
            $periodLabel = date('d.m.Y', strtotime($plan['period_from']))
                         . ' – '
                         . date('d.m.Y', strtotime($plan['period_to']));
        }

        // ----- Header-Block -----
        // R1: Kundenname (links, 16pt) | Plan-Titel (rechts, 12pt)
        $sheet->setCellValue("A{$r}", $plan['customer_name'] ?: '');
        $sheet->setCellValue("B{$r}", $plan['title'] ?: '');
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle("B{$r}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
        $r++;

        // R2: Kunden-URL (links, mit Hyperlink) | Zeitraum (rechts)
        $clientUrl = (string) ($plan['customer_url'] ?: '');
        $sheet->setCellValue("A{$r}", $clientUrl);
        $sheet->setCellValue("B{$r}", $periodLabel);
        $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$r}")->getAlignment()
            ->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
        if ($clientUrl !== '') {
            $firstUrl = trim(strtok($clientUrl, "\n"));
            if (preg_match('/^https?:\/\//', $firstUrl)) {
                $sheet->getCell("A{$r}")->getHyperlink()->setUrl($firstUrl);
                $sheet->getStyle("A{$r}")->getFont()->setColor(
                    new \PhpOffice\PhpSpreadsheet\Style\Color('FF0563C1')
                )->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE);
            }
        }
        $r++;

        // R3: leer (mit Border)
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
        $r++;

        // R4: leer | „Aufwand ca. in Std." (grau, fett, zentriert)
        $sheet->setCellValue("B{$r}", 'Aufwand ca. in Std.');
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($grayFill);
        $sheet->getStyle("B{$r}")->getFont()->setBold(true);
        $sheet->getStyle("B{$r}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $r++;

        // Freeze ab Zeile darunter
        $sheet->freezePane("A{$r}");

        // ----- Items -----
        $sectionOpen = false;
        $sectionHours = 0.0;
        $totalHours = 0.0;

        foreach ($rows as $row) {
            if ($row['row_type'] === 'spacer') continue;
            if ($row['row_type'] === 'item' && (int) $row['is_placeholder']) continue;

            if ($row['row_type'] === 'section') {
                if ($sectionOpen) {
                    $this->writeSubtotalRow($sheet, $r, $sectionHours, $thinBorder, $grayFill);
                    $r++;
                    // Trenn-Leerzeile
                    $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
                    $r++;
                }
                $sectionOpen = true;
                $sectionHours = 0.0;
                $sheet->setCellValue("A{$r}", $row['description'] ?: '');
                $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
                $sheet->getStyle("B{$r}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $r++;
                continue;
            }

            if ($row['row_type'] === 'note') {
                $noteText = (string) ($row['notes'] ?: $row['description'] ?: '');
                $sheet->setCellValue("A{$r}", $noteText);
                $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
                $sheet->getStyle("A{$r}")->getAlignment()
                    ->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("B{$r}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                if (preg_match('/^https?:\/\//', trim($noteText))) {
                    $sheet->getCell("A{$r}")->getHyperlink()->setUrl(trim($noteText));
                    $sheet->getStyle("A{$r}")->getFont()->setColor(
                        new \PhpOffice\PhpSpreadsheet\Style\Color('FF0563C1')
                    )->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE);
                }
                $r++;
                continue;
            }

            // type === 'item'
            $h = (float) $row['ist_hours'];
            $sectionHours += $h;
            $totalHours += $h;

            // Beschreibung + Suffix "(Zeitraum, KürzelLead, KürzelTeam…)"
            $desc = (string) ($row['description'] ?? '');
            $suffix = [];
            if (!empty($row['timeframe'])) $suffix[] = $row['timeframe'];
            $seenKz = [];
            $lead = trim((string) ($row['lead_responsible'] ?? ''));
            if ($lead !== '') {
                $kz = $this->resolveKuerzel($lead, $kuerzelMap);
                $seenKz[$kz] = true;
                $suffix[] = $kz;
            }
            if (!empty($row['responsible'])) {
                foreach (explode(',', (string) $row['responsible']) as $name) {
                    $name = trim($name);
                    if ($name === '') continue;
                    $kz = $this->resolveKuerzel($name, $kuerzelMap);
                    if (!isset($seenKz[$kz])) {
                        $seenKz[$kz] = true;
                        $suffix[] = $kz;
                    }
                }
            }
            if ($suffix) $desc .= ' (' . implode(', ', $suffix) . ')';

            $sheet->setCellValue("A{$r}", $desc);
            $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
            $sheet->getStyle("A{$r}")->getAlignment()
                ->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $this->writeHoursCell($sheet, $r, $h);
            $r++;
        }

        // Letzte Sektion schliessen
        if ($sectionOpen) {
            $this->writeSubtotalRow($sheet, $r, $sectionHours, $thinBorder, $grayFill);
            $r++;
        }

        // Trenn-Leerzeile
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
        $r++;

        // Gesamtsumme
        $sheet->setCellValue("A{$r}", 'Aufwand ca. insgesamt in Std.');
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($grayFill);
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("B{$r}")->getFont()->setBold(true);
        $this->writeHoursCell($sheet, $r, $totalHours);
        $r++;

        // Tagessätze (auf halbe TS abgerundet)
        $ts = $totalHours > 0 ? floor($totalHours / 4) * 0.5 : 0;
        $tsLabel = str_replace('.', ',', (string) $ts) . ' Tagessätze';
        $sheet->setCellValue("A{$r}", 'Aufwand in Tagessätzen');
        $sheet->setCellValue("B{$r}", $tsLabel);
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($grayFill);
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("B{$r}")->getFont()->setBold(true);
        $sheet->getStyle("B{$r}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $r += 2;

        // Kontakt-Footer
        $sheet->setCellValue(
            "A{$r}",
            'Noch Rückfragen? Kontakt zur Thoxan Communications GmbH unter https://www.thoxan.com/kontakt'
        );
        $sheet->getStyle("A{$r}")->getFont()->setItalic(true);

        // Alle Zeilen auf Auto-Hoehe
        for ($i = 1; $i <= $r; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(-1);
        }

        return $ss;
    }

    /** Subtotal-Zeile schreiben (rechts der Stundenwert). */
    private function writeSubtotalRow($sheet, int $r, float $hours, array $thinBorder, array $grayFill): void
    {
        $sheet->setCellValue("A{$r}", 'Aufwand ca. in Std.');
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($thinBorder);
        $sheet->getStyle("A{$r}:B{$r}")->applyFromArray($grayFill);
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $this->writeHoursCell($sheet, $r, $hours);
    }

    /** Stunden-Zelle in B: numerisch, zentriert, Format ohne ueberfluessige Nullen. */
    private function writeHoursCell($sheet, int $r, float $val): void
    {
        if ($val > 0) {
            $sheet->setCellValueExplicit("B{$r}", $val, DataType::TYPE_NUMERIC);
            if ($val == floor($val)) {
                $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode('0');
            } else {
                $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode('0.##');
            }
        }
        $sheet->getStyle("B{$r}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    // ============================================================
    // Personen-Spreadsheet
    // ============================================================

    private function buildPersonSpreadsheet(string $personName, array $rows): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle(substr($personName, 0, 31));
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $thinBorder = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $grayFill = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D8D8D8']],
        ];

        // Spaltenbreiten
        $widths = ['A' => 18, 'B' => 30, 'C' => 50, 'D' => 12, 'E' => 12, 'F' => 14, 'G' => 14, 'H' => 14];
        foreach ($widths as $col => $w) $sheet->getColumnDimension($col)->setWidth($w);

        $r = 1;
        $sheet->setCellValue("A{$r}", 'Aufgabenliste — ' . $personName);
        $sheet->mergeCells("A{$r}:H{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(16);
        $r += 2;

        $headers = ['Kunde', 'Plan', 'Aufgabe', 'Soll (h)', 'Ist (h)', 'Zeitraum', 'Termin', 'Rolle'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . $r, $h);
            $col++;
        }
        $sheet->getStyle("A{$r}:H{$r}")->applyFromArray($thinBorder);
        $sheet->getStyle("A{$r}:H{$r}")->applyFromArray($grayFill);
        $sheet->getStyle("A{$r}:H{$r}")->getFont()->setBold(true);
        $r++;

        $totSoll = 0; $totIst = 0;
        foreach ($rows as $row) {
            $cust = $row['customer_abbr'] ?: ($row['customer_name'] ?: '—');
            $soll = (float) $row['planned_hours'];
            $ist  = (float) $row['ist_hours'];
            $totSoll += $soll;
            $totIst += $ist;

            $sheet->setCellValue("A{$r}", $cust);
            $sheet->setCellValue("B{$r}", $row['plan_title'] ?? '');
            $sheet->setCellValue("C{$r}", ((int) $row['is_done'] ? '✓ ' : '') . ($row['description'] ?? ''));
            if ($soll > 0) $sheet->setCellValueExplicit("D{$r}", $soll, DataType::TYPE_NUMERIC);
            if ($ist > 0)  $sheet->setCellValueExplicit("E{$r}", $ist,  DataType::TYPE_NUMERIC);
            $sheet->setCellValue("F{$r}", $row['timeframe'] ?? '');
            $sheet->setCellValue("G{$r}", $row['deadline'] ?? '');
            $sheet->setCellValue("H{$r}", $row['_role'] ?? '');
            $sheet->getStyle("A{$r}:H{$r}")->applyFromArray($thinBorder);
            $sheet->getStyle("C{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("D{$r}:E{$r}")->getNumberFormat()->setFormatCode('0.##');
            $sheet->getStyle("D{$r}:E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            if ((int) $row['is_done']) {
                $sheet->getStyle("A{$r}:H{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D1FAE5');
            }
            $r++;
        }

        // Gesamtsumme
        $sheet->setCellValue("C{$r}", 'Gesamt');
        $sheet->getStyle("C{$r}")->getFont()->setBold(true);
        $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValueExplicit("D{$r}", $totSoll, DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("E{$r}", $totIst,  DataType::TYPE_NUMERIC);
        $sheet->getStyle("D{$r}:E{$r}")->getNumberFormat()->setFormatCode('0.##');
        $sheet->getStyle("D{$r}:E{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}:H{$r}")->applyFromArray($thinBorder);
        $sheet->getStyle("A{$r}:H{$r}")->applyFromArray($grayFill);

        for ($i = 1; $i <= $r; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(-1);
        }

        return $ss;
    }

    // ============================================================
    // Helfer
    // ============================================================

    /** Map Person-Name -> Kürzel aus pp_team_members. */
    private function buildKuerzelMap(): array
    {
        $map = [];
        $team = $this->db->query("SELECT name, abbreviation FROM pp_team_members WHERE abbreviation IS NOT NULL AND abbreviation <> ''") ?: [];
        foreach ($team as $t) {
            $map[trim($t['name'])] = $t['abbreviation'];
        }
        return $map;
    }

    private function resolveKuerzel(string $name, array $map): string
    {
        $name = trim($name);
        if (isset($map[$name])) return $map[$name];
        // Fallback: erste 3 Buchstaben gross
        return mb_strtoupper(mb_substr($name, 0, 3));
    }

    private function emitXlsxHeaders(string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function sanitizeFilename(string $s): string
    {
        $s = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $s);
        return trim($s, '_') ?: 'export';
    }
}
