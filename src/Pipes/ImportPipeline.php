<?php

namespace RaiseStudio\Import\Pipes;

class ImportPipeline
{
    /** @var array<int, ImportPipe> */
    protected array $pipes = [];

    /** @var array<string, array<int, ImportPipe>> */
    protected array $fieldPipes = [];

    /**
     * @param array<int, ImportPipe> $pipes
     */
    public function __construct(array $pipes = [])
    {
        $this->pipes = $pipes;
    }

    /**
     * Add a global pipe (affects all fields).
     *
     * @param string|ImportPipe $pipe
     * @return $this
     */
    public function pipe(string|ImportPipe $pipe): static
    {
        $this->pipes[] = is_string($pipe) ? app($pipe) : $pipe;

        return $this;
    }

    /**
     * Add a field-level pipe (affects only the specified field).
     *
     * @param string $field
     * @param string|ImportPipe $pipe
     * @return $this
     */
    public function fieldPipe(string $field, string|ImportPipe $pipe): static
    {
        $this->fieldPipes[$field][] = is_string($pipe) ? app($pipe) : $pipe;

        return $this;
    }

    /**
     * Run the pipeline on a row of data.
     *
     * Global pipes run first, then field-level pipes.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function send(array $row): array
    {
        // Run global pipes
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            fn ($next, $pipe) => fn ($row) => $pipe->handle($row, $next),
            fn ($row) => $row,
        );
        $row = $pipeline($row);

        // Run field-level pipes
        foreach ($this->fieldPipes as $field => $pipes) {
            if (array_key_exists($field, $row)) {
                $fieldPipeline = array_reduce(
                    array_reverse($pipes),
                    fn ($next, $pipe) => fn ($row) => $pipe->handle($row, $next),
                    fn ($row) => $row,
                );
                $result = $fieldPipeline([$field => $row[$field]]);
                $row[$field] = $result[$field];
            }
        }

        return $row;
    }

    /**
     * Get the count of global pipes registered.
     */
    public function count(): int
    {
        return count($this->pipes);
    }

    /**
     * Check if no pipes are registered.
     */
    public function isEmpty(): bool
    {
        return empty($this->pipes) && empty($this->fieldPipes);
    }

    /**
     * Get all global pipe instances.
     *
     * @return array<int, ImportPipe>
     */
    public function getGlobalPipes(): array
    {
        return $this->pipes;
    }

    /**
     * Get all field-level pipe instances.
     *
     * @return array<string, array<int, ImportPipe>>
     */
    public function getFieldPipes(): array
    {
        return $this->fieldPipes;
    }

    /**
     * Export pipe configuration for queue serialization.
     *
     * Only captures class-based pipes (string class names + constructor params).
     * Closure-based pipes (ClosurePipe) cannot be serialized and will be skipped.
     *
     * @return array<int, array{class: string, params: array, field: string|null}>
     */
    public function toConfig(): array
    {
        $configs = [];

        foreach ($this->pipes as $pipe) {
            $config = self::resolvePipeConfig($pipe);
            if ($config !== null) {
                $configs[] = $config;
            }
        }

        foreach ($this->fieldPipes as $field => $pipes) {
            foreach ($pipes as $pipe) {
                $config = self::resolvePipeConfig($pipe);
                if ($config !== null) {
                    $config['field'] = $field;
                    $configs[] = $config;
                }
            }
        }

        return $configs;
    }

    /**
     * Resolve a pipe instance to a serializable config array.
     * Returns null for closure-based pipes that can't be serialized.
     *
     * @return array{class: string, params: array, field: string|null}|null
     */
    protected static function resolvePipeConfig(ImportPipe $pipe): ?array
    {
        // ClosurePipe wraps closures which can't be serialized
        if ($pipe instanceof ClosurePipe) {
            return null;
        }

        // Use reflection to get constructor parameters for serialization
        try {
            $ref = new \ReflectionClass($pipe);
            $constructor = $ref->getConstructor();
            $params = [];

            if ($constructor) {
                foreach ($constructor->getParameters() as $param) {
                    if ($param->isDefaultValueAvailable()) {
                        $params[] = $param->getDefaultValue();
                    } else {
                        $property = $ref->getProperty($param->getName());
                        $property->setAccessible(true);
                        $params[] = $property->getValue($pipe);
                    }
                }
            }

            return [
                'class' => get_class($pipe),
                'params' => $params,
                'field' => null,
            ];
        } catch (\ReflectionException) {
            return null;
        }
    }
}
