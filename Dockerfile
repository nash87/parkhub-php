# =============================================================================
# ParkHub PHP — Optimized multi-stage Docker build
# Separate composer + node stages, minimal runtime layers.
# =============================================================================

# ---------------------------------------------------------------------------
# Stage 1: Frontend build (Astro)
# ---------------------------------------------------------------------------
FROM docker.io/library/node:22-slim AS frontend
WORKDIR /app
COPY parkhub-web/package*.json ./
RUN npm ci
COPY parkhub-web/ ./
RUN DOCKER=1 npm run build

# ---------------------------------------------------------------------------
# Stage 2: Composer dependency install (no dev deps)
# ---------------------------------------------------------------------------
FROM docker.io/library/composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
# Copy full app for autoload generation and post-install scripts
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------------------------------------------------------------------------
# Stage 3: Runtime — PHP + Apache
# Pin to bookworm (Debian 12) for reproducible OS packages
# ---------------------------------------------------------------------------
FROM docker.io/library/php:8.4-apache AS runtime

# Install PHP extensions in a single layer
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev \
        libzip-dev unzip sqlite3 libsqlite3-dev wget \
        libpq-dev gosu \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql pdo_sqlite pdo_pgsql pgsql gd zip bcmath opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/* /tmp/*

# PHP production hardening + OPcache tuning
RUN { \
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
    } > /usr/local/etc/php/conf.d/production.ini

# Apache hardening
RUN echo "ServerTokens Prod" >> /etc/apache2/conf-available/security.conf \
    && a2enconf security

# Set document root to Laravel public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copy application code (without vendor/ and node_modules/ per .dockerignore)
COPY --chown=www-data:www-data . .

# Copy composer vendor from builder
COPY --chown=www-data:www-data --from=vendor /app/vendor/ ./vendor/

# Overlay built Astro frontend assets into Laravel's public directory
COPY --chown=www-data:www-data --from=frontend /app/dist/ ./public/

# Set permissions and create required directories
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache \
    && mkdir -p database \
    && touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Environment
ENV APP_ENV=production
ENV PORT=10000

EXPOSE 10000

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=5 \
    CMD wget --no-verbose --tries=1 --spider http://127.0.0.1:${PORT}/api/v1/health/live || exit 1

CMD ["/usr/local/bin/docker-entrypoint.sh", "apache2-foreground"]
