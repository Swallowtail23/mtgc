<?php

/*
Version:     1.4
Date:        16/09/26
Name:        image_webp_migrate.php
Purpose:     Populate the card image cache with remote WebP variants.
Notes:       JPEG deletion requires the explicit --delete-jpeg option.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\ImageManager;
use MTG\Cards\ImageWebpMigrator;
use MTG\Core\Validation;

if (PHP_SAPI !== 'cli') :
    http_response_code(403);
    exit("This utility can only be run from the command line.\n");
endif;

// Options are parsed before the application bootstrap so --help stays lightweight.
require_once dirname(__DIR__) . '/vendor/autoload.php';

/** @return array{dry_run: bool, delete_jpeg: bool, batch_size: int, limit: ?int, after: string, help: bool} */
function parseImageWebpMigrationOptions(): array
{
    $options = getopt('', ['dry-run', 'delete-jpeg', 'batch-size:', 'limit:', 'after:', 'help']);
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

    return [
        'dry_run' => isset($options['dry-run']),
        'delete_jpeg' => isset($options['delete-jpeg']),
        'batch_size' => $batchSize,
        'limit' => $limit,
        'after' => $after,
        'help' => isset($options['help']),
    ];
}

function printImageWebpMigrationUsage(): void
{
    echo "Usage: php bulk/image_webp_migrate.php [options]\n\n";
    echo "Fetch remote WebP variants for existing card JPEG cache files.\n";
    echo "\nOptions:\n";
    echo "  --dry-run       Scan and report candidates without downloading or deleting\n";
    echo "  --delete-jpeg   Delete each JPEG only after a valid WebP is stored\n";
    echo "  --batch-size=N  Process N card records per database batch (default: 100)\n";
    echo "  --limit=N       Stop after at most N card records\n";
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

$deleteLabel = $options['delete_jpeg'] ? 'enabled' : 'disabled';
$modeLabel = $options['dry_run'] ? 'dry-run' : 'remote-download';
$limitLabel = $options['limit'] === null ? 'all cards' : (string) $options['limit'] . ' cards maximum';
$msg->logMessage(
    '[NOTICE]',
    "Starting card WebP migration ($modeLabel; JPEG deletion $deleteLabel; "
    . "batch {$options['batch_size']}; limit $limitLabel)"
);

try {
    $imageManager = new ImageManager($db, $appConfig, $gameRules);
    $migrator = new ImageWebpMigrator($db, $imageManager, $msg);
    $stats = $migrator->run(
        $options['after'],
        $options['batch_size'],
        $options['delete_jpeg'],
        $options['dry_run'],
        $options['limit']
    );
} catch (Throwable $exception) {
    $msg->logMessage('[ERROR]', "Card WebP migration failed: {$exception->getMessage()}");
    fwrite(STDERR, "Card WebP migration failed: {$exception->getMessage()}\n");
    exit(1);
}

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
