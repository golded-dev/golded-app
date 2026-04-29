# Changelog

## 7.1.0 - 2026-04-29

FTN database extraction and import hardening.

### Added

- Added `golded-dev/laravel-ftn-database` as the package-owned schema and model layer for FTN archive facts.
- Added app config that maps package relationships back to `App\Models\Area` and `App\Models\Message`.
- Added a shared import record mapper for source identity, source locator/offset, control-line JSON, provenance JSON, and scoped external IDs.
- Added coverage for package-backed model relationships, source identity dedupe, scoped external IDs, nullable external IDs, and importer provenance.

### Changed

- Moved archive-owned `areas` and `messages` columns into the FTN database package migration.
- Kept reader state in the app migration: message counts, unread counts, read markers, bookmarks, and thread keys.
- Updated app models to extend the package models while keeping app-owned factories, casts, and state.
- Switched FTN parser and database dependencies to stable Packagist versions.
- Updated Squish, JAM, MSG, and Hudson imports to write stable source identity and archive provenance.
- Scoped message uniqueness by `area_id`, `source_type`, and `source_uid`.
- Scoped external IDs by `area_id` and `external_id`.

### Fixed

- Normalized raw FTN control metadata before JSON storage so legacy non-UTF-8 bytes do not crash imports.
- Counted inserted rows for `.MSG` and Hudson imports so repeated imports report `0` and area counters match stored rows.
- Kept Squish, JAM, MSG, and Hudson re-imports idempotent under the package uniqueness rules.

### Removed

- Removed app-owned archive table creation migrations.
- Removed the old global `messages.external_id` unique migration.
- Removed local path repositories for released FTN parser and database packages.

### Still unsupported

- Ezycom, Goldbase, and PCBoard imports remain outside this release.

## 7.0.0 - 2026-04-25

Initial public release.

### Added

- Public README, license, contribution notes, security policy, and code of conduct.
- Browser and terminal screenshots for the area list, message list, and reader.
- Synthetic `.MSG` sample data under `samples/msg`.
- CI for SQLite and MySQL.

### Changed

- Project metadata now points at `golded-dev/golded-app`.
- GoldED config defaults now use safe demo data.
- Fresh installs now use one portable GoldED schema for SQLite and MySQL.
- Importer tests now use public synthetic fixtures instead of private archive paths.

### Removed

- Local agent and editor folders.
