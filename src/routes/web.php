<?php

use Illuminate\Support\Facades\Route;
use RaiseStudio\Import\Http\Controllers\TemplateController;

/*
 * Free, always-available routes — NOT gated behind a Pro license.
 * Currently only the CSV template download, which the free import action
 * links to. Keeping this in a separate file from Pro/routes/web.php ensures
 * the route exists even when License::isPro() is false.
 */
Route::middleware(['web', 'auth'])
    ->prefix('raise-import')
    ->name('raise-import.')
    ->group(function () {
        Route::get('/template/{modelClass}', [TemplateController::class, 'template'])
            ->name('template');
    });
