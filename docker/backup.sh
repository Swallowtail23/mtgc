#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"
GIT_DESCRIBE=$(git -C "$PROJECT_ROOT" describe --tags --always 2>/dev/null || echo "unknown")

if [[ ! -f "$ENV_FILE" ]]; then
    echo "[ERROR] docker/.env not found. Run docker-init.sh first."
    exit 1
fi

source "$ENV_FILE"

if [[ -z "${BASE_DIR:-}" ]]; then
    echo "[ERROR] BASE_DIR missing in docker/.env"
    exit 1
fi

BACKUP_ROOT="${BACKUP_ROOT:-$PROJECT_ROOT/backups}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DEST_DIR="$BACKUP_ROOT/${GIT_DESCRIBE}_$TIMESTAMP"
MYSQL_DUMP="$DEST_DIR/mtgc.sql.gz"
FILES_ARCHIVE="$DEST_DIR/mtgc_files.tar.gz"

mkdir -p "$DEST_DIR"

echo "[INFO] Dumping MySQL database..."
podman exec mtgc_db_1 mysqldump -u root -prootpass --databases mtg_new \
    | gzip > "$MYSQL_DUMP"

echo "[INFO] Archiving ${BASE_DIR} (config/logs/cardimg metadata)..."
tar -C "${BASE_DIR%/*}" -czf "$FILES_ARCHIVE" "$(basename "$BASE_DIR")/config" "$(basename "$BASE_DIR")/logs"

echo "[INFO] Backup complete: $DEST_DIR"
