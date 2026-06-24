<?php

namespace RaiseStudio\Import\Pro\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use RaiseStudio\Import\Enums\DuplicateBehavior;
use RaiseStudio\Import\Enums\ImportStatus;
use RaiseStudio\Import\Filament\Version;
use RaiseStudio\Import\Importers\BulkImporter;
use RaiseStudio\Import\License;
use RaiseStudio\Import\Pro\Models\ImportLog;
use RaiseStudio\Import\Readers\ReaderFactory;

class ImportLogResource extends Resource
{
    protected static ?string $model = ImportLog::class;

    protected static ?int $navigationSort = 99;

    /**
     * Custom navigation config storage.
     * Using separate private props to avoid type conflicts across Filament versions
     * (Filament 4: ?string, Filament 5: UnitEnum|string|null).
     *
     * @internal
     */
    private static ?string $customNavigationGroup = null;

    private static ?string $customNavigationIcon = null;

    private static ?string $customNavigationLabel = null;

    public static function canViewAny(): bool
    {
        return License::isPro();
    }

    /**
     * Apply Plugin-level navigation configuration.
     * Called from RaiseImportPlugin::register().
     *
     * @internal
     */
    public static function applyNavigationConfig(
        ?string $group = null,
        ?string $label = null,
        ?string $icon = null,
    ): void {
        if ($group !== null) {
            static::$customNavigationGroup = $group;
        }
        if ($label !== null) {
            static::$customNavigationLabel = $label;
        }
        if ($icon !== null) {
            static::$customNavigationIcon = $icon;
        }
    }

    public static function getNavigationIcon(): string|null
    {
        return static::$customNavigationIcon ?? 'heroicon-o-clock';
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$customNavigationGroup ?? 'Tools';
    }

    public static function getNavigationLabel(): string
    {
        return static::$customNavigationLabel ?? __('raise-import::messages.resources.import_logs');
    }

    public static function getPluralModelLabel(): string
    {
        return __('raise-import::messages.resources.import_logs');
    }

    public static function getModelLabel(): string
    {
        return __('raise-import::messages.resources.import_log');
    }

    /**
     * Get the table action class based on Filament version.
     */
    protected static function getTableActionClass(): string
    {
        return Version::isV5()
            ? \Filament\Actions\Action::class
            : \Filament\Tables\Actions\Action::class;
    }

    public static function table(Table $table): Table
    {
        $actionClass = static::getTableActionClass();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('original_file_name')
                    ->label(__('raise-import::messages.import_log.file_name'))
                    ->searchable()
                    ->limit(40)
                    ->description(fn (ImportLog $record) => str($record->file_name)->limit(20)),
                Tables\Columns\TextColumn::make('model_class')
                    ->label(__('raise-import::messages.import_log.model_class'))
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')
                    ->badge(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('raise-import::messages.import_log.user'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_rows')
                    ->label(__('raise-import::messages.import_log.total_rows'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric(),
                Tables\Columns\TextColumn::make('imported_count')
                    ->label(__('raise-import::messages.import_log.imported'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('success')
                    ->numeric(),
                Tables\Columns\TextColumn::make('skipped_count')
                    ->label(__('raise-import::messages.import_log.skipped'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('warning')
                    ->numeric(),
                Tables\Columns\TextColumn::make('failed_count')
                    ->label(__('raise-import::messages.import_log.failed'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('danger')
                    ->numeric(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('raise-import::messages.import_log.status'))
                    ->badge()
                    ->formatStateUsing(fn (ImportStatus $state) => $state->label())
                    ->color(fn (ImportStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('method')
                    ->label(__('raise-import::messages.import_method'))
                    ->badge()
                    ->state(fn (ImportLog $record) => !empty($record->meta['queued']) ? 'async' : 'sync')
                    ->formatStateUsing(fn (string $state) => $state === 'async'
                        ? '⏳ ' . __('raise-import::messages.import_method_async')
                        : '⚡ ' . __('raise-import::messages.import_method_sync'))
                    ->color(fn (string $state) => $state === 'async' ? 'warning' : 'success')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('raise-import::messages.import_log.created_at'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->label(__('raise-import::messages.import_log.started_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('finished_at')
                    ->label(__('raise-import::messages.import_log.finished_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('duration')
                    ->label(__('raise-import::messages.import_log.duration'))
                    ->state(function (ImportLog $record) {
                        if (!$record->started_at) {
                            return '—';
                        }
                        $end = $record->finished_at ?? now();
                        $seconds = $record->started_at->diffInSeconds($end);
                        if ($seconds < 60) {
                            return $seconds . 's';
                        }
                        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actionsColumnLabel(__('raise-import::messages.import_log.actions'))
            ->selectable()
            ->filters([
                SelectFilter::make('status')
                    ->label(__('raise-import::messages.import_log.status'))
                    ->options(
                        collect(ImportStatus::cases())
                            ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                    ),

                SelectFilter::make('model_class')
                    ->label(__('raise-import::messages.import_log.model_class'))
                    ->options(
                        ImportLog::query()
                            ->select('model_class')
                            ->distinct()
                            ->pluck('model_class', 'model_class')
                            ->map(fn ($v) => class_basename($v))
                    ),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')
                            ->label(__('raise-import::messages.import_log.date_from')),
                        DatePicker::make('to')
                            ->label(__('raise-import::messages.import_log.date_to')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['to'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                $actionClass::make('view_details')
                    ->label(__('raise-import::messages.import_log.details'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(__('raise-import::messages.import_log.details'))
                    ->modalSubmitAction(false)
                    ->modalContent(function (ImportLog $record) {
                        return view('raise-import::import-log-details', ['record' => $record]);
                    }),

                // Download error report
                $actionClass::make('download_errors')
                    ->label(__('raise-import::messages.results.download_errors'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function (ImportLog $record) {
                        $errors = $record->errors ?? [];

                        if (empty($errors)) {
                            \Filament\Notifications\Notification::make()
                                ->info()
                                ->title(__('raise-import::messages.errors.no_errors'))
                                ->send();

                            return;
                        }

                        $csv = "row,field,value,error\n";
                        foreach ($errors as $error) {
                            if (is_array($error)) {
                                $csv .= "{$error['row']},\"{$error['field']}\",\"{$error['value']}\",\"{$error['error']}\"\n";
                            } elseif (is_string($error)) {
                                // Backward compatibility: legacy string errors
                                $csv .= "0,,,\"{$error}\"\n";
                            }
                        }

                        $filename = 'import-errors-' . $record->id . '.csv';

                        return response()->streamDownload(function () use ($csv) {
                            echo $csv;
                        }, $filename);
                    })
                    ->visible(fn (ImportLog $record) => !empty($record->errors)),

                // Re-import button (visible on failed/partial records)
                $actionClass::make('re_import')
                    ->label(__('raise-import::messages.import_log.re_import'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (ImportLog $record) => $record->isFailed() || $record->isPartial())
                    ->modalHeading(__('raise-import::messages.import_log.re_import'))
                    ->modalWidth('2xl')
                    ->form([
                        FileUpload::make('file')
                            ->label(__('raise-import::messages.upload.label'))
                            ->required()
                            ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.oasis.opendocument.spreadsheetml.sheet', 'text/plain'])
                            ->maxSize(config('raise-import.max_file_size', 50) * 1024)
                            ->helperText(__('raise-import::messages.import_log.re_import_hint_upload')),
                    ])
                    ->action(function (ImportLog $record, array $data) {
                        $file = $data['file'];
                        $meta = $record->meta ?? [];

                        // Ensure it's a single uploaded file
                        $uploadedFile = is_array($file) ? $file[0] : $file;
                        if (!$uploadedFile) {
                            Notification::make()
                                ->danger()
                                ->title('No file uploaded')
                                ->send();
                            return;
                        }

                        // Store the file (v5 string path vs v4 UploadedFile)
                        if (is_string($uploadedFile)) {
                            $srcPath = \Illuminate\Support\Facades\Storage::path($uploadedFile);
                            if (!file_exists($srcPath)) { $srcPath = $uploadedFile; }
                            if (!file_exists($srcPath)) {
                                Notification::make()->danger()->title('File not found')->send();
                                return;
                            }
                            $destRel = 'imports/' . date('Y/m/d') . '/' . basename($uploadedFile);
                            \Illuminate\Support\Facades\Storage::makeDirectory(dirname($destRel));
                            copy($srcPath, \Illuminate\Support\Facades\Storage::path($destRel));
                            $fullPath = \Illuminate\Support\Facades\Storage::path($destRel);
                        } else {
                            $stored = $uploadedFile->store('imports/' . date('Y/m/d'));
                            if (!$stored) {
                                Notification::make()->danger()->title('Failed to store file')->send();
                                return;
                            }
                            $fullPath = \Illuminate\Support\Facades\Storage::path($stored);
                        }

                        // Read file
                        $reader = ReaderFactory::create($fullPath);
                        $headers = $reader->headers($fullPath);
                        $allRows = iterator_to_array($reader->rows($fullPath));

                        // Reuse old config from meta
                        $rawMapping = $meta['mapping'] ?? [];
                        $onDuplicate = $meta['on_duplicate'] ?? 'skip';
                        $uniqueBy = $meta['unique_by'] ?? [];

                        // Normalize mapping to flat key-value format for re-import
                        // Supports: ['name' => 'name'], ['name' => ['name']], [['file_header'=>'name','field_name'=>['name']]]
                        $normalizedMapping = [];
                        foreach ($rawMapping as $key => $value) {
                            // Raw form data: [['file_header' => 'name', 'field_name' => ['name']]]
                            if (is_array($value) && isset($value['file_header'])) {
                                $header = $value['file_header'];
                                $names = (array)($value['field_name'] ?? []);
                                foreach ($names as $name) {
                                    if (!empty($name)) {
                                        $normalizedMapping[$header] = $name;
                                    }
                                }
                            }
                            // New format: ['name' => ['name']]
                            elseif (is_array($value)) {
                                foreach ($value as $name) {
                                    if (!empty($name)) {
                                        $normalizedMapping[$key] = $name;
                                    }
                                }
                            }
                            // Legacy format: ['name' => 'name']
                            elseif (!empty($value)) {
                                $normalizedMapping[$key] = $value;
                            }
                        }

                        // Build mapped rows using normalized mapping
                        $mappedRows = [];
                        foreach ($allRows as $row) {
                            $mapped = [];
                            foreach ($normalizedMapping as $fileHeader => $fieldName) {
                                if (isset($row[$fileHeader])) {
                                    $mapped[$fieldName] = $row[$fileHeader];
                                }
                            }
                            if (!empty($mapped)) {
                                $mappedRows[] = $mapped;
                            }
                        }

                        if (empty($mappedRows)) {
                            Notification::make()
                                ->danger()
                                ->title('No valid rows after mapping')
                                ->send();
                            return;
                        }

                        // Create new ImportLog — copy filenames from the original record
                        $newLog = ImportLog::create([
                            'user_id' => auth()->id(),
                            'model_class' => $record->model_class,
                            'file_name' => $record->file_name,
                            'original_file_name' => $record->original_file_name,
                            'file_path' => $fullPath,
                            'total_rows' => count($mappedRows),
                            'status' => ImportStatus::Processing,
                            'started_at' => now(),
                            'meta' => $meta,
                        ]);

                        try {
                            $importer = new BulkImporter(
                                modelClass: $record->model_class,
                                uniqueBy: $uniqueBy,
                                onDuplicate: DuplicateBehavior::from($onDuplicate),
                            );

                            $result = $importer->import($mappedRows);

                            $status = match (true) {
                                $result['failed'] > 0 && $result['imported'] === 0 => ImportStatus::Failed,
                                $result['failed'] > 0 || $result['skipped'] > 0 => ImportStatus::Partial,
                                default => ImportStatus::Completed,
                            };

                            $newLog->update([
                                'imported_count' => $result['imported'],
                                'skipped_count' => $result['skipped'],
                                'failed_count' => $result['failed'],
                                'status' => $status,
                                'errors' => $result['failedRows'],
                                'finished_at' => now(),
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Re-import completed')
                                ->body("Imported: {$result['imported']}, Skipped: {$result['skipped']}, Failed: {$result['failed']}")
                                ->send();
                        } catch (\Throwable $e) {
                            $newLog->update([
                                'status' => ImportStatus::Failed,
                                'errors' => ['fatal' => $e->getMessage()],
                                'finished_at' => now(),
                            ]);

                            Notification::make()
                                ->danger()
                                ->title('Re-import failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // Delete action (version-agnostic implementation)
                $actionClass::make('delete')
                    ->label(__('raise-import::messages.import_log.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('raise-import::messages.import_log.delete'))
                    ->modalDescription(__('raise-import::messages.import_log.delete_confirm'))
                    ->action(fn (ImportLog $record) => $record->delete()),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('delete_selected')
                    ->label(__('raise-import::messages.import_log.delete_selected'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Support\Collection $records) {
                        $records->each->delete();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \RaiseStudio\Import\Pro\Resources\ImportLogResource\Pages\ManageImportLogs::route('/'),
        ];
    }
}
