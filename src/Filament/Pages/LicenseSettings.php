<?php

namespace RaiseStudio\Import\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use RaiseStudio\License\FeatureGate;
use RaiseStudio\License\LicenseClient;
use RaiseStudio\License\Messages as LicenseMessages;

class LicenseSettings extends Page
{
    protected static ?int $navigationSort = 1;

    protected string $view = 'raise-import::license-settings';

    /**
     * Navigation group - customizable by the user.
     */
    private static ?string $customNavigationGroup = null;

    /**
     * The license key input.
     */
    public string $licenseKey = '';

    /**
     * The activation result message.
     */
    public ?string $activationMessage = null;

    /**
     * Whether the activation was successful.
     */
    public bool $activationSuccess = false;

    /**
     * Whether the user is in the process of activating.
     */
    public bool $isActivating = false;

    /**
     * License info for display.
     */
    public array $licenseInfo = [];

    public function getTitle(): string
    {
        return __('raise-import::messages.license.page_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('raise-import::messages.license.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$customNavigationGroup ?? __('raise-import::messages.license.navigation_group');
    }

    /**
     * Allow users to customize the navigation group.
     */
    public static function navigationGroup(?string $group): void
    {
        static::$customNavigationGroup = $group;
    }

    public function mount(): void
    {
        // Only mount if SDK is available
        $this->refreshLicenseInfo();
    }

    /**
     * Activate the license with the entered key.
     */
    public function activate(): void
    {
        $this->validate([
            'licenseKey' => 'required|string|min:10',
        ]);

        $this->isActivating = true;
        $this->activationMessage = null;

        try {
            /** @var LicenseClient $client */
            $client = app(LicenseClient::class);

            $result = $client->activate($this->licenseKey);

            $this->activationSuccess = $result['success'];
            $this->activationMessage = $result['message'];

            if ($result['success']) {
                // Flush caches so isPro() reflects the new state
                \RaiseStudio\Import\License::flushCache();
                $this->dispatch('license-activated');
                Notification::make()
                    ->success()
                    ->title($result['message'])
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title($result['message'])
                    ->send();
            }
        } catch (\Throwable $e) {
            $this->activationSuccess = false;
            $this->activationMessage = $e->getMessage();
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();
        } finally {
            $this->isActivating = false;
            $this->refreshLicenseInfo();
        }
    }

    /**
     * Deactivate the license.
     */
    public function deactivate(): void
    {
        try {
            /** @var LicenseClient $client */
            $client = app(LicenseClient::class);
            $client->deactivate();

            \RaiseStudio\Import\License::flushCache();

            $this->licenseKey = '';
            $this->activationMessage = LicenseMessages::get('deactivation.success');
            $this->activationSuccess = false;
            $this->refreshLicenseInfo();

            Notification::make()
                ->success()
                ->title(LicenseMessages::get('deactivation.success'))
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();
        }
    }

    /**
     * Refresh the local license info from the SDK.
     */
    protected function refreshLicenseInfo(): void
    {
        try {
            if (!app()->bound(LicenseClient::class)) {
                $this->licenseInfo = [
                    'status' => 'unavailable',
                    'message' => LicenseMessages::get('status.sdk_unavailable'),
                ];
                return;
            }

            /** @var LicenseClient $client */
            $client = app(LicenseClient::class);
            $storedKey = $client->getStoredLicenseKey();

            /** @var FeatureGate $gate */
            $gate = app(FeatureGate::class);

            $this->licenseInfo = [
                'status' => $gate->isPro() ? 'pro' : 'community',
                'has_key' => $storedKey !== null,
                'message' => $gate->isPro()
                    ? LicenseMessages::get('status.pro_active')
                    : LicenseMessages::get('status.community'),
                'key' => $storedKey ? substr($storedKey, 0, 8) . '****' : null,
                'features' => $gate->getAvailableFeatures(),
            ];
        } catch (\Throwable $e) {
            $this->licenseInfo = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the available features grouped by type.
     */
    public function getFeatureGroups(): array
    {
        $free = config('raise-import.license.free_features', []);
        $pro = config('raise-import.license.all_pro_features', []);

        $isPro = $this->licenseInfo['status'] === 'pro' || \RaiseStudio\Import\License::isPro();

        return [
            'free' => [
                'label' => __('raise-import::messages.license.features_free'),
                'features' => $free,
                'available' => true,
            ],
            'pro' => [
                'label' => __('raise-import::messages.license.features_pro'),
                'features' => $pro,
                'available' => $isPro,
            ],
        ];
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColor(): string
    {
        return match ($this->licenseInfo['status'] ?? 'community') {
            'pro' => 'success',
            'unavailable' => 'warning',
            'error' => 'danger',
            default => 'gray',
        };
    }
}
