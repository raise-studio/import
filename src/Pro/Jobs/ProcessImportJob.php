<?php

namespace RaiseStudio\Import\Pro\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RaiseStudio\Import\Enums\ImportStatus;
use RaiseStudio\Import\Importers\BulkImporter;
use RaiseStudio\Import\License;
use RaiseStudio\Import\Pipes\ImportPipe;
use RaiseStudio\Import\Pipes\ImportPipeline;
use RaiseStudio\Import\Pro\Models\ImportLog;
use RaiseStudio\Import\Readers\ReaderFactory;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(
        protected ImportLog $importLog,
        protected array $mapping,
        protected array $fields,
        protected array $rules,
        protected array $uniqueBy,
        protected string $onDuplicate,
        protected int $chunkSize,
        protected ?array $mutateCallbacks = null,
        protected ?array $pipeConfigs = null,
    ) {}

    public function handle(): void
    {
        if (!License::isPro()) {
            $this->fail(new \Exception('Pro license required'));

            return;
        }

        $this->importLog->update([
            'status' => ImportStatus::Processing,
            'started_at' => now(),
        ]);

        try {
            $reader = ReaderFactory::create($this->importLog->file_path);
            $allRows = iterator_to_array($reader->rows($this->importLog->file_path));

            // Detect mapping format and apply AdvancedMapper for new format
            $isNewFormat = !empty($this->mapping) && is_array($this->mapping[0] ?? null) && isset($this->mapping[0]['field_name']) && is_array($this->mapping[0]['field_name']);

            if ($isNewFormat) {
                $mapper = \RaiseStudio\Import\Mappers\AdvancedMapper::fromFormData($this->mapping);
                $mappedRows = array_map(fn ($row) => $mapper->apply($row), $allRows);
            } else {
                // Legacy string-based mapping
                $mappedRows = [];
                foreach ($allRows as $row) {
                    $mapped = [];
                    foreach ($this->mapping as $fileHeader => $fieldName) {
                        if ($fieldName === '' || $fieldName === null) {
                            continue;
                        }
                        $mapped[$fieldName] = $row[$fileHeader] ?? null;
                    }
                    $mappedRows[] = $mapped;
                }
            }

            // Rebuild Field objects from serialized config for validation
            $fieldObjects = [];
            foreach ($this->fields as $fieldConfig) {
                $field = \RaiseStudio\Import\Fields\Field::make($fieldConfig['name'] ?? '')
                    ->label($fieldConfig['label'] ?? '');
                if (!empty($fieldConfig['rules'])) {
                    $field->rules($fieldConfig['rules']);
                }
                if (array_key_exists('required', $fieldConfig) && $fieldConfig['required']) {
                    $field->required(true);
                }
                if (isset($fieldConfig['default'])) {
                    $field->default($fieldConfig['default']);
                }
                if (!empty($fieldConfig['options'])) {
                    $field->options($fieldConfig['options']);
                }
                $fieldObjects[] = $field;
            }

            // Validate rows
            $validator = new \RaiseStudio\Import\Validators\RowValidator();
            $validRows = [];
            foreach ($mappedRows as $idx => $row) {
                $validated = $validator->validate($row, $fieldObjects, $idx);
                if (!empty($validated)) {
                    $validator->reset();
                    $validRows[] = $validated;
                } else {
                    $validator->reset();
                }
            }

            $importer = new BulkImporter(
                modelClass: $this->importLog->model_class,
                uniqueBy: $this->uniqueBy,
                onDuplicate: \RaiseStudio\Import\Enums\DuplicateBehavior::from($this->onDuplicate),
                pipeline: $this->buildPipeline(),
                mutateBeforeCreate: $this->buildMutateCallback(),
            );

            $importer->chunkSize($this->chunkSize);
            $result = $importer->import($mappedRows);

            $status = match (true) {
                $result['failed'] > 0 && $result['imported'] === 0 => ImportStatus::Failed,
                $result['failed'] > 0 || $result['skipped'] > 0 => ImportStatus::Partial,
                default => ImportStatus::Completed,
            };

            $this->importLog->update([
                'imported_count' => $result['imported'],
                'skipped_count' => $result['skipped'],
                'failed_count' => $result['failed'],
                'status' => $status,
                'errors' => $result['failedRows'],
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->importLog->update([
                'status' => ImportStatus::Failed,
                'errors' => ['fatal' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    protected function buildMutateCallback(): ?\Closure
    {
        if (empty($this->mutateCallbacks)) {
            return null;
        }

        $callbacks = $this->mutateCallbacks;

        return function (array $row) use ($callbacks) {
            foreach ($callbacks as $callback) {
                $row = $callback($row);
            }

            return $row;
        };
    }

    /**
     * Build the pipeline from serialized pipe configs.
     */
    protected function buildPipeline(): ?ImportPipeline
    {
        if (empty($this->pipeConfigs)) {
            return null;
        }

        $pipeline = new ImportPipeline();

        foreach ($this->pipeConfigs as $config) {
            $pipeClass = $config['class'] ?? null;
            $params = $config['params'] ?? [];
            $field = $config['field'] ?? null;

            if (! $pipeClass || ! class_exists($pipeClass)) {
                continue;
            }

            $pipe = new $pipeClass(...$params);

            if ($field) {
                $pipeline->fieldPipe($field, $pipe);
            } else {
                $pipeline->pipe($pipe);
            }
        }

        return $pipeline;
    }
}
