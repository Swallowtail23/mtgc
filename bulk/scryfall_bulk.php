<?php

/*
Version:     9.29
Date:        26/02/26
Name:        scryfall_bulk.php
Purpose:     Import/update Scryfall bulk data
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/


use MTG\Bulk\ScryfallImport;
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

// Start time tracking
$start = microtime(true);

Filesystem::ensureDirectoryExists($imgLocation . 'json', $appConfig, $msg);

// Get and interpret parameter 1

/// Call without parameters does a 'default' file update only
/// Call with 'all' gets the all cards file
/// Call with 'refresh' gets fresh copies of BOTH files (run by docker install for initial setup)

$arg1 = $argv[1] ?? '';
$arg1 = strtolower(trim($arg1));
$useTestTable = false;

if ($arg1 === 'test') :
    $type = 'default';
    $useTestTable = true;
    $msg->logMessage('[NOTICE]', 'Scryfall Bulk API: test mode enabled; using cards_scry_test');
elseif ($arg1 === 'all') :
    $type = 'all';
elseif ($arg1 === 'refresh') :
    $type = 'refresh';
else :
    $type = 'default';
endif;
$targetTable = $useTestTable ? 'cards_scry_test' : 'cards_scry';

if ($useTestTable) :
    $testFileFirst = APP_ROOT . '/tests/test_data/bulk_sample_10.json';
    $testFileSecond = APP_ROOT . '/tests/test_data/bulk_sample_10_copy.json';

    $msg->logMessage('[DEBUG]', 'Preparing cards_scry_test for bulk import test');
    $tableCheck = $db->query("SHOW TABLES LIKE 'cards_scry_test'");
    if ($tableCheck === false) :
        $text = "Scryfall Bulk API: Failed to check cards_scry_test existence: {$db->error}";
        $msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
        exit(1);
    endif;
    if ($tableCheck->num_rows > 0) :
        $msg->logMessage('[NOTICE]', 'cards_scry_test exists; dropping to refresh schema from cards_scry');
        $dropResult = $db->query("DROP TABLE `cards_scry_test`");
        if ($dropResult === false) :
            $text = "Scryfall Bulk API: Failed to drop cards_scry_test: {$db->error}";
            $msg->logMessage('[ERROR]', $text);
            if (PHP_SAPI === 'cli') :
                fwrite(STDERR, $text . PHP_EOL);
            endif;
            exit(1);
        endif;
    endif;
    $msg->logMessage('[NOTICE]', 'Creating cards_scry_test from cards_scry structure');
    $createResult = $db->query("CREATE TABLE `cards_scry_test` LIKE `cards_scry`");
    if ($createResult === false) :
        $text = "Scryfall Bulk API: Failed to create cards_scry_test: {$db->error}";
        $msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
        exit(1);
    endif;
    $tableCheck->free();

    if (!is_file($testFileFirst) || !is_file($testFileSecond)) :
        $text = "Scryfall Bulk API: Test files missing: {$testFileFirst} or {$testFileSecond}";
        $msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
        exit(1);
    endif;

    $msg->logMessage('[NOTICE]', 'Scryfall Bulk API: test run 1 (baseline) starting');
    $bulkResultFirst = ScryfallImport::scryfallImport(
        $testFileFirst,
        'default',
        $targetTable,
        $db,
        $appConfig,
        $gameRules,
        $statsFirst
    );
    if ($bulkResultFirst === false) :
        $text = "Scryfall Bulk API: Test run 1 failed for {$testFileFirst}";
        $msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
        exit(1);
    endif;

    $msg->logMessage('[NOTICE]', 'Scryfall Bulk API: test run 2 (mutated) starting');
    $bulkResultSecond = ScryfallImport::scryfallImport(
        $testFileSecond,
        'default',
        $targetTable,
        $db,
        $appConfig,
        $gameRules,
        $statsSecond
    );
    if ($bulkResultSecond === false) :
        $text = "Scryfall Bulk API: Test run 2 failed for {$testFileSecond}";
        $msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
        exit(1);
    endif;

    $report = sprintf(
        'Test summary: total %d, added %d, price only %d, content only %d, both %d',
        $statsSecond['total'] ?? 0,
        $statsSecond['added'] ?? 0,
        $statsSecond['price_only'] ?? 0,
        $statsSecond['content_only'] ?? 0,
        $statsSecond['both'] ?? 0
    );
    $msg->logMessage('[NOTICE]', $report);
    if (PHP_SAPI === 'cli') :
        echo $report . PHP_EOL;
    endif;
    exit(0);
endif;

// Get info on required files to download and their local locations
$bulkInfo = ScryfallImport::getBulkInfo($type, $appConfig, $gameRules);
if ($type === 'refresh') :
    $required = array('bulkUrlAll','bulkUrlDefault','fileLocationAll','fileLocationDefault');
else :
    $required = array('bulkUrl', 'fileLocation');
endif;

if ($bulkInfo === false || !is_array($bulkInfo)) :
    $text = "Scryfall Bulk API: Download URI: bulk_info function failed to return usable results";
    $msg->logMessage('[ERROR]', $text);
    if (PHP_SAPI === 'cli') :
        fwrite(STDERR, $text . PHP_EOL);
    endif;
    exit(1);
endif;

foreach ($required as $k) :
    if (!isset($bulkInfo[$k]) || $bulkInfo[$k] === '') :
        $text = "Scryfall Bulk API: bulkInfo missing key '$k'";
        $msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
        exit(1);
    endif;
endforeach;

if ($type === "refresh") :
    $bulkUrlAll = $bulkInfo['bulkUrlAll'];
    $bulkUrlDefault = $bulkInfo['bulkUrlDefault'];
    $fileLocationAll = $bulkInfo['fileLocationAll'];
    $fileLocationDefault = $bulkInfo['fileLocationDefault'];
    $msg->logMessage(
        '[NOTICE]',
        "Scryfall Bulk API: Download URIs: $bulkUrlAll / $bulkUrlDefault; File locations: "
        . "$fileLocationAll / $fileLocationDefault"
    );
    $maxFileAge = 0;
    $get_all = ScryfallImport::getBulkJson($bulkUrlAll, $fileLocationAll, $maxFileAge, $appConfig);
    $get_default = ScryfallImport::getBulkJson($bulkUrlDefault, $fileLocationDefault, $maxFileAge, $appConfig);
    if ($get_all === false) :
        $text = "Scryfall Bulk API: getBulkJson (all) returned error for $bulkUrlAll";
        $msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
        exit(1);
    elseif ($get_default === false) :
        $text = "Scryfall Bulk API: getBulkJson (default) returned error for $bulkUrlDefault";
        $msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
        exit(1);        
    else :
        // Tag time progress after getting bulk files
        $elapsed = microtime(true) - $start;
        $msg->logMessage('[NOTICE]', sprintf('Time after bulk files obtained: %.2f seconds', $elapsed));
        // Run 1 - 'all', no images
        $bulkResultAll = ScryfallImport::scryfallImport(
            $fileLocationAll,
            'all',
            $targetTable,
            $db,
            $appConfig,
            $gameRules
        );
        // Tag time progress after import finished
        $elapsed = microtime(true) - $start;
        $msg->logMessage('[NOTICE]', sprintf('Time after "all" import completed: %.2f seconds', $elapsed));
        if ($bulkResultAll === false) :
            $text = "Scryfall Bulk API: scryfallImport from $fileLocationAll failed for type 'all'";
            $msg->logMessage('[ERROR]', $text);
            if (PHP_SAPI === 'cli') :
                fwrite(STDERR, $text . PHP_EOL);
            endif;
            exit(1);
        endif;
        if (PHP_SAPI === 'cli') :
            echo "Scryfall Bulk API: MTG bulk update completed (all), $bulkResultAll\n";
        endif;            
        // Run 2 - 'default', assigns primary language
        $bulkResultDefault = ScryfallImport::scryfallImport(
            $fileLocationDefault,
            'default',
            $targetTable,
            $db,
            $appConfig,
            $gameRules
        );
        // Tag time progress after import finished
        $elapsed = microtime(true) - $start;
        $msg->logMessage('[NOTICE]', sprintf('Time after "default" import completed: %.2f seconds', $elapsed));
        if ($bulkResultDefault === false) :
            $text = "Scryfall Bulk API: scryfallImport from $fileLocationDefault failed for type 'default'";
            $msg->logMessage('[ERROR]', $text);
            if (PHP_SAPI === 'cli') :
                fwrite(STDERR, $text . PHP_EOL);
            endif;
            exit(1);
        endif;
        if (PHP_SAPI === 'cli') :
            echo "Scryfall Bulk API: MTG bulk update completed (default), $bulkResultDefault\n";
        endif; 
    endif;
else :
    $bulkUrl = $bulkInfo['bulkUrl'];
    $fileLocation = $bulkInfo['fileLocation'];
    $msg->logMessage('[NOTICE]', "Scryfall Bulk API: Download URI: $bulkUrl; File location: $fileLocation");
    $maxFileAge = 23 * 3600;
    $get_json = ScryfallImport::getBulkJson($bulkUrl, $fileLocation, $maxFileAge, $appConfig);
    if ($get_json === false) :
        $text = "Scryfall Bulk API: Download URI: getBulkJson returned error for $bulkUrl";
        $msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
        exit(1);
    else :
        // Tag time progress after getting bulk files
        $elapsed = microtime(true) - $start;
        $msg->logMessage('[NOTICE]', sprintf('Time after bulk files obtained: %.2f seconds', $elapsed));
        $bulkResultMessage = ScryfallImport::scryfallImport(
            $fileLocation,
            $type,
            $targetTable,
            $db,
            $appConfig,
            $gameRules
        );
        if ($bulkResultMessage === false) :
            $text = "Scryfall Bulk API: scryfallImport from $fileLocation failed for type '$type'";
            $msg->logMessage('[ERROR]', $text);
            if (PHP_SAPI === 'cli') :
                fwrite(STDERR, $text . PHP_EOL);
            endif;
            exit(1);
        endif;
        // Tag time progress after import finished
        $elapsed = microtime(true) - $start;
        $msg->logMessage('[NOTICE]', sprintf('Time after import completed: %.2f seconds', $elapsed));
        $subject = "MTG bulk update completed ($type)";
        if (!empty($emailEnabled)) :
            $mail = new MyPHPMailer(true, $appConfig);
            $mailresult = $mail->sendEmail($adminEmail, false, $subject, $bulkResultMessage);
            $msg->logMessage('[DEBUG]', "Mail result is '$mailresult'");
        else :
            $msg->logMessage(
                '[NOTICE]',
                "Email disabled; scryfall_bulk alert not sent for $type"
            );
        endif;
        if (PHP_SAPI === 'cli') :
            echo "Scryfall Bulk API: $subject, $bulkResultMessage\n";
        endif;
    endif;
endif;
exit(0);
