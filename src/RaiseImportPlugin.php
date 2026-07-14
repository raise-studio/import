<?php

namespace RaiseStudio\Import;

use Filament\Contracts\Plugin;
use Filament\Panel;
use RaiseStudio\Import\Pro\Resources\ImportLogResource;

class RaiseImportPlugin implements Plugin
{
    protected bool $withImportLog = true;

    protected ?string $navigationGroup = null;

    protected ?string $navigationLabel = null;

    protected ?string $navigationIcon = null;

    final public function __construct()
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'raise-import';
    }

    /**
     * Disable the import log resource registration.
     */
    public function withoutImportLog(): static
    {
        $this->withImportLog = false;

        return $this;
    }

    /**
     * Set the navigation group for the import log menu.
     * Default: 'Tools'
     */
    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    /**
     * Set a custom navigation label for the import log menu.
     * Default: 'Import Logs'
     */
    public function navigationLabel(string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    /**
     * Set a custom navigation icon for the import log menu.
     * Default: 'heroicon-o-clock'
     */
    public function navigationIcon(string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function register(Panel $panel): void
    {
        if (! License::isPro()) {
            return;
        }

        if ($this->withImportLog) {
            ImportLogResource::applyNavigationConfig(
                group: $this->navigationGroup,
                label: $this->navigationLabel,
                icon: $this->navigationIcon,
            );

            $panel->resources([
                ImportLogResource::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
