<?php

declare(strict_types=1);

namespace GestioBio\Services;

use RuntimeException;
use ZipArchive;

/**
 * Exportacion del diagnostico a Word (.docx) sin dependencias externas.
 *
 * Genera el paquete Open XML a mano: parrafos institucionales, titulos de nivel
 * 2, texto en negrita, vinetas y la tabla Modelo | Articulo | Costo referencial,
 * replicando el .docx que producia python-docx en el proyecto Django.
 */
final class DocxExport
{
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const CONTENT_WIDTH = 9360;

    public static function build(DiagnosticDocument $document): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extension zip de PHP es necesaria para exportar .docx.');
        }

        $files = [
            '[Content_Types].xml' => self::contentTypesXml(),
            '_rels/.rels' => self::packageRelsXml(),
            'docProps/core.xml' => self::coreXml($document),
            'docProps/app.xml' => self::appXml(),
            'word/document.xml' => self::documentXml($document),
            'word/styles.xml' => self::stylesXml(),
            'word/numbering.xml' => self::numberingXml(),
            'word/_rels/document.xml.rels' => self::documentRelsXml(),
        ];

        $temporary = tempnam(sys_get_temp_dir(), 'gestiobio');
        if ($temporary === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal del .docx.');
        }

        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo abrir el paquete .docx.');
        }
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $bytes = file_get_contents($temporary);
        @unlink($temporary);
        if ($bytes === false) {
            throw new RuntimeException('No se pudo leer el .docx generado.');
        }

        return $bytes;
    }

    private static function documentXml(DiagnosticDocument $document): string
    {
        $body = '';
        foreach ($document->blocks as $block) {
            $body .= match ($block->kind) {
                Block::HEADING => self::headingXml($block->text, $block->level),
                Block::BULLETS => self::bulletsXml($block->items),
                Block::TABLE => self::tableXml($block),
                default => self::paragraphXml($block->text, $block->bold),
            };
        }

        $body .= '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/>'
            . '<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" '
            . 'w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="' . self::NS_W . '" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<w:body>' . $body . '</w:body></w:document>';
    }

    private static function paragraphXml(string $text, bool $bold = false): string
    {
        $lines = preg_split('/\n/', $text) ?: [''];
        $runs = '';
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $runs .= '<w:r><w:br/></w:r>';
            }
            $properties = $bold ? '<w:rPr><w:b/></w:rPr>' : '';
            $runs .= '<w:r>' . $properties . '<w:t xml:space="preserve">' . self::xml($line) . '</w:t></w:r>';
        }

        return '<w:p>' . $runs . '</w:p>';
    }

    private static function headingXml(string $text, int $level): string
    {
        $level = $level > 0 ? $level : 2;

        return '<w:p><w:pPr><w:pStyle w:val="Heading' . $level . '"/></w:pPr>'
            . '<w:r><w:t xml:space="preserve">' . self::xml($text) . '</w:t></w:r></w:p>';
    }

    /**
     * @param list<string> $items
     */
    private static function bulletsXml(array $items): string
    {
        $xml = '';
        foreach ($items as $item) {
            $xml .= '<w:p><w:pPr><w:pStyle w:val="ListBullet"/>'
                . '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>'
                . '<w:r><w:t xml:space="preserve">' . self::xml($item) . '</w:t></w:r></w:p>';
        }

        return $xml;
    }

    private static function tableXml(Block $block): string
    {
        $columns = max(1, count($block->columns));
        $width = (int) floor(self::CONTENT_WIDTH / $columns);

        $borders = '<w:tblBorders>';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
            $borders .= '<w:' . $side . ' w:val="single" w:sz="4" w:space="0" w:color="auto"/>';
        }
        $borders .= '</w:tblBorders>';

        $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/>'
            . $borders . '</w:tblPr><w:tblGrid>';
        for ($index = 0; $index < $columns; $index++) {
            $xml .= '<w:gridCol w:w="' . $width . '"/>';
        }
        $xml .= '</w:tblGrid>';

        $xml .= self::tableRowXml($block->columns, $width, true);
        foreach ($block->rows as $row) {
            $xml .= self::tableRowXml($row, $width, false);
        }

        return $xml . '</w:tbl>';
    }

    /**
     * @param list<string|int> $cells
     */
    private static function tableRowXml(array $cells, int $width, bool $bold): string
    {
        $xml = '<w:tr>';
        foreach ($cells as $cell) {
            $properties = $bold ? '<w:rPr><w:b/></w:rPr>' : '';
            $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/></w:tcPr>'
                . '<w:p><w:r>' . $properties . '<w:t xml:space="preserve">' . self::xml((string) $cell) . '</w:t></w:r></w:p>'
                . '</w:tc>';
        }

        return $xml . '</w:tr>';
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private static function packageRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private static function documentRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>'
            . '</Relationships>';
    }

    private static function coreXml(DiagnosticDocument $document): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::xml($document->subject) . '</dc:title>'
            . '<dc:subject>Diagnóstico técnico ' . self::xml('#' . $document->ticketNumber) . '</dc:subject>'
            . '<dc:creator>GestioBioV3</dc:creator>'
            . '<cp:lastModifiedBy>GestioBioV3</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private static function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>GestioBioV3 (PHP)</Application><DocSecurity>0</DocSecurity>'
            . '<ScaleCrop>false</ScaleCrop><Company>Biopago C.A.</Company>'
            . '<LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc>'
            . '<HyperlinksChanged>false</HyperlinksChanged><AppVersion>16.0000</AppVersion>'
            . '</Properties>';
    }

    private static function stylesXml(): string
    {
        $borders = '';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
            $borders .= '<w:' . $side . ' w:val="single" w:sz="4" w:space="0" w:color="auto"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="' . self::NS_W . '">'
            . '<w:docDefaults><w:rPrDefault><w:rPr>'
            . '<w:rFonts w:ascii="Calibri" w:eastAsia="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>'
            . '<w:sz w:val="22"/><w:szCs w:val="22"/>'
            . '<w:lang w:val="es-VE" w:eastAsia="es-VE" w:bidi="ar-SA"/>'
            . '</w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="160" w:line="259" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
            . '</w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
            . '<w:name w:val="Normal"/><w:qFormat/></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading1">'
            . '<w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
            . '<w:uiPriority w:val="9"/><w:qFormat/>'
            . '<w:pPr><w:keepNext/><w:keepLines/><w:spacing w:before="240" w:after="0"/>'
            . '<w:outlineLvl w:val="0"/></w:pPr>'
            . '<w:rPr><w:rFonts w:ascii="Calibri Light" w:hAnsi="Calibri Light"/>'
            . '<w:b/><w:color w:val="2F5496"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading2">'
            . '<w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
            . '<w:uiPriority w:val="9"/><w:unhideWhenUsed/><w:qFormat/>'
            . '<w:pPr><w:keepNext/><w:keepLines/><w:spacing w:before="200" w:after="40"/>'
            . '<w:outlineLvl w:val="1"/></w:pPr>'
            . '<w:rPr><w:rFonts w:ascii="Calibri Light" w:hAnsi="Calibri Light"/>'
            . '<w:b/><w:color w:val="2F5496"/><w:sz w:val="26"/><w:szCs w:val="26"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="ListBullet">'
            . '<w:name w:val="List Bullet"/><w:basedOn w:val="Normal"/><w:uiPriority w:val="99"/>'
            . '<w:unhideWhenUsed/>'
            . '<w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>'
            . '<w:spacing w:after="0"/><w:ind w:left="720" w:hanging="360"/></w:pPr></w:style>'
            . '<w:style w:type="table" w:default="1" w:styleId="TableNormal">'
            . '<w:name w:val="Normal Table"/><w:uiPriority w:val="99"/><w:semiHidden/><w:unhideWhenUsed/>'
            . '<w:tblPr><w:tblInd w:w="0" w:type="dxa"/><w:tblCellMar>'
            . '<w:top w:w="0" w:type="dxa"/><w:left w:w="108" w:type="dxa"/>'
            . '<w:bottom w:w="0" w:type="dxa"/><w:right w:w="108" w:type="dxa"/>'
            . '</w:tblCellMar></w:tblPr></w:style>'
            . '<w:style w:type="table" w:styleId="TableGrid">'
            . '<w:name w:val="Table Grid"/><w:basedOn w:val="TableNormal"/><w:uiPriority w:val="59"/>'
            . '<w:tblPr><w:tblBorders>' . $borders . '</w:tblBorders></w:tblPr></w:style>'
            . '</w:styles>';
    }

    private static function numberingXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:numbering xmlns:w="' . self::NS_W . '">'
            . '<w:abstractNum w:abstractNumId="0">'
            . '<w:multiLevelType w:val="hybridMultilevel"/>'
            . '<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/>'
            . '<w:lvlText w:val="•"/><w:lvlJc w:val="left"/>'
            . '<w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>'
            . '<w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol" w:hint="default"/></w:rPr></w:lvl>'
            . '</w:abstractNum>'
            . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
            . '</w:numbering>';
    }
}



