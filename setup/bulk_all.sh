#!/bin/bash
set -e

echo "[$(date -Is)] bulk_all.sh started (all)"
cd /var/www/mtgnew/bulk
php ./scryfall_bulk.php all
echo "[$(date -Is)] bulk_all.sh started (all)"