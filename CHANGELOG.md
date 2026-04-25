# Changelog

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
