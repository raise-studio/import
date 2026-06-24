<?php

namespace RaiseStudio\Import\Readers;

interface ReaderInterface
{
    /**
     * Read rows from the given file path.
     *
     * Returns a lazy collection or array of associative arrays.
     *
     * @return \Generator|array<int, array<string, mixed>>
     */
    public function rows(string $filePath): \Generator;

    /**
     * Get the header row (first row) of the file.
     *
     * @return array<int, string>
     */
    public function headers(string $filePath): array;

    /**
     * Get total row count (excluding header).
     */
    public function count(string $filePath): int;

    /**
     * Get a preview of the first N rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function preview(string $filePath, int $limit = 10): array;
}
