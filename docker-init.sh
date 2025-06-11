#!/bin/bash

# Prompt for base directory
read -p "Enter base directory for data/config/logs (e.g. /home/you). A 'mtgc' subfolder will be created: " BASE_PARENT

# Validate input
if [ -z "$BASE_PARENT" ]; then
    echo "❌ Base directory is required. Aborting."
    exit 1
fi

# Append mtgc subdir
BASE_DIR="$BASE_PARENT/mtgc"

# Create required structure
mkdir -p "$BASE_DIR/cardimg" "$BASE_DIR/config" "$BASE_DIR/logs"

# Write .env file
echo "BASE_DIR=$BASE_DIR" > .env

# Copy placeholder config
if [ ! -f "$BASE_DIR/config/mtg_new.ini" ]; then
    echo "Creating editable config file from template..."
    cp ./setup/mtg_new.ini "$BASE_DIR/config/mtg_new.ini"
fi

# Set ownership for container (www-data = uid 33)
sudo chown -R 33:33 "$BASE_DIR/cardimg" "$BASE_DIR/logs"

# Start Docker containers
docker-compose up --build -d

# Wait for MySQL to be available
echo "⏳ Waiting for MySQL to be available..."
until docker exec mtgc-web-1 mysqladmin ping -h"db" --silent; do
    sleep 2
done

echo "✅ MySQL is available. Starting initial setup..."

# Enable maintenance mode
docker exec mtgc-db-1 mysql -u root -prootpass -e "UPDATE mtg.admin SET mtce=1 WHERE \`key\`=1;"

# Prompt for user
read -p "Enter email address for admin user: " email
read -p "Enter desired username (display only): " username
read -sp "Enter password: " password
echo ""

# Get hashed password
hash_output=$(docker exec mtgc-web-1 php /var/www/mtgnew/setup/initial.php "$username" "$password")
hashed=$(echo "$hash_output" | grep 'Hashed password:' | awk -F': ' '{print $2}' | xargs)

# Insert user and admin
docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "INSERT INTO mtg.users (username, email, password, reg_date, status) VALUES ('$username', '$email', '$hashed', NOW(), 'active');"
docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "UPDATE mtg.users SET admin=1 WHERE username='$username';"
docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "INSERT INTO mtg.groups (groupnumber, groupname, owner) VALUES (1, 'Masters', 1) ON DUPLICATE KEY UPDATE groupname='Masters';"

# Run bulk import scripts
echo "Importing data - first run can take up to 2 hours"
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_bulk.php all"
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_sets.php"
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_rulings.php"
docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_migrations.php"

# Disable maintenance mode
docker exec mtgc-db-1 mysql -u root -prootpass -e "UPDATE mtg.admin SET mtce=0 WHERE \`key\`=1;"

echo "✅ Initial setup complete. You can now log in via http://localhost:8080"
