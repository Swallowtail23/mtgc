<?php

/*
Version:     1.00
Date:        04/07/26
Name:        scryfall_manifest.php
Purpose:     Import/update Scryfall card manifest metadata
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;
use MTG\Bulk\ScryfallImport;
use MTG\Core\AppConfig;
use MTG\Core\Filesystem;
use MTG\Core\MyPHPMailer;

$ctx = require __DIR__ . '/bulk_ini.php';

$appConfig = $ctx->config();
$db = $ctx->db();
$msg = $ctx->message();
$gameRules = $ctx->rules();

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
$total_count = 0;

function getManifestData(
    string $url,
    string $file_location,
    int $max_fileage,
    int $pageNumber,
    AppConfig $appConfig
): string {
    global $msg;

    $msg->logMessage('[DEBUG]', "Scryfall manifest API: fetching $url");
    if ($pageNumber === 0) :
        $page = $file_location . 'manifest.json';
    else :
        $page = $file_location . 'manifest' . $pageNumber . '.json';
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

$page = 0;
$file = getManifestData($starturl, $file_folder, $max_fileage, $page, $appConfig);
$result_files = [];
$result_files[$page] = $file;
$moreurl = checkManifestDataForMore($file);
while ($moreurl !== 'none') :
    $page = $page + 1;
    $file = getManifestData($moreurl, $file_folder, $max_fileage, $page, $appConfig);
    $result_files[$page] = $file;
    $moreurl = checkManifestDataForMore($file);
endwhile;

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
            (?, ?, ?, ?)"
);
if ($stmt === false) :
    throw new Exception('[ERROR] scryfall_manifest.php: Preparing SQL: ' . $db->error);
endif;

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

                $stmt->bind_param(
                    'ssss',
                    $id,
                    $created_at,
                    $data_updated_at,
                    $image_updated_at
                );
                if (!$stmt->execute()) :
                    throw new Exception('[ERROR] scryfall_manifest.php: Writing manifest row: ' . $db->error);
                endif;

                $total_count = $total_count + 1;
                if ($total_count % 10000 === 0) :
                    $msg->logMessage('[DEBUG]', "Scryfall manifest API: imported $total_count rows");
                endif;
            endforeach;
        endif;
    endforeach;
endforeach;
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
