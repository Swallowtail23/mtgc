#!/bin/bash
set -e

echo "[$(date -Is)] manifest.sh started"
cd /var/www/mtgnew/bulk
php ./scryfall_manifest.php
echo "[$(date -Is)] manifest.sh completed"
