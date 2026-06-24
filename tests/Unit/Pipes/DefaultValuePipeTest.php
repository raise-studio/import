<?php

namespace RaiseStudio\Import\Tests\Unit\Pipes;

use RaiseStudio\Import\Pipes\DefaultValuePipe;
use RaiseStudio\Import\Tests\TestCase;

class DefaultValuePipeTest extends TestCase
{
    /** @test */
    public function it_fills_missing_fields()
    {
        $pipe = new DefaultValuePipe(['status' => 'active', 'type' => 'user']);

        $result = $pipe->handle(
            ['name' => 'Alice'],
            fn ($row) => $row,
        );

        $this->assertEquals('active', $result['status']);
        $this->assertEquals('user', $result['type']);
    }

    /** @test */
    public function it_does_not_override_existing_values()
    {
        $pipe = new DefaultValuePipe(['status' => 'active']);

        $result = $pipe->handle(
            ['name' => 'Alice', 'status' => 'inactive'],
            fn ($row) => $row,
        );

        $this->assertEquals('inactive', $result['status']);
    }

    /** @test */
    public function it_fills_empty_string()
    {
        $pipe = new DefaultValuePipe(['status' => 'active']);

        $result = $pipe->handle(
            ['name' => 'Alice', 'status' => ''],
            fn ($row) => $row,
        );

        $this->assertEquals('active', $result['status']);
    }

    /** @test */
    public function it_fills_null_value()
    {
        $pipe = new DefaultValuePipe(['status' => 'active']);

        $result = $pipe->handle(
            ['name' => 'Alice', 'status' => null],
            fn ($row) => $row,
        );

        $this->assertEquals('active', $result['status']);
    }

    /** @test */
    public function it_retains_falsy_values_that_are_not_null_or_empty()
    {
        $pipe = new DefaultValuePipe(['count' => 100]);

        $result = $pipe->handle(
            ['count' => 0],
            fn ($row) => $row,
        );

        $this->assertSame(0, $result['count']);
    }

    /** @test */
    public function it_handles_multiple_defaults()
    {
        $pipe = new DefaultValuePipe([
            'status' => 'active',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $result = $pipe->handle(
            ['name' => 'Alice', 'role' => 'admin'],
            fn ($row) => $row,
        );

        $this->assertEquals('active', $result['status']);
        $this->assertEquals('admin', $result['role']);
        $this->assertEquals('en', $result['locale']);
    }
}
