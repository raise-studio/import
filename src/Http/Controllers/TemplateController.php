<?php

namespace RaiseStudio\Import\Http\Controllers;

use Illuminate\Routing\Controller;
use RaiseStudio\Import\Exports\TemplateExport;
use RaiseStudio\Import\Fields\Field;

class TemplateController extends Controller
{
    /**
     * Download template CSV for a model class.
     *
     * This is a FREE feature and must remain usable without a Pro license,
     * because the "download template" link is rendered by both the free and
     * Pro import actions.
     */
    public function template(string $modelClass)
    {
        $modelClass = str_replace('_', '\\', $modelClass);

        if (!class_exists($modelClass)) {
            abort(404, "Model class {$modelClass} not found.");
        }

        $model = new $modelClass();
        $fillable = $model->getFillable();

        $fields = array_map(function ($column) {
            return Field::make($column)->label(ucwords(str_replace('_', ' ', $column)));
        }, $fillable);

        $export = new TemplateExport();

        return $export->download($fields, 'import-template.csv');
    }
}
