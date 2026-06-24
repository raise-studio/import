<?php

namespace RaiseStudio\Import\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RaiseStudio\Import\Exports\TemplateExport;
use RaiseStudio\Import\Fields\Field;

class TemplateExportTest extends TestCase
{
    /** @test */
    public function it_generates_csv_template()
    {
        $fields = [
            Field::make('name')->label('姓名'),
            Field::make('email')->label('邮箱'),
            Field::make('status')->label('状态')->default('active'),
        ];

        $export = new TemplateExport();
        $csv = $export->generate($fields);

        $lines = explode("\n", trim($csv));

        $this->assertStringContainsString('姓名,邮箱,状态', $lines[0]);
        $this->assertStringContainsString('active', $lines[1]);
    }
}
