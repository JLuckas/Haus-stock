#!/bin/sh
# Runs on every container start (each Render deploy), then hands over to Apache.
set -e
cd /var/www/html

# 1. Database schema. Neon's pooler is fine for the app, but migrations go over the
#    direct connection (DATABASE_URL_UNPOOLED) when it is set.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "→ Running migrations"
    DB_URL="${DB_URL:-${DATABASE_URL_UNPOOLED:-$DATABASE_URL}}" php artisan migrate --force --no-interaction
fi

# 2. First login: created once from ADMIN_EMAIL / ADMIN_PASSWORD, left alone afterwards.
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    php artisan user:create "${ADMIN_NAME:-Administrador}" "$ADMIN_EMAIL" \
        --password-env=ADMIN_PASSWORD --skip-existing --no-interaction
fi

# 3. Cache config, routes, views and events with this container's environment.
php artisan optimize --no-interaction
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
