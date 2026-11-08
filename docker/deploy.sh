#!/usr/bin/env bash
# The deploy, see DEPLOY.md. It lives in /opt/docker-images/ferrio-api and is run by hand on the
# server, as root, with the commit SHA of a release CI has published - every CI run on main prints
# this command:
#   sudo /opt/docker-images/ferrio-api/deploy.sh <full commit sha>
# A rollback is the same command with an older SHA.
#
# This file must keep LF line endings (.gitattributes pins every file to LF): with CRLF, bash fails
# on the first line.

set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

tag="${1:-}"
if ! [[ "$tag" =~ ^[0-9a-f]{40}$ ]]; then
    echo "Expected a full 40-character commit SHA, got: '$tag'" >&2
    echo "Usage: sudo $0 <full commit sha> - the CI run of that commit prints the exact command." >&2
    exit 2
fi

# deploy.env holds DB_NAME (the database to dump before a migration) and optionally KEEP_BACKUPS.
# shellcheck source=/dev/null
source ./deploy.env
: "${DB_NAME:?DB_NAME is not set in deploy.env}"
keep_backups="${KEEP_BACKUPS:-10}"

export IMAGE_TAG="$tag"

echo "Pulling release $tag"
docker compose pull --quiet

# Everything up to `up -d` runs from the NEW image in a throwaway container, while the old release
# keeps serving. If any step fails, the script stops here and production is still the old release.
#
# up-to-date exits 1 when migrations are pending - but a console that cannot boot, or a database it
# cannot reach, exits 1 as well (or 126, 255, ...). So "pending" needs exit 1 AND Doctrine's own
# "Out-of-date!" line; anything else is an error and stops the deploy before the dump or a migration.
check_status=0
check_output="$(docker compose run --rm --no-deps -T php bin/console doctrine:migrations:up-to-date --no-interaction 2>&1)" || check_status=$?
if [ "$check_status" -eq 0 ]; then
    echo "No pending migrations"
elif [ "$check_status" -eq 1 ] && grep -q 'Out-of-date!' <<< "$check_output"; then
    printf '%s\n' "$check_output"
    # MySQL commits every DDL statement on its own, so a migration that fails half way cannot be
    # rolled back. The dump is the last resort for that case.
    mkdir -p backups
    dump="backups/$(date -u +%Y%m%dT%H%M%SZ)-${tag:0:8}.sql.gz"
    echo "Pending migrations - dumping $DB_NAME to $dump"
    # Triggers are dumped by default. --no-tablespaces spares the app user the PROCESS privilege.
    mysqldump --defaults-extra-file=./backup.cnf --single-transaction --no-tablespaces "$DB_NAME" | gzip > "$dump"

    echo "Running migrations"
    docker compose run --rm --no-deps -T php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

    # Names start with a UTC timestamp, so reverse name order is newest first.
    printf '%s\n' backups/*.sql.gz | sort -r | tail -n +"$((keep_backups + 1))" | xargs -r rm --
else
    printf '%s\n' "$check_output" >&2
    echo "Could not check for pending migrations (exit $check_status) - stopping. Nothing was dumped or migrated; production is still the old release." >&2
    exit 1
fi

echo "Starting release $tag"
docker compose up -d --remove-orphans

# Only now is the release live, so only now does it become what a plain `docker compose up -d` on
# this box starts.
printf 'IMAGE_TAG=%s\n' "$tag" > .env

# Old releases of this project only; images of other stacks on the box are left alone. A week of
# them stays local for a quick rollback - anything older is pulled from GHCR again.
docker image prune -af --filter "label=eu.andret.project=ferrio-api" --filter "until=168h" > /dev/null

echo "Release $tag is live"
