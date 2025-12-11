#!/bin/sh
set -e

# ============================================
# FIX HOST/CONTAINER UID MISMATCH
# ============================================
# Problem: Host user (UID 1000) creates files that container user (www-data UID 82) can't write
# Solution: Match www-data UID to host UID for seamless file access

HOST_UID=${HOST_UID:-1000}
HOST_GID=${HOST_GID:-1000}

# Only modify if running as root and UID is different
if [ "$(id -u)" = "0" ] && [ "$(id -u www-data 2>/dev/null)" != "$HOST_UID" ]; then
    echo "=== Syncing www-data UID/GID with host (${HOST_UID}:${HOST_GID}) ==="

    # Modify www-data user to match host UID/GID
    # This allows PHP-FPM (running as www-data) to read/write host-mounted files
    deluser www-data 2>/dev/null || true
    addgroup -g ${HOST_GID} -S www-data 2>/dev/null || true
    adduser -u ${HOST_UID} -G www-data -S -D -H www-data 2>/dev/null || true

    echo "✓ www-data UID synced to ${HOST_UID}"
fi

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

    # Comment out allowed_clients to allow all connections (empty string causes errors)
    sed -i 's/^listen.allowed_clients = .*/; listen.allowed_clients = /' "$PHP_FPM_CONF" 2>/dev/null || true
    sed -i 's/^listen.allowed_clients =$/; listen.allowed_clients =/' "$PHP_FPM_CONF" 2>/dev/null || true

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

# Fix permissions for storage directories (mounted volumes)
echo "=== Fixing storage permissions ==="

# Ensure storage subdirectories exist
mkdir -p /var/www/html/storage/sessions
mkdir -p /var/www/html/storage/cache
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/logs/nginx
mkdir -p /var/www/html/storage/logs/schedule
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/bootstrap/cache

# Get www-data UID/GID (Alpine uses 82:82)
WWW_DATA_UID=$(id -u www-data 2>/dev/null || echo 82)
WWW_DATA_GID=$(id -g www-data 2>/dev/null || echo 82)

# Change ownership of storage to www-data (PHP-FPM user)
chown -R ${WWW_DATA_UID}:${WWW_DATA_GID} /var/www/html/storage 2>/dev/null || true
chown -R ${WWW_DATA_UID}:${WWW_DATA_GID} /var/www/html/bootstrap/cache 2>/dev/null || true

# Set permissions: directories 775, files 664
find /var/www/html/storage -type d -exec chmod 775 {} \; 2>/dev/null || true
find /var/www/html/storage -type f -exec chmod 664 {} \; 2>/dev/null || true
find /var/www/html/bootstrap/cache -type d -exec chmod 775 {} \; 2>/dev/null || true
find /var/www/html/bootstrap/cache -type f -exec chmod 664 {} \; 2>/dev/null || true

# Set umask for new files (rw-rw-r--)
umask 002

echo "✓ Storage permissions fixed (owner: www-data:${WWW_DATA_UID}:${WWW_DATA_GID})"

# Execute the main command (php-fpm)
exec "$@"

