<?php

namespace RaiseStudio\Import;

use Filament\Panel;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class RaiseImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/raise-import.php',
            'raise-import'
        );
    }

    public function boot(): void
    {
        $this->loadViews();
        $this->loadTranslations();
        $this->publishConfig();

        if (License::isPro()) {
            $this->bootProFeatures();
        }
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'raise-import'
        );
    }

    protected function loadTranslations(): void
    {
        $this->loadTranslationsFrom(
            __DIR__ . '/../resources/lang',
            'raise-import'
        );
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/raise-import.php' => config_path('raise-import.php'),
        ], 'raise-import-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/raise-import'),
        ], 'raise-import-views');

        $this->publishes([
            __DIR__ . '/../resources/lang' => lang_path('vendor/raise-import'),
        ], 'raise-import-translations');

        FilamentAsset::register([], package: 'raise-studio/raise-import');
    }

    /**
     * Boot Pro features: migrations, routes, and Filament resources.
     */
    protected function bootProFeatures(): void
    {
        $this->loadProMigrations();
        $this->loadProRoutes();
        $this->registerProResources();
    }

    protected function loadProMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(
                __DIR__ . '/Pro/database/migrations'
            );
        }
    }

    protected function loadProRoutes(): void
    {
        $this->loadRoutesFrom(
            __DIR__ . '/Pro/routes/web.php'
        );
    }

    protected function registerProResources(): void
    {
        // ImportLogResource is registered per-panel by the application.
        // See AdminPanelProvider for the conditional registration.
    }
}
