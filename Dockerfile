# Toporia Framework - Production-Ready PHP 8.2 with High-Performance Extensions
FROM php:8.2-fpm-alpine

# Maintainer
LABEL maintainer="Toporia Framework"
LABEL description="PHP 8.2 with ext-redis, ext-rdkafka, and all required extensions"

# Install system dependencies and build tools
RUN apk add --no-cache \
    # Build dependencies
    autoconf \
    g++ \
    make \
    pkgconfig \
    # librdkafka (C library for Kafka)
    librdkafka-dev \
    # Redis dependencies
    linux-headers \
    # Database dependencies
    postgresql-dev \
    mysql-dev \
    # Compression
    zlib-dev \
    libzip-dev \
    # GD image processing (required by phpoffice/phpspreadsheet)
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    # Git for Composer
    git \
    unzip \
    # Process manager
    supervisor \
    # Utilities
    bash \
    curl

# Install PHP extensions via docker-php-ext-install
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    zip \
    pcntl \
    sockets \
    gd

# Install PECL extensions (Redis + RdKafka)
RUN pecl install redis-6.0.2 && \
    pecl install rdkafka-6.0.3 && \
    docker-php-ext-enable redis rdkafka

# Configure PHP settings
RUN { \
    echo 'memory_limit=512M'; \
    echo 'upload_max_filesize=50M'; \
    echo 'post_max_size=50M'; \
    echo 'max_execution_time=300'; \
    echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/custom.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for layer caching)
COPY composer.json composer.lock* ./

# Install Composer dependencies
# Use --ignore-platform-reqs to skip platform requirement checks in Docker
# (extensions are installed via docker-php-ext-install/pecl)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Install enqueue/rdkafka specifically (if needed)
# Note: rdkafka extension is already installed via pecl above
RUN composer require enqueue/rdkafka --no-interaction --ignore-platform-reqs || true

# Copy entrypoint script early (before copying all files)
COPY docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative --ignore-platform-reqs

# Create storage directories with proper permissions
RUN mkdir -p /var/www/html/storage/logs \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s \
    CMD php -v || exit 1

# Start PHP-FPM with entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
