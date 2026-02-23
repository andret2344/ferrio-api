# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Ferrio API is a Symfony 7.3 / PHP 8.5 application that serves holiday data (fixed and floating) across multiple
languages and countries. It exposes a versioned JSON REST API (v1, v2, v3) and includes a Twig-based admin UI (
`/manage`) protected by HTTP Basic Auth.

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

- **Fixed holidays** — tied to a specific month/day (e.g., Christmas). Entities: `FixedHoliday`, `FixedHolidayMetadata`,
  `FixedHolidaySuggestion`, `FixedHolidayError`
- **Floating holidays** — date varies per year. In v1/v2, computed by a `Script` entity using `args` (JSON array for JS
  scripts). In v3, computed by `AlgorithmResolver` using `algorithmArgs` (JSON object with named keys). Entities:
  `FloatingHoliday`, `FloatingHolidayMetadata`, `FloatingHolidaySuggestion`, `FloatingHolidayError`

Holidays are keyed by a composite ID of `Language` + `Metadata`. Each holiday entity implements `JsonSerializable` for
API output.

`FloatingHolidayMetadata` has two args columns:

- `args` — JSON array for v1/v2 script-based calculation (e.g., `[2026, 4]`)
- `algorithmArgs` — JSON object for v3 algorithm-based calculation (e.g., `{"2026": "15.4"}`)

### API Versioning

Controllers are organized in `src/Controller/v1/`, `src/Controller/v2/`, and `src/Controller/v3/` with route prefixes
`/v1/`, `/v2/`, and `/v3/`. v1/v2 routes use path parameters with inline regex constraints (e.g.,
`{language<^\S{2}$>}`). v3 uses query parameters exclusively.

### v3 API

Single endpoint: `GET /v3/holidays` with query parameters:

- `lang` (required, case-insensitive) — language code
- `year` (optional, defaults to current year)
- `day` (optional) — filter by day of month
- `month` (optional) — filter by month
- `country` (optional, case-insensitive) — filter by country ISO code
- `grouping` (optional, default `false`) — when `true`, groups holidays by day in v2-compatible `HolidayDay` format

The v3 merges fixed and floating holidays into a unified flat list sorted by date. Each item has a prefixed `id` (
`fixed-*` or `floating-*`).

### Algorithm Resolver (v3)

Floating holiday dates in v3 are computed by polymorphic resolver classes in `src/Service/Algorithm/`, each implementing
`AlgorithmResolverInterface`. The `Algorithm` enum maps each case to its resolver class via `resolverClass()`.
`AlgorithmResolver` is a thin factory using Symfony's `#[AutowireLocator]` to inject all resolvers via a
`ServiceLocator`.

Available algorithms with v1/v2 `args` → v3 `algorithmArgs` mapping (dayOfWeek uses ISO 1-7, Mon-Sun):

- `nth_day_of_week_in_month` — nth occurrence of a weekday in a month. v1/v2: `[month, dayOfWeek, nth]` → v3: `{"nth": 4, "dayOfWeek": 4, "month": 11}` (4th Thursday of November = Thanksgiving)
- `last_nth_day_of_week_in_month` — nth-to-last occurrence of a weekday in a month. Same keys as above: `{"nth": 1, "dayOfWeek": 1, "month": 5}` (last Monday of May = Memorial Day)
- `first_day_of_week_after_date` — first weekday on or after a date. v1/v2: hardcoded in script → v3: `{"dayOfWeek": 6, "month": 5, "day": 19}` (first Saturday on or after May 19). Optional `"inclusive": false` to exclude the start date.
- `last_day_of_week_before_date` — last weekday on or before a date. v1/v2: hardcoded in script → v3: `{"dayOfWeek": 5, "month": 3, "day": 20}` (last Friday on or before March 20). Optional `"inclusive": false` to exclude the start date.
- `nth_day_then_next_day_of_week` — finds nth weekday, then the next occurrence of another weekday after it. v1/v2: `[month, dayOfWeek, nth, after]` → v3: `{"nth": 1, "dayOfWeek": 1, "month": 7, "afterDayOfWeek": 2}` (Tuesday after the 1st Monday of July)
- `leap_year_date` — returns different dates for leap/non-leap years. `{"leapDay": 29, "leapMonth": 2, "nonLeapDay": 1, "nonLeapMonth": 3}` (Feb 29 in leap years, Mar 1 otherwise)
- `hardcoded_dates` — year-to-date lookup, for holidays with no algorithmic pattern. `{"2024": "12.9", "2025": "20.9", "2026": "19.9"}` (year keys map to `day.month` strings). Returns null for missing years.

`algorithmArgs` is stored as a JSON column. Common pitfalls: JSON keys must always be quoted strings (e.g., `"2026"` not
`2026`) and trailing commas are not allowed.

### User Reports (Suggestions & Errors)

`UserControllerV2` dispatches to handler classes via `ReportHandlerInterface`. Four handlers cover the matrix of
{suggestion, error} × {fixed, floating}. Handlers are wired explicitly in `config/services.yaml`.

### Admin UI

`ManageController` + `WebController` serve Twig templates under `/manage` for managing holiday data (create, translate,
check). Protected by `ROLE_USER` via HTTP Basic Auth.

### Testing

- Tests extend `WebTestCase` and use `Liip\TestFixturesBundle` with `DAMA\DoctrineTestBundle` for transactional test
  isolation.
- Fixtures live in `tests/Fixture/` and are loaded per-test in `setUp()`.
- `TestUtilTrait` provides `request()` and `getFixture()` helpers used across all controller tests.
- Test environment uses SQLite in-memory (configured in `.env.test`).

### Key Conventions

- PHP 8.5 property hooks and `private(set)` visibility are used in entities.
- Doctrine mapping uses PHP 8 attributes (not XML/YAML).
- Frontend uses Webpack Encore with TypeScript and Bootstrap 5 / MDB UI Kit.
- CI runs on GitHub Actions (`.github/workflows/ci.yml`), executing PHPUnit on PHP 8.5.
