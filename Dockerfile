# =============================================================================
# ParkHub PHP — SOTA-2026 Wolfi runtime, multi-stage build
#
# Runtime stage uses Chainguard Wolfi base instead of Debian. This:
#   - eliminates libc6 CVE-2026-5450 (Critical, won't-fix on Debian Bookworm)
#   - drops the broader Debian package surface that grype/trivy flags
#   - aligns with CLAUDE.md's "Wolfi base only for service runtime images"
#
# Frontend stage keeps node:22-slim (Debian-slim Node toolchain), but pulled
# from the internal registry mirror at 192.168.178.250:5000 — never Docker Hub
# (CLAUDE.md: NEVER pull from Docker Hub in builds — 429 risk).
#
# Vendor stage uses wolfi-base + apk-installed php-8.4 + composer + git.
# composer:2 image is no longer needed; composer ships in Wolfi.
# =============================================================================

# ---------------------------------------------------------------------------
# Stage 1: Frontend build (Astro + Vite)
# Mirrored node:22-slim — pinned to the registry digest, NOT Docker Hub.
#
# NODE_BASE / WOLFI_BASE are parameterized so cloud CI (GitHub Actions) can
# pass --build-arg NODE_BASE=docker.io/library/node:22-slim@sha256:868499d5...
# and --build-arg WOLFI_BASE=cgr.dev/chainguard/wolfi-base@sha256:4973aa3c2ccbe13fe2049aab539b0ab342ec584bd5b54a269d55d4891091c639 while
# local + gitea-runner builds default to the LAN mirror. Same images either
# way, just different ingress to them.
# ---------------------------------------------------------------------------
ARG NODE_BASE=192.168.178.250:5000/node:22-slim@sha256:868499d55378719bffa87b0ed1f099591823c029b543043c09c2483468e93201
ARG WOLFI_BASE=192.168.178.250:5000/wolfi-base@sha256:4973aa3c2ccbe13fe2049aab539b0ab342ec584bd5b54a269d55d4891091c639

FROM ${NODE_BASE} AS frontend
WORKDIR /app
COPY parkhub-web/package*.json ./
RUN npm ci
COPY parkhub-web/ ./
RUN DOCKER=1 npm run build

# ---------------------------------------------------------------------------
# Stage 2: Composer dependency install (no dev deps)
# Wolfi base + apk-installed php-8.4 + composer + git — replaces composer:2
# Docker Hub image. Smaller surface, no Hub pull.
# ---------------------------------------------------------------------------
FROM ${WOLFI_BASE} AS vendor
# Vendor stage runs `composer install` + `composer dump-autoload`. The latter
# triggers Laravel's `package:discover` post-autoload-dump script, which
# touches the DB layer (PDO). Composer's Wolfi package depends on virtual PHP
# extension packages, so pin every provider to php-8.4 to avoid mixing modules
# from the default PHP stream.
# `apk upgrade` first to align with current Wolfi repo (mirrored base may
# lag); keeps vendor layer's transitive libs scan-clean too.
RUN apk update && apk upgrade --no-cache --available && apk add --no-cache \
        bash \
        ca-certificates \
        composer \
        git \
        php-8.4 \
        php-8.4-ctype \
        php-8.4-curl \
        php-8.4-dom \
        php-8.4-fileinfo \
        php-8.4-iconv \
        php-8.4-mbstring \
        php-8.4-openssl \
        php-8.4-pdo \
        php-8.4-pdo_sqlite \
        php-8.4-phar \
        php-8.4-simplexml \
        php-8.4-xml \
        php-8.4-zip
WORKDIR /app
COPY composer.json composer.lock ./
# `--no-scripts` here too — keep all Laravel post-install scripts off until
# runtime where the full app + DB env is present. Also `--ignore-platform-reqs`
# defensively so any extension introduced by a transient upstream package
# update doesn't break the build (vendor stage doesn't ship to runtime).
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
# Copy full app for autoload generation.
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---------------------------------------------------------------------------
# Stage 3: Runtime — Wolfi + apache2 + php-8.4-apache + extensions
# Replaces docker.io/library/php:8.4-apache (Debian Bookworm). Closes
# libc6 CVE-2026-5450 because Wolfi tracks current upstream and is
# scanned daily by Chainguard.
# ---------------------------------------------------------------------------
FROM ${WOLFI_BASE} AS runtime

# Single apk layer. `apk upgrade --no-cache` first to pull current glibc/etc.
# (mirrored wolfi-base digest can lag the apk repo by a release; without
# upgrade, grype flags glibc 2.43-r6 → 2.43-r7 CVE-2026-5450/-5928).
RUN apk update && apk upgrade --no-cache --available && apk add --no-cache \
        apache2 \
        apache2-config \
        apache2-utils \
        bash \
        ca-certificates \
        gosu \
        php-8.4 \
        php-8.4-apache \
        php-8.4-bcmath \
        php-8.4-ctype \
        php-8.4-curl \
        php-8.4-dom \
        php-8.4-fileinfo \
        php-8.4-gd \
        php-8.4-iconv \
        php-8.4-mbstring \
        php-8.4-opcache \
        php-8.4-openssl \
        php-8.4-pdo \
        php-8.4-mysqlnd \
        php-8.4-pdo_mysql \
        php-8.4-pdo_pgsql \
        php-8.4-pdo_sqlite \
        php-8.4-pgsql \
        php-8.4-phar \
        php-8.4-simplexml \
php-8.4-xml \
        php-8.4-zip \
        sqlite-libs \
        tini \
        wget

# Drop-in compat with existing entrypoint (which expects www-data:33).
# Wolfi defaults to apache:apache; map www-data to UID 33 explicitly to
# match historical Debian image + entrypoint chown commands.
RUN if ! getent group www-data >/dev/null 2>&1; then addgroup -g 33 -S www-data; fi \
    && if ! getent passwd www-data >/dev/null 2>&1; then \
        adduser -u 33 -D -S -G www-data -h /var/www -s /sbin/nologin www-data; \
    fi \
    && mkdir -p /var/www/html /var/log/apache2 /var/run/apache2 \
    && chown -R www-data:www-data /var/www /var/log/apache2 /var/run/apache2

# PHP production hardening + OPcache tuning.
RUN mkdir -p /etc/php/conf.d \
    && { \
        echo "expose_php = Off"; \
        echo "opcache.enable=1"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=10000"; \
        echo "opcache.validate_timestamps=0"; \
        echo "opcache.jit=on"; \
        echo "opcache.jit_buffer_size=64M"; \
        echo "realpath_cache_size=4096K"; \
        echo "realpath_cache_ttl=600"; \
    } > /etc/php/conf.d/zz-production.ini

# Apache config — append app overlay; upstream httpd.conf provides MPM + base.
RUN mkdir -p /etc/apache2/conf.d \
    && { \
        echo "ServerTokens Prod"; \
        echo "ServerSignature Off"; \
        echo "TraceEnable Off"; \
        echo "Listen 10000"; \
        echo "LoadModule rewrite_module /usr/lib/apache2/modules/mod_rewrite.so"; \
        echo "LoadModule headers_module /usr/lib/apache2/modules/mod_headers.so"; \
        echo "LoadModule deflate_module /usr/lib/apache2/modules/mod_deflate.so"; \
        echo "LoadModule expires_module /usr/lib/apache2/modules/mod_expires.so"; \
        echo "LoadModule mime_module /usr/lib/apache2/modules/mod_mime.so"; \
        echo "LoadModule dir_module /usr/lib/apache2/modules/mod_dir.so"; \
        echo "LoadModule env_module /usr/lib/apache2/modules/mod_env.so"; \
        echo ""; \
        echo "ServerName parkhub.local"; \
        echo "DocumentRoot /var/www/html/public"; \
        echo "DirectoryIndex index.php index.html"; \
        echo ""; \
        echo "<Directory /var/www/html/public>"; \
        echo "    Options FollowSymLinks"; \
        echo "    AllowOverride All"; \
        echo "    Require all granted"; \
        echo "</Directory>"; \
        echo ""; \
        echo "ErrorLog /dev/stderr"; \
        echo "CustomLog /dev/stdout combined"; \
    } > /etc/apache2/conf.d/zz-parkhub.conf

# Wolfi's stock httpd.conf does not auto-include `conf.d/*.conf` (unlike
# Debian) and does not load mod_php — the php-8.4-apache package only ships
# `modules/libphp.so` plus `extra/php_module.conf`, leaving wiring to the
# operator. Without these three lines the runtime serves zero PHP: Apache
# starts cleanly, but every request to /index.php returns raw source (or
# 403/404 depending on handler order) and the /api/v1/health/live probe
# never goes green.
RUN { \
        echo ""; \
        echo "# parkhub wiring — load mod_php and pull in the conf.d overlay."; \
        echo "LoadModule php_module /usr/lib/apache2/modules/libphp.so"; \
        echo "Include /etc/apache2/extra/php_module.conf"; \
        echo "Include /etc/apache2/conf.d/*.conf"; \
    } >> /etc/apache2/httpd.conf

WORKDIR /var/www/html

# Copy application code (without vendor/ and node_modules/ per .dockerignore).
COPY --chown=www-data:www-data . .

# Copy composer vendor + built Astro frontend assets.
COPY --chown=www-data:www-data --from=vendor /app/vendor/ ./vendor/
COPY --chown=www-data:www-data --from=frontend /app/dist/ ./public/

# Remove installer (must not be reachable in production).
RUN rm -f /var/www/html/public/install.php

# Storage + cache permissions.
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache \
    && mkdir -p database \
    && touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite

# Entrypoint.
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Environment.
ENV APP_ENV=production
ENV PORT=10000

EXPOSE 10000

# Health check.
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=5 \
    CMD wget --no-verbose --tries=1 --spider http://127.0.0.1:${PORT}/api/v1/health/live || exit 1

# tini reaps zombies + signals; entrypoint handles env wiring; httpd serves.
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/docker-entrypoint.sh"]
CMD ["httpd", "-DFOREGROUND"]
