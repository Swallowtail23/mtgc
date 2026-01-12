<?php

/*
Version:     1.19
Date:        12/01/26
Name:        ajaxcollectionhistory.php
Purpose:     Return collection value history for charting.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\CollectionHistory;
use MTG\Core\Http\AjaxResponse;

// Bootstrap

$appContext = require dirname(__DIR__) . '/bootstrap.php';

// Content
$msg->logMessage('[DEBUG]', 'ajaxcollectionhistory.php: start');
$expectedReferringPages = [
    $myURL . '/collection.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest(
    $expectedReferringPages,
    $appConfig,
    'ajaxcollectionhistory.php'
);
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', 'ajaxcollectionhistory.php: Invalid CSRF token');
        AjaxResponse::json(['error' => 'Invalid request token'], 403);
    else :
        $msg->logMessage('[ERROR]', 'ajaxcollectionhistory.php: Not called from valid page');
        AjaxResponse::json(['error' => 'Access forbidden'], 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $msg->logMessage('[ERROR]', 'ajaxcollectionhistory.php: not authenticated');
    AjaxResponse::json(['error' => 'Not authenticated'], 401);
endif;

$userId = (int) $_SESSION['user'];

$range = isset($_GET['range']) ? trim($_GET['range']) : '30d';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';
if (!in_array($format, ['json', 'csv'], true)) :
    $msg->logMessage('[DEBUG]', "ajaxcollectionhistory.php: invalid format '$format', defaulting to json");
    $format = 'json';
endif;

$msg->logMessage('[DEBUG]', "ajaxcollectionhistory.php: user {$userId}, range {$range}, format {$format}");
$history = new CollectionHistory($db, $appConfig);
$data = $history->getHistoryData($userId, $range);
if ($data === false) :
    $msg->logMessage('[ERROR]', 'ajaxcollectionhistory.php: unable to fetch history');
    AjaxResponse::json(['error' => 'Query failed'], 500);
endif;

if ($format === 'csv') :
    $csv = $history->buildCsv($data);
    if ($csv === '') :
        $msg->logMessage('[ERROR]', 'ajaxcollectionhistory.php: CSV build failed');
        AjaxResponse::json(['error' => 'CSV build failed'], 500);
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
AjaxResponse::json(['success' => true, 'data' => $data]);
