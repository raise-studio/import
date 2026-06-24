<?php

namespace RaiseStudio\Import\Tests\Unit\Pipes;

use RaiseStudio\Import\Pipes\DateFormatPipe;
use RaiseStudio\Import\Tests\TestCase;

class DateFormatPipeTest extends TestCase
{
    /** @test */
    public function it_converts_date_format()
    {
        $pipe = new DateFormatPipe('Y-m-d', ['birthday']);

        $result = $pipe->handle(
            ['birthday' => '2024/01/15'],
            fn ($row) => $row,
        );

        $this->assertEquals('2024-01-15', $result['birthday']);
    }

    /** @test */
    public function it_converts_multiple_date_fields()
    {
        $pipe = new DateFormatPipe('Y-m-d H:i:s', ['created_at', 'updated_at']);

        $result = $pipe->handle(
            ['created_at' => '2024-01-01', 'updated_at' => '2024-06-15'],
            fn ($row) => $row,
        );

        $this->assertEquals('2024-01-01 00:00:00', $result['created_at']);
        $this->assertEquals('2024-06-15 00:00:00', $result['updated_at']);
    }

    /** @test */
    public function it_keeps_original_value_on_parse_failure()
    {
        $pipe = new DateFormatPipe('Y-m-d', ['birthday']);

        $result = $pipe->handle(
            ['birthday' => 'not-a-date'],
            fn ($row) => $row,
        );

        $this->assertEquals('not-a-date', $result['birthday']);
    }

    /** @test */
    public function it_handles_missing_field()
    {
        $pipe = new DateFormatPipe('Y-m-d', ['birthday']);

        $result = $pipe->handle(
            ['name' => 'Alice'],
            fn ($row) => $row,
        );

        $this->assertEquals(['name' => 'Alice'], $result);
    }

    /** @test */
    public function it_handles_null_value()
    {
        $pipe = new DateFormatPipe('Y-m-d', ['birthday']);

        $result = $pipe->handle(
            ['birthday' => null],
            fn ($row) => $row,
        );

        $this->assertNull($result['birthday']);
    }

    /** @test */
    public function it_uses_default_format_and_fields()
    {
        $pipe = new DateFormatPipe();

        $result = $pipe->handle(
            ['created_at' => '2024-01-01', 'name' => 'Alice'],
            fn ($row) => $row,
        );

        $this->assertEquals('2024-01-01 00:00:00', $result['created_at']);
        $this->assertEquals('Alice', $result['name']);
    }

    /** @test */
    public function it_handles_different_date_formats()
    {
        $pipe = new DateFormatPipe('Y-m-d', ['date']);

        $formats = [
            '2024/01/15' => '2024-01-15',
            '01/15/2024' => '2024-01-15',
            '15-Jan-2024' => '2024-01-15',
            'January 15, 2024' => '2024-01-15',
            '2024-01-15' => '2024-01-15',
        ];

        foreach ($formats as $input => $expected) {
            $result = $pipe->handle(['date' => $input], fn ($row) => $row);
            $this->assertEquals($expected, $result['date'], "Failed for input: $input");
        }
    }
}
