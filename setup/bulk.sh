#!/bin/bash
set -e

echo "[$(date -Is)] bulk.sh started (default)"
cd /var/www/mtgnew/bulk
php ./scryfall_bulk.php default
echo "[$(date -Is)] bulk.sh started (default)"