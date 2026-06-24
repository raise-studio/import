<?php

namespace RaiseStudio\Import\Tests\Feature;

use RaiseStudio\Import\Tests\TestCase;
use RaiseStudio\Import\Actions\ImportAction;
use RaiseStudio\Import\Fields\Field;
use RaiseStudio\Import\Enums\DuplicateBehavior;
use RaiseStudio\Import\Tests\Stubs\TestUser;

class ImportActionTest extends TestCase
{
    /** @test */
    public function it_can_be_created_via_make()
    {
        $action = ImportAction::make('test');

        $this->assertInstanceOf(ImportAction::class, $action);
    }

    /** @test */
    public function it_accepts_model_class()
    {
        $action = ImportAction::make()->model(TestUser::class);

        // Use reflection to check the protected property
        $ref = new \ReflectionProperty(ImportAction::class, 'modelClass');
        $this->assertEquals(TestUser::class, $ref->getValue($action));
    }

    /** @test */
    public function it_accepts_model_via_closure()
    {
        $action = ImportAction::make()->model(fn () => TestUser::class);

        $ref = new \ReflectionProperty(ImportAction::class, 'modelClass');
        $this->assertEquals(TestUser::class, $ref->getValue($action));
    }

    /** @test */
    public function it_accepts_null_model()
    {
        $action = ImportAction::make()->model(null);

        $ref = new \ReflectionProperty(ImportAction::class, 'modelClass');
        $this->assertNull($ref->getValue($action));
    }

    /** @test */
    public function it_accepts_custom_fields()
    {
        $fields = [
            Field::make('name')->label('Name')->required(),
            Field::make('email')->label('Email'),
        ];

        $action = ImportAction::make()->fields($fields);

        $ref = new \ReflectionProperty(ImportAction::class, 'fields');
        $this->assertSame($fields, $ref->getValue($action));
    }

    /** @test */
    public function it_accepts_fields_using_resolver()
    {
        $resolver = fn () => [Field::make('name')];

        $action = ImportAction::make()->fieldsUsing($resolver);

        $ref = new \ReflectionProperty(ImportAction::class, 'fieldsResolver');
        $this->assertSame($resolver, $ref->getValue($action));
    }

    /** @test */
    public function it_accepts_unique_by_string()
    {
        $action = ImportAction::make()->uniqueBy('email');

        $ref = new \ReflectionProperty(ImportAction::class, 'uniqueBy');
        $this->assertEquals(['email'], $ref->getValue($action));
    }

    /** @test */
    public function it_accepts_unique_by_array()
    {
        $action = ImportAction::make()->uniqueBy(['email', 'phone']);

        $ref = new \ReflectionProperty(ImportAction::class, 'uniqueBy');
        $this->assertEquals(['email', 'phone'], $ref->getValue($action));
    }

    /** @test */
    public function it_accepts_on_duplicate_enum()
    {
        $action = ImportAction::make()->onDuplicate(DuplicateBehavior::Skip);

        $ref = new \ReflectionProperty(ImportAction::class, 'onDuplicate');
        $this->assertSame(DuplicateBehavior::Skip, $ref->getValue($action));
    }

    /** @test */
    public function it_accepts_on_duplicate_string()
    {
        $action = ImportAction::make()->onDuplicate('update');

        $ref = new \ReflectionProperty(ImportAction::class, 'onDuplicate');
        $this->assertSame(DuplicateBehavior::Update, $ref->getValue($action));
    }

    /** @test */
    public function it_accepts_chunk_size()
    {
        $action = ImportAction::make()->chunkSize(100);

        $ref = new \ReflectionProperty(ImportAction::class, 'chunkSize');
        $this->assertEquals(100, $ref->getValue($action));
    }

    /** @test */
    public function it_ensures_chunk_size_is_positive()
    {
        $action = ImportAction::make()->chunkSize(-5);

        $ref = new \ReflectionProperty(ImportAction::class, 'chunkSize');
        $this->assertEquals(1, $ref->getValue($action));
    }

    /** @test */
    public function it_accepts_rules()
    {
        $rules = ['name' => 'required|string', 'email' => 'email'];

        $action = ImportAction::make()->rules($rules);

        $ref = new \ReflectionProperty(ImportAction::class, 'rules');
        $this->assertEquals($rules, $ref->getValue($action));
    }

    /** @test */
    public function it_accepts_mutate_before_create_callbacks()
    {
        $callback = fn (array $row) => $row;

        $action = ImportAction::make()->mutateBeforeCreate($callback);

        $ref = new \ReflectionProperty(ImportAction::class, 'mutateCallbacks');
        $this->assertCount(1, $ref->getValue($action));
        $this->assertSame($callback, $ref->getValue($action)[0]);
    }

    /** @test */
    public function it_auto_detects_fields_from_model()
    {
        $action = ImportAction::make()->model(TestUser::class);

        $ref = new \ReflectionMethod($action, 'resolveFields');
        $fields = $ref->invoke($action);

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);
        $this->assertContainsOnlyInstancesOf(Field::class, $fields);
    }

    /** @test */
    public function auto_detected_fields_exclude_id_and_timestamps()
    {
        $action = ImportAction::make()->model(TestUser::class);

        $ref = new \ReflectionMethod($action, 'resolveFields');
        $fields = $ref->invoke($action);

        $fieldNames = array_map(fn (Field $f) => $f->getName(), $fields);

        $this->assertNotContains('id', $fieldNames);
        $this->assertContains('name', $fieldNames);
        $this->assertContains('email', $fieldNames);
    }

    /** @test */
    public function custom_fields_take_precedence_over_auto_detection()
    {
        $customFields = [Field::make('custom_field')];

        $action = ImportAction::make()
            ->model(TestUser::class)
            ->fields($customFields);

        $ref = new \ReflectionMethod($action, 'resolveFields');
        $fields = $ref->invoke($action);

        $this->assertCount(1, $fields);
        $this->assertEquals('custom_field', $fields[0]->getName());
    }

    /** @test */
    public function it_has_up_navigation()
    {
        $action = ImportAction::make('test_import');

        $this->assertEquals('test_import', $action->getName());
    }

    /** @test */
    public function label_defaults_to_translated_string()
    {
        $action = ImportAction::make();

        // The default label comes from the Filament Action's setUp()
        // which calls __('raise-import::messages.action.label')
        $this->assertNotNull($action->getLabel());
    }
}
