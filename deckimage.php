<?php 
/* Version:     1.2
    Date:       20/01/24
    Name:       deckimage.php
    Purpose:    PHP script to get and output raw jpg
    Notes:      -
        
    1.0         04/12/23
                Initial version
                
    1.1         14/01/24
                Move session.name to include
 * 
 *  1.2         20/01/24
 *              Move to logMessage
 */

if (file_exists('includes/sessionname.local.php')):
    require('includes/sessionname.local.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');          //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
$msg = new Message($logfile);

$msg->logMessage('[DEBUG]',"Called to generate jpg...");

// Check if the request is coming from deckdetail.php
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPage = $myURL.'/deckdetail.php';
$referringPagePath = parse_url($referringPage, PHP_URL_PATH);
$expectedReferringPagePath = parse_url($expectedReferringPage, PHP_URL_PATH);

if ($referringPagePath === $expectedReferringPagePath):
    // Access is OK
    $msg->logMessage('[DEBUG]',"Called from deckdetail.php");

    if(isset($_GET['deck']) AND ($_GET['deck']) !== ''):
        $decknumber = filter_input(INPUT_GET, 'deck', FILTER_SANITIZE_SPECIAL_CHARS);
        $imageFilePath = $ImgLocation.'deck_photos/'.$decknumber.'.jpg';    // Filesystem path
        
        // Check if the file exists
        if (file_exists($imageFilePath)):
            // Output the image file
            header('Content-Type: image/jpeg');
            readfile($imageFilePath);
        else:
            http_response_code(404); 
            echo 'Image not found';
        endif;
    else:
        trigger_error("[ERROR] deckimage.php: Called with no parameters", E_USER_ERROR);
    endif;

else:
    //Otherwise forbid access
    $msg->logMessage('[ERROR]',"Not called from deckdetail.php (referring page is $referringPage, expectedreferringpage is $expectedReferringPage, referringpagepath is $referringPagePath, expectedreferringpagepath is $expectedReferringPagePath)");
    http_response_code(403);
    echo 'Access forbidden';
endif;
?>
