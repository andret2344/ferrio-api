# Database migrations

How to change the schema without breaking production. Read it before any change to an entity's
mapping - a renamed property is a schema change too.

## Why it needs care

A deploy (see [`DEPLOY.md`](../DEPLOY.md)) runs pending migrations automatically, from the new image,
**while the old release is still serving**. Only after they succeed does the new release start. So:

1. For a few seconds the **old code runs on the new schema**. A migration it cannot survive means
   errors in production during every deploy that carries it.
2. **A rollback never undoes a migration.** Deploying an older commit puts the older code back on
   the newer schema. If that schema dropped or renamed something the old code uses, there is no
   rollback - just a second outage.
3. **MySQL does not roll back DDL.** Each `CREATE`/`ALTER`/`DROP` commits on its own (hence
   `transactional: false` in `config/packages/doctrine_migrations.yaml`). A migration that fails at
   its third statement leaves the first two applied, and Doctrine does not record it as executed.

Hence two rules, and everything below follows from them:

- **Rule 1 - additive only.** Every migration leaves the schema usable by the release before it.
- **Rule 2 - one table per migration.** A single `ALTER TABLE` statement is atomic in MySQL 8; a
  migration touching two tables is two statements and can stop half way.

The `destructive-migrations` CI job (`.github/scripts/check-destructive-migrations.php`) blocks
the deploy when a migration's `up()` contains `DROP` of a table or column, `RENAME` of a table or
column, `CHANGE`, `MODIFY`, or `ADD ... NOT NULL` without `DEFAULT`. It reads text, so it catches
the usual shapes, not all of them - it is a tripwire, not the review.

## The procedure, every time

### 1. Classify the change

Look up your change in the [catalog](#catalog-of-changes) below. It says whether the change ships in
one deploy or has to be split into two (expand/contract), and in what order code and schema go.
When the change has two phases, **each phase is its own commit and its own deploy**, and phase B
ships only after phase A has run in production.

### 2. Write the migration

Change the entity, then generate:

```bash
php bin/console doctrine:migrations:diff
```

Then **read every `addSql()` line** of the generated file. The diff is a starting point, not a
result:

1. **A renamed property comes out as `DROP` + `ADD`, never as a rename.** Run as generated, that
   deletes the column's data. Rewrite it according to the catalog.
2. **A new non-nullable property comes out as `ADD ... NOT NULL` without `DEFAULT`.** Add the
   `DEFAULT` by hand (in the SQL and in the mapping, `options: ['default' => ...]`), or follow the
   catalog for a column with no sensible default.
3. **A diff touching several tables** must be split into one file per table: copy the file, bump
   the timestamp in the class and file name by one second per file (`Version20261101120000`,
   `Version20261101120001`, ...), and keep one table's statements in each.
4. **Unrelated statements** - the diff also picks up any drift between the entities and your local
   database. Delete whatever does not belong to your change, and find out why it was there.
5. **`down()`** - keep it correct, but it is for local work only: the deploy never runs it.

A data change (`UPDATE`, `INSERT`) goes into a migration as plain SQL in `addSql()`. Never use
entities, repositories or services in a migration - they follow the current code, not the schema
at the time the migration runs. Make it idempotent (`UPDATE ... WHERE new_column IS NULL`), so a
rerun after a failure cannot double-apply it.

### 3. Test locally

On your local database:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```
```bash
php bin/console doctrine:schema:validate
```

`schema:validate` must say the mapping and the database are in sync. Then run the CI check
yourself:

```bash
php .github/scripts/check-destructive-migrations.php migrations
```

For anything beyond adding a column - a data change, a type change, a big table - also run the
migration against a **copy of production**. On the server:

```bash
sudo -u ferrio-deploy sh -c 'cd /opt/ferrio-api && mysqldump --defaults-extra-file=./backup.cnf --single-transaction --no-tablespaces <db> | gzip > /tmp/ferrio-copy.sql.gz'
```

On your machine:

```bash
scp -P <port> <admin>@<host>:/tmp/ferrio-copy.sql.gz .
```
```bash
mysql -uroot -proot -e "CREATE DATABASE ferrio_copy"
```
```bash
gunzip -c ferrio-copy.sql.gz | mysql -uroot -proot ferrio_copy
```
```bash
DATABASE_URL="mysql://root:root@localhost:3306/ferrio_copy?serverVersion=8.0.33" php bin/console doctrine:migrations:migrate --no-interaction
```

It shows what the real data does to the migration: rows that break a new `NOT NULL` or `UNIQUE`,
and how long a table rebuild takes. The copy holds personal data (reporters' UIDs and device data):
never commit it, and delete it afterwards - the file on both machines and the database:

```bash
mysql -uroot -proot -e "DROP DATABASE ferrio_copy"
```
```bash
rm ferrio-copy.sql.gz
```

And on the server: `rm /tmp/ferrio-copy.sql.gz`.

### 4. Before the click

1. CI is green on `main`, including `destructive-migrations`.
2. You know which phase this deploy is (single / A / B), and for B, that A has been in production
   long enough (at least one deploy, and no rollback planned past it).
3. A quiet hour (`/admin/stats`, by hour) for anything that rebuilds a table.
4. You know the rollback for this deploy: for an additive migration it is simply deploying the
   previous commit (see `DEPLOY.md`).

### 5. Deploy and watch

**Actions -> CI -> Run workflow** on `main`. In the deploy job's log, `deploy.sh` reports:

1. `Pending migrations - dumping <db> to backups/...` - the dump it will need if something breaks;
2. `Running migrations` and Doctrine's output;
3. `Release <sha> is live`.

Afterwards, on the server:

```bash
cd /opt/ferrio-api && docker compose exec php bin/console doctrine:migrations:status
```

"New" must be 0.

## When it fails

### The migration failed - production still runs the old release

`deploy.sh` stopped before `up -d`: the old code is serving, on whatever the migration managed to
change. The job is red.

1. Read the error in the deploy job's log.
2. See what was applied. Doctrine did not record the migration, so it looks pending:

   ```bash
   cd /opt/ferrio-api && docker compose exec php bin/console doctrine:migrations:status
   ```

   And compare the affected table with what the migration wanted:

   ```bash
   cd /opt/ferrio-api && mysql --defaults-extra-file=./backup.cnf <db> -e "SHOW CREATE TABLE <table>\G"
   ```
3. With one table per migration (rule 2) the failed statement was the only one, and nothing was
   applied - go to 4. Otherwise, undo the statements that did run, by hand, until the table matches
   its state before the migration. Do not leave the schema half applied: the fixed migration would
   then fail on the part that already exists.
4. Fix the failed migration in the repository and deploy again. Editing it is fine here: it never
   completed in production, so Doctrine has no record of it. (A migration that did complete is
   never edited - see the end of this guide.)

**Last resort - restore the dump** taken right before the migration, when the schema cannot be
repaired by hand. It loses everything written between the dump and the restore (reports, votes,
API traffic counters), so prefer the manual repair:

```bash
cd /opt/ferrio-api && gunzip -c backups/<newest file>.sql.gz | mysql --defaults-extra-file=./backup.cnf <db>
```

### The migration worked, the new release is broken

Deploy the previous commit (`DEPLOY.md`, "Rollback"). Because the migration was additive, the old
code runs on the new schema. Fix forward from there.

### The deploy job was red after `Release ... is live`

That is the smoke test. The new release is live; check `docker compose logs php` and treat it like
a broken release above.

## Catalog of changes

Phase A and phase B are two separate deploys. "Marker" means the migration carries a line

```php
// destructive-migration: <why it is safe now>
```

which lets it past the CI check. Only a phase B gets one, and its reason names phase A's commit.

### Add a table

One deploy. New code and the `CREATE TABLE` together - the old code does not know the table.

### Add a nullable column

One deploy. `ALTER TABLE t ADD c VARCHAR(255) DEFAULT NULL`. Instant in MySQL 8, no table rebuild.

### Add a NOT NULL column with a default

One deploy. `ALTER TABLE t ADD c INT DEFAULT 0 NOT NULL` - the old code's `INSERT`s, which do not
know the column, get the default. Put the default in the mapping as well
(`options: ['default' => 0]`), or the next `diff` wants to drop it.

### Add a NOT NULL column with no sensible default

Three deploys:

1. **A:** add the column nullable; code writes it on every insert and update.
2. **B:** a data migration fills the existing rows
   (`UPDATE t SET c = ... WHERE c IS NULL`); one deploy later, nothing writes `NULL` any more.
3. **C:** `ALTER TABLE t MODIFY c ... NOT NULL` and the mapping becomes non-nullable. Marker
   (`MODIFY`). Check on a production copy first that no `NULL` is left - the `MODIFY` fails on one.

### Rename a column

Three deploys - the rename itself never runs as `RENAME COLUMN`/`CHANGE`, because the old code
reads the old name:

1. **A:** add the new column (nullable); copy the data
   (`UPDATE t SET new_c = old_c WHERE new_c IS NULL`); the code reads the new column and writes
   **both**.
2. **B:** the code stops writing the old column and the mapping forgets it. If the old column is
   `NOT NULL` without a default, B also carries a migration making it nullable
   (`MODIFY old_c ... DEFAULT NULL`, marker "only relaxes a constraint") - it runs before the B code
   starts, so nothing ever inserts without it.
3. **C:** `ALTER TABLE t DROP old_c`. Marker.

Writing both in A is what makes a rollback from A safe: the previous release still finds current
data in the old column.

### Drop a column

Two deploys:

1. **A:** remove every use - code, mapping, queries (DQL and raw SQL), Twig. No migration. If the
   column is `NOT NULL` without a default, first make it nullable or give it a default (in A), or the
   A code's `INSERT`s fail.
2. **B:** `ALTER TABLE t DROP c`. Marker.

### Rename a table

As a column rename, one level up: A creates the new table, copies the rows and writes both; B stops
writing the old one; C drops it (marker). For a small, rarely written table the pragmatic way is a
short write freeze instead - but then it is a conscious exception, documented in the marker.

### Drop a table

Two deploys: A removes every use (entity, repository, queries), B runs `DROP TABLE` (marker).

### Change a column's type

1. **Widening** (`VARCHAR(100)` to `VARCHAR(255)`, `INT` to `BIGINT`): one deploy, the old code
   fits in the new type. It is still a `CHANGE`/`MODIFY`, so it needs the marker
   ("widening, old values fit"). Changing the type usually rebuilds the table, blocking writes for
   its duration - quiet hour, and measure on a production copy for a big table (`api_hit` grows
   by every distinct path every hour).
2. **Narrowing or changing meaning** (`VARCHAR` to `INT`, `TEXT` to `JSON`): treat it as a rename -
   a new column of the new type, a copy with conversion, both written, then drop the old one.

### Make a column nullable

One deploy, `MODIFY`, marker ("only relaxes a constraint"). Old code never writes `NULL`, so it is
unaffected.

### Add an index

One deploy. MySQL 8 builds it online (`ALGORITHM=INPLACE`, writes keep going). A `UNIQUE` index fails
when duplicates exist - check on a production copy first.

### Add a foreign key

One deploy, but check on a production copy that no existing row violates it; the `ALTER` fails on
the first orphan. Clean the orphans in a separate data migration that sorts before the one adding
the key - in the same deploy is fine, rule 2 is about one migration, not one deploy.

### Drop an index or a foreign key

One deploy. The checker does not flag these: dropping a constraint never breaks the old code.

## Things never to do

1. Edit a migration that has run in production. Doctrine will not run it again; the change silently
   never reaches production. Write a new migration.
2. Move `BASELINE` in the checker forward to get a migration past it. Use the marker - it leaves a
   reason in the history.
3. Run a migration on production by hand while a deploy may run. `deploy.sh` is the only path; the
   manual one skips the dump.
4. Put entities or services in a migration (see step 2).
