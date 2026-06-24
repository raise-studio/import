<?php

namespace RaiseStudio\Import\Pipes;

class DefaultValuePipe implements ImportPipe
{
    protected array $defaults;

    public function __construct(array $defaults)
    {
        $this->defaults = $defaults;
    }

    /**
     * Fill empty fields with default values.
     */
    public function handle(array $row, \Closure $next): array
    {
        foreach ($this->defaults as $field => $value) {
            if (! isset($row[$field]) || $row[$field] === '' || $row[$field] === null) {
                $row[$field] = $value;
            }
        }

        return $next($row);
    }
}
