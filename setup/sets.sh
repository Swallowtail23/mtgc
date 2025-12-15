#!/bin/bash
set -e

WEB_USER=""
WEB_GROUP=""

if ps -C apache2 >/dev/null 2>&1; then
    WEB_USER="$(ps -o user= -C apache2 | grep -v root | head -n 1)"
elif ps -C httpd >/dev/null 2>&1; then
    WEB_USER="$(ps -o user= -C httpd | grep -v root | head -n 1)"
elif ps -C apache >/dev/null 2>&1; then
    WEB_USER="$(ps -o user= -C apache | grep -v root | head -n 1)"
else
    echo "ERROR: Apache not running; cannot determine web user"
    exit 1
fi

WEB_GROUP="$(id -gn "$WEB_USER")"

cd /var/www/mtgnew/bulk
php ./scryfall_sets.php

chown -R "$WEB_USER:$WEB_GROUP" /opt/mtg/cardimg
