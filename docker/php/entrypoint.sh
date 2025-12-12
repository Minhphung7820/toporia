#!/bin/sh
set -e

# ============================================
# ENTRYPOINT FOR PHP-FPM CONTAINER
# ============================================
# This script runs at container startup.
# It can run as non-root user (no sudo/chown operations needed).

echo "=== Starting PHP-FPM container ==="

# Ensure storage directories exist (no chown needed - permissions set via volume)
mkdir -p /var/www/html/storage/sessions 2>/dev/null || true
mkdir -p /var/www/html/storage/cache 2>/dev/null || true
mkdir -p /var/www/html/storage/logs 2>/dev/null || true
mkdir -p /var/www/html/storage/logs/nginx 2>/dev/null || true
mkdir -p /var/www/html/storage/logs/schedule 2>/dev/null || true
mkdir -p /var/www/html/storage/framework/cache 2>/dev/null || true
mkdir -p /var/www/html/storage/framework/sessions 2>/dev/null || true
mkdir -p /var/www/html/storage/framework/views 2>/dev/null || true
mkdir -p /var/www/html/bootstrap/cache 2>/dev/null || true

# Set umask for new files (rw-rw-r--)
umask 002

echo "✓ Container ready"

# Execute the main command (php-fpm)
exec "$@"
