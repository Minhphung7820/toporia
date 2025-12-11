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

# ============================================
# FIX SOURCE CODE PERMISSIONS (WSL FIX)
# ============================================
# Problem: Files created by host (WSL) have restrictive permissions (600/700)
# Solution: Set permissive permissions on ALL source files so PHP can read them
echo "=== Fixing source code permissions for WSL compatibility ==="

# Make all directories readable and executable (755)
find /var/www/html -type d -exec chmod 755 {} \; 2>/dev/null || true

# Make all PHP/config files readable (644)
find /var/www/html -type f \( -name "*.php" -o -name "*.json" -o -name "*.xml" -o -name "*.yml" -o -name "*.yaml" -o -name "*.env*" -o -name "*.md" -o -name "*.lock" \) -exec chmod 644 {} \; 2>/dev/null || true

# Explicitly fix src directory (most common issue)
if [ -d "/var/www/html/src" ]; then
    find /var/www/html/src -type d -exec chmod 755 {} \; 2>/dev/null || true
    find /var/www/html/src -type f -exec chmod 644 {} \; 2>/dev/null || true
fi

# Explicitly fix config directory
if [ -d "/var/www/html/config" ]; then
    find /var/www/html/config -type d -exec chmod 755 {} \; 2>/dev/null || true
    find /var/www/html/config -type f -exec chmod 644 {} \; 2>/dev/null || true
fi

# Explicitly fix bootstrap directory
if [ -d "/var/www/html/bootstrap" ]; then
    find /var/www/html/bootstrap -type d -exec chmod 755 {} \; 2>/dev/null || true
    find /var/www/html/bootstrap -type f -exec chmod 644 {} \; 2>/dev/null || true
fi

# Explicitly fix routes directory
if [ -d "/var/www/html/routes" ]; then
    find /var/www/html/routes -type d -exec chmod 755 {} \; 2>/dev/null || true
    find /var/www/html/routes -type f -exec chmod 644 {} \; 2>/dev/null || true
fi

echo "✓ Source code permissions fixed (dirs: 755, files: 644)"

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

# Set permissions: 777 for storage (WSL compatibility - host user needs write access)
chmod -R 777 /var/www/html/storage 2>/dev/null || true
chmod -R 777 /var/www/html/bootstrap/cache 2>/dev/null || true

# Set umask for new files (rw-rw-r--)
umask 002

echo "✓ Storage permissions fixed (owner: www-data:${WWW_DATA_UID}:${WWW_DATA_GID})"

# Execute the main command (php-fpm)
exec "$@"

