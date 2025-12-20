#!/bin/bash
set -euo pipefail

echo "[$(date -Is)] sets.sh started"
cd /var/www/mtgnew/bulk
php ./scryfall_sets.php

chown -R www-root:www-root /opt/mtg/cardimg
echo "[$(date -Is)] sets.sh completed"
