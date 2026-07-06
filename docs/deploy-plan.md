# Deploy Plan

**Status:** Designed 2026-06-28, **not yet implemented.** Scaffold reviewed; waiting on server
prep + GitHub secrets before the `deploy` job is added to `ci.yml`.
**Owner:** Andret2344.

## Goal & constraints

Automate deployment of this Symfony backend to the VPS from CI, so that the **test step gates the
deploy** — a red build (PHPUnit, contract tests, `contract-immutability`) must stop it. Today
deploys are manual via WinSCP (SFTP).

- Single VPS, **no containers**, Apache serves the app, PHP 8.5, MySQL.
- Full SSH shell available on the VPS.
- **Migrations stay manual** — the deploy never touches the DB (safe for destructive migrations).
- Chosen approach: **lean rsync-over-SSH from GitHub Actions** with **atomic release + symlink**
  layout. (Deployer was considered for one-command rollback but we went lean; the symlink layout
  still gives instant rollback by repointing `current`.)

## Gating

The `deploy` job lives in the **same workflow** as `test` (so `needs:` can reference it) and runs
only on a green push to `main`:

```yaml
needs: [ test, contract-immutability ]
if: github.event_name == 'push' && github.ref == 'refs/heads/main'
```

The release artifact is built **in CI** (`composer install --no-dev --optimize-autoloader` +
`yarn encore production`), so the server needs neither Composer nor Node.

## Server layout (atomic)

```
<DEPLOY_PATH>/                       e.g. /var/www/ferrio-api
├── releases/<ts>-<sha8>/            one dir per deploy; keep last 5
├── shared/
│   ├── .env.local                   secrets — never overwritten
│   └── var/log/                     logs survive deploys
└── current -> releases/<latest>     Apache DocumentRoot = current/public
```

Apache points at `current/public`; a deploy only swaps the `current` symlink. Rollback = repoint
`current` at a previous release.

## Gotchas the deploy must handle

- **OPcache** — after swapping files, PHP keeps compiled bytecode cached; without a php-fpm reload
  (or `apachectl graceful`) the server keeps serving the old code.
- **Atomicity** — never rsync into the live docroot (window of half-updated files). Build a fresh
  release dir, then flip the symlink atomically (`ln -sfn … current_tmp && mv -Tf current_tmp current`).
- **Shared files** — `.env.local`, `var/log/` live outside releases and are symlinked in; never
  overwritten by rsync.

## Scaffold (`deploy` job for `ci.yml`)

```yaml
  deploy:
    name: Deploy to VPS
    needs: [ test, contract-immutability ]
    if: github.event_name == 'push' && github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    concurrency:
      group: production-deploy      # two deploys can't race
      cancel-in-progress: false
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: intl
          coverage: none

      - name: Install PHP deps (prod)
        run: composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
        env:
          APP_ENV: prod

      - name: Setup Node + build assets
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: yarn
      - run: |
          yarn install --frozen-lockfile
          yarn encore production

      - name: Prepare SSH
        run: |
          mkdir -p ~/.ssh && chmod 700 ~/.ssh
          echo "${{ secrets.DEPLOY_SSH_KEY }}" > ~/.ssh/id_deploy
          chmod 600 ~/.ssh/id_deploy
          ssh-keyscan -H "${{ secrets.DEPLOY_HOST }}" >> ~/.ssh/known_hosts 2>/dev/null

      - name: Upload release
        run: |
          RELEASE="$(date +%Y%m%d-%H%M%S)-${GITHUB_SHA::8}"
          echo "RELEASE=$RELEASE" >> "$GITHUB_ENV"
          rsync -az -e "ssh -i ~/.ssh/id_deploy" \
            --link-dest="${{ secrets.DEPLOY_PATH }}/current/" \
            --exclude='.git' --exclude='.github' --exclude='node_modules' \
            --exclude='tests' --exclude='var' --exclude='.env.local' \
            ./ "${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_HOST }}:${{ secrets.DEPLOY_PATH }}/releases/$RELEASE/"

      - name: Activate release
        run: |
          ssh -i ~/.ssh/id_deploy "${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_HOST }}" \
            "DEPLOY_PATH='${{ secrets.DEPLOY_PATH }}' RELEASE='$RELEASE' bash -s" <<'EOSSH'
          set -euo pipefail
          cd "$DEPLOY_PATH"
          rel="releases/$RELEASE"

          ln -sfn "$DEPLOY_PATH/shared/.env.local" "$rel/.env.local"
          mkdir -p "$rel/var"
          ln -sfn "$DEPLOY_PATH/shared/var/log" "$rel/var/log"

          php "$rel/bin/console" cache:clear  --env=prod --no-debug
          php "$rel/bin/console" cache:warmup --env=prod --no-debug

          ln -sfn "$rel" current_tmp && mv -Tf current_tmp current   # atomic flip

          # reset OPcache: reload fpm if present, else graceful apache
          svc=$(systemctl list-units --type=service --all 2>/dev/null | grep -Eo 'php[0-9.]+-fpm' | head -1 || true)
          if [ -n "$svc" ]; then sudo systemctl reload "$svc"; else sudo apachectl graceful; fi

          ls -1dt releases/*/ | tail -n +6 | xargs -r rm -rf          # keep last 5
          EOSSH
```

`--link-dest` hardlinks unchanged files from the live release, so subsequent deploys transfer only
deltas (vendor isn't re-sent every time).

## One-time setup (prerequisites)

1. **GitHub Actions secrets** (Settings → Secrets and variables → Actions):
   `DEPLOY_SSH_KEY` (a dedicated deploy key, not a personal one), `DEPLOY_HOST`, `DEPLOY_USER`,
   `DEPLOY_PATH` (e.g. `/var/www/ferrio-api`).
2. **Server dirs + secrets:**
   ```bash
   sudo mkdir -p /var/www/ferrio-api/{releases,shared/var/log}
   sudo mv <current-project>/.env.local /var/www/ferrio-api/shared/.env.local
   echo "ssh-ed25519 AAAA... deploy" >> ~/.ssh/authorized_keys
   ```
3. **Passwordless sudo for the reload** — first find the runtime:
   ```bash
   apachectl -M 2>/dev/null | grep -i php          # 'php_module' => mod_php
   systemctl list-units --type=service | grep fpm  # 'phpX.Y-fpm' => FPM
   ```
   Then `sudo visudo` (substitute the real service):
   ```
   deployuser ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.5-fpm
   # or, for mod_php:
   deployuser ALL=(root) NOPASSWD: /usr/sbin/apachectl graceful
   ```
4. **First-run order:** do the prep, run the first deploy (creates `releases/x` and `current`),
   **then** point the Apache vhost `DocumentRoot` at `/var/www/ferrio-api/current/public` and
   `apachectl graceful`. (`current` doesn't exist until the first deploy.)

## Migrations (manual, by design)

The deploy does not run migrations. When one is pending, run it consciously after deploy:

```bash
ssh deployuser@host 'cd /var/www/ferrio-api/current && php bin/console doctrine:migrations:migrate --no-interaction'
```

## Open items

- [ ] Determine FPM vs mod_php (decides the sudoers line; the activate script auto-detects at run time).
- [ ] Create the four GitHub secrets.
- [ ] Server prep (dirs, move `.env.local`, deploy key, sudoers).
- [ ] Add the `deploy` job to `ci.yml` (CRLF, repo convention) — deferred until the above is done,
      to avoid red builds on every push to `main`.
- [ ] First deploy, then switch the Apache `DocumentRoot` to `current/public`.
