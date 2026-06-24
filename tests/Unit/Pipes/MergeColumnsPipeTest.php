<?php

namespace RaiseStudio\Import\Tests\Unit\Pipes;

use RaiseStudio\Import\Pipes\MergeColumnsPipe;
use RaiseStudio\Import\Tests\TestCase;

class MergeColumnsPipeTest extends TestCase
{
    /** @test */
    public function it_merges_two_columns()
    {
        $pipe = new MergeColumnsPipe(['first_name', 'last_name'], 'name');

        $result = $pipe->handle(
            ['first_name' => '张', 'last_name' => '三'],
            fn ($row) => $row,
        );

        $this->assertEquals('张 三', $result['name']);
    }

    /** @test */
    public function it_removes_source_columns()
    {
        $pipe = new MergeColumnsPipe(['first_name', 'last_name'], 'name');

        $result = $pipe->handle(
            ['first_name' => '张', 'last_name' => '三', 'email' => 'test@test.com'],
            fn ($row) => $row,
        );

        $this->assertArrayNotHasKey('first_name', $result);
        $this->assertArrayNotHasKey('last_name', $result);
        $this->assertEquals('test@test.com', $result['email']);
    }

    /** @test */
    public function it_uses_custom_separator()
    {
        $pipe = new MergeColumnsPipe(['street', 'city'], 'address', ', ');

        $result = $pipe->handle(
            ['street' => 'Main St', 'city' => 'Beijing'],
            fn ($row) => $row,
        );

        $this->assertEquals('Main St, Beijing', $result['address']);
    }

    /** @test */
    public function it_handles_missing_source_columns()
    {
        $pipe = new MergeColumnsPipe(['first_name', 'last_name'], 'name');

        $result = $pipe->handle(
            ['first_name' => '张'],
            fn ($row) => $row,
        );

        $this->assertEquals('张', $result['name']);
    }

    /** @test */
    public function it_trims_result()
    {
        $pipe = new MergeColumnsPipe(['first_name', 'last_name'], 'name');

        $result = $pipe->handle(
            ['first_name' => '张', 'last_name' => ''],
            fn ($row) => $row,
        );

        $this->assertEquals('张', $result['name']);
    }

    /** @test */
    public function it_merges_multiple_columns()
    {
        $pipe = new MergeColumnsPipe(['street', 'city', 'zip'], 'full_address', ' | ');

        $result = $pipe->handle(
            ['street' => '123 Main', 'city' => 'Shanghai', 'zip' => '200000'],
            fn ($row) => $row,
        );

        $this->assertEquals('123 Main | Shanghai | 200000', $result['full_address']);
    }

    /** @test */
    public function it_skips_when_source_missing_and_target_has_data()
    {
        $pipe = new MergeColumnsPipe(['first_name', 'last_name'], 'notes');

        // Simulates the AdvancedMapper having already merged into notes
        // (first_name → notes, last_name → notes)
        $result = $pipe->handle(
            ['name' => '张三', 'notes' => 'Alice Wang', 'email' => 'a@b.com'],
            fn ($row) => $row,
        );

        $this->assertEquals('Alice Wang', $result['notes']);
        $this->assertEquals('张三', $result['name']);
    }

    /** @test */
    public function it_cleans_stray_source_keys_when_skipping()
    {
        $pipe = new MergeColumnsPipe(['first_name', 'last_name'], 'notes');

        $result = $pipe->handle(
            ['first_name' => '', 'last_name' => '', 'notes' => 'Bob Li'],
            fn ($row) => $row,
        );

        $this->assertEquals('Bob Li', $result['notes']);
        $this->assertArrayNotHasKey('first_name', $result);
        $this->assertArrayNotHasKey('last_name', $result);
    }
}
