# Feature: Holiday Likes

> Sibling feature: `docs/holiday-favorites-plan.md` (private per-user favorites). Both ship and
> coexist and are mechanically almost identical (per-metadata, per-UID, unique row, PUT/DELETE
> by prefixed id, signed-in only). They differ on ONE axis: likes expose a PUBLIC `like_count`;
> favorites are a private list with no counter. Both reject anonymous and reuse the SAME
> anon-rejecting `FirebaseTokenVerifier::verify()` - there is no forked verification path. See
> the comparison table in the favorites doc.

Build a cross-device holiday "like" feature for Ferrio API. Work in small, reviewed
steps - propose the data model and API contract first, get my OK, THEN implement.
Follow every convention in CLAUDE.md (CRLF + trailing CRLF everywhere, ASCII hyphens
only, snake_case for admin/internal JSON, no ternaries in PHP, TS style rules, PHP 8.5
property hooks). Write tests for every new code path.

## Goal

A signed-in user can like/unlike holidays and see their likes on every device, and everyone
sees a public per-holiday `like_count`. Identity is the Firebase UID. There is NO local user
table - a user only exists as a UID (same model as reports/votes/favorites). No device_id, no
migration/claim endpoint.

## Locked decisions (do not re-litigate)

- **Version:** v3 only. v2 stays frozen.
- **Scope:** both fixed AND floating holidays. A like targets the *metadata* (language-
  independent), NOT a per-language holiday row - same as how reports reference metadata.
- **Identity + signed-in only:** Firebase UID from a verified Bearer token. Reuse the existing
  stack exactly as favorites does: `FirebaseTokenVerifier::verify()` (throws on anonymous),
  `FirebaseAuthenticator` + `#[FirebaseAuth]`, `$this->getUser()->getUserIdentifier()`.
  `PollControllerV3` is the reference. Missing/invalid/anonymous token -> 401 on every likes
  route.
- **Anonymous users: REJECTED, device-only.** This reverses the earlier draft. An anonymous
  Firebase UID is per-install and never syncs across devices, so storing it gives zero
  cross-device value while costing orphan cleanup and letting anyone mint free anon tokens to
  inflate the public counter. So the server never stores anonymous likes; the client keeps them
  locally. Consequences: **no orphans, no prune command, and the public `like_count` reflects
  only real accounts** (defensible number, not spammable). Do NOT add an anon-tolerant verify
  path.
- **Idempotent verbs:** `PUT /v3/likes/{id}` = like, `DELETE /v3/likes/{id}` = unlike. `{id}` is
  the prefixed holiday id `fixed-*` / `floating-*` (same ids GET /v3/holidays returns). Parse
  the prefix to pick the table.

## Data model

Follow the parallel fixed/floating convention already in the domain (FixedHolidayError /
FloatingHolidayError). Two entities/tables:

- `FixedHolidayLike` - (`user_id` VARCHAR, `fixed_holiday_metadata_id` FK, `datetime`),
  `UNIQUE(user_id, fixed_holiday_metadata_id)`.
- `FloatingHolidayLike` - same shape against `FloatingHolidayMetadata`.

`user_id` is a plain VARCHAR holding the Firebase UID (no FK, like report user ids).
Add a Doctrine migration. Cascade-delete a like when its metadata is deleted (match how
the holiday-delete flow already cascades errors/translations).

**Denormalized counter (required).** Do NOT COUNT-on-read on GET /v3/holidays - it is the
hottest public endpoint. Add `like_count INT NOT NULL DEFAULT 0` on BOTH metadata tables
(`FixedHolidayMetadata`, `FloatingHolidayMetadata`). Maintain it transactionally with the like
write:
- On a like that actually inserts a row (affected rows = 1, not an idempotent re-like) ->
  `like_count = like_count + 1` in the same transaction.
- On an unlike that actually deletes a row -> `like_count = like_count - 1`, floored at 0.
- The like table stays the source of truth (for `liked`, stats, over-time); the column is a
  cached COUNT.
- Add a `console` reconcile command that recomputes both columns from the like tables (cheap,
  run occasionally) so any drift is self-healing.

## API

All `/v3/likes` routes require a valid non-anonymous Firebase token (controller behind
`#[FirebaseAuth]`, firewall widened - see "Wiring"). UID from
`$this->getUser()->getUserIdentifier()`. `{id}` prefix picks the table; unknown prefix or
missing metadata -> 404.

1. **PUT /v3/likes/{id}** - add the like. Idempotent (INSERT ON CONFLICT DO NOTHING or catch the
   unique violation). Bump `like_count` only when a row is actually created. Status: **201** on
   create, **200** on idempotent re-like.
2. **DELETE /v3/likes/{id}** - remove the like. Decrement `like_count` only when a row is
   actually deleted. Idempotent: **204** whether or not a row existed.
3. **GET /v3/likes** - returns THIS UID's liked holidays as LIGHT holiday objects (not bare
   ids), same shape and rationale as GET /v3/favorites: takes `lang` (required) and optional
   `year`, each item `{id, kind, name, day, month, categories, like_count}`. Bare ids would
   leave the client holding unrenderable ids (a floating like needs a year for its date, a
   mature like is hidden under a filtered listing). At a user's like-list cardinality the bytes
   are irrelevant. This is the cross-device sync + render call on app start.

4. **`like_count` on GET /v3/holidays** - add the PUBLIC `like_count` (same for everyone) to each
   holiday item, read straight from the denormalized column - no aggregation, no per-user branch,
   no token. GET /v3/holidays stays PUBLIC, STATELESS and CACHEABLE. The per-user `liked` flag
   does NOT ride on the public listing - the client gets its liked set from GET /v3/likes and
   overlays it locally (mirrors how favorites is delivered). Do not add `liked` to
   GET /v3/holidays and do not read the token there.

Keep public v3 payload keys in their existing style. New key on the public listing:
`like_count` only. (v3 already uses snake_case public keys like `mature_content`.)

## Wiring (security config)

- `config/packages/security.yaml`: widen the `firebase` firewall pattern to include `likes`
  (e.g. `^/v3/(users|polls|favorites|likes)`), and add
  `- { path: ^/v3/likes, roles: [ ROLE_FIREBASE_USER ] }` with the other `^/v3/...` firebase
  entries. Coordinate with the favorites doc's identical edit so one does not clobber the other.
- GET /v3/holidays stays PUBLIC and unauthenticated - it only reads the denormalized column.

## Admin stats

Add a Likes view to the Stats drawer group, consistent with the existing API / Holidays /
Users stats pages (`AdminStatsController`, chart_card.html.twig, assets/charts.ts).
- Most-liked holidays (top N) as a horizontal bar chart + the mandatory table underneath
  (charts always ship a table per CLAUDE.md - the API doughnut exception does not apply).
- Total likes, likes on fixed vs floating, likes-over-time (reuse ApiHitStats-style PHP
  folding, no DB date functions, so it stays SQLite-test-portable).
- Respect the fixed-order CVD palette; never add a chart without its table; keep numbers
  reachable as text.
- If favorites also ships a stats page, consider one shared "Engagement" page (likes +
  favorites series) rather than two near-identical pages - decide when the second lands.

## Tests (required)

- Controller tests for PUT/DELETE/GET likes: like (201), idempotent re-like (200), unlike
  (204), idempotent re-unlike (204), 401 without token, 401 with anonymous token, fixed and
  floating ids, unknown/malformed id (404), GET /v3/likes isolation between two UIDs.
- `like_count` maintenance: increments on first like, does NOT increment on re-like, decrements
  on unlike, floors at 0 on re-unlike; reconcile command recomputes correctly.
- GET /v3/holidays: `like_count` correct and present without any token; no `liked` key; stays
  200 with or without a token.
- GET /v3/likes: light-object shape, `lang` names them, floating dates resolve for `year`.
- Admin stats page renders with fixtures.
- Extend `tests/Fixture/` and use TestUtilTrait `request()` / `getFixture()`, with the Firebase
  test doubles (`TestFirebaseTokenVerifier`, see `PollControllerV3Test`). SQLite in-memory,
  transactional isolation as usual.

## Out of scope (do NOT build)

- No anonymous server storage, no device_id path, no /claim or migration endpoint, no
  orphan-prune command (anonymous is device-only, so there are no orphans).
- No `liked` key on GET /v3/holidays - per-user state comes from GET /v3/likes only.
- No v2 endpoints.
- No changes to the favorites/reports/votes/ban model.

## Process

1. First message back: propose the exact entity fields + the two `like_count` columns, the
   migration, the GET /v3/likes light-object shape, the PUT/DELETE status codes and counter
   maintenance, and the reconcile command. Confirm the security.yaml edits. Wait for my OK.
2. Then implement in order, tests alongside each step: entities + migration + counter columns ->
   like/unlike endpoints (with counter maintenance) -> GET /v3/likes -> `like_count` on
   GET /v3/holidays -> reconcile command -> admin stats page.
3. After each step, run `vendor/bin/phpunit` and report. Update CLAUDE.md with a new "Holiday
   Likes" section describing the model, the denormalized counter + reconcile command, the
   signed-in-only / anonymous = device-only guarantee, and that `liked` is served from
   GET /v3/likes rather than the public listing.
