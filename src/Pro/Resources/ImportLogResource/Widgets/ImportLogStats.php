<?php

namespace RaiseStudio\Import\Pro\Resources\ImportLogResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use RaiseStudio\Import\Filament\Version;
use RaiseStudio\Import\License;
use RaiseStudio\Import\Pro\Models\ImportLog;

class ImportLogStats extends BaseWidget
{
    protected static bool $isLazy = false;
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        if (!License::isPro()) {
            abort(403, 'This feature requires Raise Import Pro license.');
        }

        $totalImports = ImportLog::count();
        $totalImported = ImportLog::sum('imported_count');
        $totalSkipped = ImportLog::sum('skipped_count');
        $totalFailed = ImportLog::sum('failed_count');
        $failedImports = ImportLog::where('status', 'failed')->count();
        $failureRate = $totalImports > 0
            ? round($failedImports / $totalImports * 100, 1)
            : 0;

        return [
            Stat::make(__('raise-import::messages.stats.total_imports'), $totalImports)
                ->icon('heroicon-o-arrow-up-tray'),

            Stat::make(__('raise-import::messages.stats.records_imported'), $totalImported)
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make(__('raise-import::messages.stats.failed_imports'), $failedImports)
                ->icon('heroicon-o-exclamation-circle')
                ->color($failedImports > 0 ? 'danger' : 'success')
                ->description("{$failureRate}% " . __('raise-import::messages.stats.failure_rate')),

            Stat::make(__('raise-import::messages.stats.skipped_records'), $totalSkipped)
                ->icon('heroicon-o-forward')
                ->color('warning'),
        ];
    }
}
