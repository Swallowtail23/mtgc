<?php

/*
Version:     1.3
Date:        20/12/25
Name:        ajaxcollectionhistory.php
Purpose:     Return collection value history for charting.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (file_exists('../includes/sessionname.local.php')) :
    require '../includes/sessionname.local.php';
else :
    require '../includes/sessionname_template.php';
endif;
startCustomSession();
require '../includes/ini.php';
require '../includes/error_handling.php';
require '../includes/functions.php';

$msg = new \MTG\Core\Message($logfile);
$msg->logMessage('[DEBUG]', 'ajaxcollectionhistory.php: start');

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $msg->logMessage('[ERROR]', 'ajaxcollectionhistory.php: not authenticated');
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
endif;

$userId = (int) $_SESSION['user'];

$range = isset($_GET['range']) ? trim($_GET['range']) : '30d';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';
if (!in_array($format, ['json', 'csv'], true)) :
    $msg->logMessage('[DEBUG]', "ajaxcollectionhistory.php: invalid format '$format', defaulting to json");
    $format = 'json';
endif;

$msg->logMessage('[DEBUG]', "ajaxcollectionhistory.php: user {$userId}, range {$range}, format {$format}");
$history = new CollectionHistory($db, $logfile, $siteTitle);
$data = $history->getHistoryData($userId, $range);
if ($data === false) :
    $msg->logMessage('[ERROR]', 'ajaxcollectionhistory.php: unable to fetch history');
    http_response_code(500);
    echo json_encode(['error' => 'Query failed']);
    exit();
endif;

if ($format === 'csv') :
    $csv = $history->buildCsv($data);
    if ($csv === '') :
        $msg->logMessage('[ERROR]', 'ajaxcollectionhistory.php: CSV build failed');
        http_response_code(500);
        echo json_encode(['error' => 'CSV build failed']);
        exit();
    endif;

    $filename = "value_history_{$range}.csv";
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Length: " . strlen($csv));
    header("Content-type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=$filename");
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo $csv;
    exit();
endif;

$msg->logMessage('[DEBUG]', "ajaxcollectionhistory.php: returned " . count($data) . " rows");
header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $data]);
exit();
