#!/bin/bash

set -e

# Prompt for base directory
read -rp "Enter base directory for data/config/logs (e.g. /home/username): " BASE_PARENT

# Validate input
if [[ -z "$BASE_PARENT" ]]; then
    echo "[ERROR] Base directory is required. Aborting."
    exit 1
fi

# Normalize path and append /mtgc
BASE_DIR="${BASE_PARENT%/}/mtgc"
MARKER_FILE="$BASE_DIR/logs/.scryfall_import_done"

# Create required directories
mkdir -p "$BASE_DIR/cardimg" "$BASE_DIR/config" "$BASE_DIR/logs"

# Write .env file
echo "BASE_DIR=$BASE_DIR" > .env

# Copy placeholder configs if not present
if [[ ! -f "$BASE_DIR/config/mtg_new.ini" ]]; then
    echo "Creating editable config file from template..."
    cp setup/mtg_new.ini "$BASE_DIR/config/mtg_new.ini"
fi

if [[ ! -f "$BASE_DIR/config/php_custom.ini" ]]; then
    echo "Creating php config file from template..."
    cp setup/php_custom.ini "$BASE_DIR/config/php_custom.ini"
fi

# Make config editable
chmod +w "$BASE_DIR/config/mtg_new.ini"

# Start containers
docker-compose up --build -d

# Check if db-data volume exists
DO_DB_SETUP=1
if docker volume ls --format '{{.Name}}' | grep -qi 'mtgc_db-data'; then
    echo "Existing DB volume found."
    DO_DB_SETUP=0
fi

# Wait for MySQL
echo "Waiting for MySQL to be available..."
until docker exec mtgc-web-1 mysqladmin ping -h"db" --silent; do
    echo "MySQL is not available yet. Waiting..."
    sleep 2
done

echo "MySQL is available."

# If new DB, do full setup
if [[ "$DO_DB_SETUP" -eq 1 ]]; then
    echo "Starting initial DB setup..."

    # Put DB into maintenance mode
    docker exec mtgc-db-1 mysql -u root -prootpass -e "INSERT INTO mtg.admin (\`key\`, usemin, mtce) VALUES (1, 0, 1) ON DUPLICATE KEY UPDATE mtce=1;"

    # Prompt for user info
    read -rp "Enter email address for admin user: " email
    read -rp "Enter desired username (display only): " username
    read -rsp "Enter password: " password
    echo

    # Get hashed password from PHP script
    hashed=$(docker exec mtgc-web-1 php /var/www/mtgnew/setup/initial.php "$username" "$password" | grep "Hashed password:" | awk -F': ' '{print $2}' | xargs)

    if [[ -n "$hashed" ]]; then
        docker exec mtgc-db-1 mysql -u root -prootpass -e \
            "INSERT INTO mtg.users (username, email, password, reg_date, status) VALUES ('$username', '$email', '$hashed', NOW(), 'active');"
        docker exec mtgc-db-1 mysql -u root -prootpass -e \
            "UPDATE mtg.users SET admin=1 WHERE username='$username';"
        docker exec mtgc-db-1 mysql -u root -prootpass -e \
            "INSERT INTO mtg.groups (groupnumber, groupname, owner) VALUES (1, 'Masters', 1) ON DUPLICATE KEY UPDATE groupname='Masters';"
    else
        echo "[ERROR] Failed to get hashed password."
        exit 1
    fi
else
    echo "MySQL is available. Skipping user/admin setup - database volume already exists."
    if [[ ! -f "$MARKER_FILE" ]]; then
        echo "Existing DB volume but no import marker - assuming import was already run."
        echo "done" > "$MARKER_FILE"
    fi
fi

# Run bulk import if not already done
if [[ ! -f "$MARKER_FILE" ]]; then
    echo "Running bulk Scryfall import - this may take up to 2 hours..."
    docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_bulk.php all"
    docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_sets.php"
    docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_rulings.php"
    docker exec mtgc-web-1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_migrations.php"
    echo "done" > "$MARKER_FILE"
else
    echo "Bulk import already completed previously - skipping."
fi

# Clear maintenance mode
docker exec mtgc-db-1 mysql -u root -prootpass -e \
    "INSERT INTO mtg.admin (\`key\`, usemin, mtce) VALUES (1, 0, 0) ON DUPLICATE KEY UPDATE mtce=0;"

echo "✅ Setup complete. You can now log in via http://localhost:8080"
