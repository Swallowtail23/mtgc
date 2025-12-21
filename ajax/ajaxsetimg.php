<?php

/*
Version:     1.3
Date:        25/11/25
Name:        ajaxsetimg.php
Purpose:     Trigger reload all images for a set
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:      -
*/

if (file_exists('../includes/sessionname.local.php')) :
    require('../includes/sessionname.local.php');
else :
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require('../includes/ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
include '../includes/colour.php';
$msg = new \MTG\Core\Message($logfile);

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    header("Refresh: 2; url=login.php"); // redirect if not logged in
    // Return an error in JSON format
    echo json_encode(["status" => "error", "message" => "You are not logged in."]);
    exit();
else :
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) :
        $msg->logMessage('[ERROR]', "Invalid CSRF token for ajaxsetimg");
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid request token"]);
        exit();
    endif;

    // Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];

    if (isset($_POST['setcode'])) :
        $setcode = $_POST['setcode'];
        if (!is_string($setcode) || !preg_match('/^[A-Za-z0-9_]+$/', $setcode)) :
            $msg->logMessage('[ERROR]', "Invalid setcode supplied: '$setcode'");
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid set code supplied"]);
            exit();
        endif;
        $root = $_SERVER['DOCUMENT_ROOT'];
        $msg->logMessage('[NOTICE]', "Called with set '$setcode'");
        $safeRoot = escapeshellarg($root . '/bulk/setimgreload.php');
        $safeSetcode = escapeshellarg($setcode);
        $cmd = "php $safeRoot $safeSetcode > /dev/null 2>&1 &";
        $msg->logMessage('[NOTICE]', "Running '$cmd'");
        exec($cmd);
        echo json_encode(
            [
                "status" => "success",
                "message" => "Image reloading started for set '$setcode' - result will be emailed to server admin"
            ]
        );
    else :
        $msg->logMessage('[ERROR]', "No setcode supplied");
        echo json_encode(["status" => "error", "message" => "No setcode supplied"]);
    endif;
endif;
