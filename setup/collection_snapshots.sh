#!/bin/bash
set -e

cd /var/www/mtgnew/bulk
php ./collection_snapshots.php
