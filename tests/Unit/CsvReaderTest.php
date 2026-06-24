<?php

namespace RaiseStudio\Import\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RaiseStudio\Import\Readers\CsvReader;

class CsvReaderTest extends TestCase
{
    protected string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempFile = tempnam(sys_get_temp_dir(), 'csv_test_') . '.csv';

        $content = "name,email,phone\n";
        $content .= "Alice,alice@example.com,13800138000\n";
        $content .= "Bob,bob@example.com,13900139000\n";
        $content .= "Charlie,charlie@example.com,13700137000\n";

        file_put_contents($this->tempFile, $content);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_reads_headers()
    {
        $reader = new CsvReader();
        $headers = $reader->headers($this->tempFile);

        $this->assertEquals(['name', 'email', 'phone'], $headers);
    }

    /** @test */
    public function it_counts_rows()
    {
        $reader = new CsvReader();
        $count = $reader->count($this->tempFile);

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_previews_rows()
    {
        $reader = new CsvReader();
        $preview = $reader->preview($this->tempFile, 2);

        $this->assertCount(2, $preview);
        $this->assertEquals('Alice', $preview[0]['name']);
        $this->assertEquals('Bob', $preview[1]['name']);
    }

    /** @test */
    public function it_reads_all_rows()
    {
        $reader = new CsvReader();
        $rows = iterator_to_array($reader->rows($this->tempFile));

        $this->assertCount(3, $rows);
        $this->assertEquals('alice@example.com', $rows[0]['email']);
        $this->assertEquals('Charlie', $rows[2]['name']);
    }

    /** @test */
    public function it_reads_csv_with_different_separator()
    {
        $file = tempnam(sys_get_temp_dir(), 'csv_sep_') . '.csv';
        $content = "name;email\nFoo;foo@test.com\nBar;bar@test.com\n";
        file_put_contents($file, $content);

        $reader = new CsvReader(separator: ';');
        $rows = iterator_to_array($reader->rows($file));

        $this->assertCount(2, $rows);
        $this->assertEquals('foo@test.com', $rows[0]['email']);

        unlink($file);
    }
}
