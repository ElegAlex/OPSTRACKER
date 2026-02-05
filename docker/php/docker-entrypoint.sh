#!/bin/sh
set -e

# Copy compiled assets to the shared volume
# This ensures assets are available even after volume mounts
if [ -d "/assets-compiled" ]; then
    echo "Copying compiled assets to public directory..."
    cp -r /assets-compiled/* /var/www/html/public/ 2>/dev/null || true
    chown -R www-data:www-data /var/www/html/public 2>/dev/null || true
fi

# Ensure var directories exist with correct permissions
mkdir -p /var/www/html/var/cache /var/www/html/var/log /var/www/html/var/sessions
chown -R www-data:www-data /var/www/html/var 2>/dev/null || true

# Run cache warmup if not already done
if [ ! -d "/var/www/html/var/cache/prod" ]; then
    echo "Warming up Symfony cache..."
    php bin/console cache:warmup --env=prod --no-debug 2>/dev/null || true
fi

# Execute the main command (php-fpm)
exec "$@"
