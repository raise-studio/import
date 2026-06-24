<?php

namespace RaiseStudio\Import\Mappers;

class ColumnMappingItem
{
    /**
     * @param string $fileHeader   CSV 文件中的列名
     * @param array<int, string> $fieldNames 映射到的字段名列表
     * @param bool $merged         是否合并（多个 CSV 列→一个字段）
     * @param bool $ignored        是否忽略该列
     * @param int $sortOrder       排序序号
     */
    public function __construct(
        public string $fileHeader,
        public array $fieldNames = [],
        public bool $merged = false,
        public bool $ignored = false,
        public int $sortOrder = 0,
    ) {}

    /**
     * Whether this column has a valid mapping (not ignored and has at least one field).
     */
    public function isMapped(): bool
    {
        return !$this->ignored && !empty($this->fieldNames);
    }
}
