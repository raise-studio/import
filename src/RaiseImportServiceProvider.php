<?php

namespace RaiseStudio\Import;

use Filament\Panel;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use RaiseStudio\License\FeatureGate;
use RaiseStudio\License\LicenseClient;
use RaiseStudio\License\Messages as LicenseMessages;
use RaiseStudio\Import\License\LaravelLogger;
use RaiseStudio\License\Adapters\Laravel\LaravelCache;
use RaiseStudio\License\Adapters\Laravel\LaravelHttp;

class RaiseImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/raise-import.php',
            'raise-import'
        );

        $this->registerLicenseServices();
    }

    public function boot(): void
    {
        $this->loadViews();
        $this->loadTranslations();
        $this->publishConfig();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \RaiseStudio\Import\Console\Commands\IntegrityRehashCommand::class,
            ]);
        }

        // Set SDK locale from config
        $this->bootLicenseLocale();

        // Auto-activate from .env if a license key is configured but not yet stored
        $this->autoActivateFromEnv();

        // Free routes (e.g. template download) must always be registered,
        // independent of the Pro license status.
        $this->loadFreeRoutes();

        if (License::isPro()) {
            $this->bootProFeatures();
        }
    }

    protected function loadFreeRoutes(): void
    {
        $this->loadRoutesFrom(
            __DIR__ . '/routes/web.php'
        );
    }

    /**
     * Register the license-client SDK services into the container.
     */
    protected function registerLicenseServices(): void
    {
        $this->app->singleton(LicenseClient::class, function () {
            $license = config('raise-import.license', []);

            return new LicenseClient(
                $license['product_code'] ?? 'raise-import',
                $license['public_key_base64'] ?? '',
                new LaravelCache(),
                new LaravelHttp(),
                $license['api_base_url'] ?? 'https://admin.raisestudio.dev/api/v1',
                $this->resolveSiteUrl(),
                new LaravelLogger(),
                $license['public_key_fingerprint'] ?? '',
            );
        });

        $this->app->singleton(FeatureGate::class, function ($app) {
            $gate = new FeatureGate($app->make(LicenseClient::class));
            $gate->setFreeFeatures(config('raise-import.license.free_features', []));
            $gate->setAllProFeatures(config('raise-import.license.all_pro_features', []));
            // Surface SDK diagnostics (signature / domain / expiry / activation)
            // in the application log instead of the default silent NullLogger.
            $gate->setLogger(new LaravelLogger());

            return $gate;
        });
    }

    /**
     * Resolve the site URL actually used to reach this application.
     *
     * Prefers the real HTTP request host over config('app.url') so the
     * license domain binding is checked against the domain the user truly
     * accesses — not a config value that could be changed to spoof it.
     */
    protected function resolveSiteUrl(): string
    {
        if (function_exists('request')) {
            try {
                $request = request();
                if ($request !== null && method_exists($request, 'getHost') && $request->getHost() !== '') {
                    return rtrim($request->getScheme() . '://' . $request->getHost(), '/');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (! empty($_SERVER['HTTP_HOST'])) {
            $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return rtrim($scheme . '://' . $_SERVER['HTTP_HOST'], '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Set the SDK locale to match the application locale.
     */
    protected function bootLicenseLocale(): void
    {
        $locale = config('raise-import.license.locale', 'zh');
        LicenseMessages::setLocale($locale);
    }

    /**
     * Auto-activate from .env configuration.
     *
     * If the user has set RAISE_IMPORT_LICENSE_KEY in their .env file,
     * this method automatically activates it on the first boot.
     * No Filament UI needed — just configure and go.
     */
    protected function autoActivateFromEnv(): void
    {
        // Skip if the container doesn't have the LicenseClient yet
        if (! $this->app->bound(LicenseClient::class)) {
            return;
        }

        $licenseKey = config('raise-import.license.key', '');
        if (empty($licenseKey)) {
            return;
        }

        // Already activated — skip
        $client = $this->app->make(LicenseClient::class);
        if ($client->getStoredLicenseKey() !== null) {
            return;
        }

        // Attempt auto-activation
        $siteUrl = $this->resolveSiteUrl();
        try {
            $result = $client->activate(
                $licenseKey,
                config('raise-import.license.email', '')
            );

            if ($result['success']) {
                $features = [];
                try {
                    $features = $client->getFeatures();
                } catch (\Throwable $e) {
                    // ignore — not required for the log line
                }
                logger()->info('[raise-import] License auto-activated OK (site=' . $siteUrl
                    . ', features=' . implode(',', $features) . ')');
            } else {
                $reason = $result['message'] ?? 'unknown error';
                $code = isset($result['code']) ? ' [code=' . $result['code'] . ']' : '';
                logger()->warning('[raise-import] License auto-activation FAILED: ' . $reason . $code
                    . ' (site=' . $siteUrl . ')');
            }
        } catch (\Throwable $e) {
            logger()->warning('[raise-import] License auto-activation exception: ' . $e->getMessage()
                . ' (site=' . $siteUrl . ')');
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
