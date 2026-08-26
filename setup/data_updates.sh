#!/bin/bash
set -euo pipefail

mode="${1:-nightly}"
confirm="${2:-}"
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
app_root_source="${MTG_APP_ROOT:-${APACHE_DOCUMENT_ROOT:-${script_dir}/..}}"

if [[ ! -d "${app_root_source}" ]]; then
    echo "Application root does not exist: ${app_root_source}" >&2
    echo "Set MTG_APP_ROOT when data_updates.sh is installed outside the application tree." >&2
    exit 2
fi

app_root="$(cd -- "${app_root_source}" && pwd)"
if [[ ! -d "${app_root}/bulk" || ! -f "${app_root}/bootstrap.php" ]]; then
    echo "Application root is invalid: ${app_root}" >&2
    echo "Set MTG_APP_ROOT when data_updates.sh is installed outside the application tree." >&2
    exit 2
fi

cd "${app_root}"

run_bulk_php() {
    local script="$1"
    shift || true
    echo "[$(date -Is)] running php ./bulk/${script} $*"
    php "./bulk/${script}" "$@"
}

echo "[$(date -Is)] data_updates.sh started (${mode})"

case "${mode}" in
    new)
        run_bulk_php scryfall_sets.php
        run_bulk_php scryfall_bulk.php refresh
        run_bulk_php scryfall_rulings.php
        run_bulk_php scryfall_bulk.php tags
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
        run_bulk_php scryfall_bulk.php tags
        run_bulk_php scryfall_migrations.php
        run_bulk_php scryfall_manifest.php
        run_bulk_php scryfall_sync_state.php data-backfill
        ;;
    nightly)
        run_bulk_php scryfall_sets.php
        run_bulk_php scryfall_bulk.php default
        run_bulk_php scryfall_rulings.php
        run_bulk_php scryfall_bulk.php tags
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
        run_bulk_php scryfall_bulk.php tags
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
