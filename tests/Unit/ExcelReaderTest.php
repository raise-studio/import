<?php

namespace RaiseStudio\Import\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RaiseStudio\Import\Readers\ExcelReader;

class ExcelReaderTest extends TestCase
{
    protected string $xlsxFile;
    protected string $odsFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->xlsxFile = $this->createTempXlsx();
        $this->odsFile = $this->createTempOds();
    }

    protected function tearDown(): void
    {
        if (isset($this->xlsxFile) && file_exists($this->xlsxFile)) {
            unlink($this->xlsxFile);
        }
        if (isset($this->odsFile) && file_exists($this->odsFile)) {
            unlink($this->odsFile);
        }
        parent::tearDown();
    }

    protected function createTempXlsx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_test_') . '.xlsx';

        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['name', 'email', 'phone']));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Alice', 'alice@example.com', '13800138000']));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Bob', 'bob@example.com', '13900139000']));
        $writer->close();

        return $path;
    }

    protected function createTempOds(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ods_test_') . '.ods';

        $writer = new \OpenSpout\Writer\ODS\Writer();
        $writer->openToFile($path);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['name', 'email', 'phone']));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Alice', 'alice@example.com', '13800138000']));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Bob', 'bob@example.com', '13900139000']));
        $writer->close();

        return $path;
    }

    /** @test */
    public function it_reads_xlsx_headers()
    {
        $reader = new ExcelReader();
        $headers = $reader->headers($this->xlsxFile);

        $this->assertEquals(['name', 'email', 'phone'], $headers);
    }

    /** @test */
    public function it_reads_ods_headers()
    {
        $reader = new ExcelReader();
        $headers = $reader->headers($this->odsFile);

        $this->assertEquals(['name', 'email', 'phone'], $headers);
    }

    /** @test */
    public function it_counts_xlsx_rows()
    {
        $reader = new ExcelReader();
        $count = $reader->count($this->xlsxFile);

        $this->assertEquals(2, $count);
    }

    /** @test */
    public function it_counts_ods_rows()
    {
        $reader = new ExcelReader();
        $count = $reader->count($this->odsFile);

        $this->assertEquals(2, $count);
    }

    /** @test */
    public function it_previews_xlsx_rows()
    {
        $reader = new ExcelReader();
        $preview = $reader->preview($this->xlsxFile, 1);

        $this->assertCount(1, $preview);
        $this->assertEquals('Alice', $preview[0]['name']);
    }

    /** @test */
    public function it_previews_ods_rows()
    {
        $reader = new ExcelReader();
        $preview = $reader->preview($this->odsFile, 1);

        $this->assertCount(1, $preview);
        $this->assertEquals('Alice', $preview[0]['name']);
    }

    /** @test */
    public function it_reads_all_xlsx_rows()
    {
        $reader = new ExcelReader();
        $rows = iterator_to_array($reader->rows($this->xlsxFile));

        $this->assertCount(2, $rows);
        $this->assertEquals('alice@example.com', $rows[0]['email']);
        $this->assertEquals('Bob', $rows[1]['name']);
    }

    /** @test */
    public function it_reads_all_ods_rows()
    {
        $reader = new ExcelReader();
        $rows = iterator_to_array($reader->rows($this->odsFile));

        $this->assertCount(2, $rows);
        $this->assertEquals('alice@example.com', $rows[0]['email']);
        $this->assertEquals('Bob', $rows[1]['name']);
    }

    /** @test */
    public function it_returns_empty_preview_for_file_with_only_headers()
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_empty_') . '.xlsx';

        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($path);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['name', 'email']));
        $writer->close();

        $reader = new ExcelReader();
        $preview = $reader->preview($path, 10);

        $this->assertEmpty($preview);

        unlink($path);
    }

    /** @test */
    public function it_throws_for_unsupported_format_in_excel_reader()
    {
        $this->expectException(\InvalidArgumentException::class);

        $reader = new ExcelReader();
        // Access protected createReader via reflection for lower-level test
        $ref = new \ReflectionMethod($reader, 'createReader');
        $ref->invoke($reader, '/tmp/test.txt');
    }

    /** @test */
    public function it_casts_numbers_to_strings()
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_numeric_') . '.xlsx';

        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($path);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['phone']));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([13800138000]));
        $writer->close();

        $reader = new ExcelReader();
        $rows = iterator_to_array($reader->rows($path));

        $this->assertIsString($rows[0]['phone']);
        $this->assertEquals('13800138000', $rows[0]['phone']);

        unlink($path);
    }
}
