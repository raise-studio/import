<?php

namespace RaiseStudio\Import\Validators;

use Illuminate\Support\Facades\Validator;
use RaiseStudio\Import\Fields\Field;

class RowValidator
{
    /**
     * @var array<int, array{row: int, field: string, value: mixed, error: string}>
     */
    protected array $errors = [];

    /**
     * Validate a single row against field rules.
     *
     * @param array<string, mixed> $row Mapped row data
     * @param array<int, Field> $fields Field definitions
     * @param int $rowIndex Row number in the original file (for error reporting)
     * @return array<string, mixed> Cleaned valid row data
     */
    public function validate(array $row, array $fields, int $rowIndex): array
    {
        $rules = [];
        $data = [];

        foreach ($fields as $field) {
            $fieldName = $field->getName();
            $fieldRules = $field->getRules();

            if ($field->isRequired() && !in_array('required', $fieldRules)) {
                $fieldRules[] = 'required';
            }

            if (!empty($fieldRules)) {
                $rules[$fieldName] = $fieldRules;
            }

            if (array_key_exists($fieldName, $row)) {
                $data[$fieldName] = $row[$fieldName];
            } elseif ($field->getDefault() !== null) {
                $data[$fieldName] = $field->getDefault();
            }
        }

        // If no rules, return data directly (no validation needed)
        if (empty($rules)) {
            return $data;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->toArray() as $fieldName => $messages) {
                foreach ($messages as $message) {
                    $this->errors[] = [
                        'row' => $rowIndex,
                        'field' => $fieldName,
                        'value' => $data[$fieldName] ?? null,
                        'error' => $message,
                    ];
                }
            }

            return [];
        }

        // Return all row data — validated() only returns fields with rules,
        // which silently drops fields like name that had no rules configured.
        return $data;
    }

    /**
     * Get all validation errors from previous validate calls.
     *
     * @return array<int, array{row: int, field: string, value: mixed, error: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if there were any validation errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Reset errors.
     */
    public function reset(): void
    {
        $this->errors = [];
    }
}
