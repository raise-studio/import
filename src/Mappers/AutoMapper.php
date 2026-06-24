<?php

namespace RaiseStudio\Import\Mappers;

use RaiseStudio\Import\Fields\Field;

class AutoMapper
{
    /**
     * Auto-map file headers to system fields.
     *
     * Uses fuzzy matching: lowercase comparison, remove whitespace, common aliases.
     *
     * @param array<int, string> $fileHeaders
     * @param array<int, Field> $fields
     * @return array<string, string> [fileHeader => fieldName]
     */
    public function map(array $fileHeaders, array $fields): array
    {
        $fieldMap = $this->buildFieldIndex($fields);
        $mapping = [];

        foreach ($fileHeaders as $header) {
            $normalized = $this->normalize($header);
            $matched = false;

            // Exact match
            if (isset($fieldMap[$normalized])) {
                $mapping[$header] = $fieldMap[$normalized];
                continue;
            }

            // Fuzzy match: contains
            foreach ($fieldMap as $key => $fieldName) {
                $similar = similar_text($normalized, $key, $percent);
                if ($percent >= 70) {
                    $mapping[$header] = $fieldName;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $mapping[$header] = '';
            }
        }

        return $mapping;
    }

    /**
     * Build a normalized field name index with aliases.
     *
     * @param array<int, Field> $fields
     * @return array<string, string>
     */
    protected function buildFieldIndex(array $fields): array
    {
        $index = [];

        foreach ($fields as $field) {
            $name = $field->getName();
            $label = $field->getLabel();

            $index[$this->normalize($name)] = $name;
            $index[$this->normalize($label)] = $name;

            // Common aliases
            $aliases = $this->getCommonAliases($name);
            foreach ($aliases as $alias) {
                $index[$alias] = $name;
            }
        }

        return $index;
    }

    /**
     * Normalize a string for comparison.
     */
    protected function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', '', $value)));
    }

    /**
     * Get common aliases for a field name.
     *
     * @return array<string>
     */
    protected function getCommonAliases(string $fieldName): array
    {
        $aliases = [
            'name' => ['姓名', '名字', '名称', 'full_name', 'username', '用户姓名'],
            'email' => ['邮箱', '邮件', 'e-mail', 'mail', '电子邮箱'],
            'phone' => ['电话', '手机', '手机号', '联系电话', 'tel', 'telephone', 'mobile'],
            'password' => ['密码', 'pwd', 'pass'],
            'status' => ['状态', '状态值', 'status_name'],
            'created_at' => ['创建时间', '创建日期', 'created', 'created_date'],
            'updated_at' => ['更新时间', '更新日期', 'updated', 'updated_date'],
        ];

        $normalizedName = $this->normalize($fieldName);

        return $aliases[$normalizedName] ?? [];
    }
}
