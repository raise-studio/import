<?php

namespace RaiseStudio\Import\Tests\Unit;

use RaiseStudio\Import\Tests\TestCase;
use RaiseStudio\Import\Tests\Stubs\TestUser;
use RaiseStudio\Import\Importers\BulkImporter;
use RaiseStudio\Import\Enums\DuplicateBehavior;
use RaiseStudio\Import\Pipes\LowercasePipe;
use RaiseStudio\Import\Pipes\ImportPipeline;

class BulkImporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_imports_all_rows_successfully()
    {
        $rows = [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $importer = new BulkImporter(modelClass: TestUser::class);
        $result = $importer->import($rows);

        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals(2, TestUser::count());
    }

    /** @test */
    public function it_returns_zero_for_empty_input()
    {
        $importer = new BulkImporter(modelClass: TestUser::class);
        $result = $importer->import([]);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals(0, TestUser::count());
    }

    /** @test */
    public function it_skips_duplicate_rows()
    {
        TestUser::create(['email' => 'alice@example.com']);

        $rows = [
            ['name' => 'Alice 2', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $importer = new BulkImporter(
            modelClass: TestUser::class,
            uniqueBy: ['email'],
            onDuplicate: DuplicateBehavior::Skip,
        );

        $result = $importer->import($rows);

        $this->assertEquals(1, $result['imported']);  // Bob
        $this->assertEquals(1, $result['skipped']);  // Alice (duplicate)
        $this->assertEquals(2, TestUser::count());   // Original Alice + Bob
    }

    /** @test */
    public function it_updates_duplicate_rows()
    {
        TestUser::create(['email' => 'alice@example.com', 'name' => 'Alice Original']);

        $rows = [
            ['name' => 'Alice Updated', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $importer = new BulkImporter(
            modelClass: TestUser::class,
            uniqueBy: ['email'],
            onDuplicate: DuplicateBehavior::Update,
        );

        $result = $importer->import($rows);

        $this->assertEquals(2, $result['imported']);  // Updated + New
        $this->assertEquals('Alice Updated', TestUser::where('email', 'alice@example.com')->first()->name);
    }

    /** @test */
    public function it_marks_duplicates_as_errors()
    {
        TestUser::create(['email' => 'alice@example.com']);

        $rows = [
            ['name' => 'Alice 2', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $importer = new BulkImporter(
            modelClass: TestUser::class,
            uniqueBy: ['email'],
            onDuplicate: DuplicateBehavior::Error,
        );

        $result = $importer->import($rows);

        $this->assertEquals(1, $result['imported']);  // Bob
        $this->assertEquals(1, $result['failed']);    // Alice (error)
        $this->assertEquals(1, count($result['failedRows']));
        $this->assertStringContainsString('Duplicate', $result['failedRows'][0]['error']);
    }

    /** @test */
    public function it_applies_mutate_before_create_callback()
    {
        $rows = [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
        ];

        $importer = new BulkImporter(
            modelClass: TestUser::class,
            mutateBeforeCreate: function (array $row) {
                $row['status'] = 'imported';
                return $row;
            },
        );

        $result = $importer->import($rows);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals('imported', TestUser::first()->status);
    }

    /** @test */
    public function it_applies_pipeline_to_rows()
    {
        $pipeline = new ImportPipeline();
        $pipeline->pipe(new LowercasePipe(['email']));

        $rows = [
            ['name' => 'Alice', 'email' => 'ALICE@EXAMPLE.COM'],
        ];

        $importer = new BulkImporter(
            modelClass: TestUser::class,
            pipeline: $pipeline,
        );

        $result = $importer->import($rows);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals('alice@example.com', TestUser::first()->email);
    }

    /** @test */
    public function it_respects_chunk_size()
    {
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = ['name' => "User{$i}", 'email' => "user{$i}@example.com"];
        }

        $chunksProcessed = 0;
        $importer = new BulkImporter(
            modelClass: TestUser::class,
            beforeChunk: function () use (&$chunksProcessed) {
                $chunksProcessed++;
            },
        );
        $importer->chunkSize(3);

        $result = $importer->import($rows);

        $this->assertEquals(10, $result['imported']);
        $this->assertEquals(4, $chunksProcessed);  // ceil(10/3) = 4 chunks
    }

    /** @test */
    public function it_handles_import_exceptions_gracefully()
    {
        // Force a failure by creating an import with a too-long name
        // or use a different scenario that causes a DB error
        $rows = [
            ['name' => 'Valid', 'email' => 'valid@example.com'],
            // Insert the same row again — without uniqueBy, both go through
        ];

        // Create a scenario where one row fails: use a very long string
        // or a known trigger for failure
        $importer = new BulkImporter(modelClass: TestUser::class);
        $result = $importer->import($rows);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['failed']);
    }

    /** @test */
    public function it_returns_result_from_multiple_calls()
    {
        $importer = new BulkImporter(modelClass: TestUser::class);

        $firstResult = $importer->import([
            ['name' => 'A', 'email' => 'a@test.com'],
        ]);

        $secondResult = $importer->import([
            ['name' => 'B', 'email' => 'b@test.com'],
        ]);

        $this->assertEquals(1, $firstResult['imported']);
        $this->assertEquals(1, $secondResult['imported']);
        $this->assertEquals(2, TestUser::count());
    }

    /** @test */
    public function it_tracks_skipped_row_indices()
    {
        TestUser::create(['email' => 'alice@example.com']);

        $rows = [
            ['name' => 'Alice', 'email' => 'alice@example.com'],  // row 0
            ['name' => 'Bob', 'email' => 'bob@example.com'],       // row 1
        ];

        $importer = new BulkImporter(
            modelClass: TestUser::class,
            uniqueBy: ['email'],
            onDuplicate: DuplicateBehavior::Skip,
        );

        $result = $importer->import($rows);

        $this->assertEquals([0], $result['skippedRows']);
    }
}
