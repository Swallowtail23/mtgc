<?php

/*
Version:     5.18
Date:        11/01/26
Name:        ajaxgrid.php
Purpose:     Processes updates from Grid/Bulk views of index.php
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\PriceManager;
use MTG\Core\Message;
use MTG\Core\Validation;
use MTG\Core\Http\AjaxResponse;

// Bootstrap
$appContext = require '../bootstrap.php';

// Content
$msg->logMessage('[DEBUG]', "Ajax grid update called");

$expectedReferringPages = [
    $myURL . '/index.php',
    $myURL . '/carddetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxgrid.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        AjaxResponse::json(['error' => 'Invalid request token'], 403);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from valid page");
        AjaxResponse::text('Access forbidden', 403);
    endif;
endif;

$msg->logMessage('[DEBUG]', "Ajax grid update, referrer is valid");
if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    AjaxResponse::text("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];
    $priceMgr = new PriceManager($db, $appConfig, $userEmail);
    $msg->logMessage('[DEBUG]', "Ajax grid update user context loaded");

    $cardId = $_POST['cardid'] ?? '';
    if (Validation::validUUID($cardId, $appConfig) === false) :
        $msg->logMessage('[ERROR]', "User $userEmail({$_SERVER['REMOTE_ADDR']}) Called with invalid card UUID");
        $response['status'] = 'error';
        $response['message'] = "Called with invalid card UUID";
        AjaxResponse::json($response, 400);
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
            AjaxResponse::json($response, 400);
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
            AjaxResponse::json($response, 400);
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
            AjaxResponse::json($response, 400);
        endif;
    else :
        $msg->logMessage('[DEBUG]', "No quantity arguments provided in request");
        $msg->logMessage('[ERROR]', "User $userEmail({$_SERVER['REMOTE_ADDR']}) called with no arguments");
        $response['status'] = 'error';
        $response['message'] = "Invalid call";
        AjaxResponse::json($response, 400);
    endif;

        //Should only be here if newqty, newfoil or newetch are set
        //Set up variables
    if (is_numeric($qty) && (int)$qty == $qty) :
        $sqlqty = (int)$qty;
    else :
            $response['status'] = 'error';
            $response['message'] = "Invalid qty";
            AjaxResponse::json($response, 400);
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
        AjaxResponse::json($response, 400);
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
        AjaxResponse::json($response, 400);
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
        AjaxResponse::json($response, 400);
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
        AjaxResponse::json($response, http_response_code());
endif;
