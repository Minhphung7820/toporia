# Toporia Framework - Production-Ready PHP 8.2 Docker Image
# Optimized for stability, security, and performance

FROM php:8.2-fpm-alpine

# Maintainer
LABEL maintainer="Toporia Framework"
LABEL description="PHP 8.2 FPM with ext-redis, ext-rdkafka, optimized for production"

# Install system dependencies
RUN apk add --no-cache \
    # Build tools
    autoconf g++ make pkgconfig \
    # Kafka & Redis
    librdkafka-dev linux-headers \
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

# Install PECL extensions (Redis + RdKafka)
RUN pecl install redis-6.0.2 rdkafka-6.0.3 && \
    docker-php-ext-enable redis rdkafka

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

# Copy composer files for dependency caching
COPY composer.json composer.lock* ./

# Install Composer dependencies (production mode)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs \
    && rm -rf /root/.composer

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

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
