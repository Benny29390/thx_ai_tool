<?php
/**
 * GET /api/v1/lam/vorschlagsliste-excel?id=<liste-id>
 * Excel-Export einer Vorschlagsliste — Layout 1:1 wie das alte LAM-Tool
 * (docs/VID_Linkquellen_Final.xlsx).
 *
 * Sheet 1 „Linkquellen":
 *   Cluster | Themengebiet | URL | Impressum | Anmerkung | SI | DP | Preis |
 *   Preis min (€) | Preis max (€) | Anbieter-Anzahl | Günstigster Anbieter |
 *   Alle Angebote (sortiert) | Quelle
 * Sheet 2 „Übersicht": Titel, Statistik, Cluster-Legende, Spalten-Logik.
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$id = trim((string)($_GET['id'] ?? ''));
if ($id === '') Response::error('id erforderlich', 400);
$variante = ($_GET['variante'] ?? 'intern') === 'kunde' ? 'kunde' : 'intern';

require_once SERVICES_PATH . '/LamService.php';
require_once '/var/www/vendor/autoload.php';

$db = Database::getInstance();
$svc = new LamService($db);
$liste = $svc->getVorschlagsliste($id);
if (!$liste) Response::error('Liste nicht gefunden', 404);

// Beispiellinks pro Domain (erster Eintrag aus lam_domain_links typ=beispiellink) — nur für Kunden-Variante
$beispiellinks = [];
if ($variante === 'kunde') {
    $domainIdsAll = array_column($liste['eintraege'], 'domain_id');
    if (!empty($domainIdsAll)) {
        $inAll = implode(',', array_fill(0, count($domainIdsAll), '?'));
        $blRaw = $db->query(
            "SELECT domain_id, url, label
             FROM lam_domain_links
             WHERE domain_id IN ($inAll) AND typ = 'beispiellink' AND geloescht_am IS NULL
             ORDER BY position ASC, erstellt_am ASC",
            $domainIdsAll
        ) ?: [];
        foreach ($blRaw as $bl) {
            if (!isset($beispiellinks[$bl['domain_id']])) {
                $beispiellinks[$bl['domain_id']] = $bl['url'];
            }
        }
    }
}

// === Konditionen pro Domain laden ===
$domainIds = array_column($liste['eintraege'], 'domain_id');
$konditionen = [];
if (!empty($domainIds)) {
    $in = implode(',', array_fill(0, count($domainIds), '?'));
    $kondRaw = $db->query(
        "SELECT k.domain_id, k.preis, a.name AS anbieter_name
         FROM lam_konditionen k
         LEFT JOIN lam_anbieter a ON a.id = k.via_anbieter_id
         WHERE k.domain_id IN ($in) AND k.geloescht_am IS NULL AND k.preis IS NOT NULL
         ORDER BY k.preis ASC",
        $domainIds
    ) ?: [];
    foreach ($kondRaw as $k) $konditionen[$k['domain_id']][] = $k;
}

// === Cluster-Mapping (Tag → A, B, C …) ===
$themen = [];
foreach ($liste['eintraege'] as $e) {
    $tag = $e['tags'] ? explode('|', $e['tags'])[0] : '(ohne Cluster)';
    if (!in_array($tag, $themen, true)) $themen[] = $tag;
}
sort($themen);
$clusterMap = [];
foreach ($themen as $i => $t) $clusterMap[$t] = chr(65 + $i);

// Sortierung: Cluster, dann URL
$eintraege = $liste['eintraege'];
usort($eintraege, function ($a, $b) use ($clusterMap) {
    $tA = $a['tags'] ? explode('|', $a['tags'])[0] : '(ohne Cluster)';
    $tB = $b['tags'] ? explode('|', $b['tags'])[0] : '(ohne Cluster)';
    $cmp = strcmp($clusterMap[$tA] ?? 'Z', $clusterMap[$tB] ?? 'Z');
    return $cmp !== 0 ? $cmp : strcmp($a['domain_url'], $b['domain_url']);
});

// === Sheet 1: Linkquellen ===
$ss = new Spreadsheet();
$sheet1 = $ss->getActiveSheet();
$sheet1->setTitle('Linkquellen');

if ($variante === 'kunde') {
    // Kunden-Format laut Briefing 03 ▸ 13:
    //   URL · Beispiellink · SI · DP · Preis · Linkziel · Linktext · Artikelthema · Bemerkungen
    // OHNE Anbieter — geht den Kunden nichts an.
    $headers = [
        'URL', 'Beispiellink', 'SI', 'DP', 'Preis (€)',
        'Linkziel', 'Linktext', 'Artikelthema / Kontext', 'Bemerkungen',
    ];
    $sheet1->fromArray($headers, null, 'A1');
    $sheet1->getStyle('A1:I1')->getFont()->setBold(true);
    $sheet1->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
    $sheet1->getStyle('A1:I1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet1->getRowDimension(1)->setRowHeight(18);

    $row = 2;
    foreach ($eintraege as $e) {
        $url = (string) $e['domain_url'];
        $extUrl = 'https://' . $url;
        $beispielUrl = $beispiellinks[$e['domain_id']] ?? '';

        $sheet1->setCellValueExplicit("A{$row}", $url, DataType::TYPE_STRING);
        $sheet1->getCell("A{$row}")->getHyperlink()->setUrl($extUrl);
        if ($beispielUrl) {
            $sheet1->setCellValue("B{$row}", 'Beispiel');
            $sheet1->getCell("B{$row}")->getHyperlink()->setUrl($beispielUrl);
        }
        if ($e['si_aktuell']  !== null) $sheet1->setCellValue("C{$row}", (float) $e['si_aktuell']);
        if ($e['dp_aktuell']  !== null) $sheet1->setCellValue("D{$row}", (int) $e['dp_aktuell']);
        if ($e['preis_kunde'] !== null) $sheet1->setCellValue("E{$row}", (float) $e['preis_kunde']);
        $sheet1->setCellValue("F{$row}", $e['linkziel_url'] ?? ($e['ziel_url'] ?? ''));
        $sheet1->setCellValue("G{$row}", $e['linktext'] ?? '');
        $sheet1->setCellValue("H{$row}", $e['artikelthema'] ?? '');
        $sheet1->setCellValue("I{$row}", $e['notiz'] ?? '');

        // URL + Beispiel als Hyperlink-Style
        foreach (['A', 'B'] as $col) {
            $sheet1->getStyle("{$col}{$row}")->getFont()
                   ->getColor()->setRGB('0070C0');
            $sheet1->getStyle("{$col}{$row}")->getFont()
                   ->setUnderline(Font::UNDERLINE_SINGLE);
        }
        $row++;
    }

    // Spaltenbreiten kompakt-orientiert
    $kBreiten = ['A' => 32, 'B' => 11, 'C' => 9, 'D' => 8, 'E' => 11,
                 'F' => 28, 'G' => 24, 'H' => 24, 'I' => 38];
    foreach ($kBreiten as $col => $w) $sheet1->getColumnDimension($col)->setWidth($w);
    $sheet1->freezePane('A2');
    $lastRow = $row - 1;
    if ($lastRow >= 2) {
        $sheet1->getStyle("C2:C{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
        $sheet1->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode('0');
        $sheet1->getStyle("E2:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0 "€"');
    }

    // Sheet 2 (Übersicht) komplett überspringen für Kunden-Format — Liefersache: nur Linkquellen
    $writer = new Xlsx($ss);
    $kunde = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $liste['customer_name'] ?? 'kunde');
    $listenName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $liste['name'] ?? 'liste');
    $dateiName = "Linkoptionen_{$kunde}_{$listenName}.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dateiName . '"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}

$headers = [
    'Cluster', 'Themengebiet', 'URL', 'Impressum', 'Anmerkung',
    'SI', 'DP', 'Preis', 'Preis min (€)', 'Preis max (€)',
    'Anbieter-Anzahl', 'Günstigster Anbieter', 'Alle Angebote (sortiert)', 'Quelle',
];
$sheet1->fromArray($headers, null, 'A1');

// Header schlicht: hellgrauer Hintergrund, schwarz, fett — wie im Original
$sheet1->getStyle('A1:N1')->getFont()->setBold(true);
$sheet1->getStyle('A1:N1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
$sheet1->getStyle('A1:N1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet1->getRowDimension(1)->setRowHeight(18);

$row = 2;
foreach ($eintraege as $e) {
    $tag = $e['tags'] ? explode('|', $e['tags'])[0] : '(ohne Cluster)';
    $cluster = $clusterMap[$tag] ?? '?';

    $konds = $konditionen[$e['domain_id']] ?? [];
    $preise = array_map(fn($k) => (float) $k['preis'], $konds);
    $preisMin = $preise ? min($preise) : null;
    $preisMax = $preise ? max($preise) : null;
    $anbieterAnzahl = count(array_unique(array_filter(array_column($konds, 'anbieter_name'))));
    $guenstigster = $konds ? ($konds[0]['anbieter_name'] ?? '') : '';
    $alleAngebote = implode(' | ', array_map(
        fn($k) => ($k['anbieter_name'] ?: '?') . ': ' . number_format((float) $k['preis'], 0, ',', '.') . ' €',
        $konds
    ));

    $url = (string) $e['domain_url'];
    $impressumUrl = 'https://' . preg_replace('#^www\.#i', '', $url) . '/impressum/';
    $extUrl = 'https://' . $url;

    $sheet1->setCellValue("A{$row}", $cluster);
    $sheet1->setCellValue("B{$row}", $tag);
    $sheet1->setCellValueExplicit("C{$row}", $url, DataType::TYPE_STRING);
    $sheet1->getCell("C{$row}")->getHyperlink()->setUrl($extUrl);
    $sheet1->setCellValue("D{$row}", 'Impressum öffnen');
    $sheet1->getCell("D{$row}")->getHyperlink()->setUrl($impressumUrl);
    $sheet1->setCellValue("E{$row}", $e['notiz'] ?? '');
    if ($e['si_aktuell'] !== null) $sheet1->setCellValue("F{$row}", (float) $e['si_aktuell']);
    if ($e['dp_aktuell'] !== null) $sheet1->setCellValue("G{$row}", (int) $e['dp_aktuell']);
    // „Preis" (Spalte H) = preis_kunde aus Linkoption — wie im Original gelegentlich gesetzt, oft leer
    if ($e['preis_kunde'] !== null) $sheet1->setCellValue("H{$row}", (float) $e['preis_kunde']);
    if ($preisMin !== null) $sheet1->setCellValue("I{$row}", $preisMin);
    if ($preisMax !== null) $sheet1->setCellValue("J{$row}", $preisMax);
    if ($anbieterAnzahl > 0) $sheet1->setCellValue("K{$row}", $anbieterAnzahl);
    $sheet1->setCellValue("L{$row}", $guenstigster);
    $sheet1->setCellValue("M{$row}", $alleAngebote ?: ($konds ? '' : 'Direktanfrage an Redaktion'));
    $sheet1->setCellValue("N{$row}", $konds ? 'Linkliste' : 'Web-Recherche');

    // Hyperlink-Style — blau + unterstrichen, sonst nichts
    foreach (['C', 'D'] as $col) {
        $sheet1->getStyle("{$col}{$row}")->getFont()
               ->getColor()->setRGB('0070C0');
        $sheet1->getStyle("{$col}{$row}")->getFont()
               ->setUnderline(Font::UNDERLINE_SINGLE);
    }
    $row++;
}

// Spaltenbreiten — fix, großzügig, wie das Original
$breiten = [
    'A' => 9, 'B' => 25, 'C' => 32, 'D' => 16, 'E' => 38,
    'F' => 9, 'G' => 8, 'H' => 9, 'I' => 13, 'J' => 13,
    'K' => 16, 'L' => 22, 'M' => 60, 'N' => 14,
];
foreach ($breiten as $col => $w) $sheet1->getColumnDimension($col)->setWidth($w);

// Header fixieren
$sheet1->freezePane('A2');

// Zahlen-Formate
$lastRow = $row - 1;
if ($lastRow >= 2) {
    // SI: 4 Nachkommastellen
    $sheet1->getStyle("F2:F{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
    // Euro-Preise
    foreach (['H', 'I', 'J'] as $col) {
        $sheet1->getStyle("{$col}{$lastRow}")
               ->getNumberFormat()->setFormatCode('#,##0 "€"');
        $sheet1->getStyle("{$col}2:{$col}{$lastRow}")
               ->getNumberFormat()->setFormatCode('#,##0 "€"');
    }
    // DP + Anbieter-Anzahl als Integer
    foreach (['G', 'K'] as $col) {
        $sheet1->getStyle("{$col}2:{$col}{$lastRow}")
               ->getNumberFormat()->setFormatCode('0');
    }
}

// === Sheet 2: Übersicht ===
$sheet2 = $ss->createSheet();
$sheet2->setTitle('Übersicht');

$titel = 'Finale Linkquellen-Auswahl für ' . ($liste['customer_name'] ?: '?');
$sheet2->setCellValue('A1', $titel);
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$sheet2->mergeCells('A1:B1');

$anzahlLinkliste = 0; $anzahlWeb = 0;
foreach ($eintraege as $e) {
    if (!empty($konditionen[$e['domain_id']])) $anzahlLinkliste++;
    else $anzahlWeb++;
}

$blockRow = 3;
$sheet2->setCellValue("A{$blockRow}", 'Anzahl URLs gesamt:');
$sheet2->setCellValue("B{$blockRow}", count($eintraege));
$blockRow++;
$sheet2->setCellValue("A{$blockRow}", 'davon aus Linkliste:');
$sheet2->setCellValue("B{$blockRow}", $anzahlLinkliste . ' (über Vermittler buchbar)');
$blockRow++;
$sheet2->setCellValue("A{$blockRow}", 'davon aus Web-Recherche:');
$sheet2->setCellValue("B{$blockRow}", $anzahlWeb . ' (Direktanfrage an Redaktion erforderlich)');
$blockRow += 2;

$sheet2->setCellValue("A{$blockRow}", 'Cluster-Legende:');
$sheet2->getStyle("A{$blockRow}")->getFont()->setBold(true);
$blockRow++;
foreach ($clusterMap as $thema => $buchstabe) {
    $sheet2->setCellValue("A{$blockRow}", $buchstabe);
    $sheet2->setCellValue("B{$blockRow}", $thema);
    $blockRow++;
}
$blockRow++;

$sheet2->setCellValue("A{$blockRow}", 'Spalten-Logik:');
$sheet2->getStyle("A{$blockRow}")->getFont()->setBold(true);
$blockRow++;
$erklaerungen = [
    ['URL / Impressum',  'Anklickbare Hyperlinks. Impressum heuristisch /impressum/ (DACH-Standard).'],
    ['SI / DP',          'Sichtbarkeit (Sistrix) und Domainpopularität — letzter Snapshot.'],
    ['Preis',            'Preis aus dem Linkoption-Eintrag (vom Kunden bestätigter Preis).'],
    ['Preis min/max',    'Spannweite über alle hinterlegten Konditionen pro Domain.'],
    ['Alle Angebote',    'Sortiert nach Preis aufsteigend — Anbieter: Preis.'],
    ['Quelle',           'Linkliste = mindestens eine Kondition hinterlegt. Web-Recherche = keine.'],
];
foreach ($erklaerungen as [$l, $r]) {
    $sheet2->setCellValue("A{$blockRow}", $l);
    $sheet2->setCellValue("B{$blockRow}", $r);
    $blockRow++;
}

$sheet2->getColumnDimension('A')->setWidth(28);
$sheet2->getColumnDimension('B')->setWidth(85);
$sheet2->getStyle('A1:B' . ($blockRow - 1))->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

$ss->setActiveSheetIndex(0);

// === Output ===
$tmpFile = tempnam(sys_get_temp_dir(), 'lam_vl_') . '.xlsx';
try {
    (new Xlsx($ss))->save($tmpFile);
    $base = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $liste['name'] ?: 'liste');
    $dateiName = ($liste['customer_kuerzel'] ?: 'VL') . '_' . $base . '_' . date('Y-m-d') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dateiName . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
} catch (\Throwable $e) {
    if (file_exists($tmpFile)) @unlink($tmpFile);
    Response::error('Export fehlgeschlagen: ' . $e->getMessage(), 500);
}
