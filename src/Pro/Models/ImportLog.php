<?php

namespace RaiseStudio\Import\Pro\Models;

use Illuminate\Database\Eloquent\Model;
use RaiseStudio\Import\Enums\ImportStatus;

class ImportLog extends Model
{
    protected $fillable = [
        'user_id',
        'model_class',
        'file_name',
        'original_file_name',
        'file_path',
        'total_rows',
        'imported_count',
        'skipped_count',
        'failed_count',
        'status',
        'error_report_path',
        'errors',
        'meta',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'imported_count' => 'integer',
        'skipped_count' => 'integer',
        'failed_count' => 'integer',
        'status' => ImportStatus::class,
        'errors' => 'array',
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('raise-import.table_names.import_logs', 'import_logs'));
    }

    /**
     * Get the user who performed the import.
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Check if import completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === ImportStatus::Completed;
    }

    /**
     * Check if import had partial success.
     */
    public function isPartial(): bool
    {
        return $this->status === ImportStatus::Partial;
    }

    /**
     * Check if import failed entirely.
     */
    public function isFailed(): bool
    {
        return $this->status === ImportStatus::Failed;
    }

    /**
     * Get total processed count.
     */
    public function processedCount(): int
    {
        return $this->imported_count + $this->skipped_count + $this->failed_count;
    }
}
