<?php

namespace RaiseStudio\Import\Tests\Unit;

use RaiseStudio\Import\Tests\TestCase;
use RaiseStudio\Import\Tests\Stubs\TestUser;
use RaiseStudio\Import\Validators\BatchValidator;

class BatchValidatorTest extends TestCase
{
    private BatchValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BatchValidator();
    }

    /** @test */
    public function it_builds_empty_index_for_empty_table()
    {
        $index = $this->validator->buildUniquenessIndex(TestUser::class, ['email']);

        $this->assertArrayHasKey('email', $index);
        $this->assertEmpty($index['email']);
    }

    /** @test */
    public function it_builds_index_from_existing_records()
    {
        TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        TestUser::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $index = $this->validator->buildUniquenessIndex(TestUser::class, ['email']);

        $this->assertCount(2, $index['email']);
        $this->assertArrayHasKey('alice@example.com', $index['email']);
        $this->assertArrayHasKey('bob@example.com', $index['email']);
    }

    /** @test */
    public function it_builds_index_with_multiple_unique_fields()
    {
        TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com', 'phone' => '13800138000']);

        $index = $this->validator->buildUniquenessIndex(TestUser::class, ['email', 'phone']);

        $this->assertArrayHasKey('email', $index);
        $this->assertArrayHasKey('phone', $index);
    }

    /** @test */
    public function it_detects_duplicate_against_existing_index()
    {
        TestUser::create(['email' => 'alice@example.com']);
        $index = $this->validator->buildUniquenessIndex(TestUser::class, ['email']);

        $row = ['name' => 'Alice 2', 'email' => 'alice@example.com'];

        $this->assertTrue($this->validator->isDuplicate($row, ['email'], $index));
    }

    /** @test */
    public function it_passes_unique_rows()
    {
        TestUser::create(['email' => 'alice@example.com']);
        $index = $this->validator->buildUniquenessIndex(TestUser::class, ['email']);

        $row = ['name' => 'Charlie', 'email' => 'charlie@example.com'];

        $this->assertFalse($this->validator->isDuplicate($row, ['email'], $index));
    }

    /** @test */
    public function it_detects_incoming_batch_duplicates()
    {
        $index = $this->validator->buildUniquenessIndex(TestUser::class, ['email']);
        $incoming = $this->validator->buildIncomingIndex([], ['email']);

        // First occurrence of a value — NOT a duplicate
        $firstRow = ['name' => 'Alice', 'email' => 'alice@example.com'];
        $isFirstDup = $this->validator->isDuplicate($firstRow, ['email'], $index, $incoming, 0);
        $this->assertFalse($isFirstDup);

        // Second occurrence of same email — IS a duplicate (in-batch)
        $secondRow = ['name' => 'Alice Dup', 'email' => 'alice@example.com'];
        $isSecondDup = $this->validator->isDuplicate($secondRow, ['email'], $index, $incoming, 1);
        $this->assertTrue($isSecondDup);
    }

    /** @test */
    public function it_detects_composite_duplicates()
    {
        TestUser::create(['email' => 'alice@example.com', 'phone' => '13800138000']);
        $index = $this->validator->buildUniquenessIndex(TestUser::class, ['email', 'phone']);

        // Same email but different phone — NOT duplicate (composite check matches any unique field)
        $row = ['email' => 'alice@example.com', 'phone' => '13900139000'];

        // Composite uniqueBy: "unique" means at least one field matches exists
        $this->assertTrue($this->validator->isDuplicate($row, ['email', 'phone'], $index));
    }

    /** @test */
    public function it_handles_null_values_in_row()
    {
        $index = $this->validator->buildUniquenessIndex(TestUser::class, ['email']);

        $row = ['name' => 'No Email'];

        $this->assertFalse($this->validator->isDuplicate($row, ['email'], $index));
    }
}
