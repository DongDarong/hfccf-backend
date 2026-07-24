# Multi-stage build for Laravel 13 + PHP 8.3 + PostgreSQL
# Production-ready Dockerfile with GD, PostgreSQL PDO, and minimal runtime size

# Stage 1: Builder
FROM php:8.3-fpm-alpine AS builder

WORKDIR /app

# Install system dependencies and PHP extensions
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

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# Copy application files
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Stage 2: Runtime
FROM php:8.3-fpm-alpine

WORKDIR /app

# Install runtime dependencies only
RUN apk add --no-cache \
    libpq \
    libpng \
    libjpeg \
    freetype \
    zlib \
    libzip \
    curl \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_pgsql \
        zip \
        mbstring \
        opcache

# Copy PHP configuration
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

# Start PHP-FPM
CMD ["php-fpm"]

# Expose port (Render will set PORT env var)
EXPOSE 8000
