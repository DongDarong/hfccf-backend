# Multi-stage build for Laravel 13 + PHP 8.3 + PostgreSQL
# Production-ready Dockerfile with GD, PostgreSQL PDO, and minimal runtime size

# Stage 1: Builder - Compile PHP extensions and install dependencies
FROM php:8.3-cli-alpine AS builder

WORKDIR /app

# Install build dependencies and PHP extension requirements
RUN apk add --no-cache \
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
    && rm -rf /var/cache/apk/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# Copy application files
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Stage 2: Runtime - Only runtime libraries, no build tools
FROM php:8.3-cli-alpine

WORKDIR /app

# Install ONLY runtime dependencies (no -dev packages, no build tools)
RUN apk add --no-cache \
    libpq \
    libpng \
    libjpeg \
    freetype \
    zlib \
    libzip \
    curl

# Copy pre-compiled PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copy built application from builder
COPY --from=builder /app /app

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
