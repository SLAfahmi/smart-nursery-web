<?php

namespace App\Support;

use DateTimeInterface;
use RuntimeException;
use XMLWriter;
use ZipArchive;

/**
 * Penulis .xlsx satu sheet tanpa library tambahan (butuh ekstensi zip & xmlwriter).
 * Baris ditulis bertahap ke file sementara, jadi rentang log yang panjang tetap hemat memori.
 *
 *   $xlsx = new XlsxWriter('Log Sensor', [20, 12, 12]);
 *   $xlsx->addRow(['Waktu', 'Suhu', 'Status'], XlsxWriter::STYLE_HEADER);
 *   $xlsx->addRow([now(), 28.4, 'ON']);
 *   $path = $xlsx->finish();   // file .xlsx sementara, hapus setelah dikirim
 */
class XlsxWriter
{
    public const STYLE_DEFAULT = 0;
    public const STYLE_HEADER = 1;
    private const STYLE_DATETIME = 2;

    private XMLWriter $sheet;
    private string $sheetPath;
    private int $rowCount = 0;

    /** @param array<int, float> $columnWidths lebar kolom (satuan karakter Excel), urut dari kolom A */
    public function __construct(private string $sheetName, array $columnWidths = [])
    {
        // Nama sheet Excel: maksimal 31 karakter, tanpa : \ / ? * [ ]
        $this->sheetName = mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', ' ', $sheetName), 0, 31) ?: 'Sheet1';

        $this->sheetPath = $this->tempFile();
        $this->sheet = new XMLWriter();
        $this->sheet->openUri($this->sheetPath);
        $this->sheet->startDocument('1.0', 'UTF-8', 'yes');
        $this->sheet->startElement('worksheet');
        $this->sheet->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        // Baris pertama (header) tetap terlihat saat di-scroll
        $this->sheet->startElement('sheetViews');
        $this->sheet->startElement('sheetView');
        $this->sheet->writeAttribute('workbookViewId', '0');
        $this->sheet->startElement('pane');
        $this->sheet->writeAttribute('ySplit', '1');
        $this->sheet->writeAttribute('topLeftCell', 'A2');
        $this->sheet->writeAttribute('activePane', 'bottomLeft');
        $this->sheet->writeAttribute('state', 'frozen');
        $this->sheet->endElement();
        $this->sheet->endElement();
        $this->sheet->endElement();

        if ($columnWidths) {
            $this->sheet->startElement('cols');
            foreach (array_values($columnWidths) as $i => $width) {
                $this->sheet->startElement('col');
                $this->sheet->writeAttribute('min', (string) ($i + 1));
                $this->sheet->writeAttribute('max', (string) ($i + 1));
                $this->sheet->writeAttribute('width', (string) $width);
                $this->sheet->writeAttribute('customWidth', '1');
                $this->sheet->endElement();
            }
            $this->sheet->endElement();
        }

        $this->sheet->startElement('sheetData');
    }

    /**
     * Angka ditulis sebagai angka, tanggal sebagai tanggal Excel, null sebagai sel kosong,
     * selain itu sebagai teks.
     *
     * @param array<int, string|int|float|bool|DateTimeInterface|null> $cells
     */
    public function addRow(array $cells, int $style = self::STYLE_DEFAULT): void
    {
        $this->rowCount++;
        $this->sheet->startElement('row');
        $this->sheet->writeAttribute('r', (string) $this->rowCount);

        foreach (array_values($cells) as $i => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $this->sheet->startElement('c');
            $this->sheet->writeAttribute('r', self::columnName($i) . $this->rowCount);

            if ($value instanceof DateTimeInterface) {
                $this->sheet->writeAttribute('s', (string) self::STYLE_DATETIME);
                $this->sheet->writeElement('v', (string) self::excelDate($value));
            } elseif (is_int($value) || is_float($value)) {
                if ($style !== self::STYLE_DEFAULT) {
                    $this->sheet->writeAttribute('s', (string) $style);
                }
                $this->sheet->writeElement('v', (string) $value);
            } else {
                if ($style !== self::STYLE_DEFAULT) {
                    $this->sheet->writeAttribute('s', (string) $style);
                }
                $this->sheet->writeAttribute('t', 'inlineStr');
                $this->sheet->startElement('is');
                $this->sheet->startElement('t');
                $this->sheet->writeAttribute('xml:space', 'preserve');
                $this->sheet->text(self::cleanText(is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : (string) $value));
                $this->sheet->endElement();
                $this->sheet->endElement();
            }

            $this->sheet->endElement();
        }

        $this->sheet->endElement();

        if ($this->rowCount % 500 === 0) {
            $this->sheet->flush();
        }
    }

    /** Menutup sheet dan mengemasnya menjadi .xlsx. Mengembalikan path file sementara. */
    public function finish(): string
    {
        $this->sheet->endElement(); // sheetData
        $this->sheet->endElement(); // worksheet
        $this->sheet->endDocument();
        $this->sheet->flush();

        $path = $this->tempFile();
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat file .xlsx');
        }

        $zip->addFromString('[Content_Types].xml', self::CONTENT_TYPES);
        $zip->addFromString('_rels/.rels', self::ROOT_RELS);
        $zip->addFromString('xl/workbook.xml', sprintf(self::WORKBOOK, htmlspecialchars($this->sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::WORKBOOK_RELS);
        $zip->addFromString('xl/styles.xml', self::STYLES);
        $zip->addFile($this->sheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();

        @unlink($this->sheetPath);

        return $path;
    }

    /** 0 -> A, 25 -> Z, 26 -> AA */
    public static function columnName(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26) . $name;
        }

        return $name;
    }

    /** Nomor seri tanggal Excel dari jam dinding lokal (bukan UTC), seperti yang terlihat di dashboard */
    public static function excelDate(DateTimeInterface $date): float
    {
        return round(($date->getTimestamp() + $date->getOffset()) / 86400 + 25569, 8);
    }

    /** Karakter kontrol tidak sah di XML dan akan membuat Excel menolak file */
    private static function cleanText(string $text): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($path === false) {
            throw new RuntimeException('Tidak bisa membuat file sementara');
        }

        return $path;
    }

    private const CONTENT_TYPES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    private const ROOT_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    private const WORKBOOK = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="%s" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    private const WORKBOOK_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    // Gaya: 0 biasa, 1 header (tebal, latar hijau muda), 2 tanggal-jam
    private const STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy-mm-dd hh:mm:ss"/></numFmts>'
        . '<fonts count="2">'
        . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDCEBDD"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="3">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}
