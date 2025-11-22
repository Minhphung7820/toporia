#!/bin/sh
# Fix PHP-FPM to listen on all interfaces
# This script ensures PHP-FPM can accept connections from Nginx container

PHP_FPM_CONF="/usr/local/etc/php-fpm.d/www.conf"

if [ -f "$PHP_FPM_CONF" ]; then
    # Remove all listen directives and add clean one
    sed -i '/^listen = /d' "$PHP_FPM_CONF"

    # Add clean listen directive after [www] section
    sed -i '/^\[www\]/a listen = 9000' "$PHP_FPM_CONF"

    # Clear allowed_clients to allow all
    sed -i 's/^listen.allowed_clients = .*/listen.allowed_clients = /' "$PHP_FPM_CONF" || true
fi

