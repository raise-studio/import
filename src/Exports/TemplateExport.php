<?php

namespace RaiseStudio\Import\Exports;

use RaiseStudio\Import\Fields\Field;

class TemplateExport
{
    /**
     * Generate a CSV template with headers based on field definitions.
     *
     * @param array<int, Field> $fields
     * @return string CSV content
     */
    public function generate(array $fields): string
    {
        $headers = array_map(fn (Field $field) => $field->getLabel(), $fields);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        // Add one example row
        $example = array_map(function (Field $field) {
            if ($field->getDefault() !== null) {
                return $field->getDefault();
            }

            $options = $field->getOptions();
            if (!empty($options)) {
                return array_key_first($options);
            }

            return '';
        }, $fields);

        fputcsv($output, $example);

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Get the download response for the CSV template.
     *
     * @param array<int, Field> $fields
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function download(array $fields, string $filename = 'import-template.csv')
    {
        $content = $this->generate($fields);

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
