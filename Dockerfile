# Stage 1: Build Astro frontend
FROM node:25-slim AS frontend
WORKDIR /app
COPY parkhub-web/package*.json ./
RUN npm ci
COPY parkhub-web/ ./
RUN npm run build

# Stage 2: PHP + Apache
# Pin to bookworm (Debian 12) for reproducible OS packages; update major version intentionally
FROM php:8.4-apache-bookworm

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev unzip sqlite3 libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite gd zip bcmath \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Suppress version exposure
RUN echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini
RUN echo "ServerTokens Prod" >> /etc/apache2/conf-available/security.conf && a2enconf security

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Pin Composer to major version for reproducibility
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY --chown=www-data:www-data . /var/www/html
WORKDIR /var/www/html

# Overlay built Astro frontend assets into Laravel's public directory
COPY --chown=www-data:www-data --from=frontend /app/dist/ /var/www/html/public/

ENV APP_ENV=production

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache \
    && mkdir -p database \
    && touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Default port — Render expects 10000; override with PORT env var for self-hosting
ENV PORT=10000

EXPOSE 10000
CMD ["/usr/local/bin/docker-entrypoint.sh", "apache2-foreground"]
