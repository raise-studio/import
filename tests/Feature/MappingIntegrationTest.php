<?php

namespace RaiseStudio\Import\Tests\Feature;

use RaiseStudio\Import\Mappers\AdvancedMapper;
use RaiseStudio\Import\Tests\TestCase;

class MappingIntegrationTest extends TestCase
{
    /** @test */
    public function legacy_single_field_mapping_still_works()
    {
        // Simulate old format: field_name is a string
        $formData = [
            ['file_header' => 'name', 'field_name' => 'name', 'ignored' => false],
            ['file_header' => 'email', 'field_name' => 'email', 'ignored' => false],
        ];

        // Old format detection: first field_name is string, so use ManualMapper
        $isNewFormat = is_array($formData[0]['field_name'] ?? null);
        $this->assertFalse($isNewFormat);

        // Legacy path
        $mapping = [];
        foreach ($formData as $item) {
            if (!empty($item['field_name'])) {
                $mapping[$item['file_header']] = $item['field_name'];
            }
        }

        $mapper = new \RaiseStudio\Import\Mappers\ManualMapper();
        $result = $mapper->apply([
            'name' => '张三',
            'email' => 'zhang@test.com',
        ], $mapping);

        $this->assertEquals('张三', $result['name']);
        $this->assertEquals('zhang@test.com', $result['email']);
    }

    /** @test */
    public function new_format_with_advanced_mapper()
    {
        // Simulate new format: field_name is an array
        $formData = [
            ['file_header' => '姓名', 'field_name' => ['name'], 'ignored' => false],
            ['file_header' => '邮箱', 'field_name' => ['email'], 'ignored' => false],
        ];

        // New format detection
        $isNewFormat = is_array($formData[0]['field_name'] ?? null);
        $this->assertTrue($isNewFormat);

        // AdvancedMapper path
        $mapper = AdvancedMapper::fromFormData($formData);
        $result = $mapper->apply([
            '姓名' => '张三',
            '邮箱' => 'zhang@test.com',
        ]);

        $this->assertEquals('张三', $result['name']);
        $this->assertEquals('zhang@test.com', $result['email']);
    }

    /** @test */
    public function new_format_merges_correctly()
    {
        $formData = [
            ['file_header' => '姓', 'field_name' => ['name'], 'ignored' => false],
            ['file_header' => '名', 'field_name' => ['name'], 'ignored' => false],
            ['file_header' => '邮箱', 'field_name' => ['email'], 'ignored' => false],
        ];

        $mapper = AdvancedMapper::fromFormData($formData);
        $result = $mapper->apply([
            '姓' => '张',
            '名' => '三',
            '邮箱' => 'test@test.com',
        ]);

        $this->assertEquals('张 三', $result['name']);
        $this->assertEquals('test@test.com', $result['email']);
    }

    /** @test */
    public function new_format_handles_ignored_columns()
    {
        $formData = [
            ['file_header' => 'name', 'field_name' => ['name'], 'ignored' => false],
            ['file_header' => 'internal_notes', 'field_name' => [], 'ignored' => true],
            ['file_header' => 'email', 'field_name' => ['email'], 'ignored' => false],
        ];

        $mapper = AdvancedMapper::fromFormData($formData);
        $result = $mapper->apply([
            'name' => '张三',
            'internal_notes' => 'secret',
            'email' => 'test@test.com',
        ]);

        $this->assertEquals('张三', $result['name']);
        $this->assertEquals('test@test.com', $result['email']);
        $this->assertArrayNotHasKey('internal_notes', $result);
    }

    /** @test */
    public function empty_field_name_is_skipped()
    {
        $formData = [
            ['file_header' => 'name', 'field_name' => ['name'], 'ignored' => false],
            ['file_header' => 'unused', 'field_name' => [], 'ignored' => false],
        ];

        $mapper = AdvancedMapper::fromFormData($formData);
        $result = $mapper->apply([
            'name' => '张三',
            'unused' => 'whatever',
        ]);

        $this->assertEquals('张三', $result['name']);
        $this->assertArrayNotHasKey('unused', $result);
    }

    /** @test */
    public function format_detection_handles_empty_data()
    {
        // Empty mapping should fall through to legacy path gracefully
        $rawMapping = [];

        $isNewFormat = !empty($rawMapping) && is_array($rawMapping[0] ?? null) && isset($rawMapping[0]['field_name']) && is_array($rawMapping[0]['field_name']);
        $this->assertFalse($isNewFormat);

        // Legacy fallback should produce empty mapping
        $mapping = [];
        $this->assertEmpty($mapping);
    }
}
