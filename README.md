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

- PHP 8.2+
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

To unlock Pro features, set your license key (and the shared server secret) in `.env`:

```bash
RAISE_IMPORT_LICENSE_KEY=your-license-key-here
RAISE_IMPORT_LICENSE_SECRET=shared-hmac-secret
RAISE_IMPORT_LICENSE_PRODUCT=raise-import
```

- `RAISE_IMPORT_LICENSE_KEY` — your Pro license key.
- `RAISE_IMPORT_LICENSE_SECRET` — HMAC secret shared with `raise-license-server`
  (must equal the server's `LICENSE_SIGNATURE_KEY`). Without it, the plugin
  rejects **any** positive verification response, so a forged/relayed license
  endpoint cannot fake a valid license.
- `RAISE_IMPORT_LICENSE_PRODUCT` — product slug sent to the server during
  verification. Must match a Product slug on `raise-license-server`.

Get a license at [https://raise-studio.com](https://raise-studio.com)

### Verification request & response contract

The plugin `POST`s to `RAISE_IMPORT_LICENSE_VERIFY_URL` with:

```json
{
  "license_key": "your-license-key-here",
  "site_url": "https://example.com",
  "product": "raise-import"
}
```

The endpoint must return JSON signed with HMAC-SHA256 over
`valid|domain|expires_at|edition` (where `valid` is the literal `true`/`false`):

```json
{
  "valid": true,
  "domain": "example.com",
  "expires_at": "2026-12-31",
  "edition": "pro",
  "signature": "HMAC_SHA256('true|example.com|2026-12-31|pro', SECRET)"
}
```

The plugin verifies the signature, enforces that `edition === 'pro'`, and locks
the key to the returned `domain` (supports `*.example.com` wildcard for
subdomains).

**Local exemption is intentionally narrow.** Only loopback hosts
(`localhost`, `127.0.0.1`, `[::1]`, `0.0.0.0`) get Pro features without a key.
`*.test` / `*.local` TLDs and private IP ranges (`10.x`, `172.16–31.x`,
`192.168.x`) are **no longer** exempt — they must present a valid license, just
like production. This tightens the free-Pro surface (see strategy note
2026-07-07: 收敛豁免).

### Distributed gate (defense in depth)

Each Pro feature execution point (the `ProImportAction` wizard setup/run, the
queued `ProcessImportJob`, and the `ImportController`) calls `License::gatePro()`
**directly** instead of relying solely on the cached `License::isPro()` result.
`gatePro()` never reads the static `isPro()` cache and re-evaluates the license
on its own (key validity + signature + domain lock + integrity self-check).
This means patching `isPro()` to always return `true` is insufficient to unlock
the actual Pro features — every critical file re-checks independently.

### Integrity self-check (tamper deterrent)

In addition to online verification, the plugin verifies that its own Pro
gatekeeper files (`License.php`, `ProImportAction.php`,
`RaiseImportServiceProvider.php`) have not been patched to force Pro mode. Each
file's SHA-256 is compared against an expected value shipped in the config. If a
file's hash does not match, Pro features are refused and the installation
**silently falls back to Community mode** (with a `warning` log entry) — it never
crashes.

This is a deterrent, not a hard lock: PHP source always lives on the client's
machine, so a determined attacker can still bypass it. It raises the cost of
patching the license gate.

Configure it in `config/raise-import.php` (or `.env`):

```php
'integrity_disabled' => env('RAISE_IMPORT_INTEGRITY_DISABLED', false),
'integrity_version'  => '1.0.0',
'integrity_hashes'   => [
    'src/License.php' => '...',
    'src/Pro/Actions/ProImportAction.php' => '...',
    'src/RaiseImportServiceProvider.php' => '...',
],
```

- `RAISE_IMPORT_INTEGRITY_DISABLED=true` — disable the check entirely
  (for legitimate debugging or when you intentionally patch the source).
- `integrity_version` must match the installed package version. If it does
  not (e.g. you upgraded without regenerating hashes), the check is **skipped**
  rather than forcing legitimate users into Community mode.
- Empty `integrity_hashes` → check is **skipped** (graceful default). This is
  the shipped state, so the feature does nothing until you opt in.

Regenerate the hashes for every release with the included command:

```bash
php artisan raise-import:integrity:rehash
```

It prints the version and the exact `integrity_version` / `integrity_hashes`
block to paste into `config/raise-import.php`.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
