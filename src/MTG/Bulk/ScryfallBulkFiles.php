<?php

/*
Version:     1.0
Date:        08/07/26
Name:        ScryfallBulkFiles.php
Purpose:     Handles Scryfall bulk metadata downloads and local bulk data file iteration.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use Generator;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\Message;
use MTG\Core\UserAgent;

class ScryfallBulkFiles
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

        if (!is_file($tmp) || filesize($tmp) === 0) :
            @unlink($tmp);
            $msg->logMessage('[ERROR]', "$context: download produced empty file: $tmp");
            return false;
        endif;

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

    public static function getBulkInfo(
        string $type,
        AppConfig $appConfig,
        GameRules $gameRules,
        ?callable $fetchJson = null
    ): array|false {
        $defaultCardsUrl = self::requireGameRuleUrl($gameRules, 'defaultCardsUrl');
        $allCardsUrl = self::requireGameRuleUrl($gameRules, 'allCardsUrl');
        $imgLocation = (string) $appConfig->general('imageBaseDir', '');
        $msg = new Message($appConfig);
        $bulkInfo = false;
        $fetchJson ??= [self::class, 'fetchJson'];

        $url = $urlDefault = $urlAll = $fileLocation = $fileLocationDefault = $fileLocationAll = '';
        $scryfallBulk = $scryfallBulkDefault = $scryfallBulkAll = null;
        $msg->logMessage('[NOTICE]', "scryfall bulk API: called with '$type'");

        if ($type === "all") :
            $url = $allCardsUrl;
            $fileLocation = $imgLocation . 'json/bulk_all.jsonl.gz';
        elseif ($type === "default") :
            $url = $defaultCardsUrl;
            $fileLocation = $imgLocation . 'json/bulk.jsonl.gz';
        elseif ($type === "refresh") :
            $urlDefault = $defaultCardsUrl;
            $urlAll = $allCardsUrl;
            $fileLocationDefault = $imgLocation . 'json/bulk.jsonl.gz';
            $fileLocationAll = $imgLocation . 'json/bulk_all.jsonl.gz';
        else :
            $type = "default";
            $url = $defaultCardsUrl;
            $fileLocation = $imgLocation . 'json/bulk.jsonl.gz';
        endif;

        if (!empty($url) && !empty($fileLocation)) :
            $msg->logMessage('[NOTICE]', "Scryfall bulk API: fetching current URL $url");
            $scryfallBulk = $fetchJson($url, $msg, 'Scryfall bulk API', $appConfig);
            if ($scryfallBulk === false) :
                return false;
            endif;
        elseif (
            !empty($urlDefault)
            && !empty($urlAll)
            && !empty($fileLocationDefault)
            && !empty($fileLocationAll)
        ) :
            $msg->logMessage('[NOTICE]', "Scryfall bulk API: fetching current URL $urlDefault");
            $scryfallBulkDefault = $fetchJson($urlDefault, $msg, 'Scryfall bulk API', $appConfig);
            if ($scryfallBulkDefault === false) :
                return false;
            endif;
            $msg->logMessage('[NOTICE]', "Scryfall bulk API: fetching current URL $urlAll");
            $scryfallBulkAll = $fetchJson($urlAll, $msg, 'Scryfall bulk API', $appConfig);
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

    public static function getBulkDataFile(
        string $uri,
        string $file_location,
        int $max_fileage,
        AppConfig $appConfig,
        ?callable $downloadBulk = null
    ): string|false {
        $msg = new Message($appConfig);
        $downloadBulk ??= [self::class, 'downloadBulk'];

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

        $ok = $downloadBulk($uri, $file_location, $msg, $appConfig, 'Scryfall bulk API download', false);
        if ($ok === true) :
            $size = filesize($file_location);
            $msg->logMessage(
                '[NOTICE]',
                "Scryfall bulk API: Download OK, file at ($file_location), size ($size), proceeding"
            );
            return 'Success';
        endif;

        $msg->logMessage('[ERROR]', "Scryfall bulk API: Download failed, retrying in 20 seconds");
        sleep(20);

        $ok = $downloadBulk($uri, $file_location, $msg, $appConfig, 'Scryfall bulk API download', false);
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
        return self::getBulkDataFile($uri, $file_location, $max_fileage, $appConfig);
    }

    /**
     * @return Generator<int|string, array<string, mixed>>
     */
    public static function iterateBulkRecords(string $fileLocation): Generator
    {
        if (str_ends_with($fileLocation, '.jsonl.gz')) :
            yield from self::iterateJsonlGzipRecords($fileLocation);
            return;
        endif;

        if (str_ends_with($fileLocation, '.jsonl')) :
            yield from self::iterateJsonlRecords($fileLocation);
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
                $record = self::decodeJsonlLine($line, $lineNumber, $fileLocation);
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
                $record = self::decodeJsonlLine($line, $lineNumber, $fileLocation);
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
}
