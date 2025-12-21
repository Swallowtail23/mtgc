<?php

/*
Version:     2.5
Date:        21/12/25
Name:        scryfall_rulings.php
Purpose:     Import/update Scryfall rulings data
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
History:     See git history / CHANGELOG.md
*/

require('bulk_ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
$msg = new Message($logfile);
ensureDirectoryExists($imgLocation . 'json');

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;

// How old to overwrite
$max_fileage = 23 * 3600;

// Scryfall rulings cards URL
$url = "https://api.scryfall.com/bulk-data/rulings";

// Bulk file store point
$file_location = $imgLocation . 'json/rulings.json';

// Set counts
$total_count = 0;

$msg->logMessage('[NOTICE]', "Scryfall Rulings API: fetching $url");
$options = array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
    CURLOPT_USERAGENT => "MtGCollection/1.0",
    CURLOPT_HTTPHEADER => array("Accept: application/json;q=0.9,*/*;q=0.8"),
    );
$ch = curl_init($url);
curl_setopt_array($ch, $options);
$curlresult = curl_exec($ch);
curl_close($ch);
$scryfall_rulings = json_decode($curlresult, true);
if (isset($scryfall_rulings["type"]) and $scryfall_rulings["type"] === "rulings") :
    $rulings_uri = $scryfall_rulings["download_uri"];
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
    $rulingreturn = downloadBulk($rulings_uri, $file_location, $msg, 'Scryfall rulings data download', false);
endif;
$msg->logMessage('[NOTICE]', "Scryfall Rulings API: Local file: $file_location");

$data = Items::fromFile($imgLocation . 'json/rulings.json', ['decoder' => new ExtJsonDecoder(true)]);
if ($result = $db->query('TRUNCATE TABLE rulings_scry')) :
    $msg->logMessage('[NOTICE]', "Scryfall Rulings API: Old rulings cleared");
else :
    throw new Exception('[ERROR] scryfall_rulings.php: Preparing SQL: ' . $db->error);
endif;
foreach ($data as $key => $value) :
    $oracle_id = $value["oracle_id"];
    $source = $value["source"];
    $published = $value["published_at"];
    $comment = $value["comment"];
    $stmt = $db->prepare("INSERT INTO
                            `rulings_scry`
                                (oracle_id, source, published_at, comment)
                            VALUES
                                (?,?,?,?)");
    if ($stmt === false) :
        throw new Exception('[ERROR] scryfall_rulings: Preparing SQL: ' . $db->error);
    endif;
    $stmt->bind_param(
        "ssss",
        $oracle_id,
        $source,
        $published,
        $comment
    );
    if ($stmt === false) :
        throw new Exception('[ERROR] scryfall_rulings: Binding parameters: ' . $db->error);
    endif;
    if (!$stmt->execute()) :
        throw new Exception("[ERROR] scryfall_rulings: Writing new ruling details: " . $db->error);
    else :
        $msg->logMessage('[DEBUG]', "Add ruling $total_count - no error returned ");
        $total_count = $total_count + 1;
    endif;
    $stmt->close();
endforeach;
$msg->logMessage('[NOTICE]', "$total_count bulk rulings completed");
if (php_sapi_name() == 'cli') :
    echo "Rulings: $total_count bulk rulings completed\n";
endif;

// Email results
$subject = "MTG rulings update completed";
$body = "Total rulings: $total_count";
if (isset($emailEnabled) && $emailEnabled === true) :
    $mail = new MyPHPMailer(true, $smtpParameters, $serverEmail, $logfile);
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
