<?php

namespace RaiseStudio\Import\Pipes;

class SplitColumnPipe implements ImportPipe
{
    /**
     * @param string $sourceField  源列名（将被拆分）
     * @param array<int, string> $targetFields 目标字段名列表
     * @param string $delimiter 分隔符（用于 explode）
     * @param \Closure|null $splitter 自定义拆分回调，接收源值，返回字符串数组
     */
    public function __construct(
        protected string $sourceField,
        protected array $targetFields,
        protected string $delimiter = ' ',
        protected ?\Closure $splitter = null,
    ) {}

    /**
     * Get the source field name that will be split.
     */
    public function getSourceField(): string
    {
        return $this->sourceField;
    }

    /**
     * Get the target field names where split values will be written.
     *
     * @return array<int, string>
     */
    public function getTargetFields(): array
    {
        return $this->targetFields;
    }

    public function handle(array $row, \Closure $next): array
    {
        if (!isset($row[$this->sourceField])) {
            return $next($row);
        }

        $parts = $this->splitter
            ? call_user_func($this->splitter, $row[$this->sourceField])
            : explode($this->delimiter, $row[$this->sourceField]);

        foreach ($this->targetFields as $i => $field) {
            $row[$field] = $parts[$i] ?? '';
        }

        // Only remove source if it's not also a target (avoids double-processing)
        if (!in_array($this->sourceField, $this->targetFields)) {
            unset($row[$this->sourceField]);
        }

        return $next($row);
    }
}
