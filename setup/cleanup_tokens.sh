#!/bin/bash
set -e

echo "[$(date -Is)] cleanup_tokens.sh started"
cd /var/www/mtgnew/bulk
php ./cleanup_tokens.php
echo "[$(date -Is)] cleanup_tokens.sh started"