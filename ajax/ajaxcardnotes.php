<?php

/*
Version:     1.3
Date:        21/12/25
Name:        ajaxcardnotes.php
Purpose:     PHP script to save card notes
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
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
require('../includes/ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
include '../includes/colour.php';
$msg = new Message($logfile);

// Check if the request is coming from valid page
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages =   [
                                $myURL . '/carddetail.php'
                            ];

// Normalize the referring page URL
$normalizedReferringPage = str_replace('www.', '', $referringPage);

$isValidReferrer = false;
foreach ($expectedReferringPages as $page) :
    // Normalize each expected referring page URL
    $normalizedPage = str_replace('www.', '', $page);
    if (strpos($normalizedReferringPage, $normalizedPage) !== false) :
        $isValidReferrer = true;
        break;
    endif;
endforeach;

if ($isValidReferrer) :
    if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
        echo "<meta http-equiv='refresh' content='2;url=/login.php'>"; // redirect if not logged in
        exit();
    else :
        //Need to run these as secpagesetup not run (see page notes)
        $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
        $userArray = $sessionManager->getUserInfo();
        $user = $userArray['usernumber'];
        $mytable = $userArray['table'];
        $userEmail = $_SESSION['useremail'];
        $newnotes = isset($_POST['newnotes']) ? trim($_POST['newnotes']) : '';
        $cardUUID = isset($_POST['cardid']) ? validUUID($_POST['cardid']) : false;

        if ($cardUUID === false) :
            $msg->logMessage('[ERROR]', "Invalid UUID provided");
            http_response_code(400);
            echo json_encode(['error' => 'Invalid UUID provided']);
            exit();
        endif;

        $msg->logMessage('[NOTICE]', "Called with: Notes: $newnotes, Card ID: $cardUUID");

        try {
            $query = "INSERT INTO `$mytable` (notes,id)
                        VALUES (?,?)
                        ON DUPLICATE KEY UPDATE notes = ? ";
            $result = $db->execute_query($query, [$newnotes, $cardUUID, $newnotes]);

            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'No rows updated or SQL error occurred']);
            }
        } catch (Exception $e) {
            throw new Exception(
                "[ERROR] ajaxcardnotes.php: " . $e->getMessage() . " SQLSTATE: " . $db->error
            );
            http_response_code(400);
            echo json_encode(['error' => 'Database error']);
        }
    endif;
else :
    //Otherwise forbid access
    $msg->logMessage('[ERROR]', "Not called from valid page");
    http_response_code(403);
    echo 'Access forbidden';
endif;
