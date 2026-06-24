<?php

namespace RaiseStudio\Import\Pipes;

class TrimStringsPipe implements ImportPipe
{
    /**
     * Trim whitespace from all string fields.
     */
    public function handle(array $row, \Closure $next): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = trim($value);
            }
        }

        return $next($row);
    }
}
