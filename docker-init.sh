#!/bin/bash

set -e

# ─────────────────────────────────────────────
# Detect compose + container CLI (docker/podman)
# ─────────────────────────────────────────────

if command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD="docker-compose"
elif command -v podman-compose >/dev/null 2>&1; then
    COMPOSE_CMD="podman-compose"
else
    echo "[ERROR] Neither docker-compose nor podman-compose found in PATH."
    echo "        Install one (e.g. 'dnf install podman-compose') and try again."
    exit 1
fi

if command -v docker >/dev/null 2>&1; then
    DOCKER_CMD="docker"
elif command -v podman >/dev/null 2>&1; then
    DOCKER_CMD="podman"
else
    echo "[ERROR] Neither docker nor podman found in PATH."
    exit 1
fi

echo "[INFO] Using compose command: ${COMPOSE_CMD}"
echo "[INFO] Using container command: ${DOCKER_CMD}"

# ─────────────────────────────────────────────
# Prompt for base directory
# ─────────────────────────────────────────────

read -rp "Enter base directory for data/config/logs (e.g. /home/username): " BASE_PARENT
read -rp "Enter port for the web UI (e.g. 8082): " WEB_PORT

# Validate base dir
if [[ -z "$BASE_PARENT" ]]; then
    echo "[ERROR] Base directory is required. Aborting."
    exit 1
fi

# Default port if empty
if [[ -z "$WEB_PORT" ]]; then
    WEB_PORT=8082
fi

# Very basic numeric check (optional)
if ! [[ "$WEB_PORT" =~ ^[0-9]+$ ]]; then
    echo "[ERROR] Port must be a number. Aborting."
    exit 1
fi

# Normalize path and append /mtgc
BASE_DIR="${BASE_PARENT%/}/mtgc"
MARKER_FILE="$BASE_DIR/logs/.scryfall_import_done"

# Create required directories
mkdir -p "$BASE_DIR/cardimg" "$BASE_DIR/config" "$BASE_DIR/logs"

# Write .env file (both vars together)
{
    echo "BASE_DIR=$BASE_DIR"
    echo "WEB_PORT=$WEB_PORT"
} > .env

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

# ─────────────────────────────────────────────
# Check if db-data volume exists (before containers start)
# ─────────────────────────────────────────────
DO_DB_SETUP=1
if ${DOCKER_CMD} volume ls --format '{{.Name}}' | grep -qi 'mtgc_db-data'; then
    echo "Existing DB volume found."
    DO_DB_SETUP=0
fi

# ─────────────────────────────────────────────
# Start containers via compose
# ─────────────────────────────────────────────
${COMPOSE_CMD} up --build -d

# ─────────────────────────────────────────────
# Wait for MySQL
# ─────────────────────────────────────────────
echo "Waiting for MySQL to be available..."
until ${DOCKER_CMD} exec mtgc_web_1 mysqladmin ping -h"db" --silent; do
    echo "MySQL is not available yet. Waiting..."
    sleep 2
done

echo "MySQL is available."

# ─────────────────────────────────────────────
# If new DB, do full setup
# ─────────────────────────────────────────────
if [[ "$DO_DB_SETUP" -eq 1 ]]; then
    echo "Starting initial DB setup..."

    # Put DB into maintenance mode
    ${DOCKER_CMD} exec mtgc_db_1 mysql -u root -prootpass -e \
        "INSERT INTO mtg_new.admin (\`key\`, usemin, mtce) VALUES (1, 0, 1) ON DUPLICATE KEY UPDATE mtce=1;"

    # Prompt for user info
    read -rp "Enter email address for admin user: " email
    read -rp "Enter desired username (display only): " username
    read -rsp "Enter password: " password
    echo

    # Get hashed password from PHP script
    hashed=$(${DOCKER_CMD} exec mtgc_web_1 php /var/www/mtgnew/setup/initial.php "$username" "$password" \
        | grep "Hashed password:" | awk -F': ' '{print $2}' | xargs)

    if [[ -n "$hashed" ]]; then
        ${DOCKER_CMD} exec mtgc_db_1 mysql -u root -prootpass -e \
            "INSERT INTO mtg_new.users (username, email, password, reg_date, status) VALUES ('$username', '$email', '$hashed', NOW(), 'active');"
        ${DOCKER_CMD} exec mtgc_db_1 mysql -u root -prootpass -e \
            "UPDATE mtg_new.users SET admin=1 WHERE username='$username';"
        ${DOCKER_CMD} exec mtgc_db_1 mysql -u root -prootpass -e \
            "INSERT INTO mtg_new.groups (groupnumber, groupname, owner) VALUES (1, 'Masters', 1) ON DUPLICATE KEY UPDATE groupname='Masters';"
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

# ─────────────────────────────────────────────
# Run bulk import if not already done
# ─────────────────────────────────────────────
if [[ ! -f "$MARKER_FILE" ]]; then
    echo "Running bulk Scryfall import - this may take up to 2 hours..."
    ${DOCKER_CMD} exec mtgc_web_1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_bulk.php all"
    ${DOCKER_CMD} exec mtgc_web_1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_sets.php"
    ${DOCKER_CMD} exec mtgc_web_1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_rulings.php"
    ${DOCKER_CMD} exec mtgc_web_1 bash -c "cd /var/www/mtgnew/bulk && php scryfall_migrations.php"
    echo "done" > "$MARKER_FILE"
else
    echo "Bulk import already completed previously - skipping."
fi

# ─────────────────────────────────────────────
# Clear maintenance mode
# ─────────────────────────────────────────────
${DOCKER_CMD} exec mtgc_db_1 mysql -u root -prootpass -e \
    "INSERT INTO mtg_new.admin (\`key\`, usemin, mtce) VALUES (1, 0, 0) ON DUPLICATE KEY UPDATE mtce=0;"

echo "✅ Setup complete. You can now log in via http://localhost:${WEB_PORT}"
