#!/bin/bash

# Prompt for base directory (parent)
read -p "Enter base directory for data/config/logs (e.g. /home/username). A \"mtgc\" subfolder will be created: " BASE_PARENT

# Validate input
if [ -z "$BASE_PARENT" ]; then
    echo "❌ Base directory is required. Aborting."
    exit 1
fi

# Normalize trailing slash and append 'mtgc'
BASE_PARENT="${BASE_PARENT%/}"         # Remove trailing slash if present
BASE_DIR="$BASE_PARENT/mtgc"

# Create required structure
mkdir -p "$BASE_DIR/cardimg" "$BASE_DIR/config" "$BASE_DIR/logs"

# Copy .env file
cat > .env <<EOF
BASE_DIR=$BASE_DIR
EOF

# Copy placeholder config file if it doesn't exist
if [ ! -f "$BASE_DIR/config/mtg_new.ini" ]; then
    echo "Creating editable config file from template..."
    cp ./setup/mtg_new.ini "$BASE_DIR/config/mtg_new.ini"
fi

# Set ownership to ensure container (www-data) can write
sudo chown -R 33:33 "$BASE_DIR/cardimg" "$BASE_DIR/logs"

# Start containers
docker-compose up --build -d

echo "⏳ Waiting for MySQL to be available..."
until docker exec mtgc-web-1 mysqladmin ping -h"db" --silent; do
    sleep 2
done

echo "✅ MySQL is available. Starting initial setup..."

# Put DB in maintenance mode
docker exec mtgc-db-1 mysql -u root -prootpass -e "UPDATE mtg.admin SET mtce=1 WHERE \`key\`=1;"

# Run bulk import scripts
for script in scryfall_bulk.php scryfall_sets.php scryfall_rulings.php scryfall_migrations.php; do
    docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php $script"
done

# Prompt for user details
echo ""
read -p "Enter email address for admin user: " email
read -p "Enter desired username (display only): " username
read -sp "Enter password: " password

# Generate hashed password
hash_output=$(docker exec mtgc-web-1 php /var/www/mtgnew/setup/initial.php "$username" "$password")
hashed=$(echo "$hash_output" | grep 'Hashed password:' | awk -F': ' '{print $2}')

# Insert user and admin data
docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "INSERT INTO mtg.users (username, email, password, reg_date, status) VALUES ('$username', '$email', '$hashed', NOW(), 'active');"
docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "UPDATE mtg.users SET admin=1 WHERE username='$username';"
docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "INSERT INTO mtg.admin (\`key\`, usemin, mtce) VALUES (1, 0, 0) ON DUPLICATE KEY UPDATE mtce=0;"
docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "INSERT INTO mtg.groups (groupnumber, groupname, owner) VALUES (1, 'Masters', 1) ON DUPLICATE KEY UPDATE groupname='Masters';"

echo "✅ Initial setup complete. You can now log in via http://localhost:8080"
