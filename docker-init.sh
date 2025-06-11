#!/bin/bash

# Ensure config directory exists
mkdir -p ./opt/mtg

# Copy placeholder config file if it doesn't exist
if [ ! -f ./opt/mtg/mtg_new.ini ]; then
    echo "Creating editable config file from template..."
    cp ./setup/mtg_new.ini ./opt/mtg/mtg_new.ini
fi

# Start containers (build if needed)
docker-compose up --build -d

echo "⏳ Waiting for MySQL to be available..."
until docker exec mtgc-web-1 mysqladmin ping -h"db" --silent; do
    sleep 2
done

echo "✅ MySQL is available. Starting initial setup..."

# Put DB in maintenance mode
docker exec mtgc-db-1 mysql -u root -prootpass -e "UPDATE mtg.admin SET mtce=1 WHERE `key`=1;"

# Load bulk data
docker exec mtgc-web-1 php /var/www/mtgnew/bulk/scryfall_bulk.php all
docker exec mtgc-web-1 php /var/www/mtgnew/bulk/scryfall_sets.php
docker exec mtgc-web-1 php /var/www/mtgnew/bulk/scryfall_rulings.php
docker exec mtgc-web-1 php /var/www/mtgnew/bulk/scryfall_migrations.php

echo ""
read -p "Enter email address for admin user: " email
read -p "Enter desired username (display only): " username
read -sp "Enter password: " password
echo ""

hash_output=$(docker exec mtgc-web-1 php /var/www/mtgnew/setup/initial.php "$username" "$password")
hashed=$(echo "$hash_output" | grep 'Hashed password:' | awk -F': ' '{print $2}')

docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "INSERT INTO mtg.users (username, email, password, reg_date, status) VALUES ('$username', '$email', '$hashed', NOW(), 'active');"

docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "UPDATE mtg.users SET admin=1 WHERE username='$username';"

# Finalise admin setup
docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "INSERT INTO mtg.admin (\`key\`, usemin, mtce) VALUES (1, 0, 0) ON DUPLICATE KEY UPDATE mtce=0;"
docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "INSERT INTO mtg.groups (groupnumber, groupname, owner) VALUES (1, 'Masters', 1) ON DUPLICATE KEY UPDATE groupname='Masters';"

echo "✅ Initial setup complete. You can now log in via http://localhost:8080"
