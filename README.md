# Raise Import

> The simplest and most complete CSV/Excel import plugin for Filament.

[![Latest Version](https://img.shields.io/packagist/v/raise-studio/import.svg)](https://packagist.org/packages/raise-studio/import)
[![Total Downloads](https://img.shields.io/packagist/dt/raise-studio/import.svg)](https://packagist.org/packages/raise-studio/import)
[![License](https://img.shields.io/packagist/l/raise-studio/import.svg)](https://github.com/RaiseStudio/import/blob/main/LICENSE)

## Features

### Community (Free)

| | Feature | Description |
|---|---------|-------------|
| ✅ | CSV / XLSX / ODS import | OpenSpout-powered, memory-efficient row-by-row reading |
| ✅ | Auto field detection | Automatically reads fields from your Eloquent model |
| ✅ | Field configuration API | `Field::make()->label()->rules()->default()->options()` |
| ✅ | Automatic column mapping | Fuzzy matching (similar_text ≥70%) with Chinese/English aliases |
| ✅ | Data preview before import | Table preview with row validation status |
| ✅ | Row-level validation | Powered by Laravel Validator |
| ✅ | Duplicate handling | 3 strategies: **Skip** / **Update** / **Error** |
| ✅ | 3-step wizard UI | Upload → Mapping → Preview workflow |
| ✅ | CSV delimiter auto-detect | Comma, semicolon, tab, pipe |
| ✅ | Template download | CSV template with column headers + sample data |
| ✅ | Import result report | Success/failure/skip counts in notification |
| ✅ | mutateBeforeCreate hook | Modify data before database insert |
| ✅ | Upload file validation | Extension whitelist, 50MB limit, empty file check |
| ✅ | Dark mode support | All views include `dark:` CSS classes |
| ✅ | Multi-language | English (en) and Simplified Chinese (zh_CN) |
| ✅ | Filament Plugin | `RaiseImportPlugin::make()` one-line registration |

### Pro (Paid)

| | Feature | Description |
|---|---------|-------------|
| 🔷 | **Pipeline system** | 8 built-in pipes + custom Closure pipes |
| 🔷 | TrimStringsPipe | Auto-trim whitespace from all string fields |
| 🔷 | LowercasePipe | Convert email/username to lowercase |
| 🔷 | BcryptPipe | Hash password fields |
| 🔷 | DateFormatPipe | Normalize date formats |
| 🔷 | DefaultValuePipe | Fill empty fields with defaults |
| 🔷 | MergeColumnsPipe | Merge multiple CSV columns into one field |
| 🔷 | SplitColumnPipe | Split one CSV column into multiple fields |
| 🔷 | ClosurePipe | Adapt any Closure as a pipe |
| 🔷 | **Advanced column mapping** | Merge, split, ignore, and reorder columns |
| 🔷 | **Import history (logs)** | Full CRUD resource with stats |
| 🔷 | **5 REST API endpoints** | upload / preview / import / template / errors |
| 🔷 | **Queue support** | Auto-queues large imports via ShouldQueue |
| 🔷 | **Import stats widget** | 4 stat cards: total, imported, failed, skipped |
| 🔷 | **Re-import** | Retry failed/partial imports |
| 🔷 | **Error report download** | CSV file with row-level error details |
| 🔷 | **Multi-column mapping** | Map one CSV column to multiple fields |
| 🔷 | **Ignore column checkbox** | Skip unwanted columns during mapping

## Installation

```bash
composer require raise-studio/import
```

### Import History (Import Log)

To see the **Import Log** menu in your Filament sidebar, register the plugin in your `PanelProvider`:

```php
use RaiseStudio\Import\RaiseImportPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(RaiseImportPlugin::make())
        ;
}
```

You can customize the menu's position, label, and icon:

```php
->plugin(
    RaiseImportPlugin::make()
        ->navigationGroup('Admin')                    // 放到「Admin」分组
        ->navigationLabel(__('Import Logs'))          // 自定义菜单名
        ->navigationIcon('heroicon-o-document-arrow-down')  // 自定义图标
)
```

Alternatively, register the resource directly for full control:

```php
use RaiseStudio\Import\Pro\Resources\ImportLogResource;

->resources([
    ImportLogResource::class,
])
```

> **Note**: Import Log is a Pro feature. In local development environments it's automatically enabled. In production, you'll need a license key (see Configuration below).

## Usage

### Simplest way — one line:

```php
use RaiseStudio\Import\Actions\ImportAction;

public static function table(Table $table): Table
{
    return $table
        ->headerActions([
            ImportAction::make()
                ->model(User::class),
        ]);
}
```

### Full configuration:

```php
ImportAction::make('import')
    ->model(User::class)
    ->label('Import Users')
    ->icon('arrow-up-tray')
    ->fields([
        Field::make('name')->label('Name')->required(),
        Field::make('email')->label('Email')->rules('email|unique:users'),
        Field::make('phone')->label('Phone')->rules('numeric'),
    ])
    ->uniqueBy('email')
    ->onDuplicate('skip')
    ->chunkSize(500)
    ->rules([
        'name' => 'required|string|max:100',
        'email' => 'email|max:255',
    ])
    ->mutateBeforeCreate(function (array $row) {
        $row['password'] = bcrypt('default123');
        return $row;
    });
```

## Requirements

- PHP 8.1+
- Filament 4.x or 5.x
- Livewire 3.x (Filament 4) or 4.x (Filament 5)
- OpenSpout 4.x

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=raise-import-config
```

## Translations

Publish translations:

```bash
php artisan vendor:publish --tag=raise-import-translations
```

## Testing

```bash
vendor/bin/phpunit
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for version history.

## Pro License

To unlock Pro features, set your license key in `.env`:

```bash
RAISE_IMPORT_LICENSE_KEY=your-license-key-here
```

Get a license at [https://raise-studio.com](https://raise-studio.com)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
