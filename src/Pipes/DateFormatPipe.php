<?php

namespace RaiseStudio\Import\Pipes;

class DateFormatPipe implements ImportPipe
{
    protected string $targetFormat;

    protected array $fields;

    public function __construct(string $targetFormat = 'Y-m-d H:i:s', array $fields = ['created_at', 'updated_at'])
    {
        $this->targetFormat = $targetFormat;
        $this->fields = $fields;
    }

    /**
     * Normalize date fields to a consistent format.
     */
    public function handle(array $row, \Closure $next): array
    {
        foreach ($this->fields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                try {
                    $row[$field] = \Carbon\Carbon::parse($row[$field])->format($this->targetFormat);
                } catch (\Throwable $e) {
                    // Keep original value, let validation catch it
                }
            }
        }

        return $next($row);
    }
}
