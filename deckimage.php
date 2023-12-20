<?php 
/* Version:     1.0
    Date:       04/12/23
    Name:       deckimage.php
    Purpose:    PHP script to get and output raw jpg
    Notes:      -
        
    1.0
                Initial version
 */
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');   //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
$msg = new Message;

$msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Called to generate jpg...",$logfile);

// Check if the request is coming from deckdetail.php
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPage = $myURL.'/deckdetail.php';
if (strpos($referringPage, $expectedReferringPage) !== false):
    
    // Access is OK
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Called from deckdetail.php",$logfile);

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
    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Not called from deckdetail.php",$logfile);
    http_response_code(403);
    echo 'Access forbidden';
endif;
?>
