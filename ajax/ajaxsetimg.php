<?php
/* Version:     1.1
    Date:       20/01/24
    Name:       ajax/ajaxsetimg.php
    Purpose:    Trigger reload all images for a set
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
 
    1.1         20/01/24
 *              Include sessionname.php and move to logMessage
*/

if (file_exists('../includes/sessionname.php')):
    require('../includes/sessionname.php');
else:
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require ('../includes/ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');
include '../includes/colour.php';
$msg = new Message($logfile);

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
    // Return an error in JSON format
    echo json_encode(["status" => "error", "message" => "You are not logged in."]);
    header("Refresh: 2; url=login.php"); // check if the user is logged in; else redirect to login.php
    exit(); 
else: 
    // Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $useremail = $_SESSION['useremail'];
    
    if (isset($_POST['setcode'])):
        $setcode = $_POST['setcode'];
        $root = $_SERVER['DOCUMENT_ROOT'];
        $msg->logMessage('[NOTICE]',"Called with set '$setcode'");
        $cmd = "php $root/bulk/setimgreload.php '$setcode'> /dev/null 2>&1 &";
        $msg->logMessage('[NOTICE]',"Running '$cmd'");
        exec($cmd);
        echo json_encode(["status" => "success", "message" => "Image reloading started for set '$setcode' - result will be emailed to server admin"]);
    else:
        $msg->logMessage('[ERROR]',"No setcode supplied");
        echo json_encode(["status" => "error", "message" => "No setcode supplied"]);
    endif;
endif;
?>