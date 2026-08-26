#!/bin/bash

# Version:     1.0
# Date:        26/08/26
# Name:        backup.sh
# Purpose:     Back up the containerized MTG database and persistent host files.
# Notes:       Supports Docker and Podman hosts.
# Author:      Simon Wilson
# Copyright:   2026 MTG Collection
# To do:       -

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="${ENV_FILE:-$SCRIPT_DIR/.env}"
GIT_DESCRIBE=$(git -C "$PROJECT_ROOT" describe --tags --always 2>/dev/null || echo "unknown")
DB_CONTAINER="${DB_CONTAINER:-mtgc_db_1}"

container_is_running() {
    local engine="$1"

    command -v "$engine" >/dev/null 2>&1 || return 1
    [[ "$("$engine" inspect --format '{{.State.Running}}' "$DB_CONTAINER" 2>/dev/null)" == "true" ]]
}

select_container_engine() {
    local requested_engine="${CONTAINER_ENGINE:-}"
    local docker_running="false"
    local podman_running="false"

    if [[ -n "$requested_engine" ]]; then
        case "$requested_engine" in
            docker|podman)
                ;;
            *)
                echo "[ERROR] CONTAINER_ENGINE must be 'docker' or 'podman'." >&2
                return 1
                ;;
        esac

        if ! command -v "$requested_engine" >/dev/null 2>&1; then
            echo "[ERROR] Requested container engine '$requested_engine' was not found in PATH." >&2
            return 1
        fi
        if ! container_is_running "$requested_engine"; then
            echo "[ERROR] Container '$DB_CONTAINER' is not running under $requested_engine." >&2
            return 1
        fi

        echo "$requested_engine"
        return
    fi

    if container_is_running docker; then
        docker_running="true"
    fi
    if container_is_running podman; then
        podman_running="true"
    fi

    case "$docker_running:$podman_running" in
        true:false)
            echo "docker"
            ;;
        false:true)
            echo "podman"
            ;;
        true:true)
            echo "[ERROR] Container '$DB_CONTAINER' is running under both Docker and Podman." >&2
            echo "        Set CONTAINER_ENGINE=docker or CONTAINER_ENGINE=podman explicitly." >&2
            return 1
            ;;
        false:false)
            echo "[ERROR] Could not find a running '$DB_CONTAINER' container under Docker or Podman." >&2
            echo "        Start the stack, or set DB_CONTAINER if it uses a different name." >&2
            return 1
            ;;
    esac
}

if [[ ! -f "$ENV_FILE" ]]; then
    echo "[ERROR] docker/.env not found. Run docker-init.sh first."
    exit 1
fi

source "$ENV_FILE"

if [[ -z "${BASE_DIR:-}" || -z "${MYSQL_SECRETS_FILE:-}" ]]; then
    echo "[ERROR] BASE_DIR or MYSQL_SECRETS_FILE missing in docker/.env"
    exit 1
fi
if [[ ! -r "$MYSQL_SECRETS_FILE" ]]; then
    echo "[ERROR] Database secrets file is not readable: $MYSQL_SECRETS_FILE"
    exit 1
fi

CONTAINER_ENGINE=$(select_container_engine)
echo "[INFO] Using container engine: $CONTAINER_ENGINE"
echo "[INFO] Using database container: $DB_CONTAINER"

MYSQL_ROOT_PASSWORD=$(sed -n 's/^MYSQL_ROOT_PASSWORD=//p' "$MYSQL_SECRETS_FILE" | head -n 1)
if [[ -z "$MYSQL_ROOT_PASSWORD" ]]; then
    echo "[ERROR] MYSQL_ROOT_PASSWORD missing in $MYSQL_SECRETS_FILE"
    exit 1
fi

BACKUP_ROOT="${BACKUP_ROOT:-$PROJECT_ROOT/backups}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DEST_DIR="$BACKUP_ROOT/${GIT_DESCRIBE}_$TIMESTAMP"
MYSQL_DUMP="$DEST_DIR/mtgc.sql.gz"
FILES_ARCHIVE="$DEST_DIR/mtgc_files.tar.gz"

mkdir -p "$DEST_DIR"

echo "[INFO] Dumping MySQL database..."
"$CONTAINER_ENGINE" exec -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" "$DB_CONTAINER" \
    mysqldump -u root --databases mtg_new \
    | gzip > "$MYSQL_DUMP"

echo "[INFO] Archiving ${BASE_DIR} (config, secrets, logs, and card-image metadata)..."
tar -C "${BASE_DIR%/*}" -czf "$FILES_ARCHIVE" \
    "$(basename "$BASE_DIR")/config" \
    "$(basename "$BASE_DIR")/secrets" \
    "$(basename "$BASE_DIR")/logs"

echo "[INFO] Backup complete: $DEST_DIR"
