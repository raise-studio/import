<?php

namespace RaiseStudio\Import\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RaiseStudio\Import\RaiseImportServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            RaiseImportServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }

    protected function setUpDatabase(): void
    {
        // Create test_users table for BulkImporter / ImportAction tests
        $this->app['db']->connection()->getSchemaBuilder()->create('test_users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('status')->default('active');
        });

        // Create import_logs table (Pro feature, needed by ImportAction tests)
        $migration = include __DIR__ . '/../src/Pro/database/migrations/001_create_import_logs_table.php';
        $migration->up();
    }
}
