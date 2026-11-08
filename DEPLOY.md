# Deploying ferrio-api

Production is a Docker Compose stack on the VPS: php-fpm plus nginx, both pulled from GHCR. Host
Apache terminates TLS and reverse-proxies to nginx on `127.0.0.1:8081`. MySQL stays on the host,
reached through its unix socket. Nothing is built on the server.

A deploy is one click: **Actions -> CI -> Run workflow** on `main`. That run:

1. runs every check (PHPUnit, frozen contract fixtures, destructive migrations) - a red one stops it;
2. builds both images and pushes them to GHCR, tagged with the commit SHA;
3. connects over SSH with a key whose only allowed command is `deploy.sh`, handing it the SHA;
4. requests the login page through the public URL as a smoke test.

`deploy.sh` on the server pulls the new images and, from the new image while the old release keeps
serving, checks for pending migrations. If there are any, it dumps the database and runs them. Only
then does it start the new release. If anything before that fails, production stays on the old
release.

Pushes and pull requests never deploy.

Related documents:

1. [`docs/database-migrations.md`](docs/database-migrations.md) - how to write and ship a database
   migration safely. Read it before any change to an entity's mapping.
2. [`docs/docker-cutover.md`](docs/docker-cutover.md) - the one-time switch of production to this
   stack, which is also the full server setup from scratch.

## Rollback

On the server, deploy an older commit (full SHA) by hand:

```bash
sudo -u ferrio-deploy env SSH_ORIGINAL_COMMAND=<full commit sha> /opt/ferrio-api/deploy.sh
```

Images of the last week are still local; older ones are pulled from GHCR again. A rollback never
undoes a migration - see the migrations guide for why that is safe.

## Operations

All commands from `/opt/ferrio-api`.

```bash
docker compose logs -f php
```
```bash
docker compose logs -f nginx
```
```bash
docker compose exec php tail -n 100 var/log/reports.log
```
```bash
docker compose exec php bin/console doctrine:migrations:status
```

`compose.yaml` and `deploy.sh` are **not** shipped by a deploy. When either changes in the repo,
copy it to the server again ("Files on the server" in the cutover guide).
