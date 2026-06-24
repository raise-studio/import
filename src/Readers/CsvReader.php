<?php

namespace RaiseStudio\Import\Readers;

use OpenSpout\Reader\CSV\Reader as CsvSpoutReader;
use OpenSpout\Reader\CSV\Options;

class CsvReader implements ReaderInterface
{
    public function __construct(
        protected string $separator = ',',
        protected string $encoding = 'UTF-8',
    ) {}

    /**
     * Read rows lazily from CSV file.
     */
    public function rows(string $filePath): \Generator
    {
        $reader = $this->createReader();
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
        }

        $reader->close();
    }

    /**
     * Get headers (first row) from CSV.
     */
    public function headers(string $filePath): array
    {
        $reader = $this->createReader();
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
        $reader = $this->createReader();
        $reader->open($filePath);

        $count = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                if ($index > 1) {
                    $count++;
                }
            }
        }

        $reader->close();

        return $count;
    }

    /**
     * Preview first N rows.
     */
    public function preview(string $filePath, int $limit = 10): array
    {
        $reader = $this->createReader();
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

    protected function createReader(): CsvSpoutReader
    {
        $options = new Options();
        $options->FIELD_DELIMITER = $this->separator;
        $options->ENCODING = $this->encoding;

        return new CsvSpoutReader($options);
    }

    /**
     * Map indexed row cells to associative array using headers.
     */
    protected function mapRowToAssociative(array $cells, array $headers): array
    {
        $row = [];

        foreach ($headers as $i => $header) {
            $row[$header] = $cells[$i] ?? null;
        }

        return $row;
    }
}
