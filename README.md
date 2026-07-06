# Ferrio API

Symfony 7.4 / PHP 8.5 application serving holiday data (fixed and floating) across multiple
languages and countries. It exposes a versioned JSON REST API (`/v1`, `/v2`, `/v3`) and a
Twig-based admin UI under `/admin`.

See [`CLAUDE.md`](CLAUDE.md) for the full architecture, domain model, and conventions.

## Getting started

```bash
composer install
yarn install
yarn build           # build frontend assets (yarn dev / yarn watch for development)
```

## Tests

```bash
vendor/bin/phpunit                                   # everything (SQLite in-memory, no DB setup)
vendor/bin/phpunit tests/Controller                  # functional / behavior tests
vendor/bin/phpunit tests/Contract                    # contract regression suite (see below)
```

CI (`.github/workflows/ci.yml`) runs the whole suite on every push / PR to `main`.

## Contract regression tests

`tests/Contract/` is an **append-only** regression suite that pins the public REST contract so an
**old client keeps working** as the API evolves. The rules it enforces:

- **Adding** a field to a request or response does **not** break the suite.
- **Removing**, **renaming**, or **changing the type of** a field **does** break it.
- The literal request body a previous client sent is still accepted (status code), and the
  response still satisfies the shape that client was built against.

It is independent of the functional tests under `tests/Controller/` — it catches *contract drift*,
not business behavior.

### The generational model

Each endpoint owns a folder under `tests/Contract/data/<version>/<endpoint>/`. File naming follows
one convention across every endpoint:

| File                              | Scenario                                                                 |
|-----------------------------------|--------------------------------------------------------------------------|
| `gen01_baseline.json`             | request body of the **baseline** client generation                       |
| `gen02_device.json`               | request body of the next generation (adds `device`)                      |
| `genNN_<label>.json`              | request body of a later generation (append-only — see below)             |
| `reject_banned.json`              | a valid request from a **banned** user (expects 403)                     |
| `reject_missing_required.json`    | a request missing a required field (expects 422)                         |
| `<scenario>.schema.json`          | the response shape pinned for that scenario (JSON Schema draft-07)       |

Rule for the schema files: a `<scenario>.schema.json` exists **iff** the response body is part of
*our* contract. So `gen*` (the `GET`-back list shape) and `reject_banned` (the controller-authored
`{reason}` body) have schemas; `reject_missing_required` does not — its 422 body is framework-
rendered, so only the status code is pinned (matching the functional tests' stance).

For each `gen*` generation the test (`tests/Contract/V2/*ContractTest.php`) replays the request →
asserts the success status, then `GET`s the resource and validates the response against the
generation's schema. The `reject_*` scenarios replay the request and assert the error status (plus
the `{reason}` body for 403).

Why this proves backwards-compat: `gen01` (e.g. a pre-`device` client) is replayed against today's
server and must still succeed, and `gen01.schema.json` is validated against today's response.
Response schemas omit `additionalProperties: false`, so an **added** field is invisible to older
generations (forward-compat), while a **removed / renamed / retyped** field breaks *every*
generation that declared it.

```
tests/Contract/
├── ContractTestCase.php          # loadRequest() + assertResponseMatchesContract()
├── V2/                           # one test class per v2 endpoint
├── V3/                           # UserReportsContractTest (the /v3/users/reports endpoint)
└── data/
    ├── v2/<endpoint>/            # gen01_baseline.* + gen02_device.* + reject_*.* per endpoint
    └── v3/users_reports/<combo>/ # gen*.* per reportType×holidayType + shared reject_*.*
```

Phase 1 covers the v2 reports + suggestions endpoints (`/v2/report/{fixed,floating}`,
`/v2/missing/{fixed,floating}`) and the v3 endpoint `/v3/users/reports` — the one the Android app
actually calls. v3 adds an **auth dimension**: the `user_id` comes from the Firebase token (so v3
request fixtures carry no `user_id`), and the suite pins 401 (no/invalid token), 403 (banned) and
422 (invalid field) — all controller-authored bodies.

> **Do not de-duplicate the schemas with `$ref`.** The `device` block and the per-endpoint item
> shapes repeat across generations and endpoints on purpose. Sharing them through a common file
> would mean a later edit to that file retroactively changes a *frozen* generation — exactly what
> this suite exists to prevent. Each generation must stay self-contained.

### Adding a generation

When you change a contract — say, add a field — **never edit an existing generation**:

1. Add `genNN_label.json` (request with the new field) and `genNN_label.schema.json` (previous
   shape **plus** the new field).
2. Add a `yield 'genNN - label' => ['genNN_label'];` line to that test's `generations()` provider.
3. Leave `gen01*`, `gen02*`, … untouched — they are the proof that older clients still work.

A genuinely **breaking** change is *supposed* to fail the older generations: that failing test is
the gate forcing the change to be conscious. Never silently regenerate a frozen snapshot to make
it green — read the diff, decide whether the break is intended, and bump the consuming client
rather than editing the old generation.

Full design notes and the Phase 2 backlog live in
[`docs/contract-testing-plan.md`](docs/contract-testing-plan.md).
