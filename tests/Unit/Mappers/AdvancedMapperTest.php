<?php

namespace RaiseStudio\Import\Tests\Unit\Mappers;

use RaiseStudio\Import\Mappers\AdvancedMapper;
use RaiseStudio\Import\Tests\TestCase;

class AdvancedMapperTest extends TestCase
{
    /** @test */
    public function it_applies_one_to_one_mapping()
    {
        $mapper = new AdvancedMapper();
        $mapper->addMapping('name', 'name');
        $mapper->addMapping('email', 'email');

        $result = $mapper->apply([
            'name' => '张三',
            'email' => 'zhang@test.com',
            'phone' => '13800138000',
        ]);

        $this->assertEquals('张三', $result['name']);
        $this->assertEquals('zhang@test.com', $result['email']);
        $this->assertArrayNotHasKey('phone', $result);
    }

    /** @test */
    public function it_handles_column_merging()
    {
        $mapper = new AdvancedMapper();
        $mapper->addMapping('first_name', 'name');
        $mapper->addMapping('last_name', 'name');
        $mapper->addMapping('email', 'email');

        $result = $mapper->apply([
            'first_name' => '张',
            'last_name' => '三',
            'email' => 'test@test.com',
        ]);

        $this->assertEquals('张 三', $result['name']);
        $this->assertEquals('test@test.com', $result['email']);
    }

    /** @test */
    public function it_handles_column_splitting()
    {
        $mapper = new AdvancedMapper();
        $mapper->addMapping('full_name', 'first_name');
        $mapper->addMapping('full_name', 'last_name');
        $mapper->addMapping('email', 'email');

        $result = $mapper->apply([
            'full_name' => 'Alice Wang',
            'email' => 'test@test.com',
        ]);

        // Split: same source column → multiple fields, split by space by default
        $this->assertEquals('Alice', $result['first_name']);
        $this->assertEquals('Wang', $result['last_name']);
        $this->assertEquals('test@test.com', $result['email']);
    }

    /** @test */
    public function it_skips_ignored_columns()
    {
        $mapper = new AdvancedMapper();
        $mapper->addMapping('name', 'name');
        $mapper->ignore('notes');

        $result = $mapper->apply([
            'name' => '张三',
            'notes' => 'some notes',
        ]);

        $this->assertEquals('张三', $result['name']);
        $this->assertArrayNotHasKey('notes', $result);
    }

    /** @test */
    public function it_respects_sort_order()
    {
        $mapper = new AdvancedMapper();
        $mapper->addMapping('phone', 'phone');
        $mapper->addMapping('name', 'name');
        $mapper->addMapping('email', 'email');

        $mapper->reorder(['name', 'email', 'phone']);

        $items = $mapper->all();
        $this->assertCount(3, $items);
        $this->assertEquals('name', $items[0]->fileHeader);
        $this->assertEquals('email', $items[1]->fileHeader);
        $this->assertEquals('phone', $items[2]->fileHeader);
    }

    /** @test */
    public function it_builds_from_form_data_one_to_one()
    {
        $formData = [
            ['file_header' => '姓名', 'field_name' => ['name'], 'ignored' => false],
            ['file_header' => '邮箱', 'field_name' => ['email'], 'ignored' => false],
        ];

        $mapper = AdvancedMapper::fromFormData($formData);

        $result = $mapper->apply([
            '姓名' => '张三',
            '邮箱' => 'zhang@test.com',
            '电话' => '13800138000',
        ]);

        $this->assertEquals('张三', $result['name']);
        $this->assertEquals('zhang@test.com', $result['email']);
        $this->assertArrayNotHasKey('电话', $result);
    }

    /** @test */
    public function it_builds_from_form_data_with_merging()
    {
        $formData = [
            ['file_header' => '姓', 'field_name' => ['name'], 'ignored' => false],
            ['file_header' => '名', 'field_name' => ['name'], 'ignored' => false],
        ];

        $mapper = AdvancedMapper::fromFormData($formData);

        $result = $mapper->apply([
            '姓' => '张',
            '名' => '三',
        ]);

        $this->assertEquals('张 三', $result['name']);
    }

    /** @test */
    public function it_builds_from_form_data_with_ignored()
    {
        $formData = [
            ['file_header' => 'name', 'field_name' => ['name'], 'ignored' => false],
            ['file_header' => 'notes', 'field_name' => [], 'ignored' => true],
        ];

        $mapper = AdvancedMapper::fromFormData($formData);

        $result = $mapper->apply([
            'name' => '张三',
            'notes' => 'internal',
        ]);

        $this->assertEquals('张三', $result['name']);
        $this->assertArrayNotHasKey('notes', $result);
    }

    /** @test */
    public function it_builds_from_form_data_with_split()
    {
        $formData = [
            ['file_header' => 'full_name', 'field_name' => ['first_name', 'last_name'], 'ignored' => false],
            ['file_header' => 'email', 'field_name' => ['email'], 'ignored' => false],
        ];

        $mapper = AdvancedMapper::fromFormData($formData);

        $result = $mapper->apply([
            'full_name' => 'Alice Wang',
            'email' => 'alice@test.com',
        ]);

        $this->assertEquals('Alice', $result['first_name']);
        $this->assertEquals('Wang', $result['last_name']);
        $this->assertEquals('alice@test.com', $result['email']);
    }

    /** @test */
    public function it_handles_column_splitting_without_space()
    {
        $mapper = new AdvancedMapper();
        $mapper->addMapping('full_name', 'first_name');
        $mapper->addMapping('full_name', 'last_name');

        $result = $mapper->apply([
            'full_name' => '张三',
        ]);

        // No space: first field gets full value, second gets empty
        $this->assertEquals('张三', $result['first_name']);
        $this->assertEquals('', $result['last_name']);
    }

    /** @test */
    public function it_merges_three_columns()
    {
        $mapper = new AdvancedMapper();
        $mapper->addMapping('street', 'address');
        $mapper->addMapping('city', 'address');
        $mapper->addMapping('zip', 'address');

        $result = $mapper->apply([
            'street' => 'Main St',
            'city' => 'Beijing',
            'zip' => '100000',
        ]);

        $this->assertEquals('Main St Beijing 100000', $result['address']);
    }
}
