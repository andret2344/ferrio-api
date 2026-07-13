# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**Maintenance note:** If you notice something in this file that has become inaccurate (framework/PHP version bumps,
changed commands, renamed paths, new conventions, etc.), update it in the same change — even if the user did not
explicitly ask. Do not silently leave stale guidance.

## Project Overview

Ferrio API is a Symfony 7.4 / PHP 8.5 application that serves holiday data (fixed and floating) across multiple
languages and countries. It exposes a versioned JSON REST API (v2, v3) and includes a Twig-based admin UI (
`/admin`, with a 301 redirect from the legacy `/manage` prefix) protected by HTTP Basic Auth.

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
- **Floating holidays** — date varies per year. In v2, computed by a `Script` entity using `args` (JSON array for JS
  scripts). In v3, computed by `AlgorithmResolver` using `algorithmArgs` (JSON object with named keys). Entities:
  `FloatingHoliday`, `FloatingHolidayMetadata`, `FloatingHolidaySuggestion`, `FloatingHolidayError`

Holidays are keyed by a composite ID of `Language` + `Metadata`. Each holiday entity implements `JsonSerializable` for
API output.

`FloatingHolidayMetadata` has two args columns:

- `args` — JSON array for v2 script-based calculation (e.g., `[2026, 4]`)
- `algorithmArgs` — JSON object for v3 algorithm-based calculation (e.g., `{"2026": "15.4"}`)

### API Versioning

Controllers are organized in `src/Controller/v2/` and `src/Controller/v3/` with route prefixes `/v2/` and `/v3/`.
(v1 no longer exists — no client queries it.) v2 routes use path parameters with inline regex constraints (e.g.,
`{language<^\S{2}$>}`); v3 uses query parameters exclusively.

Version numbers are a snapshot of the *whole* API, not per-resource. The rule for a resource whose behaviour is
identical across a version range: mount ONE controller under a version-parameter prefix so a single definition serves
all those versions —
`#[Route('/{_version<v2|v3>}/report', name: 'report_')]`. Releasing a new version then costs no new code for
unchanged resources — you widen the `_version` regex. When a resource's behaviour actually changes in a new version,
split it into a separate versioned controller instead (freeze the old one's regex, add a new one). Back-compat holds
because old prefixes are never unmounted. `report` is the current example (served at `/v2/report` and `/v3/report`
by the same `ReportControllerV2`); v3 also has its own distinct report contract at `/v3/users/reports`
(`UserControllerV3`). `countries` and `missing` are deliberately v2-only. Security `access_control`
paths must match the same range — use `^/v\d+/report` (version-agnostic) for aliased resources, a pinned `^/v2/...`
for single-version ones.

Contract tests (`tests/Contract/`) unify the driver but freeze the data. The v2 report/suggestion matrix
({error, suggestion} × {fixed, floating}) is driven by a single `V2/ReportsContractTest` with a data-provider over
combos; v3 does the same in `V3/UserReportsContractTest`. Frozen request/schema fixtures under `data/**` stay
per-combo and append-only — unify test code, never the fixtures. For a resource aliased across versions (report),
the provider adds a **version axis**: the same frozen generation is replayed against every version path that serves
it (`/v2/report` and `/v3/report`), which catches an alias silently drifting the response shape. "Always latest" is
covered by the ordinary controller tests, never the frozen contract suite.

### API traffic metrics

Per-endpoint GET traffic is counted in the `api_hit` table (entity `ApiHit`): one row per
`(bucket_hour, path)` with a `hits` counter, bucketed by **UTC hour**, where `path` is the full
request path (e.g. `/v3/holidays`, `/v2/holiday/en/day/3/1`). `ApiHitListener`
(on `kernel.terminate`, so it adds no latency to the response) calls `ApiHitCounter::count($method, $path)`,
which records **only GET** requests to a versioned path (`/v\d+…`; non-versioned admin/asset paths are ignored)
and runs an atomic `INSERT ... ON DUPLICATE KEY UPDATE hits = hits + 1` via raw DBAL. Raw DBAL
(not the ORM) is deliberate: read-modify-write through Doctrine would race under concurrency.
Errors are caught and logged as warnings — analytics must never break a request. Cardinality is
bounded by the number of distinct GET paths hit per hour; query it directly: per endpoint via
`GROUP BY path`, per version via `path LIKE '/v3/%'`, per day via `GROUP BY DATE(bucket_hour), path`.

The admin UI surfaces this at `/admin/stats` (`AdminStatsController::api`, sidebar Stats → API). `ApiHitStats::collect()`
loads the raw rows for the selected window (`?days=` ∈ 1/7/30/90/365/0-for-all, default 30) and folds them in PHP —
no SQL date functions, so it stays portable across MySQL and the SQLite test DB. `ApiHitGrouping` (`?group=` ∈
hour/day/week/month, default day) supplies the `bucket_hour` format string used as the period key; the formats are
chosen so string sort == chronological sort. Endpoints are normalised by dropping every purely numeric path segment
(`/v2/holiday/en/day/7/7` → `/v2/holiday/en/day`). The page renders a hits-over-time line (one line per version), a
top-endpoints bar and the full period × endpoint matrix with per-version (`v2`, `v3`) columns and totals.

### Stats pages & charts (Admin UI)

`AdminStatsController` (`/admin/stats`) serves the three pages of the collapsible "Stats" drawer group:

- **API** (`/admin/stats`, route `admin_stats`) — API traffic, described above.
- **Holidays** (`/admin/stats/holidays`, route `admin_stats_holidays`) — holiday/translation coverage. This page
  replaced the old per-kind "Missing translations" pages: it shows fixed/floating/language/tag/missing tiles, a stacked
  bar of translated-vs-missing per language, fixed holidays per month, floating holidays per algorithm, and a table
  whose cells link into the still-existing per-language drill-down `admin_missing`
  (`/admin/missing/{kind}/{lang}`).
- **Users** (`/admin/stats/users`, route `admin_stats_users`) — reporter activity: tiles, reports-per-month line,
  reports-by-type doughnut, top-ten-reporters bar and the full reporter table with ban/unban actions.

Charts are Chart.js, bundled through Encore (never a CDN) and driven from `assets/charts.ts`. Templates stay
declarative: `admin/components/chart_card.html.twig` renders a `<canvas data-chart='{id}-data'>` next to a
`<script type='application/json' id='{id}-data'>` holding `{kind, labels, series}` (snake_case, like every other
Twig-embedded JSON), and the controller builds that spec. `kind` ∈ `line | bar | stacked_bar | horizontal_bar |
doughnut`. Axis labels are spelled out, never codes: languages come from ICU (`Locale::getDisplayName($code, 'en')`,
hence the `ext-intl` requirement) rather than the DB `name` column, months are full names, algorithms use
`Algorithm::label()`; reporters are keyed by UID but labelled with their name (UID only as a last-resort fallback). A
label may also be a list of strings, which Chart.js renders as one line per tick. The optional `notes` array carries
one hover-only string per category, shown under the tooltip title — that is where detail goes that would crowd the axis
(the top-reporters chart puts the reporter's e-mail there). The categorical palette in `charts.ts` is fixed-order and validated for colour-vision deficiency against the
dark admin surface — series take slots by position; never cycle, recolour or extend the hues ad hoc. Its CVD margin
sits in the floor band, which is only legal with a secondary encoding, so **every chart keeps a legend (≥ 2 series) and
the same numbers in a table on the same page** — do not add a chart without its table.

### v3 API

Single endpoint: `GET /v3/holidays` with query parameters:

- `lang` (required, case-insensitive) — language code
- `year` (optional, defaults to current year)
- `day` (optional) — filter by day of month
- `month` (optional) — filter by month
- `country` (optional, case-insensitive) — filter by country ISO code
- `grouping` (optional, default `false`) — when `true`, groups holidays by day in v2-compatible `HolidayDay` format
- `includeMatureContent` (optional, default `false`) — when `true`, includes holidays whose metadata `matureContent` is
  true alongside the rest; otherwise mature ones are filtered out

The v3 merges fixed and floating holidays into a unified flat list sorted by date. Each item has a prefixed `id` (
`fixed-*` or `floating-*`) and includes a `categories` array of tag names translated into the requested `lang` (falling
back to the tag slug when no translation exists). Categories are bulk-loaded once per side via DQL to avoid N+1.

### Algorithm Resolver (v3)

Floating holiday dates in v3 are computed by polymorphic resolver classes in `src/Service/Algorithm/`, each implementing
`AlgorithmResolverInterface`. The `Algorithm` enum maps each case to its resolver class via `resolverClass()`.
`AlgorithmResolver` is a thin factory using Symfony's `#[AutowireLocator]` to inject all resolvers via a
`ServiceLocator`. `Algorithm::label()` is the human name ("N-th day of week in month") shown everywhere in the admin UI
(the floating holiday form's `<select>`, the algorithm chart); the snake_case case value stays the wire format — DB
column, JSON payloads, `<option value>` — so add a label whenever you add a case.

Available algorithms with v2 `args` → v3 `algorithmArgs` mapping (dayOfWeek uses ISO 1-7, Mon-Sun):

- `nth_day_of_week_in_month` — nth occurrence of a weekday in a month. v2: `[month, dayOfWeek, nth]` → v3:
  `{"nth": 4, "dayOfWeek": 4, "month": 11}` (4th Thursday of November = Thanksgiving)
- `last_nth_day_of_week_in_month` — nth-to-last occurrence of a weekday in a month. Same keys as above:
  `{"nth": 1, "dayOfWeek": 1, "month": 5}` (last Monday of May = Memorial Day)
- `first_day_of_week_after_date` — first weekday on or after a date. v2: hardcoded in script → v3:
  `{"dayOfWeek": 6, "month": 5, "day": 19}` (first Saturday on or after May 19). Optional `"inclusive": false` to
  exclude the start date.
- `last_day_of_week_before_date` — last weekday on or before a date. v2: hardcoded in script → v3:
  `{"dayOfWeek": 5, "month": 3, "day": 20}` (last Friday on or before March 20). Optional `"inclusive": false` to
  exclude the start date.
- `nearest_day_of_week_to_date` — weekday nearest to a given date. `{"dayOfWeek": 6, "month": 6, "day": 17}`
  (Saturday nearest June 17). Forward/backward distances can never tie (they sum to 7), so the result is always
  unambiguous; it may cross month or year boundaries.
- `nth_day_then_next_day_of_week` — finds nth weekday, then the next occurrence of another weekday after it. v2:
  `[month, dayOfWeek, nth, after]` → v3: `{"nth": 1, "dayOfWeek": 1, "month": 7, "afterDayOfWeek": 2}` (Tuesday after
  the 1st Monday of July)
- `leap_year_date` — returns different dates for leap/non-leap years.
  `{"leapDay": 29, "leapMonth": 2, "nonLeapDay": 1, "nonLeapMonth": 3}` (Feb 29 in leap years, Mar 1 otherwise)
- `easter_offset` — a fixed number of days before/after Easter Sunday, for movable feasts tied to Easter.
  `{"offset": 60}` (Easter + 60 days = Corpus Christi / Boże Ciało). Negative offsets go backwards
  (`{"offset": -46}` = Ash Wednesday). Easter itself is computed via PHP's `easter_days()`; the offset may cross
  month or year boundaries.
- `earth_hour` — last Saturday of March, shifted back one week if it would fall on Holy Saturday. Takes no args:
  `{}`. (Uses `easter_days()` to detect the Holy Saturday clash.)
- `hardcoded_dates` — year-to-date lookup, for holidays with no algorithmic pattern.
  `{"2024": "12.9", "2025": "20.9", "2026": "19.9"}` (year keys map to `day.month` strings). Returns null for missing
  years.
- `fixed_date_with_changes` — a default month/day that changes from a given year onward (e.g. a holiday whose official
  date was moved). `{"defaultDay": 6, "defaultMonth": 4, "changes": [{"fromYear": 2023, "day": 23, "month": 4}]}`
  (April 6 before 2023, April 23 from 2023 on). `changes` are applied in order; the last matching `fromYear` wins.

`algorithmArgs` is stored as a JSON column. Common pitfalls: JSON keys must always be quoted strings (e.g., `"2026"` not
`2026`) and trailing commas are not allowed.

### User Reports (Suggestions & Errors)

`UserControllerV2` dispatches to handler classes via `ReportHandlerInterface`. Four handlers cover the matrix of
{suggestion, error} × {fixed, floating}. Handlers are wired explicitly in `config/services.yaml`.

Reports carry two distinct country fields, and only one is a reference. A suggestion's `country` is a real FK into
`country` — the user picks it from the list `GET /v2/countries` serves. `device_country` (in `ReporterDeviceMetaTrait`,
on all four report tables) is **telemetry**: a plain `VARCHAR(2)` holding whatever ISO-3166 code the reporter's device
locale reported. It has no FK, because a device may legitimately sit in a country we hold no holidays for, and an FK
would silently null those rows out and skew `by_device_country` in `/admin/api/reports/stats`. Normalisation
(uppercase, `''`/`'null'` → `null`) is the pure static `Country::normalizeCode`. Do not "fix" this into a relation.

### Admin UI

`ManageController` + `WebController` serve Twig templates under `/admin`. Protected by `ROLE_USER` via HTTP Basic Auth.
`AdminTagController` (under `/admin/tags`) handles tag (category) management with chip UI and per-type usage counters.
`AdminLanguageController` (under `/admin/languages`) handles language create/delete; deletion requires the user to
type either the language's display name or the literal word `DELETE` in a confirmation modal, and cascades all
related rows (fixed/floating holiday translations, error reports, category translations) in a single transaction.
The Polish source language (`pl`) cannot be deleted from the UI.
Legacy `/manage/*` URLs return 301 redirects to `/admin/*` via `ManageRedirectController`.

Reports moderation is split into four separate drawer entries under a "Reports" sidebar group — one page per report
type at `/admin/reports/{type}` where `type` ∈ `fixed-suggestions | floating-suggestions | fixed-errors |
floating-errors` (route `admin_reports`). `/admin/reports` (route `admin_reports_index`) 302-redirects to the first
type. Each page renders a single `admin/components/reports_table.html.twig` (no tabs) plus the matching moderation
modal (suggestion modal for `*-suggestions`, error modal for `*-errors`). Per-type pending badges in the sidebar come
from `AdminNavRuntime`'s `reportsByKind` (the `reported`-state count per `ReportKind`). The dashboard's Reports panel
rows link to the corresponding per-type page. `admin_reports_moderate` / `admin_reports_delete` remain the shared
POST endpoints driven by `assets/reports.ts`.

### Users & bans (Admin UI)

There is no local user table — an end user only exists as a Firebase UID repeated across the four report tables, the
poll votes and the `ban` table. `FirebaseUserLookup` resolves UIDs to name/email/avatar (Gravatar identicon fallback);
`AdminUserService` does the GROUP BY aggregation over the report tables and owns the bulk report deletion.

The two user-facing pages live in different places on purpose: the read-only **User stats** page is a Stats page
(`/admin/stats/users`, `AdminStatsController::users`, see above), while **Banned** (`/admin/bans`, route
`admin_users_banned`, `AdminUserController::banned`) is a top-level drawer entry — the `ban` table (user, reason,
report counts, ban date) with unban and, when the user still has reports, a "delete their reports" button. Its sidebar
badge is `AdminNavRuntime`'s `bannedCount`. `AdminUserController` also owns every `/admin/api/users/*` write endpoint.

Bans are **permanent**: `Ban` stores `user_id` (unique), `reason`, `datetime`; unbanning deletes the row and re-banning
overwrites the reason (`Ban::update`). `BanService::getBanInfo` is what blocks banned users from reporting
(`UserControllerV3`) and voting (`PollControllerV3`).

Every ban entry point opens the same modal, `admin/components/ban_modal.html.twig` (ban / unban / delete-reports),
driven by `assets/users.ts`: the reporter bar of both report moderation modals (a Ban button that flips to Unban with a
"Banned" badge carrying the reason), the reporter rows of the User stats table, and the "Ban user" topbar button on the
User stats and Banned pages (manual ban by UID). The ban modal always asks what to do with the user's reports: keep
them, delete only the pending
(`REPORTED`) ones, or delete all — with live counts fetched from `GET /admin/api/users/report-counts`. The JSON
endpoints (`/admin/api/users/ban`, `/unban`, `/reports/delete`) are CSRF-protected with the `user_ban` token and use
`snake_case` keys like every other admin API. Report rows carry the reporter's ban state as
`data-report-user-banned` / `data-report-user-ban-reason`; after a ban/unban `users.ts` reloads the Users pages and
emits `ferrio:user-updated` elsewhere, which `reports.ts` uses to patch the rows in place (or reload, if reports were
deleted).

List views (`/admin/create/{month}` for fixed, `/admin/floating` for floating) are read-only summaries. Each row is a
button linking to the **holiday detail page** at `/admin/holiday/{kind}/{id}` (kind ∈ `fixed` | `floating`). Each row
also shows a `XX/YY` translation count (translations present / total non-Polish languages) sourced from
`AdminMetricsService::{fixed,floating}TranslationCountsByMetadata`. Each list view has an "Add holiday" button in the
topbar that links to `/admin/holiday/{kind}/new` (the fixed page passes the current month as `?month=N` so the new
page pre-fills it). There is no separate translate page — translation editing lives on the detail page.

The detail page (`templates/admin/holiday_detail.html.twig`) handles both edit and create flows. In edit mode the URL
is `/admin/holiday/{kind}/{id}`; in create mode it is `/admin/holiday/{kind}/new` with an `isNew` Twig flag that
swaps the save URL to `POST /admin/api/holiday/{kind}` (create endpoint) instead of
`POST /admin/api/holiday/{kind}/{id}` (save endpoint). In create mode the Save button enables as soon as the Polish
source name is non-empty (instead of tracking dirty state), and on success the JSON response includes a `redirect`
URL pointing to the new detail page — the frontend navigates there so subsequent saves go through the regular edit
flow. Three sections: Metadata (date for fixed; algorithm + algorithm args JSON for floating; country, mature switch,
tag picker for both), Source (Polish name + description; AI sparkle button only on the description — there is no AI
generation for the Polish name since Polish IS the source from which everything else is translated), and Translations
(dynamic rows for every non-Polish language present in the DB, with an "Add translation" dropdown for unused languages
and a × to delete a translation). The "Add translation" dropdown auto-closes after a pick and the button hides itself
once every language has been added. Save (in edit mode: `POST /admin/api/holiday/{kind}/{id}`) is a single
CSRF-protected JSON call that upserts the metadata + the PL source row + every dirty/added/removed translation in one
transaction. Translation rows whose name and description both come in empty are deleted server-side. The frontend
(`assets/holidayDetail.ts`) tracks dirty state across all sections and only enables Save when something has changed.
In edit mode the actions row also exposes a Delete button (red, bottom-left) that opens a yes/no confirmation modal;
on confirm the frontend POSTs `/admin/api/holiday/{kind}/{id}/delete` (CSRF-protected) which removes the metadata in
a transaction — related holiday translations, error reports, and tag join rows cascade via Doctrine/DB constraints;
suggestion FKs pointing at the holiday are nullified first.

The detail page topbar also exposes a three-dot menu (edit mode only) with a "Convert to {opposite kind}" action.
The link points at `/admin/holiday/{otherKind}/new?from={kind}&fromId={id}`; `holidayNew` reads those query params
and pre-fills source name/description, all translations, country, mature flag, and tags from the original metadata.
Date (for fixed) / algorithm + algorithmArgs (for floating) are intentionally left blank — the user must fill them
before saving. Conversion is non-destructive: it copies into a brand-new metadata row of the opposite kind, leaving
the original untouched.

`GenerateDescriptionController` (`POST /admin/api/generate`) calls the Anthropic API (Claude Sonnet 4.6) to generate
holiday content. It accepts `{day, month, name, type?, language?}` JSON and returns `{result}` (or `{error}`). `type`
selects the prompt set: `description_pl` (default, Polish description), `description` (English-style description), or
`name` (translates a Polish holiday name into the target `language`). The system prompts enforce Ferrio's copywriting
style (150-250 words, informative tone) and live in `config/prompts/`. The API key is configured via `ANTHROPIC_API_KEY`
env var. The detail page's Source description and per-translation sections each have AI sparkle buttons. The shared
helper lives in `assets/aiGenerate.ts` — `attachGenerateHandler` accepts an optional `enabled: () => boolean`
predicate and returns a `GenerateHandle` with a `refresh()` that re-evaluates `disabled` (also called after each
generation finishes, instead of unconditionally re-enabling). The detail page wires the following predicates and calls
`refreshAiButtons()` whenever date/algorithmArgs, source name, or any translation name changes:

- **All AI buttons** require the date to be fully set: for fixed, both day and month are non-empty; for floating,
  `algorithmArgs` is non-empty.
- **Translation name buttons** (per language) additionally require the Polish source name — they translate FROM Polish,
  so without a source name there is nothing to translate.
- **Description buttons** (Source PL and per-language) additionally require the name in the SAME language to be
  present — descriptions are generated about a specific holiday name, and the prompts expect a name in the target
  language.

### Testing

- Tests extend `WebTestCase` and use `Liip\TestFixturesBundle` with `DAMA\DoctrineTestBundle` for transactional test
  isolation.
- Fixtures live in `tests/Fixture/` and are loaded per-test in `setUp()`.
- `TestUtilTrait` provides `request()` and `getFixture()` helpers used across all controller tests.
- Test environment uses SQLite in-memory (configured in `.env.test`).

### Key Conventions

- PHP 8.5 property hooks and `private(set)` visibility are used in entities.
- Doctrine mapping uses PHP 8 attributes (not XML/YAML).
- Avoid ternary expressions (`?:`) in PHP — prefer `if`/`else` blocks for clarity. Simple one-liners
  (a single condition assigning or returning, fits cleanly on one line) are the allowed exception, as
  are Twig inline ternaries inside template attributes where `{% if %}` would be unwieldy. Avoid nested
  or multi-condition ternaries even when they fit on a line.
- All JSON keys in admin internal APIs (`/admin/api/*`) and JSON embedded in Twig templates (data-* blobs and
  `<script type='application/json'>` payloads) MUST use `snake_case` — never camelCase. The PHP-side variable that
  holds the value can stay camelCase; only the JSON key gets converted (e.g. `'country_code' => $country->isoCode`).
  This convention does NOT apply to public versioned APIs under `/v2`, `/v3` — those keep their existing shape
  for backwards compatibility.
- Always use CRLF line endings in all files. Every file must also end with a final CRLF (trailing newline) — no
  exceptions, including JSON, YAML, SCSS, TS, PHP, Twig, and Markdown.
- In titles and headings, only use the plain ASCII hyphen `-` (the key on a normal keyboard). Never use en-dash `–`
  or em-dash `—`.
- Frontend uses Webpack Encore with TypeScript and Bootstrap 5 / MDB UI Kit. Icons: Bootstrap Icons, Unicons, Font
  Awesome 5.
- TypeScript conventions:
    - Always use single-quoted string literals (no double quotes, no backticks unless interpolation is needed).
    - Always use template-literal interpolation (`` `${a} ${b}` ``) over string concatenation with `+`.
    - Always use curly braces for one-line `if`/`else`/`for`/`while` bodies — never the brace-less form.
    - Prefer named `function` declarations over `const` arrow functions for top-level/module functions. Arrow functions
      are fine when passed as an argument (callbacks, handlers, array methods).
    - Prefer `element.dataset.foo` over `element.getAttribute('data-foo')` when reading `data-*` attributes.
    - Mark all interface properties `readonly` by default. Interfaces describe shapes that are passed around, not
      mutable state — if a property genuinely needs to be reassigned, model the mutability on a class instead.
- CI runs on GitHub Actions (`.github/workflows/ci.yml`), executing PHPUnit on PHP 8.5.
- Composer is at `C:\Tools\php85-ts\composer.bat` (not on PATH in bash).
