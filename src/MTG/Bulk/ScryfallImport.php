<?php

/*
Version:     1.21
Date:        08/07/26
Name:        ScryfallImport.php
Purpose:     Scryfall bulk import helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use Generator;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\Message;

class ScryfallImport
{
    public static function downloadBulk(
        string $url,
        string $dest,
        Message $msg,
        AppConfig $appConfig,
        string $context = 'downloadBulk',
        bool $debug = false
    ): bool {
        return ScryfallBulkFiles::downloadBulk($url, $dest, $msg, $appConfig, $context, $debug);
    }

    public static function fetchJson(string $url, Message $msg, string $context, AppConfig $appConfig): array|false
    {
        return ScryfallBulkFiles::fetchJson($url, $msg, $context, $appConfig);
    }

    public static function getBulkInfo(string $type, AppConfig $appConfig, GameRules $gameRules): array|false
    {
        return ScryfallBulkFiles::getBulkInfo($type, $appConfig, $gameRules, [static::class, 'fetchJson']);
    }

    public static function getBulkDataFile(
        string $uri,
        string $file_location,
        int $max_fileage,
        AppConfig $appConfig
    ): string|false {
        return ScryfallBulkFiles::getBulkDataFile(
            $uri,
            $file_location,
            $max_fileage,
            $appConfig,
            [static::class, 'downloadBulk']
        );
    }

    public static function getBulkJson(
        string $uri,
        string $file_location,
        int $max_fileage,
        AppConfig $appConfig
    ): string|false {
        return static::getBulkDataFile($uri, $file_location, $max_fileage, $appConfig);
    }

    /**
     * @return Generator<int|string, array<string, mixed>>
     */
    public static function iterateBulkRecords(string $fileLocation): Generator
    {
        yield from ScryfallBulkFiles::iterateBulkRecords($fileLocation);
    }

    public static function scryfallImport(
        string $file_location,
        string $type,
        string $tableName,
        mixed $db,
        AppConfig $appConfig,
        GameRules $gameRules,
        ?array &$stats = null
    ): string|false {
        return ScryfallCardImportRunner::run(
            $file_location,
            $type,
            $tableName,
            $db,
            $appConfig,
            $gameRules,
            $stats,
            func_num_args() >= 7
        );
    }

    /**
    * @param \mysqli|object $db
    * @return array<string, mixed>
    */
    public static function importTags(
        string $mode,
        $db,
        AppConfig $appConfig,
        GameRules $gameRules,
        Message $msg
    ): array {
        return ScryfallTagImport::import($mode, $db, $appConfig, $gameRules, $msg);
    }

    /**
    * @param \mysqli|object $db
    */
    public static function backfillDataSyncState($db, Message $msg): int
    {
        return ScryfallSyncStateUpdater::backfillData($db, $msg);
    }
}
