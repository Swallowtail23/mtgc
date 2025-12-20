#!/bin/bash
set -e

echo "[$(date -Is)] migrations.sh started"
cd /var/www/mtgnew/bulk
php ./scryfall_migrations.php
echo "[$(date -Is)] migrations.sh completed"