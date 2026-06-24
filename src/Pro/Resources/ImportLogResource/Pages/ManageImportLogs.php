<?php

namespace RaiseStudio\Import\Pro\Resources\ImportLogResource\Pages;

use Filament\Resources\Pages\ManageRecords;
use RaiseStudio\Import\License;
use RaiseStudio\Import\Pro\Resources\ImportLogResource;
use RaiseStudio\Import\Pro\Resources\ImportLogResource\Widgets\ImportLogStats;

class ManageImportLogs extends ManageRecords
{
    protected static string $resource = ImportLogResource::class;

    public function mount(): void
    {
        if (!License::isPro()) {
            abort(403, 'This feature requires Raise Import Pro license.');
        }

        parent::mount();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ImportLogStats::class,
        ];
    }
}
