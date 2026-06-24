<div class="space-y-4 p-4">
    {{-- Basic Info --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.file_name') }}:</span>
            <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ $record->original_file_name ?: $record->file_name }}</span>
            @if($record->original_file_name && $record->original_file_name !== $record->file_name)
                <br><span class="text-xs text-gray-400 dark:text-gray-500 ml-2">(stored: {{ $record->file_name }})</span>
            @endif
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.model_class') }}:</span>
            <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ class_basename($record->model_class) }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.total_rows') }}:</span>
            <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ $record->total_rows }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.imported') }}:</span>
            <span class="text-sm text-green-600 dark:text-green-400 ml-2">{{ $record->imported_count }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.skipped') }}:</span>
            <span class="text-sm text-yellow-600 dark:text-yellow-400 ml-2">{{ $record->skipped_count }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.failed') }}:</span>
            <span class="text-sm text-red-600 dark:text-red-400 ml-2">{{ $record->failed_count }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.status') }}:</span>
            <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ $record->status }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.created_at') }}:</span>
            <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ $record->created_at }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.started_at') }}:</span>
            <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ $record->started_at ?? '—' }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.finished_at') }}:</span>
            <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ $record->finished_at ?? '—' }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_method') }}:</span>
            <span class="text-sm ml-2 {{ !empty($record->meta['queued']) ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400' }}">
                {{ !empty($record->meta['queued'])
                    ? '⏳ ' . __('raise-import::messages.import_method_async')
                    : '⚡ ' . __('raise-import::messages.import_method_sync') }}
            </span>
        </div>
        @if($record->started_at)
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.duration') }}:</span>
                <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">
                    @php
                        $end = $record->finished_at ?? now();
                        $seconds = $record->started_at->diffInSeconds($end);
                    @endphp
                    {{ $seconds < 60 ? $seconds . 's' : floor($seconds / 60) . 'm ' . ($seconds % 60) . 's' }}
                </span>
            </div>
        @endif
    </div>

    {{-- Import Config (from meta) --}}
    @if(!empty($record->meta))
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('raise-import::messages.import_log.config') }}</h4>
            <div class="grid grid-cols-2 gap-4">
                @if(!empty($record->meta['on_duplicate']))
                    <div>
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.duplicate_behavior') }}:</span>
                        <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ $record->meta['on_duplicate'] }}</span>
                    </div>
                @endif
                @if(!empty($record->meta['chunk_size']))
                    <div>
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.chunk_size') }}:</span>
                        <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ $record->meta['chunk_size'] }}</span>
                    </div>
                @endif
                @if(!empty($record->meta['unique_by']))
                    <div class="col-span-2">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('raise-import::messages.import_log.unique_by') }}:</span>
                        <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ implode(', ', $record->meta['unique_by']) }}</span>
                    </div>
                @endif
            </div>

            @if(!empty($record->meta['mapping']))
                <div class="mt-3">
                    <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('raise-import::messages.import_log.column_mapping') }}</h5>
                    <div style="overflow: auto; max-height: 15rem; border: 1px solid #d1d5db; border-radius: 0.5rem;">
                        <table style="width: 100%; border-collapse: collapse; min-width: 300px;">
                            <thead>
                                <tr style="background: #f3f4f6;">
                                    <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; border: 1px solid #d1d5db; white-space: nowrap;">CSV 列</th>
                                    <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; border: 1px solid #d1d5db; white-space: nowrap;">→ 字段</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($record->meta['mapping'] as $csvCol => $fieldName)
                                    @php
                                        // Handle both old format (nested array with 'field_name' key)
                                        // and new format (flat key-value or array of field names)
                                        if (is_array($fieldName) && isset($fieldName['field_name'])) {
                                            $displayValue = is_array($fieldName['field_name'])
                                                ? implode(', ', $fieldName['field_name'])
                                                : ($fieldName['field_name'] ?? '—');
                                            $displayCol = $fieldName['file_header'] ?? $csvCol;
                                        } else {
                                            $displayCol = $csvCol;
                                            $displayValue = is_array($fieldName)
                                                ? implode(', ', $fieldName)
                                                : ($fieldName ?: '—');
                                        }
                                    @endphp
                                    <tr style="background: {{ $loop->index % 2 === 0 ? '#ffffff' : '#f9fafb' }};">
                                        <td style="padding: 0.5rem 0.75rem; font-size: 0.875rem; color: #374151; border: 1px solid #d1d5db; white-space: nowrap; max-width: 10rem; overflow: hidden; text-overflow: ellipsis;">{{ $displayCol }}</td>
                                        <td style="padding: 0.5rem 0.75rem; font-size: 0.875rem; color: #374151; border: 1px solid #d1d5db; white-space: nowrap;">{{ $displayValue }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Errors Table --}}
    @if(!empty($record->errors))
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                {{ __('raise-import::messages.results.download_errors') }}
                <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-1">({{ count($record->errors) }})</span>
            </h4>

            @php
                $isStructured = is_array($record->errors[0] ?? null);
            @endphp

            @if($isStructured)
                <div style="overflow: auto; max-height: 20rem; border: 1px solid #d1d5db; border-radius: 0.5rem; margin-top: 0.5rem;">
                    <table style="width: 100%; border-collapse: collapse; min-width: 500px;">
                        <thead>
                            <tr style="background: #f3f4f6;">
                                <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; border: 1px solid #d1d5db; white-space: nowrap;">#</th>
                                <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; border: 1px solid #d1d5db; white-space: nowrap;">{{ __('raise-import::messages.import_log.field') }}</th>
                                <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; border: 1px solid #d1d5db; white-space: nowrap;">{{ __('raise-import::messages.import_log.value') }}</th>
                                <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; border: 1px solid #d1d5db; white-space: nowrap;">{{ __('raise-import::messages.import_log.error') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($record->errors as $index => $error)
                                <tr style="background: {{ $index % 2 === 0 ? '#ffffff' : '#f9fafb' }};">
                                    <td style="padding: 0.5rem 0.75rem; font-size: 0.75rem; color: #6b7280; border: 1px solid #d1d5db; white-space: nowrap; text-align: center; font-family: monospace;">
                                        {{ $error['row'] ?? '—' }}
                                    </td>
                                    <td style="padding: 0.5rem 0.75rem; font-size: 0.875rem; color: #374151; border: 1px solid #d1d5db; white-space: nowrap; font-family: monospace;">
                                        {{ $error['field'] ?? '—' }}
                                    </td>
                                    <td style="padding: 0.5rem 0.75rem; font-size: 0.875rem; color: #374151; border: 1px solid #d1d5db; white-space: nowrap; max-width: 12rem; overflow: hidden; text-overflow: ellipsis;" title="{{ $error['value'] ?? '' }}">
                                        {{ $error['value'] ?? '—' }}
                                    </td>
                                    <td style="padding: 0.5rem 0.75rem; font-size: 0.875rem; color: #dc2626; border: 1px solid #d1d5db; white-space: nowrap;">
                                        {{ $error['error'] ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="space-y-1">
                    @foreach($record->errors as $error)
                        <div class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950 p-2 rounded">
                            {{ is_string($error) ? $error : json_encode($error) }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
