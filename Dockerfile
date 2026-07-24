# Laravel 13 + PHP 8.3 + PostgreSQL — production image
# Uses the Debian-based official PHP image and mlocati/docker-php-extension-installer,
# which auto-resolves all system libraries for each extension (no manual apt/apk package guessing).

FROM php:8.3-cli

WORKDIR /app

# Install PHP extensions via the extension installer (handles all system deps automatically)
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    gd \
    pdo_pgsql \
    zip \
    mbstring \
    opcache

# Composer (copied from the official Composer image)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install PHP dependencies first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# Copy application source
COPY . .

# Finalize autoloader
RUN composer dump-autoload --optimize

# Storage/cache directories with correct permissions
RUN mkdir -p storage/logs storage/app storage/framework/cache storage/framework/sessions storage/framework/views \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 0775 storage bootstrap/cache

ENV APP_ENV=production
ENV APP_DEBUG=false

# Start the built-in PHP server bound to Render's $PORT (defaults to 8000 locally)
CMD ["sh", "-c", "php -d variables_order=EGPCS -d display_errors=stderr -d error_reporting=E_ALL -S 0.0.0.0:${PORT:-8000} -t public/"]

EXPOSE 8000
