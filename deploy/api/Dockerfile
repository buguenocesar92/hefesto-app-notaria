# ---------------------------------------------------------------------------
# Imagen de la API (Laravel).
#
# VERSIONES FIJADAS
# -----------------
# Node, Composer y PHP van con version exacta, no con `latest`. Una imagen que
# se actualiza sola un miercoles a las 4 de la tarde es una forma de tener un
# incidente sin haber tocado nada.
# ---------------------------------------------------------------------------

# --- Etapa 1: dependencias ------------------------------------------------
FROM node:22.21.1-bookworm-slim AS deps

WORKDIR /app

RUN corepack enable

COPY package.json ./

# En un proyecto real, este archivo tambien se copia (package-lock.json) para
# que las versiones de JavaScript queden fijadas.
RUN --mount=type=cache,target=/root/.npm npm install --no-audit --no-fund

# --- Etapa 2: dependencias de PHP ----------------------------------------
FROM composer:2.9.4 AS vendor

WORKDIR /app

COPY composer.json ./

# En un proyecto real, este archivo tambien se copia (composer.lock) para que
# las versiones de PHP queden fijadas. Sin el lock, cada compilacion instala
# versiones distintas.
RUN --mount=type=cache,target=/root/.cache/composer composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev

# --- Etapa 3: PHP ----------------------------------------------------------
FROM php:8.3.29-fpm-bookworm AS php

# Extensiones que necesita Laravel con PostgreSQL.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        unzip \
        supervisor \
        nginx \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        opcache \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Node dentro de la imagen final: Laravel lo usa para compilar los recursos.
COPY --from=node:22.21.1-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22.21.1-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

COPY deploy/api/docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY deploy/api/docker/nginx.conf /etc/nginx/sites-available/default
COPY deploy/api/docker/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY deploy/api/docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

# Usuario sin privilegios: el proceso web no corre como root.
RUN groupadd -r hefesto && useradd -r -g hefesto -d /var/www/html hefesto

WORKDIR /var/www/html

COPY --from=vendor --chown=hefesto:hefesto /app /var/www/html

RUN mkdir -p \
        /var/www/html/storage/app/documentos \
        /var/www/html/storage/framework/cache \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/log/caddy \
    && chown -R hefesto:hefesto /var/www/html/storage /var/log/caddy

USER hefesto

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/app.conf"]