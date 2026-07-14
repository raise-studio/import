<?php

namespace RaiseStudio\Import\Pro\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use RaiseStudio\Import\Exports\TemplateExport;
use RaiseStudio\Import\Fields\Field;
use RaiseStudio\Import\License;
use RaiseStudio\Import\Pro\Models\ImportLog;
use RaiseStudio\Import\Readers\ReaderFactory;

class ImportController extends Controller
{
    public function __construct()
    {
        // Independent Pro gate — re-validates even if isPro() was patched.
        if (!License::gatePro()) {
            abort(403, 'This feature requires Raise Import Pro license.');
        }
    }

    /**
     * Upload file and return headers/preview.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,ods|max:' . (config('raise-import.max_file_size', 50) * 1024),
            'model_class' => 'required|string',
        ]);

        $file = $request->file('file');
        $path = $file->store('imports/' . date('Y/m/d'));

        $fullPath = Storage::path($path);
        $reader = ReaderFactory::create($fullPath);

        return response()->json([
            'path' => $path,
            'headers' => $reader->headers($fullPath),
            'preview' => $reader->preview($fullPath, config('raise-import.preview_limit', 10)),
            'total_rows' => $reader->count($fullPath),
        ]);
    }

    /**
     * Preview data with mapping applied.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'mapping' => 'required|array',
        ]);

        $fullPath = Storage::path($request->path);
        $reader = ReaderFactory::create($fullPath);
        $mapping = $request->mapping;

        $previewRows = [];
        foreach ($reader->rows($fullPath) as $index => $row) {
            if ($index >= 10) {
                break;
            }

            $mapped = [];
            foreach ($mapping as $fileHeader => $fieldName) {
                if (!empty($fieldName)) {
                    $mapped[$fieldName] = $row[$fileHeader] ?? null;
                }
            }
            $previewRows[] = $mapped;
        }

        return response()->json([
            'rows' => $previewRows,
            'total' => $reader->count($fullPath),
        ]);
    }

    /**
     * Execute import.
     */
    public function import(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'model_class' => 'required|string',
            'mapping' => 'required|array',
            'fields' => 'nullable|array',
            'unique_by' => 'nullable|array',
            'on_duplicate' => 'nullable|string|in:skip,update,error',
        ]);

        $fullPath = Storage::path($request->path);
        $reader = ReaderFactory::create($fullPath);

        $allRows = iterator_to_array($reader->rows($fullPath));
        $mapping = $request->mapping;

        // Build mapped rows
        $mappedRows = array_map(function ($row) use ($mapping) {
            $mapped = [];
            foreach ($mapping as $fileHeader => $fieldName) {
                if (!empty($fieldName)) {
                    $mapped[$fieldName] = $row[$fileHeader] ?? null;
                }
            }

            return $mapped;
        }, $allRows);

        // Create import log
        $importLog = ImportLog::create([
            'user_id' => auth()->id(),
            'model_class' => $request->model_class,
            'file_name' => basename($request->path),
            'original_file_name' => $request->header('X-Original-Name', basename($request->path)),
            'file_path' => $fullPath,
            'total_rows' => count($mappedRows),
            'status' => \RaiseStudio\Import\Enums\ImportStatus::Processing,
            'started_at' => now(),
            'meta' => [
                'mapping' => $mapping,
                'on_duplicate' => $request->on_duplicate ?? 'skip',
                'unique_by' => $request->unique_by ?? [],
            ],
        ]);

        try {
            $importer = new \RaiseStudio\Import\Importers\BulkImporter(
                modelClass: $request->model_class,
                uniqueBy: $request->unique_by ?? [],
                onDuplicate: \RaiseStudio\Import\Enums\DuplicateBehavior::from($request->on_duplicate ?? 'skip'),
            );

            $importer->chunkSize(config('raise-import.chunk_size', 500));
            $result = $importer->import($mappedRows);

            $status = match (true) {
                $result['failed'] > 0 && $result['imported'] === 0 => \RaiseStudio\Import\Enums\ImportStatus::Failed,
                $result['failed'] > 0 || $result['skipped'] > 0 => \RaiseStudio\Import\Enums\ImportStatus::Partial,
                default => \RaiseStudio\Import\Enums\ImportStatus::Completed,
            };

            $importLog->update([
                'imported_count' => $result['imported'],
                'skipped_count' => $result['skipped'],
                'failed_count' => $result['failed'],
                'status' => $status,
                'errors' => $result['failedRows'],
                'finished_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'import_log_id' => $importLog->id,
            ]);
        } catch (\Throwable $e) {
            $importLog->update([
                'status' => \RaiseStudio\Import\Enums\ImportStatus::Failed,
                'errors' => ['fatal' => $e->getMessage()],
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download template CSV for a model class.
     */
    public function template(string $modelClass)
    {
        $modelClass = str_replace('_', '\\', $modelClass);

        if (!class_exists($modelClass)) {
            abort(404, "Model class {$modelClass} not found.");
        }

        $model = new $modelClass();
        $fillable = $model->getFillable();

        $fields = array_map(function ($column) {
            return Field::make($column)->label(ucwords(str_replace('_', ' ', $column)));
        }, $fillable);

        $export = new TemplateExport();

        return $export->download($fields, 'import-template.csv');
    }

    /**
     * Download error report CSV.
     */
    public function downloadErrors(ImportLog $importLog)
    {
        $errors = $importLog->errors ?? [];

        if (empty($errors)) {
            abort(404, 'No errors found.');
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

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"import-errors-{$importLog->id}.csv\"",
        ]);
    }
}
