<?php

/*
Version:     1.0
Date:        10/07/26
Name:        ScryfallManifestImport.php
Purpose:     Import/update Scryfall card manifest metadata.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;
use MTG\Core\AppConfig;
use MTG\Core\Filesystem;
use MTG\Core\MyPHPMailer;
use Throwable;

class ScryfallManifestImport
{
    private static ?float $lastRequestAt = null;
    private static mixed $message = null;

    public static function run(mixed $ctx): void
    {

$appConfig = $ctx->config();
$db = $ctx->db();
$msg = $ctx->message();
$gameRules = $ctx->rules();
$GLOBALS['msg'] = $msg;

$adminEmail = (string) $appConfig->email('adminEmail', '');
$imgLocation = (string) $appConfig->general('imageBaseDir', '');

Filesystem::ensureDirectoryExists($imgLocation . 'json', $appConfig, $msg);

$starturl = $gameRules->get('manifestUrl');
if (!is_string($starturl) || trim($starturl) === '') :
    throw new Exception("[ERROR] scryfall_manifest.php: Missing Scryfall game rule 'manifestUrl'. "
        . "Define it in includes/game_rules.php.");
endif;
$starturl = trim($starturl);
$emailEnabled = (bool) $appConfig->email('enabled', false);

$file_folder = $imgLocation . 'json/';
$max_fileage = 23 * 3600;
$manifest_request_interval = 10.0;
global $manifest_last_request_at;
$manifest_last_request_at = null;
$batch_size = 5000;
$log_interval = 10000;
$timeslice_start = microtime(true);
$total_count = 0;

function throttleManifestRequest(float $minimumIntervalSeconds): void
{
    global $manifest_last_request_at, $msg;
    if ($manifest_last_request_at !== null) :
        $elapsed = microtime(true) - $manifest_last_request_at;
        if ($elapsed < $minimumIntervalSeconds) :
            $sleepSeconds = $minimumIntervalSeconds - $elapsed;
            $msg->logMessage(
                '[DEBUG]',
                'Scryfall manifest API: rate-limit pause for ' . sprintf('%.2f', $sleepSeconds) . ' seconds'
            );
            usleep((int) ceil($sleepSeconds * 1000000));
        endif;
    endif;

    $manifest_last_request_at = microtime(true);
}

function getManifestData(
    string $url,
    string $file_location,
    int $max_fileage,
    float $minimumIntervalSeconds,
    int $pageNumber,
    string $language,
    AppConfig $appConfig
): string {
    global $msg;

    $msg->logMessage('[DEBUG]', "Scryfall manifest API: fetching $url for language $language");
    if ($pageNumber === 0) :
        $page = $file_location . 'manifest_' . $language . '.json';
    else :
        $page = $file_location . 'manifest_' . $language . '_' . $pageNumber . '.json';
    endif;

    if (file_exists($page)) :
        $fileage = filemtime($page);
        $file_date = date('d-m-Y H:i', $fileage);
        if (time() - $fileage > $max_fileage) :
            $download = 2;
            $msg->logMessage('[DEBUG]', "Scryfall manifest API: File old ($page, $file_date), downloading");
        else :
            $download = 0;
            $msg->logMessage('[DEBUG]', "Scryfall manifest API: File fresh ($page, $file_date), skipping download");
        endif;
    else :
        $download = 1;
        $msg->logMessage('[DEBUG]', "Scryfall manifest API: No file at ($page), downloading: $url");
    endif;

    if ($download > 0) :
        throttleManifestRequest($minimumIntervalSeconds);
        ScryfallImport::downloadBulk(
            $url,
            $page,
            $msg,
            $appConfig,
            'Scryfall manifest data download',
            false
        );
    endif;

    return $page;
}

function getManifestLanguages(\mysqli $db): array
{
    global $msg;

    $result = $db->execute_query(
        "SELECT DISTINCT lang
        FROM cards_scry
        WHERE lang IS NOT NULL AND lang <> ''
        ORDER BY lang"
    );
    if ($result === false) :
        throw new Exception('[ERROR] scryfall_manifest.php: Fetching manifest languages: ' . $db->error);
    endif;

    $languages = [];
    while ($row = $result->fetch_assoc()) :
        $language = isset($row['lang']) && is_string($row['lang']) ? trim($row['lang']) : '';
        if ($language === '') :
            continue;
        endif;
        if (!preg_match('/^[a-z0-9]{2,3}$/i', $language)) :
            throw new Exception("[ERROR] scryfall_manifest.php: Invalid card language '$language'");
        endif;
        $languages[] = strtolower($language);
    endwhile;

    if ($languages === []) :
        throw new Exception('[ERROR] scryfall_manifest.php: No card languages found in cards_scry.');
    endif;

    $msg->logMessage(
        '[NOTICE]',
        'Scryfall manifest API: fetching manifest languages: ' . implode(', ', $languages)
    );

    return $languages;
}

function buildManifestLanguageUrl(string $baseUrl, string $language): string
{
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    return $baseUrl . $separator . 'lang=' . rawurlencode($language);
}

function checkManifestDataForMore(string $file): string
{
    global $msg;

    $data = Items::fromFile($file, ['decoder' => new ExtJsonDecoder(true)]);
    $hasMore = false;
    $next_page = 'none';
    foreach ($data as $key => $value) :
        if ($key === 'has_more') :
            $hasMore = ($value === true || $value === 'true' || $value === 1 || $value === '1');
            if ($hasMore) :
                $msg->logMessage('[DEBUG]', 'Scryfall manifest API: Further pages available');
            endif;
        endif;
        if ($hasMore && $key === 'next_page' && is_string($value) && trim($value) !== '') :
            $next_page = trim($value);
        endif;
    endforeach;

    return $next_page;
}

function getManifestRowCount(string $file): int
{
    $data = Items::fromFile($file, ['decoder' => new ExtJsonDecoder(true)]);
    $count = 0;
    foreach ($data as $key => $value) :
        if ($key === 'data') :
            foreach ($value as $manifest) :
                if (is_array($manifest) && isset($manifest['id'])) :
                    $count = $count + 1;
                endif;
            endforeach;
        endif;
    endforeach;

    return $count;
}

function clearDBManifest(\mysqli $db): void
{
    global $msg;

    if ($db->query('TRUNCATE TABLE scryfall_manifest')) :
        $msg->logMessage('[NOTICE]', 'Scryfall manifest API: scryfall_manifest table cleared');
    else :
        throw new Exception('[ERROR] scryfall_manifest.php: Clearing manifest table: ' . $db->error);
    endif;
}

function manifestDateTime(?string $value): ?string
{
    if ($value === null || trim($value) === '') :
        return null;
    endif;

    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Exception) {
        throw new Exception("[ERROR] scryfall_manifest.php: Invalid Scryfall timestamp '$value'");
    }
}

$manifest_languages = getManifestLanguages($db);
$result_files = [];

foreach ($manifest_languages as $language) :
    $page = 0;
    $manifestUrl = buildManifestLanguageUrl($starturl, $language);
    $file = getManifestData(
        $manifestUrl,
        $file_folder,
        $max_fileage,
        $manifest_request_interval,
        $page,
        $language,
        $appConfig
    );
    $result_files[] = $file;
    $moreurl = checkManifestDataForMore($file);
    while ($moreurl !== 'none') :
        $page = $page + 1;
        $file = getManifestData(
            $moreurl,
            $file_folder,
            $max_fileage,
            $manifest_request_interval,
            $page,
            $language,
            $appConfig
        );
        $result_files[] = $file;
        $moreurl = checkManifestDataForMore($file);
    endwhile;
endforeach;

$total_rows = 0;
foreach ($result_files as $data) :
    $total_rows = $total_rows + getManifestRowCount($data);
endforeach;

if ($total_rows <= 0) :
    throw new Exception('[ERROR] scryfall_manifest.php: No manifest rows found; refusing to clear manifest table.');
endif;

clearDBManifest($db);

$stmt = $db->prepare(
    "INSERT INTO
        `scryfall_manifest`
            (id, created_at, data_updated_at, image_updated_at)
        VALUES
            (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            created_at = VALUES(created_at),
            data_updated_at = VALUES(data_updated_at),
            image_updated_at = VALUES(image_updated_at)"
);
if ($stmt === false) :
    throw new Exception('[ERROR] scryfall_manifest.php: Preparing SQL: ' . $db->error);
endif;

$id = null;
$created_at = null;
$data_updated_at = null;
$image_updated_at = null;
$bind = $stmt->bind_param('ssss', $id, $created_at, $data_updated_at, $image_updated_at);
if ($bind === false) :
    throw new Exception('[ERROR] scryfall_manifest.php: Binding parameters: ' . $db->error);
endif;

$msg->logMessage('[DEBUG]', 'Scryfall manifest API: Starting manifest import transaction batch');
$batchStart = $db->begin_transaction();
if ($batchStart === false) :
    throw new Exception('[ERROR] scryfall_manifest.php: Starting transaction batch: ' . $db->error);
endif;

try {
    foreach ($result_files as $data) :
        $decodedjson = Items::fromFile($data, ['decoder' => new ExtJsonDecoder(true)]);
        foreach ($decodedjson as $key => $value) :
            if ($key === 'data') :
                foreach ($value as $manifest) :
                    if (!is_array($manifest) || !isset($manifest['id']) || !is_string($manifest['id'])) :
                        throw new Exception('[ERROR] scryfall_manifest.php: Manifest row missing id.');
                    endif;

                    $id = $manifest['id'];
                    $created_at = manifestDateTime(
                        isset($manifest['created_at']) && is_string($manifest['created_at'])
                            ? $manifest['created_at']
                            : null
                    );
                    $data_updated_at = manifestDateTime(
                        isset($manifest['data_updated_at']) && is_string($manifest['data_updated_at'])
                            ? $manifest['data_updated_at']
                            : null
                    );
                    $image_updated_at = manifestDateTime(
                        isset($manifest['image_updated_at']) && is_string($manifest['image_updated_at'])
                            ? $manifest['image_updated_at']
                            : null
                    );

                    if (!$stmt->execute()) :
                        throw new Exception('[ERROR] scryfall_manifest.php: Writing manifest row: ' . $db->error);
                    endif;

                    $total_count = $total_count + 1;
                    $commit_due = ($total_count % $batch_size === 0);
                    $log_due = ($total_count % $log_interval === 0);

                    if ($commit_due) :
                        $commitResult = $db->commit();
                        if ($commitResult === false) :
                            throw new Exception(
                                '[ERROR] scryfall_manifest.php: Committing transaction batch: ' . $db->error
                            );
                        endif;
                        $msg->logMessage('[DEBUG]', "Scryfall manifest API: committed batch at row $total_count");
                        if ($total_count < $total_rows) :
                            $batchStart = $db->begin_transaction();
                            if ($batchStart === false) :
                                throw new Exception(
                                    '[ERROR] scryfall_manifest.php: Starting transaction batch: ' . $db->error
                                );
                            endif;
                        endif;
                    endif;

                    if ($log_due) :
                        $timeslice = microtime(true) - $timeslice_start;
                        $commit_note = $commit_due ? '; batch committed' : '';
                        $msg->logMessage(
                            '[NOTICE]',
                            "Scryfall manifest progress: $total_count records imported; timeslice: "
                            . sprintf('%.2f', $timeslice) . "s{$commit_note}"
                        );
                        $timeslice_start = microtime(true);
                    endif;
                endforeach;
            endif;
        endforeach;
    endforeach;

    if ($total_count % $batch_size !== 0) :
        $commitResult = $db->commit();
        if ($commitResult === false) :
            throw new Exception('[ERROR] scryfall_manifest.php: Final commit failed: ' . $db->error);
        endif;
    endif;
} catch (Throwable $e) {
    $msg->logMessage('[ERROR]', 'Scryfall manifest import aborted: ' . $e->getMessage());
    $db->rollback();
    $stmt->close();
    throw $e;
}
$stmt->close();

$msg->logMessage('[NOTICE]', "$total_count card manifest rows completed");
if (php_sapi_name() === 'cli') :
    echo "Manifest: $total_count card manifest rows completed\n";
endif;

$subject = 'MTG Scryfall manifest update completed';
$body = "Total: $total_count\n";
if ($emailEnabled === true) :
    $mail = new MyPHPMailer(true, $appConfig);
    $mailresult = $mail->sendEmail($adminEmail, false, $subject, $body);
else :
    $msg->logMessage('[NOTICE]', 'Email disabled; skipping scryfall_manifest alert');
    $mailresult = false;
endif;
$msg->logMessage(
    '[NOTICE]',
    $mailresult === false
        ? 'Email result: not sent (disabled or failure)'
        : "Email result: $mailresult"
);
    }
}
