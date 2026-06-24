<?php

namespace RaiseStudio\Import\Pipes;

class MergeColumnsPipe implements ImportPipe
{
    /**
     * @param array<int, string> $sourceFields 源字段名列表（将被合并）
     * @param string $targetField 目标字段名（合并后的值写入此字段）
     * @param string $separator 分隔符（用于 implode）
     */
    public function __construct(
        protected array $sourceFields,
        protected string $targetField,
        protected string $separator = ' ',
    ) {}

    /**
     * Get the list of source field names that need to be mapped.
     * Used by the import wizard to auto-inject these as virtual mapping fields.
     *
     * @return array<int, string>
     */
    public function getSourceFields(): array
    {
        return $this->sourceFields;
    }

    /**
     * Get the target field name where merged result will be written.
     */
    public function getTargetField(): string
    {
        return $this->targetField;
    }

    /**
     * Merge multiple source columns into a single target column.
     *
     * If the source fields are not present in the row AND the target field
     * already contains data (merged directly by the AdvancedMapper),
     * the pipe skips its transformation to avoid overwriting.
     */
    public function handle(array $row, \Closure $next): array
    {
        // Check if source fields exist in the row
        $hasSource = false;
        foreach ($this->sourceFields as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $hasSource = true;
                break;
            }
        }

        if (!$hasSource) {
            // Source fields not found — the merge was likely handled by
            // the AdvancedMapper (multiple CSV columns mapped to target).
            // Remove any stray source keys and pass through.
            foreach ($this->sourceFields as $field) {
                unset($row[$field]);
            }

            return $next($row);
        }

        $parts = [];
        foreach ($this->sourceFields as $field) {
            $parts[] = $row[$field] ?? '';
            unset($row[$field]);
        }
        $row[$this->targetField] = trim(implode($this->separator, $parts));

        return $next($row);
    }
}
