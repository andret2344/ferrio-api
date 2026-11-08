#!/usr/bin/env bash
# Server side of the deploy, see DEPLOY.md. It lives in /opt/ferrio-api and is the forced command of
# the CI deploy key in authorized_keys, so that key can run this script and nothing else - the
# deploy user is in the docker group, which is as good as root. CI sends the commit SHA as the SSH
# command; it arrives here as SSH_ORIGINAL_COMMAND and is the only input this script accepts.
#
# For a manual deploy or a rollback, run it yourself on the server:
#   SSH_ORIGINAL_COMMAND=<full commit sha> /opt/ferrio-api/deploy.sh
#
# This file must keep LF line endings (see .gitattributes): with CRLF, bash fails on the first line.

set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

tag="${SSH_ORIGINAL_COMMAND:-}"
if ! [[ "$tag" =~ ^[0-9a-f]{40}$ ]]; then
    echo "Expected a full 40-character commit SHA, got: '$tag'" >&2
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
if docker compose run --rm --no-deps -T php bin/console doctrine:migrations:up-to-date --no-interaction; then
    echo "No pending migrations"
else
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
