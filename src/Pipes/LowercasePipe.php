<?php

namespace RaiseStudio\Import\Pipes;

class LowercasePipe implements ImportPipe
{
    protected array $fields;

    public function __construct(array $fields = ['email', 'username'])
    {
        $this->fields = $fields;
    }

    /**
     * Convert specified fields to lowercase.
     */
    public function handle(array $row, \Closure $next): array
    {
        foreach ($this->fields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = mb_strtolower($row[$field]);
            }
        }

        return $next($row);
    }
}
