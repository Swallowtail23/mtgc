#!/bin/bash

# Ensure application ini is linked from mounted config directory
CONFIG_SRC="/mnt/data/config/mtg_new.ini"
CONFIG_DEST="/opt/mtg/mtg_new.ini"
if [ -f "$CONFIG_SRC" ]; then
    mkdir -p /opt/mtg
    ln -sf "$CONFIG_SRC" "$CONFIG_DEST"
fi

# Expose helper scripts at /opt/mtg/scripts for cron compatibility
SCRIPTS_SRC="/mnt/data/config/scripts"
SCRIPTS_DEST="/opt/mtg/scripts"
if [ -d "$SCRIPTS_SRC" ]; then
    mkdir -p /opt/mtg
    ln -sfn "$SCRIPTS_SRC" "$SCRIPTS_DEST"
    chmod +x "$SCRIPTS_SRC"/*.sh 2>/dev/null
fi

# Install logrotate config if provided
LOGROTATE_SRC="/mnt/data/config/logrotate-mtgc.conf"
if [ -f "$LOGROTATE_SRC" ]; then
    mkdir -p /etc/logrotate.d
    cp -f "$LOGROTATE_SRC" /etc/logrotate.d/mtgc
    chown root:root /etc/logrotate.d/mtgc
    chmod 644 /etc/logrotate.d/mtgc
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

# Install cron schedule if present
CRON_FILE="/mnt/data/config/cron_mtgc"
if [ -f "$CRON_FILE" ]; then
    crontab "$CRON_FILE"
    service cron start
fi

# Now run the original CMD
exec "$@"
