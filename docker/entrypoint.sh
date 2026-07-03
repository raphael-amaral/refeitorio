#!/bin/sh
set -e

APP_DIR=/var/www/html

mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/backups/questor"
chown -R www-data:www-data "$APP_DIR/storage"
chmod -R 2775 "$APP_DIR/storage"

if [ ! -f "$APP_DIR/vendor/autoload.php" ] || [ "$APP_DIR/composer.lock" -nt "$APP_DIR/vendor/autoload.php" ]; then
    su -s /bin/sh www-data -c "cd '$APP_DIR' && composer install --no-interaction --no-progress --optimize-autoloader"
fi

exec docker-php-entrypoint "$@"