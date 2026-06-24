<?php

namespace RaiseStudio\Import\Importers;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RaiseStudio\Import\Enums\DuplicateBehavior;
use RaiseStudio\Import\Pipes\ImportPipeline;
use RaiseStudio\Import\Validators\BatchValidator;

class BulkImporter
{
    protected int $chunkSize = 500;

    protected int $imported = 0;

    protected int $skipped = 0;

    protected int $failed = 0;

    /** @var array<int, int> */
    protected array $skippedRows = [];

    /** @var array<int, array{row: int, field: string, value: string, error: string}> */
    protected array $failedRows = [];

    public function __construct(
        protected string $modelClass,
        protected array $uniqueBy = [],
        protected DuplicateBehavior $onDuplicate = DuplicateBehavior::Skip,
        protected ?ImportPipeline $pipeline = null,
        protected ?Closure $mutateBeforeCreate = null,
        protected ?Closure $beforeChunk = null,
        protected ?Closure $afterChunk = null,
    ) {}

    /**
     * Import a set of rows.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array{imported: int, skipped: int, failed: int, skippedRows: array<int, int>, failedRows: array<int, array{row: int, field: string, value: string, error: string}>}
     */
    public function import(array $rows): array
    {
        $this->imported = 0;
        $this->skipped = 0;
        $this->failed = 0;
        $this->skippedRows = [];
        $this->failedRows = [];

        if (empty($rows)) {
            return $this->result();
        }

        // Build uniqueness index if needed
        $batchValidator = new BatchValidator();
        $existingIndex = [];
        $incomingIndex = null;

        if (!empty($this->uniqueBy)) {
            $existingIndex = $batchValidator->buildUniquenessIndex($this->modelClass, $this->uniqueBy);
            $incomingIndex = $batchValidator->buildIncomingIndex($rows, $this->uniqueBy);
        }

        // Process in chunks
        $chunks = array_chunk($rows, $this->chunkSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            if ($this->beforeChunk) {
                call_user_func($this->beforeChunk, $chunk, $chunkIndex);
            }

            $this->processChunk($chunk, $chunkIndex, $batchValidator, $existingIndex, $incomingIndex);

            if ($this->afterChunk) {
                call_user_func($this->afterChunk, $this->result(), $chunkIndex);
            }
        }

        return $this->result();
    }

    /**
     * Process a single chunk within a transaction.
     */
    protected function processChunk(
        array $chunk,
        int $chunkIndex,
        BatchValidator $batchValidator,
        array $existingIndex,
        ?array &$incomingIndex,
    ): void {
        DB::transaction(function () use ($chunk, $chunkIndex, $batchValidator, $existingIndex, &$incomingIndex) {
            foreach ($chunk as $rowIndex => $row) {
                $absoluteRowIndex = ($chunkIndex * $this->chunkSize) + $rowIndex;

                try {
                    // Pipeline processing (runs first)
                    if ($this->pipeline) {
                        $row = $this->pipeline->send($row);
                    }

                    // Legacy mutation callback (backward compat)
                    if ($this->mutateBeforeCreate) {
                        $row = call_user_func($this->mutateBeforeCreate, $row);
                    }

                    // Duplicate check
                    if (!empty($this->uniqueBy)) {
                        $isDuplicate = $batchValidator->isDuplicate(
                            $row,
                            $this->uniqueBy,
                            $existingIndex,
                            $incomingIndex,
                            $absoluteRowIndex,
                        );

                        if ($isDuplicate) {
                            match ($this->onDuplicate) {
                                DuplicateBehavior::Skip => $this->skipRow($absoluteRowIndex),
                                DuplicateBehavior::Update => $this->updateRow($row),
                                DuplicateBehavior::Error => $this->failRow($absoluteRowIndex, 'Duplicate record'),
                            };
                            continue;
                        }
                    }

                    // Create record
                    $this->modelClass::create($row);
                    $this->imported++;
                } catch (\Throwable $e) {
                    $this->failRow($absoluteRowIndex, $e->getMessage());
                }
            }
        });
    }

    protected function skipRow(int $rowIndex): void
    {
        $this->skipped++;
        $this->skippedRows[] = $rowIndex;
    }

    protected function failRow(int $rowIndex, string $error): void
    {
        $this->failed++;
        $this->failedRows[] = [
            'row' => $rowIndex,
            'field' => '',
            'value' => '',
            'error' => $error,
        ];
    }

    protected function updateRow(array $row): void
    {
        $query = $this->modelClass::query();

        foreach ($this->uniqueBy as $field) {
            $query->where($field, $row[$field] ?? null);
        }

        $query->update($row);
        $this->imported++;
    }

    public function chunkSize(int $size): self
    {
        $this->chunkSize = max(1, $size);

        return $this;
    }

    /**
     * @return array{imported: int, skipped: int, failed: int, skippedRows: array<int, int>, failedRows: array<int, array{row: int, field: string, value: string, error: string}>}
     */
    public function result(): array
    {
        return [
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'skippedRows' => $this->skippedRows,
            'failedRows' => $this->failedRows,
        ];
    }
}
