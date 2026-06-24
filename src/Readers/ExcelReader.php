<?php

namespace RaiseStudio\Import\Readers;

use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;

class ExcelReader implements ReaderInterface
{
    /**
     * Read rows lazily from XLSX/ODS file.
     */
    public function rows(string $filePath): \Generator
    {
        $reader = $this->createReader($filePath);
        $reader->open($filePath);

        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $cells = $row->toArray();

                if ($index === 1) {
                    $headers = array_map('trim', $cells);
                    continue;
                }

                if ($headers === null) {
                    continue;
                }

                yield $this->mapRowToAssociative($cells, $headers);
            }
            // Only process first sheet
            break;
        }

        $reader->close();
    }

    /**
     * Get headers (first row) from Excel file.
     */
    public function headers(string $filePath): array
    {
        $reader = $this->createReader($filePath);
        $reader->open($filePath);

        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                if ($index === 1) {
                    $headers = array_map('trim', $row->toArray());
                }
                break;
            }
            break;
        }

        $reader->close();

        return $headers;
    }

    /**
     * Count data rows (excluding header).
     */
    public function count(string $filePath): int
    {
        $reader = $this->createReader($filePath);
        $reader->open($filePath);

        $count = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                if ($index > 1) {
                    $count++;
                }
            }
            break;
        }

        $reader->close();

        return $count;
    }

    /**
     * Preview first N rows.
     */
    public function preview(string $filePath, int $limit = 10): array
    {
        $reader = $this->createReader($filePath);
        $reader->open($filePath);

        $headers = null;
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $cells = $row->toArray();

                if ($index === 1) {
                    $headers = array_map('trim', $cells);
                    continue;
                }

                if (count($rows) >= $limit) {
                    break;
                }

                $rows[] = $this->mapRowToAssociative($cells, $headers);
            }
            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * Create appropriate reader based on file extension.
     */
    protected function createReader(string $filePath): XlsxReader|OdsReader
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'ods') {
            // OpenSpout's ODS SheetIterator uses assert() to check sheet style
            // name, which crashes on some ODS files that omit table:style-name.
            // Suppress assertion exceptions so the sheet is treated as visible.
            if (PHP_VERSION_ID >= 80000) {
                ini_set('assert.exception', '0');
            }
        }

        return match ($extension) {
            'xlsx' => new XlsxReader(),
            'ods' => new OdsReader(),
            default => throw new \InvalidArgumentException("Unsupported file format: {$extension}. Supported formats: xlsx, ods."),
        };
    }

    /**
     * Map indexed row cells to associative array using headers.
     */
    protected function mapRowToAssociative(array $cells, array $headers): array
    {
        $row = [];

        foreach ($headers as $i => $header) {
            $value = $cells[$i] ?? null;
            // Cast numeric values to string for consistency with CSV reader
            $row[$header] = is_null($value) ? null : (string) $value;
        }

        return $row;
    }
}
