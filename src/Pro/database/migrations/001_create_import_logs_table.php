<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('raise-import.table_names.import_logs', 'import_logs');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('model_class');
            $table->string('file_name');
            $table->string('original_file_name')->nullable();   // ← 从 003 合并
            $table->string('file_path');
            $table->integer('total_rows')->default(0);
            $table->integer('imported_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable()->after('status');        // ← 从 002 合并
            $table->timestamp('finished_at')->nullable()->after('started_at');   // ← 从 002 合并
            $table->string('error_report_path')->nullable();
            $table->json('errors')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        $tableName = config('raise-import.table_names.import_logs', 'import_logs');
        Schema::dropIfExists($tableName);
    }
};
