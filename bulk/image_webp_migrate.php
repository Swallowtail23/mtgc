<?php

/*
Version:     1.5
Date:        17/09/26
Name:        image_webp_migrate.php
Purpose:     Migrates card images to WebP and validates the card-image cache.
Notes:       JPEG and stale-file deletion each require an explicit option.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\ImageManager;
use MTG\Cards\ImageCacheCleaner;
use MTG\Cards\ImageWebpMigrator;
use MTG\Core\Validation;

if (PHP_SAPI !== 'cli') :
    http_response_code(403);
    exit("This utility can only be run from the command line.\n");
endif;

// Options are parsed before the application bootstrap so --help stays lightweight.
require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * @return array{
 *     dry_run: bool,
 *     delete_jpeg: bool,
 *     cleanup_stale: bool,
 *     delete_stale: bool,
 *     batch_size: int,
 *     limit: ?int,
 *     after: string,
 *     help: bool
 * }
 */
function parseImageWebpMigrationOptions(): array
{
    $options = getopt(
        '',
        ['dry-run', 'delete-jpeg', 'cleanup-stale', 'delete-stale', 'batch-size:', 'limit:', 'after:', 'help']
    );
    if ($options === false) :
        throw new InvalidArgumentException('Unable to parse command-line options');
    endif;

    $limit = null;
    if (isset($options['limit'])) :
        $limitValue = (string) $options['limit'];
        if (!preg_match('/^[0-9]+$/', $limitValue) || (int) $limitValue < 1) :
            throw new InvalidArgumentException('--limit must be a positive integer');
        endif;
        $limit = (int) $limitValue;
    endif;

    $batchSize = 100;
    if (isset($options['batch-size'])) :
        $batchValue = (string) $options['batch-size'];
        if (!preg_match('/^[0-9]+$/', $batchValue)) :
            throw new InvalidArgumentException('--batch-size must be a positive integer');
        endif;
        $batchSize = (int) $batchValue;
        if ($batchSize < 1 || $batchSize > 1000) :
            throw new InvalidArgumentException('--batch-size must be between 1 and 1000');
        endif;
    endif;

    $after = isset($options['after']) ? trim((string) $options['after']) : '';
    if ($after !== '' && Validation::validUUID($after) === false) :
        throw new InvalidArgumentException('--after must be a valid card UUID');
    endif;

    $cleanupStale = isset($options['cleanup-stale']);
    $deleteStale = isset($options['delete-stale']);
    if ($deleteStale && !$cleanupStale) :
        throw new InvalidArgumentException('--delete-stale requires --cleanup-stale');
    endif;
    if ($cleanupStale && isset($options['delete-jpeg'])) :
        throw new InvalidArgumentException('--cleanup-stale cannot be combined with --delete-jpeg');
    endif;
    if ($cleanupStale && $after !== '') :
        throw new InvalidArgumentException('--after is not supported with --cleanup-stale');
    endif;
    if ($deleteStale && isset($options['dry-run'])) :
        throw new InvalidArgumentException('--delete-stale cannot be combined with --dry-run');
    endif;

    return [
        'dry_run' => isset($options['dry-run']),
        'delete_jpeg' => isset($options['delete-jpeg']),
        'cleanup_stale' => $cleanupStale,
        'delete_stale' => $deleteStale,
        'batch_size' => $batchSize,
        'limit' => $limit,
        'after' => $after,
        'help' => isset($options['help']),
    ];
}

function printImageWebpMigrationUsage(): void
{
    echo "Usage: php bulk/image_webp_migrate.php [options]\n\n";
    echo "Fetch remote WebP variants or validate cached card-image paths.\n";
    echo "\nOptions:\n";
    echo "  --dry-run       Scan and report candidates without downloading or deleting\n";
    echo "  --delete-jpeg   Delete each JPEG only after a valid WebP is stored\n";
    echo "  --cleanup-stale Validate .jpg/.webp cache paths against cards_scry (report-only by default)\n";
    echo "  --delete-stale  Delete files reported by --cleanup-stale\n";
    echo "  --batch-size=N  Process N records or cache files per database batch (default: 100)\n";
    echo "  --limit=N       Stop after at most N card records or cache files\n";
    echo "  --after=UUID    Resume after this card UUID\n";
    echo "  --help          Show this help\n";
}

try {
    $options = parseImageWebpMigrationOptions();
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, "Error: {$exception->getMessage()}\n");
    printImageWebpMigrationUsage();
    exit(2);
}

if ($options['help']) :
    printImageWebpMigrationUsage();
    exit(0);
endif;

$ctx = require __DIR__ . '/bulk_ini.php';
$appConfig = $ctx->config();
$db = $ctx->db();
$msg = $ctx->message();
$gameRules = $ctx->rules();

$imageBaseDir = (string) $appConfig->general('imageBaseDir', '');
if ($imageBaseDir === '') :
    $msg->logMessage('[ERROR]', 'Image migration cannot run without general.imageBaseDir');
    fwrite(STDERR, "Error: imageBaseDir is not configured.\n");
    exit(1);
endif;

$cleanupMode = $options['cleanup_stale'];
$deleteLabel = $cleanupMode
    ? ($options['delete_stale'] ? 'enabled' : 'disabled')
    : ($options['delete_jpeg'] ? 'enabled' : 'disabled');
$modeLabel = $cleanupMode ? 'stale-cache validation' : ($options['dry_run'] ? 'dry-run' : 'remote-download');
$limitUnit = $cleanupMode ? 'cache files' : 'cards';
$limitLabel = $options['limit'] === null ? "all $limitUnit" : (string) $options['limit'] . " $limitUnit maximum";
$msg->logMessage(
    '[NOTICE]',
    "Starting card image utility ($modeLabel; deletion $deleteLabel; "
    . "batch {$options['batch_size']}; limit $limitLabel)"
);

try {
    if ($cleanupMode) :
        $cleaner = new ImageCacheCleaner($db, $msg, $imageBaseDir);
        $stats = $cleaner->run($options['batch_size'], $options['delete_stale'], $options['limit']);
    else :
        $imageManager = new ImageManager($db, $appConfig, $gameRules);
        $migrator = new ImageWebpMigrator($db, $imageManager, $msg);
        $stats = $migrator->run(
            $options['after'],
            $options['batch_size'],
            $options['delete_jpeg'],
            $options['dry_run'],
            $options['limit']
        );
    endif;
} catch (Throwable $exception) {
    $msg->logMessage('[ERROR]', "Card image utility failed: {$exception->getMessage()}");
    fwrite(STDERR, "Card image utility failed: {$exception->getMessage()}\n");
    exit(1);
}

if ($cleanupMode) :
    foreach ($stats['stale_entries'] as $entry) :
        $status = 'STALE';
        if ($entry['deleted']) :
            $status = 'DELETED';
        elseif ($entry['delete_failed']) :
            $status = 'DELETE FAILED';
        endif;
        printf(
            "%s [%s] %s (%d bytes)\n",
            $status,
            str_replace('_', ' ', $entry['reason']),
            $entry['relative_path'],
            $entry['bytes']
        );
    endforeach;

    $summary = sprintf(
        "Card image cache validation complete. Files seen: %d; images checked: %d; valid: %d; "
        . "stale: %d (missing card: %d; wrong set: %d; invalid path: %d); skipped: %d; "
        . "stale bytes: %d; deleted: %d; deleted bytes: %d; delete failures: %d",
        $stats['files_seen'],
        $stats['image_files_checked'],
        $stats['files_valid'],
        $stats['files_stale'],
        $stats['stale_missing_card'],
        $stats['stale_wrong_set'],
        $stats['stale_invalid_path'],
        $stats['files_skipped'],
        $stats['stale_bytes'],
        $stats['files_deleted'],
        $stats['bytes_deleted'],
        $stats['delete_failures']
    );
    $msg->logMessage('[NOTICE]', $summary);
    echo $summary . "\n";
    exit($stats['delete_failures'] > 0 ? 1 : 0);
endif;

$summary = sprintf(
    "Card WebP migration complete. Cards: %d; converted: %d; already WebP: %d; "
    . "dry-run: %d; missing JPEG: %d; missing WebP source: %d; failed: %d; "
    . "JPEGs deleted: %d; cleanup failures: %d; legacy JPEG bytes found: %d; "
    . "candidate JPEG bytes: %d; converted JPEG bytes: %d; deleted JPEG bytes: %d; "
    . "existing WebP bytes: %d; downloaded WebP bytes: %d; database paths updated: %d; "
    . "database update failures: %d; last ID: %s",
    $stats['cards_seen'],
    $stats['faces_converted'],
    $stats['faces_already_webp'],
    $stats['faces_dry_run'],
    $stats['faces_missing_jpeg'],
    $stats['faces_missing_webp_source'],
    $stats['faces_failed'],
    $stats['jpeg_deleted'],
    $stats['cleanup_failed'],
    $stats['jpeg_bytes_found'],
    $stats['jpeg_candidate_bytes'],
    $stats['jpeg_bytes_converted'],
    $stats['jpeg_bytes_deleted'],
    $stats['webp_bytes_existing'],
    $stats['webp_bytes_downloaded'],
    $stats['database_paths_updated'],
    $stats['database_update_failures'],
    $stats['last_id'] === '' ? '(none)' : $stats['last_id']
);
$msg->logMessage('[NOTICE]', $summary);
echo $summary . "\n";

if (
    $stats['faces_failed'] > 0
    || $stats['cleanup_failed'] > 0
    || $stats['database_update_failures'] > 0
) :
    exit(1);
endif;
