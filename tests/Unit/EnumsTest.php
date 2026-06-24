<?php

namespace RaiseStudio\Import\Tests\Unit;

use RaiseStudio\Import\Tests\TestCase;
use RaiseStudio\Import\Enums\DuplicateBehavior;
use RaiseStudio\Import\Enums\ImportStatus;

class EnumsTest extends TestCase
{
    /** @test */
    public function duplicate_behavior_has_three_cases()
    {
        $this->assertCount(3, DuplicateBehavior::cases());
    }

    /** @test */
    public function duplicate_behavior_values_are_correct()
    {
        $this->assertEquals('skip', DuplicateBehavior::Skip->value);
        $this->assertEquals('update', DuplicateBehavior::Update->value);
        $this->assertEquals('error', DuplicateBehavior::Error->value);
    }

    /** @test */
    public function duplicate_behavior_from_string()
    {
        $this->assertSame(DuplicateBehavior::Skip, DuplicateBehavior::from('skip'));
        $this->assertSame(DuplicateBehavior::Update, DuplicateBehavior::from('update'));
        $this->assertSame(DuplicateBehavior::Error, DuplicateBehavior::from('error'));
    }

    /** @test */
    public function import_status_has_six_cases()
    {
        $this->assertCount(6, ImportStatus::cases());
    }

    /** @test */
    public function import_status_values_are_correct()
    {
        $this->assertEquals('pending', ImportStatus::Pending->value);
        $this->assertEquals('previewing', ImportStatus::Previewing->value);
        $this->assertEquals('processing', ImportStatus::Processing->value);
        $this->assertEquals('completed', ImportStatus::Completed->value);
        $this->assertEquals('failed', ImportStatus::Failed->value);
        $this->assertEquals('partial', ImportStatus::Partial->value);
    }

    /** @test */
    public function import_status_has_label_for_each_case()
    {
        foreach (ImportStatus::cases() as $status) {
            $label = $status->label();
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }

    /** @test */
    public function import_status_has_color_for_each_case()
    {
        foreach (ImportStatus::cases() as $status) {
            $color = $status->color();
            $this->assertIsString($color);
            $this->assertNotEmpty($color);
        }
    }

    /** @test */
    public function completed_status_has_success_color()
    {
        $this->assertEquals('success', ImportStatus::Completed->color());
    }

    /** @test */
    public function failed_status_has_danger_color()
    {
        $this->assertEquals('danger', ImportStatus::Failed->color());
    }
}
