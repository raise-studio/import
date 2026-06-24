<?php

namespace RaiseStudio\Import\Tests\Unit\Pro;

use RaiseStudio\Import\Enums\ImportStatus;
use RaiseStudio\Import\Pro\Models\ImportLog;
use RaiseStudio\Import\Tests\TestCase;

class ImportLogTest extends TestCase
{
    /** @test */
    public function it_can_create_an_import_log()
    {
        $log = ImportLog::create([
            'model_class' => 'App\Models\User',
            'file_name' => 'users.csv',
            'file_path' => '/tmp/users.csv',
            'total_rows' => 100,
            'status' => ImportStatus::Pending,
        ]);

        $this->assertNotNull($log->id);
        $this->assertEquals('App\Models\User', $log->model_class);
        $this->assertEquals('users.csv', $log->file_name);
        $this->assertEquals(100, $log->total_rows);
        $this->assertEquals(ImportStatus::Pending, $log->status);
    }

    /** @test */
    public function it_defaults_counts_to_zero()
    {
        $log = ImportLog::create([
            'model_class' => 'App\Models\User',
            'file_name' => 'users.csv',
            'file_path' => '/tmp/users.csv',
        ]);

        $this->assertEquals(0, $log->imported_count);
        $this->assertEquals(0, $log->skipped_count);
        $this->assertEquals(0, $log->failed_count);
        $this->assertEquals(0, $log->total_rows);
    }

    /** @test */
    public function it_detects_completed_status()
    {
        $log = ImportLog::create([
            'model_class' => 'App\Models\User',
            'file_name' => 'users.csv',
            'file_path' => '/tmp/users.csv',
            'status' => ImportStatus::Completed,
        ]);

        $this->assertTrue($log->isCompleted());
        $this->assertFalse($log->isPartial());
        $this->assertFalse($log->isFailed());
    }

    /** @test */
    public function it_detects_partial_status()
    {
        $log = ImportLog::create([
            'model_class' => 'App\Models\User',
            'file_name' => 'users.csv',
            'file_path' => '/tmp/users.csv',
            'status' => ImportStatus::Partial,
        ]);

        $this->assertTrue($log->isPartial());
        $this->assertFalse($log->isCompleted());
        $this->assertFalse($log->isFailed());
    }

    /** @test */
    public function it_detects_failed_status()
    {
        $log = ImportLog::create([
            'model_class' => 'App\Models\User',
            'file_name' => 'users.csv',
            'file_path' => '/tmp/users.csv',
            'status' => ImportStatus::Failed,
        ]);

        $this->assertTrue($log->isFailed());
        $this->assertFalse($log->isCompleted());
        $this->assertFalse($log->isPartial());
    }

    /** @test */
    public function it_calculates_processed_count()
    {
        $log = ImportLog::create([
            'model_class' => 'App\Models\User',
            'file_name' => 'users.csv',
            'file_path' => '/tmp/users.csv',
            'imported_count' => 10,
            'skipped_count' => 2,
            'failed_count' => 1,
        ]);

        $this->assertEquals(13, $log->processedCount());
    }

    /** @test */
    public function it_handles_errors_as_array()
    {
        $errors = [
            ['row' => 1, 'field' => 'email', 'value' => 'bad', 'error' => 'Invalid email'],
            ['row' => 2, 'field' => 'name', 'value' => '', 'error' => 'Required'],
        ];

        $log = ImportLog::create([
            'model_class' => 'App\Models\User',
            'file_name' => 'users.csv',
            'file_path' => '/tmp/users.csv',
            'errors' => $errors,
        ]);

        $this->assertIsArray($log->errors);
        $this->assertCount(2, $log->errors);
        $this->assertEquals('Invalid email', $log->errors[0]['error']);
    }

    /** @test */
    public function it_handles_meta_as_array()
    {
        $meta = ['source' => 'api', 'ip' => '192.168.1.1'];

        $log = ImportLog::create([
            'model_class' => 'App\Models\User',
            'file_name' => 'users.csv',
            'file_path' => '/tmp/users.csv',
            'meta' => $meta,
        ]);

        $this->assertIsArray($log->meta);
        $this->assertEquals('api', $log->meta['source']);
    }

    /** @test */
    public function it_uses_custom_table_name_from_config()
    {
        config()->set('raise-import.table_names.import_logs', 'my_import_logs');

        // Re-run the migration with the custom name
        $connection = $this->app['db']->connection();
        $schema = $connection->getSchemaBuilder();

        // Create the custom named table
        $schema->create('my_import_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('model_class');
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('total_rows')->default(0);
            $table->integer('imported_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('status')->default('pending');
            $table->string('error_report_path')->nullable();
            $table->json('errors')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });

        $log = ImportLog::create([
            'model_class' => 'App\Models\User',
            'file_name' => 'users.csv',
            'file_path' => '/tmp/users.csv',
        ]);

        $this->assertEquals('my_import_logs', $log->getTable());

        // Clean up
        $schema->drop('my_import_logs');
    }
}
