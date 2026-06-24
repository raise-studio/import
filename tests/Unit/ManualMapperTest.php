<?php

namespace RaiseStudio\Import\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RaiseStudio\Import\Mappers\ManualMapper;

class ManualMapperTest extends TestCase
{
    private ManualMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ManualMapper();
    }

    /** @test */
    public function it_maps_fields_one_to_one()
    {
        $row = [
            'Name' => 'Alice Wang',
            'Email' => 'alice@example.com',
            'Phone' => '13800138000',
        ];

        $mapping = [
            'Name' => 'name',
            'Email' => 'email',
            'Phone' => 'phone',
        ];

        $result = $this->mapper->apply($row, $mapping);

        $this->assertEquals([
            'name' => 'Alice Wang',
            'email' => 'alice@example.com',
            'phone' => '13800138000',
        ], $result);
    }

    /** @test */
    public function it_skips_empty_field_names()
    {
        $row = [
            'Name' => 'Alice Wang',
            'Extra' => 'ignored value',
        ];

        $mapping = [
            'Name' => 'name',
            'Extra' => '',
        ];

        $result = $this->mapper->apply($row, $mapping);

        $this->assertEquals(['name' => 'Alice Wang'], $result);
        $this->assertArrayNotHasKey('Extra', $result);
    }

    /** @test */
    public function it_skips_null_field_names()
    {
        $row = ['Name' => 'Alice'];

        $mapping = ['Name' => null];

        $result = $this->mapper->apply($row, $mapping);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_returns_null_for_missing_row_values()
    {
        $row = ['Name' => 'Alice'];

        $mapping = ['Name' => 'name', 'Missing' => 'phone'];

        $result = $this->mapper->apply($row, $mapping);

        $this->assertEquals('Alice', $result['name']);
        $this->assertNull($result['phone']);
    }

    /** @test */
    public function it_renames_columns_according_to_mapping()
    {
        $row = ['first_name' => 'Alice', 'last_name' => 'Wang'];

        $mapping = [
            'first_name' => 'given_name',
            'last_name' => 'family_name',
        ];

        $result = $this->mapper->apply($row, $mapping);

        $this->assertEquals('Alice', $result['given_name']);
        $this->assertEquals('Wang', $result['family_name']);
        $this->assertArrayNotHasKey('first_name', $result);
        $this->assertArrayNotHasKey('last_name', $result);
    }

    /** @test */
    public function it_handles_empty_row()
    {
        $result = $this->mapper->apply([], ['Name' => 'name']);

        $this->assertEquals(['name' => null], $result);
    }

    /** @test */
    public function it_handles_empty_mapping()
    {
        $row = ['Name' => 'Alice', 'Email' => 'alice@example.com'];

        $result = $this->mapper->apply($row, []);

        $this->assertEmpty($result);
    }
}
