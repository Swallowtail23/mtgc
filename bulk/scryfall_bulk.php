<?php

/*
Version:     9.1
Date:        19/12/25
Name:        scryfall_bulk.php
Purpose:     Import/update Scryfall bulk data
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

require('bulk_ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
$msg = new Message($logfile);

// Start time tracking
$start = microtime(true);

ensureDirectoryExists($imgLocation . 'json');

// Get and interpret parameter 1

/// Call without parameters does a 'default' file update only
/// Call with 'all' gets the all cards file
/// Call with 'refresh' gets fresh copies of BOTH files (run by docker install for initial setup)

$arg1 = $argv[1] ?? '';
$arg1 = strtolower(trim($arg1));

if ($arg1 === 'all') :
    $type = 'all';
elseif ($arg1 === 'refresh') :
    $type = 'refresh';
else :
    $type = 'default';
endif;

// Get info on required files to download and their local locations
$bulkInfo = getBulkInfo($type);
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
    $get_all = getBulkJson($bulkUrlAll, $fileLocationAll, $maxFileAge);
    $get_default = getBulkJson($bulkUrlDefault, $fileLocationDefault, $maxFileAge);
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
        $bulkResultAll = scryfallImport($fileLocationAll, 'all');
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
        $bulkResultDefault = scryfallImport($fileLocationDefault, 'default');
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
    $get_json = getBulkJson($bulkUrl, $fileLocation, $maxFileAge);
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
        $bulkResultMessage = scryfallImport($fileLocation, $type);
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
            $mail = new MyPHPMailer(true, $smtpParameters, $serverEmail, $logfile);
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