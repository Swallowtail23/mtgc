#!/bin/bash
set -e

# Copy Apache configuration into place
cp /var/www/html/setup/mtgc.conf /etc/apache2/sites-available/mtgc.conf

# Enable custom site and disable default
a2ensite mtgc.conf
a2dissite 000-default.conf

# Reload Apache if running
apache2ctl -k restart 2>/dev/null || true

exec "$@"
