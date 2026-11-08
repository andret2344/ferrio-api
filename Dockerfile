# Production image of ferrio-api, see DEPLOY.md. Three stages: the Encore assets are built in Node,
# the php stage is the app itself (php-fpm), and the web stage is nginx serving the php stage's
# public/ - copied from it, so the two can never drift apart. CI pushes the php and web stages to
# GHCR as two images tagged with the commit SHA; the server only ever pulls them.

FROM node:26-alpine AS assets

WORKDIR /app

# Node images ship without yarn since Node 25 (corepack was dropped), so it comes from npm - the
# same Yarn Classic that reads yarn.lock (v1) on a developer machine, pinned so it never drifts.
RUN npm install -g yarn@1.22.22

COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile --non-interactive

COPY webpack.config.mjs tsconfig.json ./
COPY assets ./assets
RUN yarn build


FROM php:8.5-fpm AS php

LABEL eu.andret.project="ferrio-api"

# ext-calendar is what easter_days() needs (the Easter-based floating holidays), ext-intl the ICU
# display names on the admin charts. Everything else composer requires ships with the base image.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev unzip \
    && docker-php-ext-install -j"$(nproc)" calendar intl pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/zz-ferrio.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Set before composer install: its cache:clear boots the kernel, and the committed .env says
# APP_ENV=dev, whose bundles (web profiler, maker) --no-dev deliberately did not install.
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Dependencies first, for layer caching. --no-scripts because the Symfony scripts need the
# application code, which is not copied yet; the second install below runs them.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --no-progress

COPY . .
# The repository is committed from Windows (core.fileMode=false), so git never records the
# executable bit and bin/console arrives as 0644 - `docker compose run php bin/console` would then
# fail with "Permission denied". Set it here rather than trusting the file mode in git.
RUN chmod +x bin/console
COPY --from=assets /app/public/build ./public/build
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --classmap-authoritative

# var/sessions and var/log are mounted as named volumes in compose.yaml. A fresh named volume is
# seeded from the image directory, ownership included, so they have to exist here, owned by
# www-data, or PHP could not write a session file into the empty volume.
RUN mkdir -p var/sessions var/log && chown -R www-data:www-data var

USER www-data

CMD ["php-fpm"]


# Unprivileged nginx: runs as uid 101 and listens on 8080 instead of 80, so a compromise of nginx
# never yields root in the container. The public edge is Apache on the host, which reaches this only
# over the loopback port published in compose.yaml.
FROM nginxinc/nginx-unprivileged:1.30-alpine AS web

LABEL eu.andret.project="ferrio-api"

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=php /app/public /app/public
