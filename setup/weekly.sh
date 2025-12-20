#!/bin/bash
set -e

echo "[$(date -Is)] weekly.sh started"
cd /var/www/mtgnew/bulk
php ./weekly_exports.php
echo "[$(date -Is)] weekly.sh completed"