#!/bin/bash

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.yml"
ENV_FILE="$SCRIPT_DIR/.env"
COMPOSE_ARGS=(-f docker-compose.yml)
DEV_OVERRIDE_FILE="$SCRIPT_DIR/docker-compose.override.yml"

cd "$PROJECT_ROOT"

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

read -rp "Do you want to mount host web root for direct access (Y) or use container copy (N)? [Y/N]: " ANSWER
ANSWER=${ANSWER^^}

if [[ "$ANSWER" == "Y" ]]; then
    echo "[INFO] Using host web root bind-mount for development."
    COMPOSE_ARGS+=(-f docker-compose.dev.yml)
    cp "$SCRIPT_DIR/docker-compose.dev.yml" "$DEV_OVERRIDE_FILE"
    echo "[INFO] Created docker-compose.override.yml for persistent dev bind-mount. Remove it to revert."
elif [[ "$ANSWER" == "N" ]]; then
    echo "[INFO] Using container's internal copy of the web root."
    if [[ -f "$DEV_OVERRIDE_FILE" ]]; then
        rm -f "$DEV_OVERRIDE_FILE"
        echo "[INFO] Removed docker-compose.override.yml (dev bind-mount disabled)."
    fi
else
    echo "[WARN] Invalid choice, defaulting to container copy (no host bind-mount)."
    if [[ -f "$DEV_OVERRIDE_FILE" ]]; then
        rm -f "$DEV_OVERRIDE_FILE"
    fi
fi

restore_host_permissions() {
    local target="$1"
    if [[ ! -e "$target" ]]; then
        return
    fi
    local owner group
    owner=$(stat -c %u "$target" 2>/dev/null || echo "")
    group=$(stat -c %g "$target" 2>/dev/null || echo "")
    if [[ -z "$owner" || -z "$group" ]]; then
        return
    fi
    if [[ "$owner" -ne "$(id -u)" || "$group" -ne "$(id -g)" ]]; then
        echo "Adjusting ownership for $target back to $(id -u):$(id -g)"
        if [[ "$DOCKER_CMD" == "podman" ]]; then
            podman unshare chown -R 0:0 "$target"
        else
            chown -R "$(id -u):$(id -g)" "$target"
        fi
    fi
}

# ─────────────────────────────────────────────
# Prompt for base directory
# ─────────────────────────────────────────────

read -rp "Enter base directory for card images/config/logs (e.g. /home/username - recommend 25-40GB): " BASE_PARENT
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

# Ensure host ownership is restored if directories already exist from rootless containers
restore_host_permissions "$BASE_DIR"
restore_host_permissions "$BASE_DIR/cardimg"
restore_host_permissions "$BASE_DIR/config"
restore_host_permissions "$BASE_DIR/logs"
restore_host_permissions "$BASE_DIR/config/scripts"

# Create required directories
mkdir -p "$BASE_DIR/cardimg" "$BASE_DIR/config" "$BASE_DIR/logs" "$BASE_DIR/secrets"
# The config directory contains the application database and integration
# credentials. Keep it private on the host until the container assigns it to
# its runtime account during startup.
chmod 700 "$BASE_DIR/config"
chmod 700 "$BASE_DIR/secrets"

MYSQL_SECRETS_FILE="$BASE_DIR/secrets/mysql.env"

generate_database_secret() {
    od -An -N32 -tx1 /dev/urandom | tr -d ' \n'
}

read_database_secret() {
    local key="$1"
    local value
    value=$(sed -n "s/^${key}=//p" "$MYSQL_SECRETS_FILE" | head -n 1)
    if [[ -z "$value" ]]; then
        echo "[ERROR] Missing $key in $MYSQL_SECRETS_FILE."
        exit 1
    fi
    printf '%s' "$value"
}

COMPOSER_CHECK_FILE="$BASE_DIR/config/composer_installed.flag"
LEGACY_COMPOSER_CHECK="$BASE_DIR/config/.composer_installed"
rm -f "$BASE_DIR/config/.force_composer_install" 2>/dev/null || true
if [[ -f "$LEGACY_COMPOSER_CHECK" && ! -f "$COMPOSER_CHECK_FILE" ]]; then
    mv "$LEGACY_COMPOSER_CHECK" "$COMPOSER_CHECK_FILE"
fi
if [[ ! -f "$COMPOSER_CHECK_FILE" ]]; then
    echo "[INFO] Composer dependencies not yet installed — will run on next container start."
else
    read -rp "Existing Composer install detected. Re-run install? (y/N): " COMPOSER_RERUN
    if [[ "$COMPOSER_RERUN" =~ ^[Yy]$ ]]; then
        rm -f "$COMPOSER_CHECK_FILE"
    fi
fi

# ─────────────────────────────────────────────
# Check if db-data volume exists (before containers start)
# ─────────────────────────────────────────────
DO_DB_SETUP=1
if ${DOCKER_CMD} volume ls --format '{{.Name}}' | grep -qi 'mtgc_db-data'; then
    echo "Existing DB volume found."
    DO_DB_SETUP=0
fi

if [[ "$DO_DB_SETUP" -eq 1 && ! -f "$MYSQL_SECRETS_FILE" ]]; then
    echo "Generating database credentials in $MYSQL_SECRETS_FILE..."
    umask 077
    {
        printf 'MYSQL_ROOT_PASSWORD=%s\n' "$(generate_database_secret)"
        printf 'MYSQL_DATABASE=mtg_new\n'
        printf 'MYSQL_USER=mtg\n'
        printf 'MYSQL_PASSWORD=%s\n' "$(generate_database_secret)"
    } > "$MYSQL_SECRETS_FILE"
    chmod 600 "$MYSQL_SECRETS_FILE"
elif [[ "$DO_DB_SETUP" -eq 0 && ! -f "$MYSQL_SECRETS_FILE" ]]; then
    echo "[ERROR] Existing database volume found, but $MYSQL_SECRETS_FILE is missing."
    echo "        Do not generate replacement credentials: they would not match the existing database."
    echo "        Restore this file from backup or create it with the database's current MYSQL_* values."
    exit 1
fi

DB_NAME=$(read_database_secret 'MYSQL_DATABASE')
DB_USER=$(read_database_secret 'MYSQL_USER')
DB_PASS=$(read_database_secret 'MYSQL_PASSWORD')
DB_ROOT_PASS=$(read_database_secret 'MYSQL_ROOT_PASSWORD')
DB_SERVER="db"

# Write Compose variables without including credential values.
cat <<EOF > "$ENV_FILE"
BASE_DIR=$BASE_DIR
WEB_PORT=$WEB_PORT
MYSQL_SECRETS_FILE=$MYSQL_SECRETS_FILE
EOF

# ─────────────────────────────────────────────
# Handle mtg_new.ini based on whether this is a fresh install
# ─────────────────────────────────────────────
if [[ "$DO_DB_SETUP" -eq 1 ]]; then
    echo "Fresh install detected — generating mtg_new.ini from template..."
    cp setup/mtg_new.ini "$BASE_DIR/config/mtg_new.ini"

    INI_FILE="$BASE_DIR/config/mtg_new.ini"

    # 1) Strip inline // comments that are preceded by whitespace
    #    (keeps URLs like https:// intact)
    sed -i -E 's/[[:space:]]+\/\/.*$//' "$INI_FILE"

    # 2) Strip trailing whitespace
    sed -i -E 's/[[:space:]]+$//' "$INI_FILE"

    # The app database credentials come from the protected host secrets file.
    # DBServer is the Compose service name.
    ESC_DB_NAME=$(printf '%s\n' "$DB_NAME" | sed 's/[&/]/\\&/g')
    ESC_DB_USER=$(printf '%s\n' "$DB_USER" | sed 's/[&/]/\\&/g')
    ESC_DB_PASS=$(printf '%s\n' "$DB_PASS" | sed 's/[&/]/\\&/g')

    # 4) Update [database] section
    sed -i -E "s/^DBServer[[:space:]]*=.*/DBServer    = \"${DB_SERVER}\"/" "$INI_FILE"
    sed -i -E "s/^DBUser[[:space:]]*=.*/DBUser      = \"${ESC_DB_USER}\"/" "$INI_FILE"
    sed -i -E "s/^DBPass[[:space:]]*=.*/DBPass      = \"${ESC_DB_PASS}\"/" "$INI_FILE"
    sed -i -E "s/^DBName[[:space:]]*=.*/DBName      = \"${ESC_DB_NAME}\"/" "$INI_FILE"

    # 5) Force FreecurrencyAPI to be empty
    sed -i -E 's/^FreecurrencyAPI[[:space:]]*=.*/FreecurrencyAPI = ""/' "$INI_FILE"

    # The ini contains database credentials after the substitutions above.
    chmod 600 "$INI_FILE"
else
    echo "Existing install detected — keeping mtg_new.ini unchanged."
fi

# php_custom.ini: only create if not present
if [[ ! -f "$BASE_DIR/config/php_custom.ini" ]]; then
    echo "Creating php config file from template..."
    cp setup/php_custom.ini "$BASE_DIR/config/php_custom.ini"
fi

# Copy helper shell script for cron/data workflows
SCRIPTS_DEST="$BASE_DIR/config/scripts"
mkdir -p "$SCRIPTS_DEST"
if [[ ! -f "$SCRIPTS_DEST/data_updates.sh" ]]; then
    cp setup/data_updates.sh "$SCRIPTS_DEST/data_updates.sh"
fi
CRON_TEMPLATE_DEST="$BASE_DIR/config/cron_mtgc"
if [[ ! -f "$CRON_TEMPLATE_DEST" ]]; then
    cp docker/cron_mtgc.example "$CRON_TEMPLATE_DEST"
fi

LOGROTATE_DEST="$BASE_DIR/config/logrotate-mtgc.conf"
if [[ ! -f "$LOGROTATE_DEST" ]]; then
    sed "s|__BASE_DIR__|/var/log/mtg|g" docker/logrotate-mtgc.conf > "$LOGROTATE_DEST"
fi
restore_host_permissions "$SCRIPTS_DEST"
chmod +x "$SCRIPTS_DEST"/*.sh

# ─────────────────────────────────────────────
# Start containers via compose
# ─────────────────────────────────────────────
export COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME:-mtgc}
(
    cd "$SCRIPT_DIR"
    ${COMPOSE_CMD} "${COMPOSE_ARGS[@]}" build --pull
    ${COMPOSE_CMD} "${COMPOSE_ARGS[@]}" up --build -d
)

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
# Ensure runtime directories are owned by www-data inside the container
# ─────────────────────────────────────────────
echo "Applying container permissions to mounted directories..."
${DOCKER_CMD} exec mtgc_web_1 bash -c \
    "chown -R www-data:www-data /mnt/data/cardimg /var/log/mtg && chmod -R u+rwX /mnt/data/cardimg /var/log/mtg"

# ─────────────────────────────────────────────
# If new DB, do full setup
# ─────────────────────────────────────────────
run_user_setup() {
    echo "Starting initial DB setup..."

    ${DOCKER_CMD} exec -e MYSQL_PWD="$DB_ROOT_PASS" mtgc_db_1 mysql -u root -e \
        "INSERT INTO mtg_new.admin (\`key\`, usemin, mtce) VALUES (1, 0, 1) ON DUPLICATE KEY UPDATE mtce=1;"

    ${DOCKER_CMD} exec -e MYSQL_PWD="$DB_ROOT_PASS" mtgc_db_1 mysql -u root -e \
        "SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE mtg_new.collection_values; TRUNCATE TABLE mtg_new.users; SET FOREIGN_KEY_CHECKS=1;"

    read -rp "Enter email address for admin user: " email
    echo "[INFO] This will be set as the site admin email address in mtg_new.ini."
    read -rp "Enter desired username (display only): " username
    read -rsp "Enter password: " password
    echo

    INI_FILE="$BASE_DIR/config/mtg_new.ini"
    if [[ -f "$INI_FILE" ]]; then
        ESC_EMAIL=$(printf '%s\n' "$email" | sed 's/[&/]/\\&/g')
        sed -i -E "s/^AdminEmail[[:space:]]*=.*/AdminEmail     = \"${ESC_EMAIL}\"/" "$INI_FILE"
    fi

    hashed=$(${DOCKER_CMD} exec mtgc_web_1 php /var/www/mtgnew/setup/initial.php "$username" "$password" \
        | grep "Hashed password:" | awk -F': ' '{print $2}' | xargs)

    if [[ -n "$hashed" ]]; then
        ${DOCKER_CMD} exec -e MYSQL_PWD="$DB_ROOT_PASS" mtgc_db_1 mysql -u root -e \
            "INSERT INTO mtg_new.users (username, email, password, reg_date, status) VALUES ('$username', '$email', '$hashed', NOW(), 'active');"
        ${DOCKER_CMD} exec -e MYSQL_PWD="$DB_ROOT_PASS" mtgc_db_1 mysql -u root -e \
            "UPDATE mtg_new.users SET admin=1 WHERE username='$username';"
        ${DOCKER_CMD} exec -e MYSQL_PWD="$DB_ROOT_PASS" mtgc_db_1 mysql -u root -e \
            "INSERT INTO mtg_new.groups (groupnumber, groupname, owner) VALUES (1, 'Masters', 1) ON DUPLICATE KEY UPDATE groupname='Masters';"
    else
        echo "[ERROR] Failed to get hashed password."
        exit 1
    fi
}

if [[ "$DO_DB_SETUP" -eq 1 ]]; then
    run_user_setup
else
    echo "MySQL is available. Skipping user/admin setup - database volume already exists."
    read -rp "Do you want to re-run user setup anyway? (y/N): " rerun_reply
    if [[ "$rerun_reply" =~ ^[Yy]$ ]]; then
        run_user_setup
    fi
fi

# ─────────────────────────────────────────────
# Run bulk import if not already done
# ─────────────────────────────────────────────
marker_exists() {
    ${DOCKER_CMD} exec mtgc_web_1 bash -c "[ -f /var/log/mtg/scryfall_import_done ]"
}

if ! marker_exists; then
    echo "Running bulk Scryfall import - this may take up to 2 hours..."
    ${DOCKER_CMD} exec mtgc_web_1 bash -c "/opt/mtg/scripts/data_updates.sh new"
    ${DOCKER_CMD} exec mtgc_web_1 bash -c "printf 'done\n' > /var/log/mtg/scryfall_import_done"
else
    echo "Bulk import already completed previously - skipping."
fi

# ─────────────────────────────────────────────
# Hand config directory ownership back to container user
# ─────────────────────────────────────────────
echo "Finalising config directory ownership for container runtime..."
${DOCKER_CMD} exec mtgc_web_1 bash -c \
    "chown -R www-data:www-data /mnt/data/config \
    && chmod -R u+rwX /mnt/data/config \
    && chmod 700 /mnt/data/config \
    && if [ -f /mnt/data/config/mtg_new.ini ]; then chmod 600 /mnt/data/config/mtg_new.ini; fi"

# ─────────────────────────────────────────────
# Clear maintenance mode
# ─────────────────────────────────────────────
${DOCKER_CMD} exec -e MYSQL_PWD="$DB_ROOT_PASS" mtgc_db_1 mysql -u root -e \
    "INSERT INTO mtg_new.admin (\`key\`, usemin, mtce) VALUES (1, 0, 0) ON DUPLICATE KEY UPDATE mtce=0;"

echo "✅ Setup complete. You can now log in via http://localhost:${WEB_PORT}"
