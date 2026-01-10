<?php

/*
Version:     5.11
Date:        10/01/26
Name:        ajaxgrid.php
Purpose:     Processes updates from Grid/Bulk views of index.php
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (file_exists('../includes/sessionname.local.php')) :
    require('../includes/sessionname.local.php');
else :
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require('../includes/ini.php');                //Initialise and load ini file
require('../includes/error_handling.php');
require('../includes/functions.php');      //Includes basic functions for non-secure pages
require('../includes/secpagesetup.php');       //Setup page variables
include '../includes/colour.php';
$msg = new \MTG\Core\Message($logfile);
$priceMgr = new \MTG\Cards\PriceManager($db, $logfile, $userEmail);
$msg->logMessage('[DEBUG]', "Ajax grid update called");

$expectedReferringPages = [
    $myURL . '/index.php',
    $myURL . '/carddetail.php'
];
$ajaxValidation = \MTG\Auth\SessionManager::validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxgrid.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        ajaxRespondJson(['error' => 'Invalid request token'], 403);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from valid page");
        ajaxRespondText('Access forbidden', 403);
    endif;
endif;

$msg->logMessage('[DEBUG]', "Ajax grid update, referrer is valid");
if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    ajaxRespondText("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
else :
    $cardId = $_POST['cardid'] ?? '';
    if (validUUID($cardId) === false) :
        $msg->logMessage('[ERROR]', "User $userEmail({$_SERVER['REMOTE_ADDR']}) Called with invalid card UUID");
        $response['status'] = 'error';
        $response['message'] = "Called with invalid card UUID";
        ajaxRespondJson($response, 400);
    endif;

    //Process and log new quantity request
    if (isset($_POST['newqty'])) :
        $msg->logMessage('[DEBUG]', "Processing normal quantity update");
        $qtyInput = $_POST['newqty'];
        $qty = filter_var($qtyInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($qty !== false) :
            $msg->logMessage(
                '[NOTICE]',
                "User $userEmail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardId, request: Normal:$qty"
            );
        else :
            $msg->logMessage(
                '[ERROR]',
                "User $userEmail({$_SERVER['REMOTE_ADDR']}) invalid qty $qtyInput passed for normal $cardId"
            );
            $response['status'] = 'error';
            $response['message'] = "Invalid normal qty";
            ajaxRespondJson($response, 400);
        endif;
    elseif (isset($_POST['newfoil'])) :
        $msg->logMessage('[DEBUG]', "Processing foil quantity update");
        $qtyInput = $_POST['newfoil'];
        $qty = filter_var($qtyInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($qty !== false) :
            $msg->logMessage(
                '[NOTICE]',
                "User $userEmail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardId, request: Foil:$qty"
            );
        else :
            $msg->logMessage(
                '[ERROR]',
                "User $userEmail({$_SERVER['REMOTE_ADDR']}) invalid qty $qtyInput passed for foil $cardId"
            );
            $response['status'] = 'error';
            $response['message'] = "Invalid foil qty";
            ajaxRespondJson($response, 400);
        endif;
    elseif (isset($_POST['newetch'])) :
        $msg->logMessage('[DEBUG]', "Processing etched quantity update");
        $qtyInput = $_POST['newetch'];
        $qty = filter_var($qtyInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($qty !== false) :
            $msg->logMessage(
                '[NOTICE]',
                "User $userEmail({$_SERVER['REMOTE_ADDR']}) Qty update request for $cardId, request: Etched:$qty"
            );
        else :
            $msg->logMessage(
                '[ERROR]',
                "User $userEmail({$_SERVER['REMOTE_ADDR']}) invalid qty $qtyInput passed for etched $cardId"
            );
            $response['status'] = 'error';
            $response['message'] = "Invalid etch qty";
            ajaxRespondJson($response, 400);
        endif;
    else :
        $msg->logMessage('[DEBUG]', "No quantity arguments provided in request");
        $msg->logMessage('[ERROR]', "User $userEmail({$_SERVER['REMOTE_ADDR']}) called with no arguments");
        $response['status'] = 'error';
        $response['message'] = "Invalid call";
        ajaxRespondJson($response, 400);
    endif;

        //Should only be here if newqty, newfoil or newetch are set
        //Set up variables
    if (is_numeric($qty) && (int)$qty == $qty) :
        $sqlqty = (int)$qty;
    else :
            $response['status'] = 'error';
            $response['message'] = "Invalid qty";
            ajaxRespondJson($response, 400);
    endif;

        $sqlid = $cardId;

        //Check existing quantity
        $beforeresultqry = $db->execute_query(
            "SELECT normal, foil, etched FROM $mytable WHERE id = ? LIMIT 1",
            [$sqlid]
        );
    if ($beforeresultqry === false) :
        $msg->logMessage('[ERROR]', "User $userEmail({$_SERVER['REMOTE_ADDR']}) Unable to get 'before' values");
        $response['status'] = 'error';
        $response['message'] = "SQL update error: $db->error";
        ajaxRespondJson($response, 400);
    else :
            $beforeresult = $beforeresultqry->fetch_assoc();
        if (empty($beforeresult['normal'])) :
            $myqty = 0;
        else :
                $myqty = $beforeresult['normal'];
        endif;
        if (empty($beforeresult['foil'])) :
            $myfoil = 0;
        else :
                $myfoil = $beforeresult['foil'];
        endif;
        if (empty($beforeresult['etched'])) :
            $myetch = 0;
        else :
                $myetch = $beforeresult['etched'];
        endif;
            $msg->logMessage(
                '[NOTICE]',
                "User $userEmail({$_SERVER['REMOTE_ADDR']}) Qty update for $sqlid, prior values: Normal:$myqty, "
                . "Foil:$myfoil, Etched:$myetch"
            );
    endif;
        // Run update
    if (isset($_POST['newqty'])) :
        $updatequery = "
                INSERT INTO $mytable (normal,id)
                VALUES (?,?)
                ON DUPLICATE KEY UPDATE
                normal = ?";
    elseif (isset($_POST['newfoil'])) :
            $updatequery = "
                INSERT INTO $mytable (foil,id)
                VALUES (?,?)
                ON DUPLICATE KEY UPDATE
                foil = ?";
    elseif (isset($_POST['newetch'])) :
            $updatequery = "
                INSERT INTO $mytable (etched,id)
                VALUES (?,?)
                ON DUPLICATE KEY UPDATE
                etched = ?";
    endif;
        $params = [$sqlqty, $sqlid, $sqlqty];
        $sqlupdate = $db->execute_query($updatequery, $params);
    if ($sqlupdate === false) :
        $msg->logMessage('[ERROR]', "User $userEmail({$_SERVER['REMOTE_ADDR']}) Unable to update: $db->error");
        $response['status'] = 'error';
        $response['message'] = "SQL update error: $db->error";
        ajaxRespondJson($response, 400);
    else :
            $affected_rows = $db->affected_rows;
        if ($affected_rows === 2) :
            $msg->logMessage('[DEBUG]', "Update query run for $sqlid, existing entry updated");
        elseif ($affected_rows === 1) :
                $msg->logMessage('[DEBUG]', "Update query run for $sqlid, new row inserted");
        endif;
    endif;
        // Update topvalue
        $msg->logMessage('[DEBUG]', "Updating topvalue based on new quantities");
        $priceMgr->updateCollectionValues($mytable, $sqlid);

        // Retrieve new record to display
        $response = [];

        $checkresultqry = $db->execute_query(
            "SELECT normal, foil, etched FROM $mytable WHERE id = ? LIMIT 1",
            [$sqlid]
        );
    if ($checkresultqry === false) :
        $msg->logMessage('[ERROR]', "User $userEmail({$_SERVER['REMOTE_ADDR']}) Unable to update: $db->error");
        $response['status'] = 'error';
        $response['message'] = "SQL update error: $db->error";
        ajaxRespondJson($response, 400);
    else :
            $checkresult = $checkresultqry->fetch_assoc();
        if (isset($_POST['newqty'])) :
            if ((int)$sqlqty === (int)$checkresult['normal']) :
                $msg->logMessage(
                    '[NOTICE]',
                    "User $userEmail({$_SERVER['REMOTE_ADDR']}) Qty update completed for $sqlid, "
                    . "new value: Normal:{$checkresult['normal']}"
                );
                $response['status'] = 'success';
                $response['message'] = "Qty update completed for $sqlid, new value: Normal:"
                    . "{$checkresult['normal']}";
            else :
                $msg->logMessage(
                    '[ERROR]',
                    "User $userEmail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, "
                    . "new value: Normal:{$checkresult['normal']}"
                );
                $response['status'] = 'error';
                $response['message'] = "Grid check FAIL for $sqlid, new value: Normal:{$checkresult['normal']}";
                http_response_code(400);
            endif;
        elseif (isset($_POST['newfoil'])) :
            if ((int)$sqlqty === (int)$checkresult['foil']) :
                $msg->logMessage(
                    '[NOTICE]',
                    "User $userEmail({$_SERVER['REMOTE_ADDR']}) Grid check completed for $sqlid, "
                    . "new value: Foil: {$checkresult['foil']}"
                );
                $response['status'] = 'success';
                $response['message'] = "Grid check completed for $sqlid, new value: Foil: {$checkresult['foil']}";
            else :
                    $msg->logMessage(
                        '[ERROR]',
                        "User $userEmail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, "
                        . "new value: Foil: {$checkresult['foil']}"
                    );
                    $response['status'] = 'error';
                    $response['message'] = "Grid check FAIL for $sqlid, new value: Foil: {$checkresult['foil']}";
                    http_response_code(400);
            endif;
        elseif (isset($_POST['newetch'])) :
            if ((int)$sqlqty === (int)$checkresult['etched']) :
                $msg->logMessage(
                    '[NOTICE]',
                    "User $userEmail({$_SERVER['REMOTE_ADDR']}) Grid check completed for $sqlid, "
                    . "new value: Etched: {$checkresult['etched']}"
                );
                $response['status'] = 'success';
                $response['message'] = "Grid check completed for $sqlid, new value: Etched: "
                    . "{$checkresult['etched']}";
            else :
                    $msg->logMessage(
                        '[ERROR]',
                        "User $userEmail({$_SERVER['REMOTE_ADDR']}) Grid check FAIL for $sqlid, "
                        . "new value: Etched: {$checkresult['etched']}"
                    );
                    $response['status'] = 'error';
                    $response['message'] = "Grid check FAIL for $sqlid, new value: Etched: {$checkresult['etched']}";
                    http_response_code(400);
            endif;
        endif;
    endif;

        // Send JSON response
        ajaxRespondJson($response, http_response_code());
endif;
