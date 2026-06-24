<?php

namespace RaiseStudio\Import\Filament;

/**
 * Filament version detection helper.
 *
 * Provides a unified API for accessing components whose namespaces
 * changed between Filament 4 and Filament 5.
 *
 * - Filament 4: layout components under Filament\Forms\Components
 * - Filament 5: layout components under Filament\Schemas\Components
 */
class Version
{
    protected static ?int $cachedMajor = null;

    /**
     * Get the installed Filament major version (4 or 5).
     */
    public static function major(): int
    {
        if (static::$cachedMajor !== null) {
            return static::$cachedMajor;
        }

        // Schemas namespace is unique to v5+
        if (class_exists(\Filament\Schemas\Components\Wizard::class)) {
            return static::$cachedMajor = 5;
        }

        // Forms namespace indicates Filament 4
        return static::$cachedMajor = 4;
    }

    /**
     * Whether the installed Filament is v5 or later.
     */
    public static function isV5(): bool
    {
        return static::major() >= 5;
    }

    /**
     * Get the fully-qualified class name for the Wizard component.
     */
    public static function wizardClass(): string
    {
        return static::isV5()
            ? \Filament\Schemas\Components\Wizard::class
            : \Filament\Forms\Components\Wizard::class;
    }

    /**
     * Get the fully-qualified class name for the Wizard Step component.
     */
    public static function wizardStepClass(): string
    {
        return static::isV5()
            ? \Filament\Schemas\Components\Wizard\Step::class
            : \Filament\Forms\Components\Wizard\Step::class;
    }

    /**
     * Get the fully-qualified class name for the Grid component.
     */
    public static function gridClass(): string
    {
        return static::isV5()
            ? \Filament\Schemas\Components\Grid::class
            : \Filament\Forms\Components\Grid::class;
    }

    /**
     * Get the fully-qualified class name for the Section component.
     */
    public static function sectionClass(): string
    {
        return static::isV5()
            ? \Filament\Schemas\Components\Section::class
            : \Filament\Forms\Components\Section::class;
    }

    /**
     * Clear the cached major version (useful in tests).
     */
    public static function reset(): void
    {
        static::$cachedMajor = null;
    }
}
