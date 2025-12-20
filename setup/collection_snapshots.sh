#!/bin/bash
set -e

echo "[$(date -Is)] collection_snapshots.sh started"
cd /var/www/mtgnew/bulk
php ./collection_snapshots.php
echo "[$(date -Is)] collection_snapshots.sh completed"