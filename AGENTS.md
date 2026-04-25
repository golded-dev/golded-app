# GoldED App Agent Notes

GoldED 7 is a Laravel app for reading imported FTN message bases in the browser and terminal.

## Boundaries

- This repo is the public app, not a private archive dump.
- Do not commit real message bases, local agent folders, editor config, credentials, or private machine paths.
- Sending mail over FidoNet is out of scope for `7.0.0`.
- Supported databases are SQLite and MySQL. Do not claim PostgreSQL support unless it is tested.

## Stack

- PHP 8.4
- Laravel 13
- Livewire 4
- Flux UI 2
- Pest 4
- Tailwind CSS 4
- Pint, PHPStan, Rector

Use Laravel Boost docs before Laravel ecosystem code changes.

## Code Rules

- Follow the existing structure.
- Keep changes tight.
- Prefer deleting over inventing framework furniture.
- Use descriptive names.
- Keep migrations portable across SQLite and MySQL.
- Use named routes and Laravel conventions.
- Add or update tests when behavior changes.

## Public Writing

Use Odinn's voice for docs and comments: clear point first, concrete wording, no pitch-deck sludge.

Avoid vague product words like "platform", "solution", "leverage", "seamless", and "aggregated insights".

## Commands

Useful checks:

```bash
composer validate --strict
npm run build
composer lint:check
composer test:types
composer test:refactor
php artisan test --compact
```

If PHP changed, run:

```bash
vendor/bin/pint --dirty --format agent
```

## Import Surface

Public commands:

```bash
php artisan golded:config [path]
php artisan golded:import {msg|jam|squish|hudson} <path> [--fresh]
php artisan golded:import-config [--root=...]
php artisan golded:run
```

The checked-in demo config points at `samples/msg`.
