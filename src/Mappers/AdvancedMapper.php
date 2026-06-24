<?php

namespace RaiseStudio\Import\Mappers;

class AdvancedMapper
{
    /** @var array<string, ColumnMappingItem> */
    protected array $mapping = [];

    /** @var array<string, string> 合并列目标: [fileHeader => mergeGroupKey] */
    protected array $mergeGroups = [];

    /**
     * Get all mapping items (ordered by sortOrder).
     *
     * @return array<int, ColumnMappingItem>
     */
    public function all(): array
    {
        $items = array_values($this->mapping);

        usort($items, fn (ColumnMappingItem $a, ColumnMappingItem $b) => $a->sortOrder <=> $b->sortOrder);

        return $items;
    }

    /**
     * Register a mapped column.
     *
     * Calling multiple times for the same fileHeader with different fieldNames
     * creates a merge (multiple CSV columns → one field) or split (one column → multiple fields).
     */
    public function addMapping(string $fileHeader, string $fieldName): static
    {
        if (!isset($this->mapping[$fileHeader])) {
            $this->mapping[$fileHeader] = new ColumnMappingItem(
                fileHeader: $fileHeader,
                sortOrder: count($this->mapping),
            );
        }

        $item = $this->mapping[$fileHeader];

        // If the item already has fieldNames, this is a merge or split scenario
        if (!empty($item->fieldNames)) {
            $item->merged = true;
            // Track merge group
            foreach ($item->fieldNames as $existingField) {
                $this->mergeGroups[$existingField] = $existingField;
            }
            $this->mergeGroups[$fieldName] = $fieldName;
        }

        if (!in_array($fieldName, $item->fieldNames)) {
            $item->fieldNames[] = $fieldName;
        }

        return $this;
    }

    /**
     * Mark a CSV column as ignored (will not be imported).
     */
    public function ignore(string $fileHeader): static
    {
        $this->mapping[$fileHeader] = new ColumnMappingItem(
            fileHeader: $fileHeader,
            ignored: true,
            sortOrder: count($this->mapping),
        );

        return $this;
    }

    /**
     * Reorder mapping items by specifying the desired order of file headers.
     */
    public function reorder(array $fileHeadersInOrder): static
    {
        foreach ($fileHeadersInOrder as $index => $header) {
            if (isset($this->mapping[$header])) {
                $this->mapping[$header]->sortOrder = $index;
            }
        }

        return $this;
    }

    /**
     * Apply the advanced mapping to a single row of data.
     *
     * Handles three scenarios:
     * 1. One-to-one: single CSV column → single field
     * 2. Merge: multiple CSV columns → one field (e.g., first_name + last_name → name)
     * 3. Split: one CSV column → multiple fields (e.g., full_name → first_name + last_name)
     *
     * @param array<string, mixed> $row Raw row data from file (header => value)
     * @return array<string, mixed> Mapped data (fieldName => value)
     */
    public function apply(array $row): array
    {
        $result = [];
        // Track which fields have been set to detect merge (same field from different source)
        $seenFields = [];

        foreach ($this->all() as $item) {
            if ($item->ignored || empty($item->fieldNames)) {
                continue;
            }

            $value = $row[$item->fileHeader] ?? null;

            foreach ($item->fieldNames as $fieldName) {
                if ($item->merged) {
                    // Split mode: same source column → multiple fields
                    // Split by space by default
                    $parts = explode(' ', (string) ($value ?? ''));
                    foreach ($item->fieldNames as $i => $fn) {
                        $result[$fn] = $parts[$i] ?? '';
                        $seenFields[$fn] = true;
                    }
                    break;
                } elseif (isset($seenFields[$fieldName])) {
                    // Merge mode: different source columns → same field, concatenate
                    $result[$fieldName] = trim(($result[$fieldName] ?? '') . ' ' . ($value ?? ''));
                } else {
                    // One-to-one
                    $result[$fieldName] = $value;
                }

                $seenFields[$fieldName] = true;
            }
        }

        return $result;
    }

    /**
     * Build the mapper from repeater-style form data.
     *
     * @param array<int, array<string, mixed>> $formData
     * @return static
     */
    public static function fromFormData(array $formData): static
    {
        $mapper = new static();

        foreach ($formData as $item) {
            $fileHeader = $item['file_header'] ?? '';

            if (!empty($item['ignored'])) {
                $mapper->ignore($fileHeader);
                continue;
            }

            $fieldNames = (array)($item['field_name'] ?? []);

            foreach ($fieldNames as $fieldName) {
                if (empty($fieldName)) {
                    continue;
                }
                $mapper->addMapping($fileHeader, $fieldName);
            }
        }

        return $mapper;
    }
}
