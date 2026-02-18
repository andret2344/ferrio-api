# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Ferrio API is a Symfony 7.3 / PHP 8.5 application that serves holiday data (fixed and floating) across multiple languages and countries. It exposes a versioned JSON REST API (v1, v2) and includes a Twig-based admin UI (`/manage`) protected by HTTP Basic Auth.

## Commands

```bash
# Install dependencies
composer install
yarn install

# Run all tests (uses SQLite in-memory, no DB setup needed)
vendor/bin/phpunit

# Run a single test class
vendor/bin/phpunit tests/Controller/v2/CountryControllerV2Test.php

# Run a single test method
vendor/bin/phpunit --filter testGet tests/Controller/v2/CountryControllerV2Test.php

# Build frontend assets
yarn build        # production
yarn dev          # development
yarn watch        # development with watch

# Doctrine migrations
php bin/console doctrine:migrations:migrate

# Clear cache
php bin/console cache:clear
```

## Architecture

### Domain Model

Two holiday types with parallel structures:
- **Fixed holidays** — tied to a specific month/day (e.g., Christmas). Entities: `FixedHoliday`, `FixedHolidayMetadata`, `FixedHolidaySuggestion`, `FixedHolidayError`
- **Floating holidays** — date varies per year, computed by a `Script` entity. Entities: `FloatingHoliday`, `FloatingHolidayMetadata`, `FloatingHolidaySuggestion`, `FloatingHolidayError`

Holidays are keyed by a composite ID of `Language` + `Metadata`. Each holiday entity implements `JsonSerializable` for API output.

### API Versioning

Controllers are organized in `src/Controller/v1/` and `src/Controller/v2/` with route prefixes `/v1/` and `/v2/`. Routes use PHP 8 attributes (`#[Route]`) with inline regex constraints (e.g., `{language<^\S{2}$>}`).

### User Reports (Suggestions & Errors)

`UserControllerV2` dispatches to handler classes via `ReportHandlerInterface`. Four handlers cover the matrix of {suggestion, error} × {fixed, floating}. Handlers are wired explicitly in `config/services.yaml`.

### Admin UI

`ManageController` + `WebController` serve Twig templates under `/manage` for managing holiday data (create, translate, check). Protected by `ROLE_USER` via HTTP Basic Auth.

### Testing

- Tests extend `WebTestCase` and use `Liip\TestFixturesBundle` with `DAMA\DoctrineTestBundle` for transactional test isolation.
- Fixtures live in `tests/Fixture/` and are loaded per-test in `setUp()`.
- `TestUtilTrait` provides `request()` and `getFixture()` helpers used across all controller tests.
- Test environment uses SQLite in-memory (configured in `.env.test`).

### Key Conventions

- PHP 8.5 property hooks and `private(set)` visibility are used in entities.
- Doctrine mapping uses PHP 8 attributes (not XML/YAML).
- Frontend uses Webpack Encore with TypeScript and Bootstrap 5 / MDB UI Kit.
- CI runs on GitHub Actions (`.github/workflows/ci.yml`), executing PHPUnit on PHP 8.5.
