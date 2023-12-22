<?php 
/* Version:     1.0
    Date:       19/10/23
    Name:       ajaxgroup.php
    Purpose:    PHP script to turn ajax group on/off
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
require ('../includes/functions.php');
include '../includes/colour.php';
$msg = new Message;

// Check if the request is coming from valid page
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages =   [
                                $myURL . '/profile.php'
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

        if(isset($_POST['group']) && $_POST['group'] === 'OPT OUT'):
            $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Call to opt out of groups",$logfile);
            $query = "UPDATE users SET grpinout = ? WHERE usernumber = ?";
            $params = ['0', $user];
            $result = $db->execute_query($query, $params);
            if($result === false):
                trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
            else:    
                $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Group opt-out run for $useremail",$logfile);
            endif;
        elseif(isset($_POST['group']) && $_POST['group'] === 'OPT IN'):
            $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Call to opt into groups",$logfile);
            $query = "UPDATE users SET grpinout = ? WHERE usernumber = ?";
            $params = ['1', $user];
            $result = $db->execute_query($query, $params);
            if($result === false):
                trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
            else:    
                $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Group opt-in run for $useremail",$logfile);
            endif;
        else:
            http_response_code(400);
            $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Called with invalid input", $logfile);
            echo json_encode(['error' => 'Called with invalid input']);
            exit();
        endif;
    endif;
else:
    //Otherwise forbid access
    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Not called from profile.php",$logfile);
    http_response_code(403);
    echo 'Access forbidden';
endif;
?>