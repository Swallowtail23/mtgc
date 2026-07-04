<?php

/*
Version:     3.24
Date:        04/07/26
Name:        scryfall_rulings.php
Purpose:     Import/update Scryfall rulings data
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
History:     See git history / CHANGELOG.md
To do:       -
*/


use MTG\Bulk\ScryfallImport;
use MTG\Bulk\RulingsHasher;
use MTG\Core\Filesystem;
use MTG\Core\MyPHPMailer;

$ctx = require __DIR__ . '/bulk_ini.php';

$appConfig = $ctx->config();
$db = $ctx->db();
$msg = $ctx->message();
$gameRules = $ctx->rules();

$adminEmail = (string) $appConfig->email('adminEmail', '');
$emailEnabled = (bool) $appConfig->email('enabled', false);
$imgLocation = (string) $appConfig->general('imageBaseDir', '');

Filesystem::ensureDirectoryExists($imgLocation . 'json', $appConfig, $msg);

// How old to overwrite
$max_fileage = 23 * 3600;

// Scryfall rulings bulk metadata URL
$url = $gameRules->get('rulingsUrl');
if (!is_string($url) || trim($url) === '') :
    throw new Exception("[ERROR] scryfall_rulings.php: Missing Scryfall game rule 'rulingsUrl'. "
        . "Define it in includes/game_rules.php.");
endif;
$url = trim($url);

// Bulk file store point
$file_location = $imgLocation . 'json/rulings.jsonl.gz';

// Set counts
$total_count = 0;
$count_add = 0;
$count_update = 0;
$count_other = 0;

$batch_size = 5000;
$log_interval = 2500;
$timeslice_start = microtime(true);

$msg->logMessage('[NOTICE]', "Scryfall Rulings API: fetching $url");
$scryfall_rulings = ScryfallImport::fetchJson($url, $msg, 'Scryfall Rulings API', $appConfig);
if ($scryfall_rulings === false || ($scryfall_rulings["type"] ?? '') !== "rulings") :
    throw new Exception('[ERROR] scryfall_rulings.php: Scryfall rulings bulk metadata unavailable');
endif;

$rulings_uri = $scryfall_rulings["jsonl_download_uri"] ?? null;
if ($rulings_uri === null || $rulings_uri === '') :
    throw new Exception('[ERROR] scryfall_rulings.php: Scryfall rulings jsonl_download_uri not available');
endif;
$msg->logMessage('[NOTICE]', "Scryfall Rulings API: Download URI: $rulings_uri");

if (file_exists($file_location)) :
    $fileage = filemtime($file_location);
    $file_date = date('d-m-Y H:i', $fileage);
    if (time() - $fileage > $max_fileage) :
        $download = 2;
        $msg->logMessage(
            '[NOTICE]',
            "Scryfall Rulings API: File old ($file_location, $file_date), downloading $rulings_uri"
        );
    else :
        $download = 0;
        $msg->logMessage(
            '[NOTICE]',
            "Scryfall Rulings API: File fresh ($file_location, $file_date), skipping download"
        );
    endif;
else :
    $download = 1;
    $msg->logMessage('[NOTICE]', "Scryfall Rulings API: No file at ($file_location), downloading: $rulings_uri");
endif;
if ($download > 0) :
    $msg->logMessage('[NOTICE]', "Scryfall Rulings API: downloading: $rulings_uri");
    $rulingreturn = ScryfallImport::getBulkDataFile($rulings_uri, $file_location, 0, $appConfig);
    if ($rulingreturn === false) :
        throw new Exception('[ERROR] scryfall_rulings.php: Scryfall rulings data download failed');
    endif;
endif;
$msg->logMessage('[NOTICE]', "Scryfall Rulings API: Local file: $file_location");

$data = ScryfallImport::iterateBulkRecords($file_location);
$msg->logMessage('[DEBUG]', 'Checking for rulings_scry content_hash column');
$hashColumnResult = $db->query("SHOW COLUMNS FROM `rulings_scry` LIKE 'content_hash'");
if ($hashColumnResult === false) :
    throw new Exception('[ERROR] scryfall_rulings.php: Checking rulings_scry content_hash: ' . $db->error);
elseif ($hashColumnResult->num_rows === 0) :
    $msg->logMessage('[FATAL]', 'rulings_scry content_hash column missing; aborting import');
    exit(1);
else :
    $msg->logMessage('[DEBUG]', 'rulings_scry content_hash column present');
endif;
if ($hashColumnResult !== false) :
    $hashColumnResult->free();
endif;

$msg->logMessage('[DEBUG]', 'Checking for rulings_scry content_hash unique key');
$hashIndexResult = $db->query("SHOW INDEX FROM `rulings_scry` WHERE Key_name = 'rulings_unique'");
if ($hashIndexResult === false) :
    throw new Exception('[ERROR] scryfall_rulings.php: Checking rulings_scry unique index: ' . $db->error);
elseif ($hashIndexResult->num_rows === 0) :
    $msg->logMessage('[FATAL]', 'rulings_scry content_hash unique key missing; aborting import');
    exit(1);
else :
    $msg->logMessage('[DEBUG]', 'rulings_scry content_hash unique key present');
endif;
if ($hashIndexResult !== false) :
    $hashIndexResult->free();
endif;

$msg->logMessage('[DEBUG]', 'Preparing temporary rulings key table');
$tempResult = $db->query("DROP TEMPORARY TABLE IF EXISTS `rulings_scry_keys`");
if ($tempResult === false) :
    throw new Exception('[ERROR] scryfall_rulings.php: Dropping temp table: ' . $db->error);
endif;
$tempResult = $db->query("CREATE TEMPORARY TABLE `rulings_scry_keys` (
    `content_hash` char(40) NOT NULL,
    PRIMARY KEY (`content_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
if ($tempResult === false) :
    throw new Exception('[ERROR] scryfall_rulings.php: Creating temp table: ' . $db->error);
endif;

$stmt = $db->prepare("INSERT INTO
                        `rulings_scry`
                            (oracle_id, source, published_at, comment, content_hash)
                        VALUES
                            (?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE
                            comment = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(comment),
                                comment
                            ),
                            content_hash = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(content_hash),
                                content_hash
                            )");
if ($stmt === false) :
    throw new Exception('[ERROR] scryfall_rulings: Preparing SQL: ' . $db->error);
endif;

$keyStmt = $db->prepare("INSERT IGNORE INTO `rulings_scry_keys` (content_hash) VALUES (?)");
if ($keyStmt === false) :
    throw new Exception('[ERROR] scryfall_rulings: Preparing key SQL: ' . $db->error);
endif;

$oracle_id = null;
$source = null;
$published = null;
$comment = null;
$content_hash = null;

$bind = $stmt->bind_param("sssss", $oracle_id, $source, $published, $comment, $content_hash);
if ($bind === false) :
    throw new Exception('[ERROR] scryfall_rulings: Binding parameters: ' . $db->error);
endif;
$keyBind = $keyStmt->bind_param("s", $content_hash);
if ($keyBind === false) :
    throw new Exception('[ERROR] scryfall_rulings: Binding key parameters: ' . $db->error);
endif;

$hasher = new RulingsHasher();

$msg->logMessage('[DEBUG]', 'Starting rulings import transaction batch');
$batchStart = $db->begin_transaction();
if ($batchStart === false) :
    throw new Exception('[ERROR] scryfall_rulings.php: Starting transaction batch: ' . $db->error);
endif;

try {
    foreach ($data as $key => $value) :
        $total_count = $total_count + 1;
        $commit_due = ($total_count % $batch_size === 0);
        $log_due = ($total_count % $log_interval === 0);

        $oracle_id = $value["oracle_id"] ?? null;
        $source = $value["source"] ?? null;
        $published = $value["published_at"] ?? null;
        $comment = $value["comment"] ?? null;

        if ($oracle_id === null || $source === null || $published === null || $comment === null) :
            $count_other = $count_other + 1;
            $msg->logMessage('[WARNING]', "Skipping ruling $total_count: missing required field");
            if ($commit_due) :
                $commitResult = $db->commit();
                if ($commitResult === false) :
                    throw new Exception('[ERROR] scryfall_rulings.php: Committing transaction batch: ' . $db->error);
                endif;
                $msg->logMessage('[DEBUG]', "Committed transaction batch at ruling $total_count");
                $batchStart = $db->begin_transaction();
                if ($batchStart === false) :
                    throw new Exception('[ERROR] scryfall_rulings.php: Starting transaction batch: ' . $db->error);
                endif;
            endif;
            if ($log_due) :
                $timeslice = microtime(true) - $timeslice_start;
                $commit_note = $commit_due ? '; batch committed' : '';
                $msg->logMessage(
                    '[NOTICE]',
                    "Scryfall rulings progress: $total_count records processed; timeslice: "
                    . sprintf('%.2f', $timeslice) . "s{$commit_note}"
                );
                $timeslice_start = microtime(true);
            endif;
            continue;
        endif;

        $content_hash = $hasher->buildContentHash($oracle_id, $source, $published, $comment);

        if (!$keyStmt->execute()) :
            throw new Exception("[ERROR] scryfall_rulings: Writing ruling key: " . $db->error);
        endif;

        if (!$stmt->execute()) :
            throw new Exception("[ERROR] scryfall_rulings: Writing new ruling details: " . $db->error);
        else :
            $status = $stmt->affected_rows; // 1 = add, 2 = change, 0 = no change
            if ($status === 1) :
                $count_add = $count_add + 1;
                $msg->logMessage('[DEBUG]', "Added ruling $total_count - no error returned");
            elseif ($status === 2) :
                $count_update = $count_update + 1;
                $msg->logMessage('[DEBUG]', "Updated ruling $total_count - no error returned");
            else :
                $count_other = $count_other + 1;
                $msg->logMessage('[DEBUG]', "No change for ruling $total_count - no error returned");
            endif;
        endif;

        if ($commit_due) :
            $commitResult = $db->commit();
            if ($commitResult === false) :
                throw new Exception('[ERROR] scryfall_rulings.php: Committing transaction batch: ' . $db->error);
            endif;
            $msg->logMessage('[DEBUG]', "Committed transaction batch at ruling $total_count");
            $batchStart = $db->begin_transaction();
            if ($batchStart === false) :
                throw new Exception('[ERROR] scryfall_rulings.php: Starting transaction batch: ' . $db->error);
            endif;
        endif;
        if ($log_due) :
            $timeslice = microtime(true) - $timeslice_start;
            $commit_note = $commit_due ? '; batch committed' : '';
            $msg->logMessage(
                '[NOTICE]',
                "Scryfall rulings progress: $total_count records processed; timeslice: "
                . sprintf('%.2f', $timeslice) . "s{$commit_note}"
            );
            $timeslice_start = microtime(true);
        endif;
    endforeach;
} catch (Throwable $e) {
    $msg->logMessage('[ERROR]', 'Scryfall rulings import aborted: ' . $e->getMessage());
    $db->rollback();
    $stmt->close();
    $keyStmt->close();
    throw $e;
}

$commitResult = $db->commit();
if ($commitResult === false) :
    throw new Exception('[ERROR] scryfall_rulings.php: Final commit failed: ' . $db->error);
endif;
$stmt->close();
$keyStmt->close();

$msg->logMessage('[DEBUG]', 'Removing rulings no longer present in Scryfall data');
$deleteResult = $db->query(
    "DELETE rs FROM `rulings_scry` rs
    LEFT JOIN `rulings_scry_keys` rk
        ON rs.content_hash = rk.content_hash
    WHERE rk.content_hash IS NULL"
);
if ($deleteResult === false) :
    throw new Exception('[ERROR] scryfall_rulings.php: Deleting stale rulings: ' . $db->error);
endif;
$deleted_count = $db->affected_rows;

$msg->logMessage(
    '[NOTICE]',
    "$total_count rulings completed (added: $count_add; updated: $count_update; unchanged: $count_other; "
    . "removed: $deleted_count)"
);
if (php_sapi_name() == 'cli') :
    echo "Rulings: $total_count completed (added: $count_add; updated: $count_update; "
        . "unchanged: $count_other; removed: $deleted_count)\n";
endif;

// Email results
$subject = "MTG rulings update completed";
$body = "Total rulings: $total_count; added: $count_add; updated: $count_update; unchanged: $count_other; "
    . "removed: $deleted_count";
if (isset($emailEnabled) && $emailEnabled === true) :
    $mail = new MyPHPMailer(true, $appConfig);
    $mailresult = $mail->sendEmail($adminEmail, false, $subject, $body);
else :
    $msg->logMessage('[NOTICE]', 'Email disabled; skipping scryfall_rulings alert');
    $mailresult = false;
endif;
$msg->logMessage(
    '[NOTICE]',
    $mailresult === false
        ? 'Email result: not sent (disabled or failure)'
        : "Email result: $mailresult"
);
