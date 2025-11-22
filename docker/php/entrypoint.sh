#!/bin/sh
set -e

# CRITICAL: Configure PHP-FPM to listen on 0.0.0.0:9000 (all interfaces)
# This allows Nginx container to connect via Docker network
# Default is 127.0.0.1:9000 (localhost only), which doesn't work with Docker
# This MUST run before php-fpm starts to ensure correct configuration

PHP_FPM_CONF="/usr/local/etc/php-fpm.d/www.conf"

if [ -f "$PHP_FPM_CONF" ]; then
    echo "=== Configuring PHP-FPM to listen on all interfaces ==="

    # Remove ALL existing listen directives (including commented ones)
    sed -i '/^listen = /d' "$PHP_FPM_CONF"
    sed -i '/^;listen = /d' "$PHP_FPM_CONF"

    # Find [www] section and add listen directive right after it
    if grep -q "^\[www\]" "$PHP_FPM_CONF"; then
        sed -i '/^\[www\]/a listen = 0.0.0.0:9000' "$PHP_FPM_CONF"
    else
        # If [www] section doesn't exist, add it at the end
        echo "" >> "$PHP_FPM_CONF"
        echo "[www]" >> "$PHP_FPM_CONF"
        echo "listen = 0.0.0.0:9000" >> "$PHP_FPM_CONF"
    fi

    # Clear allowed_clients to allow all connections
    sed -i 's/^listen.allowed_clients = .*/listen.allowed_clients = /' "$PHP_FPM_CONF" 2>/dev/null || true
    sed -i 's/^;listen.allowed_clients = .*/listen.allowed_clients = /' "$PHP_FPM_CONF" 2>/dev/null || true

    # Verify configuration
    if grep -q "^listen = 0.0.0.0:9000" "$PHP_FPM_CONF"; then
        echo "✓ PHP-FPM configured to listen on 0.0.0.0:9000"
    else
        echo "✗ ERROR: Failed to configure PHP-FPM listen directive"
        exit 1
    fi
else
    echo "✗ WARNING: PHP-FPM config file not found: $PHP_FPM_CONF"
fi

# Fix permissions for source files and directories
# This ensures www-data user can read all PHP files and traverse directories
# Directories need execute permission for traversal, files need read permission
find /var/www/html -type d -exec chmod 755 {} \; 2>/dev/null || true
find /var/www/html -type f -exec chmod 644 {} \; 2>/dev/null || true

# Fix permissions for storage directories
# This ensures www-data user can write logs, cache, etc.
if [ -d /var/www/html/storage ]; then
    chown -R www-data:www-data /var/www/html/storage
    chmod -R 775 /var/www/html/storage
fi

if [ -d /var/www/html/bootstrap/cache ]; then
    chown -R www-data:www-data /var/www/html/bootstrap/cache
    chmod -R 775 /var/www/html/bootstrap/cache
fi

# Execute the main command (php-fpm)
exec "$@"

