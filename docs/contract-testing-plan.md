# Contract Testing Plan

**Status:** Phase 1 implemented (2026-06-28) — v2 reports + suggestions **and** the v3
`/v3/users/reports` endpoint (incl. its auth dimension). Phase 2 (other v2/v3 endpoints) planned.
**Created:** 2026-06-19, during the reporter-device-meta refactor.
**Owner:** Andret2344.

## Goal

An append-only regression suite that pins the public REST contract of `/v2/*` and `/v3/*` so that:

- **Adding** a new field to a request or response does NOT break the suite.
- **Removing**, **renaming**, or **changing the type of** a field does break it.
- An **old client keeps working**: the literal request body a previous client generation sent is
  still accepted, and the response still satisfies the shape that generation was built against.
- Status codes, route paths, and value formats (date/datetime/enum) are part of the contract and
  asserted alongside the body shape.

These tests are independent of the functional tests under `tests/Controller/` — they exist to
catch contract drift, not to verify business behavior.

## The generational model

Each endpoint owns a folder of **frozen generation pairs**. A generation is one snapshot of the
client contract at a point in time:

- `genNN[_label].json` — the **exact request body** a client of that generation sends (a literal,
  never-edited fixture).
- `genNN[_label].schema.json` — the **response shape** that generation relies on (JSON Schema
  draft-07).

The discipline is strict: **a new field never edits an existing generation.** You drop in a new
`genNN` pair. Old generations live forever and keep being replayed.

Why this proves "old client still works":

- The old request (`gen01.json`, no `device`) is replayed against today's server → must still
  return `201`. That is the **request** half of backwards-compat.
- The old response schema (`gen01.schema.json`, no `device`) is validated against today's
  response → must still pass. Response schemas deliberately omit `additionalProperties: false`,
  so an added field (`device`) is invisible to `gen01` (forward-compat), while a removed/renamed/
  retyped field breaks **every** generation that declared it. That is the **response** half.

`gen02_device` is the first generation that knows about `device`: its request sends the nested
`device` object and its schema requires it.

## Decisions already made

| Decision                          | Choice                                                       |
|-----------------------------------|--------------------------------------------------------------|
| Response validation               | JSON Schema (draft-07), `justinrainbow/json-schema ^6.0`     |
| Request side                      | Literal frozen fixtures per generation (replayed, not schema)|
| Coverage                          | Requests (replay → status) AND responses (schema)           |
| API versions                      | v2 and v3 only (v1 = legacy, skip)                          |
| Test structure                    | One test class per endpoint; one `@DataProvider` per gen     |
| Status codes                      | Asserted in the same test as the body shape                 |
| `additionalProperties` on shapes  | omitted (default `true`) — forward-compat for added fields  |

## Dependency

```bash
composer require --dev "justinrainbow/json-schema:^6.0"
```

## File layout (as built)

```
tests/Contract/
├── ContractTestCase.php              # base: loadRequest() + assertResponseMatchesContract()
├── V2/
│   ├── FixedErrorReportContractTest.php       # POST/GET /v2/report/.../fixed
│   ├── FloatingErrorReportContractTest.php    # POST/GET /v2/report/.../floating
│   ├── FixedSuggestionContractTest.php        # POST/GET /v2/missing/.../fixed
│   └── FloatingSuggestionContractTest.php     # POST/GET /v2/missing/.../floating
└── data/
    └── v2/
        ├── fixed_error/         success: gen01_baseline, gen02_device  (.json + .schema.json)
        │                        reject:  reject_banned (.json + .schema.json), reject_missing_required (.json)
        ├── floating_error/      (same set)
        ├── fixed_suggestion/    (same set)
        └── floating_suggestion/ (same set)
    └── v3/
        └── users_reports/       one endpoint, 4 combos (reportType × holidayType):
            ├── error_fixed/         success: gen01_baseline, gen02_device (.json + .schema.json)
            ├── error_floating/      (same set)
            ├── suggestion_fixed/    (same set)
            ├── suggestion_floating/ (same set)
            ├── reject_banned.{json,schema.json}          403 {reason}
            ├── reject_invalid_field.{json,schema.json}   422 {errors:[{property,message}]}
            └── reject_invalid_token.schema.json          401 {error}  (GET, no request body)
```

The v3 response schemas are byte-identical to the matching v2 ones (shared handlers → same shape).
They are still **copied**, not `$ref`-shared: v2 and v3 are independent contracts that may diverge,
and the no-retroactive-mutation rule applies across versions too.

### File naming convention

One convention across every endpoint folder:

- `gen01_baseline`, `gen02_device`, … `genNN_<label>` — success generations (append-only).
- `reject_banned` — a valid request from a banned user (403).
- `reject_missing_required` — a request missing a required field (422).
- A `<scenario>.schema.json` exists **iff** the response body is part of *our* contract: the `gen*`
  `GET`-back shape and the controller-authored `{reason}` 403 body have schemas; the framework-
  rendered 422 body is status-only (matching the functional tests).

**Do not de-duplicate schemas with `$ref`.** The `device` block and per-endpoint item shapes repeat
on purpose — a shared file edited later would retroactively mutate a *frozen* generation, which is
exactly what this suite prevents. Each generation stays self-contained.

The `tests` testsuite in `phpunit.dist.xml` already covers `tests/Contract/`, so no config change
was needed.

## ContractTestCase

Two helpers, both resolving paths relative to `tests/Contract/data/`:

- `loadRequest(string $relativePath): array` — decodes a frozen request fixture to an associative
  array so the test can inject the runtime-only foreign keys it cannot know at authoring time
  (e.g. a fixture-loaded `metadata` id). The on-disk file stays a literal wire payload; only DB
  surrogate ids are overridden before sending.
- `assertResponseMatchesContract(string $relativePath): void` — validates the current JSON
  response against a generation's schema using `JsonSchema\Validator`. On failure it fails with
  the validator's `[property] message` list.

The shared `$fixtures` property lives on `ContractTestCase` (not the subclasses) so the
`TestUtilTrait::getFixture()` helper — flattened into the base class scope — can read it.

## What each endpoint test does

- `testGenerationContract(string $generation)` — driven by `#[DataProvider('generations')]` that
  yields `gen01_baseline`, `gen02_device`, … For each generation:
  1. `loadRequest("<endpoint>/$generation.json")`, inject the `metadata` FK (error endpoints only).
  2. `POST` it → assert `201` (request contract / old-client acceptance).
  3. `GET` the user's list → assert `200`.
  4. `assertResponseMatchesContract("<endpoint>/$generation.schema.json")` (response contract).
- `testRejectsBannedUser()` — `POST reject_banned.json` → assert `403` + match `reject_banned.schema.json`.
- `testRejectsMissingRequiredField()` — `POST reject_missing_required.json` → assert `422` (status only).

The 400 invalid-JSON case stays in the functional tests (`ReportControllerV2Test`): a malformed
body cannot be a `.json` fixture, and it pins transport behavior rather than a field contract.

### v3 specifics (`UserReportsContractTest`)

The v3 endpoint is one auth-gated route parametrized by `reportType` × `holidayType` query params;
the `user_id` comes from the Firebase token (test stub: `test-token` → `user-id`, `banned-token` →
banned, `invalid-token`/`anonymous-token`/no header → 401), so v3 request fixtures carry no
`user_id`. `testGenerationContract` runs over all 4 combos × 2 generations. Extra methods pin the
auth + error contract, whose bodies are controller-authored (so they *are* pinned):

| Method                              | Asserts                                                        |
|-------------------------------------|----------------------------------------------------------------|
| `testRequestWithoutTokenReturns401` | 401 (status only)                                              |
| `testInvalidTokenReturns401`        | 401 + `{error}` (`reject_invalid_token.schema.json`)          |
| `testAnonymousTokenReturns401`      | 401 (status only)                                             |
| `testRejectsBannedUser`             | 403 + `{reason}` (`reject_banned.schema.json`)               |
| `testRejectsInvalidField`           | 422 + `{errors:[{property,message}]}` (`reject_invalid_field`)|
| `testUnknownReportTypeReturns400`   | 400 (status only — bad `reportType` query param)             |

Note a v3 quirk surfaced while writing these: v3 denormalizes the body *then* validates, so a body
**missing** a constructor-required field fails during denormalization (500), not validation (422).
Only the "present but invalid" path yields the 422 `{errors}` contract, so that is what is pinned.
The 500-on-missing-field behavior is a candidate follow-up, not pinned here.

## Adding a generation (the maintenance loop)

When you change a contract — e.g. add a `region` field to the device object:

1. Add `gen03_region.json` (request with the new field) and `gen03_region.schema.json` (response
   schema = the previous generation's shape + the new field, still `additionalProperties` open).
2. Add `yield 'gen03 - region client' => ['gen03_region'];` to the test's provider.
3. **Do not touch** `gen01*` / `gen02*`. They are the proof that older clients still work.

When you intentionally make a **breaking** change (rename/drop/retype a field), the old
generation tests are *supposed* to fail — that failing test is the gate forcing the break to be
conscious. Never silently regenerate an old generation to make it green; read the diff and decide
whether the break is intended. If it is, document it (and bump the consuming client) rather than
editing the frozen snapshot.

## Enforcement

Two automated guards back the discipline this plan relies on; they cover different failure modes
and do not overlap:

1. **Append-only immutability (git, CI).** The `contract-immutability` job in
   `.github/workflows/ci.yml` fails the build if any file under `tests/Contract/data/` is
   **modified or deleted** relative to the branch's merge-base with `main` (or, for a direct push
   to `main`, relative to the pre-push tip). Added files pass — adding a `genNN` pair is the whole
   point. `--no-renames` makes a renamed frozen file surface as a deletion. This turns "never edit
   a frozen generation" into a build failure instead of a convention. It is a red build, not a
   hard block: true prevention needs branch protection requiring this job green before merge.

2. **Authoring sanity (`FixtureSanityTest`).** A fast, kernel-free, DB-free PHPUnit test asserting
   every fixture is well-formed: each `*.json` decodes as JSON, and each `*.schema.json` uses
   draft-07 keywords with the right shapes (`type` is a known type or list of them, `required` is
   a list of strings, `enum` is a non-empty list, `properties` is an object, `items` is a schema
   or list of schemas). The git guard ignores file *contents* and the per-endpoint tests give
   cryptic errors for a broken schema; this one fails fast with the offending file and JSON
   pointer. It deliberately does **not** catch a schema that is valid but too loose, nor a
   misspelled keyword (unknown keywords are legal and ignored in draft-07) — those are
   structurally uncatchable. The library bundles only the draft-03/04 meta-schemas, so this is a
   scoped structural lint, not full meta-schema validation.

## Phasing

**Phase 1 — DONE (2026-06-28):** framework + the device-touched endpoints.
- `composer.json` / `composer.lock`: `justinrainbow/json-schema`.
- `ContractTestCase` + 4 v2 test classes (16 cases: 8 generation + 4×403 + 4×422).
- `UserReportsContractTest` (v3) — 14 cases: 8 generation (4 combos × 2) + 401×3 + 403 + 422 + 400.
- v2: 4 endpoints × (2 generations + `reject_banned` + `reject_missing_required`) = 28 data files.
- v3: 4 combos × 2 generations + 5 shared reject/auth files = 21 data files.

**Phase 2 — planned (separate PR):** the rest — `/v2/countries`, `/v2/holiday/*`, `/v3/holidays`,
`/v3/polls/*`. Low priority unless one of them is about to change. The same generational model
applies; for any other `/v3/*` route reuse the auth dimension already built here.

## Sample schema (v2 fixed error report - gen02 response item)

See `tests/Contract/data/v2/fixed_error/gen02_device.schema.json`. Shape:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "array",
  "items": {
    "type": "object",
    "required": ["id", "user_id", "language_code", "metadata_id", "report_type",
                 "description", "datetime", "report_state", "comment", "device"],
    "properties": {
      "id":            { "type": "integer" },
      "user_id":       { "type": "string", "minLength": 1 },
      "language_code": { "type": "string", "pattern": "^[a-zA-Z]{2}$" },
      "metadata_id":   { "type": "integer" },
      "report_type":   { "type": "string", "enum": ["WRONG_DATE", "WRONG_NAME", "WRONG_COUNTRY", "WRONG_DESCRIPTION", "NOT_EXISTS", "OTHER"] },
      "description":   { "type": ["string", "null"] },
      "datetime":      { "type": "string", "pattern": "^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$" },
      "report_state":  { "type": "string", "enum": ["REPORTED", "APPLIED", "DECLINED", "ON_HOLD", "DUPLICATE", "ALREADY_EXISTS"] },
      "comment":       { "type": ["string", "null"] },
      "device": {
        "type": "object",
        "required": ["platform", "model", "country", "os_version", "app_version"],
        "properties": {
          "platform":    { "type": "string", "enum": ["android", "ios", "web", "api", "unknown"] },
          "model":       { "type": ["string", "null"] },
          "country":     { "type": ["string", "null"], "pattern": "^[A-Z]{2}$" },
          "os_version":  { "type": ["string", "null"], "maxLength": 64 },
          "app_version": { "type": ["string", "null"], "maxLength": 64 }
        }
      }
    }
  }
}
```

Note: no `additionalProperties: false` anywhere — schemas are forward-compatible by design.
The `gen01` schema is the same minus the `device` property and its `required` entry.

## Stuff this plan deliberately doesn't cover

- HTTP headers (CORS, content-type) — not currently part of any consumer contract we know of.
- Pagination shape — `/v3/holidays` isn't paginated.
- Rate limiting — not implemented.
- Request **schemas** — the request side is pinned by literal replay, not by a schema. A
  forward-compat request-acceptance check (server ignores unknown request fields) already exists
  as a functional test (`ReportControllerV2Test::testPostFixedReportIgnoresLegacyFlatDeviceFields`).
- OpenAPI generation — could be follow-up; the schemas here are the raw material.
