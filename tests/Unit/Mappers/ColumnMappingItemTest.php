<?php

namespace RaiseStudio\Import\Tests\Unit\Mappers;

use RaiseStudio\Import\Mappers\ColumnMappingItem;
use RaiseStudio\Import\Tests\TestCase;

class ColumnMappingItemTest extends TestCase
{
    /** @test */
    public function it_creates_a_basic_mapping_item()
    {
        $item = new ColumnMappingItem(
            fileHeader: 'name',
            fieldNames: ['name'],
            sortOrder: 1,
        );

        $this->assertEquals('name', $item->fileHeader);
        $this->assertEquals(['name'], $item->fieldNames);
        $this->assertTrue($item->isMapped());
        $this->assertFalse($item->merged);
        $this->assertFalse($item->ignored);
        $this->assertEquals(1, $item->sortOrder);
    }

    /** @test */
    public function it_is_not_mapped_when_ignored()
    {
        $item = new ColumnMappingItem(
            fileHeader: 'notes',
            fieldNames: [],
            ignored: true,
        );

        $this->assertFalse($item->isMapped());
    }

    /** @test */
    public function it_is_not_mapped_without_field_names()
    {
        $item = new ColumnMappingItem(
            fileHeader: 'unused',
            fieldNames: [],
        );

        $this->assertFalse($item->isMapped());
    }

    /** @test */
    public function it_detects_merge_mode()
    {
        $item = new ColumnMappingItem(
            fileHeader: 'first_name',
            fieldNames: ['name'],
            merged: true,
        );

        $this->assertTrue($item->merged);
        $this->assertTrue($item->isMapped());
    }
}
