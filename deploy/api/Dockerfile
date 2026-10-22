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

# La API no tiene recursos de JavaScript que compilar: el frontend es una
# imagen aparte (deploy/web/Dockerfile, Nuxt). Esta etapa existe solo para
# traer el binario de Node que se copia a la etapa final. En un repo con
# `package.json` propio de la API, el `npm install` va aqui.

# --- Etapa 2: dependencias de PHP ----------------------------------------
FROM composer:2.9.4 AS vendor

WORKDIR /app

COPY api/composer.json ./

# En un proyecto real, este archivo tambien se copia (composer.lock) para que
# las versiones de PHP queden fijadas. Sin el lock, cada compilacion instala
# versiones distintas.
RUN --mount=type=cache,target=/root/.cache/composer composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY api/ ./

RUN composer dump-autoload --optimize --no-dev

# --- Etapa 3: PHP ----------------------------------------------------------
# =============================================================================
# CVE-2026-109618 / CVE-2026-109619 (PHP2Shell, 2026-10-22)
# =============================================================================
# La imagen se construye con PHP 8.3.36, que es la version PARCHEADA
# de la serie 8.3. Las versiones parcheadas de PHP son
# 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12.
#
# Verificado el 2026-10-22 en el servidor de desarrollo, con el tag exacto:
#
#     $ docker run --rm php:8.3.36-fpm-bookworm php -v
#     PHP 8.3.36 (cli) (built: Oct 21 2026 16:00:25)
#
# El pin sube de `php:8.3.29-fpm-bookworm` a `php:8.3.36-fpm-bookworm`.
# Este repo NO usa tags flotantes, y 8.3.29 esta por DEBAJO de la version
# parcheada: mientras el pin apuntaba ahi, la imagen llevaba PHP vulnerable.
#
# OJO: LA IMAGEN DESPLIEGADA HAY QUE RECONSTRUIRLA. El parche de PHP entra en
# el contenedor por la IMAGEN, no por este repositorio: mientras no se
# reconstruya y se redespliegue, el contenedor sigue con el PHP viejo. El
# endurecimiento de mas abajo (el ini y el pool de php-fpm) es la red de seguridad
# para ese mientras, y lo que sobrevive a un redeploy.
FROM php:8.3.36-fpm-bookworm AS php

# Extensiones que necesita Laravel con PostgreSQL.
RUN apt-get update \
    && apt-get upgrade -y \
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

# Endurecimiento PHP2Shell (CVE-2026-109618 / CVE-2026-109619):
# la via CONFIRMADA (session.upload_progress) apagada, y los
# trabajadores de php-fpm reciclados cada 100 peticiones.
COPY deploy/api/docker/99-php2shell-hardening.ini /usr/local/etc/php/conf.d/99-php2shell-hardening.ini
COPY deploy/api/docker/zz-php2shell-fpm.conf /usr/local/etc/php-fpm.d/zz-php2shell-hardening.conf
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