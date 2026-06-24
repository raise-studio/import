<?php

namespace RaiseStudio\Import\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RaiseStudio\Import\Enums\DuplicateBehavior;
use RaiseStudio\Import\Exports\TemplateExport;
use RaiseStudio\Import\Fields\Field;
use RaiseStudio\Import\Filament\Version;
use RaiseStudio\Import\License;
use RaiseStudio\Import\Mappers\AutoMapper;
use RaiseStudio\Import\Mappers\ManualMapper;
use RaiseStudio\Import\Readers\ReaderFactory;
use RaiseStudio\Import\Validators\RowValidator;

class ImportAction extends Action
{
    /** @var class-string<Model>|null */
    protected ?string $modelClass = null;

    /** @var array<int, Field> */
    protected array $fields = [];

    /** @var array<int, string> */
    protected array $uniqueBy = [];

    protected DuplicateBehavior $onDuplicate = DuplicateBehavior::Skip;

    protected int $chunkSize = 500;

    /** @var array<Closure|string> */
    protected array $mutateCallbacks = [];

    /** @var array<string, array<int, string>> */
    protected array $rules = [];

    /** @var array<string, mixed> */
    protected array $previewData = [];

    protected ?Closure $fieldsResolver = null;

    /** @var class-string|null Cached ProImportAction class for auto-resolve */
    private static ?string $proActionClass = null;

    /**
     * Auto-resolve to ProImportAction when running in Pro mode.
     *
     * Developers always write ImportAction::make(...) without needing
     * to know which version is active — the correct implementation
     * is resolved automatically based on the license status.
     */
    public static function make(?string $name = null): static
    {
        if (static::class === self::class && License::isPro()) {
            $proClass = self::resolveProClass();
            if ($proClass !== null) {
                /** @var static $instance */
                return $proClass::make($name);
            }
        }

        return parent::make($name);
    }

    /**
     * Resolve the ProImportAction class name.
     * Uses cached result to avoid repeated autoloader checks.
     */
    private static function resolveProClass(): ?string
    {
        if (self::$proActionClass !== null) {
            return self::$proActionClass;
        }

        $class = 'RaiseStudio\\Import\\Pro\\Actions\\ProImportAction';
        if (class_exists($class)) {
            self::$proActionClass = $class;
            return $class;
        }

        self::$proActionClass = false;

        return null;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('raise-import::messages.action.label'))
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading(__('raise-import::messages.modal.heading'))
            ->modalWidth('3xl')
            ->modalSubmitAction(false)
            ->form($this->buildForm())
            ->action(function (array $data) {
                $this->handle($data);
            });
    }

    /**
     * Set the model class to import into.
     *
     * @param \Closure|class-string<Model>|null $modelClass
     */
    public function model(\Closure|string|null $modelClass): static
    {
        if ($modelClass instanceof \Closure) {
            $this->modelClass = $modelClass();
        } else {
            $this->modelClass = $modelClass;
        }

        return $this;
    }

    /**
     * Define import fields (auto-detected from model if omitted).
     *
     * @param array<int, Field> $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Set a custom resolver for auto-detecting fields from the model.
     */
    public function fieldsUsing(Closure $resolver): static
    {
        $this->fieldsResolver = $resolver;

        return $this;
    }

    /**
     * Set columns to check for uniqueness.
     *
     * @param array<int, string>|string $columns
     */
    public function uniqueBy(array|string $columns): static
    {
        $this->uniqueBy = is_string($columns) ? [$columns] : $columns;

        return $this;
    }

    public function onDuplicate(DuplicateBehavior|string $behavior): static
    {
        $this->onDuplicate = is_string($behavior) ? DuplicateBehavior::from($behavior) : $behavior;

        return $this;
    }

    public function chunkSize(int $size): static
    {
        $this->chunkSize = max(1, $size);

        return $this;
    }

    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Register a callback to mutate row data before creating the model.
     *
     * For advanced use cases (multiple pipes, field-level pipes),
     * ProImportAction::pipe() / fieldPipe() offer more flexibility.
     */
    public function mutateBeforeCreate(Closure $callback): static
    {
        $this->mutateCallbacks[] = $callback;

        return $this;
    }

    // -----------------------------------------------------------------------
    //  Form builder — version-aware (Filament 4 vs 5)
    // -----------------------------------------------------------------------

    protected function buildForm(): Closure
    {
        return Version::isV5()
            ? $this->buildFormV5()
            : $this->buildFormV4();
    }

    /**
     * Form builder for Filament 4 (Forms namespace: simple edition, single select, 2 columns).
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
                                    ->dehydrated(true),
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
                                            ->placeholder(__('raise-import::messages.mapping.placeholder')),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->extraAttributes(['style' => 'max-height: 20rem; overflow-y: auto;']),
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
     * Form builder for Filament V5 (simple edition: single select, 2 columns).
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
                                    ->dehydrated(true),
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
                                            ->placeholder(__('raise-import::messages.mapping.placeholder')),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->extraAttributes(['style' => 'max-height: 20rem; overflow-y: auto;']),
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

    /**
     * Handle file upload state update (shared by v4 and v5).
     */
    protected function handleFileUpload($state, $set): void
    {
        if (!$state) {
            return;
        }

        // Client-side file extension validation
        $ext = strtolower(pathinfo(
            is_string($state) ? basename($state) : ($state->getClientOriginalName() ?? ''),
            PATHINFO_EXTENSION
        ));
        $allowed = config('raise-import.allowed_extensions', ['csv', 'xlsx', 'ods']);
        if ($ext && !in_array($ext, $allowed)) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title(__('raise-import::messages.errors.invalid_file'))
                ->send();
            $this->clearUploadState($set);
            return;
        }

        // Server-side file size validation
        $maxSizeMb = (int) config('raise-import.max_file_size', 50);
        $fileSizeBytes = is_string($state)
            ? (file_exists($state) ? filesize($state) : 0)
            : $state->getSize();
        if ($fileSizeBytes > $maxSizeMb * 1048576) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title(__('raise-import::messages.errors.file_too_large', ['size' => $maxSizeMb]))
                ->send();
            $this->clearUploadState($set);
            return;
        }

        // Save the file permanently and get the absolute path
        $importFilePath = $this->persistImportFile($state);
        if (!$importFilePath) {
            $this->clearUploadState($set);
            return;
        }

        $reader = ReaderFactory::create($importFilePath);
        $headers = $reader->headers($importFilePath);

        // Check if file has any headers at all (completely empty file)
        if (empty($headers)) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title(__('raise-import::messages.errors.empty_file'))
                ->send();
            $this->clearUploadState($set);
            return;
        }

        // Check if file has any data rows
        $rowCount = $reader->count($importFilePath);
        if ($rowCount === 0) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title(__('raise-import::messages.errors.no_data'))
                ->send();
            $this->clearUploadState($set);
            return;
        }

        $fields = $this->resolveFieldsForMapping();
        $autoMapping = (new AutoMapper())->map($headers, $fields);
        $autoMapping = $this->applyAutoMappingRedirects($autoMapping, $headers);

        // Build mapping data: Pro format uses array (multi-select), community uses string
        $isPro = License::isPro();
        $repeaterData = [];
        foreach ($autoMapping as $fileHeader => $fieldName) {
            $repeaterData[] = [
                'file_header' => $fileHeader,
                'field_name' => $isPro
                    ? (is_array($fieldName) ? $fieldName : ($fieldName !== '' ? [$fieldName] : []))
                    : (is_array($fieldName) ? implode(',', $fieldName) : $fieldName),
            ];
        }

        $set('column_mapping', $repeaterData);
        $set('preview_table', [
            'rows' => $reader->preview($importFilePath, 10),
            'total' => $reader->count($importFilePath),
        ]);
        $set('import_file_path', $importFilePath);
    }

    /**
     * Clear import processing state but keep the uploaded file visible.
     * The import_file_path Hidden field's ->required() rule will then catch
     * the null value and prevent the Wizard from advancing.
     */
    protected function clearUploadState($set): void
    {
        // Keep 'file' in state so the uploaded filename stays visible
        $set('column_mapping', []);
        $set('preview_table', ['rows' => [], 'total' => 0]);
        $set('import_file_path', null);
    }

    /**
     * Persist the uploaded file to a permanent location and return its absolute path.
     * Writes a .meta sidecar file alongside with the original filename.
     */
    protected function persistImportFile($state): ?string
    {
        if (!is_string($state)) {
            $uploadedFile = is_array($state) ? ($state[0] ?? null) : $state;
            if ($uploadedFile) {
                $originalName = $uploadedFile->getClientOriginalName();
                $storedPath = $uploadedFile->store('imports');
                if ($storedPath) {
                    $absPath = \Illuminate\Support\Facades\Storage::path($storedPath);
                    $this->writeOriginalNameMeta($absPath, $originalName);
                    return $absPath;
                }
            }
        }

        if (is_string($state)) {
            $absolutePath = \Illuminate\Support\Facades\Storage::path($state);
            if (!file_exists($absolutePath)) {
                $absolutePath = $state;
            }
            if (!file_exists($absolutePath)) {
                return null;
            }

            $originalName = $this->extractOriginalNameFromLivewire($state);
            $destRelPath = 'imports/' . date('Y/m/d') . '/' . basename($state);
            $destAbsPath = \Illuminate\Support\Facades\Storage::path($destRelPath);

            try {
                \Illuminate\Support\Facades\Storage::makeDirectory(dirname($destRelPath));
                copy($absolutePath, $destAbsPath);
            } catch (\Throwable $e) {
                $this->writeOriginalNameMeta($absolutePath, $originalName);
                return $absolutePath;
            }

            $this->writeOriginalNameMeta($destAbsPath, $originalName);
            return $destAbsPath;
        }

        return null;
    }

    protected function extractOriginalNameFromLivewire(string $path): ?string
    {
        if (!class_exists(\Livewire\Features\SupportFileUploads\TemporaryUploadedFile::class)) {
            return null;
        }

        try {
            $tempFile = \Livewire\Features\SupportFileUploads\TemporaryUploadedFile::createFromLivewire($path);
            return $tempFile?->getClientOriginalName();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function writeOriginalNameMeta(string $filePath, ?string $originalName): void
    {
        if ($originalName === null || $originalName === '') {
            return;
        }
        try {
            file_put_contents($filePath . '.meta', json_encode([
                'original_name' => $originalName,
            ]));
        } catch (\Throwable) {
            // Best effort
        }
    }

    protected function readOriginalNameMeta(string $filePath): ?string
    {
        $metaPath = $filePath . '.meta';
        if (!file_exists($metaPath)) {
            return null;
        }
        try {
            $data = json_decode(file_get_contents($metaPath), true);
            return $data['original_name'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    // -----------------------------------------------------------------------
    //  Import execution
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

            // Read all rows
            $reader = ReaderFactory::create($filePath);
            $allRows = iterator_to_array($reader->rows($filePath));

            // Simple one-to-one mapping via ManualMapper
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

            $mapper = new ManualMapper();
            $mappedRows = array_map(fn ($row) => $mapper->apply($row, $mapping), $allRows);

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

            // Import
            $importer = new \RaiseStudio\Import\Importers\BulkImporter(
                modelClass: $this->modelClass,
                uniqueBy: $this->uniqueBy,
                onDuplicate: DuplicateBehavior::from($duplicateBehavior),
                mutateBeforeCreate: $this->buildMutateCallback(),
            );

            $importer->chunkSize($this->chunkSize);
            $result = $importer->import($validRows);

            $allErrors = array_merge($result['failedRows'], $validationErrors);

            Notification::make()
                ->success()
                ->title(__('raise-import::messages.results.title'))
                ->body(trans_choice('raise-import::messages.results.summary', $result['imported'], [
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                    'failed' => $result['failed'] + count($validationErrors),
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
    //  Helpers
    // -----------------------------------------------------------------------

    protected function resolveOriginalFileName(array $data, ?string $filePath, string $fallback): string
    {
        $stored = $data['original_file_names'] ?? [];
        $names = is_array($stored) ? ($stored['file'] ?? $stored) : $stored;
        if (is_array($names)) {
            $names = reset($names);
        }
        if (!empty($names)) {
            return $names;
        }

        if (!empty($data['original_file_name'])) {
            return $data['original_file_name'];
        }

        if ($filePath) {
            $fromMeta = $this->readOriginalNameMeta($filePath);
            if ($fromMeta !== null) {
                return $fromMeta;
            }
        }

        return $fallback;
    }

    /**
     * @return array<int, Field>
     */
    protected function resolveFields(): array
    {
        if (!empty($this->fields)) {
            return $this->applyCustomRules($this->fields);
        }

        if ($this->fieldsResolver) {
            $fields = call_user_func($this->fieldsResolver, $this->modelClass);

            return $this->applyCustomRules($fields);
        }

        return $this->autoDetectFields();
    }

    /**
     * Resolve fields for the mapping step.
     *
     * By default, returns the same as resolveFields(). Pro edition overrides
     * this to inject virtual fields from pipes like MergeColumnsPipe,
     * so that CSV columns matching pipe source fields are auto-mapped.
     *
     * @return array<int, Field>
     */
    protected function resolveFieldsForMapping(): array
    {
        return $this->resolveFields();
    }

    /**
     * Post-process auto-mapping results to apply pipe redirects.
     *
     * Community edition has no pipeline, so this is a no-op by default.
     * Pro edition overrides this to redirect pipe source field mappings
     * to the pipe's target field(s) — e.g., MergeColumnsPipe redirects
     * first_name → notes, and SplitColumnPipe redirects full_name
     * → [first_name, last_name].
     *
     * @param array<string, string> $autoMapping Auto-mapped headers → field names
     * @param array<int, string> $headers CSV headers
     * @return array<string, string|array<int, string>>
     */
    protected function applyAutoMappingRedirects(array $autoMapping, array $headers): array
    {
        return $autoMapping;
    }

    /**
     * @return array<int, Field>
     */
    protected function autoDetectFields(): array
    {
        if (!$this->modelClass) {
            return [];
        }

        $model = new $this->modelClass();
        $fillable = $model->getFillable();
        $table = $model->getTable();
        $columns = Schema::getColumnListing($table);

        $fields = [];
        $targetColumns = !empty($fillable) ? $fillable : $columns;
        $exclude = ['id', 'created_at', 'updated_at', 'deleted_at'];

        foreach ($targetColumns as $column) {
            if (in_array($column, $exclude)) {
                continue;
            }

            $fields[] = Field::make($column)
                ->label(ucwords(str_replace('_', ' ', $column)));
        }

        return $this->applyCustomRules($fields);
    }

    /**
     * @param array<int, Field> $fields
     * @return array<int, Field>
     */
    protected function applyCustomRules(array $fields): array
    {
        if (empty($this->rules)) {
            return $fields;
        }

        foreach ($fields as $field) {
            $name = $field->getName();
            if (isset($this->rules[$name])) {
                $field->rules($this->rules[$name]);
            }
        }

        return $fields;
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
}
