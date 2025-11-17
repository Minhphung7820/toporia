#!/bin/sh
set -e

# Configure OPcache based on environment variables
# Default: validate_timestamps=1 for development (auto-reload on file changes)
OPCACHE_ENABLE=${OPCACHE_ENABLE:-1}
OPCACHE_VALIDATE_TIMESTAMPS=${OPCACHE_VALIDATE_TIMESTAMPS:-1}
OPCACHE_REVALIDATE_FREQ=${OPCACHE_REVALIDATE_FREQ:-0}

cat > /usr/local/etc/php/conf.d/opcache.ini <<EOF
opcache.enable=${OPCACHE_ENABLE}
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=${OPCACHE_VALIDATE_TIMESTAMPS}
opcache.revalidate_freq=${OPCACHE_REVALIDATE_FREQ}
opcache.fast_shutdown=1
EOF

# Execute the main command
exec "$@"

