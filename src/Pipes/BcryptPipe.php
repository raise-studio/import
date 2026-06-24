<?php

namespace RaiseStudio\Import\Pipes;

class BcryptPipe implements ImportPipe
{
    protected string $field;

    public function __construct(string $field = 'password')
    {
        $this->field = $field;
    }

    /**
     * Hash the specified field using bcrypt.
     *
     * When used as a global pipe via pipe(): hashes the configured field
     * (default: 'password'). When used as a field pipe via fieldPipe():
     * the row only contains the target field, which is detected and hashed
     * automatically.
     */
    public function handle(array $row, \Closure $next): array
    {
        // Detect field pipe usage: row has only the target field, hash it
        if (count($row) === 1 && isset($row[$this->field])) {
            if (is_string($row[$this->field])) {
                $row[$this->field] = bcrypt($row[$this->field]);
            }
            return $next($row);
        }

        // Global pipe usage: hash the configured field if present
        if (isset($row[$this->field]) && is_string($row[$this->field])) {
            $row[$this->field] = bcrypt($row[$this->field]);
        }

        return $next($row);
    }
}
