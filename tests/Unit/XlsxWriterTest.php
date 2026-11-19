<?php

namespace Tests\Unit;

use App\Support\XlsxWriter;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class XlsxWriterTest extends TestCase
{
    public function test_column_names(): void
    {
        $this->assertSame('A', XlsxWriter::columnName(0));
        $this->assertSame('Z', XlsxWriter::columnName(25));
        $this->assertSame('AA', XlsxWriter::columnName(26));
        $this->assertSame('ZZ', XlsxWriter::columnName(701));
        $this->assertSame('AAA', XlsxWriter::columnName(702));
    }

    public function test_excel_date_uses_local_wall_clock(): void
    {
        $jakarta = new DateTimeImmutable('2026-10-11 08:00:00', new DateTimeZone('Asia/Jakarta'));
        $this->assertEqualsWithDelta(46306 + 8 / 24, XlsxWriter::excelDate($jakarta), 1e-6);
    }

    public function test_text_is_escaped_and_control_characters_removed(): void
    {
        $xlsx = new XlsxWriter('Log: [uji]/1');
        $xlsx->addRow(['<b>&"x"</b>', "a\x01b", '=1+1', true]);
        $path = $xlsx->finish();

        $zip = new ZipArchive();
        $zip->open($path);
        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($path);

        $cells = $sheet->sheetData->row[0]->c;
        $this->assertSame('<b>&"x"</b>', (string) $cells[0]->is->t);
        $this->assertSame('ab', (string) $cells[1]->is->t);
        // Teks yang diawali "=" tetap teks, tidak dijalankan sebagai rumus
        $this->assertSame('inlineStr', (string) $cells[2]['t']);
        $this->assertFalse(isset($cells[2]->f));
        $this->assertSame('TRUE', (string) $cells[3]->is->t);
        $this->assertSame('Log   uji  1', (string) $workbook->sheets->sheet['name']);
    }
}
