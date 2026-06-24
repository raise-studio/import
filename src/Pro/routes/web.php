<?php

use Illuminate\Support\Facades\Route;
use RaiseStudio\Import\Pro\Http\Controllers\ImportController;

Route::middleware(['web', 'auth'])->prefix('raise-import')->name('raise-import.')->group(function () {
    // Upload and preview endpoints
    Route::post('/upload', [ImportController::class, 'upload'])->name('upload');
    Route::post('/preview', [ImportController::class, 'preview'])->name('preview');
    Route::post('/import', [ImportController::class, 'import'])->name('import');

    // Template download
    Route::get('/template/{modelClass}', [ImportController::class, 'template'])->name('template');

    // Error report download
    Route::get('/errors/{importLog}/download', [ImportController::class, 'downloadErrors'])->name('errors.download');
});
