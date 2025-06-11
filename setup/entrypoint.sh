#!/bin/bash

# Ensure log file exists and has correct ownership
LOG_FILE="/var/log/mtg/mtgapp.log"
if [ ! -f "$LOG_FILE" ]; then
touch "$LOG_FILE"
fi
chown www-data:www-data "$LOG_FILE"

# Now run the original CMD
exec "$@"
