#!/bin/sh
set -e

APP_DIR=/var/www/html

mkdir -p "$APP_DIR/storage/logs"
chown -R www-data:www-data "$APP_DIR/storage"
find "$APP_DIR/storage" -type d -exec chmod 2775 {} \;
find "$APP_DIR/storage" -type f -exec chmod 0664 {} \;

if [ -n "$BACKUP_DIR" ]; then
    mkdir -p "$BACKUP_DIR/questor"
    chown www-data:www-data "$BACKUP_DIR/questor"
    chmod 2775 "$BACKUP_DIR/questor"
    find "$BACKUP_DIR/questor" -type f -exec chmod 0664 {} \;
fi

if [ ! -f "$APP_DIR/vendor/autoload.php" ]; then
    su -s /bin/sh www-data -c "cd '$APP_DIR' && composer install --no-interaction --no-progress --optimize-autoloader"
fi

exec docker-php-entrypoint "$@"
