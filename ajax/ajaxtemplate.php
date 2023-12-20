<?php 
/* Version:     1.0
    Date:       12/12/23
    Name:       ajaxtemplate.php
    Purpose:    PHP script to...
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('../includes/ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');
include '../includes/colour.php';
$msg = new Message;

// Check if the request is coming from valid page(s)
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages = [
                            $myURL . '/index.php',
                            $myURL . '/profile.php'
                          ];

$isValidReferrer = false;
foreach ($expectedReferringPages as $page):
    if (strpos($referringPage, $page) !== false):
        $isValidReferrer = true;
        break;
    endif;
endforeach;
if ($isValidReferrer):

    if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
        echo "<table class='ajaxshow'><tr><td class='name'>You are not logged in.</td></tr></table>";
        echo "<meta http-equiv='refresh' content='2;url=/login.php'>";               // check if user is logged in; else redirect to login.php
        exit(); 
    else: 
        //Need to run these as secpagesetup not run (see page notes)
        $sessionManager = new SessionManager($db,$adminip,$_SESSION, $fxAPI, $fxLocal, $logfile);
        $userArray = $sessionManager->getUserInfo();
        $user = $userArray['usernumber'];
        $mytable = $userArray['table'];
        $useremail = $_SESSION['useremail'];

        if (isset($_GET['filter'], $_GET['setsPerPage'], $_GET['offset']) ):  //Update GET details
            $filter = $_GET['filter'];
            $setsPerPage = intval($_GET['setsPerPage']);
            $offset = intval($_GET['offset']);

            $msg->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Called with filter '$filter', setsPerPage '$setsPerPage', offset '$offset'", $logfile);

        else:  // Error handling
            http_response_code(400);
            $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Offset not in range", $logfile);
            echo json_encode(['error' => 'Offset not in range']);
            exit();
        endif;
    endif;
else:
    //Otherwise forbid access
    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Not called from valid page",$logfile);
    http_response_code(403);
    echo 'Access forbidden';
endif;
?>