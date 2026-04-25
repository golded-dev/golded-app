# Contributing

Keep the change small enough to review without a map and provisions.

## Before You Start

- Use the existing Laravel, Livewire, Flux, Pest, Pint, PHPStan, and Rector patterns.
- Do not add dependencies without a clear reason.
- Do not commit private message archives, local agent config, editor state, or credentials.
- Do not claim support for formats or databases that are not tested here.

## Making Changes

- Add or update tests when behavior changes.
- Prefer feature tests for user-visible behavior.
- Keep migrations portable across SQLite and MySQL.
- Keep public copy concrete. If it sounds like a pitch deck, cut it.

## Checks

Run the smallest useful test while working, then run the full gate before a PR:

```bash
composer validate --strict
npm run build
composer lint:check
composer test:types
composer test:refactor
php artisan test --compact
```

If you touch PHP, format it:

```bash
vendor/bin/pint --dirty --format agent
```
