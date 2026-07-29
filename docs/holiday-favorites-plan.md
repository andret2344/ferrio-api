# Feature: Holiday Favorites (cross-device, private)

Build a cross-device "favorite" feature for Ferrio API. Work in small, reviewed steps -
propose the data model and API contract first, get my OK, THEN implement. Follow every
convention in CLAUDE.md (CRLF + trailing CRLF everywhere, ASCII hyphens only, snake_case
for admin/internal JSON, no ternaries in PHP, TS style rules, PHP 8.5 property hooks).
Write tests for every new code path.

This is a SEPARATE feature from `docs/holiday-likes-plan.md`. Both ship and coexist - do not
merge them, do not treat one as replacing the other. They are mechanically almost identical
(per-metadata, per-UID, unique row, PUT/DELETE by prefixed id, signed-in only) and differ on
exactly one axis: likes expose a PUBLIC counter, favorites are a PRIVATE list.

| | Likes (`holiday-likes-plan.md`) | Favorites (this doc) |
|---|---|---|
| Purpose | public popularity signal | private per-user bookmark |
| Public counter | `like_count` on every holiday | none, ever |
| Per-user flag | `liked` (via GET /v3/likes) | `favorited` (via GET /v3/favorites) |
| Anonymous users | REJECTED (signed-in only) | REJECTED (signed-in only) |
| Route | `/v3/likes` | `/v3/favorites` |
| Entities | `*HolidayLike` | `*HolidayFavorite` |

Both features are signed-in only and reuse the SAME anon-rejecting verifier
(`FirebaseTokenVerifier::verify()`) - there is no forked "anon-tolerant" path anymore. Neither
stores anonymous rows, so neither has orphans and neither needs a prune command.

**Where per-user state lives.** GET /v3/holidays stays PUBLIC and STATELESS so it remains
cacheable. Per-user flags (`liked`, `favorited`) do NOT ride on the public listing - each
feature serves the caller's own set from its own signed-in endpoint (GET /v3/likes, GET
/v3/favorites) and the client overlays them. The only engagement data on the public listing is
`like_count`, which is public (same for everyone) and read from a denormalized column - that is
the likes feature's concern, not this doc's.

## Goal

A single logged-in user marks holidays as favorites and sees the same private set on every
device. It is a PRIVATE bookmark list (later drives client-side notifications - that part is
the client's job, out of scope here). It is NOT a public counter: no holiday ever exposes a
total favorite count to anyone, and no other user's favorite state is ever visible.

Identity is the Firebase UID. There is NO local user table - a user only exists as a UID
(same model as reports/votes/likes). No device_id, no migration/claim endpoint. Anonymous
Firebase UIDs are per-install and never sync across devices, so storing them would give zero
cross-device value; favorites are therefore signed-in only and anonymous stays device-only
(the client keeps anonymous favorites locally; the server never learns about them).

## Locked decisions (do not re-litigate)

- **Version:** v3 only. v2 stays frozen.
- **Semantics:** private per-user list. No public counter anywhere. No per-user favorite state
  is ever exposed for any user other than the authenticated caller.
- **Scope:** both fixed AND floating holidays. A favorite targets the *metadata* (language-
  independent), NOT a per-language holiday row - same as how reports/likes reference metadata.
- **Naming:** `favorite`. Entities `FixedHolidayFavorite` / `FloatingHolidayFavorite`, routes
  under `/v3/favorites`, JSON key `favorited`.
- **Identity + anonymous rejection:** reuse the existing Firebase stack. It ALREADY does
  exactly what we need - do not rebuild it:
  - `App\Service\FirebaseTokenVerifier::verify()` verifies the Bearer token AND throws for
    anonymous users (`firebase.sign_in_provider === 'anonymous'`, and the UID-provider check).
    So "anonymous = rejected" is already enforced at the verifier level.
  - `App\Security\FirebaseAuthenticator` + the `#[FirebaseAuth]` attribute wire that verifier
    into a firewall and return 401 (`onAuthenticationFailure`) for missing/invalid/anonymous
    tokens. `PollControllerV3` is the reference usage: `#[FirebaseAuth]` on the class,
    `$this->getUser()->getUserIdentifier()` to read the UID.
  - Net effect: **anonymous users are device-only, the server never learns about them.** An
    anonymous or absent token gets 401 and nothing is persisted. No server-side anonymous
    favorite rows can ever exist, so there are no favorite orphans and no prune command.

## Data model

Follow the parallel fixed/floating convention already in the domain (FixedHolidayError /
FloatingHolidayError). Two entities/tables:

- `FixedHolidayFavorite` - (`user_id` VARCHAR, `fixed_holiday_metadata_id` FK, `datetime`),
  `UNIQUE(user_id, fixed_holiday_metadata_id)`.
- `FloatingHolidayFavorite` - same shape against `FloatingHolidayMetadata`.

`user_id` is a plain VARCHAR holding the Firebase UID (no FK, like report/vote user ids).
Add a Doctrine migration. Cascade-delete a favorite when its metadata is deleted (match how
the holiday-delete flow already cascades errors/translations - see the holiday-detail delete
endpoint in `AdminUserController`/holiday delete flow; extend that cascade to favorites).

Favorites do NOT need a denormalized counter column (that is a likes-only optimization, because
only likes reads a public total on the hot path).

## API

All `/v3/favorites` routes require a valid non-anonymous Firebase token (put the controller
behind `#[FirebaseAuth]` AND widen the firewall so it applies - see "Wiring" below). The UID
comes from `$this->getUser()->getUserIdentifier()`. Missing/invalid/anonymous token -> 401 for
every route below.

`{id}` is the prefixed holiday id `fixed-*` / `floating-*` (the same ids GET /v3/holidays
returns). Parse the prefix to pick the table; unknown prefix or missing metadata -> 404.

1. **PUT /v3/favorites/{id}** - add favorite for this UID+metadata. Idempotent (INSERT with
   ON CONFLICT DO NOTHING, or catch the unique-constraint violation). Status codes: **201** when
   a row is actually created, **200** when it already existed (idempotent re-add). Never 500 on
   a duplicate.
2. **DELETE /v3/favorites/{id}** - remove the favorite. Idempotent: **204** whether or not a row
   existed (deleting a non-existent favorite is still success).
3. **GET /v3/favorites** - returns THIS UID's favorites as a list of LIGHT holiday objects, not
   bare ids. Takes `lang` (required, to name them) and optional `year` (defaults to current, to
   resolve floating dates). Each item: `{id, kind, name, day, month, categories}` (prefixed id,
   `fixed`/`floating`, name in `lang`, resolved day/month for `year`, translated categories) -
   the same serializer subset GET /v3/holidays produces, computed for that year. This is the
   cross-device sync + render call on app start.

   Why light objects and not bare ids: a favorite may not appear in any given GET /v3/holidays
   response (floating needs a year to have a date; a mature favorite is hidden when
   `includeMatureContent=false`; a filtered listing hides most). Bare ids would leave the client
   holding unrenderable ids. At favorites-list cardinality (a user's handful of picks) the extra
   bytes are irrelevant, so return enough to render. Merge fixed + floating, order
   deterministically (e.g. datetime desc, then id) so tests are stable.

GET /v3/holidays is NOT touched by this feature. It carries no `favorited` key. The client
gets the user's favorite set from GET /v3/favorites and overlays it locally. This keeps the
public listing stateless and cacheable and keeps Firebase off its hot path.

## Wiring (security config)

- `config/packages/security.yaml`: widen the `firebase` firewall pattern from
  `^/v3/(users|polls)` to `^/v3/(users|polls|favorites)`, and add an `access_control` line
  `- { path: ^/v3/favorites, roles: [ ROLE_FIREBASE_USER ] }` (place it with the other
  `^/v3/...` firebase entries, before the catch-all `^/`). When likes ships it widens the same
  pattern again to add `likes` - coordinate so one edit does not clobber the other.
- Do NOT change GET /v3/holidays wiring. It stays PUBLIC and unauthenticated.

## Admin stats

Add a Favorites view to the Stats drawer group, consistent with the existing API / Holidays /
Users stats pages (`AdminStatsController`, `chart_card.html.twig`, `assets/charts.ts`). The
server can aggregate favorites because every stored row belongs to a real (non-anonymous) UID.

- Most-favorited holidays (top N) as a horizontal bar chart + the mandatory table underneath
  (every chart ships a table per CLAUDE.md - the API doughnut exception does NOT apply here).
- Tiles: total favorites, favorites on fixed vs floating, distinct users with >=1 favorite.
- Favorites-over-time line (reuse the ApiHitStats-style PHP folding, no SQL date functions, so
  it stays SQLite-test-portable).
- Respect the fixed-order CVD palette; keep every chart's numbers reachable as text; cap
  categories to the palette (top N + "Other") so hues never repeat.
- Route/label consistent with siblings (e.g. `/admin/stats/favorites`, route
  `admin_stats_favorites`, sidebar Stats -> Favorites). If likes also ships a stats page,
  consider one shared "Engagement" page with likes + favorites series instead of two near-
  identical pages - decide when the second one lands, not now.

## Tests (required)

- Controller tests for /v3/favorites: add (201), idempotent re-add (200), remove (204),
  idempotent re-remove (204), 401 without token, 401 with anonymous token, fixed and floating
  ids, unknown/malformed id (404), and that GET /v3/favorites returns only the caller's rows
  (isolation between two UIDs).
- GET /v3/favorites: correct light-object shape, `lang` names the holiday, floating dates
  resolve for the given/defaulted `year`, deterministic ordering.
- Admin favorites stats page renders with fixtures.
- Extend `tests/Fixture/` and use `TestUtilTrait` `request()` / `getFixture()`. For Firebase
  auth in tests use the existing test doubles (`TestFirebaseTokenVerifier` /
  `TestFirebaseUserLookup`, see `PollControllerV3Test` / `UserControllerV3Test` for how a
  token maps to a UID and how anonymous rejection is exercised). SQLite in-memory,
  transactional isolation as usual.

## Out of scope (do NOT build)

- No public counter FOR FAVORITES (the public counter is the separate likes feature).
- No `favorited` key on GET /v3/holidays - per-user state comes from GET /v3/favorites only.
- No anonymous server storage of favorites, no device_id path, no /claim or migration
  endpoint, no orphan-prune command for favorites (there are no favorite orphans).
- No v2 endpoints.
- No notifications (client concern).
- No changes to the likes/reports/votes/ban/poll model. Favorites is additive alongside them.

## Process

1. First message back: propose the exact entity fields, the migration, the GET /v3/favorites
   light-object shape (with `lang`/`year` params), and the PUT/DELETE status codes. Confirm the
   security.yaml firewall/access_control edits. Wait for my OK.
2. Then implement in order, tests alongside each step:
   entities + migration -> PUT/DELETE /v3/favorites -> GET /v3/favorites (light objects) ->
   admin favorites stats page.
3. After each step, run `vendor/bin/phpunit` and report. Update CLAUDE.md with a new "Holiday
   Favorites" section describing the model, endpoints, the signed-in-only / anonymous =
   device-only guarantee, and that per-user state is served from GET /v3/favorites rather than
   riding on the public listing.
