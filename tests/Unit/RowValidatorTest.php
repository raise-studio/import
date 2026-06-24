<?php

namespace RaiseStudio\Import\Tests\Unit;

use RaiseStudio\Import\Tests\TestCase;
use RaiseStudio\Import\Fields\Field;
use RaiseStudio\Import\Validators\RowValidator;

class RowValidatorTest extends TestCase
{
    private RowValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new RowValidator();
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $fields = [
            Field::make('name')->required(),
            Field::make('email')->rules('email'),
        ];

        $row = ['name' => '', 'email' => 'not-an-email'];

        $validated = $this->validator->validate($row, $fields, 1);

        $this->assertTrue($this->validator->hasErrors());
        $this->assertEmpty($validated);
    }

    /** @test */
    public function it_passes_valid_rows()
    {
        $fields = [
            Field::make('name')->required()->rules('string|max:100'),
            Field::make('email')->rules('email'),
        ];

        $row = ['name' => 'John Doe', 'email' => 'john@example.com'];

        $validated = $this->validator->validate($row, $fields, 1);

        $this->assertFalse($this->validator->hasErrors());
    }

    /** @test */
    public function it_applies_defaults_for_missing_fields()
    {
        $fields = [
            Field::make('name')->required(),
            Field::make('status')->default('active'),
        ];

        $row = ['name' => 'John'];

        $validated = $this->validator->validate($row, $fields, 1);

        $this->assertFalse($this->validator->hasErrors());
        $this->assertEquals('active', $validated['status']);
    }

    /** @test */
    public function it_returns_errors_in_expected_format()
    {
        $fields = [
            Field::make('name')->required(),
            Field::make('email')->rules('email'),
        ];

        $row = ['name' => '', 'email' => 'bad'];

        $this->validator->validate($row, $fields, 5);

        $errors = $this->validator->getErrors();

        $this->assertNotEmpty($errors);
        foreach ($errors as $error) {
            $this->assertArrayHasKey('row', $error);
            $this->assertArrayHasKey('field', $error);
            $this->assertArrayHasKey('value', $error);
            $this->assertArrayHasKey('error', $error);
            $this->assertEquals(5, $error['row']);
        }
    }

    /** @test */
    public function it_tracks_multiple_errors_on_same_row()
    {
        $fields = [
            Field::make('name')->required(),
            Field::make('email')->rules('email')->required(),
        ];

        $row = ['name' => '', 'email' => ''];


        $this->validator->validate($row, $fields, 1);

        $this->assertCount(2, $this->validator->getErrors());
    }

    /** @test */
    public function it_tracks_errors_across_multiple_rows()
    {
        $fields = [Field::make('name')->required()];

        $this->validator->validate(['name' => ''], $fields, 1);
        $this->validator->validate(['name' => ''], $fields, 2);

        $this->assertCount(2, $this->validator->getErrors());
        $this->assertEquals(1, $this->validator->getErrors()[0]['row']);
        $this->assertEquals(2, $this->validator->getErrors()[1]['row']);
    }

    /** @test */
    public function it_resets_errors()
    {
        $fields = [Field::make('name')->required()];

        $this->validator->validate(['name' => ''], $fields, 1);
        $this->assertTrue($this->validator->hasErrors());

        $this->validator->reset();
        $this->assertFalse($this->validator->hasErrors());
        $this->assertEmpty($this->validator->getErrors());
    }

    /** @test */
    public function it_handles_fields_without_rules()
    {
        $fields = [
            Field::make('name'),
            Field::make('email'),
        ];

        $row = ['name' => 'John', 'email' => 'john@example.com'];

        $validated = $this->validator->validate($row, $fields, 1);

        $this->assertFalse($this->validator->hasErrors());
        $this->assertEquals($row, $validated);
    }

    /** @test */
    public function it_uses_default_value_when_field_is_missing_from_row()
    {
        $fields = [
            Field::make('name')->required(),
            Field::make('status')->default('active')->rules('in:active,inactive'),
        ];

        $row = ['name' => 'John'];

        $validated = $this->validator->validate($row, $fields, 1);

        $this->assertFalse($this->validator->hasErrors());
        $this->assertEquals('active', $validated['status']);
    }

    /** @test */
    public function it_validates_option_fields()
    {
        $fields = [
            Field::make('status')->rules('in:active,inactive'),
        ];

        $row = ['status' => 'invalid_value'];

        $validated = $this->validator->validate($row, $fields, 1);

        $this->assertTrue($this->validator->hasErrors());
    }
}
