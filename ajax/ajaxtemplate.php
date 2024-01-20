<?php 
/* Version:     1.1
    Date:       20/01/24
    Name:       ajaxtemplate.php
    Purpose:    PHP script to...
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
 
    1.1         20/01/24
 *              Include sessionname.php and move to logMessage
*/

require ('../includes/sessionname.php');
startCustomSession();
require ('../includes/ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');
include '../includes/colour.php';
$msg = new Message($logfile);

// Check if the request is coming from valid page
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages =   [
                                $myURL . '/sets.php',
                                $myURL . '/index.php'
                            ];

// Normalize the referring page URL
$normalizedReferringPage = str_replace('www.', '', $referringPage);

$isValidReferrer = false;
foreach ($expectedReferringPages as $page):
    // Normalize each expected referring page URL
    $normalizedPage = str_replace('www.', '', $page);
    if (strpos($normalizedReferringPage, $normalizedPage) !== false):
        $isValidReferrer = true;
        break;
    endif;
endforeach;

if ($isValidReferrer):

    if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
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

            $msg->logMessage('[DEBUG]',"Called with filter '$filter', setsPerPage '$setsPerPage', offset '$offset'");

        else:  // Error handling
            http_response_code(400);
            $msg->logMessage('[ERROR]',"Offset not in range");
            echo json_encode(['error' => 'Offset not in range']);
            exit();
        endif;
    endif;
else:
    //Otherwise forbid access
    $msg->logMessage('[ERROR]',"Not called from valid page");
    http_response_code(403);
    echo 'Access forbidden';
endif;
?>