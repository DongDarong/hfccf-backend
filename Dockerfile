# Single-stage build for Laravel 13 + PHP 8.3 + PostgreSQL
# Production-ready Dockerfile with all extensions compiled once

FROM php:8.3-alpine

WORKDIR /app

# Install build tools, system dependencies, and compile PHP extensions
RUN apk add --no-cache \
    build-base \
    curl \
    git \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    freetype-dev \
    zlib-dev \
    libzip-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_pgsql \
        zip \
        mbstring \
        opcache \
    && apk del --no-cache build-base

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# Copy application files
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Create storage directories with proper permissions
RUN mkdir -p storage/logs storage/app storage/framework/cache storage/framework/sessions storage/framework/views \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 0775 storage bootstrap/cache

# Set production environment
ENV APP_ENV=production
ENV APP_DEBUG=false

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

# Start built-in PHP server
CMD ["php", "-d", "variables_order=EGPCS", "-d", "display_errors=stderr", "-d", "error_reporting=E_ALL", "-S", "0.0.0.0:8000", "-t", "public/"]

# Expose port
EXPOSE 8000
