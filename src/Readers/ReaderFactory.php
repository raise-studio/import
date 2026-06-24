<?php

namespace RaiseStudio\Import\Readers;

class ReaderFactory
{
    /** @var array<string, string> Common CSV delimiters to detect */
    protected const DELIMITERS = [',', ';', "\t", '|'];

    /**
     * Create a reader for the given file.
     */
    public static function create(string $filePath): ReaderInterface
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => new CsvReader(
                separator: self::detectDelimiter($filePath),
            ),
            'xlsx', 'ods' => new ExcelReader(),
            default => throw new \InvalidArgumentException(
                "Unsupported file format: {$extension}. Supported formats: csv, xlsx, ods."
            ),
        };
    }

    /**
     * Auto-detect the CSV field delimiter by reading the first line.
     *
     * Counts occurrences of common delimiters and picks the one with the
     * most matches. Falls back to comma if detection is inconclusive.
     */
    protected static function detectDelimiter(string $filePath): string
    {
        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            return ',';
        }

        $firstLine = fgets($handle, 4096);
        fclose($handle);

        if ($firstLine === false || trim($firstLine) === '') {
            return ',';
        }

        $bestDelimiter = ',';
        $bestCount = 0;

        foreach (self::DELIMITERS as $delimiter) {
            $count = substr_count($firstLine, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }
}
