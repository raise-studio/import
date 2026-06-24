<?php

namespace RaiseStudio\Import\Pipes;

interface ImportPipe
{
    /**
     * Process a row of data and pass it to the next pipe.
     *
     * @param array<string, mixed> $row
     * @param \Closure $next
     * @return array<string, mixed>
     */
    public function handle(array $row, \Closure $next): array;
}
