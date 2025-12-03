#!/bin/bash

# Ensure application ini is linked from mounted config directory
CONFIG_SRC="/mnt/data/config/mtg_new.ini"
CONFIG_DEST="/opt/mtg/mtg_new.ini"
if [ -f "$CONFIG_SRC" ]; then
    mkdir -p /opt/mtg
    ln -sf "$CONFIG_SRC" "$CONFIG_DEST"
fi

# Ensure log file exists and has correct ownership
LOG_FILE="/var/log/mtg/mtgapp.log"
if [ ! -f "$LOG_FILE" ]; then
    touch "$LOG_FILE"
fi
chown www-data:www-data "$LOG_FILE"

# Populate vendor volume on first boot or when check file is removed
COMPOSER_CHECK="/mnt/data/config/composer_installed.flag"
LEGACY_COMPOSER_CHECK="/mnt/data/config/.composer_installed"
if [ -f "$LEGACY_COMPOSER_CHECK" ] && [ ! -f "$COMPOSER_CHECK" ]; then
    mv "$LEGACY_COMPOSER_CHECK" "$COMPOSER_CHECK"
fi

if [ ! -f /var/www/mtgnew/vendor/autoload.php ] || [ ! -f "$COMPOSER_CHECK" ]; then
    echo "[entrypoint] Installing composer dependencies..."
    export COMPOSER_ALLOW_SUPERUSER=1
    export COMPOSER_HOME=/tmp/composer
    composer install --no-dev --no-interaction --prefer-dist
    touch "$COMPOSER_CHECK"
fi

# Now run the original CMD
exec "$@"
