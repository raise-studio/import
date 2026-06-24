<?php

namespace RaiseStudio\Import\Pipes;

class ClosurePipe implements ImportPipe
{
    protected \Closure $callback;

    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Adapt a Closure to become a pipe in the pipeline.
     */
    public function handle(array $row, \Closure $next): array
    {
        $row = call_user_func($this->callback, $row);

        return $next($row);
    }
}
