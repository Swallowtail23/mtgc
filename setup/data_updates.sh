#!/bin/bash
set -euo pipefail

mode="${1:-nightly}"
confirm="${2:-}"

run_bulk_php() {
    local script="$1"
    shift || true
    echo "[$(date -Is)] running php ./bulk/${script} $*"
    cd /var/www/mtgnew
    php "./bulk/${script}" "$@"
}

echo "[$(date -Is)] data_updates.sh started (${mode})"

case "${mode}" in
    new)
        run_bulk_php scryfall_sets.php
        run_bulk_php scryfall_bulk.php refresh
        run_bulk_php scryfall_rulings.php
        run_bulk_php scryfall_migrations.php
        run_bulk_php scryfall_manifest.php
        run_bulk_php scryfall_sync_state.php data-backfill
        ;;
    refresh)
        if [[ "${confirm}" != "--confirm" ]]; then
            echo "refresh is destructive. Re-run as: $0 refresh --confirm" >&2
            exit 2
        fi
        run_bulk_php scryfall_reset_data.php confirm
        run_bulk_php scryfall_sets.php
        run_bulk_php scryfall_bulk.php refresh
        run_bulk_php scryfall_rulings.php
        run_bulk_php scryfall_migrations.php
        run_bulk_php scryfall_manifest.php
        run_bulk_php scryfall_sync_state.php data-backfill
        ;;
    nightly)
        run_bulk_php scryfall_sets.php
        run_bulk_php scryfall_bulk.php default
        run_bulk_php scryfall_rulings.php
        run_bulk_php scryfall_migrations.php
        run_bulk_php scryfall_manifest.php
        run_bulk_php collection_snapshots.php
        run_bulk_php cleanup_tokens.php
        ;;
    weekly)
        run_bulk_php scryfall_sets.php
        run_bulk_php scryfall_bulk.php all
        run_bulk_php scryfall_bulk.php default
        run_bulk_php scryfall_rulings.php
        run_bulk_php scryfall_migrations.php
        run_bulk_php scryfall_manifest.php
        run_bulk_php weekly_exports.php
        run_bulk_php collection_snapshots.php
        run_bulk_php cleanup_tokens.php
        ;;
    *)
        echo "Usage: $0 {new|refresh --confirm|nightly|weekly}" >&2
        exit 2
        ;;
esac

echo "[$(date -Is)] data_updates.sh completed (${mode})"
