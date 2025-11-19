#!/bin/sh
set -e

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

