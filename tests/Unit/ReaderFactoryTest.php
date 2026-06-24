<?php

namespace RaiseStudio\Import\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RaiseStudio\Import\Readers\ReaderFactory;
use RaiseStudio\Import\Readers\CsvReader;
use RaiseStudio\Import\Readers\ExcelReader;

class ReaderFactoryTest extends TestCase
{
    protected string $csvFile;
    protected string $semicolonCsvFile;
    protected string $xlsxFile;
    protected string $odsFile;
    protected string $txtFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csvFile = tempnam(sys_get_temp_dir(), 'factory_csv_') . '.csv';
        file_put_contents($this->csvFile, "name,email\nAlice,alice@test.com\nBob,bob@test.com\n");

        $this->semicolonCsvFile = tempnam(sys_get_temp_dir(), 'factory_scsv_') . '.csv';
        file_put_contents($this->semicolonCsvFile, "name;email\nAlice;alice@test.com\nBob;bob@test.com\n");

        $this->xlsxFile = tempnam(sys_get_temp_dir(), 'factory_xlsx_') . '.xlsx';
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($this->xlsxFile);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['name', 'email']));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Alice', 'alice@test.com']));
        $writer->close();

        $this->odsFile = tempnam(sys_get_temp_dir(), 'factory_ods_') . '.ods';
        $writer = new \OpenSpout\Writer\ODS\Writer();
        $writer->openToFile($this->odsFile);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['name', 'email']));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Alice', 'alice@test.com']));
        $writer->close();

        $this->txtFile = tempnam(sys_get_temp_dir(), 'factory_txt_') . '.txt';
        file_put_contents($this->txtFile, 'not a spreadsheet');
    }

    protected function tearDown(): void
    {
        foreach ([$this->csvFile, $this->semicolonCsvFile, $this->xlsxFile, $this->odsFile, $this->txtFile] as $file) {
            if (isset($file) && file_exists($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    /** @test */
    public function it_creates_csv_reader_for_csv()
    {
        $reader = ReaderFactory::create($this->csvFile);

        $this->assertInstanceOf(CsvReader::class, $reader);
    }

    /** @test */
    public function it_creates_excel_reader_for_xlsx()
    {
        $reader = ReaderFactory::create($this->xlsxFile);

        $this->assertInstanceOf(ExcelReader::class, $reader);
    }

    /** @test */
    public function it_creates_excel_reader_for_ods()
    {
        $reader = ReaderFactory::create($this->odsFile);

        $this->assertInstanceOf(ExcelReader::class, $reader);
    }

    /** @test */
    public function it_auto_detects_comma_delimiter()
    {
        $reader = ReaderFactory::create($this->csvFile);
        $headers = $reader->headers($this->csvFile);

        $this->assertEquals(['name', 'email'], $headers);
    }

    /** @test */
    public function it_auto_detects_semicolon_delimiter()
    {
        $reader = ReaderFactory::create($this->semicolonCsvFile);
        $headers = $reader->headers($this->semicolonCsvFile);

        $this->assertEquals(['name', 'email'], $headers);
    }

    /** @test */
    public function it_throws_for_unsupported_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported file format: txt');

        ReaderFactory::create($this->txtFile);
    }

    /** @test */
    public function it_throws_for_empty_extension()
    {
        $path = tempnam(sys_get_temp_dir(), 'noext_');

        $this->expectException(\InvalidArgumentException::class);

        ReaderFactory::create($path);

        unlink($path);
    }
}
