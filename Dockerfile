# Toporia Framework - Production-Ready PHP 8.4 Docker Image
# Optimized for stability, security, and performance

FROM php:8.4-fpm-alpine

# Maintainer
LABEL maintainer="Toporia Framework"
LABEL description="PHP 8.4 FPM with ext-redis, ext-rdkafka, ext-mongodb, optimized for production"

# Install system dependencies
RUN apk add --no-cache \
    # Build tools
    autoconf g++ make pkgconfig \
    # Kafka & Redis
    librdkafka-dev linux-headers \
    # MongoDB
    openssl-dev \
    # Database drivers
    postgresql-dev mysql-dev \
    # Compression & utilities
    zlib-dev libzip-dev \
    # Image processing (GD)
    freetype-dev libjpeg-turbo-dev libpng-dev \
    # Runtime utilities
    git unzip bash curl wget \
    # Netcat for healthcheck
    netcat-openbsd \
    # Supervisor for process management
    supervisor

# Install PHP core extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        zip \
        pcntl \
        sockets \
        gd \
        opcache

# Install PECL extensions (Redis + RdKafka + MongoDB)
RUN pecl install redis rdkafka mongodb && \
    docker-php-ext-enable redis rdkafka mongodb

# Configure PHP for production
RUN { \
    echo 'memory_limit=512M'; \
    echo 'upload_max_filesize=50M'; \
    echo 'post_max_size=50M'; \
    echo 'max_execution_time=300'; \
    echo 'max_input_time=300'; \
    echo 'date.timezone=UTC'; \
    echo 'expose_php=Off'; \
    echo 'display_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'error_log=/proc/self/fd/2'; \
    } > /usr/local/etc/php/conf.d/99-custom.ini

# OPcache enabled (same as Laravel default)
# validate_timestamps=1 means check if files changed (good for development)
# revalidate_freq=2 means check every 2 seconds
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'opcache.revalidate_freq=2'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy PHP-FPM pool configuration
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Copy healthcheck script
COPY docker/php/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

# Copy entrypoint script
COPY docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Copy application code
COPY . .

# Install Composer dependencies (development mode - install in container)
# For production, uncomment --no-dev
RUN composer install \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-reqs \
    || true

# Generate autoloader
RUN composer dump-autoload --optimize || true

# Create storage directories with full permissions (no restrictions)
RUN mkdir -p \
    storage/logs \
    storage/sessions \
    storage/cache \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache \
    && chmod -R 777 /var/www/html

# Expose PHP-FPM port
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

# Use entrypoint for initialization
ENTRYPOINT ["docker-entrypoint.sh"]

# Start PHP-FPM
CMD ["php-fpm", "-F"]
