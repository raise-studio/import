<?php

namespace RaiseStudio\Import\Pro\Actions;

use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use RaiseStudio\Import\Actions\ImportAction;
use RaiseStudio\Import\Enums\DuplicateBehavior;
use RaiseStudio\Import\Enums\ImportStatus;
use RaiseStudio\Import\Filament\Version;
use RaiseStudio\Import\License;
use RaiseStudio\Import\Pipes\ImportPipe;
use RaiseStudio\Import\Pipes\ImportPipeline;
use RaiseStudio\Import\Pipes\ClosurePipe;
use RaiseStudio\Import\Pipes\MergeColumnsPipe;
use RaiseStudio\Import\Pipes\SplitColumnPipe;
use RaiseStudio\Import\Fields\Field;
use RaiseStudio\Import\Mappers\AdvancedMapper;
use RaiseStudio\Import\Mappers\AutoMapper;
use RaiseStudio\Import\Mappers\ManualMapper;
use RaiseStudio\Import\Pro\Models\ImportLog;
use RaiseStudio\Import\Readers\ReaderFactory;
use RaiseStudio\Import\Validators\RowValidator;

class ProImportAction extends ImportAction
{
    protected ImportPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();

        if (!License::isPro()) {
            abort(403, 'This feature requires Raise Import Pro license.');
        }

        $this->pipeline = new ImportPipeline();
    }

    // -----------------------------------------------------------------------
    //  Pro: Pipeline methods
    // -----------------------------------------------------------------------

    /**
     * Add a global transform pipe (affects all fields).
     */
    public function pipe(string|ImportPipe $pipe): static
    {
        $this->pipeline->pipe($pipe);

        return $this;
    }

    /**
     * Add a field-level transform pipe (affects only the specified field).
     */
    public function fieldPipe(string $field, string|ImportPipe $pipe): static
    {
        $this->pipeline->fieldPipe($field, $pipe);

        return $this;
    }

    // -----------------------------------------------------------------------
    //  Pro: Form builder (multi-select + ignore checkbox + 3 columns)
    // -----------------------------------------------------------------------

    /**
     * Pro version: multi-select Select, ignore checkbox, 3-column layout.
     */
    protected function buildFormV4(): Closure
    {
        $Wizard = \Filament\Forms\Components\Wizard::class;
        $Step = \Filament\Forms\Components\Wizard\Step::class;

        return function (Form $form) use ($Wizard, $Step) {
            return $form
                ->schema([
                    $Wizard::make([
                        $Step::make('upload')
                            ->label(__('raise-import::messages.step.upload'))
                            ->schema([
                                FileUpload::make('file')
                                    ->label(__('raise-import::messages.upload.label'))
                                    ->nullable()
                                    ->preserveFilenames()
                                    ->storeFileNamesIn('original_file_names')
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $this->handleFileUpload($state, $set);
                                    }),
                                \Filament\Forms\Components\ViewField::make('template_download')
                                    ->view('raise-import::template-download')
                                    ->viewData(['modelClass' => $this->modelClass ?? ''])
                                    ->visible($this->modelClass !== null),
                                \Filament\Forms\Components\Hidden::make('import_file_path')
                                    ->dehydrated(true)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('raise-import::messages.errors.upload_incomplete'),
                                    ]),
                                \Filament\Forms\Components\Hidden::make('original_file_name')
                                    ->dehydrated(true),
                            ]),

                        $Step::make('mapping')
                            ->label(__('raise-import::messages.step.mapping'))
                            ->schema([
                                Repeater::make('column_mapping')
                                    ->label(__('raise-import::messages.mapping.title'))
                                    ->schema([
                                        TextInput::make('file_header')
                                            ->label(__('raise-import::messages.mapping.file_header'))
                                            ->disabled()
                                            ->dehydrated(),
                                        Select::make('field_name')
                                            ->label(__('raise-import::messages.mapping.field_name'))
                                            ->options(function () {
                                                $options = [];
                                                foreach ($this->resolveFieldsForMapping() as $field) {
                                                    $options[$field->getName()] = $field->getLabel();
                                                }
                                                return $options;
                                            })
                                            ->multiple()
                                            ->placeholder(__('raise-import::messages.mapping.placeholder')),
                                        \Filament\Forms\Components\Checkbox::make('ignored')
                                            ->label(__('raise-import::messages.mapping.ignored'))
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $set) {
                                                if ($state) {
                                                    $set('field_name', []);
                                                }
                                            }),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->extraAttributes(['style' => 'max-height: 24rem; overflow-y: auto; padding: 10px;']),
                            ]),

                        $Step::make('preview')
                            ->label(__('raise-import::messages.step.preview'))
                            ->schema([
                                ViewField::make('preview_table')
                                    ->view('raise-import::import-preview-table')
                                    ->label(__('raise-import::messages.preview.title')),
                                Select::make('duplicate_behavior')
                                    ->label(__('raise-import::messages.preview.duplicate_behavior'))
                                    ->options([
                                        'skip' => __('raise-import::messages.duplicate_behavior.skip'),
                                        'update' => __('raise-import::messages.duplicate_behavior.update'),
                                        'error' => __('raise-import::messages.duplicate_behavior.error'),
                                    ])
                                    ->default($this->onDuplicate->value)
                                    ->visible(!empty($this->uniqueBy)),
                            ]),
                    ])
                        ->submitAction(view('raise-import::wizard-submit', [
                            'nextLabel' => __('raise-import::messages.wizard.next'),
                            'previousLabel' => __('raise-import::messages.wizard.previous'),
                            'startImportLabel' => __('raise-import::messages.wizard.start_import'),
                            'closeLabel' => __('raise-import::messages.wizard.close'),
                        ])),
                ]);
        };
    }

    /**
     * Pro version: multi-select Select, ignore checkbox, 3-column layout (Filament V5).
     */
    protected function buildFormV5(): Closure
    {
        $Wizard = \Filament\Schemas\Components\Wizard::class;
        $Step = \Filament\Schemas\Components\Wizard\Step::class;

        return function (\Filament\Schemas\Schema $form) use ($Wizard, $Step) {
            return $form
                ->schema([
                    $Wizard::make([
                        $Step::make('upload')
                            ->label(__('raise-import::messages.step.upload'))
                            ->schema([
                                FileUpload::make('file')
                                    ->label(__('raise-import::messages.upload.label'))
                                    ->nullable()
                                    ->preserveFilenames()
                                    ->storeFileNamesIn('original_file_names')
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $this->handleFileUpload($state, $set);
                                    }),
                                \Filament\Schemas\Components\View::make('raise-import::template-download')
                                    ->viewData(['modelClass' => $this->modelClass ?? ''])
                                    ->visible($this->modelClass !== null),
                                \Filament\Forms\Components\Hidden::make('import_file_path')
                                    ->dehydrated(true)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('raise-import::messages.errors.upload_incomplete'),
                                    ]),
                                \Filament\Forms\Components\Hidden::make('original_file_name')
                                    ->dehydrated(true),
                            ]),

                        $Step::make('mapping')
                            ->label(__('raise-import::messages.step.mapping'))
                            ->schema([
                                Repeater::make('column_mapping')
                                    ->label(__('raise-import::messages.mapping.title'))
                                    ->schema([
                                        TextInput::make('file_header')
                                            ->label(__('raise-import::messages.mapping.file_header'))
                                            ->disabled()
                                            ->dehydrated(),
                                        Select::make('field_name')
                                            ->label(__('raise-import::messages.mapping.field_name'))
                                            ->options(function () {
                                                $options = [];
                                                foreach ($this->resolveFieldsForMapping() as $field) {
                                                    $options[$field->getName()] = $field->getLabel();
                                                }
                                                return $options;
                                            })
                                            ->multiple()
                                            ->placeholder(__('raise-import::messages.mapping.placeholder')),
                                        \Filament\Forms\Components\Checkbox::make('ignored')
                                            ->label(__('raise-import::messages.mapping.ignored'))
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $set) {
                                                if ($state) {
                                                    $set('field_name', []);
                                                }
                                            }),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->extraAttributes(['style' => 'max-height: 24rem; overflow-y: auto; padding: 10px;']),
                            ]),

                        $Step::make('preview')
                            ->label(__('raise-import::messages.step.preview'))
                            ->schema([
                                ViewField::make('preview_table')
                                    ->view('raise-import::import-preview-table')
                                    ->label(__('raise-import::messages.preview.title')),
                                Select::make('duplicate_behavior')
                                    ->label(__('raise-import::messages.preview.duplicate_behavior'))
                                    ->options([
                                        'skip' => __('raise-import::messages.duplicate_behavior.skip'),
                                        'update' => __('raise-import::messages.duplicate_behavior.update'),
                                        'error' => __('raise-import::messages.duplicate_behavior.error'),
                                    ])
                                    ->default($this->onDuplicate->value)
                                    ->visible(!empty($this->uniqueBy)),
                            ]),
                    ])
                        ->submitAction(view('raise-import::wizard-submit-v5', [
                            'startImportLabel' => __('raise-import::messages.wizard.start_import'),
                        ])),
                ]);
        };
    }

    // -----------------------------------------------------------------------
    //  Pro: Import execution (AdvancedMapper + pipeline + ImportLog)
    // -----------------------------------------------------------------------

    public function handle(array $data): void
    {
        try {
            $rawMapping = $data['column_mapping'] ?? [];
            $duplicateBehavior = $data['duplicate_behavior'] ?? $this->onDuplicate->value;
            $fields = $this->resolveFields();

            // Resolve file path
            $filePath = $data['import_file_path'] ?? null;
            $fileName = null;

            if ($filePath && file_exists($filePath)) {
                $fileName = basename($filePath);
            } else {
                $file = $data['file'] ?? null;
                if (is_string($file)) {
                    $filePath = \Illuminate\Support\Facades\Storage::path($file);
                    $fileName = basename($file);
                    if (!file_exists($filePath)) {
                        $filePath = $file;
                    }
                } elseif ($file) {
                    $filePath = is_array($file) ? ($file[0]?->getRealPath()) : $file?->getRealPath();
                    $fileName = is_array($file) ? ($file[0]?->getClientOriginalName()) : $file?->getClientOriginalName();
                }
            }

            if (!$filePath || !$fileName) {
                Notification::make()
                    ->danger()
                    ->title(__('raise-import::messages.errors.no_file'))
                    ->send();
                return;
            }

            if (!file_exists($filePath)) {
                Notification::make()
                    ->danger()
                    ->title('File not found')
                    ->body("Path: {$filePath}")
                    ->send();
                return;
            }

            // Count rows (fast) — decide queue vs sync before heavy processing
            $reader = ReaderFactory::create($filePath);
            $totalRows = $reader->count($filePath);

            $queueConfig = config('raise-import.queue', []);
            $queueEnabled = $queueConfig['enabled'] ?? false;
            $queueThreshold = $queueConfig['threshold'] ?? 5000;
            $pipeline = $this->buildPipeline();

            // Queue if: enabled, large enough, and all pipes are serializable
            $canQueue = $queueEnabled
                && $totalRows > $queueThreshold
                && !$this->pipelineHasClosures($pipeline);

            if ($canQueue) {
                // ── Async path: create ImportLog + dispatch Job, return immediately ──
                $importLog = ImportLog::create([
                    'user_id' => auth()->id(),
                    'model_class' => $this->modelClass,
                    'file_name' => $fileName,
                    'original_file_name' => $this->resolveOriginalFileName($data, $filePath, $fileName),
                    'file_path' => $filePath,
                    'total_rows' => $totalRows,
                    'status' => ImportStatus::Pending,
                    'meta' => [
                        'mapping' => $this->buildMappingMeta($rawMapping),
                        'queued' => true,
                        'on_duplicate' => $duplicateBehavior,
                        'unique_by' => $this->uniqueBy,
                        'chunk_size' => $this->chunkSize,
                        'fields' => array_map(fn ($f) => [
                            'name' => $f->getName(),
                            'label' => $f->getLabel(),
                        ], $this->resolveFields()),
                    ],
                ]);

                $job = new \RaiseStudio\Import\Pro\Jobs\ProcessImportJob(
                    importLog: $importLog,
                    mapping: $rawMapping,
                    fields: array_map(fn ($f) => [
                        'name' => $f->getName(),
                        'label' => $f->getLabel(),
                        'rules' => $f->getRules(),
                        'default' => $f->getDefault(),
                        'options' => $f->getOptions(),
                        'required' => $f->isRequired(),
                    ], $this->resolveFields()),
                    rules: $this->rules,
                    uniqueBy: $this->uniqueBy,
                    onDuplicate: $duplicateBehavior,
                    chunkSize: $this->chunkSize,
                    pipeConfigs: $pipeline ? $pipeline->toConfig() : [],
                    mutateCallbacks: [],
                );

                $connection = $queueConfig['connection'] ?? 'sync';
                $queue = $queueConfig['queue'] ?? 'imports';
                dispatch($job)->onConnection($connection)->onQueue($queue);

                Notification::make()
                    ->success()
                    ->title('导入已加入队列')
                    ->body("共 {$totalRows} 条数据，队列正在处理中...")
                    ->send();

                return;
            }

            // ── Sync path: read → map → validate → import in HTTP request ──
            $allRows = iterator_to_array($reader->rows($filePath));

            // Detect format: old format uses string field_name, new format uses array
            $isNewFormat = !empty($rawMapping) && is_array($rawMapping[0] ?? null) && isset($rawMapping[0]['field_name']) && is_array($rawMapping[0]['field_name']);

            if ($isNewFormat) {
                // Pro: AdvancedMapper handles merge/split/ignore
                $mapper = AdvancedMapper::fromFormData($rawMapping);
                $mappedRows = array_map(fn ($row) => $mapper->apply($row), $allRows);

                $mapping = [];
                foreach ($mapper->all() as $item) {
                    if ($item->isMapped()) {
                        $mapping[$item->fileHeader] = $item->fieldNames;
                    }
                }
            } else {
                // Legacy format fallback
                $mapping = [];
                if (!empty($rawMapping) && is_array($rawMapping[0] ?? null)) {
                    foreach ($rawMapping as $item) {
                        if (!empty($item['field_name'])) {
                            $mapping[$item['file_header']] = $item['field_name'];
                        }
                    }
                } else {
                    $mapping = $rawMapping;
                }

                $legacyMapper = new ManualMapper();
                $mappedRows = array_map(fn ($row) => $legacyMapper->apply($row, $mapping), $allRows);
            }

            // Validate rows
            $validator = new RowValidator();
            $validRows = [];
            $validationErrors = [];
            foreach ($mappedRows as $idx => $row) {
                $validated = $validator->validate($row, $fields, $idx);
                if (empty($validated)) {
                    foreach ($validator->getErrors() as $err) {
                        $validationErrors[] = $err;
                    }
                    $validator->reset();
                } else {
                    $validator->reset();
                    $validRows[] = $validated;
                }
            }

            // Create import log (Pro feature)
            $importLog = null;
            if (License::isPro()) {
                $importLog = ImportLog::create([
                    'user_id' => auth()->id(),
                    'model_class' => $this->modelClass,
                    'file_name' => $fileName,
                    'original_file_name' => $this->resolveOriginalFileName($data, $filePath, $fileName),
                    'file_path' => $filePath,
                    'total_rows' => count($mappedRows),
                    'status' => \RaiseStudio\Import\Enums\ImportStatus::Processing,
                    'started_at' => now(),
                    'meta' => [
                        'mapping' => $mapping,
                        'on_duplicate' => $duplicateBehavior,
                        'unique_by' => $this->uniqueBy,
                        'chunk_size' => $this->chunkSize,
                        'fields' => array_map(fn ($f) => [
                            'name' => $f->getName(),
                            'label' => $f->getLabel(),
                        ], $this->resolveFields()),
                    ],
                ]);
            }

            // Bulk import with pipeline
            $importer = new \RaiseStudio\Import\Importers\BulkImporter(
                modelClass: $this->modelClass,
                uniqueBy: $this->uniqueBy,
                onDuplicate: DuplicateBehavior::from($duplicateBehavior),
                pipeline: $pipeline,
                mutateBeforeCreate: $this->buildMutateCallback(),
            );

            $importer->chunkSize($this->chunkSize);
            $result = $importer->import($validRows);

            $allErrors = array_merge($result['failedRows'], $validationErrors);

            $status = match (true) {
                $result['failed'] > 0 && $result['imported'] === 0 && empty($validRows) => \RaiseStudio\Import\Enums\ImportStatus::Failed,
                !empty($validationErrors) || $result['failed'] > 0 || $result['skipped'] > 0 => \RaiseStudio\Import\Enums\ImportStatus::Partial,
                default => \RaiseStudio\Import\Enums\ImportStatus::Completed,
            };

            $totalFailed = $result['failed'] + count($validationErrors);

            if ($importLog) {
                $importLog->update([
                    'imported_count' => $result['imported'],
                    'skipped_count' => $result['skipped'],
                    'failed_count' => $totalFailed,
                    'status' => $status,
                    'errors' => $allErrors,
                    'finished_at' => now(),
                ]);
            }

            Notification::make()
                ->success()
                ->title(__('raise-import::messages.results.title'))
                ->body(trans_choice('raise-import::messages.results.summary', $result['imported'], [
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                    'failed' => $totalFailed,
                ]))
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('raise-import::messages.errors.import_failed'))
                ->body($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine())
                ->send();
        }
    }

    // -----------------------------------------------------------------------
    //  Pro: Pipeline building
    // -----------------------------------------------------------------------

    /**
     * Build the pipeline, wrapping legacy callbacks as ClosurePipe.
     */
    protected function buildPipeline(): ?ImportPipeline
    {
        if ($this->pipeline->isEmpty() && empty($this->mutateCallbacks)) {
            return null;
        }

        $pipeline = clone $this->pipeline;

        foreach ($this->mutateCallbacks as $callback) {
            $pipeline->pipe(new ClosurePipe($callback));
        }

        return $pipeline;
    }

    protected function buildMutateCallback(): ?Closure
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
     * Check if the pipeline contains any ClosurePipe instances.
     * ClosurePipe wraps inline closures which cannot be serialized for queues.
     */
    protected function pipelineHasClosures(?ImportPipeline $pipeline): bool
    {
        if ($pipeline === null) {
            return false;
        }

        foreach ($pipeline->getGlobalPipes() as $pipe) {
            if ($pipe instanceof \RaiseStudio\Import\Pipes\ClosurePipe) {
                return true;
            }
        }

        foreach ($pipeline->getFieldPipes() as $pipes) {
            foreach ($pipes as $pipe) {
                if ($pipe instanceof \RaiseStudio\Import\Pipes\ClosurePipe) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Convert raw mapping form data to a flat key-value format for display.
     *
     * Raw format: [['file_header' => 'name', 'field_name' => ['name']], ...]
     * Output:    ['name' => 'name', 'full_name' => ['first_name', 'last_name']]
     */
    protected function buildMappingMeta(array $rawMapping): array
    {
        $meta = [];

        foreach ($rawMapping as $item) {
            $fileHeader = $item['file_header'] ?? '';
            $fieldNames = $item['field_name'] ?? [];

            if (is_array($fieldNames)) {
                // New format: array of field names
                $meta[$fileHeader] = $fieldNames;
            } elseif (!empty($fieldNames)) {
                // Legacy format: single field name as string
                $meta[$fileHeader] = $fieldNames;
            }
        }

        return $meta;
    }

    /**
     * Resolve fields including virtual fields from pipes like MergeColumnsPipe.
     *
     * This ensures that CSV columns matching pipe source fields (e.g., first_name,
     * last_name in MergeColumnsPipe) are available as mapping targets, so they
     * appear in the mapping dropdown and can be auto-detected by the AutoMapper.
     *
     * @return array<int, Field>
     */
    protected function resolveFieldsForMapping(): array
    {
        $fields = parent::resolveFields();
        $fieldNames = array_map(fn (Field $f) => $f->getName(), $fields);

        foreach ($this->pipeline->getGlobalPipes() as $pipe) {
            if ($pipe instanceof MergeColumnsPipe) {
                foreach ($pipe->getSourceFields() as $sourceField) {
                    if (!in_array($sourceField, $fieldNames, true)) {
                        $fields[] = Field::make($sourceField);
                        $fieldNames[] = $sourceField;
                    }
                }
            } elseif ($pipe instanceof SplitColumnPipe) {
                $sourceField = $pipe->getSourceField();
                if (!in_array($sourceField, $fieldNames, true)) {
                    $fields[] = Field::make($sourceField);
                    $fieldNames[] = $sourceField;
                }
            }
        }

        return $fields;
    }

    /**
     * Redirect pipe source field auto-mappings to the pipe's target field(s).
     *
     * - MergeColumnsPipe: redirects source fields (first_name, last_name)
     *   to the target field (notes) — AdvancedMapper merges them.
     * - SplitColumnPipe: redirects the source field (full_name)
     *   to ALL target fields (first_name, last_name) — AdvancedMapper
     *   handles the split.
     *
     * @return array<string, string|array<int, string>>
     */
    protected function applyAutoMappingRedirects(array $autoMapping, array $headers): array
    {
        foreach ($this->pipeline->getGlobalPipes() as $pipe) {
            if ($pipe instanceof MergeColumnsPipe) {
                $targetField = $pipe->getTargetField();
                foreach ($pipe->getSourceFields() as $sourceField) {
                    if (in_array($sourceField, $headers, true) && isset($autoMapping[$sourceField])) {
                        $autoMapping[$sourceField] = $targetField;
                    }
                }
            } elseif ($pipe instanceof SplitColumnPipe) {
                $sourceField = $pipe->getSourceField();
                if (in_array($sourceField, $headers, true) && isset($autoMapping[$sourceField])) {
                    $autoMapping[$sourceField] = $pipe->getTargetFields();
                }
            }
        }

        return $autoMapping;
    }
}
