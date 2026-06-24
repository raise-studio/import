# Changelog

## v1.0.0 - 2026-06-24

### Added
- Initial release of Raise Import
- **Core**: CSV / XLSX / ODS file import via OpenSpout
- **Fields**: Chainable Field API (`label`, `rules`, `default`, `options`, `required`)
- **Auto-detection**: Automatic field detection from Eloquent models
- **Auto-mapping**: Fuzzy column matching (similar_text ≥70%) with Chinese/English aliases
- **Wizard UI**: 3-step import flow (Upload → Mapping → Preview → Results)
- **Validation**: Row-level Laravel Validator with field rules
- **Duplicate handling**: Skip / Update / Error strategies
- **CSV intelligence**: Automatic delimiter detection (comma, semicolon, tab, pipe)
- **Template download**: CSV with column headers and sample data
- **Customization**: `mutateBeforeCreate` callback, `fieldsUsing` resolver, `uniqueBy`, `chunkSize`
- **Upload validation**: Extension whitelist, file size limit, empty file check
- **Dark mode**: Full dark theme support across all views
- **Localization**: English and Simplified Chinese
- **Filament plugin**: `RaiseImportPlugin::make()` one-line registration
- **Pipeline system (Pro)**: 8 built-in pipes (TrimStrings, Lowercase, Bcrypt, DateFormat,
  DefaultValue, MergeColumns, SplitColumn, Closure) + field-level pipes
- **Advanced mapping (Pro)**: Column merge, split, ignore, reorder
- **Import history (Pro)**: Full CRUD resource with filters, sorting, bulk actions
- **Import stats (Pro)**: Statistics widget (total/success/failed/skipped)
- **REST API (Pro)**: 5 endpoints (upload, preview, import, template, errors)
- **Queue support (Pro)**: Auto-queue large imports (ProcessImportJob, 3 retries)
- **Re-import (Pro)**: Retry failed or partial imports
- **Error report (Pro)**: Downloadable CSV error report
- **Multi-column mapping (Pro)**: One CSV column → multiple fields
- **Ignore columns (Pro)**: Skip unwanted columns during mapping
