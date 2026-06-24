<div style="overflow: auto; max-height: 20rem; border: 1px solid #d1d5db; border-radius: 0.5rem;">
    @php
        $state = $getState();
        $rawRows = is_array($state) ? ($state['rows'] ?? []) : [];
        $totalRows = is_array($state) ? ($state['total'] ?? count($rawRows)) : 0;

        // Get mapping state from the form (across wizard steps)
        $columnMapping = $get('column_mapping') ?? [];

        // Apply mapping to raw rows for preview
        $isNewFormat = !empty($columnMapping) && is_array($columnMapping[0] ?? null) && isset($columnMapping[0]['field_name']) && is_array($columnMapping[0]['field_name']);

        if ($isNewFormat && !empty($rawRows)) {
            $mapper = \RaiseStudio\Import\Mappers\AdvancedMapper::fromFormData($columnMapping);
            $previewRows = array_map(fn ($row) => $mapper->apply($row), $rawRows);

            // Apply SplitColumnPipe character-based split to preview rows.
            // Detects when first_name and last_name have the same value (from split mapping)
            // and splits first_name by first character.
            $previewRows = array_map(function ($row) {
                if (
                    isset($row['first_name'], $row['last_name']) &&
                    $row['first_name'] !== '' &&
                    $row['first_name'] === $row['last_name']
                ) {
                    $value = $row['first_name'];
                    $row['first_name'] = mb_substr($value, 0, 1);
                    $row['last_name'] = mb_substr($value, 1);
                }
                return $row;
            }, $previewRows);

            // Collect ordered field name headers from mapping items
            $previewHeaders = [];
            foreach ($mapper->all() as $item) {
                if ($item->isMapped()) {
                    foreach ($item->fieldNames as $fieldName) {
                        if (!in_array($fieldName, $previewHeaders)) {
                            $previewHeaders[] = $fieldName;
                        }
                    }
                }
            }

            // After pipes run, collect any new headers that may have been introduced
            if (!empty($previewRows)) {
                foreach (array_keys($previewRows[0]) as $key) {
                    if (!in_array($key, $previewHeaders)) {
                        $previewHeaders[] = $key;
                    }
                }
            }
        } else {
            // Legacy format or no mapping: show raw data as-is
            $previewRows = $rawRows;
            $previewHeaders = !empty($rawRows) ? array_keys($rawRows[0]) : [];
        }
    @endphp

    @if(empty($previewRows))
        <div style="padding: 1.5rem; text-align: center; color: #6b7280;">
            {{ __('raise-import::messages.preview.no_data') }}
        </div>
    @else
        <div style="padding: 0.75rem; background: #f9fafb; border-bottom: 1px solid #d1d5db;">
            <span style="font-size: 0.875rem; font-weight: 500; color: #374151;">
                {{ __('raise-import::messages.preview.total_rows', ['count' => $totalRows]) }}
            </span>
            <span style="font-size: 0.75rem; color: #6b7280; margin-left: 0.5rem;">
                ({{ __('raise-import::messages.preview.valid_rows', ['count' => count($previewRows)]) }})
            </span>
            @if($isNewFormat)
                <span style="font-size: 0.75rem; color: #059669; margin-left: 0.75rem;">
                    ✓ {{ __('raise-import::messages.mapping.auto_mapped') }}
                </span>
            @endif
        </div>

        <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
            <thead>
                <tr style="background: #f3f4f6;">
                    <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; border: 1px solid #d1d5db; white-space: nowrap;">
                        #
                    </th>
                    @foreach($previewHeaders as $header)
                        <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; border: 1px solid #d1d5db; white-space: nowrap;">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($previewRows as $index => $row)
                    <tr style="background: {{ $index % 2 === 0 ? '#ffffff' : '#f9fafb' }};">
                        <td style="padding: 0.5rem 0.75rem; font-size: 0.75rem; color: #6b7280; border: 1px solid #d1d5db; white-space: nowrap; text-align: center;">
                            {{ $index + 1 }}
                        </td>
                        @foreach($previewHeaders as $header)
                            <td style="padding: 0.5rem 0.75rem; font-size: 0.875rem; color: #374151; border: 1px solid #d1d5db; white-space: nowrap; max-width: 12rem; overflow: hidden; text-overflow: ellipsis;">
                                {{ $row[$header] ?? '—' }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
