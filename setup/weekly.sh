#!/bin/bash
set -e

cd /var/www/mtgnew/bulk
php ./weekly_exports.php
