<?php

namespace RaiseStudio\Import\Validators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BatchValidator
{
    /**
     * Build an in-memory uniqueness index from the database.
     *
     * @param class-string<Model> $modelClass
     * @param array<int, string> $uniqueBy Column names to check uniqueness on
     * @return array<string, array<int, mixed>> [fieldName => [value1, value2, ...]]
     */
    public function buildUniquenessIndex(string $modelClass, array $uniqueBy): array
    {
        $index = [];

        foreach ($uniqueBy as $field) {
            $values = $modelClass::query()
                ->pluck($field)
                ->map(fn ($v) => (string) $v)
                ->values()
                ->toArray();

            $index[$field] = array_flip($values);
        }

        return $index;
    }

    /**
     * Build uniqueness index from a set of rows being imported (inter-row duplicates).
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $uniqueBy
     * @return array<string, array<string, true>> [fieldName => [value => true]]
     */
    public function buildIncomingIndex(array $rows, array $uniqueBy): array
    {
        $index = [];

        foreach ($uniqueBy as $field) {
            $index[$field] = [];
        }

        return $index;
    }

    /**
     * Check if a row is duplicate based on the uniqueness index.
     *
     * @param array<string, mixed> $row
     * @param array<int, string> $uniqueBy
     * @param array<string, array<int|string, mixed>> $existingIndex
     * @param array<string, array<string, int>>|null $incomingIndex
     * @param int $rowIndex
     * @return bool
     */
    public function isDuplicate(
        array $row,
        array $uniqueBy,
        array $existingIndex,
        ?array &$incomingIndex = null,
        int $rowIndex = 0,
    ): bool {
        foreach ($uniqueBy as $field) {
            $value = (string) ($row[$field] ?? '');

            // Check existing DB records
            if (isset($existingIndex[$field][$value])) {
                return true;
            }

            // Check incoming (in-batch) duplicates using a "seen" set.
            // First occurrence of a value is NOT a duplicate;
            // subsequent occurrences ARE duplicates.
            if ($incomingIndex !== null) {
                if (isset($incomingIndex[$field][$value])) {
                    return true; // Already seen → duplicate
                }
                $incomingIndex[$field][$value] = true; // Mark as seen
            }
        }

        return false;
    }
}
