<?php

namespace RaiseStudio\Import\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RaiseStudio\Import\Fields\Field;

class FieldTest extends TestCase
{
    /** @test */
    public function it_can_be_created_via_make()
    {
        $field = Field::make('email');

        $this->assertInstanceOf(Field::class, $field);
        $this->assertEquals('email', $field->getName());
    }

    /** @test */
    public function it_returns_name_when_no_label_set()
    {
        $field = Field::make('first_name');

        $this->assertEquals('first_name', $field->getLabel());
    }

    /** @test */
    public function it_accepts_custom_label()
    {
        $field = Field::make('email')->label('Email Address');

        $this->assertEquals('Email Address', $field->getLabel());
    }

    /** @test */
    public function label_returns_self_for_chaining()
    {
        $result = Field::make('x')->label('X');

        $this->assertInstanceOf(Field::class, $result);
    }

    /** @test */
    public function it_supports_array_rules()
    {
        $field = Field::make('email')->rules(['required', 'email', 'max:255']);

        $this->assertEquals(['required', 'email', 'max:255'], $field->getRules());
    }

    /** @test */
    public function it_supports_pipe_string_rules()
    {
        $field = Field::make('name')->rules('required|string|max:100');

        $this->assertEquals(['required', 'string', 'max:100'], $field->getRules());
    }

    /** @test */
    public function rules_returns_self_for_chaining()
    {
        $result = Field::make('x')->rules('required');

        $this->assertInstanceOf(Field::class, $result);
    }

    /** @test */
    public function it_stores_default_value()
    {
        $field = Field::make('status')->default('active');

        $this->assertEquals('active', $field->getDefault());
    }

    /** @test */
    public function default_is_null_when_not_set()
    {
        $field = Field::make('name');

        $this->assertNull($field->getDefault());
    }

    /** @test */
    public function it_stores_and_returns_options()
    {
        $options = ['active' => 'Active', 'inactive' => 'Inactive'];
        $field = Field::make('status')->options($options);

        $this->assertEquals($options, $field->getOptions());
    }

    /** @test */
    public function options_are_null_when_not_set()
    {
        $field = Field::make('name');

        $this->assertNull($field->getOptions());
    }

    /** @test */
    public function it_marks_field_as_required()
    {
        $field = Field::make('name')->required();

        $this->assertTrue($field->isRequired());
    }

    /** @test */
    public function it_marks_field_as_not_required_by_default()
    {
        $field = Field::make('name');

        $this->assertFalse($field->isRequired());
    }

    /** @test */
    public function it_can_disable_required()
    {
        $field = Field::make('name')->required(false);

        $this->assertFalse($field->isRequired());
    }

    /** @test */
    public function all_setters_are_chainable()
    {
        $field = Field::make('email')
            ->label('Email')
            ->rules('required|email')
            ->default('test@example.com')
            ->required();

        $this->assertEquals('Email', $field->getLabel());
        $this->assertEquals(['required', 'email'], $field->getRules());
        $this->assertEquals('test@example.com', $field->getDefault());
        $this->assertTrue($field->isRequired());
    }

    /** @test */
    public function constructor_arguments_are_optional()
    {
        $field = new Field('test');

        $this->assertEquals('test', $field->getName());
        $this->assertEquals('test', $field->getLabel());  // falls back to name
        $this->assertEquals([], $field->getRules());
        $this->assertNull($field->getDefault());
        $this->assertNull($field->getOptions());
        $this->assertFalse($field->isRequired());
    }
}
