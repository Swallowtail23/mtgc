<?php

/*
Version:     1.7
Date:        21/12/25
Name:        ajaxcview.php
Purpose:     PHP script to turn ajax collection view on/off
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
$msg = new \MTG\Core\Message($logfile);

// Check if the request is coming from valid page
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages =   [
                                $myURL . '/index.php',
                                $myURL . '/profile.php'
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
        $sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
        $userArray = $sessionManager->getUserInfo();
        $user = $userArray['usernumber'];
        $mytable = $userArray['table'];
        $userEmail = $_SESSION['useremail'];

        if (isset($_POST['collection_view']) && $_POST['collection_view'] === 'TURN OFF') :
            $msg->logMessage('[ERROR]', "Call to turn off collection view");
            $query = "UPDATE users SET collection_view = ? WHERE usernumber = ?";
            $params = ['0', $user];
            $result = $db->execute_query($query, $params);
            if ($result === false) :
                throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
            else :
                $msg->logMessage('[ERROR]', "Call to turn off collection view run for $userEmail");
            endif;
        elseif (isset($_POST['collection_view']) && $_POST['collection_view'] === 'TURN ON') :
            $msg->logMessage('[ERROR]', "Call to turn on collection view");
            $query = "UPDATE users SET collection_view = ? WHERE usernumber = ?";
            $params = ['1', $user];
            $result = $db->execute_query($query, $params);
            if ($result === false) :
                throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
            else :
                $msg->logMessage('[ERROR]', "Call to turn on collection view run for $userEmail");
            endif;
        else :
            http_response_code(400);
            $msg->logMessage('[ERROR]', "Called with invalid input");
            echo json_encode(['error' => 'Called with invalid input']);
            exit();
        endif;
    endif;
else :
    //Otherwise forbid access
    $msg->logMessage('[ERROR]', "Not called from valid page");
    http_response_code(403);
    echo 'Access forbidden';
endif;
