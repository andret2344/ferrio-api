# Contract Testing Plan

**Status:** planned, not started.
**Created:** 2026-06-19, during the reporter-device-meta refactor.
**Owner:** Andret2344.

## Goal

A separate test suite that pins the public REST contract of `/v2/*` and `/v3/*`
so that:

- **Adding** a new field to a request or response does NOT break the suite.
- **Removing**, **renaming**, or **changing the type of** a field does break it.
- Status codes, route paths, and value formats (date/datetime/enum) are part
  of the contract and asserted alongside the body shape.

These tests are independent of the existing functional tests under
`tests/Controller/` — they exist to catch contract drift, not to verify
business behavior.

## Decisions already made

| Decision                          | Choice                                      |
|-----------------------------------|---------------------------------------------|
| Validation strategy               | JSON Schema (Option A)                      |
| Coverage                          | Requests AND responses                      |
| API versions                      | v2 and v3 only (v1 = legacy, skip)          |
| Test structure                    | One test class per endpoint                 |
| Status codes                      | Asserted in the same test as the body shape |
| JSON Schema draft                 | draft-07                                    |
| `additionalProperties` on shapes  | `true` (default) — forward-compat for fields|

## Open questions (decide before starting)

1. **Phasing:** do all v2/v3 in one go, or start with the recently-touched
   reports/suggestions family and follow up with the rest?
2. **Forward-compat request test:** is "server must ignore unknown request
   fields" part of the contract we want to enforce? Today
   `MapRequestPayload` ignores extras by default, but `strict: true` would
   change that.

## Dependencies to add

```bash
composer require --dev justinrainbow/json-schema
```

Library is small (~300 KB), no transitive deps of concern.

## File layout

```
tests/
└── Contract/
    ├── ContractTestCase.php          # base class with helpers
    ├── Schema/
    │   ├── v2/
    │   │   ├── fixed_error_request.schema.json
    │   │   ├── fixed_error_response.schema.json
    │   │   ├── floating_error_request.schema.json
    │   │   ├── floating_error_response.schema.json
    │   │   ├── fixed_suggestion_request.schema.json
    │   │   ├── fixed_suggestion_response.schema.json
    │   │   ├── floating_suggestion_request.schema.json
    │   │   ├── floating_suggestion_response.schema.json
    │   │   ├── country_response.schema.json
    │   │   ├── holiday_list_response.schema.json
    │   │   └── holiday_day_response.schema.json
    │   ├── v3/
    │   │   ├── holidays_response.schema.json
    │   │   ├── holiday_day_response.schema.json   # grouping=true variant
    │   │   ├── user_reports_request.schema.json   # union of 4 DTOs
    │   │   ├── user_reports_response.schema.json
    │   │   ├── poll_list_response.schema.json
    │   │   ├── poll_response.schema.json
    │   │   └── poll_vote_request.schema.json
    │   └── shared/
    │       ├── ban_response.schema.json           # {reason: string}
    │       ├── validation_errors_response.schema.json
    │       └── error_response.schema.json
    └── V2/
        ├── CountryContractTest.php
        ├── HolidayContractTest.php
        ├── FixedErrorReportContractTest.php
        ├── FloatingErrorReportContractTest.php
        ├── FixedSuggestionContractTest.php
        └── FloatingSuggestionContractTest.php
    └── V3/
        ├── HolidaysContractTest.php
        ├── UserReportsContractTest.php
        └── PollsContractTest.php
```

## ContractTestCase shape

```php
abstract class ContractTestCase extends WebTestCase
{
    use TestUtilTrait;

    protected function assertJsonMatchesSchema(string $relativeSchemaPath, ?string $body = null): void
    {
        $body ??= $this->client->getResponse()->getContent();
        $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        $schema = (object) ['$ref' => 'file://' . realpath(__DIR__ . '/Schema/' . $relativeSchemaPath)];

        $validator = new \JsonSchema\Validator();
        $validator->validate($data, $schema, \JsonSchema\Constraints\Constraint::CHECK_MODE_TYPE_CAST);

        if (!$validator->isValid()) {
            $errors = array_map(fn($e) => sprintf('[%s] %s', $e['property'], $e['message']), $validator->getErrors());
            self::fail("Response does not match contract:\n" . implode("\n", $errors));
        }
    }

    protected function assertJsonArrayMatchesSchema(string $itemSchemaPath): void
    {
        $body = $this->client->getResponse()->getContent();
        $items = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($items);
        foreach ($items as $i => $item) {
            $this->assertJsonMatchesSchema($itemSchemaPath, json_encode($item, JSON_THROW_ON_ERROR));
        }
    }
}
```

## What each endpoint test verifies (template)

Per endpoint, one test class. Per concern, one method. Each method asserts
both status code and body shape — they are facets of the same contract.

| Method                                            | Asserts                                                                       |
|---------------------------------------------------|-------------------------------------------------------------------------------|
| `testGetReturns200AndItemsMatchSchema`            | 200 + array of items, each matches response-schema                            |
| `testPostValidPayloadReturns201`                  | 201 + (if body) matches response-schema                                       |
| `testPostAcceptsExtraRequestFields`               | 201 — extra fields in request body still accepted (forward-compat)            |
| `testPostMissingRequiredFieldReturns422`          | 422 + matches validation_errors_response.schema                               |
| `testPostBannedUserReturns403`                    | 403 + matches ban_response.schema                                             |
| `testGetInvalidRouteParamReturns404`              | for endpoints with regex-constrained route params (e.g. `{userId<^\S+$>}`)    |

For `/v3/*` add an auth dimension:

| Method                                            | Asserts                                                                       |
|---------------------------------------------------|-------------------------------------------------------------------------------|
| `testRequestWithoutTokenReturns401`               | 401 + matches error_response.schema                                           |
| `testRequestWithInvalidTokenReturns401`           | 401 + `{error: "Invalid token"}`                                              |

## Sample schema (v2 fixed error report — response item)

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "FixedHolidayError",
  "type": "object",
  "required": [
    "id", "user_id", "language_code", "metadata_id", "report_type",
    "datetime", "report_state"
  ],
  "properties": {
    "id":            { "type": "integer" },
    "user_id":       { "type": "string", "minLength": 1 },
    "language_code": { "type": "string", "pattern": "^[a-zA-Z]{2}$" },
    "metadata_id":   { "type": "integer" },
    "report_type":   { "type": "string", "enum": ["WRONG_DATE", "WRONG_NAME", "WRONG_DESCRIPTION", "NOT_EXISTS", "OTHER"] },
    "description":   { "type": ["string", "null"] },
    "datetime":      { "type": "string", "pattern": "^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$" },
    "report_state":  { "type": "string", "enum": ["REPORTED", "VERIFIED", "REJECTED"] },
    "comment":       { "type": ["string", "null"] },
    "platform":      { "type": ["string", "null"], "enum": ["android", "ios", "web", "api", null] },
    "real_device":   { "type": ["string", "null"] },
    "device_country":{ "type": ["string", "null"], "pattern": "^[A-Z]{2}$" }
  }
}
```

Note: no `additionalProperties: false` — schema is forward-compatible.

## Phasing recommendation

**Phase 1 (~10 files):** framework + recently-touched endpoints.
- `composer.json` change + `composer.lock`
- `ContractTestCase.php`
- 8 schema files for v2 report+suggestion + 3 shared/v3 schemas
- 4 v2 test classes + 1 v3 test class (`UserReportsContractTest`)

**Phase 2 (separate PR):** the rest — `/v2/countries`, `/v2/holiday/*`,
`/v3/holidays`, `/v3/polls/*`. Low priority unless you're about to change
one of them.

## Maintenance discipline

- When you intentionally change a contract (rename a field, drop a field,
  change a type), update the schema in the SAME commit as the code change.
  The failing test is the gate that forces the change to be conscious.
- Never silently regenerate a schema to make a failing test pass. Read the
  diff and decide if the contract change is intended.
- Adding a field never requires a schema update — that's the whole point.

## Stuff this plan deliberately doesn't cover

- HTTP headers (CORS, content-type) — not currently part of any consumer
  contract we know of.
- Pagination shape — `/v3/holidays` isn't paginated.
- Rate limiting — not implemented.
- OpenAPI generation — could be follow-up if anyone needs Swagger docs;
  the schemas here are the raw material.
