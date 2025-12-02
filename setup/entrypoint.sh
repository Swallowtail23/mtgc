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

# Now run the original CMD
exec "$@"
