<?php
/**
 * DOCX Generator — Erzeugt Word-Dokumente nativ (ZIP + XML)
 * Kein externer Dependency noetig.
 */

namespace Services;

class DocxGenerator
{
    private array $body = [];

    public function addHeading(string $text, int $level = 1): void
    {
        $size = match($level) {
            1 => 32, 2 => 26, 3 => 22, default => 20,
        };
        $this->body[] = $this->paragraph($text, $size, true, 'Heading' . $level);
    }

    public function addParagraph(string $text, bool $bold = false, int $size = 11): void
    {
        $this->body[] = $this->paragraph($text, $size, $bold);
    }

    public function addBullet(string $text): void
    {
        $this->body[] = $this->bulletParagraph($text);
    }

    public function addSpacer(): void
    {
        $this->body[] = '<w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>';
    }

    public function addHorizontalRule(): void
    {
        $this->body[] = '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="CCCCCC"/></w:pBdr></w:pPr></w:p>';
    }

    public function addTable(array $headers, array $rows): void
    {
        $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
            . '<w:left w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
            . '<w:right w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
            . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
            . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
            . '</w:tblBorders></w:tblPr>';

        // Header row
        $xml .= '<w:tr>';
        foreach ($headers as $h) {
            $xml .= '<w:tc><w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="E8E8E8"/></w:tcPr>'
                . $this->paragraph($this->escXml($h), 10, true)
                . '</w:tc>';
        }
        $xml .= '</w:tr>';

        // Data rows
        foreach ($rows as $row) {
            $xml .= '<w:tr>';
            foreach ($row as $cell) {
                $xml .= '<w:tc>' . $this->paragraph($this->escXml($cell), 10) . '</w:tc>';
            }
            $xml .= '</w:tr>';
        }

        $xml .= '</w:tbl>';
        $this->body[] = $xml;
    }

    /**
     * Generiert das DOCX als String (ZIP-Datei)
     */
    public function generate(): string
    {
        $bodyXml = implode("\n", $this->body);

        $tmpFile = tempnam(sys_get_temp_dir(), 'docx_');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>');

        // _rels/.rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

        // word/_rels/document.xml.rels
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        // word/styles.xml
        $zip->addFromString('word/styles.xml', $this->stylesXml());

        // word/document.xml
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>' . $bodyXml . '
    <w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>
  </w:body>
</w:document>');

        $zip->close();

        $data = file_get_contents($tmpFile);
        unlink($tmpFile);
        return $data;
    }

    private function paragraph(string $text, int $sizePt = 11, bool $bold = false, ?string $style = null): string
    {
        $sizeHalfPt = $sizePt * 2;
        $xml = '<w:p><w:pPr>';
        if ($style) {
            $xml .= '<w:pStyle w:val="' . $style . '"/>';
        }
        $xml .= '<w:spacing w:after="120"/>';
        $xml .= '</w:pPr>';

        // Text in Zeilen aufteilen fuer Zeilenumbrueche
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if ($i > 0) {
                $xml .= '<w:r><w:br/></w:r>';
            }
            $xml .= '<w:r><w:rPr>';
            $xml .= '<w:sz w:val="' . $sizeHalfPt . '"/><w:szCs w:val="' . $sizeHalfPt . '"/>';
            if ($bold) $xml .= '<w:b/><w:bCs/>';
            $xml .= '</w:rPr>';
            $xml .= '<w:t xml:space="preserve">' . $this->escXml($line) . '</w:t></w:r>';
        }

        $xml .= '</w:p>';
        return $xml;
    }

    private function bulletParagraph(string $text): string
    {
        return '<w:p><w:pPr><w:pStyle w:val="ListBullet"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr><w:spacing w:after="60"/></w:pPr>'
            . '<w:r><w:rPr><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>'
            . '<w:t xml:space="preserve">  ' . $this->escXml($text) . '</w:t></w:r></w:p>';
    }

    private function escXml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="22"/><w:szCs w:val="22"/><w:color w:val="000000"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:pPr><w:spacing w:before="480" w:after="120"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:b/><w:bCs/><w:sz w:val="28"/><w:szCs w:val="28"/><w:color w:val="000000"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:pPr><w:spacing w:before="200" w:after="80"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:color w:val="000000"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading3">
    <w:name w:val="heading 3"/>
    <w:pPr><w:spacing w:before="120" w:after="60"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:b/><w:bCs/><w:sz w:val="22"/><w:szCs w:val="22"/><w:color w:val="000000"/></w:rPr>
  </w:style>
</w:styles>';
    }
}
