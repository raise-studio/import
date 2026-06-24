<?php

namespace RaiseStudio\Import\Tests\Unit\Pipes;

use RaiseStudio\Import\Pipes\TrimStringsPipe;
use RaiseStudio\Import\Tests\TestCase;

class TrimStringsPipeTest extends TestCase
{
    private TrimStringsPipe $pipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pipe = new TrimStringsPipe();
    }

    /** @test */
    public function it_removes_leading_and_trailing_whitespace()
    {
        $result = $this->pipe->handle(
            ['name' => '  Alice  '],
            fn ($row) => $row,
        );

        $this->assertEquals('Alice', $result['name']);
    }

    /** @test */
    public function it_trims_all_string_fields()
    {
        $result = $this->pipe->handle(
            ['name' => '  Alice  ', 'email' => '  alice@test.com  ', 'phone' => '  12345  '],
            fn ($row) => $row,
        );

        $this->assertEquals('Alice', $result['name']);
        $this->assertEquals('alice@test.com', $result['email']);
        $this->assertEquals('12345', $result['phone']);
    }

    /** @test */
    public function it_ignores_non_string_values()
    {
        $result = $this->pipe->handle(
            ['age' => 25, 'active' => true, 'count' => 0],
            fn ($row) => $row,
        );

        $this->assertSame(25, $result['age']);
        $this->assertTrue($result['active']);
        $this->assertSame(0, $result['count']);
    }

    /** @test */
    public function it_handles_empty_string()
    {
        $result = $this->pipe->handle(
            ['name' => ''],
            fn ($row) => $row,
        );

        $this->assertEquals('', $result['name']);
    }

    /** @test */
    public function it_handles_null_value()
    {
        $result = $this->pipe->handle(
            ['name' => null],
            fn ($row) => $row,
        );

        $this->assertNull($result['name']);
    }

    /** @test */
    public function it_passes_to_next_pipe()
    {
        $nextCalled = false;

        $this->pipe->handle(['name' => '  Alice  '], function ($row) use (&$nextCalled) {
            $nextCalled = true;
            return $row;
        });

        $this->assertTrue($nextCalled);
    }
}
