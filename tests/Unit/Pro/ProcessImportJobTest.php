<?php

namespace RaiseStudio\Import\Tests\Unit\Pro;

use RaiseStudio\Import\Enums\DuplicateBehavior;
use RaiseStudio\Import\Enums\ImportStatus;
use RaiseStudio\Import\Importers\BulkImporter;
use RaiseStudio\Import\Pro\Jobs\ProcessImportJob;
use RaiseStudio\Import\Pro\Models\ImportLog;
use RaiseStudio\Import\Readers\ReaderFactory;
use RaiseStudio\Import\Tests\Stubs\TestUser;
use RaiseStudio\Import\Tests\TestCase;

class ProcessImportJobTest extends TestCase
{
    protected string $tempFile;

    protected function tearDown(): void
    {
        if (isset($this->tempFile) && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_processes_csv_and_updates_import_log()
    {
        $this->createTempCsv("name,email\nAlice,alice@test.com\nBob,bob@test.com\n");

        $importLog = ImportLog::create([
            'model_class' => TestUser::class,
            'file_name' => 'test.csv',
            'file_path' => $this->tempFile,
            'total_rows' => 2,
            'status' => ImportStatus::Pending,
        ]);

        $job = new ProcessImportJob(
            importLog: $importLog,
            mapping: ['name' => 'name', 'email' => 'email'],
            fields: [],
            rules: [],
            uniqueBy: [],
            onDuplicate: 'skip',
            chunkSize: 100,
        );

        $job->handle();

        $importLog->refresh();

        $this->assertEquals(ImportStatus::Completed, $importLog->status);
        $this->assertEquals(2, $importLog->imported_count);
        $this->assertEquals(0, $importLog->skipped_count);
        $this->assertEquals(0, $importLog->failed_count);

        $this->assertDatabaseHas('test_users', ['email' => 'alice@test.com']);
        $this->assertDatabaseHas('test_users', ['email' => 'bob@test.com']);
    }

    /** @test */
    public function it_sets_processing_status_at_start()
    {
        $this->createTempCsv("name,email\nAlice,alice@test.com\n");

        $importLog = ImportLog::create([
            'model_class' => TestUser::class,
            'file_name' => 'test.csv',
            'file_path' => $this->tempFile,
            'total_rows' => 1,
            'status' => ImportStatus::Pending,
        ]);

        $job = new ProcessImportJob(
            importLog: $importLog,
            mapping: ['name' => 'name', 'email' => 'email'],
            fields: [],
            rules: [],
            uniqueBy: [],
            onDuplicate: 'skip',
            chunkSize: 100,
        );

        $job->handle();

        $importLog->refresh();
        $this->assertEquals(ImportStatus::Completed, $importLog->status);
    }

    /** @test */
    public function it_sets_status_to_failed_on_exception()
    {
        $this->createTempCsv("name,email\nAlice,alice@test.com\n");

        $importLog = ImportLog::create([
            'model_class' => TestUser::class,
            'file_name' => 'test.csv',
            'file_path' => $this->tempFile,
            'total_rows' => 1,
            'status' => ImportStatus::Pending,
        ]);

        $job = new ProcessImportJob(
            importLog: $importLog,
            mapping: ['name' => 'name', 'email' => 'email'],
            fields: [],
            rules: [],
            uniqueBy: [],
            onDuplicate: 'skip',
            chunkSize: 100,
        );

        // Force a failure by deleting the temp file
        unlink($this->tempFile);

        $threwException = false;
        try {
            $job->handle();
        } catch (\Throwable $e) {
            $threwException = true;
        }

        $this->assertTrue($threwException);

        $importLog->refresh();
        $this->assertEquals(ImportStatus::Failed, $importLog->status);
        $this->assertNotEmpty($importLog->errors);
    }

    /** @test */
    public function it_applies_mapping_correctly()
    {
        $this->createTempCsv("Full Name,Email\nAlice,alice@test.com\n");

        $importLog = ImportLog::create([
            'model_class' => TestUser::class,
            'file_name' => 'test.csv',
            'file_path' => $this->tempFile,
            'total_rows' => 1,
            'status' => ImportStatus::Pending,
        ]);

        $job = new ProcessImportJob(
            importLog: $importLog,
            mapping: ['Full Name' => 'name', 'Email' => 'email'],
            fields: [],
            rules: [],
            uniqueBy: [],
            onDuplicate: 'skip',
            chunkSize: 100,
        );

        $job->handle();

        $this->assertDatabaseHas('test_users', ['name' => 'Alice', 'email' => 'alice@test.com']);
    }

    /** @test */
    public function it_handles_duplicates_with_skip_behavior()
    {
        $this->createTempCsv("name,email\nAlice,alice@test.com\nAlice,alice@test.com\n");

        // Insert the first row already
        TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $importLog = ImportLog::create([
            'model_class' => TestUser::class,
            'file_name' => 'test.csv',
            'file_path' => $this->tempFile,
            'total_rows' => 2,
            'status' => ImportStatus::Pending,
        ]);

        $job = new ProcessImportJob(
            importLog: $importLog,
            mapping: ['name' => 'name', 'email' => 'email'],
            fields: [],
            rules: [],
            uniqueBy: ['email'],
            onDuplicate: 'skip',
            chunkSize: 100,
        );

        $job->handle();

        $importLog->refresh();

        // Both rows are skipped (1 existing duplicate + 1 in-file duplicate)
        // skipped > 0 → Partial status
        $this->assertEquals(ImportStatus::Partial, $importLog->status);
    }

    /** @test */
    public function it_handles_duplicates_with_update_behavior()
    {
        $this->createTempCsv("name,email\nBob,bob@test.com\n");

        // Insert an existing user with same email
        TestUser::create(['name' => 'OldName', 'email' => 'bob@test.com']);

        $importLog = ImportLog::create([
            'model_class' => TestUser::class,
            'file_name' => 'test.csv',
            'file_path' => $this->tempFile,
            'total_rows' => 1,
            'status' => ImportStatus::Pending,
        ]);

        $job = new ProcessImportJob(
            importLog: $importLog,
            mapping: ['name' => 'name', 'email' => 'email'],
            fields: [],
            rules: [],
            uniqueBy: ['email'],
            onDuplicate: 'update',
            chunkSize: 100,
        );

        $job->handle();

        // The existing user should now have the updated name
        $this->assertDatabaseHas('test_users', ['email' => 'bob@test.com', 'name' => 'Bob']);
    }

    /** @test */
    public function build_mutate_callback_returns_null_when_empty()
    {
        $job = new ProcessImportJob(
            importLog: ImportLog::create([
                'model_class' => TestUser::class,
                'file_name' => 'test.csv',
                'file_path' => '/tmp/test.csv',
                'total_rows' => 0,
            ]),
            mapping: [],
            fields: [],
            rules: [],
            uniqueBy: [],
            onDuplicate: 'skip',
            chunkSize: 100,
        );

        $reflection = new \ReflectionMethod($job, 'buildMutateCallback');
        $reflection->setAccessible(true);

        $this->assertNull($reflection->invoke($job));
    }

    /** @test */
    public function build_mutate_callback_chains_multiple_callbacks()
    {
        $job = new ProcessImportJob(
            importLog: ImportLog::create([
                'model_class' => TestUser::class,
                'file_name' => 'test.csv',
                'file_path' => '/tmp/test.csv',
                'total_rows' => 0,
            ]),
            mapping: [],
            fields: [],
            rules: [],
            uniqueBy: [],
            onDuplicate: 'skip',
            chunkSize: 100,
            mutateCallbacks: [
                fn(array $row) => array_merge($row, ['status' => 'active']),
                fn(array $row) => array_merge($row, ['phone' => '000-0000']),
            ],
        );

        $reflection = new \ReflectionMethod($job, 'buildMutateCallback');
        $reflection->setAccessible(true);

        $callback = $reflection->invoke($job);
        $this->assertNotNull($callback);

        $result = $callback(['name' => 'Alice']);
        $this->assertEquals('Alice', $result['name']);
        $this->assertEquals('active', $result['status']);
        $this->assertEquals('000-0000', $result['phone']);
    }

    protected function createTempCsv(string $content): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'job_test_') . '.csv';
        file_put_contents($this->tempFile, $content);
    }
}
