<?php

namespace RaiseStudio\Import\Tests\Unit\Pipes;

use RaiseStudio\Import\Pipes\SplitColumnPipe;
use RaiseStudio\Import\Tests\TestCase;

class SplitColumnPipeTest extends TestCase
{
    /** @test */
    public function it_splits_a_column_by_delimiter()
    {
        $pipe = new SplitColumnPipe('full_name', ['first_name', 'last_name']);

        $result = $pipe->handle(
            ['full_name' => '张 三', 'email' => 'test@test.com'],
            fn ($row) => $row,
        );

        $this->assertEquals('张', $result['first_name']);
        $this->assertEquals('三', $result['last_name']);
        $this->assertEquals('test@test.com', $result['email']);
        $this->assertArrayNotHasKey('full_name', $result);
    }

    /** @test */
    public function it_uses_custom_delimiter()
    {
        $pipe = new SplitColumnPipe('full_name', ['first_name', 'last_name'], ',');

        $result = $pipe->handle(
            ['full_name' => '张三,李四'],
            fn ($row) => $row,
        );

        $this->assertEquals('张三', $result['first_name']);
        $this->assertEquals('李四', $result['last_name']);
    }

    /** @test */
    public function it_uses_custom_splitter_callback()
    {
        $pipe = new SplitColumnPipe(
            'full_name',
            ['first_char', 'rest'],
            splitter: fn ($value) => [mb_substr($value, 0, 1), mb_substr($value, 1)],
        );

        $result = $pipe->handle(
            ['full_name' => '张三'],
            fn ($row) => $row,
        );

        $this->assertEquals('张', $result['first_char']);
        $this->assertEquals('三', $result['rest']);
    }

    /** @test */
    public function it_handles_missing_source_column()
    {
        $pipe = new SplitColumnPipe('full_name', ['first_name', 'last_name']);

        $result = $pipe->handle(
            ['email' => 'test@test.com'],
            fn ($row) => $row,
        );

        $this->assertEquals('test@test.com', $result['email']);
        $this->assertArrayNotHasKey('first_name', $result);
        $this->assertArrayNotHasKey('last_name', $result);
    }

    /** @test */
    public function it_pads_missing_parts_with_empty_string()
    {
        $pipe = new SplitColumnPipe('full_name', ['first_name', 'middle_name', 'last_name']);

        $result = $pipe->handle(
            ['full_name' => '张 三'],
            fn ($row) => $row,
        );

        $this->assertEquals('张', $result['first_name']);
        $this->assertEquals('三', $result['middle_name']);
        $this->assertEquals('', $result['last_name']);
    }

    /** @test */
    public function it_splits_into_more_than_two_fields()
    {
        $pipe = new SplitColumnPipe('full_address', ['street', 'city', 'zip'], ',');

        $result = $pipe->handle(
            ['full_address' => '123 Main,Beijing,100000'],
            fn ($row) => $row,
        );

        $this->assertEquals('123 Main', $result['street']);
        $this->assertEquals('Beijing', $result['city']);
        $this->assertEquals('100000', $result['zip']);
    }
}
