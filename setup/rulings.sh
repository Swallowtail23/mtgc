#!/bin/bash
set -e

echo "[$(date -Is)] rulings.sh started"
cd /var/www/mtgnew/bulk
php ./scryfall_rulings.php
echo "[$(date -Is)] rulings.sh started"