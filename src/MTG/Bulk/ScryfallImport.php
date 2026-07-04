<?php

/*
Version:     1.13
Date:        04/07/26
Name:        ScryfallImport.php
Purpose:     Scryfall bulk import helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Generator;
use Throwable;
use MTG\Cards\ImageManager;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\Message;
use MTG\Core\UserAgent;

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
        $dir = dirname($dest);
        if (!is_dir($dir)) :
            if (!mkdir($dir, 0775, true)) :
                $msg->logMessage('[ERROR]', "$context: unable to create directory $dir");
                return false;
            endif;
        endif;

        $userAgent = UserAgent::buildFromConfig($appConfig, null, $msg);
        $tmp = $dest . '.tmp';
        if (is_file($tmp)) :
            @unlink($tmp);
        endif;

        $logfp = @fopen($tmp, 'wb');
        if ($logfp === false) :
            $msg->logMessage('[ERROR]', "$context: failed to open temp file for download: $tmp");
            return false;
        endif;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $logfp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        if ($debug === true) :
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        endif;

        $ok = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        curl_close($ch);
        if (is_resource($logfp)) :
            fclose($logfp);
        endif;

        if ($ok === false) :
            @unlink($tmp);
            $msg->logMessage('[ERROR]', "$context: curl download failed (HTTP $httpCode): $err");
            return false;
        endif;

        // Basic sanity: must be non-zero
        if (!is_file($tmp) || filesize($tmp) === 0) :
            @unlink($tmp);
            $msg->logMessage('[ERROR]', "$context: download produced empty file: $tmp");
            return false;
        endif;

        // Atomic replace
        if (!rename($tmp, $dest)) :
            @unlink($tmp);
            $msg->logMessage('[ERROR]', "$context: failed to move temp file into place: $tmp -> $dest");
            return false;
        endif;

        return true;
    }

    public static function fetchJson(string $url, Message $msg, string $context, AppConfig $appConfig): array|false
    {
        $userAgent = UserAgent::buildFromConfig($appConfig, null, $msg);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json;q=0.9,*/*;q=0.8"));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($body === false) :
            $msg->logMessage(
                '[ERROR]',
                "$context: curl_exec failed (HTTP $httpCode): " . curl_error($ch)
            );
            curl_close($ch);
            return false;
        endif;

        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) :
            $msg->logMessage('[ERROR]', "$context: HTTP $httpCode from $url");
            return false;
        endif;

        $data = json_decode($body, true);
        if (!is_array($data)) :
            $msg->logMessage(
                '[ERROR]',
                "$context: JSON decode failed: " . json_last_error_msg()
            );
            return false;
        endif;

        return $data;
    }

    public static function getBulkInfo(string $type, AppConfig $appConfig, GameRules $gameRules): array|false
    {
        // Function to return the URI for the Scryfall bulk data file, and the file location where it needs to go
        $defaultCardsUrl = static::requireGameRuleUrl($gameRules, 'defaultCardsUrl');
        $allCardsUrl = static::requireGameRuleUrl($gameRules, 'allCardsUrl');
        $imgLocation = (string) $appConfig->general('imageBaseDir', '');
        $msg = new Message($appConfig);
        $bulkInfo = false;

        $url = $urlDefault = $urlAll = $fileLocation = $fileLocationDefault = $fileLocationAll = '';
        $scryfallBulk = $scryfallBulkDefault = $scryfallBulkAll = null;
        $msg->logMessage('[NOTICE]', "scryfall bulk API: called with '$type'");

        if ($type === "all") :
            $url = $allCardsUrl;
            $fileLocation = $imgLocation . 'json/bulk_all.jsonl.gz';
        elseif ($type === "default") :  // At the moment, elseif and else do the same, i.e. a "primary" load only
            $url = $defaultCardsUrl;
            $fileLocation = $imgLocation . 'json/bulk.jsonl.gz';
        elseif ($type === "refresh") :
            $urlDefault = $defaultCardsUrl;
            $urlAll = $allCardsUrl;
            $fileLocationDefault = $imgLocation . 'json/bulk.jsonl.gz';
            $fileLocationAll = $imgLocation . 'json/bulk_all.jsonl.gz';
        else :  // At the moment, else does a "default" load only - catches anything else
            $type = "default";
            $url = $defaultCardsUrl;
            $fileLocation = $imgLocation . 'json/bulk.jsonl.gz';
        endif;

        if (!empty($url) && !empty($fileLocation)) :
            $msg->logMessage('[NOTICE]', "Scryfall bulk API: fetching current URL $url");
            $scryfallBulk = static::fetchJson($url, $msg, 'Scryfall bulk API', $appConfig);
            if ($scryfallBulk === false) :
                return false;
            endif;
        elseif (
            !empty($urlDefault)
            && !empty($urlAll)
            && !empty($fileLocationDefault)
            && !empty($fileLocationAll)
        ) :
            // Run twice, once for each file and location
            $msg->logMessage('[NOTICE]', "Scryfall bulk API: fetching current URL $urlDefault");
            $scryfallBulkDefault = static::fetchJson($urlDefault, $msg, 'Scryfall bulk API', $appConfig);
            if ($scryfallBulkDefault === false) :
                return false;
            endif;
            $msg->logMessage('[NOTICE]', "Scryfall bulk API: fetching current URL $urlAll");
            $scryfallBulkAll = static::fetchJson($urlAll, $msg, 'Scryfall bulk API', $appConfig);
            if ($scryfallBulkAll === false) :
                return false;
            endif;
        else :
            $msg->logMessage('[ERROR]', "Scryfall bulk API: failed");
            return false;
        endif;
        if (
            isset($scryfallBulk['type'])
            && in_array($scryfallBulk['type'], ['default_cards', 'all_cards'], true)
        ) :
            if ($type === 'all' && $scryfallBulk['type'] !== 'all_cards') :
                $msg->logMessage('[ERROR]', "Scryfall bulk API: expected all_cards, got {$scryfallBulk['type']}");
                return false;
            endif;
            if ($type === 'default' && $scryfallBulk['type'] !== 'default_cards') :
                $msg->logMessage('[ERROR]', "Scryfall bulk API: expected default_cards, got {$scryfallBulk['type']}");
                return false;
            endif;
            if (isset($scryfallBulk["jsonl_download_uri"])) :
                $bulk_uri = $scryfallBulk["jsonl_download_uri"];
                $msg->logMessage('[NOTICE]', "Scryfall bulk API: Download URI: $bulk_uri");
                $bulkInfo = [
                    'bulkUrl' => $bulk_uri,
                    'fileLocation' => $fileLocation
                ];
            else :
                $msg->logMessage('[ERROR]', "Scryfall bulk API jsonl_download_uri not available");
                return false;
            endif;
        elseif (
            isset($scryfallBulkDefault['type'], $scryfallBulkAll['type'])
            && $scryfallBulkDefault['type'] === 'default_cards'
            && $scryfallBulkAll['type'] === 'all_cards'
        ) :
            if (isset($scryfallBulkDefault["jsonl_download_uri"])) :
                $bulk_uri_default = $scryfallBulkDefault["jsonl_download_uri"];
                $msg->logMessage('[NOTICE]', "Scryfall bulk API: Download URI: $bulk_uri_default");
            else :
                $msg->logMessage('[ERROR]', "Scryfall bulk API: default_cards jsonl_download_uri not available");
                return false;
            endif;
            if (isset($scryfallBulkAll["jsonl_download_uri"])) :
                $bulk_uri_all = $scryfallBulkAll["jsonl_download_uri"];
                $msg->logMessage('[NOTICE]', "Scryfall bulk API: Download URI: $bulk_uri_all");
            else :
                $msg->logMessage('[ERROR]', "Scryfall bulk API: all_cards jsonl_download_uri not available");
                return false;
            endif;
            $bulkInfo = [
                'bulkUrlDefault' => $bulk_uri_default,
                'fileLocationDefault' => $fileLocationDefault,
                'bulkUrlAll' => $bulk_uri_all,
                'fileLocationAll' => $fileLocationAll,
            ];
        else :
            $msg->logMessage('[ERROR]', "Scryfall bulk API info not available");
            return false;
        endif;

        return $bulkInfo;
    }

    private static function requireGameRuleUrl(GameRules $gameRules, string $key): string
    {
        $value = $gameRules->get($key);
        if (!is_string($value) || trim($value) === '') :
            throw new \InvalidArgumentException(
                "Missing Scryfall game rule '$key'. Define it in includes/game_rules.php."
            );
        endif;

        return trim($value);
    }

    public static function getBulkDataFile(
        string $uri,
        string $file_location,
        int $max_fileage,
        AppConfig $appConfig
    ): string|false
    {
        // Function to download and save bulk Scryfall data files
        $msg = new Message($appConfig);

        $shouldDownload = true;
        $reason = '';

        if (is_file($file_location)) :
            $size = filesize($file_location);
            if ($size > 0) :
                $mtime = filemtime($file_location);
                $fileDate = date('d-m-Y H:i', $mtime);

                if ((time() - $mtime) > $max_fileage) :
                    $shouldDownload = true;
                    $reason = "File old ($fileDate), downloading: $uri";
                else :
                    $shouldDownload = false;
                    $msg->logMessage(
                        '[NOTICE]',
                        "Scryfall bulk API: File fresh ($file_location, $fileDate, $size), skipping download"
                    );
                endif;
            else :
                $shouldDownload = true;
                $reason = "0-byte file at ($file_location), downloading: $uri";
            endif;
        else :
            $shouldDownload = true;
            $reason = "No file at ($file_location), downloading: $uri";
        endif;

        if ($shouldDownload === false) :
            $msg->logMessage('[NOTICE]', "Scryfall bulk API: Existing file not too old, skipping");
            return 'Skipped';
        endif;

        $msg->logMessage('[NOTICE]', "Scryfall bulk API: $reason");

        $ok = static::downloadBulk($uri, $file_location, $msg, $appConfig, 'Scryfall bulk API download', false);
        if ($ok === true) :
            $size = filesize($file_location);
            $msg->logMessage(
                '[NOTICE]',
                "Scryfall bulk API: Download OK, file at ($file_location), size ($size), proceeding"
            );
            return 'Success';
        endif;

        // Retry once, briefly
        $msg->logMessage('[ERROR]', "Scryfall bulk API: Download failed, retrying in 20 seconds");
        sleep(20);

        $ok = static::downloadBulk($uri, $file_location, $msg, $appConfig, 'Scryfall bulk API download', false);
        if ($ok === true) :
            $size = filesize($file_location);
            $msg->logMessage(
                '[NOTICE]',
                "Scryfall bulk API: Download OK after retry, file at ($file_location), size ($size), proceeding"
            );
            return 'Success';
        endif;

        $msg->logMessage('[ERROR]', "Scryfall bulk API: Download failed after retry, exiting");
        return false;
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
        if (str_ends_with($fileLocation, '.jsonl.gz')) :
            yield from static::iterateJsonlGzipRecords($fileLocation);
            return;
        endif;

        if (str_ends_with($fileLocation, '.jsonl')) :
            yield from static::iterateJsonlRecords($fileLocation);
            return;
        endif;

        $data = Items::fromFile(
            $fileLocation,
            ['decoder' => new ExtJsonDecoder(true)]
        );

        foreach ($data as $key => $value) :
            if (is_array($value)) :
                yield $key => $value;
            endif;
        endforeach;
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private static function iterateJsonlGzipRecords(string $fileLocation): Generator
    {
        $handle = gzopen($fileLocation, 'rb');
        if ($handle === false) :
            throw new \RuntimeException("Unable to open gzipped JSONL file: {$fileLocation}");
        endif;

        try {
            $lineNumber = 0;
            while (!gzeof($handle)) :
                $line = gzgets($handle);
                if ($line === false) :
                    break;
                endif;
                $lineNumber++;
                $record = static::decodeJsonlLine($line, $lineNumber, $fileLocation);
                if ($record !== null) :
                    yield $lineNumber => $record;
                endif;
            endwhile;
        } finally {
            gzclose($handle);
        }
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private static function iterateJsonlRecords(string $fileLocation): Generator
    {
        $handle = fopen($fileLocation, 'rb');
        if ($handle === false) :
            throw new \RuntimeException("Unable to open JSONL file: {$fileLocation}");
        endif;

        try {
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) :
                $lineNumber++;
                $record = static::decodeJsonlLine($line, $lineNumber, $fileLocation);
                if ($record !== null) :
                    yield $lineNumber => $record;
                endif;
            endwhile;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeJsonlLine(string $line, int $lineNumber, string $fileLocation): ?array
    {
        $line = trim($line);
        if ($line === '') :
            return null;
        endif;

        try {
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                "Invalid JSONL in {$fileLocation} at line {$lineNumber}: " . $e->getMessage(),
                0,
                $e
            );
        }

        if (!is_array($record)) :
            throw new \RuntimeException("Invalid JSONL in {$fileLocation} at line {$lineNumber}: record is not object");
        endif;

        return $record;
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
        // Function to process and import lines within Scryfall bulk data files
        $msg = new Message($appConfig);
        $games_to_include = $gameRules->get('games_to_include', []);
        $langs_to_skip = $gameRules->get('langs_to_skip', []);
        $langs_to_skip_all = $gameRules->get('langs_to_skip_all', []);
        $layouts_to_skip = $gameRules->get('layouts_to_skip', []);
        if (!is_array($games_to_include)) :
            $games_to_include = [];
        endif;
        if (!is_array($langs_to_skip)) :
            $langs_to_skip = [];
        endif;
        if (!is_array($langs_to_skip_all)) :
            $langs_to_skip_all = [];
        endif;
        if (!is_array($layouts_to_skip)) :
            $layouts_to_skip = [];
        endif;

        $allowedTables = ['cards_scry', 'cards_scry_test'];
        if (!in_array($tableName, $allowedTables, true)) :
            $msg->logMessage('[ERROR]', "Invalid table name '$tableName' for scryfallImport");
            throw new \Exception('[ERROR] scryfall_bulk.php: Invalid cards table name supplied');
        endif;
        $msg->logMessage('[DEBUG]', "Using cards table '$tableName' for scryfall import");
        $msg->logMessage('[DEBUG]', "Checking for {$tableName} content_hash and price_hash columns");
        $contentHashQuery = sprintf("SHOW COLUMNS FROM `%s` LIKE 'content_hash'", $tableName);
        $contentHashResult = $db->query($contentHashQuery);
        if ($contentHashResult === false) :
            throw new \Exception(
                '[ERROR] scryfall_bulk.php: Checking cards table content_hash column: ' . $db->error
            );
        elseif ($contentHashResult->num_rows === 0) :
            throw new \Exception(
                '[ERROR] scryfall_bulk.php: cards table content_hash column missing (manual schema update required)'
            );
        else :
            $msg->logMessage('[DEBUG]', 'cards table content_hash column present');
        endif;
        if ($contentHashResult !== false) :
            $contentHashResult->free();
        endif;

        $priceHashQuery = sprintf("SHOW COLUMNS FROM `%s` LIKE 'price_hash'", $tableName);
        $priceHashResult = $db->query($priceHashQuery);
        if ($priceHashResult === false) :
            throw new \Exception(
                '[ERROR] scryfall_bulk.php: Checking cards table price_hash column: ' . $db->error
            );
        elseif ($priceHashResult->num_rows === 0) :
            throw new \Exception(
                '[ERROR] scryfall_bulk.php: cards table price_hash column missing (manual schema update required)'
            );
        else :
            $msg->logMessage('[DEBUG]', 'cards table price_hash column present');
        endif;
        if ($priceHashResult !== false) :
            $priceHashResult->free();
        endif;

        // Initiate counters at zero
        $count_inc = $count_skip = $total_count = $count_add = $count_update = $count_other = 0;
        $count_update_content = $count_update_price = $count_update_both = 0;
        $data = static::iterateBulkRecords($file_location);

        $date = date('Y-m-d');
        $timeslice_start = microtime(true);
        $batch_size = 5000;
        $log_interval = 2500;

        if ($type === 'default') :
            $primary = 1;

            // By default, set to TRUE. This will download all images for cards in the Default Cards file when run
            // with an empty database (about 90,000 images, i.e. potentially about 20GB)
            $imageDownloads = true;
        elseif ($type === 'all') :
            $primary = 0;

            // Don't by default download all images for all cards.
            // Images will be obtained on first card detail load or search result inclusion
            $imageDownloads = false;
        else :
            $msg->logMessage('[ERROR]', "Invalid import type '$type' for scryfallImport");
            throw new \Exception('[ERROR] scryfall_bulk.php: Invalid import type supplied');
        endif;

        $imageManager = null;
        if ($tableName === 'cards_scry_test') :
            $imageDownloads = false;
        endif;

        if ($imageDownloads === true) :
            $imageManager = new ImageManager($db, $appConfig, $gameRules);
        endif;

        $syncStmt = null;
        $syncLookupId = null;
        if ($tableName === 'cards_scry') :
            $syncStmt = $db->prepare(
                "INSERT INTO
                    `scryfall_sync_state`
                        (id, manifest_data_updated_at, data_checked_at)
                    SELECT
                        lookup.id,
                        manifest.data_updated_at,
                        NOW()
                    FROM
                        (SELECT ? AS id) AS lookup
                    LEFT JOIN
                        `scryfall_manifest` AS manifest
                        ON manifest.id = lookup.id
                    ON DUPLICATE KEY UPDATE
                        manifest_data_updated_at = VALUES(manifest_data_updated_at),
                        data_checked_at = VALUES(data_checked_at)"
            );
            if ($syncStmt === false) :
                throw new \Exception('[ERROR] scryfall_bulk.php: Preparing sync state SQL: ' . $db->error);
            endif;
            $syncBind = $syncStmt->bind_param('s', $syncLookupId);
            if ($syncBind === false) :
                throw new \Exception('[ERROR] scryfall_bulk.php: Binding sync state SQL: ' . $db->error);
            endif;
        endif;

        $insertSql = sprintf("INSERT INTO
                                `%s`
                                (id, oracle_id, tcgplayer_id, multiverse, multiverse2,
                                name, printed_name, flavor_name, lang, release_date,
                                api_uri, scryfall_uri, layout, image_uri, manacost,
                                cmc, type, ability, power, toughness,
                                loyalty, color, color_identity, keywords, generatedmana,
                                legalitystandard, legalitypioneer, legalitymodern, legalitylegacy, legalitypauper,
                                legalityvintage, legalitycommander, legalityalchemy, legalityhistoric, reserved,
                                foil, nonfoil, oversized, promo, set_id,
                                game_types, finishes, promo_types, setcode, set_name,
                                number, number_import, rarity, flavor, backid,
                                artist, price, price_foil, price_etched, gatherer_uri,
                                updatetime, f1_name, f1_manacost, f1_power, f1_toughness,
                                f1_loyalty, f1_type, f1_ability, f1_colour, f1_artist,
                                f1_flavor, f1_image_uri, f1_cmc, f1_printed_name, f1_flavor_name,
                                f2_name, f2_manacost, f2_power, f2_toughness, f2_loyalty,
                                f2_type, f2_ability, f2_colour, f2_artist, f2_flavor,
                                f2_image_uri, f2_cmc, f2_printed_name, f2_flavor_name, p1_id,
                                p1_component, p1_name, p1_type_line, p1_uri, p2_id,
                                p2_component, p2_name, p2_type_line, p2_uri, p3_id,
                                p3_component, p3_name, p3_type_line, p3_uri, p4_id,
                                p4_component, p4_name, p4_type_line, p4_uri, p5_id,
                                p5_component, p5_name, p5_type_line, p5_uri, p6_id,
                                p6_component, p6_name, p6_type_line, p6_uri, p7_id,
                                p7_component, p7_name, p7_type_line, p7_uri, maxpower,
                                minpower, maxtoughness, mintoughness, maxloyalty, minloyalty,
                                printed_type_line, printed_text, f1_printed_type_line, f1_printed_text,
                                f2_printed_type_line, f2_printed_text,
                                price_sort, content_hash, price_hash, date_added, primary_card
                                )
                            VALUES(
                                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
                                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
                                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
                                ?,?,?,?,?,?,?,?,?,?,?,?,?
                            )
                            ON DUPLICATE KEY UPDATE
                                id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(id), id),
                                oracle_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(oracle_id), oracle_id),
                                tcgplayer_id = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(tcgplayer_id),
                                    tcgplayer_id
                                ),
                                multiverse = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(multiverse),
                                    multiverse
                                ),
                                multiverse2 = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(multiverse2),
                                    multiverse2
                                ),
                                name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(name), name),
                                printed_name = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(printed_name),
                                    printed_name
                                ),
                                flavor_name = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(flavor_name),
                                    flavor_name
                                ),
                                lang = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(lang), lang),
                                release_date = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(release_date),
                                    release_date
                                ),
                                api_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(api_uri), api_uri),
                                scryfall_uri = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(scryfall_uri),
                                    scryfall_uri
                                ),
                                layout = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(layout), layout),
                                image_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(image_uri), image_uri),
                                manacost = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(manacost), manacost),
                                cmc = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(cmc), cmc),
                                type = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(type), type),
                                printed_type_line = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(printed_type_line),
                                    printed_type_line
                                ),
                                ability = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(ability), ability),
                                printed_text = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(printed_text),
                                    printed_text
                                ),
                                power = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(power), power),
                                toughness = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(toughness), toughness),
                                loyalty = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(loyalty), loyalty),
                                color = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(color), color),
                                color_identity = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(color_identity),
                                    color_identity
                                ),
                                keywords = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(keywords), keywords),
                                generatedmana = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(generatedmana),
                                    generatedmana
                                ),
                                legalitystandard = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(legalitystandard),
                                    legalitystandard
                                ),
                                legalitypioneer = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(legalitypioneer),
                                    legalitypioneer
                                ),
                                legalitymodern = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(legalitymodern),
                                    legalitymodern
                                ),
                                legalitylegacy = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(legalitylegacy),
                                    legalitylegacy
                                ),
                                legalitypauper = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(legalitypauper),
                                    legalitypauper
                                ),
                                legalityvintage = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(legalityvintage),
                                    legalityvintage
                                ),
                                legalitycommander = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(legalitycommander),
                                    legalitycommander
                                ),
                                legalityalchemy = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(legalityalchemy),
                                    legalityalchemy
                                ),
                                legalityhistoric = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(legalityhistoric),
                                    legalityhistoric
                                ),
                                reserved = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(reserved), reserved),
                                foil = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(foil), foil),
                                nonfoil = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(nonfoil), nonfoil),
                                oversized = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(oversized), oversized),
                                promo = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(promo), promo),
                                set_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(set_id), set_id),
                                game_types = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(game_types),
                                    game_types
                                ),
                                finishes = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(finishes), finishes),
                                promo_types = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(promo_types),
                                    promo_types
                                ),
                                setcode = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(setcode), setcode),
                                set_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(set_name), set_name),
                                number = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(number), number),
                                number_import = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(number_import),
                                    number_import
                                ),
                                rarity = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(rarity), rarity),
                                flavor = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(flavor), flavor),
                                backid = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(backid), backid),
                                artist = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(artist), artist),
                                price = IF(NOT (price_hash <=> VALUES(price_hash)), VALUES(price), price),
                                price_foil = IF(NOT (price_hash <=> VALUES(price_hash)), VALUES(price_foil), price_foil),
                                price_etched = IF(
                                    NOT (price_hash <=> VALUES(price_hash)),
                                    VALUES(price_etched),
                                    price_etched
                                ),
                                price_sort = IF(NOT (price_hash <=> VALUES(price_hash)), VALUES(price_sort), price_sort),
                                gatherer_uri = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(gatherer_uri),
                                    gatherer_uri
                                ),
                                updatetime = IF(
                                    NOT (content_hash <=> VALUES(content_hash)) OR NOT (price_hash <=> VALUES(price_hash)),
                                    VALUES(updatetime),
                                    updatetime
                                ),
                                f1_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_name), f1_name),
                                f1_manacost = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f1_manacost),
                                    f1_manacost
                                ),
                                f1_power = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_power), f1_power),
                                f1_toughness = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f1_toughness),
                                    f1_toughness
                                ),
                                f1_loyalty = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f1_loyalty),
                                    f1_loyalty
                                ),
                                f1_type = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_type), f1_type),
                                f1_printed_type_line = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f1_printed_type_line),
                                    f1_printed_type_line
                                ),
                                f1_ability = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f1_ability),
                                    f1_ability
                                ),
                                f1_printed_text = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f1_printed_text),
                                    f1_printed_text
                                ),
                                f1_colour = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_colour), f1_colour),
                                f1_artist = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_artist), f1_artist),
                                f1_flavor = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_flavor), f1_flavor),
                                f1_image_uri = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f1_image_uri),
                                    f1_image_uri
                                ),
                                f1_cmc = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_cmc), f1_cmc),
                                f1_printed_name = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f1_printed_name),
                                    f1_printed_name
                                ),
                                f1_flavor_name = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f1_flavor_name),
                                    f1_flavor_name
                                ),
                                f2_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_name), f2_name),
                                f2_manacost = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f2_manacost),
                                    f2_manacost
                                ),
                                f2_power = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_power), f2_power),
                                f2_toughness = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f2_toughness),
                                    f2_toughness
                                ),
                                f2_loyalty = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f2_loyalty),
                                    f2_loyalty
                                ),
                                f2_type = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_type), f2_type),
                                f2_printed_type_line = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f2_printed_type_line),
                                    f2_printed_type_line
                                ),
                                f2_ability = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f2_ability),
                                    f2_ability
                                ),
                                f2_printed_text = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f2_printed_text),
                                    f2_printed_text
                                ),
                                f2_colour = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_colour), f2_colour),
                                f2_artist = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_artist), f2_artist),
                                f2_flavor = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_flavor), f2_flavor),
                                f2_image_uri = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f2_image_uri),
                                    f2_image_uri
                                ),
                                f2_cmc = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_cmc), f2_cmc),
                                f2_printed_name = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f2_printed_name),
                                    f2_printed_name
                                ),
                                f2_flavor_name = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(f2_flavor_name),
                                    f2_flavor_name
                                ),
                                p1_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p1_id), p1_id),
                                p1_component = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p1_component),
                                    p1_component
                                ),
                                p1_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p1_name), p1_name),
                                p1_type_line = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p1_type_line),
                                    p1_type_line
                                ),
                                p1_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p1_uri), p1_uri),
                                p2_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p2_id), p2_id),
                                p2_component = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p2_component),
                                    p2_component
                                ),
                                p2_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p2_name), p2_name),
                                p2_type_line = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p2_type_line),
                                    p2_type_line
                                ),
                                p2_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p2_uri), p2_uri),
                                p3_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p3_id), p3_id),
                                p3_component = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p3_component),
                                    p3_component
                                ),
                                p3_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p3_name), p3_name),
                                p3_type_line = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p3_type_line),
                                    p3_type_line
                                ),
                                p3_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p3_uri), p3_uri),
                                p4_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p4_id), p4_id),
                                p4_component = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p4_component),
                                    p4_component
                                ),
                                p4_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p4_name), p4_name),
                                p4_type_line = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p4_type_line),
                                    p4_type_line
                                ),
                                p4_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p4_uri), p4_uri),
                                p5_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p5_id), p5_id),
                                p5_component = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p5_component),
                                    p5_component
                                ),
                                p5_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p5_name), p5_name),
                                p5_type_line = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p5_type_line),
                                    p5_type_line
                                ),
                                p5_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p5_uri), p5_uri),
                                p6_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p6_id), p6_id),
                                p6_component = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p6_component),
                                    p6_component
                                ),
                                p6_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p6_name), p6_name),
                                p6_type_line = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p6_type_line),
                                    p6_type_line
                                ),
                                p6_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p6_uri), p6_uri),
                                p7_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p7_id), p7_id),
                                p7_component = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p7_component),
                                    p7_component
                                ),
                                p7_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p7_name), p7_name),
                                p7_type_line = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(p7_type_line),
                                    p7_type_line
                                ),
                                p7_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p7_uri), p7_uri),
                                maxpower = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(maxpower), maxpower),
                                minpower = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(minpower), minpower),
                                maxtoughness = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(maxtoughness),
                                    maxtoughness
                                ),
                                mintoughness = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(mintoughness),
                                    mintoughness
                                ),
                                maxloyalty = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(maxloyalty),
                                    maxloyalty
                                ),
                                minloyalty = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(minloyalty),
                                    minloyalty
                                ),
                                content_hash = IF(
                                    NOT (content_hash <=> VALUES(content_hash)),
                                    VALUES(content_hash),
                                    content_hash
                                ),
                                price_hash = IF(
                                    NOT (price_hash <=> VALUES(price_hash)),
                                    VALUES(price_hash),
                                    price_hash
                                ),
                                primary_card = IF(?, 1, primary_card)
                            ", $tableName);
        $stmt = $db->prepare($insertSql);
        if ($stmt === false) :
            throw new \Exception('[ERROR] cards.php: Preparing SQL: ' . $db->error);
        endif;
        $hashSql = sprintf(
            "SELECT content_hash, price_hash FROM `%s` WHERE id = ? LIMIT 1",
            $tableName
        );
        $hashStmt = $db->prepare($hashSql);
        if ($hashStmt === false) :
            throw new \Exception('[ERROR] scryfall_bulk.php: Preparing hash lookup SQL: ' . $db->error);
        endif;
        // Initialise all variables for binding
        $id = null;
        $oracle_id = null;
        $tcgplayer_id = null;
        $multi_1 = null;
        $multi_2 = null;
        $name = null;
        $printed_name = null;
        $flavor_name = null;
        $lang = null;
        $released_at = null;
        $uri = null;
        $scryfall_uri = null;
        $layout = null;
        $image_uri = null;
        $mana_cost = null;
        $cmc = null;
        $type_line = null;
        $oracle_text = null;
        $printed_type_line = null;
        $printed_text = null;
        $power = null;
        $toughness = null;
        $loyalty = null;
        $colors = null;
        $color_identity = null;
        $keywords = null;
        $produced_mana = null;

        $legality_standard = null;
        $legality_pioneer = null;
        $legality_modern = null;
        $legality_legacy = null;
        $legality_pauper = null;
        $legality_vintage = null;
        $legality_commander = null;
        $legality_alchemy = null;
        $legality_historic = null;

        $reserved = null;
        $foil = null;
        $nonfoil = null;
        $oversized = null;
        $promo = null;
        $set_id = null;

        $game_types = null;
        $finishes = null;
        $promo_types = null;

        $set_code = null;
        $set_name = null;
        $number_int = null;
        $collector_number = null;
        $rarity = null;
        $flavor_text = null;
        $card_back_id = null;
        $artist = null;

        $price_usd = null;
        $price_usd_foil = null;
        $price_usd_etched = null;
        $gatherer_uri = null;

        $time = null;

        /* Face 1 */
        $name_1 = null;
        $manacost_1 = null;
        $power_1 = null;
        $toughness_1 = null;
        $loyalty_1 = null;
        $type_1 = null;
        $printed_type_1 = null;
        $ability_1 = null;
        $printed_text_1 = null;
        $colour_1 = null;
        $artist_1 = null;
        $flavor_1 = null;
        $image_1 = null;
        $cmc_1 = null;
        $printed_name_1 = null;
        $flavor_name_1 = null;

        /* Face 2 */
        $name_2 = null;
        $manacost_2 = null;
        $power_2 = null;
        $toughness_2 = null;
        $loyalty_2 = null;
        $type_2 = null;
        $printed_type_2 = null;
        $ability_2 = null;
        $printed_text_2 = null;
        $colour_2 = null;
        $artist_2 = null;
        $flavor_2 = null;
        $image_2 = null;
        $cmc_2 = null;
        $printed_name_2 = null;
        $flavor_name_2 = null;

        /* Parts */
        $id_p1 = $component_p1 = $name_p1 = $type_line_p1 = $uri_p1 = null;
        $id_p2 = $component_p2 = $name_p2 = $type_line_p2 = $uri_p2 = null;
        $id_p3 = $component_p3 = $name_p3 = $type_line_p3 = $uri_p3 = null;
        $id_p4 = $component_p4 = $name_p4 = $type_line_p4 = $uri_p4 = null;
        $id_p5 = $component_p5 = $name_p5 = $type_line_p5 = $uri_p5 = null;
        $id_p6 = $component_p6 = $name_p6 = $type_line_p6 = $uri_p6 = null;
        $id_p7 = $component_p7 = $name_p7 = $type_line_p7 = $uri_p7 = null;

        /* Stats */
        $maxpower = null;
        $minpower = null;
        $maxtoughness = null;
        $mintoughness = null;
        $maxloyalty = null;
        $minloyalty = null;

        $price_sort = null;
        $content_hash = null;
        $price_hash = null;
        $primary = (int) $primary;

        $hash_id = null;
        $hash_lookup = null;
        $existing_id = null;
        $existing_found = false;
        $existing_content_hash = null;
        $existing_price_hash = null;

        $bind = $stmt->bind_param(
            "sssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss"
            . "ssssssssssssssssssssssssssssssssssssssssssssssssssssssii",
            $id,
            $oracle_id,
            $tcgplayer_id,
            $multi_1,
            $multi_2,
            $name,
            $printed_name,
            $flavor_name,
            $lang,
            $released_at,
            $uri,
            $scryfall_uri,
            $layout,
            $image_uri,
            $mana_cost,
            $cmc,
            $type_line,
            $oracle_text,
            $power,
            $toughness,
            $loyalty,
            $colors,
            $color_identity,
            $keywords,
            $produced_mana,
            $legality_standard,
            $legality_pioneer,
            $legality_modern,
            $legality_legacy,
            $legality_pauper,
            $legality_vintage,
            $legality_commander,
            $legality_alchemy,
            $legality_historic,
            $reserved,
            $foil,
            $nonfoil,
            $oversized,
            $promo,
            $set_id,
            $game_types,
            $finishes,
            $promo_types,
            $set_code,
            $set_name,
            $number_int,
            $collector_number,
            $rarity,
            $flavor_text,
            $card_back_id,
            $artist,
            $price_usd,
            $price_usd_foil,
            $price_usd_etched,
            $gatherer_uri,
            $time,
            $name_1,
            $manacost_1,
            $power_1,
            $toughness_1,
            $loyalty_1,
            $type_1,
            $ability_1,
            $colour_1,
            $artist_1,
            $flavor_1,
            $image_1,
            $cmc_1,
            $printed_name_1,
            $flavor_name_1,
            $name_2,
            $manacost_2,
            $power_2,
            $toughness_2,
            $loyalty_2,
            $type_2,
            $ability_2,
            $colour_2,
            $artist_2,
            $flavor_2,
            $image_2,
            $cmc_2,
            $printed_name_2,
            $flavor_name_2,
            $id_p1,
            $component_p1,
            $name_p1,
            $type_line_p1,
            $uri_p1,
            $id_p2,
            $component_p2,
            $name_p2,
            $type_line_p2,
            $uri_p2,
            $id_p3,
            $component_p3,
            $name_p3,
            $type_line_p3,
            $uri_p3,
            $id_p4,
            $component_p4,
            $name_p4,
            $type_line_p4,
            $uri_p4,
            $id_p5,
            $component_p5,
            $name_p5,
            $type_line_p5,
            $uri_p5,
            $id_p6,
            $component_p6,
            $name_p6,
            $type_line_p6,
            $uri_p6,
            $id_p7,
            $component_p7,
            $name_p7,
            $type_line_p7,
            $uri_p7,
            $maxpower,
            $minpower,
            $maxtoughness,
            $mintoughness,
            $maxloyalty,
            $minloyalty,
            $printed_type_line,
            $printed_text,
            $printed_type_1,
            $printed_text_1,
            $printed_type_2,
            $printed_text_2,
            $price_sort,
            $content_hash,
            $price_hash,
            $date,
            $primary,
            $primary
        );

        if ($bind === false) :
            mtgError(
                E_USER_ERROR,
                '[ERROR] scryfall_bulk.php: Binding parameters: ' . $db->error,
                __FILE__,
                __LINE__,
                $appConfig
            );
        endif;
        $hashBind = $hashStmt->bind_param("s", $hash_id);
        if ($hashBind === false) :
            mtgError(
                E_USER_ERROR,
                '[ERROR] scryfall_bulk.php: Binding hash id: ' . $db->error,
                __FILE__,
                __LINE__,
                $appConfig
            );
        endif;
        $lastGoodId = null;
        $lastGoodCount = 0;

        $msg->logMessage('[DEBUG]', 'Starting bulk import transaction batch');
        $batchStart = $db->begin_transaction();
        if ($batchStart === false) :
            mtgError(
                E_USER_ERROR,
                '[ERROR] scryfall_bulk.php: Starting transaction batch: ' . $db->error,
                __FILE__,
                __LINE__,
                $appConfig
            );
        endif;

        try {
            foreach ($data as $key => $value) :
                $total_count = $total_count + 1;
                $commit_due = ($total_count % $batch_size === 0);
                $log_due = ($total_count % $log_interval === 0);

                // Bind vars that always exist
                $id = $value["id"] ?? null;
                if ($id === null) :
                    $count_skip = $count_skip + 1;
                    $msg->logMessage('[WARNING]', "Skipping record {$total_count}: missing id");
                    if ($commit_due) :
                        $commitResult = $db->commit();
                        if ($commitResult === false) :
                            mtgError(
                                E_USER_ERROR,
                                '[ERROR] scryfall_bulk.php: Committing transaction batch: ' . $db->error,
                                __FILE__,
                                __LINE__,
                                $appConfig
                            );
                        endif;
                        $msg->logMessage('[DEBUG]', "Committed transaction batch at record $total_count");
                        $batchStart = $db->begin_transaction();
                        if ($batchStart === false) :
                            mtgError(
                                E_USER_ERROR,
                                '[ERROR] scryfall_bulk.php: Starting transaction batch: ' . $db->error,
                                __FILE__,
                                __LINE__,
                                $appConfig
                            );
                        endif;
                    endif;
                    if ($log_due) :
                        $timeslice = microtime(true) - $timeslice_start;
                        $commit_note = $commit_due ? '; batch committed' : '';
                        $msg->logMessage(
                            '[NOTICE]',
                            "Scryfall bulk API ($type) progress: $total_count records processed; timeslice: "
                            . sprintf('%.2f', $timeslice) . "s{$commit_note}"
                        );
                        $timeslice_start = microtime(true);
                    endif;
                    continue;
                endif;

                $msg->logMessage('[DEBUG]', "Scryfall bulk API ($type), Record $id: $total_count");

                // Re-null per record
                $multi_1 = $multi_2 = null;
                $number_int = null;

                /* Face 1 + Face 2 bind vars */
                $name_1 = $name_2 = null;
                $printed_name_1 = $printed_name_2 = null;
                $flavor_name_1 = $flavor_name_2 = null;
                $manacost_1 = $manacost_2 = null;
                $power_1 = $power_2 = null;
                $toughness_1 = $toughness_2 = null;
                $loyalty_1 = $loyalty_2 = null;
                $type_1 = $type_2 = null;
                $printed_type_1 = $printed_type_2 = null;
                $ability_1 = $ability_2 = null;
                $printed_text_1 = $printed_text_2 = null;
                $colour_1 = $colour_2 = null;
                $artist_1 = $artist_2 = null;
                $flavor_1 = $flavor_2 = null;
                $image_1 = $image_2 = null;
                $cmc_1 = $cmc_2 = null;

                /* Parts */
                $id_p1 = $component_p1 = $name_p1 = $type_line_p1 = $uri_p1 = null;
                $id_p2 = $component_p2 = $name_p2 = $type_line_p2 = $uri_p2 = null;
                $id_p3 = $component_p3 = $name_p3 = $type_line_p3 = $uri_p3 = null;
                $id_p4 = $component_p4 = $name_p4 = $type_line_p4 = $uri_p4 = null;
                $id_p5 = $component_p5 = $name_p5 = $type_line_p5 = $uri_p5 = null;
                $id_p6 = $component_p6 = $name_p6 = $type_line_p6 = $uri_p6 = null;
                $id_p7 = $component_p7 = $name_p7 = $type_line_p7 = $uri_p7 = null;

                /* JSON-ish bind vars */
                $colors = $game_types = $promo_types = $color_identity = $keywords = $produced_mana = null;
                $finishes = null;

                /* Derived stats */
                $maxpower = $minpower = $maxtoughness = $mintoughness = null;
                $maxloyalty = $minloyalty = null;
                $price_sort = null;
                $content_hash = null;
                $price_hash = null;

                /* New bind vars that replace direct $value[...] usage */
                $oracle_id = $value["oracle_id"] ?? null;
                $tcgplayer_id = $value["tcgplayer_id"] ?? null;

                $name = $value["name"] ?? null;
                $printed_name = $value["printed_name"] ?? null;
                $flavor_name = $value["flavor_name"] ?? null;

                $lang = $value["lang"] ?? null;
                $released_at = $value["released_at"] ?? null;

                $uri = $value["uri"] ?? null;
                $scryfall_uri = $value["scryfall_uri"] ?? null;
                $layout = $value["layout"] ?? null;

                $image_uri = $value["image_uris"]["normal"] ?? null;
                $mana_cost = $value["mana_cost"] ?? null;
                $cmc = $value["cmc"] ?? null;
                $type_line = $value["type_line"] ?? null;
                $oracle_text = $value["oracle_text"] ?? null;
                $printed_type_line = (isset($value["printed_type_line"]) and $value["printed_type_line"] !== '')
                    ? $value["printed_type_line"]
                    : null;
                $printed_text = (isset($value["printed_text"]) and $value["printed_text"] !== '')
                    ? $value["printed_text"]
                    : null;

                $power = $value["power"] ?? null;
                $toughness = $value["toughness"] ?? null;
                $loyalty = $value["loyalty"] ?? null;

                $reserved = $value["reserved"] ?? null;
                $foil = $value["foil"] ?? null;
                $nonfoil = $value["nonfoil"] ?? null;
                $oversized = $value["oversized"] ?? null;
                $promo = $value["promo"] ?? null;
                $set_id = $value["set_id"] ?? null;

                $set_code = $value["set"] ?? null;
                $set_name = $value["set_name"] ?? null;
                $collector_number = $value["collector_number"] ?? null;
                $rarity = $value["rarity"] ?? null;
                $flavor_text = $value["flavor_text"] ?? null;
                $card_back_id = $value["card_back_id"] ?? null;
                $artist = $value["artist"] ?? null;

                $gatherer_uri = $value["related_uris"]["gatherer"] ?? null;

                // Legalities (bind vars)
                $legality_standard = $value["legalities"]["standard"] ?? null;
                $legality_pioneer = $value["legalities"]["pioneer"] ?? null;
                $legality_modern = $value["legalities"]["modern"] ?? null;
                $legality_legacy = $value["legalities"]["legacy"] ?? null;
                $legality_pauper = $value["legalities"]["pauper"] ?? null;
                $legality_vintage = $value["legalities"]["vintage"] ?? null;
                $legality_commander = $value["legalities"]["commander"] ?? null;
                $legality_alchemy = $value["legalities"]["alchemy"] ?? null;
                $legality_historic = $value["legalities"]["historic"] ?? null;

                // Skip logic (leave unchanged)
                $skip = 1; // skip by default

                // Check if game type is to be included
                $games = $value['games'] ?? array();
                foreach ($games as $game_type) :
                    if (in_array($game_type, $games_to_include, true)) :
                        $skip = 0;
                        break;
                    endif;
                endforeach;

                // Check langs to include
                if (
                    (in_array($lang, $langs_to_skip, true) and $type === 'default')
                    or
                    (in_array($lang, $langs_to_skip_all, true) and $type === 'all')
                    or
                    (in_array($layout, $layouts_to_skip, true))
                ) :
                    $skip = 1;
                endif;

                // Only proceed if not to be skipped
                if ($skip === 1) :
                    $count_skip = $count_skip + 1;
                elseif ($skip === 0) :
                    $time = time();
                    $count_inc = $count_inc + 1;

                    // Card faces / parts / multiverse loops (keep logic, no structural changes)
                    $cardFaces = $value['card_faces'] ?? array();
                    if (!empty($cardFaces)) :
                        $face_loop = 1;
                        foreach ($cardFaces as $value3) :
                            if (isset($value3["name"])) :
                                ${'name_' . $face_loop} = $value3["name"];
                            endif;
                            if (isset($value3["printed_name"])) :
                                ${'printed_name_' . $face_loop} = $value3["printed_name"];
                            endif;
                            if (isset($value3["flavor_name"])) :
                                ${'flavor_name_' . $face_loop} = $value3["flavor_name"];
                            endif;
                            if (isset($value3["mana_cost"])) :
                                ${'manacost_' . $face_loop} = $value3["mana_cost"];
                            endif;
                            if (isset($value3["power"])) :
                                ${'power_' . $face_loop} = $value3["power"];
                            endif;
                            if (isset($value3["toughness"])) :
                                ${'toughness_' . $face_loop} = $value3["toughness"];
                            elseif (isset($value3["defense"])) :
                                ${'toughness_' . $face_loop} = $value3["defense"];
                            endif;
                            if (isset($value3["loyalty"])) :
                                ${'loyalty_' . $face_loop} = $value3["loyalty"];
                            endif;
                            if (isset($value3["type_line"])) :
                                ${'type_' . $face_loop} = $value3["type_line"];
                            endif;
                            if (isset($value3["printed_type_line"]) and $value3["printed_type_line"] !== '') :
                                ${'printed_type_' . $face_loop} = $value3["printed_type_line"];
                            endif;
                            if (isset($value3["oracle_text"])) :
                                ${'ability_' . $face_loop} = $value3["oracle_text"];
                            endif;
                            if (isset($value3["printed_text"]) and $value3["printed_text"] !== '') :
                                ${'printed_text_' . $face_loop} = $value3["printed_text"];
                            endif;
                            if (isset($value3["colors"])) :
                                ${'colour_' . $face_loop} = json_encode($value3["colors"]);
                            endif;
                            if (isset($value3["artist"])) :
                                ${'artist_' . $face_loop} = $value3["artist"];
                            endif;
                            if (isset($value3["flavor_text"])) :
                                ${'flavor_' . $face_loop} = $value3["flavor_text"];
                            endif;
                            if (isset($value3["image_uris"]["normal"])) :
                                ${'image_' . $face_loop} = $value3["image_uris"]["normal"];
                            endif;
                            if (isset($value3["cmc"])) :
                                ${'cmc_' . $face_loop} = $value3["cmc"];
                            endif;
                            $face_loop = $face_loop + 1;
                            if ($face_loop > 2) :
                                break;
                            endif;
                        endforeach;
                        $msg->logMessage(
                            '[DEBUG]',
                            "Scryfall bulk API ($type), Record $id: $total_count - finished face loops"
                        );
                    endif;

                    $allParts = $value['all_parts'] ?? array();
                    if (!empty($allParts)) :
                        $all_parts_loop = 1;
                        foreach ($allParts as $value4) :
                            if (isset($value4["component"]) and $value4["component"] != "combo_piece") :
                                if (isset($value4["id"])) :
                                    ${'id_p' . $all_parts_loop} = $value4["id"];
                                endif;
                                if (isset($value4["component"])) :
                                    ${'component_p' . $all_parts_loop} = $value4["component"];
                                endif;
                                if (isset($value4["name"])) :
                                    ${'name_p' . $all_parts_loop} = $value4["name"];
                                endif;
                                if (isset($value4["type_line"])) :
                                    ${'type_line_p' . $all_parts_loop} = $value4["type_line"];
                                endif;
                                if (isset($value4["uri"])) :
                                    ${'uri_p' . $all_parts_loop} = $value4["uri"];
                                endif;
                                $all_parts_loop = $all_parts_loop + 1;
                                if ($all_parts_loop > 7) :
                                    break;
                                endif;
                            endif;
                        endforeach;
                    endif;

                    $multiverseIds = $value['multiverse_ids'] ?? array();
                    $multiverse_loop = 1;
                    foreach ($multiverseIds as $m_id) :
                        ${'multi_' . $multiverse_loop} = $m_id;
                        $multiverse_loop = $multiverse_loop + 1;
                        if ($multiverse_loop > 2) :
                            break;
                        endif;
                    endforeach;

                    // Derived power/toughness/loyalty (unchanged)
                    $powerarray = array();
                    $toughnessarray = array();
                    $loyaltyarray = array();

                    if (isset($value['power'])) :
                        array_push($powerarray, (int)$value['power']);
                    endif;
                    if (isset($power_1)) :
                        array_push($powerarray, (int)$power_1);
                    endif;
                    if (isset($power_2)) :
                        array_push($powerarray, (int)$power_2);
                    endif;
                    if (!empty($powerarray)) :
                        $maxpower = max($powerarray);
                        $minpower = min($powerarray);
                    endif;

                    if (isset($value['toughness'])) :
                        array_push($toughnessarray, (int)$value['toughness']);
                    endif;
                    if (isset($toughness_1)) :
                        array_push($toughnessarray, (int)$toughness_1);
                    endif;
                    if (isset($toughness_2)) :
                        array_push($toughnessarray, (int)$toughness_2);
                    endif;
                    if (!empty($toughnessarray)) :
                        $maxtoughness = max($toughnessarray);
                        $mintoughness = min($toughnessarray);
                    endif;

                    if (isset($value['loyalty'])) :
                        array_push($loyaltyarray, (int)$value['loyalty']);
                    endif;
                    if (isset($loyalty_1)) :
                        array_push($loyaltyarray, (int)$loyalty_1);
                    endif;
                    if (isset($loyalty_2)) :
                        array_push($loyaltyarray, (int)$loyalty_2);
                    endif;
                    if (!empty($loyaltyarray)) :
                        $maxloyalty = max($loyaltyarray);
                        $minloyalty = min($loyaltyarray);
                    endif;

                    // JSON-ish extras to bind vars (same names as your bind list)
                    $colors = isset($value["colors"]) ? json_encode($value["colors"]) : null;
                    $game_types = isset($value["games"]) ? json_encode($value["games"]) : null;
                    $promo_types = isset($value["promo_types"]) ? json_encode($value["promo_types"]) : null;
                    $finishes = isset($value["finishes"]) ? json_encode($value["finishes"]) : null;
                    $color_identity = isset($value["color_identity"]) ? json_encode($value["color_identity"]) : null;
                    $keywords = isset($value["keywords"]) ? json_encode($value["keywords"]) : null;
                    $produced_mana = isset($value["produced_mana"]) ? json_encode($value["produced_mana"]) : null;

                    // Prices -> new bind vars
                    $price_usd = $value["prices"]['usd'] ?? null;
                    $price_usd_foil = $value["prices"]['usd_foil'] ?? null;
                    $price_usd_etched = $value["prices"]['usd_etched'] ?? null;

                    // Keep your price_sort logic but run it using the new vars
                    if ($price_usd_foil === null and $price_usd === null and $price_usd_etched === null) :
                        $price_sort = null;
                    elseif ($price_usd_foil === null and $price_usd_etched === null) :
                        $price_sort = $price_usd;
                    elseif ($price_usd === null and $price_usd_etched === null) :
                        $price_sort = $price_usd_foil;
                    elseif ($price_usd_foil === null and $price_usd === null) :
                        $price_sort = $price_usd_etched;
                    elseif ($price_usd === null) :
                        $price_sort = min($price_usd_etched, $price_usd_foil);
                    elseif ($price_usd_foil === null) :
                        $price_sort = min($price_usd_etched, $price_usd);
                    elseif ($price_usd_etched === null) :
                        $price_sort = min($price_usd, $price_usd_foil);
                    else :
                        $price_sort = min($price_usd, $price_usd_foil, $price_usd_etched);
                    endif;

                    // Collector number -> number_int (keep existing normalisation)
                    if (isset($value["collector_number"])) :
                        $coll_no = $value["collector_number"];

                        if (isset($value["layout"]) and $value["layout"] === 'meld') :
                            $coll_no = str_replace('a', '', $coll_no);
                            $coll_no = str_replace('b', '', $coll_no);
                        endif;

                        $coll_no = str_replace('-', '', $coll_no);
                        $coll_no = str_replace('a', '1', $coll_no);
                        $coll_no = str_replace('b', '2', $coll_no);
                        $coll_no = str_replace('c', '3', $coll_no);
                        $coll_no = str_replace('d', '4', $coll_no);
                        $coll_no = str_replace('e', '5', $coll_no);
                        $coll_no = str_replace('f', '6', $coll_no);
                        $coll_no = str_replace('g', '7', $coll_no);
                        $coll_no = str_replace('h', '8', $coll_no);
                        $coll_no = str_replace('E', '', $coll_no);
                        $coll_no = str_replace('★', '', $coll_no);
                        $coll_no = str_replace('*', '', $coll_no);
                        $coll_no = str_replace('†', '', $coll_no);
                        $coll_no = str_replace('U', '', $coll_no);

                        // For cards with collector number "XXXs", turn into "5XXX"
                        // so they go to the end of the series
                        if (substr($coll_no, strlen($coll_no) - 1) === 's') :
                            $coll_no = str_replace('s', '', $coll_no);
                            if (ctype_digit($coll_no)) :
                                $coll_no = (int) $coll_no + 5000;
                            endif;
                        endif;

                        if (substr($coll_no, strlen($coll_no) - 1) === 'p') :
                            $coll_no = str_replace('p', '', $coll_no);
                        endif;

                        $number_int = (int) $coll_no;
                    endif;

                    $contentHashData = array(
                        $id,
                        $oracle_id,
                        $tcgplayer_id,
                        $multi_1,
                        $multi_2,
                        $name,
                        $printed_name,
                        $flavor_name,
                        $lang,
                        $released_at,
                        $uri,
                        $scryfall_uri,
                        $layout,
                        $image_uri,
                        $mana_cost,
                        $cmc,
                        $type_line,
                        $oracle_text,
                        $printed_type_line,
                        $printed_text,
                        $power,
                        $toughness,
                        $loyalty,
                        $colors,
                        $color_identity,
                        $keywords,
                        $produced_mana,
                        $legality_standard,
                        $legality_pioneer,
                        $legality_modern,
                        $legality_legacy,
                        $legality_pauper,
                        $legality_vintage,
                        $legality_commander,
                        $legality_alchemy,
                        $legality_historic,
                        $reserved,
                        $foil,
                        $nonfoil,
                        $oversized,
                        $promo,
                        $set_id,
                        $game_types,
                        $finishes,
                        $promo_types,
                        $set_code,
                        $set_name,
                        $number_int,
                        $collector_number,
                        $rarity,
                        $flavor_text,
                        $card_back_id,
                        $artist,
                        $gatherer_uri,
                        $name_1,
                        $manacost_1,
                        $power_1,
                        $toughness_1,
                        $loyalty_1,
                        $type_1,
                        $printed_type_1,
                        $ability_1,
                        $printed_text_1,
                        $colour_1,
                        $artist_1,
                        $flavor_1,
                        $image_1,
                        $cmc_1,
                        $printed_name_1,
                        $flavor_name_1,
                        $name_2,
                        $manacost_2,
                        $power_2,
                        $toughness_2,
                        $loyalty_2,
                        $type_2,
                        $printed_type_2,
                        $ability_2,
                        $printed_text_2,
                        $colour_2,
                        $artist_2,
                        $flavor_2,
                        $image_2,
                        $cmc_2,
                        $printed_name_2,
                        $flavor_name_2,
                        $id_p1,
                        $component_p1,
                        $name_p1,
                        $type_line_p1,
                        $uri_p1,
                        $id_p2,
                        $component_p2,
                        $name_p2,
                        $type_line_p2,
                        $uri_p2,
                        $id_p3,
                        $component_p3,
                        $name_p3,
                        $type_line_p3,
                        $uri_p3,
                        $id_p4,
                        $component_p4,
                        $name_p4,
                        $type_line_p4,
                        $uri_p4,
                        $id_p5,
                        $component_p5,
                        $name_p5,
                        $type_line_p5,
                        $uri_p5,
                        $id_p6,
                        $component_p6,
                        $name_p6,
                        $type_line_p6,
                        $uri_p6,
                        $id_p7,
                        $component_p7,
                        $name_p7,
                        $type_line_p7,
                        $uri_p7,
                        $maxpower,
                        $minpower,
                        $maxtoughness,
                        $mintoughness,
                        $maxloyalty,
                        $minloyalty
                    );
                    $contentPayload = json_encode($contentHashData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if ($contentPayload === false) :
                        $msg->logMessage('[WARNING]', "Failed to JSON encode content_hash data for $id");
                        $contentPayload = '';
                    endif;
                    $content_hash = sha1($contentPayload);

                    $priceHashData = array(
                        $price_usd,
                        $price_usd_foil,
                        $price_usd_etched,
                        $price_sort
                    );
                    $pricePayload = json_encode($priceHashData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if ($pricePayload === false) :
                        $msg->logMessage('[WARNING]', "Failed to JSON encode price_hash data for $id");
                        $pricePayload = '';
                    endif;
                    $price_hash = sha1($pricePayload);

                    $content_changed = false;
                    $price_changed = false;
                    $existing_content_hash = null;
                    $existing_price_hash = null;
                    $existing_id = null;
                    $existing_found = false;
                    $hash_lookup = 'none';

                    $hash_id = $id;
                    $hashExec = $hashStmt->execute();
                    if ($hashExec === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Checking existing hashes: ' . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    endif;
                    $hashStore = $hashStmt->store_result();
                    if ($hashStore === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Storing hash results: ' . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    endif;
                    $hashBindResult = $hashStmt->bind_result($existing_content_hash, $existing_price_hash);
                    if ($hashBindResult === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Binding hash results: ' . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    endif;
                    if ($hashStmt->num_rows > 0) :
                        $hashStmt->fetch();
                        $existing_found = true;
                        $existing_id = $id;
                        $hash_lookup = 'id';
                        $content_changed = ($existing_content_hash !== $content_hash);
                        $price_changed = ($existing_price_hash !== $price_hash);
                    endif;
                    $hashStmt->free_result();

                    // Execute using already-bound params
                    $exec = $stmt->execute();

                    if ($exec === false) :
                        mtgError(
                            E_USER_ERROR,
                            "[ERROR] scryfall_bulk.php: Writing new card details: " . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    else :
                        $lastGoodId = $id;
                        $lastGoodCount = $total_count;
                        $status = $stmt->affected_rows; // 1 = add, 2 = change, 0 = no change

                        if ($status === 1) :
                            $count_add = $count_add + 1;
                            $msg->logMessage('[DEBUG]', "Added card - no error returned; return code: $status");

                            if ($syncStmt !== null) :
                                $syncLookupId = $id;
                                if (!$syncStmt->execute()) :
                                    throw new \Exception(
                                        '[ERROR] scryfall_bulk.php: Updating sync state for added card: '
                                        . $db->error
                                    );
                                endif;
                            endif;

                            if ($imageDownloads === true) :
                                $imageManager->getImage(
                                    $set_code,
                                    $id,
                                    $layout
                                );
                            endif;
                        elseif ($status === 2) :
                            $count_update = $count_update + 1;
                            if ($content_changed === true and $price_changed === true) :
                                $count_update_both = $count_update_both + 1;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Updated card - content and price hash change; return code: $status"
                                );
                                if ($syncStmt !== null) :
                                    $syncLookupId = $id;
                                    if (!$syncStmt->execute()) :
                                        throw new \Exception(
                                            '[ERROR] scryfall_bulk.php: Updating sync state for content update: '
                                            . $db->error
                                        );
                                    endif;
                                endif;
                            elseif ($content_changed === true) :
                                $count_update_content = $count_update_content + 1;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Updated card - content hash change; return code: $status"
                                );
                                if ($syncStmt !== null) :
                                    $syncLookupId = $id;
                                    if (!$syncStmt->execute()) :
                                        throw new \Exception(
                                            '[ERROR] scryfall_bulk.php: Updating sync state for content update: '
                                            . $db->error
                                        );
                                    endif;
                                endif;
                            elseif ($price_changed === true) :
                                $count_update_price = $count_update_price + 1;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Updated card - price hash change; return code: $status"
                                );
                            else :
                                $msg->logMessage(
                                    '[WARNING]',
                                    "Updated card - hash change not detected; return code: $status"
                                );
                            endif;
                        else :
                            $count_other = $count_other + 1;
                            $msg->logMessage('[DEBUG]', "No change - no error returned; return code: $status");
                        endif;
                    endif;
                endif;
                if ($commit_due) :
                    $commitResult = $db->commit();
                    if ($commitResult === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Committing transaction batch: ' . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    endif;
                    $msg->logMessage('[DEBUG]', "Committed transaction batch at record $total_count");
                    $batchStart = $db->begin_transaction();
                    if ($batchStart === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Starting transaction batch: ' . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    endif;
                endif;
                if ($log_due) :
                    $timeslice = microtime(true) - $timeslice_start;
                    $commit_note = $commit_due ? '; batch committed' : '';
                    $msg->logMessage(
                        '[NOTICE]',
                        "Scryfall bulk API ($type) progress: $total_count records processed; timeslice: "
                        . sprintf('%.2f', $timeslice) . "s{$commit_note}"
                    );
                    $timeslice_start = microtime(true);
                endif;
            endforeach;
        } catch (Throwable $e) {
            $msg->logMessage(
                '[ERROR]',
                "Bulk import aborted (likely truncated JSON). Last good: {$lastGoodId} at {$lastGoodCount}. "
                . "File: {$file_location}. Error: " . $e->getMessage()
            );
            $db->rollback();
            if ($syncStmt !== null) :
                $syncStmt->close();
            endif;
            $stmt->close();
            $hashStmt->close();

            $badPath = $file_location . '.bad-' . date('Ymd-His');
            $renamed = @rename($file_location, $badPath);
            $msg->logMessage(
                $renamed ? '[NOTICE]' : '[WARNING]',
                $renamed
                    ? "Quarantined bad JSON to {$badPath}"
                    : "Failed to quarantine JSON from {$file_location} to {$badPath}"
            );

            return "FAILED: aborted at {$lastGoodCount} (id {$lastGoodId}). Quarantined to {$badPath}";
        }
        $commitResult = $db->commit();
        if ($commitResult === false) :
            throw new \Exception('[ERROR] scryfall_bulk.php: Final commit failed: ' . $db->error);
        endif;
        $stmt->close();
        $hashStmt->close();
        if ($syncStmt !== null) :
            $syncStmt->close();
        endif;

        $msg->logMessage(
            '[NOTICE]',
            "Bulk update completed: Total $total_count, added: $count_add, skipped $count_skip, "
            . "included $count_inc, updated: $count_update (content: $count_update_content, "
            . "price: $count_update_price, both: $count_update_both), unchanged: $count_other"
        );
        if (func_num_args() >= 7) :
            $stats = [
                'total' => $total_count,
                'included' => $count_inc,
                'skipped' => $count_skip,
                'added' => $count_add,
                'updated' => $count_update,
                'content_only' => $count_update_content,
                'price_only' => $count_update_price,
                'both' => $count_update_both,
                'other' => $count_other
            ];
        endif;
        $message = "Total: $total_count; total added: $count_add; total skipped: $count_skip; "
            . "total included: $count_inc; total updated: $count_update (content: $count_update_content; "
            . "price: $count_update_price; both: $count_update_both)";
        return $message;
        // return $message to then use in parent to send email using MyPHPMailer
    }

    /**
    * @param \mysqli|object $db
    */
    public static function backfillDataSyncState($db, Message $msg): int
    {
        $msg->logMessage('[NOTICE]', 'Scryfall sync state: starting data backfill');

        $sql = "INSERT INTO
            `scryfall_sync_state`
                (id, manifest_data_updated_at, data_checked_at)
            SELECT
                cards_scry.id,
                scryfall_manifest.data_updated_at,
                NOW()
            FROM
                `cards_scry`
            LEFT JOIN
                `scryfall_manifest`
                ON scryfall_manifest.id = cards_scry.id
            ON DUPLICATE KEY UPDATE
                manifest_data_updated_at = VALUES(manifest_data_updated_at),
                data_checked_at = VALUES(data_checked_at)";

        $result = $db->query($sql);
        if ($result === false) :
            throw new \Exception('[ERROR] scryfall_sync_state: data backfill failed: ' . $db->error);
        endif;

        $affected = isset($db->affected_rows) ? (int) $db->affected_rows : 0;
        $msg->logMessage('[NOTICE]', "Scryfall sync state: data backfill completed; affected rows: $affected");
        return $affected;
    }

}
