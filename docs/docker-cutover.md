# Switching production to Docker

A runbook for the one-time move of production from mod_php (files uploaded with WinSCP) to the
Docker stack described in [`DEPLOY.md`](../DEPLOY.md). Follow it top to bottom; every part ends with a
check, and nothing goes on until the check passes. Parts 3 to 5 are also the full server setup from
scratch, should the VPS ever be rebuilt.

**Why it is safe:** the old site keeps serving until part 7, and the old and the new stack share the
same database. Until the very end the rollback is putting a few Apache lines back, with no data lost
- as long as no migration runs during the switch (part 2 makes sure of that).

Placeholders used below:

- `<old dir>` - the directory the current site is served from (the parent of its `public/`);
- `<api host>` - the public host name of the API;
- `<db>`, `<db user>` - the production database and the user the app connects as;
- `<admin>` - your own login on the VPS; `<host>`, `<port>` - the VPS and its SSH port.

Commands marked "on the server" run there; the rest run on your machine, in Git Bash, from the
repository root.

## Part 1 - Before you start

1. Pick a quiet hour. `/admin/stats` (by hour, last 7 days) shows when traffic is lowest. Budget an
   hour; the actual switch (part 7) takes seconds.
2. The deploy code must be on `main` and green in CI, and nothing else unfinished may ride along -
   the first deploy ships whatever `main` holds. Commit and push the deploy changes on their own,
   keep unrelated work-in-progress out of that push.
3. Find `<old dir>` (on the server):

   ```bash
   grep -ri "DocumentRoot" /etc/apache2/sites-enabled/
   ```

   The vhost serving `<api host>` points at `<old dir>/public`. Note which file it is - with certbot
   the `*:443` vhost usually lives in a separate `...-le-ssl.conf`.

## Part 2 - Inventory of the old setup

All on the server.

1. **`APP_SECRET`.** The committed `.env` holds a value, and the repository is public:

   ```bash
   grep -H "^APP_SECRET" <old dir>/.env <old dir>/.env.local
   ```

   If `.env.local` has no `APP_SECRET` line, or the same value as `.env`, production signs its
   remember-me cookies with a public secret. That alone does not let anyone in - the signature also
   covers the admin's password hash - but anyone who ever gets a copy of the database can then forge
   an admin login without cracking a single password. Then you generate a new one in part 5, and the
   switch fixes that hole (it logs you out once). If `.env.local` has its
   own value, keep it in part 5, so nobody gets logged out.
   Also check that no `.env.local.php` exists (`ls <old dir>/.env*`): if it does, it overrides
   both files and is the real source of the values.
2. **All other values.** Print the current overrides, you will carry them over in part 5:

   ```bash
   cat <old dir>/.env.local
   ```

   Pay attention to `FIREBASE_CREDENTIALS`: is it base64, the JSON itself, or a file path? The first
   two go into `secrets.env` as they are; only a path needs part 5, step 2.
3. **Pending migrations.** Production must be fully migrated before the switch, so that the first
   Docker deploy runs no migration:

   ```bash
   cd <old dir> && php bin/console doctrine:migrations:status
   ```

   "New" must be 0. If it is not, either migrate the old setup now, by hand, the way you always did,
   or make sure those migrations follow [`database-migrations.md`](database-migrations.md) - the
   first deploy would run them.
4. **Database server and version** - `serverVersion` in the new `DATABASE_URL` must match:

   ```bash
   mysql --version
   ```

   MySQL 8.0.x gives `serverVersion=8.0.x`; MariaDB gives `serverVersion=mariadb-10.11.x` (its
   real version).
5. **Database user host.** Through the socket the app user is matched as `'<db user>'@'localhost'`:

   ```bash
   sudo mysql -e "SELECT user, host FROM mysql.user WHERE user = '<db user>'"
   ```

   With `localhost` or `%` in the list you are done. With only `127.0.0.1` (a TCP-only user), add a
   `localhost` twin with the same password and grants:

   ```bash
   sudo mysql -e "CREATE USER '<db user>'@'localhost' IDENTIFIED BY '<password>'; GRANT ALL PRIVILEGES ON <db>.* TO '<db user>'@'localhost';"
   ```
6. **Socket path:**

   ```bash
   ls -l /var/run/mysqld/mysqld.sock
   ```

   If it lives elsewhere, change the mount in `compose.yaml` and the `unix_socket` in part 5.
7. **Docker and Compose** - Compose must be v2.24 or newer:

   ```bash
   docker compose version
   ```

## Part 3 - Safety copy

On the server, a full dump and a copy of the old configuration, kept until part 9:

```bash
sudo install -d -m 700 /root/ferrio-pre-docker
```
```bash
sudo sh -c 'mysqldump --single-transaction --no-tablespaces <db> | gzip > /root/ferrio-pre-docker/db.sql.gz'
```
```bash
sudo cp <old dir>/.env.local /etc/apache2/sites-available/<vhost file> /root/ferrio-pre-docker/
```

Check: `sudo ls -l /root/ferrio-pre-docker` lists three files, and the dump is not a few bytes.

## Part 4 - Directory and files

On the server:

```bash
sudo install -d -m 755 /opt/docker-images/ferrio-api
```

Everything in it belongs to root, and `deploy.sh` runs as root (`sudo`): starting containers takes
root's power anyway, so a separate deploy user would add nothing.

### Files on the server

From your machine:

```bash
scp -P <port> compose.yaml docker/deploy.sh <admin>@<host>:/tmp/
```

On the server:

```bash
sudo install -m 644 /tmp/compose.yaml /opt/docker-images/ferrio-api/compose.yaml
```
```bash
sudo install -m 755 /tmp/deploy.sh /opt/docker-images/ferrio-api/deploy.sh
```

Check: `file /opt/docker-images/ferrio-api/deploy.sh` must not say "CRLF" - bash cannot run it then.

Repeat this subsection whenever `compose.yaml` or `docker/deploy.sh` changes in the repository.

## Part 5 - Secrets

On the server.

1. `secrets.env` - start from `secrets.env.dist` in the repository and fill it with the values from
   part 2:

   ```bash
   sudo vim /opt/docker-images/ferrio-api/secrets.env
   ```

   - `APP_SECRET`: the old one, or a new one if part 2 found it public. A new one:
     `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.
   - `DATABASE_URL`: the socket form from the dist file, with the `serverVersion` from part 2.
   - Every other value as in the old `.env.local`.
   - Do not carry over `HTTP_BASIC_AUTH_USERNAME` / `HTTP_BASIC_AUTH_PASSWORD` - nothing reads them
     any more.
2. Firebase - only when `FIREBASE_CREDENTIALS` was a file path. Copy the file and let the container
   user (uid 33, `www-data`) read it, nobody else:

   ```bash
   sudo install -o root -g 33 -m 640 <path from .env.local> /opt/docker-images/ferrio-api/firebase.json
   ```

   Uncomment the `firebase.json` volume in `/opt/docker-images/ferrio-api/compose.yaml` and set
   `FIREBASE_CREDENTIALS=/run/secrets/firebase.json` in `secrets.env`.
3. `deploy.env` - what `deploy.sh` needs to make its dump:

   ```bash
   sudo vim /opt/docker-images/ferrio-api/deploy.env
   ```

   ```bash
   DB_NAME=<db>
   KEEP_BACKUPS=10
   ```
4. `backup.cnf` - credentials for `mysqldump` and for a restore:

   ```bash
   sudo vim /opt/docker-images/ferrio-api/backup.cnf
   ```

   ```ini
   [mysqldump]
   user=<db user>
   password=<password>

   [mysql]
   user=<db user>
   password=<password>
   ```
5. Lock them down:

   ```bash
   sudo chmod 600 /opt/docker-images/ferrio-api/secrets.env /opt/docker-images/ferrio-api/backup.cnf
   ```

Check that the dump works:

```bash
sudo sh -c 'cd /opt/docker-images/ferrio-api && mysqldump --defaults-extra-file=./backup.cnf --single-transaction --no-tablespaces <db> | wc -c'
```

A large number and no error.

## Part 6 - First deploy, next to the old site

1. The push of the deploy code to `main` already built the release: once its CI run is green, the
   `image` job has published `ferrio-api-php` and `ferrio-api-nginx`, and the run's summary page
   shows the deploy command.
2. GHCR creates new packages as **private**, so the server could not pull them. Make both public
   (GitHub -> your profile -> Packages -> each package -> Package settings -> Change visibility ->
   Public). The server needs no registry login after that.
3. On the server, run the command from the summary page:

   ```bash
   sudo /opt/docker-images/ferrio-api/deploy.sh <full commit sha>
   ```

   It must say `No pending migrations` (part 2 made sure of it) and end with
   `Release <sha> is live`.

The stack now runs on `127.0.0.1:8081`, while Apache still serves the old site. Check the new stack
directly, on the server:

```bash
curl -sS -H "Host: <api host>" -H "X-Forwarded-Proto: https" -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:8081/v3/holidays?lang=pl"
```
```bash
curl -sS -H "Host: <api host>" -H "X-Forwarded-Proto: https" -D - -o /dev/null "http://127.0.0.1:8081/admin"
```

Expected: `200`, then a `302` whose `Location` is `https://<api host>/` - an `http://` there means
the proxy headers are not trusted. Compare the holiday list with the live one:

```bash
diff <(curl -sS "https://<api host>/v3/holidays?lang=pl") <(curl -sS -H "Host: <api host>" -H "X-Forwarded-Proto: https" "http://127.0.0.1:8081/v3/holidays?lang=pl")
```

No output means identical. Then the admin panel, through an SSH tunnel from your machine:

```bash
ssh -p <port> -L 8081:127.0.0.1:8081 <admin>@<host>
```

Open `http://localhost:8081/`, log in, open a holiday, check `/admin/stats`. If something fails, read
`docker compose logs php` on the server. Nothing so far has touched the live site.

## Part 7 - The switch

On the server.

```bash
sudo a2enmod proxy proxy_http headers
```

Edit the `*:443` vhost of `<api host>` (the file from part 1):

```bash
sudo vim /etc/apache2/sites-available/<vhost file>
```

Remove `DocumentRoot` and its `<Directory>` block, keep everything else (`ServerName`, the TLS
lines, the logs), and add:

```apache
ProxyPreserveHost On
ProxyPass        / http://127.0.0.1:8081/
ProxyPassReverse / http://127.0.0.1:8081/
RequestHeader set X-Forwarded-Proto "https"
```

`ProxyPreserveHost` keeps the public host name in generated URLs; `X-Forwarded-Proto` is what makes
Symfony see the request as HTTPS, so login redirects stay on https and the session cookie keeps its
Secure flag. Leave the `*:80` vhost alone - it only redirects, and certbot renewals go through it.

```bash
sudo apache2ctl configtest
```
```bash
sudo systemctl reload apache2
```

## Part 8 - Verify

1. Live traffic reaches the stack - the apps' requests appear within a minute:

   ```bash
   cd /opt/docker-images/ferrio-api && docker compose logs -f nginx
   ```
2. The public API answers:

   ```bash
   curl -sS -o /dev/null -w "%{http_code}\n" "https://<api host>/v3/holidays?lang=pl"
   ```
3. Log in to `https://<api host>/` (once more, if part 2 changed `APP_SECRET`). Open the dashboard,
   a holiday, the reports.
4. No new errors: `docker compose logs php` and `sudo tail -n 50 /var/log/apache2/error.log`.
5. An hour later `/admin/stats` (by hour) shows traffic in the hours since the switch - the counter
   runs inside the new stack, so that proves the apps reach it.

**Rollback, if anything here is wrong:** put back the vhost from the safety copy and reload. The old
site and the database are untouched, so nothing is lost.

```bash
sudo cp /root/ferrio-pre-docker/<vhost file> /etc/apache2/sites-available/<vhost file>
```
```bash
sudo systemctl reload apache2
```

## Part 9 - Cleanup, a week later

Only after a week without a rollback.

1. If the old `reports.log` has history you want to keep, carry it into the stack first:

   ```bash
   cd /opt/docker-images/ferrio-api && docker compose cp <old dir>/var/log/reports.log php:/app/var/log/reports-pre-docker.log
   ```
2. Archive and remove the old site:

   ```bash
   sudo tar czf /root/ferrio-pre-docker/old-site.tar.gz -C <old dir> .
   ```
   ```bash
   sudo rm -rf <old dir>
   ```
3. mod_php: if no other vhost runs PHP through Apache, disable it (`apachectl -M | grep php` shows
   the module, `sudo a2dismod php8.5`, then reload). With another PHP site on the box, leave it.
4. Delete the WinSCP session for this server - from now on the only way code reaches production is
   the deploy button.
5. Drop `HTTP_BASIC_AUTH_*` wherever they still exist outside the repo.
6. Once you trust the setup, delete `/root/ferrio-pre-docker` (it holds a full database dump with
   personal data - the reporters' UIDs and device data; do not keep it forever).
