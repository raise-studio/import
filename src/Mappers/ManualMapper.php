<?php

namespace RaiseStudio\Import\Mappers;

class ManualMapper
{
    /**
     * Apply user-defined column mapping to a row.
     *
     * @param array<string, mixed> $row Raw row from file (header => value)
     * @param array<string, string> $mapping [fileHeader => fieldName]
     * @return array<string, mixed> [fieldName => value]
     */
    public function apply(array $row, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $fileHeader => $fieldName) {
            if ($fieldName === '' || $fieldName === null) {
                continue;
            }
            $mapped[$fieldName] = $row[$fileHeader] ?? null;
        }

        return $mapped;
    }
}
